"""Generer des sous-titres SRT a partir d'un script et de durees REELLES de plans.

Pas de reconnaissance vocale ni de synchronisation mot a mot : chaque plan
recoit un sous-titre couvrant sa duree. Aucune API ou publication.
Usage: python -m src.video_subtitles --manifest timing.json --output episode.srt
Manifeste: {"scenes":[{"duration_seconds":4.2,"text":"Bonjour..."}, ...]}
"""
import argparse
import json
from pathlib import Path


def stamp(seconds):
    if seconds < 0:
        raise ValueError("Horodatage negatif")
    milliseconds = round(seconds * 1000)
    hours, rest = divmod(milliseconds, 3600000)
    minutes, rest = divmod(rest, 60000)
    secs, ms = divmod(rest, 1000)
    return f"{hours:02d}:{minutes:02d}:{secs:02d},{ms:03d}"


def make_srt(scenes):
    if not scenes:
        raise ValueError("Scenes absentes")
    blocks = []
    elapsed = 0.0
    for index, scene in enumerate(scenes, 1):
        duration = float(scene["duration_seconds"])
        text = str(scene["text"]).strip()
        if not 0.1 <= duration <= 3600 or not text:
            raise ValueError(f"Scene {index} : duree ou texte invalide")
        # Un sous-titre par plan, sans pretendre a une synchronisation vocale.
        blocks.append(f"{index}\n{stamp(elapsed)} --> {stamp(elapsed + duration)}\n{text}\n")
        elapsed += duration
    return "\n".join(blocks) + "\n"


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    data = json.loads(Path(args.manifest).read_text(encoding="utf-8"))
    output = Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(make_srt(data["scenes"]), encoding="utf-8")
    print(f"Sous-titres crees : {output} (a verifier avec la narration)")


if __name__ == "__main__":
    main()
