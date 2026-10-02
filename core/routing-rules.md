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
- whatsapp/message -> whatsapp-agent si connecteur autorisé, sinon customer-service-agent pour préparation de réponse
- shopify/order -> commerce-agent
- shopify_event -> commerce-agent
- product_intake / part_photo / product_reference -> product-agent
- shopify_draft_task / stock_event -> shopify-agent
- lead -> sales-agent
- campaign_request / video_asset -> marketing-agent
- publication_task -> social-publisher-agent si connecteur autorisé, sinon file d'attente
- heartbeat / agent_result / runtime_error -> monitor-agent
- alert -> supervisor

## Continuité de service
Un connecteur externe indisponible ne doit pas bloquer le système entier. L'action concernée passe en attente, le monitor-agent reçoit une alerte et les autres événements continuent à être traités.

Chaque retry doit réutiliser la même clé d'idempotence. Une action déjà réussie ne doit jamais être rejouée.

## Garde-fous
Aucun secret, mot de passe, token ou donnée bancaire dans GitHub.
Journaliser chaque décision et chaque erreur.
Une action financière, suppression destructive ou changement d'infrastructure critique exige une règle d'autorisation explicite.
Une publication réelle vers Shopify ou un réseau social exige un connecteur autorisé et un état compatible avec la politique d'action.
En cas d'incertitude: créer une tâche humaine ciblée au lieu d'inventer une réponse.
