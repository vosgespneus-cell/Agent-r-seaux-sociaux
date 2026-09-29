# Récepteur LWS pour VOSGES PNEUS

Le sous-domaine `agents.vosgespneus.com` a son dossier web dans
`/var/www/vosgespneus.com/htdocs/agents.vosgespneus.com`.
Le dossier `/var/www/vosgespneus.com/home` se trouve hors de `htdocs` et
accueille `vp_config.php`. Les noms, téléphones et messages sont conservés
dans MySQL, jamais dans GitHub.
LWS affiche PHP 8.3 pour ce sous-domaine.

1. Vérifier que `agents` pointe vers LWS et qu'un certificat valide répond en HTTPS
2. Créer une base MySQL distincte et y exécuter `setup/schema.sql`
3. Placer la configuration remplie dans `home/vp_config.php`, hors de `htdocs`
4. Placer `public/index.php` et `public/.htaccess` dans `htdocs/agents.vosgespneus.com/`
5. Tester un événement synthétique signé, sa répétition, un même ID avec un corps différent (409), puis une signature erronée
6. Installer le worker privé et la tâche cron décrits dans `AUTONOMIE_SANS_MAKE.md`
7. Raccorder chaque source officielle directement au récepteur, sans Make

Le service rejette les requêtes sans HTTPS, sans HMAC valide ou de plus de 64 Kio.
Il ne publie ni les événements ni un tableau de bord. Le chemin `/events` est opérationnel depuis la correction LWS du 29 septembre 2026. Un POST avec signature volontairement invalide renvoie 401, preuve que la requête atteint le contrôle PHP. Le chemin direct `/index.php` avait été validé avec un événement synthétique (202), puis sa répétition (200, doublon). Le worker a créé une seule tâche privée `accueil / nouveau`.

Le formulaire Jotform de demande de pneus est synchronisé par cron toutes les cinq minutes. La vérification du 29 septembre a renvoyé `JOTFORM_SYNC_OK` et `VP_WORKER_OK`, avec une entrée Jotform et une tâche correspondante. Pour consulter uniquement les totaux sans coordonnées ni messages, lancer `php /var/www/vosgespneus.com/home/vp_status.php`. Le fichier provient de `private/status.php` et doit rester hors de `htdocs` avec les droits 0600.
Aucun secret réel n'est inclus ici.
