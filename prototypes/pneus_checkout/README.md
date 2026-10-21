# Panier pneus — préparation du 21 octobre 2026

État : prototype de calcul uniquement, non déployé. Aucun panier, paiement,
email ni commande fournisseur n'est créé. Ce dossier ne doit pas être publié
comme un parcours fonctionnel. Aucun abonnement ni service payant ajouté.

## Vérifications réalisées
- Lecture API Shopify de la boutique VOSGES PNEUS et de la page demande-pneus.
- Catalogue public Camodo : recherche 205/55 R16, prix TTC, stocks affichés,
  choix de quantité et formulaire de demande de devis.
- Soumission du formulaire destinée à Camodo /Reifen/SubmitAnfrage.
  Pas de soumission finale effectuée ; cause de la page blanche non déterminée.
- Notice officielle B2C Camodo lue : intégration iframe/lien, marges,
  prix/stocks actualisés plusieurs fois par jour, adresse de réception.
- Code HTML public et fichier preisauskunft.js examinés : aucun mécanisme
  de transmission de sélection au parent Shopify trouvé dans ces fichiers.
  Cela ne démontre pas l'absence de toute autre interface chez Camodo.
- Réglages vus sur capture : B2C et devis par email cochés.
- Administration Camodo bloquée après authentification par la protection
  de l'environnement navigateur. Aucune clé fournisseur reçue.
- Aucun contrat API exploitable retrouvé dans les recherches ciblées Gmail.

## Prototype testable
python -m unittest discover -s prototypes/pneus_checkout -p 'test_*.py' -v

Cinq tests passent : totaux et options, trois tranches montage, frais requis,
quantités incorrectes, stock insuffisant, péremption, dates invalides,
configuration de prestations manquante.
Les montants des tests sont des fixtures, pas des prix fournisseur garantis
ni une validation des tarifs atelier actuels ou de la durée de gardiennage.

Le calcul travaille en centimes TTC, avec une offre résolue côté serveur.
VerifiedOffer est une structure interne, pas un document signé : ne jamais
la remplir directement à partir du JSON client. Aucun endpoint public fourni.
Un objet calculé ne réserve aucun stock. payment_enabled reste toujours false.

## Raccordement restant
1. Camodo doit fournir un moyen autorisé de récupérer une sélection et de
   revalider référence/EAN, prix, quantité disponible, délai et frais :
   API, export ou callback documenté. L'iframe seule ne suffit pas.
2. Mapper la référence à une variante Shopify précise et les prestations à
   leurs variantes. Les prix Shopify doivent être cohérents avec l'offre
   serveur. Les attributs de ligne du panier ne remplacent pas les prix Shopify.
3. Résoudre tous les frais selon la commande, confirmer les tarifs de prestations,
   leur TVA et la période de gardiennage. Ne pas ajouter de taxe aux prix déjà TTC.
4. Mettre en place la concurrence/réservation fournisseur et empêcher les
   doubles commandes avec une clé d'idempotence. Ne jamais commander chez le
   fournisseur avant un statut de paiement vérifié selon le processus retenu.
5. Tester en environnement adapté sans modifier le mode paiement du site public :
   sélection, options, stock épuisé, prix modifié, retour/échec paiement,
   paiement de test réussi, commande et confirmations effectivement reçues.
6. Activer le parcours seulement après ces tests. Aucune autorisation de dépenses
   ni de modification irréversible n'est déduite de ce prototype.

## Diagnostic page blanche restant
Récupérer le statut HTTP et l'erreur de la soumission réelle, puis les traces
Camodo et la réception email. CAPTCHA, cookies et serveur ne sont à ce stade
que des hypothèses : aucune cause n'est confirmée.

## Sources
- https://www.camodo.com/public/fr-FR/FR_Instructions%20Module%20B2C.pdf
- https://www.camodo.com/de-DE/Home/GrosshaendlerInformationen
- https://shopify.dev/docs/api/storefront/latest
