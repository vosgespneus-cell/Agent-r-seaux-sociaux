# Google Calendar — VOSGES PNEUS

Compte cible : `vosgespneus@gmail.com`  
Fuseau : `Europe/Paris`

## Flux
1. Le Planning reçoit une demande structurée
2. Il vérifie les créneaux occupés du calendrier VOSGES PNEUS
3. Un chevauchement interdit la création
4. Si le créneau est libre, l'adaptateur crée un événement privé
5. L'événement est relu
6. Le rendez-vous local passe à `confirmed` uniquement si l'ID Google, le début, la fin et le statut confirmé sont vérifiés

## Isolation
Le calendrier Transmalin ne doit jamais être utilisé par ce flux.

## Sécurité
Les coordonnées et notes client restent dans les systèmes privés. Aucun contenu client n'est commité dans GitHub.

## État vérifié
Le 29 septembre 2026, la connexion Google Calendar du compte VOSGES PNEUS a été vérifiée en lecture de disponibilité. La création automatique reste à activer dans le runtime après déploiement et test contrôlé.
