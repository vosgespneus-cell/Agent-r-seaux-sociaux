import hashlib
import hmac
import json
import os
import tempfile
import unittest
from pathlib import Path

from src.intake import receive, summary
from src.supervisor import inspect


class IntakeTests(unittest.TestCase):
    def test_private_payload_and_duplicate(self):
        secret = "a" * 32
        markers = {"customer_name": "PRIVATE_NAME_MARKER",
                   "phone": "PRIVATE_PHONE_MARKER",
                   "message": "PRIVATE_MESSAGE_MARKER"}
        raw = json.dumps({"event_id": "synthetic-event-42", "source": "make",
                          "kind": "message", **markers}).encode()
        signature = "sha256=" + hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()
        with tempfile.TemporaryDirectory() as directory:
            db = Path(directory) / "private" / "events.sqlite3"
            status, result = receive(raw, signature, secret, str(db))
            self.assertEqual(status, 202)
            self.assertEqual(os.stat(db).st_mode & 0o777, 0o600)
            safe = summary(str(db))
            self.assertTrue(inspect(safe)["ok"])
            for marker in markers.values():
                self.assertNotIn(marker, json.dumps(safe))
            self.assertEqual(receive(raw, signature, secret, str(db))[0], 200)
            changed = raw.replace(b"PRIVATE_MESSAGE_MARKER", b"DIFFERENT_MESSAGE")
            changed_signature = "sha256=" + hmac.new(secret.encode(), changed, hashlib.sha256).hexdigest()
            self.assertEqual(receive(changed, changed_signature, secret, str(db))[0], 409)
            self.assertEqual(len(summary(str(db))["tasks"]), 1)
            self.assertEqual(result["task_id"], safe["tasks"][0]["id"])

    def test_rejects_unsigned_and_invalid(self):
        with tempfile.TemporaryDirectory() as directory:
            db = str(Path(directory) / "events.sqlite3")
            raw = b'{"event_id":"x","source":"make","kind":"message"}'
            self.assertEqual(receive(raw, "", "a" * 32, db)[0], 401)
            self.assertFalse(Path(db).exists())
            signature = "sha256=" + hmac.new(b"a" * 32, raw, hashlib.sha256).hexdigest()
            self.assertEqual(receive(raw, signature, "a" * 32, db)[0], 202)


if __name__ == "__main__":
    unittest.main()
