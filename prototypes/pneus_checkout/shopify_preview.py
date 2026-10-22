"""Shopify draft calculation adapter. Does not create orders or enable checkout.

Input must come from server-side pricing.calculate(), never from a browser.
This module deliberately contains no credentials, HTTP client or write operation.
"""
import re
from decimal import Decimal, InvalidOperation

CALCULATE_OPERATION = """mutation PreviewPneus($input: DraftOrderInput!) {
  draftOrderCalculate(input: $input) {
    calculatedDraftOrder {
      taxesIncluded
      totalPriceSet { shopMoney { amount currencyCode } }
      totalTaxSet { shopMoney { amount currencyCode } }
    }
    userErrors { field message }
  }
}"""


def money(cents):
    if type(cents) is not int or cents < 0:
        raise ValueError("invalid_money")
    return f"{cents // 100}.{cents % 100:02d}"


def calculation_input(preview, *, quote_id):
    """Build a tax calculation, with no customer identity or payable link."""
    if not isinstance(quote_id, str) or not re.fullmatch(r"[a-f0-9]{32}", quote_id):
        raise ValueError("opaque_quote_id_required")
    if preview.get("currency") != "EUR" or preview.get("taxes_included") is not True:
        raise ValueError("eur_ttc_required")
    lines = preview.get("lines")
    if not isinstance(lines, list) or not lines or len(lines) > 6:
        raise ValueError("invalid_lines")
    items, total, kinds = [], 0, set()
    for line in lines:
        kind = line.get("kind")
        if kind not in {"tyres", "fitting", "valves", "recycling", "storage", "fees"} or kind in kinds:
            raise ValueError("invalid_line_kind")
        kinds.add(kind)
        q, unit = line.get("quantity"), line.get("unit_ttc_cents")
        if type(q) is not int or not 1 <= q <= 24:
            raise ValueError("invalid_quantity")
        amount = money(unit)
        if type(line.get("total_ttc_cents")) is not int or line["total_ttc_cents"] != q * unit:
            raise ValueError("inconsistent_line_total")
        label = line.get("label")
        if not isinstance(label, str) or not label.strip() or len(label) > 200:
            raise ValueError("invalid_label")
        items.append({"title": label, "quantity": q,
                      "originalUnitPriceWithCurrency": {"amount": amount, "currencyCode": "EUR"},
                      "taxable": True, "requiresShipping": False})
        total += q * unit
    if "tyres" not in kinds or type(preview.get("total_ttc_cents")) is not int or total != preview["total_ttc_cents"]:
        raise ValueError("inconsistent_quote_total")
    workshop = kinds & {"fitting", "valves", "recycling"}
    if workshop and workshop != {"fitting", "valves", "recycling"}:
        raise ValueError("incomplete_fitting_bundle")
    tyre_qty = next(x["quantity"] for x in lines if x["kind"] == "tyres")
    if any(x["quantity"] != tyre_qty for x in lines if x["kind"] in workshop):
        raise ValueError("fitting_quantity_mismatch")
    # France is a tax calculation context only. Fulfilment/delivery remains a
    # separate release gate; requiresShipping=False must not enable pickup.
    return {"lineItems": items, "shippingAddress": {"countryCode": "FR"},
            "presentmentCurrencyCode": "EUR", "taxExempt": False,
            "acceptAutomaticDiscounts": False, "allowDiscountCodesInCheckout": False,
            "tags": ["VP_TEST_PNEUS", "NE_PAS_TRAITER"],
            "customAttributes": [{"key": "vp_quote_id", "value": quote_id}]}


def verify_calculation(response, *, expected_cents):
    """Refuse divergent prices, taxes or currency; success never enables payment."""
    money(expected_cents)
    if response.get("errors"):
        raise ValueError("shopify_graphql_error")
    result = response.get("data", {}).get("draftOrderCalculate")
    if not isinstance(result, dict) or result.get("userErrors") != []:
        raise ValueError("shopify_calculation_error")
    draft = result.get("calculatedDraftOrder") or {}
    if draft.get("taxesIncluded") is not True:
        raise ValueError("tax_basis_mismatch")
    try:
        total = draft["totalPriceSet"]["shopMoney"]
        tax = draft["totalTaxSet"]["shopMoney"]
        if total["currencyCode"] != "EUR" or tax["currencyCode"] != "EUR":
            raise ValueError("currency_mismatch")
        amount, tax_amount = Decimal(total["amount"]), Decimal(tax["amount"])
        if not amount.is_finite() or not tax_amount.is_finite():
            raise ValueError("invalid_shopify_amount")
        if amount != Decimal(expected_cents) / 100 or not 0 <= tax_amount <= amount:
            raise ValueError("shopify_total_mismatch")
    except (KeyError, TypeError, InvalidOperation) as exc:
        raise ValueError("invalid_shopify_response") from exc
    return {"calculation_matches": True, "total_ttc_cents": expected_cents,
            "payment_enabled": False, "supplier_order_created": False}
