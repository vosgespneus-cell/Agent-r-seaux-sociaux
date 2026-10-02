"""Final safety gate before any connector is allowed to execute an action."""
from __future__ import annotations

FINANCIAL_ACTIONS = {"refund_order", "capture_payment", "charge", "pay"}
DESTRUCTIVE_ACTIONS = {"delete", "delete_product", "delete_post"}


def authorize_execution(action: dict, connector_state: dict | None = None) -> dict:
    state = connector_state or {}
    action_type = action.get("type")
    mode = action.get("execution_mode", "draft")

    if action_type in FINANCIAL_ACTIONS:
        return {"allowed": False, "reason": "financial_action_requires_human"}
    if action_type in DESTRUCTIVE_ACTIONS:
        return {"allowed": False, "reason": "destructive_action_requires_human"}
    if action.get("authorization") == "human_required":
        return {"allowed": False, "reason": "human_authorization_required"}
    if mode != "live":
        return {"allowed": False, "reason": "action_not_in_live_mode"}
    if not state.get("live_actions", False):
        return {"allowed": False, "reason": "connector_not_authorized_for_live_actions"}
    if action.get("status") not in {"proposed", "queued"}:
        return {"allowed": False, "reason": "invalid_action_status"}
    if not action.get("idempotency_key"):
        return {"allowed": False, "reason": "missing_idempotency_key"}
    return {"allowed": True, "reason": "authorized"}
