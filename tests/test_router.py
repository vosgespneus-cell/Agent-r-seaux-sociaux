import unittest

from src.router import route_event


class RouterTests(unittest.TestCase):
    def test_part_photo_goes_to_product_agent(self):
        result = route_event({"type": "part_photo", "source": "manual", "payload": {}})
        self.assertEqual(result["agent"], "product-agent")

    def test_shopify_draft_goes_to_shopify_agent(self):
        result = route_event({"type": "shopify_draft_task", "source": "manual", "payload": {}})
        self.assertEqual(result["agent"], "shopify-agent")
        self.assertEqual(result["action"], "prepare_product_draft")

    def test_whatsapp_stays_draft_if_connector_not_live(self):
        result = route_event(
            {"type": "message", "source": "whatsapp", "payload": {}},
            {"whatsapp": {"live_actions": False}},
        )
        self.assertEqual(result["agent"], "whatsapp-agent")
        self.assertEqual(result["execution_mode"], "draft")

    def test_social_publish_is_blocked_from_live_without_connector(self):
        result = route_event(
            {"type": "publication_task", "source": "manual", "payload": {"channel": "facebook"}},
            {"facebook": {"live_actions": False}},
        )
        self.assertEqual(result["execution_mode"], "draft")
        self.assertTrue(result["requires_human"])

    def test_social_publish_can_be_live_only_when_connector_allows_it(self):
        result = route_event(
            {"type": "publication_task", "source": "manual", "payload": {"channel": "facebook"}},
            {"facebook": {"live_actions": True}},
        )
        self.assertEqual(result["execution_mode"], "live")
        self.assertFalse(result["requires_human"])

    def test_unknown_event_falls_back_to_supervisor(self):
        result = route_event({"type": "future_event", "source": "manual", "payload": {}})
        self.assertEqual(result["agent"], "supervisor")
        self.assertEqual(result["execution_mode"], "draft")


if __name__ == "__main__":
    unittest.main()
