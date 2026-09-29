# Exécution sans Make

GitHub conserve le code public et les contrôles. Le serveur LWS reçoit les événements signés dans `vp_events`, conserve les données dans MySQL privé et exécute `vp_worker.php` par cron. Le worker attribue automatiquement un agent selon le type d'événement et ouvre une tâche dans `vp_tasks`. Il ne publie rien et ne contacte pas les clients tant qu'un connecteur officiel n'est pas configuré et vérifié.

## Installation

1. Exécuter `deploy/lws/setup/tasks.sql` dans la base MySQL dédiée
2. Copier `deploy/lws/private/worker.php` vers `/var/www/vosgespneus.com/home/vp_worker.php`, hors du dossier web. `vp_config.php` est dans ce même dossier
3. Vérifier `php -l /var/www/vosgespneus.com/home/vp_worker.php` et lancer `php /var/www/vosgespneus.com/home/vp_worker.php`
4. Ajouter une tâche cron LWS toutes les cinq minutes : `php /var/www/vosgespneus.com/home/vp_worker.php >/dev/null 2>&1`
5. Tester l'arrivée d'un événement signé, puis constater une seule ligne correspondante dans `vp_tasks`

Le récepteur `/events` attend encore la correction du routage LWS. Un fonctionnement autonome complet exige les accès API et autorisations propres à chaque canal. GitHub Actions est utilisé pour les tests du code ; son planificateur peut être retardé et les workflows programmés des dépôts publics inactifs sont désactivés après 60 jours.
