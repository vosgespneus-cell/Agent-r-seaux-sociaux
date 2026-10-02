import json
import tempfile
import unittest
from pathlib import Path

from src.audit_log import AuditLog, sanitize_audit_entry


class AuditLogTests(unittest.TestCase):
    def test_sensitive_payload_fields_are_removed(self):
        safe = sanitize_audit_entry({
            "event_id": "evt-1",
            "agent": "customer-service-agent",
            "payload": {"message": "secret customer message"},
            "email": "customer@example.com",
            "phone": "0600000000",
            "token": "secret-token",
        })
        self.assertEqual(safe["event_id"], "evt-1")
        self.assertNotIn("payload", safe)
        self.assertNotIn("email", safe)
        self.assertNotIn("phone", safe)
        self.assertNotIn("token", safe)

    def test_append_writes_one_json_line(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "audit.jsonl"
            AuditLog(path).append({"event_id": "evt-1", "runtime_status": "draft_ready"})
            lines = path.read_text(encoding="utf-8").splitlines()
            self.assertEqual(len(lines), 1)
            saved = json.loads(lines[0])
            self.assertEqual(saved["event_id"], "evt-1")
            self.assertIn("recorded_at", saved)

    def test_append_is_additive(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "audit.jsonl"
            log = AuditLog(path)
            log.append({"event_id": "evt-1"})
            log.append({"event_id": "evt-2"})
            self.assertEqual(len(path.read_text(encoding="utf-8").splitlines()), 2)


if __name__ == "__main__":
    unittest.main()
