import unittest

from src.orchestrator import propose_action


class OrchestratorTests(unittest.TestCase):
    def base_event(self, **changes):
        event = {
            "event_id": "evt-001",
            "correlation_id": "corr-001",
            "source": "manual",
            "type": "part_photo",
            "payload": {"reference": "ABC123"},
        }
        event.update(changes)
        return event

    def test_product_event_becomes_safe_proposed_action(self):
        action = propose_action(self.base_event(), {})
        self.assertEqual(action["agent"], "product-agent")
        self.assertEqual(action["status"], "proposed")
        self.assertEqual(action["execution_mode"], "draft")
        self.assertEqual(action["attempt"], 0)

    def test_idempotency_key_is_stable_for_same_event(self):
        first = propose_action(self.base_event(), {})
        second = propose_action(self.base_event(), {})
        self.assertEqual(first["idempotency_key"], second["idempotency_key"])
        self.assertNotEqual(first["action_id"], second["action_id"])

    def test_payload_change_changes_idempotency_key(self):
        first = propose_action(self.base_event(), {})
        changed = self.base_event(payload={"reference": "XYZ999"})
        second = propose_action(changed, {})
        self.assertNotEqual(first["idempotency_key"], second["idempotency_key"])

    def test_disabled_social_connector_requires_human(self):
        event = self.base_event(
            type="publication_task",
            payload={"channel": "facebook", "content_id": "post-1"},
        )
        action = propose_action(event, {"facebook": {"live_actions": False}})
        self.assertEqual(action["execution_mode"], "draft")
        self.assertEqual(action["authorization"], "human_required")

    def test_enabled_social_connector_can_propose_live(self):
        event = self.base_event(
            type="publication_task",
            payload={"channel": "facebook", "content_id": "post-1"},
        )
        action = propose_action(event, {"facebook": {"live_actions": True}})
        self.assertEqual(action["execution_mode"], "live")
        self.assertEqual(action["authorization"], "automatic")


if __name__ == "__main__":
    unittest.main()
