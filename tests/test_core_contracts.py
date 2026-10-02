import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class CoreContractTests(unittest.TestCase):
    def load_json(self, relative_path):
        with (ROOT / relative_path).open(encoding="utf-8") as handle:
            return json.load(handle)

    def test_core_json_files_are_valid(self):
        for path in [
            "core/event-schema.json",
            "core/action-schema.json",
            "core/agents-registry.json",
            "core/task-state-machine.json",
            "adapters/shopify/contract.json",
        ]:
            with self.subTest(path=path):
                self.assertIsInstance(self.load_json(path), dict)

    def test_shopify_starts_in_draft_only_mode(self):
        contract = self.load_json("adapters/shopify/contract.json")
        self.assertEqual(contract["mode"], "draft_only")
        self.assertIn("publish_product", contract["blocked_by_default"])
        self.assertIn("refund_order", contract["blocked_by_default"])
        self.assertTrue(contract["idempotency"]["required"])

    def test_external_publishers_are_not_live_by_default(self):
        registry = self.load_json("core/agents-registry.json")
        self.assertNotEqual(
            registry["social-publisher-agent"].get("mode"), "live"
        )
        self.assertNotEqual(registry["whatsapp-agent"].get("mode"), "live")
        self.assertEqual(
            registry["shopify-agent"].get("publication_mode"),
            "draft_only_until_verified",
        )

    def test_event_contract_supports_recovery(self):
        event = self.load_json("core/event-schema.json")
        self.assertIn("correlation_id", event)
        self.assertIn("deduplication", event)
        self.assertIn("attempt", event)
        self.assertIn("runtime_error", event["type"])

    def test_action_contract_supports_safe_execution(self):
        action = self.load_json("core/action-schema.json")
        self.assertIn("idempotency_key", action)
        self.assertIn("execution_mode", action)
        self.assertIn("max_attempts", action)
        self.assertIn("blocked", action["status"])


if __name__ == "__main__":
    unittest.main()
