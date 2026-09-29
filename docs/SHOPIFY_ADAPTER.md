# Shopify — raccordement progressif

La boutique connectée a été vérifiée en lecture avant toute écriture.

Le premier mode est `read_only` :
- lecture catalogue
- lecture stock
- normalisation des produits pour le superviseur
- aucune création, modification, publication, suppression ou modification de stock

Les secrets Shopify ne doivent jamais être committés. Ils restent dans la configuration privée du serveur ou dans le mécanisme sécurisé du connecteur.

## Passage à l'écriture
L'ouverture se fera capacité par capacité :
1. création de produit en brouillon
2. contrôle du résultat et conservation de l'identifiant Shopify
3. mise à jour encadrée d'un produit
4. publication sous règles
5. stock uniquement après validation du mécanisme anti-doublon

Une action Shopify n'est jamais considérée terminée sans référence Shopify vérifiable.
