# Garantie anti-doublon Shopify

Avant l'appel externe, le runner verrouille l'empreinte produit dans `vp_product_drafts`.

Si cette empreinte possède déjà un produit Shopify créé, aucun second appel n'est effectué.

Pour une nouvelle empreinte :
1. enregistrer l'intention validée
2. terminer la transaction DB
3. créer le produit Shopify en DRAFT
4. relire le produit par son GID
5. vérifier la preuve
6. enregistrer GID, handle et `proof_verified=1`
7. marquer l'entrée atelier `draft_created`

Important : une panne réseau exactement entre les étapes 3 et 6 reste un cas délicat. Avant mise en production massive, le mécanisme de réconciliation devra rechercher une création orpheline avant toute nouvelle tentative.
