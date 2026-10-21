# Vérification technique — 21 octobre 2026

## LWS : test réel
Sonde privée CLI uniquement : /home/vp_pneus_probe_20261021/camodo_readonly.php.
Code récupéré depuis un commit fixe et contrôlé par SHA-256.
Dossier 0700, fichiers 0600, hors racine web. Aucun cron ni endpoint ajouté.
PHP lint : succès. Self-test : 6 cas réussis.
Lecture du devis réel : succès, quantité 4, unité TTC 5718 centimes, total 22872.
Référence fournisseur extraite sans impression de sa valeur ni de l'URL.
La sonde ne valide pas la provenance, ne persiste aucun message, ne marque pas
le message lu, ne transmet rien et n'appelle aucune API fournisseur/Shopify.

## Shopify : essais effectivement exécutés
draftOrderCalculate, sans création ni envoi :
| Cas | Total TTC | TVA incluse |
| --- | ---: | ---: |
| 4 pneus | 228,72 EUR | 38,12 EUR |
| 4 pneus + montage 4 x 15 EUR | 288,72 EUR | 48,12 EUR |
| précédent + gardiennage 2 paires x 30 EUR | 348,72 EUR | 58,12 EUR |

draftOrderCreate : brouillon TEST #D1 créé à 348,72 EUR, état OPEN.
invoiceSentAt=null, aucun email client, visibleToCustomer=false.
Tags VP_TEST_PNEUS_20261021 et NE_PAS_TRAITER. Pas de facture envoyée,
pas de draftOrderComplete, pas de paiement ni d'achat.
Articles personnalisés, sans création/publication de produits.
requiresShipping=false dans ce test technique : la logistique réelle
(retrait atelier ou expédition) doit être configurée avant production.

## Connexion autonome
Le module de stock existant utilise des identifiants OAuth JSON privés et
contrôle exactement read_inventory + read_products. Aucun secret lu ni affiché.
Ne pas modifier ces permissions pour ne pas casser ce contrôle existant.
Formulaire d'une application dédiée préparé, non soumis :
- Nom : VOSGES PNEUS - commandes pneus
- Version : pneus-test-20261021
- Portées : write_draft_orders,read_orders
- Intégration dans l'admin désactivée ; aucun flux hérité.
Création/installation et stockage des nouveaux identifiants restent à autoriser.
Ces droits permettraient de gérer les brouillons et lire les commandes, avec les
données clients associées, pour vérifier le paiement. Aucune autorisation de
dépense ou d'achat fournisseur n'est déduite de cette demande de connexion.

## Pas encore testé
Page personnelle HTTPS, délivrance du lien, provenance mail, rapprochement client,
planification, stock/prix actuel fournisseur, frais réels, paiement de test,
confirmations et commande fournisseur. Le parcours complet n'est pas livré.
