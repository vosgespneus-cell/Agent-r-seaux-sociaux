# Agent Réseaux Sociaux — VOSGES PNEUS

Base technique de l'agent de contenu et de publication multicanal de VOSGES PNEUS.

## Objectif
Transformer les informations commerciales (pneus, pièces d'occasion, atelier, promotions, photos et vidéos) en contenus prêts à publier, avec validation, journalisation et automatisations contrôlées.

## Architecture
- `src/` logique de l'agent
- `config/` paramètres éditoriaux et canaux
- `prompts/` règles de génération
- `data/` modèles d'entrée/sortie
- `.github/workflows/` contrôles automatiques

## Sécurité
Aucun mot de passe, token Meta/TikTok, clé OpenAI, secret Shopify ou webhook privé ne doit être commité. Utiliser les variables d'environnement / GitHub Secrets.

## Démarrer

```bash
pip install -r requirements.txt
python -m src.main data/content.example.json --output output/exemple.json
python -m unittest discover -s tests -v
```

Le paquet JSON contient un brouillon par canal et la liste des données manquantes.
`ready_for_review` signifie que les champs essentiels sont renseignés : cela ne certifie
pas leur exactitude. Vérifier le prix, le stock, les références, la compatibilité et
les droits sur les images/vidéos avant toute publication. `publication_allowed` reste
toujours `false` : aucune intégration de publication n'est activée ici.

Types acceptés : `piece_auto`, `pneu`, `service`, `video`. Canaux acceptés :
`facebook`, `instagram`, `tiktok`, `youtube`. Une vidéo nécessite un asset.
Marketplace est désactivé tant qu'aucune intégration autorisée n'est disponible.

## Clips vidéo Agnes

Le tutoriel TikTok de septembre 2026 montre `agnes-video-v2.0`, annoncé en fin
de service le 25 septembre 2026. Le module `src/video_agnes.py` utilise
`agnes-video-2.5-flash` en 720P. Sa gratuité est une promotion temporaire :
vérifier les conditions du compte avant d'envoyer une demande. Ne jamais
sélectionner `agnes-video-2.5` par erreur : ce modèle standard est payant.

```bash
python -m src.video_agnes --prompt "Plan vertical d'un pneu en atelier, lumière naturelle, rotation lente"
```

Cette commande affiche la demande sans appeler l'API. Pour une vidéo réelle,
définir `AGNES_API_KEY` dans l'environnement local, puis relancer avec
`--submit`. Ajouter `--image-url https://.../photo.jpg` pour animer une photo
accessible publiquement. Les photos présentes seulement sur le PC ou dans un
Drive privé ne sont pas accessibles par l'API. La sortie contient un lien vidéo,
à vérifier avant montage et publication. Ne pas placer de clé dans un fichier
HTML téléchargé ou dans ce dépôt public.

## Principe
1. recevoir un sujet ou produit
2. normaliser les informations
3. générer des variantes adaptées aux canaux
4. contrôler les mentions obligatoires et données manquantes
5. produire un paquet de publication
6. publier uniquement via une intégration autorisée
7. conserver un journal du résultat

## Entrées privées

Le récepteur signé et son journal local sont documentés dans
[`docs/ENTREES_PRIVEES.md`](docs/ENTREES_PRIVEES.md). Les mails de la boîte atelier et Jotform sont raccordés sur LWS. L'analyse IA des
mails et la préparation des rendez-vous fonctionnent sur le serveur privé. Le pont
Google Agenda tourne toutes les cinq minutes ; l'ajout d'un prochain rendez-vous
complet reste à vérifier de bout en bout. Shopify et les publications automatiques
restent à raccorder au moteur central.

## Suivi privé sur LWS

`lws/vp_run.php` enregistre les passages des agents existants, leurs codes de sortie
et la dernière réussite. `lws/vp_monitor.php` produit des compteurs et une vue HTML
privée, renouvelés toutes les cinq minutes. Les données de suivi, configurations,
clés et messages clients restent hors du dépôt et hors de la racine publique.
Voir [`docs/SUIVI_LWS.md`](docs/SUIVI_LWS.md).

WhatsApp est préparé mais sa réception et ses envois restent désactivés en attente
du raccordement Meta. La téléphonie n'est pas encore raccordée.
