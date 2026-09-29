# Demandes de pneus Jotform → LWS sans Make

Formulaire existant : `262624697144060` (« Demande de pneus – VOSGES PNEUS »).

Le script privé `jotform_puller.php` interroge uniquement ce formulaire avec une clé Jotform **Read Access**. Il conserve les réponses dans `vp_events` sur MySQL LWS, enregistre le dernier identifiant traité dans `vp_sync_state` et laisse le worker général créer une tâche `accueil / nouveau`. Les doublons sont ignorés. Aucun message n'est envoyé automatiquement au client à ce stade.

1. Exécuter `setup/jotform_state.sql` sur la base dédiée
2. Installer `private/jotform_puller.php` dans `/var/www/vosgespneus.com/home/vp_jotform.php`, hors du dossier web
3. Créer `home/vp_jotform_config.php` à partir de `setup/jotform_config.example.php`, permission 0600
4. Le propriétaire crée une clé Jotform **Read Access** et la saisit directement dans ce fichier privé. Ne jamais envoyer la clé dans le chat, GitHub ou une URL
5. La table, le script, le modèle privé et le cron sont installés sur LWS ; sans clé, l'exécution affiche `JOTFORM_NOT_CONFIGURED` et ne contacte pas l'API
6. Après saisie de la clé, exécuter manuellement `php /var/www/vosgespneus.com/home/vp_jotform.php` ; attendre `JOTFORM_SYNC_OK`
7. Faire une demande de test dans le formulaire et vérifier exactement un `event_id` `JF-...` dans `vp_events` et une tâche `accueil / nouveau` dans `vp_tasks`

Le cron actif exécute d'abord `vp_jotform.php`, puis `vp_worker.php`, toutes les cinq minutes.

Le script ne contacte pas l'API sans clé valide. La clé reste sur LWS ; GitHub ne contient que le code. Avec une exécution toutes les cinq minutes, une consultation correspond à 288 appels API par jour quand il n'y a aucune nouvelle demande, sous la limite Starter publiée de 1000 appels par jour. En cas de retard important, le script s'arrête après dix pages de cent soumissions sans avancer le curseur ; l'erreur est signalée dans un journal privé.

L'API Jotform peut avoir un autre domaine pour les comptes EU ou HIPAA. Vérifier le domaine du compte avant d'activer une clé.