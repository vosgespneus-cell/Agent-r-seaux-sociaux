"""End-to-end local runtime: event -> proposed action -> execution decision.

This module never calls an external provider. It is the safe integration point
for future connector workers.
"""
from __future__ import annotations

from .action_gate import authorize_execution
from .orchestrator import load_connector_states, propose_action


def process_event(event: dict, connector_states: dict | None = None) -> dict:
    states = connector_states if connector_states is not None else load_connector_states()
    action = propose_action(event, states)
    channel = action.get("target", {}).get("channel") or event.get("source")
    connector_state = states.get(channel, {})
    decision = authorize_execution(action, connector_state)

    if decision["allowed"]:
        runtime_status = "ready_for_connector"
    elif action.get("execution_mode") == "draft":
        runtime_status = "draft_ready"
    else:
        runtime_status = "blocked"

    return {
        "event_id": event.get("event_id"),
        "correlation_id": action.get("correlation_id"),
        "runtime_status": runtime_status,
        "action": action,
        "execution_decision": decision,
    }
