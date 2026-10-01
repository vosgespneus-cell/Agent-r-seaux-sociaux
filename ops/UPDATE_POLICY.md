# Politique de mise à jour

Le moteur et la configuration client sont séparés.

## Mise à jour sûre
1. relever la version active
2. sauvegarder
3. télécharger la nouvelle version du moteur
4. conserver la configuration client
5. lancer les tests
6. lancer le diagnostic
7. démarrer en dry-run
8. activer si PASS
9. rollback automatique si échec critique

Une mise à jour ne doit jamais remplacer les secrets ni les règles métier propres au client.

## Canaux
Les adapters sont versionnés indépendamment afin de remplacer un fournisseur sans modifier le superviseur.
