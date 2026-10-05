# Préparation eBay par l’agent LWS

`vp_ebay_prepare.php` transforme le rapport commercial en `vp_commercial/ebay_queue.json`. Quand le module est présent dans le répertoire privé du programme, `vp_commercial.php` l’appelle à chaque passage, y compris en mode catalogue en cache. Le cron existant suffit. Le journal privé `ebay_journal.jsonl` consigne les changements de contenu sans dupliquer chaque passage.

Le module ne publie pas et ne passe aucun appel eBay. Le champ `publication_active` reste false. Il contrôle la fraîcheur du stock Shopify (300 secondes), la quantité unitaire, les erreurs du rapport, la confirmation physique (36 heures), et le contenu. L’annonce Nemo 800759185876 est exclue de la création, même si son identifiant disparaît du rapport. Cela ne remplace pas une recherche de doublons sur toutes les annonces du compte.

Installation dans `/var/www/vosgespneus.com/home` : tester d’abord `vp_ebay_prepare.php --self-test` avec le PHP 8.3 de LWS ; installer le module privé avec permissions 600 avant de remplacer le programme commercial. L’installateur commercial existant installe le programme et le lecteur Shopify ; pour cette extension il faut aussi copier le module de préparation.

Avant tout exécuteur de publication : connecter le compte développeur eBay et le consentement vendeur OAuth, récupérer les identifiants des politiques et du lieu, vérifier catégorie/aspects/état, rechercher les annonces existantes sur tout le compte et prévoir les ventes/épuisement du stock. La connexion Marketplace Connect dans Shopify ne constitue pas un jeton eBay utilisable par LWS. Ne jamais enregistrer les identifiants privés dans Git.

Attention : la condition eBay « Used » correspond à une pièce opérationnelle dans la définition officielle. Les brouillons actuels n’affirment pas un fonctionnement testé ; ce point doit être résolu avant publication. Ne pas choisir automatiquement un état promettant le fonctionnement.

Documentation officielle : https://developer.ebay.com/develop/guides/sell/authorization ; https://developer.ebay.com/api-docs/sell/static/inventory/publishing-offers.html ; https://developer.ebay.com/api-docs/sell/static/metadata/condition-id-values.html
