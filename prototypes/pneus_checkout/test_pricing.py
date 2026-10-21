import unittest
from dataclasses import replace
from datetime import datetime, timedelta, timezone
from pricing import VerifiedOffer, Tariffs, calculate


class PricingTest(unittest.TestCase):
    def setUp(self):
        self.now = datetime(2026, 10, 21, 16, tzinfo=timezone.utc)
        # Synthetic fixtures, not a current supplier quotation or live tariff.
        self.offer = VerifiedOffer("fixture", "TEST-2055516", "Pneu test 205/55 R16 91V",
                                   4370, 8, 16, self.now, self.now + timedelta(minutes=5))
        self.tariffs = Tariffs(1500, 1800, 2200, 3000, "période de test", 0, "")

    def quote(self, **kw):
        return calculate(self.offer, self.tariffs, quantity=4, now=self.now, **kw)

    def test_itemized_ttc_and_optional_services(self):
        self.assertEqual(self.quote()["total_ttc_cents"], 17480)
        q = self.quote(fitting=True, storage_pairs=2)
        self.assertEqual(q["total_ttc_cents"], 29480)
        self.assertEqual([x["total_ttc_cents"] for x in q["lines"]], [17480, 6000, 6000])
        self.assertFalse(q["payment_enabled"])
        self.assertFalse(q["supplier_reservation"])

    def test_all_fitting_bands_and_required_fees(self):
        for rim, fitting_total in [(13,6000),(16,6000),(17,7200),(18,8800),(22,8800)]:
            q = calculate(replace(self.offer, rim_inches=rim),
                          replace(self.tariffs, other_required_fees_cents=2595,
                                  required_fees_label="Frais test"),
                          quantity=4, fitting=True, now=self.now)
            self.assertEqual(q["total_ttc_cents"],17480+fitting_total+2595)

    def test_invalid_quantities_and_boolean_values(self):
        for qty in [0,-1,1.5,"4",True,25]:
            with self.subTest(qty=qty), self.assertRaises(ValueError):
                calculate(self.offer,self.tariffs,quantity=qty,now=self.now)
        for kw in [{"fitting":"false"},{"storage_pairs":True},{"storage_pairs":-1}]:
            with self.assertRaises(ValueError): self.quote(**kw)

    def test_no_stock_expired_future_and_naive_dates(self):
        for offer in [replace(self.offer,available=3),
                      replace(self.offer,expires_at=self.now),
                      replace(self.offer,checked_at=self.now-timedelta(minutes=6)),
                      replace(self.offer,checked_at=self.now+timedelta(seconds=1)),
                      replace(self.offer,checked_at=self.now.replace(tzinfo=None)),
                      replace(self.offer,unit_ttc_cents=-1)]:
            with self.assertRaises(ValueError):
                calculate(offer,self.tariffs,quantity=4,now=self.now)

    def test_missing_service_configuration_blocks(self):
        with self.assertRaises(ValueError):
            calculate(self.offer,replace(self.tariffs,storage_period=""),
                      quantity=4,storage_pairs=1,now=self.now)
        with self.assertRaises(ValueError):
            calculate(replace(self.offer,rim_inches=12),self.tariffs,
                      quantity=4,fitting=True,now=self.now)


if __name__ == "__main__": unittest.main()
