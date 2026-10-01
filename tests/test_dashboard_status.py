import unittest
from dashboard.status import action_status

class DashboardStatusTests(unittest.TestCase):
    def test_completed_without_proof_is_not_trusted(self):
        status=action_status({"status":"completed"})
        self.assertEqual(status["label"],"A VERIFIER")
        self.assertFalse(status["trusted"])

    def test_receipt_with_reference_is_executed(self):
        action={"status":"completed"}
        receipt={"executed":True,"proof":{"message_id":"M1"}}
        status=action_status(action,receipt)
        self.assertEqual(status["label"],"EXECUTE")
        self.assertTrue(status["trusted"])

if __name__=="__main__":
    unittest.main()
