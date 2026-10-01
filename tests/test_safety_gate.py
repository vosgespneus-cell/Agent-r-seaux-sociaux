import unittest
from runtime.safety_gate import evaluate

class SafetyGateTests(unittest.TestCase):
    def test_refund_cannot_be_made_automatic(self):
        result=evaluate({"type":"refund","authorization":"automatic","idempotency_key":"r1"})
        self.assertFalse(result["allowed"])
        self.assertEqual(result["authorization"],"human_required")

    def test_delete_cannot_be_made_automatic(self):
        self.assertFalse(evaluate({
            "type":"delete","authorization":"automatic","idempotency_key":"d1"
        })["allowed"])

    def test_normal_authorized_action_can_pass(self):
        self.assertTrue(evaluate({
            "type":"reply","authorization":"automatic","idempotency_key":"m1"
        })["allowed"])

    def test_missing_idempotency_is_blocked(self):
        self.assertFalse(evaluate({
            "type":"reply","authorization":"automatic"
        })["allowed"])

if __name__=="__main__":
    unittest.main()
