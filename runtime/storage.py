"""Persistent local state for the pilot runtime. No secrets are stored here."""
import json, sqlite3
from datetime import datetime, timezone

SCHEMA = """
CREATE TABLE IF NOT EXISTS events (
 event_id TEXT PRIMARY KEY, source TEXT, source_id TEXT, type TEXT,
 priority TEXT, payload TEXT, status TEXT, received_at TEXT,
 UNIQUE(source, source_id)
);
CREATE TABLE IF NOT EXISTS actions (
 action_id TEXT PRIMARY KEY, event_id TEXT, type TEXT, payload TEXT,
 idempotency_key TEXT UNIQUE, authorization TEXT, status TEXT,
 attempts INTEGER DEFAULT 0, next_retry_at TEXT, last_error TEXT
);
CREATE TABLE IF NOT EXISTS audit (
 id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT, kind TEXT,
 object_id TEXT, message TEXT
);
"""

def connect(path="runtime.db"):
    db=sqlite3.connect(path)
    db.executescript(SCHEMA)
    return db

def audit(db, kind, object_id, message):
    db.execute("INSERT INTO audit(ts,kind,object_id,message) VALUES(?,?,?,?)",
      (datetime.now(timezone.utc).isoformat(),kind,object_id,message))
    db.commit()

def enqueue_event(db,event):
    try:
        db.execute("""INSERT INTO events(event_id,source,source_id,type,priority,payload,status,received_at)
        VALUES(?,?,?,?,?,?,?,?)""",(event["event_id"],event["source"],event.get("source_id"),
        event["type"],event.get("priority","normal"),json.dumps(event.get("payload",{})),
        "received",event["received_at"]))
        db.commit(); audit(db,"event",event["event_id"],"received"); return True
    except sqlite3.IntegrityError:
        return False
