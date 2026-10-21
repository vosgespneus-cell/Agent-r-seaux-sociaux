# Parcours pneus — état du 21 octobre 2026

Le parcours d'achat complet n'est pas en production. Le site existant est préservé.
Aucun abonnement ajouté, paiement, envoi client ni achat fournisseur effectué.

## Vérifié réellement
- Deux devis Camodo reçus sur la boîte métier, lus en IMAP en lecture seule.
- Sonde PHP privée installée hors racine web sur LWS, fichiers 0600, dossier 0700.
  Syntaxe PHP et 6 contrôles serveur réussis. Extraction sur un vrai mail réussie :
  désignation, quantité, prix de vente TTC et référence fournisseur.
  Ni les coordonnées du client, ni le PID, ni le lien fournisseur ne sont imprimés.
- Shopify draftOrderCalculate exécuté : totaux et TVA cohérents dans trois cas.
- Une commande brouillon de TEST #D1 créée, sans client ni facture envoyée,
  marquée NE_PAS_TRAITER et visibleToCustomer=false. Aucun lien de paiement diffusé.
- 16 tests Python réussis sur calcul, extraction synthétique et sessions.

## Fichiers
- camodo_mail.py / test_camodo_mail.py : extraction stricte du HTML français.
- camodo_readonly.php / camodo_fixture.html : sonde CLI PHP privée et exemple fictif.
  Aucun traitement planifié ni envoi : elle lit uniquement le message demandé.
  La provenance du mail n'est pas authentifiée par cette sonde.
- pricing.py : calcul en centimes TTC, options et offre à revalider côté fournisseur.
- sessions.py : base de rapprochement par marqueur, non raccordée au site.
- apercu-parcours-pneus.html : démonstration hors ligne, paiement désactivé.
- MAIL_FLOW.md : architecture et limites du parcours mail.
- VERIFICATION_20261021.md : résultats et autorisation préparée.

## Suite nécessaire
La connexion de stock existante sur LWS attend exactement read_products et
read_inventory. Ne pas étendre ses droits : son contrôle rejetterait le changement.
L'application séparée VOSGES PNEUS - commandes pneus est créée et installée sur
la boutique de production, vérification visuelle du statut Installé dans le
Dev Dashboard. Les droits write_draft_orders et read_orders ont été autorisés.
Le raccordement des identifiants LWS n'est pas terminé : la validation automatique
de sécurité a refusé la saisie du secret via le navigateur. Aucun secret n'a été
lu ni enregistré. Le formulaire temporaire a été désactivé (expiration à zéro).
Aucune connexion autonome aux commandes n'est active.

Il reste à raccorder la relève et l'extraction client, authentifier la provenance,
envoyer un lien personnel à durée limitée, livrer la page HTTPS des options,
revalider stock/prix/frais puis créer le brouillon Shopify. Le paiement et les
confirmations doivent être testés avant publication.
La référence PID a été extraite ; elle ne démontre aucune API d'achat fournisseur.
Le symptôme de page blanche après envoi reste à diagnostiquer séparément.

## Tests
python -m unittest discover -s prototypes/pneus_checkout -p 'test_*.py' -v

Les prix de prestations utilisés pour les essais ne valident pas leur configuration
commerciale actuelle, notamment la durée de gardiennage. Aucun mail brut, secret,
lien réel fournisseur ou donnée personnelle n'est enregistré dans ce dépôt.

## Complément : essai privé et frais
La page d'essai LWS affiche désormais une sélection provenant d'un vrai devis.
Son enregistrement d'options n'est pas validé (refus de la politique URL navigateur).
Le test ne remplace pas un parcours client de production. Aucun lien client envoyé.
Le dernier contrôle catalogue montre le modèle SUNNY NL106 195/65 R16 à 57,79 EUR
TTC, stock affiché 9, sans réservation ; le PID/EAN précis reste à rapprocher.
Le prix catalogue exclut montage/équilibrage, valves et retraitement. Leur inclusion
dans les forfaits atelier, les frais de livraison et la durée du gardiennage
doivent être confirmés avant toute activation d'un paiement.
