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
Application dédiée créée et version publiée après accord explicite du propriétaire :
- Nom : VOSGES PNEUS - commandes pneus
- Version : pneus-test-20261021
- Portées : write_draft_orders,read_orders
- Intégration dans l'admin désactivée ; aucun flux hérité.
Création et installation autorisées par le propriétaire. Création confirmée dans le
Dev Dashboard, version active pneus-test-20261021. Installation lancée mais état
inconnu : l'observation de l'administration Shopify est bloquée par la protection
des identifiants du navigateur, y compris après navigation explicite à l'origine.
Ne pas confondre version active avec application installée. Aucun secret client
n'a été lu ou transféré, aucune connexion de commandes configurée sur LWS.
Une reprise manuelle sur l'écran d'installation est nécessaire à ce stade.
Ces droits permettraient de gérer les brouillons et lire les commandes, avec les
données clients associées, pour vérifier le paiement. Aucune autorisation de
dépense ou d'achat fournisseur n'est déduite de cette demande de connexion.

## Pas encore testé
Page personnelle HTTPS, délivrance du lien, provenance mail, rapprochement client,
planification, stock/prix actuel fournisseur, frais réels, paiement de test,
confirmations et commande fournisseur. Le parcours complet n'est pas livré.

## Reprise après installation par le propriétaire
Installation confirmée visuellement sur VOSGES PNEUS, domaine technique attendu,
statut Installé et forfait Basic. shopify_connect.php a été préparé, vérifié par
lint PHP sur LWS et servi sur une route temporaire aléatoire. Il vérifie TLS,
origine/CSRF, expiration, nombre de tentatives et droits OAuth avant stockage privé.
La saisie via browserAuth a été refusée par la validation automatique de sécurité.
Aucun contournement ni autre transfert de secret n'a été tenté. Le formulaire a
été désactivé immédiatement en fixant son expiration à zéro.
Contrôle serveur : credentials_present=false, token_present=false.
La connexion autonome LWS reste à finaliser par une procédure autorisée indépendante.
Le site public et le paiement restent inchangés.

## Nouveau devis et page d'essai
Un nouveau mail a été reçu le 21 octobre à 20 h 27 (Paris). Le lecteur initial
rejetait sa dimension écrite « R 16 ». Le parseur Python et la sonde PHP acceptent
désormais les espaces et retirent l'étiquette énergie de la désignation.
Le format « R16 » reste couvert. 17 tests Python passent, 7 cas PHP de sonde
et 10 cas PHP de calcul/identification client passent sur LWS.
L'extraction du nouveau mail réussit et le champ Courriel est identifié.
Le marqueur TEST AUTRE PC 21 OCTOBRE n'est pas présent dans le mail observé.
La réception seule ne confirme pas l'état visuel après envoi sur l'autre PC.

review_lib.php, review_import.php et review_page.php sont installés sur LWS.
Le script CLI exige --owner-test et un UID explicite. Il n'est pas planifié.
Un seul devis a été importé pour l'essai, sans envoi client. La sélection et le
courriel sont stockés dans une base SQLite privée 0600, dossier 0700.
Lien aléatoire de 256 bits haché en base, valable une heure, transmis dans le
fragment puis échangé contre une session Secure/HttpOnly/SameSite Strict.
Le site public Shopify est inchangé. La route d'essai LWS ne montre aucune
sélection sans lien/session valide et n'affiche pas les coordonnées client.
Le statut de provenance n'est pas authentifié : usage propriétaire uniquement.

La page réelle a été observée : quatre pneus, 231,16 EUR TTC, montage facultatif,
gardiennage par paire, récapitulatif et paiement désactivé. Le navigateur a ensuite
refusé le test des options (browser URL policy). Aucun contournement ni enregistrement
via un autre canal n'a été tenté. Contrôle SQL en lecture seule : saved=0,
fitting=0, storage_pairs=0. Le test d'enregistrement navigateur n'est donc PAS validé.
Les tarifs de prestations sont explicitement ceux de l'essai, leur validité
commerciale et la durée de garde restent à confirmer.

Aucun paiement, achat fournisseur, commande supplémentaire ni mail envoyé.
Restent : test d'enregistrement autorisé, purge des données temporaires,
provenance des mails, collecte planifiée, page client de production, stock/prix/frais,
connexion autonome Shopify, tests de paiement et confirmations.
