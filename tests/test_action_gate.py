import unittest

from src.action_gate import authorize_execution


class ActionGateTests(unittest.TestCase):
    def action(self, **changes):
        value = {
            "type": "publish",
            "execution_mode": "live",
            "authorization": "automatic",
            "status": "proposed",
            "idempotency_key": "vp:test:1",
        }
        value.update(changes)
        return value

    def test_live_action_needs_authorized_connector(self):
        result = authorize_execution(self.action(), {"live_actions": False})
        self.assertFalse(result["allowed"])

    def test_valid_live_action_is_allowed(self):
        result = authorize_execution(self.action(), {"live_actions": True})
        self.assertTrue(result["allowed"])

    def test_draft_can_never_execute(self):
        result = authorize_execution(self.action(execution_mode="draft"), {"live_actions": True})
        self.assertFalse(result["allowed"])

    def test_financial_action_is_blocked(self):
        result = authorize_execution(self.action(type="refund_order"), {"live_actions": True})
        self.assertEqual(result["reason"], "financial_action_requires_human")

    def test_destructive_action_is_blocked(self):
        result = authorize_execution(self.action(type="delete_product"), {"live_actions": True})
        self.assertEqual(result["reason"], "destructive_action_requires_human")

    def test_missing_idempotency_key_is_blocked(self):
        result = authorize_execution(self.action(idempotency_key=""), {"live_actions": True})
        self.assertEqual(result["reason"], "missing_idempotency_key")


if __name__ == "__main__":
    unittest.main()
