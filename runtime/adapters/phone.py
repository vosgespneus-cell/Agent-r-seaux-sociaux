"""Generic phone adapter. Outbound calling is protected by default."""
from .base import Adapter

class PhoneAdapter(Adapter):
    channel = "phone"

    def __init__(self, backend, allow_outbound=False):
        self.backend = backend
        self.allow_outbound = allow_outbound

    def health_check(self):
        try:
            ok = bool(self.backend.health_check())
            return {"channel": self.channel, "status": "healthy" if ok else "degraded",
                    "outbound_enabled": self.allow_outbound}
        except Exception as exc:
            return {"channel": self.channel, "status": "down",
                    "error": type(exc).__name__, "outbound_enabled": self.allow_outbound}

    def read_events(self):
        events = []
        for item in self.backend.read_calls():
            events.append({
                "source": "phone",
                "source_id": item["id"],
                "type": "call",
                "priority": "high" if item.get("missed") else item.get("priority", "normal"),
                "payload": item.get("payload", {}),
            })
        return events

    def execute_action(self, action):
        if action.get("type") != "callback":
            raise ValueError("unsupported_phone_action")
        if not self.allow_outbound:
            return {"channel": self.channel, "executed": False, "mode": "protected"}
        return self.backend.callback(action)
