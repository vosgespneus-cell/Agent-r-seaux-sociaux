# Outils vidéo locaux — VOSGES PNEUS

## Objectif

Conserver la génération IA lourde dans le cloud quand nécessaire, mais réaliser localement le maximum de travail sans crédits : découpe, assemblage, recadrage vertical, audio, sous-titres, contrôles et export.

## Kinocut

Kinocut est un outil local open source basé sur FFmpeg. Il fournit une CLI et des outils structurés pour automatiser des montages vidéo, notamment le découpage, le redimensionnement, les sous-titres et la préparation de Shorts/Reels/TikTok.

Installation Windows préparée dans :

`tools/INSTALL_VIDEO_TOOLS_WINDOWS.bat`

Le script :
1. vérifie Python
2. vérifie la présence de FFmpeg dans le PATH
3. met pip à jour
4. installe ou met à jour `kinocut`
5. lance `kino doctor`

## Architecture retenue

- Génération IA de quelques plans premium : service cloud lorsque des crédits sont disponibles
- Montage et transformations : local
- Format final : 9:16
- Voix : véritable voix d'Adam enregistrée séparément
- Textes/logo : ajoutés au montage pour éviter les erreurs de texte des générateurs vidéo
- Export : MP4 pour TikTok, Reels et Shorts

## Limitation de la machine actuelle

Le PC identifié utilise Intel HD Graphics 4400. Les gros modèles modernes de génération vidéo IA ne sont donc pas la cible de cette machine. Le poste reste en revanche adapté au montage FFmpeg/CPU et aux automatisations légères.

## Sécurité

Ne jamais stocker de clé API réelle dans GitHub. Utiliser les variables d'environnement et conserver uniquement des exemples de configuration sans secret.
