# Pipeline produit autonome V1

Flux cible :

`entrée produit -> agent produits -> validation -> empreinte anti-doublon -> brouillon Shopify -> preuve -> contrôle`

## Conditions obligatoires
Un brouillon est rejeté s'il manque :
- titre
- description
- prix positif
- référence source

Le statut demandé est toujours forcé à `DRAFT`. Une demande contenant `publish=true` est rejetée par le validateur V1.

## Anti-doublon
Avant création, calculer un SHA-256 stable à partir de la référence source normalisée et des identifiants techniques disponibles. Enregistrer l'empreinte dans `vp_product_drafts`. La contrainte UNIQUE empêche une seconde création concurrente.

## Preuve
Après création réussie, enregistrer uniquement :
- identifiant produit Shopify
- handle Shopify
- état `created`

Ne jamais stocker de token Shopify, cookie, mot de passe ou donnée client dans ce registre.

## Étape suivante
Le connecteur d'exécution devra appeler Shopify avec le brouillon validé, puis écrire la preuve dans `vp_product_drafts`. La publication reste une capacité distincte et désactivée.
