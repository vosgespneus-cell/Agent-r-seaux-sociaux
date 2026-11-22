"""Préparer un épisode pédagogique sans API, abonnement ni publication.

Usage: python -m src.youtube_weekly --week 1 --output output/youtube
La sortie est un dossier de production, PAS une vidéo ni une publication.
"""
import argparse
import json
from pathlib import Path

EPISODES = [
    {
        "title": "Filtre à huile : 5 erreurs à éviter avant une vidange",
        "topic": "filtre à huile",
        "hook": "Changer un filtre à huile paraît simple. Voici cinq erreurs à connaître avant de commencer.",
        "points": [
            "Vérifier la référence du filtre selon le véhicule, le moteur et la documentation constructeur.",
            "Identifier s'il s'agit d'une cartouche ou d'un filtre vissé : les procédures diffèrent.",
            "Ne pas réutiliser un joint abîmé et respecter les indications de lubrification du fabricant.",
            "Ne pas improviser le serrage : respecter la procédure et les couples indiqués pour le véhicule.",
            "Après intervention, contrôler le niveau, l'étanchéité et éliminer l'huile usagée en filière adaptée.",
        ],
        "warning": "Démonstration pédagogique : la procédure exacte dépend du véhicule. Ne pas travailler sous un véhicule non sécurisé.",
    },
    {
        "title": "Rotule à la presse : comprendre les risques et les bons contrôles",
        "topic": "rotule et presse",
        "hook": "Une presse hydraulique développe des efforts considérables. Le positionnement et la sécurité ne s'improvisent pas.",
        "points": [
            "Identifier précisément la rotule et vérifier si elle est remplaçable séparément.",
            "Consulter les préconisations constructeur et choisir les outillages adaptés.",
            "Repérer les zones d'appui prévues et les risques de projection ou de rupture.",
            "Ne jamais utiliser de cales improvisées ni placer une main dans la zone de compression.",
            "Faire contrôler la pièce remontée et les éléments de suspension selon la procédure constructeur.",
        ],
        "warning": "Opération à risque : aucune simulation IA ne doit servir de modèle de positionnement réel. Intervention réservée aux personnes équipées et formées.",
    },
    {
        "title": "Freins : comment reconnaître des plaquettes et disques usés ?",
        "topic": "usure des freins",
        "hook": "Bruit, vibration, témoin : tous les signes n'ont pas la même signification.",
        "points": [
            "Reconnaître les principaux signes d'alerte sans conclure à distance.",
            "Distinguer l'usure des plaquettes de celle des disques.",
            "Comprendre pourquoi les seuils d'usure dépendent des références constructeur.",
            "Savoir quand arrêter de rouler et demander une vérification professionnelle.",
            "Éviter toute intervention sans méthode de levage et de serrage validée.",
        ],
        "warning": "Les freins sont un organe de sécurité. Un diagnostic et une réparation doivent suivre les données constructeur.",
    },
]

def build(week: int) -> dict:
    if not 1 <= week <= len(EPISODES):
        raise ValueError(f"Semaine disponible : 1 à {len(EPISODES)}")
    e = EPISODES[week - 1]
    scenes = [{"index": 1, "narration": e["hook"], "visual": "Titre animé et schéma pédagogique, sans geste technique simulé"}]
    scenes += [
        {"index": i + 2, "narration": point, "visual": "Schéma ou photo vérifiée à sélectionner ; pas de geste mécanique généré sans validation"}
        for i, point in enumerate(e["points"])
    ]
    scenes.append({"index": len(scenes) + 1, "narration": e["warning"] + " Quel sujet voulez-vous voir ensuite ? Dites-le en commentaire.", "visual": "Écran sécurité et appel aux commentaires"})
    return {
        "brand": "VOSGES PNEUS", "series": "La mécanique expliquée simplement",
        "week": week, "title": e["title"], "topic": e["topic"],
        "youtube_description": e["hook"] + "\n\n" + "\n".join(f"• {p}" for p in e["points"]) + "\n\n" + e["warning"] + "\n\nVOSGES PNEUS — Thaon-les-Vosges. Abonnez-vous et proposez le prochain sujet.\n#MecaniqueAuto #VosgesPneus",
        "scenes": scenes,
        "shorts": [
            {"title": "L'erreur à éviter", "source_scene": 2},
            {"title": "Le contrôle important", "source_scene": 4},
            {"title": "Le conseil sécurité", "source_scene": len(scenes)},
        ],
        "production_status": "script_draft_requires_technical_review",
        "technical_review_approved": False,
        "visual_rights_approved": False,
        "video_rendered": False,
        "publication_allowed": False,
        "youtube_uploaded": False,
    }

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--week", type=int, default=1)
    p.add_argument("--output", default="output/youtube")
    a = p.parse_args()
    package = build(a.week)
    out = Path(a.output) / f"semaine_{a.week:02d}"
    out.mkdir(parents=True, exist_ok=True)
    (out / "episode.json").write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    (out / "narration.txt").write_text("\n\n".join(s["narration"] for s in package["scenes"]) + "\n", encoding="utf-8")
    (out / "description_youtube.txt").write_text(package["youtube_description"] + "\n", encoding="utf-8")
    print(f"Dossier préparé : {out} (brouillon, non publié)")

if __name__ == "__main__":
    main()
