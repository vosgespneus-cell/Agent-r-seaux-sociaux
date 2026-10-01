# Checklist exploitation

## A chaque cycle
1. Lire les nouvelles entrées
2. Dédupliquer par event_id / identifiant source
3. Normaliser
4. Router
5. Exécuter uniquement les actions autorisées
6. Journaliser résultat
7. Programmer retry si erreur temporaire
8. Escalader si erreur répétée ou décision humaine nécessaire

## Santé système
- heartbeat superviseur
- dernière entrée reçue
- dernière action réussie
- nombre d'erreurs 1h/24h
- file d'attente
- connecteurs indisponibles

## Retry
1 min -> 5 min -> 15 min -> 1 h, puis alerte humaine.

## Données
Logs techniques sans secrets.
Données client minimales.
Aucun mot de passe/token dans le dépôt.
