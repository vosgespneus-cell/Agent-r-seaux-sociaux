# Entrée produit vers Shopify

L'agent Produits reçoit uniquement les informations nécessaires à l'identification de la pièce.

Il produit un candidat JSON conforme au schéma `config/product_candidate.schema.json`.

Trois décisions existent :
- `ready` : données minimales suffisantes pour créer un brouillon
- `needs_information` : une donnée nécessaire manque
- `reject` : entrée inutilisable ou incohérente

Une empreinte SHA-256 stable est calculée avec la référence source et les références techniques triées. Elle sert à empêcher qu'une même pièce soit créée deux fois lorsque deux workers traitent la même entrée.

Le pipeline n'autorise que `DRAFT`. La publication reste une action distincte.
