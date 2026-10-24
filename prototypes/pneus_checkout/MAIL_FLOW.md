# Parcours par réception mail — état vérifié le 21 octobre 2026

## Résultats réels
Deux demandes B2C ont été reçues dans contact@vosgespneus.com et lues en IMAP
en mode lecture seule. L'envoi fonctionne pour ces demandes.
Le lecteur existant /home/vp_mail.php dispose déjà de la configuration de cette
boîte. Aucune redirection Gmail ni nouvelle connexion mail n'est nécessaire.

Le HTML contient les coordonnées client, le commentaire, la désignation, la
quantité, les colonnes PA HT et PV TTC et un total TTC.
Le lien « Ajouter au panier » vise le fournisseur, chemin /shop/wk.html,
avec les paramètres PID et Menge. La sonde a extrait une référence de format
attendu et une quantité cohérente, sans suivre le lien ni imprimer sa valeur.
L'identité de l'offre et son stock/prix actuels restent à vérifier chez le fournisseur.
Le mail avertit que prix et stock peuvent changer.
Aucune donnée personnelle, aucun mail brut ni lien réel n'est inclus au dépôt.

## Code et sonde privée
- camodo_mail.py : extraction stricte du format HTML français observé, séparation
  PA HT / PV TTC, quantités entières, sommes en centimes et contrôle des totaux.
  Le parseur ne fait aucun appel réseau. Il accepte uniquement un article et un
  lien fournisseur du format attendu, sinon il rejette pour examen.
- test_camodo_mail.py : exemple synthétique reprenant la structure observée,
  prix et référence fictifs. La version PHP camodo_readonly.php a désormais été
  installée hors racine web et testée sur un vrai mail avec succès. Le MIME et
  la référence PID ont été extraits. Les coordonnées, le commentaire et
  l'authentification de provenance restent à raccorder.
- sessions.py : sessions privées éphémères, rapprochement marqueur + email,
  empreintes HMAC et jeton, expiration, idempotence et gestion des conflits.
- pricing.py : calcul des options avec configuration tarifaire explicite.
- apercu-parcours-pneus.html : démonstration hors ligne, réception simulée
  manuellement, aucun achat, paiement désactivé.

## Vérifications
16 tests unitaires passent : extraction synthétique, calcul et rapprochement.
Syntaxe JavaScript de l'aperçu précédemment vérifiée avec node --check.
Pas de test réel mail → session → panier → paiement.
Sonde PHP CLI privée déployée sur LWS : lint et 6 cas serveur réussis, extraction
réelle réussie. Trois calculs Shopify et un brouillon TEST #D1 réalisés, sans
facture envoyée. Aucun parcours public, cron, paiement ni achat fournisseur.
Voir VERIFICATION_20261021.md pour les résultats et les droits Shopify préparés.

## Blocages concrets et suite
1. La demande réelle contient le commentaire standard, sans marqueur de session.
   Ne jamais afficher une demande sur la seule base d'un email saisi.
   Deux solutions à étudier : transmettre un marqueur dans Camodo, ou envoyer
   au destinataire du devis un lien personnel à durée limitée permettant de
   reprendre son achat. Cette seconde approche ne nécessite pas de rattacher
   le mail au navigateur initial mais exige un service HTTPS privé.
2. Confirmer la fréquence réelle de relève et isoler le traitement Camodo des
   autres agents. Ne pas promettre une attente de 90 secondes sans mesure.
3. Valider provenance, MIME, référence fournisseur, extraction des coordonnées,
   et déduplication des deux demandes. Le From seul n'authentifie pas un email.
4. Ajouter endpoints HTTPS, cookies adaptés ou lien personnel protégé,
   contrôles d'origine/CSRF selon le flux, limites de débit et rétention minimale.
5. Revalider prix/stock et frais avant le panier Shopify, puis tester options,
   paiement et confirmations. L'achat fournisseur est une étape distincte ;
   le lien de panier ne prouve pas l'existence d'une API d'achat.
6. Le symptôme de page blanche après envoi reste à diagnostiquer séparément :
   une réception réussie ne prouve pas un retour visuel correct dans l'iframe.
