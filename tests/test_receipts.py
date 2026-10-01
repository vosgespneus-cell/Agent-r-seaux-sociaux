import unittest
from runtime.receipts import build_receipt, is_proven

class ReceiptTests(unittest.TestCase):
    def test_external_reference_proves_execution(self):
        action={"action_id":"A1","event_id":"E1","type":"reply","target":{"channel":"email"}}
        receipt=build_receipt(action,{"executed":True,"message_id":"M1"})
        self.assertTrue(is_proven(receipt))
        self.assertEqual(receipt["proof"]["message_id"],"M1")

    def test_customer_payload_is_not_copied(self):
        action={"action_id":"A1","event_id":"E1","type":"reply",
                "target":{"channel":"email"},
                "payload":{"body":"PRIVATE","customer_phone":"PRIVATE"}}
        receipt=build_receipt(action,{"executed":True,"message_id":"M1","body":"PRIVATE"})
        self.assertNotIn("payload",receipt)
        self.assertNotIn("body",receipt["proof"])

    def test_dry_run_is_not_execution_proof(self):
        receipt=build_receipt(
            {"action_id":"A1","type":"reply","target":{"channel":"email"}},
            {"executed":False,"mode":"dry_run"}
        )
        self.assertFalse(is_proven(receipt))

if __name__=="__main__":
    unittest.main()
