"""Crash recovery helpers for persistent actions."""

RECOVERABLE = {"proposed", "processing", "retry_scheduled", "waiting_external"}

def recoverable_actions(db):
    placeholders=",".join("?" for _ in RECOVERABLE)
    rows=db.execute(
        f"""SELECT action_id,event_id,type,payload,idempotency_key,authorization,
                   status,attempts,next_retry_at,last_error
            FROM actions WHERE status IN ({placeholders})
            ORDER BY rowid""",
        tuple(RECOVERABLE),
    ).fetchall()
    keys=("action_id","event_id","type","payload","idempotency_key",
          "authorization","status","attempts","next_retry_at","last_error")
    return [dict(zip(keys,row)) for row in rows]

def mark_interrupted_for_review(db):
    cur=db.execute(
        """UPDATE actions SET status='retry_scheduled',
           last_error=COALESCE(last_error,'interrupted_runtime')
           WHERE status='processing'"""
    )
    db.commit()
    return cur.rowcount

def already_completed(db, idempotency_key):
    if not idempotency_key:
        return False
    row=db.execute(
        "SELECT 1 FROM actions WHERE idempotency_key=? AND status='completed' LIMIT 1",
        (idempotency_key,),
    ).fetchone()
    return bool(row)
