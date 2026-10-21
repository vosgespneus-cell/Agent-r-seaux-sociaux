"""Private session/mail correlation for a future LWS adapter. No HTTP endpoint.

Only a trusted mail worker may call receive(). It must authenticate the mail
source and parse/validate its actual format before this layer. A mail price is
not a verified supplier price. No checkout or supplier order is created here.
"""
import hashlib
import hmac
import re
import secrets
import sqlite3
from pathlib import Path


class Sessions:
    def __init__(self, database, secret):
        if not isinstance(secret, bytes) or len(secret) < 32:
            raise ValueError("application_secret_required")
        self.secret = secret
        path = Path(database)
        self.db = sqlite3.connect(path)
        path.chmod(0o600)
        self.db.execute("PRAGMA foreign_keys=ON")
        self.db.executescript('''
        CREATE TABLE IF NOT EXISTS sessions (
          token_hash TEXT PRIMARY KEY, marker TEXT UNIQUE NOT NULL,
          email_hash TEXT NOT NULL, created INTEGER NOT NULL, expires INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS selections (
          message_id TEXT PRIMARY KEY, marker TEXT NOT NULL,
          title TEXT NOT NULL, reference TEXT NOT NULL, quantity INTEGER NOT NULL,
          unit_cents INTEGER NOT NULL,
          FOREIGN KEY(marker) REFERENCES sessions(marker));
        ''')

    def email_hash(self, email):
        if not isinstance(email, str):
            raise ValueError("email_required")
        email = email.strip().casefold()
        if len(email) > 254 or not re.fullmatch(r"[^\s@]+@[^\s@]+\.[^\s@]+", email):
            raise ValueError("invalid_email")
        return hmac.new(self.secret, email.encode(), hashlib.sha256).hexdigest()

    def start(self, email, now):
        email_hash = self.email_hash(email)
        token = secrets.token_urlsafe(32)
        marker = "VP-" + secrets.token_hex(16).upper()
        with self.db:
            self.db.execute("INSERT INTO sessions VALUES(?,?,?,?,?)", (
                hashlib.sha256(token.encode()).hexdigest(), marker, email_hash, now, now+1800))
        # HTTP adapter must keep token in a Secure/HttpOnly/SameSite cookie,
        # never in URL, email, browser logs, or public HTML.
        return {"session_token": token, "comment_marker": marker, "expires_at": now+1800}

    def receive(self, *, message_id, marker, customer_email, title, reference,
                quantity, unit_cents, received_at, now):
        """Accept a normalized selection from the PRIVATE trusted mail worker.

        marker must be present verbatim in the Camodo comment. Email alone is
        deliberately insufficient. All customer-facing prices stay unverified.
        """
        if (not isinstance(message_id, str) or not 1 <= len(message_id) <= 512 or
            not re.fullmatch(r"VP-[A-F0-9]{32}", marker)):
            raise ValueError("invalid_mail_metadata")
        if not all(isinstance(s, str) and 1 <= len(s.strip()) <= 250 for s in (title,reference)):
            raise ValueError("missing_selection")
        if type(quantity) is not int or not 1 <= quantity <= 24:
            raise ValueError("invalid_quantity")
        if type(unit_cents) is not int or not 0 < unit_cents <= 10000000:
            raise ValueError("invalid_price")
        with self.db:
            row = self.db.execute("SELECT email_hash,created,expires FROM sessions WHERE marker=?",
                                  (marker,)).fetchone()
            if not row or not hmac.compare_digest(row[0],self.email_hash(customer_email)):
                return "unmatched"
            if not row[1] <= received_at <= now < row[2]:
                return "expired_or_old"
            previous = self.db.execute("SELECT marker,title,reference,quantity,unit_cents FROM selections WHERE message_id=?",
                                       (message_id,)).fetchone()
            values = (marker,title,reference,quantity,unit_cents)
            if previous:
                return "duplicate" if previous == values else "conflict"
            self.db.execute("INSERT INTO selections VALUES(?,?,?,?,?,?)", (message_id,*values))
        return "received"

    def status(self, token, now):
        row = self.db.execute("SELECT marker,created,expires FROM sessions WHERE token_hash=?",
                              (hashlib.sha256(token.encode()).hexdigest(),)).fetchone()
        if not row or now >= row[2]:
            return {"state":"expired", "payment_enabled":False}
        selections = self.db.execute("SELECT title,reference,quantity,unit_cents FROM selections WHERE marker=?",
                                     (row[0],)).fetchall()
        if not selections:
            return {"state":"waiting" if now-row[1] < 90 else "delayed",
                    "payment_enabled":False}
        # Never silently choose among multiple requests for the same session.
        if len(selections) != 1:
            return {"state":"needs_review", "payment_enabled":False}
        title,reference,quantity,unit = selections[0]
        return {"state":"selection_received", "title":title, "reference":reference,
                "quantity":quantity, "mail_unit_ttc_cents":unit,
                "supplier_recheck_required":True, "payment_enabled":False}

    def purge(self, now):
        with self.db:
            self.db.execute("DELETE FROM selections WHERE marker IN (SELECT marker FROM sessions WHERE expires<=?)", (now,))
            self.db.execute("DELETE FROM sessions WHERE expires<=?", (now,))

    def close(self):
        self.db.close()
