# Installateur client

Objectif: transformer le moteur générique en une instance client sans demander au client final de manipuler GitHub ou du code.

## Parcours cible
1. nom de l'entreprise
2. identifiant de l'instance
3. fuseau horaire
4. choix des canaux
5. autorisations OAuth / comptes externes dans leur écran sécurisé
6. diagnostic
7. simulation de bout en bout
8. activation progressive

## Sécurité
L'assistant ne collecte pas de mot de passe dans un fichier de configuration.
Les secrets restent dans le système de secrets de la machine ou du fournisseur.
Toute nouvelle installation démarre en dry-run.
L'activation réelle d'un connecteur vient après un test réussi.

## Expérience finale
Le client voit une interface simple et son tableau de bord.
GitHub reste un composant technique invisible pour lui.
