# Parcours par réception mail — état du 21 octobre 2026

## Adresse confirmée par le propriétaire
Les demandes Camodo arrivent sur contact@vosgespneus.com.
vosgespneus@gmail.com n'est PAS la boîte de réception de ce parcours.
Les recherches Gmail effectuées ne permettent donc aucune conclusion sur la
présence ou l'absence des demandes dans contact@vosgespneus.com.
Aucune redirection mail n'a été configurée et aucun changement de destinataire
Camodo n'est nécessaire pour l'architecture proposée.

## Ajouts réalisés
- sessions.py : session éphémère, référence aléatoire à inclure dans le commentaire
  Camodo, rapprochement référence + email par un worker mail privé.
- Empreinte HMAC de l'email et empreinte du jeton de session en base privée.
- Un email saisi seul ne donne accès à aucune sélection.
- Rejeu idempotent, conflit rejeté, plusieurs demandes marquées needs_review.
- Après 90 secondes sans demande : delayed, sans fausse confirmation.
- Expiration de session à 30 minutes et purge explicite des sélections expirées.
- apercu-parcours-pneus.html : démonstration autonome sans réseau, données de
  test explicites, réception déclenchée manuellement, paiement désactivé.

## Validation
12 tests unitaires réussis (calcul et rapprochement).
Syntaxe JavaScript de l'aperçu vérifiée avec node --check.
Pas de test réel Camodo → contact@vosgespneus.com → session client réalisé.
Pas de test navigateur de mise en page ni de paiement effectué.
Aucun déploiement LWS, modification Shopify, envoi de mail ou achat fournisseur.

## Raccordements indispensables
1. Inspecter le lecteur mail existant sur LWS et confirmer qu'il lit bien
   contact@vosgespneus.com ; relever sa fréquence réelle de collecte.
   Une relève toutes les cinq minutes ne permet pas de promettre une réception
   traitée en moins de 90 secondes, même si le message arrive immédiatement.
2. Lire un vrai mail de demande Camodo pour construire le parseur adapté :
   référence du pneu, dimension, quantité, prix, email client et commentaire.
   Le code actuel reçoit des champs normalisés ; il ne parse aucun mail.
3. Vérifier que le commentaire contenant la référence de session ressort
   intégralement dans le mail Camodo. Sans ce marqueur, ne pas exposer les
   informations d'une demande à partir du seul email fourni dans un formulaire.
4. Vérifier l'authenticité/provenance du mail dans le worker, sans se fier au seul
   From, avant d'appeler receive. Les prix mail restent non vérifiés côté fournisseur.
5. Ajouter HTTP HTTPS, cookie Secure/HttpOnly/SameSite, contrôles origine/CSRF,
   limites de débit et conservation minimale, puis raccorder le polling UI.
   Aucun endpoint HTTP de production n'est livré ici.
6. Revalider prix et stock et résoudre tous frais avant création du panier Shopify.
7. Tester le parcours réel et les confirmations, puis seulement activer paiement
   et commande fournisseur, avec idempotence et gestion d'échec/remboursement.

## Limite importante
Le bouton « J'ai envoyé ma sélection » dans notre page évite d'imposer une
redirection depuis Camodo. Il ne répare pas l'éventuel échec d'envoi Camodo :
si Camodo n'envoie aucun mail, la session passe en attente prolongée.
