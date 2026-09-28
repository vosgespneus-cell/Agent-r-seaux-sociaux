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
5. Tester un événement synthétique signé, sa répétition, puis une signature erronée
6. Raccorder Make seulement après ces vérifications

Le service rejette les requêtes sans HTTPS, sans HMAC valide ou de plus de 64 Kio.
Il ne publie ni les événements ni un tableau de bord. Le chemin `/events` est
routé vers `index.php` par `.htaccess` ; le routage reste à vérifier sur LWS.
Aucun secret réel n'est inclus ici.
