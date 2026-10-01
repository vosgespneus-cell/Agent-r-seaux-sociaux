"""Final safety gate applied immediately before any external execution."""

ALWAYS_HUMAN = {
    "refund",
    "payment",
    "purchase",
    "delete",
    "dns_change",
    "credential_change",
    "contract_commitment",
}

def evaluate(action):
    action_type = action.get("type")
    if action_type in ALWAYS_HUMAN:
        return {
            "allowed": False,
            "authorization": "human_required",
            "reason": "sensitive_action",
        }
    if action.get("authorization") == "human_required":
        return {
            "allowed": False,
            "authorization": "human_required",
            "reason": "requested_human_review",
        }
    if not action.get("idempotency_key"):
        return {
            "allowed": False,
            "authorization": action.get("authorization"),
            "reason": "missing_idempotency_key",
        }
    return {
        "allowed": action.get("authorization") in ("automatic","rule_required"),
        "authorization": action.get("authorization"),
        "reason": "authorized" if action.get("authorization") in ("automatic","rule_required") else "not_authorized",
    }
