"""Safe action executor boundary."""
from datetime import datetime, timedelta, timezone

RETRY_MINUTES=(1,5,15,60)

def can_execute(action):
    return action.get("authorization") in ("automatic","rule_required") and bool(action.get("idempotency_key"))

def execute(action, adapters):
    if not can_execute(action):
        return {"status":"waiting_human","error":None}
    adapter=adapters.get(action.get("target",{}).get("channel"))
    if not adapter:
        return {"status":"failed","error":"adapter_unavailable"}
    try:
        result=adapter.execute_action(action)
        return {"status":"completed","result":result,"error":None}
    except Exception as exc:
        return {"status":"retry_scheduled","error":type(exc).__name__}

def next_retry(attempt):
    if attempt >= len(RETRY_MINUTES):
        return None
    return datetime.now(timezone.utc)+timedelta(minutes=RETRY_MINUTES[attempt])
