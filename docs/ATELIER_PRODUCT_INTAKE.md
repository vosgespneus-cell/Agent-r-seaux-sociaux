# Canal atelier — entrée des pièces

Le formulaire pneus existant reste un flux client. Il ne doit pas être utilisé comme entrée de stock pièces.

Les nouvelles pièces utilisent un événement privé :
- `source=atelier`
- `kind=produit`
- `payload.source_reference` obligatoire
- `payload.reference_text` facultatif
- `payload.notes` facultatif
- `payload.media` : jusqu'à 12 URL HTTPS privées/contrôlées

Le corps brut reste dans le journal privé LWS. Les médias et notes ne sont jamais committés dans GitHub.

Après création de la tâche, `ProductIntake` extrait uniquement les champs utiles et les enregistre dans `vp_product_intake`. L'agent Produits transforme ensuite cette entrée en candidat conforme au contrat produit.

Aucune compatibilité, référence ou prix n'est déduit par le collecteur : son rôle est uniquement de transporter les faits disponibles.
