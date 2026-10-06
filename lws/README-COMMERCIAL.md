# Module commercial LWS — état réel

Le module est installé sur LWS depuis le 5 octobre 2026. Il contrôle quatre fiches Shopify : SKU, prix EUR, disponibilité publique, photos et quantités vendables authentifiées, puis prépare des textes d'annonces. L'annonce eBay existante 800759185876 est explicitement marquée pour éviter sa duplication.

Il n'utilise aucun modèle IA ni API payante. Il ne publie aucune annonce, ne modifie aucun stock et n'envoie aucun message. Les brouillons ne valident pas la compatibilité ou le fonctionnement des pièces.

## Lecture officielle du catalogue

La version `commercial-lws-3` lit les quatre produits dans une seule requête à l'API Storefront officielle, version 2026-10, via `zaiwdm-st.myshopify.com`. La lecture des champs publics est sans jeton et ne crée aucun nouvel accès administrateur.

Documentation : https://shopify.dev/docs/api/storefront/latest

Les anciennes lectures `products/<handle>.js` renvoyaient HTTP 429 depuis LWS. L'entrée locale de /etc/hosts envoyait aussi le domaine personnalisé vers LWS. Le nouvel accès utilise directement le domaine Shopify et conserve la validation TLS. Aucun proxy, faux IP acheteur ou désactivation SSL n'est utilisé.

## Installation

Transférer `vp_commercial.php`, `vp_stock.php` et `install-commercial.sh` dans un dossier privé hors `htdocs`, puis exécuter `bash install-commercial.sh`. Le script vérifie PHP 8.3, la syntaxe, 19 contrôles commerciaux et huit contrôles de stock et une lecture réelle. En cas d'échec, il restaure l'ancien programme ou conserve la tentative sous un nom `.failed`.

L'installateur fonctionne avec le terminal restreint LWS, sans `dirname` ni `mktemp`. Il ne modifie aucune tâche cron.

Programme actif : `/var/www/vosgespneus.com/home/vp_commercial.php`

Rapports privés JSON et HTML : `/var/www/vosgespneus.com/home/vp_commercial/`

## Planification active

La nouvelle tâche commerciale est enregistrée dans LWS, statut Actif, à `5-59/5 * * * *`. Les cinq tâches préexistantes sont conservées.

```sh
PHP_INI_SCAN_DIR=/usr/base/opt/php8.3/etc/conf.d /usr/base/opt/php8.3/bin/php -c /usr/base/opt/php8.3/etc/php.ini -d extension_dir=/usr/base/opt/php8.3/lib/php/extensions/no-debug-non-zts-20230831 /var/www/vosgespneus.com/home/vp_commercial.php >>/var/www/vosgespneus.com/home/vp_commercial.log 2>>/var/www/vosgespneus.com/home/vp_commercial.err
```

Le cache d'une heure limite la lecture automatique à une requête par heure pour les quatre fiches. Le verrou empêche les traitements simultanés. La requête a un délai maximum de 20 secondes et une réponse limitée à 1 Mo. Une garde locale impose au moins 60 secondes entre lectures forcées et respecte Retry-After en cas de HTTP 429. Ne pas utiliser `--force` dans le cron.

Pour arrêter le module, désactiver uniquement cette tâche. Les journaux sont privés (permissions 0600).

## Validation sur LWS

Le 5 octobre 2026 à 14:18:35 Europe/Paris :
- Syntaxe PHP 8.3 valide et 19 contrôles réussis
- Lecture réelle : quatre produits, zéro erreur, aucune anomalie de SKU, prix ou photos
- Prix contrôlés : 69 EUR, 39 EUR, 49 EUR, 49 EUR ; cinq photos par produit
- Deuxième exécution : cache valide, sans nouvelle lecture Shopify
- Installation confirmée par `MODULE COMMERCIAL INSTALLE ET CONTROLE EN LIGNE`
- Tâche commerciale confirmée active dans LWS et présente dans le crontab serveur
- Premier déclenchement automatique observé à 14:30:01 Europe/Paris : `VP_COMMERCIAL_CACHE_OK`, journal d'erreurs vide. La tâche a donc été exécutée par le serveur, sans intervention ; le cache évite une lecture inutile du catalogue

## Restant avant publication autonome

- Raccorder une API de publication officielle avec les autorisations nécessaires
- Rechercher les annonces existantes pour les trois autres SKU
- Valider les photos, frais de livraison, retours et références des pièces
- Tester une annonce, son identifiant, puis une vente et la synchronisation du stock
- Étendre le périmètre au reste du catalogue après validation

Le rapport conserve `publication_allowed=false`. La confirmation physique initiale expire après 36 heures et demande ensuite un nouveau contrôle. GitHub conserve le code ; les mises à jour ultérieures ne sont pas déployées automatiquement sur LWS.

## Accès Shopify authentifié — 5 octobre 2026

L'application privée `VOSGES PNEUS Agent LWS` est créée et installée sur la boutique, avec uniquement `read_products,read_inventory`. Version active : `lecture-stocks-20261005`. Identifiant d'application Dev Dashboard : 431686025217.

Le lecteur `vp_stock.php` utilise le client credentials grant officiel : https://shopify.dev/docs/apps/build/authentication-authorization/client-credentials-grant

Les identifiants et le jeton sont dans le dossier privé `home/vp_commercial/`, permissions 0600, jamais dans GitHub. Le jeton est renouvelé avant son expiration de 24 heures. La clé temporaire de transfert chiffré a été supprimée après stockage.

La tâche commerciale existante appelle désormais le lecteur de stock à chaque exécution. Le cache de stock est limité à 240 secondes ; le catalogue garde son cache d'une heure, calculé à partir d'une date séparée pour éviter que l'actualisation du stock ne repousse la lecture du catalogue.

Le lecteur vérifie l'identifiant de variante, le SKU, le suivi de stock et le type entier de la quantité. En cas d'erreur ou de réponse incomplète, aucune quantité précédente n'est réutilisée pour autoriser une publication. Les quantités restent des stocks vendables Shopify, pas une preuve physique.

Première lecture depuis LWS : `VP_STOCK_OK items=4 errors=0`. Les quatre variantes ont une quantité de 1 et le suivi du stock activé. Intégration au module : `VP_COMMERCIAL_CACHE_OK stock_items=4 stock_errors=0`. Syntaxe et 27 contrôles locaux validés sur PHP 8.3. La publication reste désactivée.
