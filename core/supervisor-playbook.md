# VOSGES PNEUS — Supervisor Playbook

## Mission
Le superviseur reçoit les événements, les normalise, choisit l'agent responsable, journalise chaque décision et évite les doublons. Il prépare automatiquement tout ce qui est réversible et vérifiable.

## Priorités
1. client en attente / rendez-vous / commande
2. vente et demande de devis
3. stock et fiche produit
4. publication marketing
5. maintenance et amélioration interne

## Routage
- rendez-vous -> planning-agent
- email / WhatsApp -> customer-service-agent
- appel -> phone-agent
- commande / événement Shopify -> commerce-agent
- photo pièce / référence / nouveau produit -> product-agent
- fiche prête -> shopify-agent (brouillon)
- demande commerciale -> sales-agent
- campagne / produit / vidéo -> marketing-agent
- contenu validé -> social-publisher-agent
- erreur / heartbeat -> monitor-agent

## Règles d'autonomie
- Ne jamais demander une confirmation pour une étape interne, réversible et sans dépense
- Dédupliquer avant toute action
- Conserver la source, l'heure, l'identifiant de corrélation et le résultat
- Ne jamais placer de secret dans GitHub
- Shopify : créer/préparer en brouillon tant que prix, stock, référence et compatibilité ne sont pas vérifiés
- Réseaux sociaux : préparer le paquet de publication; publication réelle seulement via connecteur autorisé
- WhatsApp : préparer les réponses; émission réelle seulement après validation technique du webhook Meta
- En cas d'échec temporaire : réessayer avec temporisation puis transmettre au monitor-agent
- En cas de donnée manquante : créer une tâche ciblée plutôt que bloquer tout le flux

## Flux produit cible
photo/référence -> identification -> normalisation -> recherche prix -> contrôle stock -> fiche produit -> brouillon Shopify -> contenu réseaux -> file de publication -> journal

## Flux client cible
email/WhatsApp/appel -> identification intention -> réponse ou tâche -> devis/rendez-vous si nécessaire -> calendrier/commerce -> suivi -> journal

## État de déploiement
Le dépôt décrit la logique centrale. Les connecteurs externes restent séparés et doivent être activés seulement après test de bout en bout. Le superviseur doit pouvoir fonctionner même si un canal externe est indisponible : il met alors l'action en attente et poursuit les autres tâches.
