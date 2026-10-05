# Module commercial LWS — état réel

Ce module lit les quatre fiches Shopify du stock confirmé le 5 octobre 2026, contrôle leur SKU, prix, disponibilité publique et photos, et prépare des textes d'annonces. L'annonce eBay existante 800759185876 est explicitement marquée pour éviter une nouvelle création.

Il n'utilise aucun modèle IA ni API payante. Il ne publie aucune annonce, ne modifie aucun stock et n'envoie aucun message. Les brouillons ne constituent pas une validation de compatibilité ou de fonctionnement des pièces.

## Installation sur l'hébergement

Transférer les deux fichiers `vp_commercial.php` et `install-commercial.sh` dans un dossier privé de LWS, hors du dossier web `htdocs`, puis exécuter `bash install-commercial.sh` depuis ce dossier. Le script vérifie l'installation existante et le PHP 8.3 réellement utilisé par les agents, exécute le contrôle de syntaxe, les 13 vérifications locales et une lecture réelle des quatre fiches. Si cette lecture échoue, il restaure le programme précédent ou conserve le nouveau fichier sous un nom `.failed`. Aucune tâche cron existante n'est modifiée.

Le rapport JSON et HTML reste privé dans `/var/www/vosgespneus.com/home/vp_commercial/`. En cas de retour arrière du code, ce rapport peut contenir le résultat de la tentative échouée : consulter son horodatage et son compteur `errors`.

## Planification après validation

Ajouter une tâche LWS à la minute `5-59/5`, avec les autres champs à `*` :

```sh
PHP_INI_SCAN_DIR=/usr/base/opt/php8.3/etc/conf.d /usr/base/opt/php8.3/bin/php -c /usr/base/opt/php8.3/etc/php.ini -d extension_dir=/usr/base/opt/php8.3/lib/php/extensions/no-debug-non-zts-20230831 /var/www/vosgespneus.com/home/vp_commercial.php >>/var/www/vosgespneus.com/home/vp_commercial.log 2>>/var/www/vosgespneus.com/home/vp_commercial.err
```

Le verrou empêche les traitements simultanés. Un cache d'une heure limite les lectures Shopify à quatre par heure. Chaque requête a un délai maximum de 15 secondes et une réponse limitée à 1 Mo. Ne pas utiliser `--force` dans la tâche périodique. Pour arrêter le module, désactiver uniquement cette nouvelle tâche.

## Restant avant publication autonome

- Vérifier quantitativement le stock dans Shopify avec un accès serveur autorisé : l'indicateur public `available` n'est pas une quantité
- Raccorder une API de publication officielle avec les autorisations nécessaires
- Rechercher les annonces existantes pour les trois autres SKU avant création
- Valider les photos, les frais de livraison, les retours et les références des pièces
- Tester une seule annonce, son identifiant, puis une vente et la synchronisation du stock

Le rapport expose toujours `publication_allowed=false`. L'ajout d'une connexion ne doit pas suffire à inverser cette valeur sans les validations ci-dessus. La confirmation physique initiale expire après 36 heures et demande alors un nouveau contrôle.

## Validation à ce stade

Le 5 octobre 2026, accès LWS et terminal rétablis. Les fichiers ont été transférés dans le dossier privé `home/deploy-commercial-20261005`. PHP 8.3 valide la syntaxe et les 13 contrôles locaux. L'installateur a été adapté au terminal restreint (absence de dirname et mktemp). Le module résout désormais le domaine via DNS public pour éviter l'entrée locale LWS de /etc/hosts, en conservant la validation TLS.

La lecture réelle des fiches Shopify renvoie HTTP 429, corps `local_rate_limited`, Retry-After 60. Après le délai, la validation reste en échec (3 puis 4 erreurs). L'installateur a retiré le fichier actif et conservé les tentatives `.failed` ; aucun cron commercial n'a été ajouté. Les cinq crons existants n'ont pas été modifiés. Le module commercial n'est donc pas actif. Prochaine étape : résoudre la limitation de lecture Shopify depuis LWS, puis relancer l'installateur et n'activer la planification qu'après `errors=0`.
