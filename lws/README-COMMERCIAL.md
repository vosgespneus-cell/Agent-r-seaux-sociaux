# Module commercial LWS — état réel

Le module est installé sur LWS depuis le 5 octobre 2026. Il contrôle quatre fiches Shopify : SKU, prix EUR, disponibilité publique et photos, puis prépare des textes d'annonces. L'annonce eBay existante 800759185876 est explicitement marquée pour éviter sa duplication.

Il n'utilise aucun modèle IA ni API payante. Il ne publie aucune annonce, ne modifie aucun stock et n'envoie aucun message. Les brouillons ne valident pas la compatibilité ou le fonctionnement des pièces.

## Lecture officielle du catalogue

La version `commercial-lws-2` lit les quatre produits dans une seule requête à l'API Storefront officielle, version 2026-10, via `zaiwdm-st.myshopify.com`. La lecture des champs publics est sans jeton et ne crée aucun nouvel accès administrateur.

Documentation : https://shopify.dev/docs/api/storefront/latest

Les anciennes lectures `products/<handle>.js` renvoyaient HTTP 429 depuis LWS. L'entrée locale de /etc/hosts envoyait aussi le domaine personnalisé vers LWS. Le nouvel accès utilise directement le domaine Shopify et conserve la validation TLS. Aucun proxy, faux IP acheteur ou désactivation SSL n'est utilisé.

## Installation

Transférer `vp_commercial.php` et `install-commercial.sh` dans un dossier privé hors `htdocs`, puis exécuter `bash install-commercial.sh`. Le script vérifie PHP 8.3, la syntaxe, 19 contrôles locaux et une lecture réelle. En cas d'échec, il restaure l'ancien programme ou conserve la tentative sous un nom `.failed`.

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

- Vérifier les quantités avec un accès serveur autorisé : la disponibilité publique ne prouve pas la quantité en stock
- Raccorder une API de publication officielle avec les autorisations nécessaires
- Rechercher les annonces existantes pour les trois autres SKU
- Valider les photos, frais de livraison, retours et références des pièces
- Tester une annonce, son identifiant, puis une vente et la synchronisation du stock
- Étendre le périmètre au reste du catalogue après validation

Le rapport conserve `publication_allowed=false`. La confirmation physique initiale expire après 36 heures et demande ensuite un nouveau contrôle. GitHub conserve le code ; les mises à jour ultérieures ne sont pas déployées automatiquement sur LWS.
