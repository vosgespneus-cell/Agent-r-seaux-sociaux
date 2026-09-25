from .models import ContentRequest

UNKNOWN = (None, "", "à confirmer", "inconnu", "unknown")
REQUIRED = {
    "piece_auto": ("price", "reference", "condition", "availability"),
    "pneu": ("size", "price", "availability"),
    "service": ("description", "price"),
    "video": ("description",),
}
CHANNELS = {"facebook", "instagram", "tiktok", "youtube"}
VIDEO_CHANNELS = {"tiktok", "youtube"}


def missing_commercial_facts(req: ContentRequest) -> list[str]:
    required = REQUIRED.get(req.type)
    if required is None:
        return ["type (piece_auto, pneu, service ou video)"]
    missing = [key for key in required if req.facts.get(key) in UNKNOWN]
    if req.type == "piece_auto" and req.facts.get("compatibility") and not req.facts.get("compatibility_verified"):
        missing.append("compatibility_verified")
    if req.type == "video" and not req.assets:
        missing.append("assets")
    if req.notes and "ne pas publier" in req.notes.lower():
        missing.append("notes: ne pas publier")
    if not req.channels:
        missing.append("channels")
    for channel in req.channels:
        if channel not in CHANNELS:
            missing.append(f"channel: {channel}")
        if channel in VIDEO_CHANNELS and not req.assets:
            missing.append(f"asset video pour {channel}")
    return list(dict.fromkeys(missing))


def can_auto_publish(req: ContentRequest) -> tuple[bool, list[str]]:
    """Legacy name: reports editorial readiness only; never publishes anything."""
    missing = missing_commercial_facts(req)
    return not missing, missing
