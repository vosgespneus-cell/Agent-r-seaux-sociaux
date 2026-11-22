# Studio video VOSGES PNEUS — mode d'emploi (brouillon technique)

## Ce qui existe dans le depot

- `src/youtube_weekly.py` : prepares sujets, scripts et descriptions.
- `src/video_asset_plan.py` : cree une liste de medias par scene.
- `src/video_preflight.py` : inventorie les outils disponibles localement.
- `src/video_voice.py` : pilote Piper local, si un modele vocal compatible et autorise est installe.
- `src/video_render.py` : assemble des **plans video reels** avec FFmpeg.
- `src/video_production.py` : enchaine la narration et le rendu.
- `src/video_subtitles.py` : cree un SRT par scene, sans synchronisation mot a mot.
- `tests/test_video_*.py` : controles unitaires et integration FFmpeg.
- `.github/workflows/video-tests.yml` : tests automatiques sans publication.

## Avant de lancer sur un PC

1. `python -m src.video_preflight` : diagnostic sans modification du PC.
2. `python -m unittest discover -s tests -p 'test_video_*.py' -v` : tests.
3. `python -m src.youtube_weekly --week 1 --output output/youtube` : script.
4. `python -m src.video_asset_plan --week 1 --output output/youtube` : besoins en medias.

**Aucune de ces commandes ne cree une vraie demonstration mecanique.**
Pour produire une video, fournir de vrais clips autorises, une narration, une
validation technique, les droits des medias et les logiciels locaux necessaires.

Exemple `production.json` dans un dossier contenant `scene_01.mp4`,
`scene_02.mp4` et `narration.txt` :

```json
{
  "clips": [{"path": "scene_01.mp4"}, {"path": "scene_02.mp4"}],
  "narration_text": "narration.txt",
  "technical_review_approved": false,
  "rights_approved": false
}
```

Les deux validations sont **false** par defaut. Elles ne doivent etre changees
qu'apres controle reel de la technique et des droits.

Avec Piper et un modele francais deja installes et autorises :
```bash
python -m src.video_production --manifest production.json --model voix/fr_FR.onnx --output episode.mp4
```

Le moteur n'est pas encore une usine autonome : pas de generation de scenes
mecaniques, pas de synchronisation vocale fine, pas de miniature automatique,
pas de publication YouTube, pas de controle qualite humain remplace.

## Principes

- Aucun abonnement, credit payant ou appel reseau requis pour ces scripts.
- Ne pas presenter une animation IA de geste dangereux comme procedure reelle.
- Conserver les originaux, ne jamais publier sans autorisation et validation.
- Ne pas confondre code commite, tests passes et rendu professionnel approuve.
