"""Authenticated intake and private event journal. No customer data enters Git."""
import argparse
import hashlib
import hmac
import json
import os
import sqlite3
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

MAX_BODY = 64 * 1024
SOURCES = {"make", "contact", "telephone", "whatsapp", "atelier"}
KINDS = {
    "message": ("accueil", "demande", "Demande client reçue"),
    "appel": ("telephone", "rappel", "Appel reçu"),
    "rendez_vous": ("planning", "rendez_vous", "Demande de rendez-vous"),
    "produit": ("produits", "shopify", "Produit à examiner"),
    "media": ("communication", "publication", "Média à examiner"),
}


def _connect(path):
    path = Path(path)
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.exists() and path.is_symlink():
        raise ValueError("Le journal ne peut pas être un lien symbolique")
    conn = sqlite3.connect(path)
    os.chmod(path, 0o600)
    conn.execute("""CREATE TABLE IF NOT EXISTS events (
        id TEXT PRIMARY KEY, received_at TEXT NOT NULL, source TEXT NOT NULL,
        kind TEXT NOT NULL, body TEXT NOT NULL, task_id TEXT NOT NULL UNIQUE
    )""")
    return conn


def receive(raw: bytes, signature: str, secret: str, db_path: str) -> tuple[int, dict]:
    """Verify HMAC over exact request bytes, then retain event only in private SQLite."""
    if not secret or len(raw) > MAX_BODY:
        return 413 if len(raw) > MAX_BODY else 503, {"error": "requête indisponible"}
    expected = hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()
    if not hmac.compare_digest(signature, "sha256=" + expected):
        return 401, {"error": "signature invalide"}
    try:
        data = json.loads(raw)
        if not isinstance(data, dict) or not isinstance(data.get("event_id"), str):
            raise ValueError()
        event_id = data["event_id"]
        if not 1 <= len(event_id) <= 128 or not event_id.isascii():
            raise ValueError()
        source, kind = data["source"], data["kind"]
        if source not in SOURCES or kind not in KINDS:
            raise ValueError()
    except (ValueError, KeyError, TypeError, UnicodeDecodeError):
        return 400, {"error": "événement invalide"}
    # Opaque task ID: no name, phone number, message, or upstream ID is echoed.
    task_id = "VP-" + hashlib.sha256(event_id.encode()).hexdigest()[:20].upper()
    with _connect(db_path) as conn:
        cursor = conn.execute("INSERT OR IGNORE INTO events VALUES (?,?,?,?,?,?)", (
            event_id, datetime.now(timezone.utc).isoformat(), source, kind,
            raw.decode("utf-8"), task_id))
        if not cursor.rowcount:
            existing = conn.execute("SELECT task_id, body FROM events WHERE id=?", (event_id,)).fetchone()
            if not existing or not hmac.compare_digest(existing[1].encode("utf-8"), raw):
                return 409, {"error": "identifiant déjà utilisé"}
            return 200, {"task_id": existing[0], "duplicate": True}
    return 202, {"task_id": task_id, "duplicate": False}


def summary(db_path: str) -> dict:
    """Safe public-shaped view: fixed titles and opaque IDs, no raw content."""
    with _connect(db_path) as conn:
        rows = conn.execute("SELECT task_id,source,kind FROM events ORDER BY received_at").fetchall()
    return {"schema_version": 1, "tasks": [
        {"id": task_id, "source": source, "agent": KINDS[kind][0],
         "kind": KINDS[kind][1], "title": KINDS[kind][2],
         "status": "nouveau", "result": {}}
        for task_id, source, kind in rows]}


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        if self.path != "/events":
            self.send_error(404)
            return
        try:
            length = int(self.headers.get("Content-Length", "-1"))
        except ValueError:
            length = -1
        if length < 0 or length > MAX_BODY:
            self.send_error(413)
            return
        raw = self.rfile.read(length)
        status, response = receive(raw, self.headers.get("X-VP-Signature", ""),
                                   self.server.secret, self.server.db_path)
        encoded = json.dumps(response).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)

    def log_message(self, format, *args):
        pass  # HTTP paths, headers, and customer payloads must not enter logs.


def main():
    parser = argparse.ArgumentParser(description="Journal privé des événements VOSGES PNEUS")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=8765)
    parser.add_argument("--db", default="private/events.sqlite3")
    parser.add_argument("--summary", action="store_true")
    args = parser.parse_args()
    if args.summary:
        print(json.dumps(summary(args.db), ensure_ascii=False, indent=2))
        return
    secret = os.environ.get("VP_WEBHOOK_SECRET", "")
    if len(secret) < 32:
        parser.error("VP_WEBHOOK_SECRET doit contenir au moins 32 caractères aléatoires")
    server = ThreadingHTTPServer((args.host, args.port), Handler)
    server.secret, server.db_path = secret, args.db
    server.serve_forever()


if __name__ == "__main__":
    main()
