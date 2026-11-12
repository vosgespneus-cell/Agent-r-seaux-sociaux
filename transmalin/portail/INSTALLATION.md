# Reprise du portail du 12 septembre — version de travail du 12 novembre

NON DÉPLOYÉ. Aucun compte réel créé. La copie originale est conservée dans l'archive.

Réutiliser le projet Apps Script existant s'il est retrouvé, sans créer un second service.
Tableau cible : TRANSMALIN — Tableau de bord 2026. Ne pas partager ce tableau aux chauffeurs.
Propriétés privées du projet : SPREADSHEET_ID, PROOF_FOLDER_ID (dossier sans partage ni héritage public), DRIVER_ACCOUNTS_JSON.
Chaque compte validé par Adam utilise son email comme clé ; valeurs : id stable opaque, label, missionLabel correspondant exactement à Courses ATS colonne C, active, version, salt aléatoire, hash SHA256(salt + ':' + mot de passe aléatoire d'au moins 24 caractères). Aucun exemple de mot de passe réutilisable. Pas de réemploi des anciens PIN. Incrémenter version ou désactiver active révoque immédiatement les sessions. Ne pas exposer ces propriétés dans GitHub.

Avant activation : sauvegarder le projet et son déploiement existant ; vérifier les partages Drive et Sheets ; tester sur une copie avec deux comptes fictifs ; tester les autorisations et justificatifs sur Google ; vérifier les quotas gratuits et le propriétaire. Déployer en tant que propriétaire seulement après ces contrôles. Aucun déploiement effectué par cette intervention. Ne pas intégrer en iframe : protection d'encadrement conservée ; préférer un lien depuis WordPress.

Les missions consultables ont une cohorte Active (U), un ID unique (Q) et une attribution personnelle exacte (C). Mouna seule renseigne les affectations dans le tableau privé. Les noms historiques de la préfacture ne constituent pas des comptes. Les lignes Historiques sont exclues de la saisie et des alertes d'absence. Aucune date de démarrage réelle configurée.

Écritures dans Réponses chauffeurs : 25 colonnes existantes conservées ; Y conserve son ancien titre Jotform pour compatibilité mais reçoit l'UUID du portail. Horaires réels U/V comprennent les dates pour les trajets de nuit. Les frais carburant sont des déclarations à vérifier, pas une validation de remboursement. Pas de paiement ni d'envoi externe.

Dédoublonnage sous verrou par compte + UUID ou mission. Une nouvelle tentative conserve l'UUID après erreur ; une correction administrative reste à Mouna, sans écrasement automatique. Les photos sont privées, jamais renvoyées dans l'historique chauffeur ; aucune identité patient ne doit y figurer. Un fichier orphelin après échec est réutilisé par son nom stable. Journaux applicatifs sans payload ; historique des déclarations append-only dans le tableau. Les protections effectives du déploiement Google restent à valider.

Rapprochement : demande, déclaration, préfacture, facture et règlement restent des preuves séparées. La présence dans une préfacture ne prouve pas la réalisation. Les montants mensuels facturés ne sont pas répartis automatiquement sur les courses. Factor distinct du règlement du donneur d'ordre. Le module reconcile.py traite un export JSON minimisé et ne contacte aucun service ; raccordement automatique Gmail/Sheets et cron OVH encore à faire après accès au déploiement existant.
