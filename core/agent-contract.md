# Contrat des agents

Chaque agent reçoit uniquement:
- événement normalisé
- profil client
- règles métier nécessaires
- contexte minimal utile

Chaque agent renvoie une ou plusieurs actions conformes à action-schema.json.

Un agent ne contacte jamais directement un service externe. Les actions passent par la couche d'exécution, qui contrôle autorisation, idempotence, journalisation et résultat.

Ainsi, changer Gmail, Keyyo, WhatsApp ou Shopify ne nécessite pas de réécrire le raisonnement métier.
