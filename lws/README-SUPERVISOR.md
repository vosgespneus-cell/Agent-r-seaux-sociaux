# Superviseur LWS

Programme privé `vp_supervisor.php`, à lancer toutes les cinq minutes. Dépendances : `vp_monitor.php`, `vp_run.php`, `vp_leads.php` et leurs modules/configurations existants. Aucun secret dans GitHub.

Surveille les huit traitements rapportés par le moniteur. Reprises autorisées uniquement pour `worker`, `calendar` et `leads`, qui possèdent leurs protections contre les doublons. Ne relance pas les appels IA payants, WhatsApp, la collecte mail/Jotform ou le commercial. Signale leurs incidents au propriétaire.

Trois tentatives maximum dans une fenêtre glissante de 24 heures, espacées de 30 minutes. Ne relance pas un heartbeat encore marqué `running`, même périmé : il alerte afin d'éviter de tuer ou doubler une opération incertaine. Un verrou empêche deux superviseurs simultanés ; le runner conserve ses propres verrous.

Une identité d'incident est persistée avant envoi. Les alertes réutilisent l'outbox SMTP privée : un envoi accepté, en cours ou incertain n'est pas répété. Une nouvelle panne après résolution ouvre un nouvel incident. Les files à vérifier et les limites atteintes sont aussi signalées. Adresse propriétaire et expéditeur issus de `vp_leads_config.php`.

État privé : `vp_supervisor_status.json`, historique : `vp_supervisor_state.json`, journaux : `vp_supervisor.err`, `vp_supervisor_retries.log`. Permissions privées 600. Le superviseur ne répare pas le code, ne déploie pas GitHub et ne peut pas avertir si tout LWS ou sa propre messagerie est indisponible. Un contrôle externe reste nécessaire pour détecter ces pannes globales.

Tests sans envoi externe : `vp_php vp_supervisor.php --selftest`. Scénarios : reprise réussie, liste autorisée, processus en cours, délai entre tentatives, limite quotidienne, identité stable d'incident, nouvelle panne après résolution. Le déploiement doit vérifier les tests sur PHP LWS puis un passage réel avant d'ajouter le cron, en conservant les tâches existantes.
