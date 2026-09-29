# Résilience et surveillance

Les workers restent séparés afin qu'une panne d'un composant ne bloque pas toute la machine.

Ordre conseillé :
- worker.php : transforme les événements en tâches
- supervisor.php : transforme les tâches en actions
- executor.php : exécute une action sûre
- recovery.php : récupère les exécutions interrompues et applique un délai progressif
- health.php : imprime uniquement des compteurs, jamais les données clients

## Politique de reprise
Une action bloquée en `running` plus de 15 minutes est récupérée. Tant que `max_attempts` n'est pas atteint, elle repasse en `retry` avec attente progressive. Une action épuisée passe en `failed` et n'est plus exécutée automatiquement.

## Avant production
Tester chaque script manuellement sur LWS. Ne programmer les cron qu'après création des tables et validation des sorties `VP_*_OK`.
