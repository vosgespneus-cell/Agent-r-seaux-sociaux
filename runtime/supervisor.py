"""Minimal supervisor routing for normalized events."""

ROUTES = {
    "appointment": "planning-agent",
    "message": "customer-service-agent",
    "call": "phone-agent",
    "order": "commerce-agent",
    "lead": "sales-agent",
    "alert": "supervisor",
}

def route_event(event):
    """Return the agent responsible for a normalized event."""
    return ROUTES.get(event.get("type"), "supervisor")

def decision(event):
    """Build a deterministic routing decision."""
    return {
        "event_id": event.get("event_id"),
        "agent": route_event(event),
        "priority": event.get("priority", "normal"),
        "status": "queued",
    }
