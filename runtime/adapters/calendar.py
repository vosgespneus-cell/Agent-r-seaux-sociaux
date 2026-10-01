"""Generic calendar adapter with protected writes."""
from .base import Adapter

class CalendarAdapter(Adapter):
    channel = "calendar"

    def __init__(self, backend, allow_write=False):
        self.backend = backend
        self.allow_write = allow_write

    def health_check(self):
        try:
            ok = bool(self.backend.health_check())
            return {"channel": self.channel, "status": "healthy" if ok else "degraded",
                    "write_enabled": self.allow_write}
        except Exception as exc:
            return {"channel": self.channel, "status": "down",
                    "error": type(exc).__name__, "write_enabled": self.allow_write}

    def read_events(self):
        return self.backend.read_events()

    def execute_action(self, action):
        if action.get("type") not in ("create_appointment","update_appointment"):
            raise ValueError("unsupported_calendar_action")
        if not self.allow_write:
            return {"channel":"calendar","executed":False,"mode":"protected"}
        if action["type"] == "create_appointment":
            return self.backend.create_appointment(action)
        return self.backend.update_appointment(action)
