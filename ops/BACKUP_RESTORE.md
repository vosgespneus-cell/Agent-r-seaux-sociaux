# Sauvegarde et restauration

## A sauvegarder
- base runtime privée
- configuration client non secrète
- références de configuration des connecteurs
- journal d'audit selon durée de rétention

## A ne jamais placer dans une sauvegarde GitHub
- mots de passe
- tokens OAuth
- clés API
- messages ou coordonnées clients

Les secrets doivent être sauvegardés par le mécanisme sécurisé de la machine/hébergeur.

## Procédure cible
1. mettre le runtime en pause
2. vérifier l'intégrité SQLite
3. produire une sauvegarde horodatée
4. chiffrer la sauvegarde privée
5. vérifier qu'une restauration de test est possible
6. reprendre le runtime

## Restauration
Restaurer d'abord les données, puis la configuration, puis vérifier les connecteurs en dry-run avant de réactiver les actions réelles.
