"""Read-only supervisor for the shared VOSGES PNEUS task register.

The public repository must never contain customer names, phone numbers,
addresses, credentials, or private media links.
"""
import argparse
import json
from collections import Counter
from pathlib import Path

AGENTS = {
    "accueil", "telephone", "planning", "produits", "stock",
    "marketing", "communication", "gestion", "supervision"
}
STATUSES = {"nouveau", "en_cours", "bloque", "a_verifier", "termine"}
RESULT_KEYS = {"shopify": "product_url", "publication": "post_url",
               "rappel": "call_reference", "rendez_vous": "calendar_reference"}
SENSITIVE_KEYS = {"nom_client", "telephone_client", "email_client", "adresse_client",
                  "customer_name", "phone_number", "email", "password", "token"}


def inspect(register: dict) -> dict:
    errors, warnings = [], []
    tasks = register.get("tasks")
    if not isinstance(tasks, list):
        return {"ok": False, "errors": ["tasks doit être une liste"], "warnings": [], "counts": {}}
    ids = set()
    for index, task in enumerate(tasks):
        label = f"tasks[{index}]"
        if not isinstance(task, dict):
            errors.append(f"{label}: objet attendu")
            continue
        if task.get("id") in ids or not task.get("id"):
            errors.append(f"{label}: identifiant absent ou doublon")
        ids.add(task.get("id"))
        if task.get("agent") not in AGENTS:
            errors.append(f"{label}: agent inconnu")
        if task.get("status") not in STATUSES:
            errors.append(f"{label}: statut inconnu")
        if not task.get("title") or not task.get("source"):
            errors.append(f"{label}: titre et source requis")
        if task.get("status") == "termine":
            kind = task.get("kind")
            proof = RESULT_KEYS.get(kind)
            if proof and not task.get("result", {}).get(proof):
                errors.append(f"{label}: preuve {proof} requise avant termine")
        if task.get("status") == "bloque" and not task.get("blocker"):
            warnings.append(f"{label}: préciser le blocage")
        if task.get("status") == "a_verifier" and not task.get("reviewer"):
            warnings.append(f"{label}: désigner un responsable de vérification")
        present = SENSITIVE_KEYS.intersection(task)
        if present:
            errors.append(f"{label}: champs privés interdits dans le dépôt public")
    return {"ok": not errors, "errors": errors, "warnings": warnings,
            "counts": dict(Counter(t.get("status") for t in tasks if isinstance(t, dict)))}


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Contrôler un registre de tâches sans agir sur les plateformes")
    parser.add_argument("register")
    args = parser.parse_args()
    report = inspect(json.loads(Path(args.register).read_text(encoding="utf-8")))
    print(json.dumps(report, ensure_ascii=False, indent=2))
    raise SystemExit(0 if report["ok"] else 1)
