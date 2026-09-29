# Exploitation quotidienne — pipeline Produits

Commandes privées prévues sur LWS :
- `preflight.php` : vérifie l'installation
- `worker.php` : transforme les événements en tâches et entrées atelier
- `supervisor.php` : crée les actions
- `executor.php` : exécute les actions autorisées
- `recovery.php` : récupère les traitements interrompus
- `health.php` : état général
- `pipeline_status.php` : compteurs Produits sans données privées

Le tableau d'état ne doit afficher ni photos, ni notes, ni noms, ni téléphones, ni clés API.

Objectif d'exploitation :
- aucune action `running` bloquée durablement
- aucune duplication de `source_reference`
- aucune création Shopify sans preuve
- aucune publication Shopify par le pipeline V1
