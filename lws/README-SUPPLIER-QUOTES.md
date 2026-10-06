# Devis privés à partir des prix fournisseur

`vp_lead_quotes.php` est appelé à chaque exécution de `vp_leads.php` et produit `vp_lead_quotes.json` dans le dossier privé LWS. Aucun devis n'est envoyé et aucune commande n'est passée.

La collecte automatique Allopneus n'est pas raccordée. Un accès navigateur PRO ne constitue pas un accès fournisseur pour LWS. La récupération d'un flux officiel reste nécessaire pour actualiser les prix sans intervention.

Le fichier privé `vp_supplier_feed.json` accepte cette structure (exemple fictif) :

```json
{
  "schema": 1,
  "observed_at": 1791280000,
  "source_kind": "manual_browser",
  "offers": [{
    "brand": "Exemple",
    "model": "Exemple",
    "size": "225/45R17",
    "season": "allseason",
    "indices": "94V",
    "purchase_cents": 7000,
    "price_basis": "PA_HT",
    "vat_basis_points": 2000,
    "stock_quantity": null,
    "shipping_total_cents": null,
    "delivery_verified": false
  }]
}
```

Prix d'achat PA HT convertis avec TVA 20 % explicite et arrondi au centime par pneu. PA TTC accepté directement. Prix de vente PV refusé pour éviter d'ajouter deux fois une marge. La TVA doit être vérifiée sur les données et factures du fournisseur ; ce module ne détermine pas le régime fiscal de l'entreprise.

Marge 500 centimes/pneu, montage 1500 centimes pour 13–15 pouces, 1800 pour 16–17 pouces, 2200 au-delà. Livraison totale ajoutée une seule fois. Une livraison inconnue ne vaut pas zéro. Une quantité inconnue ne produit pas de total.

Les données de plus d'une heure, dates futures ou tailles/saisons différentes ne produisent aucune offre. Les indices, stocks, délais et données relevées manuellement demandent une vérification. Chaque proposition reste un brouillon avec validation humaine avant envoi.

Tests sans message ni commande : `vp_php vp_leads.php --selftest-quotes`. Ne pas enregistrer de prix commerciaux réels, identifiants ou cookies dans GitHub. Le flux et les brouillons restent dans le dossier LWS privé, avec permissions 600.
