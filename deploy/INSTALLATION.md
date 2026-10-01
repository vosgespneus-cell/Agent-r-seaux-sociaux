# Installation cible

Objectif commercial: le client final ne manipule pas le code.

## Assistant d'installation
Le technicien renseigne uniquement:
- nom entreprise
- boîte mail
- téléphone
- agenda
- site/e-commerce
- WhatsApp si utilisé
- horaires
- règles métier principales

Les identifiants sensibles sont saisis dans un gestionnaire de secrets, jamais dans GitHub.

## Déploiement
1. Copier le moteur
2. Créer configuration client
3. Autoriser les connecteurs
4. Tester chaque entrée
5. Lancer diagnostic
6. Activer superviseur
7. Vérifier heartbeat
8. Remettre un tableau de bord simple au client

## Critère de livraison
Un client doit pouvoir utiliser la machine sans GitHub, terminal, code ou Make.
