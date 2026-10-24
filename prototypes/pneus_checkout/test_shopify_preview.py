import copy
import unittest
from datetime import datetime, timedelta, timezone
from pricing import Tariffs, VerifiedOffer, calculate
from shopify_preview import calculation_input, verify_calculation


class ShopifyPreviewTests(unittest.TestCase):
    def setUp(self):
        now = datetime(2026, 10, 22, tzinfo=timezone.utc)
        offer = VerifiedOffer("fixture", "TEST-ONLY", "Pneu de test 195/65 R16", 5779,
                              4, 16, now, now + timedelta(minutes=5))
        self.preview = calculate(offer, Tariffs(1500, 1800, 2200, 3000,
                                 "période synthétique", 0, ""), quantity=4,
                                 fitting=True, storage_pairs=2, now=now)

    def test_all_services_to_shopify_without_double_charging(self):
        data = calculation_input(self.preview, quote_id="a" * 32)
        self.assertEqual([x["originalUnitPriceWithCurrency"]["amount"]
                          for x in data["lineItems"]], ["57.79", "15.00", "3.00", "2.50", "30.00"])
        self.assertEqual(self.preview["total_ttc_cents"], 37316)
        self.assertNotIn("email", data)
        self.assertNotIn("reference", repr(data))

    def test_tampered_totals_and_incomplete_services_refused(self):
        for mutate in [lambda p: p.update(total_ttc_cents=1),
                       lambda p: p["lines"][0].update(total_ttc_cents=True),
                       lambda p: p["lines"][1].update(quantity=True),
                       lambda p: p["lines"][2].update(kind="fitting")]:
            altered = copy.deepcopy(self.preview)
            mutate(altered)
            with self.assertRaises(ValueError):
                calculation_input(altered, quote_id="a" * 32)

    def response(self, amount="373.16", currency="EUR", taxes=True):
        return {"data": {"draftOrderCalculate": {"userErrors": [], "calculatedDraftOrder": {
            "taxesIncluded": taxes,
            "totalPriceSet": {"shopMoney": {"amount": amount, "currencyCode": currency}},
            "totalTaxSet": {"shopMoney": {"amount": "62.19", "currencyCode": currency}}}}}}

    def test_matching_shopify_total_still_does_not_enable_payment(self):
        out = verify_calculation(self.response(), expected_cents=37316)
        self.assertTrue(out["calculation_matches"])
        self.assertFalse(out["payment_enabled"])
        self.assertFalse(out["supplier_order_created"])

    def test_shopify_divergence_and_errors_refused(self):
        for response in [self.response("373.17"), self.response(currency="USD"),
                         self.response(taxes=False), self.response("NaN"),
                         {"errors": [{"message": "test error"}]}, {"data": {}}]:
            with self.subTest(response=response), self.assertRaises(ValueError):
                verify_calculation(response, expected_cents=37316)


if __name__ == "__main__":
    unittest.main()
