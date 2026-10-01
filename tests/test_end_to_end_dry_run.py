import unittest
from runtime.supervisor import decision
from runtime.legacy_bridge import public_task
from runtime.adapters.registry import dry_run_registry
from runtime.executor import execute
from src.supervisor import inspect

class EndToEndDryRunTests(unittest.TestCase):
    def test_email_request_can_cross_system_without_side_effect(self):
        event = {
            "event_id": "E2E-EMAIL-1",
            "source": "email",
            "type": "message",
            "priority": "high",
            "payload": {"text": "private customer request"},
        }

        routing = decision(event)
        self.assertEqual(routing["agent"], "customer-service-agent")

        public = public_task(event, routing, "Demande client a traiter")
        self.assertTrue(inspect({"tasks": [public]})["ok"])
        self.assertNotIn("payload", public)

        action = {
            "action_id": "ACT-E2E-1",
            "event_id": event["event_id"],
            "type": "reply",
            "authorization": "automatic",
            "idempotency_key": "reply:E2E-EMAIL-1",
            "target": {"channel": "email"},
            "payload": {"body": "simulation only"},
        }
        result = execute(action, dry_run_registry(["email"]))
        self.assertEqual(result["status"], "completed")
        self.assertFalse(result["result"]["executed"])

    def test_human_required_never_reaches_adapter(self):
        adapters = dry_run_registry(["shopify"])
        action = {
            "action_id": "ACT-SENSITIVE-1",
            "type": "refund",
            "authorization": "human_required",
            "idempotency_key": "refund-once",
            "target": {"channel": "shopify"},
        }
        result = execute(action, adapters)
        self.assertEqual(result["status"], "waiting_human")
        self.assertEqual(adapters["shopify"].actions, [])

if __name__ == "__main__":
    unittest.main()
