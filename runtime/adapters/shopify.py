"""Generic Shopify adapter with protected writes."""
from .base import Adapter

class ShopifyAdapter(Adapter):
    channel = "shopify"

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
        events=[]
        for item in self.backend.read_orders():
            events.append({
                "source":"shopify",
                "source_id":item["id"],
                "type":"order",
                "priority":item.get("priority","normal"),
                "payload":item.get("payload",{}),
            })
        return events

    def execute_action(self, action):
        if action.get("type") not in ("customer_update","update_order"):
            raise ValueError("unsupported_shopify_action")
        if not self.allow_write:
            return {"channel":self.channel,"executed":False,"mode":"protected"}
        return self.backend.execute(action)
