"""Small health snapshot used by dashboard/monitoring."""
from datetime import datetime, timezone

def snapshot(db, connectors=None):
    connectors=connectors or {}
    q=db.execute("SELECT COUNT(*) FROM actions WHERE status IN ('proposed','retry_scheduled')").fetchone()[0]
    h=db.execute("SELECT COUNT(*) FROM actions WHERE status='waiting_human'").fetchone()[0]
    e=db.execute("SELECT COUNT(*) FROM actions WHERE status='failed'").fetchone()[0]
    return {
      "service":"supervisor","timestamp":datetime.now(timezone.utc).isoformat(),
      "status":"healthy" if e==0 else "degraded",
      "queue_depth":q,"waiting_human":h,"failed":e,
      "connectors":connectors
    }
