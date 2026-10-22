"""Server-side EUR/TTC quote calculation. No supplier or Shopify connector yet.

The application must resolve an opaque offer ID using an authorized supplier
adapter. Never construct VerifiedOffer from prices/stock supplied by a browser.
No quote produced here is a reservation, payment, order, or checkout URL.
"""
from dataclasses import dataclass
from datetime import datetime, timedelta


def integer(value, name, minimum=0):
    if type(value) is not int or value < minimum:
        raise ValueError(name)
    return value


def aware(value):
    if not isinstance(value, datetime) or value.tzinfo is None or value.utcoffset() is None:
        raise ValueError("timezone_required")


@dataclass(frozen=True)
class VerifiedOffer:
    supplier: str
    reference: str
    title: str
    unit_ttc_cents: int
    available: int
    rim_inches: int
    checked_at: datetime
    expires_at: datetime


@dataclass(frozen=True)
class Tariffs:
    # Explicit server configuration, not values submitted by the customer.
    fitting_13_16_cents: int
    fitting_17_18_cents: int
    fitting_19_plus_cents: int
    storage_pair_cents: int
    storage_period: str
    other_required_fees_cents: int  # Resolved delivery/other fees; exclude workshop lines below.
    required_fees_label: str
    max_offer_age_seconds: int = 300
    valve_unit_ttc_cents: int = 300
    recycling_unit_ttc_cents: int = 250


def calculate(offer, tariffs, *, quantity, fitting=False, storage_pairs=0, now):
    """Return an itemized preview; revalidate supplier and Shopify before payment.

    Taxes are already included. No float arithmetic or inferred TVA calculation.
    Storage counts pairs explicitly and is independent of tyre purchase quantity.
    Required fees are the resolved total for this particular selection, not a rate.
    """
    aware(now)
    aware(offer.checked_at)
    aware(offer.expires_at)
    integer(quantity, "quantity", 1)
    integer(storage_pairs, "storage_pairs")
    if quantity > 24 or storage_pairs > 12 or type(fitting) is not bool:
        raise ValueError("invalid_options")
    for name in ("unit_ttc_cents", "available", "rim_inches"):
        integer(getattr(offer, name), name, 1 if name != "available" else 0)
    for name in ("fitting_13_16_cents", "fitting_17_18_cents", "fitting_19_plus_cents",
                 "storage_pair_cents", "other_required_fees_cents",
                 "valve_unit_ttc_cents", "recycling_unit_ttc_cents"):
        integer(getattr(tariffs, name), name)
    integer(tariffs.max_offer_age_seconds, "max_offer_age_seconds", 1)
    if not all(isinstance(s, str) and s.strip() for s in
               (offer.supplier, offer.reference, offer.title)):
        raise ValueError("missing_reference")
    if (offer.checked_at > now or offer.expires_at <= now or
        offer.expires_at <= offer.checked_at or
        now - offer.checked_at > timedelta(seconds=tariffs.max_offer_age_seconds)):
        raise ValueError("offer_expired_recheck_supplier")
    if quantity > offer.available:
        raise ValueError("insufficient_stock")
    lines = [{"kind": "tyres", "label": offer.title, "reference": offer.reference,
              "quantity": quantity, "unit_ttc_cents": offer.unit_ttc_cents}]
    if fitting:
        if offer.rim_inches < 13:
            raise ValueError("fitting_tariff_unavailable")
        rate = (tariffs.fitting_13_16_cents if offer.rim_inches <= 16 else
                tariffs.fitting_17_18_cents if offer.rim_inches <= 18 else
                tariffs.fitting_19_plus_cents)
        if rate == 0:
            raise ValueError("fitting_tariff_unavailable")
        lines.append({"kind": "fitting", "label": "Montage et équilibrage",
                      "quantity": quantity, "unit_ttc_cents": rate})
        lines.append({"kind": "valves", "label": "Valve standard",
                      "quantity": quantity, "unit_ttc_cents": tariffs.valve_unit_ttc_cents})
        lines.append({"kind": "recycling", "label": "Recyclage du pneu usagé",
                      "quantity": quantity, "unit_ttc_cents": tariffs.recycling_unit_ttc_cents})
    if storage_pairs:
        if not tariffs.storage_period.strip() or tariffs.storage_pair_cents == 0:
            raise ValueError("storage_tariff_unavailable")
        lines.append({"kind": "storage", "label": "Gardiennage par paire — " + tariffs.storage_period,
                      "quantity": storage_pairs, "unit_ttc_cents": tariffs.storage_pair_cents})
    if tariffs.other_required_fees_cents:
        if not tariffs.required_fees_label.strip():
            raise ValueError("fees_label_required")
        lines.append({"kind": "fees", "label": tariffs.required_fees_label,
                      "quantity": 1, "unit_ttc_cents": tariffs.other_required_fees_cents})
    for line in lines:
        line["total_ttc_cents"] = line["quantity"] * line["unit_ttc_cents"]
    return {"currency": "EUR", "taxes_included": True, "lines": lines,
            "total_ttc_cents": sum(line["total_ttc_cents"] for line in lines),
            "expires_at": min(offer.expires_at, offer.checked_at + timedelta(
                seconds=tariffs.max_offer_age_seconds)).isoformat(),
            "payment_enabled": False, "supplier_reservation": False}
