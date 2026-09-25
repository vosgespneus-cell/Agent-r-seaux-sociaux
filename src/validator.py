from .models import ContentRequest

SENSITIVE_FACTS = ("price", "reference", "availability")

def missing_commercial_facts(req: ContentRequest) -> list[str]:
    missing = []
    for key in SENSITIVE_FACTS:
        if key in req.facts and req.facts.get(key) in (None, "", "à confirmer"):
            missing.append(key)
    return missing

def can_auto_publish(req: ContentRequest) -> tuple[bool, list[str]]:
    missing = missing_commercial_facts(req)
    return (len(missing) == 0, missing)
