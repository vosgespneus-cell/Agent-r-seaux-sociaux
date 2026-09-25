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

## Principe
1. recevoir un sujet ou produit
2. normaliser les informations
3. générer des variantes adaptées aux canaux
4. contrôler les mentions obligatoires et données manquantes
5. produire un paquet de publication
6. publier uniquement via une intégration autorisée
7. conserver un journal du résultat
