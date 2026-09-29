# Adaptateurs externes

Le cœur ne doit jamais appeler directement une plateforme. Chaque service possède un adaptateur séparé.

Contrat minimal d'un adaptateur :
- reçoit une action interne normalisée
- vérifie que l'action est autorisée
- effectue au maximum une opération externe
- retourne une référence vérifiable
- ne journalise jamais les secrets ni le contenu privé inutile

Connecteurs prévus :
- Shopify : lecture catalogue/stock, puis création ou mise à jour sous règles
- Meta/WhatsApp : réception et réponses sous règles
- Réseaux sociaux : préparation puis publication quand l'API et les autorisations le permettent
- Jotform : entrée déjà prévue sans Make
- Email : lecture/routage, puis réponses encadrées
- Planning : disponibilité et rendez-vous

Les opérations financières, suppressions importantes et actions irréversibles ne doivent jamais être autorisées par défaut.
