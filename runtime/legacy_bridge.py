"""Compatibility bridge between the generic runtime and the legacy public task register.

This module carries no customer data and performs no external action.
"""

AGENT_MAP = {
    "planning-agent": "planning",
    "customer-service-agent": "accueil",
    "phone-agent": "telephone",
    "commerce-agent": "gestion",
    "sales-agent": "accueil",
    "marketing-agent": "marketing",
    "supervisor": "supervision",
}

STATUS_MAP = {
    "received": "nouveau",
    "normalized": "nouveau",
    "queued": "nouveau",
    "processing": "en_cours",
    "waiting_external": "bloque",
    "waiting_human": "a_verifier",
    "retry_scheduled": "bloque",
    "completed": "termine",
    "failed": "bloque",
    "cancelled": "bloque",
}

def public_task(event, routing, title="Tache automatisee"):
    """Return a privacy-safe task compatible with src.supervisor.inspect."""
    return {
        "id": event["event_id"],
        "agent": AGENT_MAP.get(routing["agent"], "supervision"),
        "kind": event.get("type", "demande"),
        "title": title,
        "source": event.get("source", "runtime"),
        "status": STATUS_MAP.get(routing.get("status"), "a_verifier"),
    }
