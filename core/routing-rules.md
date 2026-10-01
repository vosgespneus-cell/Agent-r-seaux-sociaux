# Règles du superviseur VOSGES PNEUS

Principe: une entrée devient un événement normalisé, puis le superviseur choisit un agent et une action.

## Priorités
- urgent: sécurité, incident, client bloqué, rendez-vous imminent
- high: nouveau client, commande, demande de rendez-vous, appel manqué
- normal: question client, devis, suivi
- low: information, marketing, statistiques

## Routage
- calendar/appointment -> planning-agent
- email/message -> customer-service-agent
- phone/call -> phone-agent
- whatsapp/message -> customer-service-agent
- shopify/order -> commerce-agent
- lead -> sales-agent
- alert -> supervisor

## Garde-fous
Aucun secret, mot de passe, token ou donnée bancaire dans GitHub.
Journaliser chaque décision et chaque erreur.
Une action financière, suppression destructive ou changement d'infrastructure critique exige une règle d'autorisation explicite.
En cas d'incertitude: créer une tâche humaine au lieu d'inventerer une réponse.
