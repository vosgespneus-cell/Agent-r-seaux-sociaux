# Premier test Shopify réel

Le pipeline a été validé avec une fiche explicitement marquée comme test et créée en statut DRAFT.

Contrôles effectués après création :
- identifiant Shopify retourné
- relecture du produit depuis Shopify
- statut toujours DRAFT
- titre identique
- SKU identique
- prix identique

La preuve technique confirme le principe `action -> relecture -> vérification`.

Le produit de test ne doit pas être utilisé comme stock réel. Sa suppression/archivage n'est pas automatisé dans cette étape afin de conserver une trace vérifiable du test et d'éviter une action destructive non nécessaire.
