"""Central capability gates for external side effects."""

DEFAULTS = {
    "email": {"read": True, "send": False},
    "calendar": {"read": True, "write": False},
    "whatsapp": {"read": True, "send": False},
    "phone": {"read": True, "outbound": False},
    "shopify": {"read": True, "write": False},
}

def resolve(overrides=None):
    overrides = overrides or {}
    result = {name: values.copy() for name, values in DEFAULTS.items()}
    for channel, values in overrides.items():
        if channel not in result:
            raise ValueError("unsupported channel")
        for capability, enabled in values.items():
            if capability not in result[channel]:
                raise ValueError("unsupported capability")
            if not isinstance(enabled, bool):
                raise ValueError("capability must be boolean")
            result[channel][capability] = enabled
    return result

def enabled(config, channel, capability):
    return bool(config.get(channel, {}).get(capability, False))
