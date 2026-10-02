"""Append-only privacy-safe audit log for runtime decisions.

Only operational metadata is persisted. Customer payloads, message bodies,
credentials and secrets must never be written here.
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ALLOWED_FIELDS = {
    "event_id",
    "correlation_id",
    "action_id",
    "agent",
    "action_type",
    "runtime_status",
    "reason",
    "attempt",
    "connector",
}


def sanitize_audit_entry(entry: dict) -> dict:
    return {key: entry.get(key) for key in ALLOWED_FIELDS if entry.get(key) is not None}


class AuditLog:
    def __init__(self, path: str | Path):
        self.path = Path(path)

    def append(self, entry: dict) -> dict:
        safe = sanitize_audit_entry(entry)
        safe["recorded_at"] = datetime.now(timezone.utc).isoformat()
        self.path.parent.mkdir(parents=True, exist_ok=True)
        with self.path.open("a", encoding="utf-8") as handle:
            handle.write(json.dumps(safe, ensure_ascii=False, sort_keys=True) + "\n")
        return safe
