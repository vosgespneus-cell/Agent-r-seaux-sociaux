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

## Principe
1. recevoir un sujet ou produit
2. normaliser les informations
3. générer des variantes adaptées aux canaux
4. contrôler les mentions obligatoires et données manquantes
5. produire un paquet de publication
6. publier uniquement via une intégration autorisée
7. conserver un journal du résultat
