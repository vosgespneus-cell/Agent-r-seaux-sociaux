import unittest

from src.retry import decide_retry


POLICY = {
    "default": {"max_attempts": 3, "delays_seconds": [30, 120, 600]},
    "retryable": ["timeout", "rate_limit", "network_error"],
    "non_retryable": ["authentication_failed", "invalid_payload"],
}


class RetryTests(unittest.TestCase):
    def action(self, attempt=0):
        return {"attempt": attempt, "max_attempts": 3, "idempotency_key": "vp:test:1"}

    def test_first_temporary_failure_waits_30_seconds(self):
        result = decide_retry(self.action(0), "timeout", POLICY)
        self.assertTrue(result["retry"])
        self.assertEqual(result["delay_seconds"], 30)
        self.assertEqual(result["next_attempt"], 1)

    def test_second_temporary_failure_waits_120_seconds(self):
        result = decide_retry(self.action(1), "network_error", POLICY)
        self.assertTrue(result["retry"])
        self.assertEqual(result["delay_seconds"], 120)

    def test_retry_keeps_same_idempotency_key(self):
        result = decide_retry(self.action(0), "rate_limit", POLICY)
        self.assertEqual(result["idempotency_key"], "vp:test:1")

    def test_attempts_exhausted_fail(self):
        result = decide_retry(self.action(2), "timeout", POLICY)
        self.assertFalse(result["retry"])
        self.assertEqual(result["reason"], "attempts_exhausted")

    def test_authentication_failure_never_retries(self):
        result = decide_retry(self.action(0), "authentication_failed", POLICY)
        self.assertFalse(result["retry"])
        self.assertEqual(result["reason"], "non_retryable")

    def test_unknown_error_fails_instead_of_looping(self):
        result = decide_retry(self.action(0), "mystery", POLICY)
        self.assertFalse(result["retry"])
        self.assertEqual(result["reason"], "unknown_error_kind")


if __name__ == "__main__":
    unittest.main()
