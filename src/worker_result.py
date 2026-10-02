"""Normalize connector results into runtime outcomes."""
from __future__ import annotations

from .retry import decide_retry


def handle_connector_result(action: dict, result: dict, ledger=None, policy: dict | None = None) -> dict:
    """Return the next runtime state after a connector attempt."""
    if result.get("success") is True:
        if ledger is not None:
            ledger.mark_succeeded(action.get("idempotency_key", ""), action.get("action_id"))
        return {
            "status": "succeeded",
            "retry": False,
            "action_id": action.get("action_id"),
            "idempotency_key": action.get("idempotency_key"),
        }

    error_kind = result.get("error_kind") or "unknown"
    retry = decide_retry(action, error_kind, policy)
    return {
        "status": retry["status"],
        "retry": retry["retry"],
        "reason": retry["reason"],
        "delay_seconds": retry.get("delay_seconds"),
        "next_attempt": retry.get("next_attempt", action.get("attempt", 0)),
        "idempotency_key": action.get("idempotency_key"),
    }
