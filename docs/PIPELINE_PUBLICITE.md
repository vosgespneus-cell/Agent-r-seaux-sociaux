# Pipeline publicité VOSGES PNEUS

Chaîne de production retenue :

**Rush téléphone / photo réelle → nettoyage → vertical 9:16 → sous-titres → plans premium → vraie voix d'Adam → logo/CTA → contrôle → MP4**

## Principes

- Le PC réalise les opérations légères en local avec FFmpeg et les outils CPU
- Les modèles génératifs lourds restent optionnels et sont utilisés seulement pour quelques plans premium
- Les textes importants, prix, coordonnées et logo sont ajoutés au montage et non générés dans l'image
- La voix finale est la vraie voix d'Adam
- Aucun secret ni clé API n'est stocké dans GitHub

## Scripts Windows

- `INSTALL_VIDEO_TOOLS_WINDOWS.bat` : Kinocut
- `INSTALL_QMM_AUTOEDIT_WINDOWS.bat` : QMM AutoEdit
- `CREER_PUB_VOSGES_PNEUS.bat` : conversion rapide d'un rush en vertical 9:16
- `VERIFIER_VIDEO.bat` : contrôle technique avant publication

## Cible finale

TikTok, Facebook Reels, Instagram Reels et YouTube Shorts.
