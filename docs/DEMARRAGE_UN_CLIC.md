# Démarrage en un clic

## Première installation
Lancer `tools/INSTALLER_LA_MACHINE_VIDEO.bat`.

Ce lanceur prépare les outils légers retenus pour le PC sans installer de gros modèle génératif local.

## Utilisation quotidienne
Glisser un fichier MP4 ou MOV sur `tools/CREER_MA_PUB.bat`.

Le script :
1. analyse la source
2. crée une vidéo verticale 9:16 en 720 × 1280
3. normalise l'audio en AAC 48 kHz
4. encode en H.264
5. vérifie le résultat
6. ouvre l'Explorateur Windows sur le MP4 final

Les exports sont placés dans un dossier `VOSGES_PNEUS_EXPORTS` à côté de la vidéo source.

Le fichier source n'est jamais modifié.

## Suite prévue
Le bouton sera progressivement enrichi avec sous-titres automatiques, vraie voix d'Adam, logo, CTA et assemblage multi-plans.
