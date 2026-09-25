"""Prepare or submit one Agnes Video 2.5 Flash task.

The default is an offline preview. No API key is stored in source code.
"""

import argparse
import json
import os
import time

import requests

MODEL = "agnes-video-2.5-flash"
API = "https://apihub.agnes-ai.com"


def build_request(prompt: str, seconds: int = 5, image_url: str | None = None) -> dict:
    if not prompt.strip():
        raise ValueError("Le scénario ne peut pas être vide")
    if not 4 <= seconds <= 12:
        raise ValueError("Durée autorisée : 4 à 12 secondes")
    if image_url and not image_url.startswith("https://"):
        raise ValueError("La photo doit avoir une adresse publique HTTPS")
    data = {
        "model": MODEL, "prompt": prompt.strip(), "seconds": str(seconds),
        "mode": "keyframe" if image_url else "text",
        "size": "720P", "aspect_ratio": "9:16",
    }
    if image_url:
        data["first_frame"] = image_url
    return data


def submit(data: dict, key: str) -> dict:
    response = requests.post(
        f"{API}/v1/videos", json=data,
        headers={"Authorization": f"Bearer {key}"}, timeout=45,
    )
    response.raise_for_status()
    task = response.json()
    video_id = task.get("video_id")
    if not video_id:
        raise RuntimeError("La réponse Agnes ne contient pas de video_id")
    for _ in range(120):
        time.sleep(3)
        reply = requests.get(
            f"{API}/agnesapi",
            params={"video_id": video_id, "model_name": MODEL},
            headers={"Authorization": f"Bearer {key}"}, timeout=30,
        )
        reply.raise_for_status()
        result = reply.json()
        if result.get("status") == "completed":
            return {"video_id": video_id, "url": result["url"], "status": "completed"}
        if result.get("status") == "failed":
            raise RuntimeError(f"Génération échouée : {result.get('error')}")
    raise TimeoutError(f"Tâche {video_id} toujours en cours après 6 minutes")


def main() -> None:
    parser = argparse.ArgumentParser(description="Préparer un clip vertical VOSGES PNEUS")
    parser.add_argument("--prompt", required=True)
    parser.add_argument("--image-url", help="URL HTTPS publique de la photo produit")
    parser.add_argument("--seconds", type=int, default=5)
    parser.add_argument("--submit", action="store_true", help="Créer réellement la vidéo")
    args = parser.parse_args()
    data = build_request(args.prompt, args.seconds, args.image_url)
    if not args.submit:
        print(json.dumps({"mode": "preview_only", "request": data}, ensure_ascii=False, indent=2))
        return
    key = os.environ.get("AGNES_API_KEY")
    if not key:
        parser.error("AGNES_API_KEY doit être défini hors du dépôt GitHub")
    print(json.dumps(submit(data, key), ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
