"""Small persistent ledger preventing duplicate external actions.

The default store is local JSON and contains action fingerprints only, never
customer payloads or secrets. A database backend can replace it later.
"""
from __future__ import annotations

import json
from pathlib import Path
from tempfile import NamedTemporaryFile


class LedgerCorruptionError(RuntimeError):
    """Raised when duplicate-protection state cannot be trusted."""


class IdempotencyLedger:
    def __init__(self, path: str | Path):
        self.path = Path(path)

    def _load(self) -> dict:
        if not self.path.exists():
            return {"completed": {}}
        try:
            data = json.loads(self.path.read_text(encoding="utf-8"))
        except (json.JSONDecodeError, OSError) as exc:
            raise LedgerCorruptionError("idempotency ledger is unreadable") from exc
        if not isinstance(data, dict) or not isinstance(data.get("completed", {}), dict):
            raise LedgerCorruptionError("idempotency ledger has an invalid structure")
        data.setdefault("completed", {})
        return data

    def has_succeeded(self, key: str) -> bool:
        return bool(key) and key in self._load()["completed"]

    def mark_succeeded(self, key: str, action_id: str | None = None) -> None:
        if not key:
            raise ValueError("idempotency key is required")
        data = self._load()
        data["completed"][key] = {"action_id": action_id}
        self.path.parent.mkdir(parents=True, exist_ok=True)
        with NamedTemporaryFile("w", encoding="utf-8", dir=self.path.parent, delete=False) as handle:
            json.dump(data, handle, ensure_ascii=False, indent=2, sort_keys=True)
            handle.write("\n")
            temporary = Path(handle.name)
        temporary.replace(self.path)
