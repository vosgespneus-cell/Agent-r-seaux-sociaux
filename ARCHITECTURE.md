# Architecture de la machine

ENTREES
email / agenda / téléphone / WhatsApp / e-commerce / web / humain
↓
ADAPTERS
convertissent chaque source vers event-schema
↓
SUPERVISEUR
priorité + contexte + routage + politique d'action
↓
AGENTS
planning / service client / téléphone / commerce / vente / marketing
↓
ACTIONS
réponse / rendez-vous / tâche / commande / publication / alerte
↓
JOURNAL + ETAT
audit, anti-doublon, retry, heartbeat, escalade

Le moteur reste générique.
Les règles propres à une entreprise vivent dans clients/<client-id>/.
Les secrets restent hors du dépôt.

VOSGES PNEUS est l'instance pilote servant à valider le système avant création d'un installateur simplifié.
