import unittest

from src.runtime import process_event


class RuntimeTests(unittest.TestCase):
    def test_part_photo_is_prepared_without_external_execution(self):
        result = process_event({
            "event_id": "evt-photo-1",
            "correlation_id": "corr-photo-1",
            "source": "manual",
            "type": "part_photo",
            "payload": {"reference": "ABC123"},
        }, {})
        self.assertEqual(result["runtime_status"], "draft_ready")
        self.assertEqual(result["action"]["agent"], "product-agent")
        self.assertFalse(result["execution_decision"]["allowed"])

    def test_disabled_facebook_publish_stays_draft(self):
        result = process_event({
            "event_id": "evt-post-1",
            "source": "manual",
            "type": "publication_task",
            "payload": {"channel": "facebook", "content_id": "content-1"},
        }, {"facebook": {"live_actions": False}})
        self.assertEqual(result["runtime_status"], "draft_ready")
        self.assertEqual(result["action"]["authorization"], "human_required")

    def test_authorized_facebook_publish_reaches_connector_boundary(self):
        result = process_event({
            "event_id": "evt-post-2",
            "source": "manual",
            "type": "publication_task",
            "payload": {"channel": "facebook", "content_id": "content-2"},
        }, {"facebook": {"live_actions": True}})
        self.assertEqual(result["runtime_status"], "ready_for_connector")
        self.assertTrue(result["execution_decision"]["allowed"])

    def test_whatsapp_without_live_permission_stays_draft(self):
        result = process_event({
            "event_id": "evt-wa-1",
            "source": "whatsapp",
            "type": "message",
            "payload": {"intent": "appointment_request"},
        }, {"whatsapp": {"live_actions": False}})
        self.assertEqual(result["runtime_status"], "draft_ready")
        self.assertEqual(result["action"]["agent"], "whatsapp-agent")


if __name__ == "__main__":
    unittest.main()
