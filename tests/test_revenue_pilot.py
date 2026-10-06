import unittest
from datetime import datetime, timezone
from src.revenue_pilot import build

class RevenueTests(unittest.TestCase):
    def test_unpaid_not_revenue(self):
        r = build({"receipts": [{"id":"a", "amount_eur":40, "status":"reserved"},
             {"id":"b", "amount_eur":60, "status":"paid", "payment_reference":"local-b"}]})
        self.assertEqual(r["paid_eur"], "60")
    def test_duplicate_payment_rejected(self):
        with self.assertRaises(ValueError):
            build({"receipts":[{"id":"a","amount_eur":1},{"id":"a","amount_eur":1}]})
    def test_incomplete_piece_never_promoted(self):
        r = build({"products":[{"id":"p","stock":1,"price_verified":True}]})
        self.assertEqual(r["tasks"][0]["id"], "verify:p")
    def test_no_fake_availability(self):
        self.assertFalse(build({"free_slots":["tomorrow"]})["tasks"])
    def test_stale_lead_followup_no_send(self):
        r = build({"leads":[{"id":"l","status":"quoted","updated_at":"2026-10-01T10:00:00Z"}]},
                  datetime(2026,10,4,tzinfo=timezone.utc))
        self.assertEqual(r["tasks"][0]["id"], "followup:l")
        self.assertFalse(r["external_actions_executed"])
    def test_tariffs_before_marketing(self):
        r = build({"tariff_conflicts":True,"calendar_verified":True,
                   "workshop_capacity_verified":True,"free_slots":["slot"]})
        self.assertEqual(r["tasks"][0]["id"], "tariffs")
    def test_cancelled_lead_ignored(self):
        self.assertFalse(build({"leads":[{"id":"x","status":"cancelled"}]})["tasks"])
    def test_ready_product(self):
        p = dict(id="p", stock=1, reference_verified=True, condition_verified=True,
                 price_verified=True, compatibility_verified=True, real_photos=True)
        self.assertEqual(build({"products":[p]})["tasks"][0]["id"], "list:p")

if __name__ == "__main__": unittest.main()
