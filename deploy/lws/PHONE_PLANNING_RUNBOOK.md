# Runbook Téléphone + Planning

## Avant activation
- preflight base de données OK
- calendrier cible = vosgespneus@gmail.com
- fuseau = Europe/Paris
- lecture des disponibilités OK
- aucune utilisation du calendrier Transmalin
- création automatique désactivée tant que le runtime LWS n'est pas validé

## Cycle rendez-vous
1. Téléphone collecte le besoin
2. Planning crée une demande locale
3. Disponibilité Google vérifiée
4. Si occupé : statut unavailable, aucune création
5. Si libre : création événement privé
6. Relecture de l'événement
7. Preuve event_id + horaires + status confirmed
8. Passage local à confirmed
9. Seulement après : confirmation au client

## Incident
- API indisponible : retry
- informations manquantes : needs_information
- conflit de créneau : proposer une alternative
- absence de preuve : ne pas confirmer
- ambiguïté : human_required
