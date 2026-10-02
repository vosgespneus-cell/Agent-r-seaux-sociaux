"""End-to-end local runtime: event -> proposed action -> execution decision.

This module never calls an external provider. It is the safe integration point
for future connector workers.
"""
from __future__ import annotations

from .action_gate import authorize_execution
from .orchestrator import load_connector_states, propose_action


def process_event(event: dict, connector_states: dict | None = None, ledger=None, audit_log=None) -> dict:
    states = connector_states if connector_states is not None else load_connector_states()
    action = propose_action(event, states)
    channel = action.get("target", {}).get("channel") or event.get("source")
    connector_state = states.get(channel, {})
    decision = authorize_execution(action, connector_state)

    duplicate = False
    if ledger is not None and ledger.has_succeeded(action.get("idempotency_key", "")):
        duplicate = True
        decision = {"allowed": False, "reason": "already_succeeded"}

    if duplicate:
        runtime_status = "duplicate_ignored"
    elif decision["allowed"]:
        runtime_status = "ready_for_connector"
    elif action.get("execution_mode") == "draft":
        runtime_status = "draft_ready"
    else:
        runtime_status = "blocked"

    output = {
        "event_id": event.get("event_id"),
        "correlation_id": action.get("correlation_id"),
        "runtime_status": runtime_status,
        "duplicate": duplicate,
        "action": action,
        "execution_decision": decision,
    }

    if audit_log is not None:
        audit_log.append({
            "event_id": event.get("event_id"),
            "correlation_id": action.get("correlation_id"),
            "action_id": action.get("action_id"),
            "agent": action.get("agent"),
            "action_type": action.get("type"),
            "runtime_status": runtime_status,
            "reason": decision.get("reason"),
            "attempt": action.get("attempt"),
            "connector": channel,
        })

    return output
