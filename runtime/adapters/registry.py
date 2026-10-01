"""Adapter registry built from enabled channels."""
from .dry_run import DryRunAdapter

SUPPORTED = ("email", "calendar", "phone", "whatsapp", "shopify")

def dry_run_registry(channels=SUPPORTED):
    return {channel: DryRunAdapter(channel) for channel in channels}

def health(adapters):
    return {name: adapter.health_check() for name, adapter in adapters.items()}
