import tempfile
import unittest
from pathlib import Path

from src.idempotency import IdempotencyLedger
from src.worker_result import handle_connector_result


POLICY = {
    "default": {"max_attempts": 3, "delays_seconds": [30, 120, 600]},
    "retryable": ["timeout", "rate_limit"],
    "non_retryable": ["authentication_failed", "invalid_payload"],
}


class WorkerResultTests(unittest.TestCase):
    def action(self, attempt=0):
        return {
            "action_id": "action-1",
            "attempt": attempt,
            "max_attempts": 3,
            "idempotency_key": "vp:test:1",
        }

    def test_success_is_recorded_in_ledger(self):
        with tempfile.TemporaryDirectory() as directory:
            ledger = IdempotencyLedger(Path(directory) / "ledger.json")
            outcome = handle_connector_result(self.action(), {"success": True}, ledger, POLICY)
            self.assertEqual(outcome["status"], "succeeded")
            self.assertTrue(ledger.has_succeeded("vp:test:1"))

    def test_timeout_schedules_retry(self):
        outcome = handle_connector_result(self.action(), {"success": False, "error_kind": "timeout"}, None, POLICY)
        self.assertEqual(outcome["status"], "waiting")
        self.assertTrue(outcome["retry"])
        self.assertEqual(outcome["delay_seconds"], 30)

    def test_authentication_failure_stops(self):
        outcome = handle_connector_result(self.action(), {"success": False, "error_kind": "authentication_failed"}, None, POLICY)
        self.assertEqual(outcome["status"], "failed")
        self.assertFalse(outcome["retry"])

    def test_last_timeout_stops_after_attempt_limit(self):
        outcome = handle_connector_result(self.action(2), {"success": False, "error_kind": "timeout"}, None, POLICY)
        self.assertEqual(outcome["status"], "failed")
        self.assertEqual(outcome["reason"], "attempts_exhausted")


if __name__ == "__main__":
    unittest.main()
