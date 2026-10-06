"""Private commercial register -> ranked tasks. No external sends or bookings."""
import argparse
import json
from datetime import datetime, timezone
from decimal import Decimal
from pathlib import Path

def build(state, now=None):
    now = now or datetime.now(timezone.utc)
    tasks, seen = [], set()
    def add(key, agent, title, priority, reason):
        if key not in seen:
            seen.add(key)
            tasks.append(dict(id=key, agent=agent, title=title, priority=priority,
                              reason=reason, status="nouveau", source="pilotage_commercial"))
    receipts = state.get("receipts", [])
    paid, receipt_ids = Decimal("0"), set()
    for receipt in receipts:
        key = receipt["id"]
        if key in receipt_ids:
            raise ValueError("Duplicate receipt")
        receipt_ids.add(key)
        amount = Decimal(str(receipt["amount_eur"]))
        if not amount.is_finite() or amount < 0:
            raise ValueError("Invalid receipt amount")
        if receipt.get("status") == "paid" and receipt.get("payment_reference"):
            paid += amount
    for lead in state.get("leads", []):
        if lead.get("status") in {"won", "lost", "cancelled"}:
            continue
        key = lead["id"]
        stamp = datetime.fromisoformat(lead["updated_at"].replace("Z", "+00:00"))
        if stamp.tzinfo is None:
            raise ValueError("Timezone required")
        age = (now - stamp).total_seconds() / 3600
        if lead.get("status") == "new":
            add("qualify:" + key, "accueil", "Qualifier la demande et proposer une prochaine étape", 100,
                "Demande client entrante")
        elif age >= 24:
            add("followup:" + key, "gestion", "Préparer le suivi de la demande en attente", 90,
                "Demande sans progression depuis au moins 24 heures")
    for item in state.get("products", []):
        if item.get("stock", 0) <= 0:
            continue
        verified = all(item.get(k) for k in ("reference_verified", "condition_verified",
                                             "price_verified", "compatibility_verified", "real_photos"))
        if not verified:
            add("verify:" + item["id"], "produits", "Compléter les preuves de la pièce avant mise en vente", 75,
                "Stock disponible mais fiche incomplète")
        elif not item.get("live_url"):
            add("list:" + item["id"], "produits", "Préparer la fiche de la pièce disponible", 80,
                "Référence, état, prix, compatibilité et photos vérifiés")
        else:
            add("promote:" + item["id"], "communication", "Préparer une publication liée à la fiche disponible", 60,
                "Produit vérifié et accessible")
    if state.get("tariff_conflicts"):
        add("tariffs", "gestion", "Harmoniser les tarifs par canal avant diffusion", 110,
            "Écart entre prix direct et prix partenaire")
    if state.get("calendar_verified") and state.get("workshop_capacity_verified") and state.get("free_slots"):
        add("workshop", "marketing", "Préparer une offre atelier vers les créneaux réellement libres", 70,
            "Disponibilité atelier et agenda vérifiés")
    for service in state.get("connectors", []):
        if service.get("stale") or service.get("error"):
            add("health:" + service["id"], "supervision", "Vérifier le connecteur en défaut", 105,
                "Activité absente ou erreur signalée")
    return dict(generated_at=now.isoformat(), paid_eur=str(paid),
                paid_note="Encaissements avec référence uniquement ; ni bénéfice ni prévision",
                tasks=sorted(tasks, key=lambda t: (-t["priority"], t["id"])),
                external_actions_executed=False)

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("private_register")
    args = parser.parse_args()
    print(json.dumps(build(json.loads(Path(args.private_register).read_text())), ensure_ascii=False, indent=2))
