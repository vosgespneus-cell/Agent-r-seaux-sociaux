import tempfile
import unittest
from pathlib import Path

from src.idempotency import IdempotencyLedger


class IdempotencyLedgerTests(unittest.TestCase):
    def test_unknown_key_is_not_completed(self):
        with tempfile.TemporaryDirectory() as directory:
            ledger = IdempotencyLedger(Path(directory) / "ledger.json")
            self.assertFalse(ledger.has_succeeded("vp:test:1"))

    def test_completed_key_survives_new_instance(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "ledger.json"
            IdempotencyLedger(path).mark_succeeded("vp:test:1", "action-1")
            self.assertTrue(IdempotencyLedger(path).has_succeeded("vp:test:1"))

    def test_different_key_remains_available(self):
        with tempfile.TemporaryDirectory() as directory:
            ledger = IdempotencyLedger(Path(directory) / "ledger.json")
            ledger.mark_succeeded("vp:test:1")
            self.assertFalse(ledger.has_succeeded("vp:test:2"))

    def test_empty_key_cannot_be_recorded(self):
        with tempfile.TemporaryDirectory() as directory:
            ledger = IdempotencyLedger(Path(directory) / "ledger.json")
            with self.assertRaises(ValueError):
                ledger.mark_succeeded("")

    def test_corrupt_ledger_fails_closed_to_empty_local_state(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "ledger.json"
            path.write_text("not-json", encoding="utf-8")
            ledger = IdempotencyLedger(path)
            self.assertFalse(ledger.has_succeeded("vp:test:1"))


if __name__ == "__main__":
    unittest.main()
