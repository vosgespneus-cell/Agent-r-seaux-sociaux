# Runtime pilote

Cette couche transforme l'architecture en composants exécutables.

- storage.py: SQLite local, événements, actions, audit, anti-doublon
- executor.py: barrière d'autorisation et retry
- health.py: état synthétique pour dashboard/surveillance

## Principes
Le runtime ne contient aucun secret.
Les connecteurs injectent leurs identifiants via l'environnement.
Une action externe exige une idempotency_key.
Les actions human_required restent bloquées.
Les erreurs temporaires sont retentées avec 1/5/15/60 minutes.

## Prochaine intégration
Brancher les adapters réels déjà/puis autorisés sur LWS: email, agenda, téléphone, WhatsApp et Shopify.
