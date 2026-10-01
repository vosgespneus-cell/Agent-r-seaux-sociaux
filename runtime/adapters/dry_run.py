"""Safe adapter used to test the full runtime without external side effects."""
from .base import Adapter

class DryRunAdapter(Adapter):
    def __init__(self, channel):
        self.channel = channel
        self.actions = []

    def health_check(self):
        return {"channel": self.channel, "status": "healthy", "mode": "dry_run"}

    def read_events(self):
        return []

    def execute_action(self, action):
        receipt = {
            "channel": self.channel,
            "action_id": action.get("action_id"),
            "type": action.get("type"),
            "mode": "dry_run",
            "executed": False,
        }
        self.actions.append(receipt)
        return receipt
