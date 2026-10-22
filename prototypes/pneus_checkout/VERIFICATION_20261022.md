# Reprise et tarifs confirmés — 22 octobre 2026

## Changements effectués
Le calcul Python et l'essai PHP distinguent désormais :
- montage/équilibrage : 15 EUR jusqu'à 16 pouces, 18 EUR pour 17–18 pouces,
  22 EUR à partir de 19 pouces ;
- valve standard : 3 EUR par pneu ;
- recyclage : 2,50 EUR par pneu.
Valve et recyclage s'ajoutent uniquement quand le montage est choisi.
Le montant par pneu monté est donc 20,50 / 23,50 / 27,50 EUR TTC.
Chaque poste reste visible séparément dans le récapitulatif.
Sans montage, seuls les pneus sont calculés, hors autres frais résolus séparément.
Les noms de configuration sont désormais fitting_17_18_cents et fitting_19_plus_cents :
les appelants éventuels de l'ancien prototype doivent migrer ces noms.
La page d'essai PHP et la démonstration HTML reprennent ces suppléments.
Aucun paiement ou achat fournisseur n'est activé.

## Validation
18 tests Python passent. Ils couvrent les limites de diamètre (16/17/18/19),
les quantités 1/2/4, le montage facultatif, les suppléments, les frais séparés,
le stock et la péremption. Syntaxe JavaScript de l'aperçu validée par node --check.
Exemple arithmétique utilisant le dernier mail, pas une offre fournisseur actuelle :
4 SUNNY à 57,79 EUR + 4 x 20,50 EUR = 313,16 EUR TTC, hors livraison éventuelle.
La durée commerciale du gardiennage n'est pas confirmée.
Les nouveaux fichiers PHP n'ont pas encore été testés ni déployés sur LWS
pendant cette reprise. Le test navigateur d'enregistrement précédemment refusé
n'a pas été retenté.

## État Shopify effectivement relu
Le thème VOSGES PNEUS — pneus first 2026 existe et reste UNPUBLISHED.
Le thème VOSGES PNEUS PRO — refonte 2026 est toujours MAIN.
Le produit de prestations existe, statut ACTIVE, et ses trois variantes
correspondent bien à 20,50 / 23,50 / 27,50 EUR avec les bons seuils de diamètre.
Aucune variante ni thème n'a été modifié pendant ce contrôle.

## Problèmes du module observés en lecture seule
- sections/product-information.liquid appelle bien vp-montage-panier.
- blocks/buy-buttons.liquid conserve add-to-cart et accelerated-checkout :
  les deux parcours coexistent.
- Le module présélectionne le montage alors qu'il est annoncé facultatif.
- Le filtre produit cherche « pneu » dans le titre/type : la prestation de
  montage elle-même contient « pneus » dans son titre et satisfait ce filtre.
- Si le diamètre n'est pas reconnu, un forfait peut être choisi sans contrôle
  de correspondance de taille.
- Le contrôle de diamètre s'appuie sur le titre initial, sans mise à jour
  démontrée lors d'un changement de variante.
Ces constats doivent être traités avant une publication. Aucun correctif du
fichier buy-buttons précédemment refusé n'a été appliqué par un autre canal.

## Blocages conservés
La sécurité du navigateur avait refusé le transfert du secret Shopify ainsi que
le test d'enregistrement des options. La connexion autonome LWS n'est pas active.
Aucun contournement de ces refus n'est tenté.
Il reste à traiter les problèmes du thème, le lien entre sélection Camodo et
produit Shopify, l'authenticité des mails, les frais/délais fournisseur, le stock
réel, la collecte et les confirmations automatiques. Le parcours de paiement
complet reste non testé et ne doit pas être publié.
