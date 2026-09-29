# Réconciliation Shopify après interruption

Chaque création automatique reçoit un tag technique :
`vpauto:<24 caractères du SHA-256>`

Ce tag :
- est déterministe pour une même empreinte
- ne contient ni nom client, ni téléphone, ni référence source en clair
- permet de retrouver une création orpheline

Avant chaque création Shopify, le runner recherche ce tag.

Résultats :
- aucun produit : création DRAFT normale
- un produit : relecture + vérification + rattachement local, sans recréer
- plusieurs produits : arrêt avec erreur d'ambiguïté, aucune nouvelle création

Cette réconciliation protège le cas où Shopify a accepté la création mais où LWS s'est interrompu avant l'enregistrement de la preuve.
