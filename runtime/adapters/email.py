"""Generic email adapter boundary.

Credentials and message bodies stay in the private runtime. Real sending is
disabled unless the private deployment explicitly enables it.
"""
from .base import Adapter

class EmailAdapter(Adapter):
    channel = "email"

    def __init__(self, backend, allow_send=False):
        self.backend = backend
        self.allow_send = allow_send

    def health_check(self):
        try:
            ok = bool(self.backend.health_check())
            return {"channel": self.channel, "status": "healthy" if ok else "degraded",
                    "send_enabled": self.allow_send}
        except Exception as exc:
            return {"channel": self.channel, "status": "down",
                    "error": type(exc).__name__, "send_enabled": self.allow_send}

    def read_events(self):
        events = []
        for item in self.backend.read_messages():
            events.append({
                "source": "email",
                "source_id": item["id"],
                "type": "message",
                "priority": item.get("priority", "normal"),
                "payload": item.get("payload", {}),
            })
        return events

    def execute_action(self, action):
        if action.get("type") != "reply":
            raise ValueError("unsupported_email_action")
        if not self.allow_send:
            return {"channel": "email", "executed": False, "mode": "protected"}
        return self.backend.send_reply(action)
