"""Safe orchestration layer for VOSGES PNEUS.

It converts an event into a proposed action. External execution is deliberately
out of scope: connectors consume only actions that pass their own live gate.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
from uuid import uuid4

from .router import route_event

ROOT = Path(__file__).resolve().parents[1]


def load_connector_states(path: Path | None = None) -> dict:
    target = path or ROOT / "core" / "connector-state.json"
    data = json.loads(target.read_text(encoding="utf-8"))
    return data.get("connectors", {})


def _stable_payload_hash(payload: dict) -> str:
    raw = json.dumps(payload or {}, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()[:16]


def build_idempotency_key(event: dict, routing: dict) -> str:
    identity = event.get("event_id") or event.get("correlation_id") or "anonymous"
    return f"vp:{routing['agent']}:{routing['action']}:{identity}:{_stable_payload_hash(event.get('payload', {}))}"


def propose_action(event: dict, connector_states: dict | None = None) -> dict:
    states = connector_states if connector_states is not None else load_connector_states()
    routing = route_event(event, states)
    authorization = "automatic"
    if routing["requires_human"]:
        authorization = "human_required"

    return {
        "version": "1.1",
        "action_id": str(uuid4()),
        "event_id": event.get("event_id"),
        "correlation_id": event.get("correlation_id") or event.get("event_id"),
        "business": "vosges_pneus",
        "agent": routing["agent"],
        "type": routing["action"],
        "target": {
            "channel": event.get("payload", {}).get("channel"),
            "destination": None,
        },
        "payload": event.get("payload", {}),
        "idempotency_key": build_idempotency_key(event, routing),
        "authorization": authorization,
        "execution_mode": routing["execution_mode"],
        "status": "proposed",
        "attempt": 0,
        "max_attempts": 3,
        "last_error": None,
    }
