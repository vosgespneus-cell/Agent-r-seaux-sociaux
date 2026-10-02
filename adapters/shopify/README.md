# Shopify adapter — VOSGES PNEUS

Ce module est la frontière entre le superviseur et Shopify. La logique métier reste dans les agents; cet adapter traduit uniquement les événements et actions.

## Mode initial
`draft_only`

Le système peut préparer une fiche produit, contrôler les champs nécessaires, lire/normaliser une commande et préparer une mise à jour de stock. Il ne publie pas automatiquement une fiche et n'effectue aucune opération financière tant que le connecteur réel n'a pas été validé de bout en bout.

## Fiche produit minimale
- titre
- référence
- prix
- état du stock
- description

Pour les pièces automobiles, ajouter si disponible : compatibilité véhicule, photos, provenance, état et références constructeur.

Pour les pneus, ajouter si disponible : largeur, hauteur, diamètre, indices charge/vitesse, saison, marque et modèle.

## Sécurité
Les identifiants Shopify viennent uniquement des variables d'environnement. Aucun token dans le dépôt, les logs, les événements ou les messages d'erreur.

Une fiche dont le prix ou la compatibilité n'est pas vérifié reste en brouillon et génère une tâche de contrôle.

Les remboursements, captures de paiement, suppressions et changements de prix en production restent bloqués par défaut.

## Contrat
Voir `contract.json`. Toute implémentation future doit conserver les clés d'idempotence afin qu'un retry ne crée jamais deux produits ou deux actions identiques.
