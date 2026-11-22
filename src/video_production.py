"""Orchestrer localement narration Piper + montage de vrais plans verifies.

Usage:
 python -m src.video_production --manifest dossier/production.json --model voix/fr_FR.onnx --output dossier/episode.mp4

Manifest:
 {
  "clips":[{"path":"scene1.mp4"}],
  "narration_text":"narration.txt",
  "technical_review_approved":true,
  "rights_approved":true
 }

Aucune API, aucun abonnement, aucune publication. Le rendu n'est pas lance si
les validations sont absentes. Les chemins relatifs sont relatifs au manifeste.
"""
import argparse
import json
from pathlib import Path

from src.video_render import render, validate_manifest
from src.video_voice import synthesize


def produce(manifest_path, model_path, output_path):
    manifest = Path(manifest_path).resolve()
    data = json.loads(manifest.read_text(encoding="utf-8"))
    # Controle avant synthese pour eviter toute operation inutile.
    validate_manifest(data, manifest.parent)
    narration_name = data.get("narration_text")
    if not narration_name:
        raise ValueError("narration_text est obligatoire")
    narration = (manifest.parent / narration_name).resolve()
    if not narration.is_file():
        raise ValueError("Texte de narration introuvable")
    audio = manifest.parent / "narration_piper.wav"
    synthesize(narration, model_path, audio)
    # Le fichier original n'est jamais modifie. La copie est dans le meme dossier
    # pour conserver la resolution des chemins de clips.
    assembled = dict(data, voice=audio.name)
    temporary_manifest = manifest.parent / "production_with_voice.generated.json"
    temporary_manifest.write_text(json.dumps(assembled, ensure_ascii=False, indent=2), encoding="utf-8")
    try:
        return render(temporary_manifest, output_path)
    finally:
        temporary_manifest.unlink(missing_ok=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    print(f"Rendu local cree : {produce(args.manifest, args.model, args.output)}")


if __name__ == "__main__":
    main()
