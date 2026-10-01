"""Privacy-safe execution receipts.

Receipts prove that an action reached an external connector without copying
customer message bodies or credentials into public logs.
"""
from datetime import datetime, timezone

ALLOWED_PROOF_KEYS = {
    "message_id", "calendar_reference", "order_reference",
    "call_reference", "post_url", "external_id"
}

def build_receipt(action, result):
    proof = {
        key: result[key] for key in ALLOWED_PROOF_KEYS
        if key in result and result[key]
    }
    return {
        "action_id": action.get("action_id"),
        "event_id": action.get("event_id"),
        "channel": action.get("target", {}).get("channel"),
        "type": action.get("type"),
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "executed": bool(result.get("executed", False)),
        "proof": proof,
    }

def is_proven(receipt):
    return bool(receipt.get("executed") and receipt.get("proof"))
