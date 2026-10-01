"""Build and validate a client configuration without collecting secrets."""

ALLOWED_CHANNELS = {"email","calendar","phone","whatsapp","shopify"}

def build_client_config(name, client_id, timezone="Europe/Paris", channels=None):
    channels = channels or []
    invalid = set(channels) - ALLOWED_CHANNELS
    if invalid:
        raise ValueError("unsupported channels: " + ", ".join(sorted(invalid)))
    return {
        "client": {"id": client_id, "name": name, "timezone": timezone},
        "connectors": {c: {"enabled": c in channels} for c in sorted(ALLOWED_CHANNELS)},
        "automation": {
            "supervisor_interval_minutes": 5,
            "human_escalation": True,
            "dry_run_first": True,
        },
        "security": {
            "secrets_in_repository": False,
            "redact_logs": True,
        },
    }

def validate(config):
    errors = []
    client = config.get("client", {})
    if not client.get("id"): errors.append("client.id missing")
    if not client.get("name"): errors.append("client.name missing")
    if config.get("security", {}).get("secrets_in_repository") is not False:
        errors.append("repository secrets must be disabled")
    return {"ready": not errors, "errors": errors}
