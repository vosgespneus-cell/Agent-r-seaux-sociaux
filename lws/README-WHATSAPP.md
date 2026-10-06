# Assistant WhatsApp sur LWS

Le webhook public charge `vp_whatsapp.php`, conservé hors du dossier web. Ce fichier utilise `vp_wa_delivery.php` pour préparer et envoyer les réponses. `vp_run.php whatsapp` est exécuté chaque minute sur LWS et enregistre son état privé. Le superviseur lit cet état et distingue une exécution réussie d'une connexion en production.

## Configuration privée

`vp_wa_config.php` reste hors de GitHub et du dossier web, avec les droits 0600. Les clés existantes `receive_enabled`, `drafts_enabled`, `send_enabled`, `verify_token`, `app_secret`, `phone_ids` et `daily_request_limit` sont conservées. L'envoi exige aussi `access_token`. `graph_version` vaut par défaut `v26.0`. `production_phone_id` doit correspondre à l'identifiant API du numéro réel et figurer dans `phone_ids`; ce n'est ni le numéro de téléphone ni l'identifiant du compte WhatsApp.

Au déploiement du 6 octobre 2026, la réception est autorisée pour le numéro de test uniquement. La préparation et l'envoi restent désactivés, et aucun jeton d'envoi n'est installé. Le numéro réel est associé à Meta Business Suite en tant que compte Application WhatsApp Business. Cela ne prouve pas son raccordement Cloud API. La publication Meta et ce raccordement restent à terminer.

## Fonctionnement

- Vérification HMAC des notifications Meta et filtrage des identifiants de numéros autorisés
- Déduplication des messages entrants, regroupement après 45 secondes de silence et verrou d'exécution
- Maximum de 10 appels IA par jour; les pièces jointes et demandes explicitement sensibles pour l'activité reçoivent un accusé de réception déterministe sans appel IA
- Prix, devis, réservations, recrutement et réclamations demandent une vérification humaine; aucune sélection de candidat ni confirmation de stock ou de rendez-vous
- Réponses texte seulement, à la suite d'un message entrant récent; marge de dix minutes avant la limite de 24 heures
- File d'envoi privée avec réservation atomique de chaque réponse; un timeout ou une interruption reste `uncertain`, sans réessai automatique
- `sent` signifie accepté par Meta, et ne prouve pas la livraison ou la lecture par le destinataire
- Les demandes humaines restent en état `review`, même après l'envoi de leur accusé de réception

Le superviseur signale les envois `failed`, `uncertain`, `expired` et `blocked`, ainsi que les demandes humaines. Les messages et jetons ne sont jamais inclus dans son résumé. Les réponses marketing initiées par l'entreprise et les modèles payants ne sont pas utilisés par ce moteur. Les appels OpenAI ne sont pas gratuits.

## Vérification avant activation

1. Publier l'application Meta et raccorder le numéro réel à la Cloud API
2. Accorder les permissions nécessaires à cette application et installer son accès privé sur LWS
3. Configurer le véritable identifiant API du numéro dans `production_phone_id` et `phone_ids`
4. Vérifier la réception d'un vrai message de test avant d'activer préparation et envoi
5. Vérifier une réponse effectivement reçue par le téléphone de test
6. Vérifier le passage cron suivant, une demande à transmettre à un humain et l'accès opérationnel de l'équipe à ces demandes

L'agent ne peut être déclaré autonome en production qu'après ces vérifications. Les engagements commerciaux et les décisions humaines restent à traiter par l'équipe.

## Tests sans appel externe

`vp_whatsapp.php --selftest` vérifie signatures, extraction, filtrage, fenêtre de réponse et construction des envois. `test-wa-delivery.php` utilise des tables MySQL temporaires et un transport simulé pour vérifier désactivation, envoi unique, expiration, filtrage, maintien de la revue humaine, timeout et interruption. Il ne contacte ni Meta ni OpenAI et ne modifie pas les tables réelles.

Le lanceur `vp_run.php --selftest` vérifie succès, erreurs, conservation du dernier succès, verrou et permissions privées. Tous ces tests ont passé sur le PHP 8.3 LWS le 6 octobre 2026.
