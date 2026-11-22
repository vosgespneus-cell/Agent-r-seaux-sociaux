"""Creer un plan de tournage et une liste de medias pour un episode existant.

Usage: python -m src.video_asset_plan --week 1 --output output/youtube
Aucune generation IA, connexion reseau, depense ou publication.
"""
import argparse
import json
from pathlib import Path
from src.youtube_weekly import build


def create_plan(week):
    episode = build(week)
    shots = []
    for scene in episode["scenes"]:
        shots.append({
            "scene": scene["index"],
            "narration": scene["narration"],
            "required_media": [],
            "media_source": "real_workshop_or_verified_licensed_media",
            "technical_accuracy_checked": False,
            "usage_rights_checked": False,
            "visual_status": "missing",
            "notes": "Aucune demonstration de geste technique generee sans controle."
        })
    return {
        "episode_title": episode["title"],
        "episode_week": week,
        "target_format": {"youtube": "1920x1080", "shorts": "1080x1920"},
        "shots": shots,
        "release_gate": {
            "all_visuals_present": False,
            "all_rights_verified": False,
            "technical_review_passed": False,
            "narration_review_passed": False,
            "render_review_passed": False,
            "publish_allowed": False
        }
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--week", type=int, default=1)
    parser.add_argument("--output", default="output/youtube")
    args = parser.parse_args()
    plan = create_plan(args.week)
    target = Path(args.output) / f"semaine_{args.week:02d}"
    target.mkdir(parents=True, exist_ok=True)
    output = target / "plan_medias.json"
    output.write_text(json.dumps(plan, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Plan cree : {output} (non approuve, non publie)")


if __name__ == "__main__":
    main()
