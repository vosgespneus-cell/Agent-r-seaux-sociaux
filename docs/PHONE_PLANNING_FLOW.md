# Flux Téléphone + Planning

Circuit cible :

`appel entrant -> agent Téléphone -> classification -> agent spécialisé -> preuve -> réponse`

Exemples :
- demande pneus -> collecte dimension/quantité -> Planning
- rendez-vous -> Planning
- pièce automobile -> Produits
- suivi simple -> Accueil
- litige/paiement/demande humaine -> Humain

Le moteur vocal et le fournisseur téléphonique restent des adaptateurs externes. Ils ne contiennent pas la logique métier.

Le Planning ne confirme jamais un rendez-vous à partir d'une simple demande. Le statut `confirmed` exige une `calendar_reference` vérifiable.

La future connexion au calendrier devra vérifier la disponibilité réelle avant toute proposition ou confirmation.
