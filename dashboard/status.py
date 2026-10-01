"""Simple operator-facing action status."""
from runtime.receipts import is_proven

def action_status(action, receipt=None):
    if action.get("status") == "waiting_human":
        return {"label":"A VERIFIER","trusted":True}
    if receipt and is_proven(receipt):
        return {"label":"EXECUTE","trusted":True}
    if action.get("status") in ("proposed","queued","processing","retry_scheduled"):
        return {"label":"PREPARE","trusted":True}
    if action.get("status") == "completed":
        return {"label":"A VERIFIER","trusted":False}
    return {"label":"A VERIFIER","trusted":False}
