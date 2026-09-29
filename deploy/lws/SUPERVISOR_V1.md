# Superviseur V1 — test sans risque

Cette branche ajoute une file d'actions séparée. Aucun appel Shopify, Meta, WhatsApp, Jotform ou autre service externe n'est exécuté.

Ordre de test sur LWS :
1. Importer `setup/actions.sql`
2. Copier `private/supervisor.php` hors de `htdocs`, à côté de `vp_config.php`
3. Lancer une fois en CLI : `php supervisor.php`
4. Vérifier que la sortie est uniquement `VP_SUPERVISOR_OK`
5. Contrôler `vp_actions` : une tâche existante doit produire au maximum une action grâce à la clé unique
6. Relancer : aucun doublon ne doit être créé

Ne pas ajouter le cron avant validation manuelle. Les données privées restent en base LWS et ne doivent jamais être copiées dans GitHub.
