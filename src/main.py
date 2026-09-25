"""Prepare factual, channel-specific copy. No network calls or publishing."""

import argparse
import json
from pathlib import Path
from .models import ContentRequest
from .validator import can_auto_publish


def load_request(path: str) -> ContentRequest:
    return ContentRequest.model_validate_json(Path(path).read_text(encoding="utf-8"))


def post_text(req: ContentRequest, channel: str) -> str:
    facts = req.facts
    lines = [req.title.strip()]
    labels = {
        "reference": "Référence", "size": "Dimension", "condition": "État",
        "description": "Détails", "price": "Prix", "availability": "Disponibilité",
    }
    for key, label in labels.items():
        value = facts.get(key)
        if value is not None and str(value).strip().lower() not in ("", "à confirmer", "inconnu"):
            lines.append(f"{label} : {value}")
    if facts.get("compatibility") and facts.get("compatibility_verified"):
        lines.append(f"Compatibilité vérifiée : {facts['compatibility']}")
    if req.cta:
        lines.append(req.cta.strip())
    if channel == "instagram":
        lines.append("#VosgesPneus #Epinal")
    elif channel in ("tiktok", "youtube"):
        lines.append("Vidéo VOSGES PNEUS")
    return "\n".join(lines)


def prepare(path: str) -> dict:
    req = load_request(path)
    ready, missing = can_auto_publish(req)
    return {
        "title": req.title,
        "mode": "ready_for_review" if ready else "blocked",
        "publication_allowed": False,
        "missing_fields": missing,
        "posts": [
            {"channel": channel, "text": post_text(req, channel),
             "status": "draft" if ready else "blocked"}
            for channel in req.channels
        ],
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Préparer des brouillons VOSGES PNEUS")
    parser.add_argument("request", help="Fichier JSON de demande")
    parser.add_argument("--output", help="Chemin du paquet JSON de sortie")
    args = parser.parse_args()
    result = json.dumps(prepare(args.request), ensure_ascii=False, indent=2) + "\n"
    if args.output:
        target = Path(args.output)
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(result, encoding="utf-8")
    else:
        print(result, end="")
