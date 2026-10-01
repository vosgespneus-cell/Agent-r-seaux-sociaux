"""Common adapter contract. External providers implement this interface."""
from abc import ABC, abstractmethod

class Adapter(ABC):
    channel = "unknown"

    @abstractmethod
    def health_check(self):
        """Return a privacy-safe connector health result."""

    @abstractmethod
    def read_events(self):
        """Return normalized raw events from the provider."""

    @abstractmethod
    def execute_action(self, action):
        """Execute one already-authorized action."""

    def normalize_error(self, exc):
        return {"type": type(exc).__name__, "retryable": True}
