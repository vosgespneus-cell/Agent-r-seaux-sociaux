# Client Shopify Admin privé

Le client LWS utilise l'API GraphQL Admin Shopify avec une version explicitement fixée.

Configuration privée requise dans `vp_shopify_config.php`, hors `htdocs` et hors GitHub :
- domaine permanent `*.myshopify.com`
- jeton Admin privé avec le droit minimal nécessaire
- version API stable

Le client ne possède volontairement aucune méthode de publication ou suppression.

La création force `status=DRAFT`. Après création, le produit doit être relu par son GID et vérifié avant d'être considéré comme créé.

Ne jamais utiliser `www.vosgespneus.com` comme domaine Admin API : utiliser le domaine Shopify permanent du magasin.
