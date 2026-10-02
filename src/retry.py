"""Retry decisions for connector workers.

The module calculates what to do after a connector failure; it does not sleep
or execute retries itself, which keeps workers observable and testable.
"""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def load_retry_policy(path: Path | None = None) -> dict:
    target = path or ROOT / "core" / "retry-policy.json"
    return json.loads(target.read_text(encoding="utf-8"))


def decide_retry(action: dict, error_kind: str, policy: dict | None = None) -> dict:
    rules = policy or load_retry_policy()
    attempt = int(action.get("attempt", 0))
    max_attempts = int(action.get("max_attempts", rules["default"]["max_attempts"]))

    if error_kind in rules.get("non_retryable", []):
        return {"retry": False, "status": "failed", "reason": "non_retryable", "delay_seconds": None, "next_attempt": attempt}

    if error_kind not in rules.get("retryable", []):
        return {"retry": False, "status": "failed", "reason": "unknown_error_kind", "delay_seconds": None, "next_attempt": attempt}

    next_attempt = attempt + 1
    if next_attempt >= max_attempts:
        return {"retry": False, "status": "failed", "reason": "attempts_exhausted", "delay_seconds": None, "next_attempt": next_attempt}

    delays = rules["default"].get("delays_seconds", [30])
    delay_index = min(attempt, len(delays) - 1)
    return {
        "retry": True,
        "status": "waiting",
        "reason": "temporary_failure",
        "delay_seconds": delays[delay_index],
        "next_attempt": next_attempt,
        "idempotency_key": action.get("idempotency_key"),
    }
