# Agent de suivi des demandes de pneus

Déployé sur LWS le 6 octobre 2026. Le code reste dans cette branche GitHub ; aucune exécution GitHub Actions n'est nécessaire.

## Fonctionnement

Le collecteur Jotform existant alimente `vp_events`. `vp_leads.php` importe les demandes du formulaire 262624697144060, exclut le test technique explicite et conserve les brouillons dans la base privée LWS. La tâche cron exécute `vp_run.php leads` chaque minute. Le délai de collecte Jotform s'ajoute à cette minute.

Une alerte groupée est envoyée au propriétaire pour les nouvelles demandes. Un rappel quotidien après 9 h (Europe/Paris) concerne les demandes encore ouvertes. Les alertes ne contiennent pas les coordonnées clients : elles donnent la dimension et un lien vers la boîte Jotform sécurisée. Les envois sont dédupliqués ; un résultat SMTP incertain n'est pas relancé automatiquement.

## Tarifs

- Majoration commerciale : 5 EUR par pneu.
- Montage et équilibrage par pneu : 15 EUR en 13–15 pouces, 18 EUR en 16–17 pouces, 22 EUR au-delà de 17 pouces.
- Total : quantité × (achat TTC unitaire + 5 EUR + montage si demandé) + livraison totale, une seule fois.

Les promotions conditionnelles ne sont pas déduites avant vérification. La quantité « 4+ » doit être précisée. Les indices de charge et vitesse, le prix fournisseur, la disponibilité et la livraison doivent être vérifiés avant toute offre ferme. La majoration de 5 EUR ne constitue pas une estimation du bénéfice net.

## Contrôles et exploitation

Les commandes se lancent avec le PHP 8.3 et la configuration d'extensions du serveur LWS, identiques au cron existant.

- `vp_leads.php --selftest` : calculs, dimensions et exclusion du test.
- `vp_leads.php --selftest-db` : tables temporaires, ingestion, doublons, alerte unique et clôture ; aucun envoi externe.
- `vp_leads.php --preview` : données et brouillons privés. Ne pas publier cette sortie.
- `vp_leads.php --close JF-ID` : clôture du suivi et arrêt des rappels de cette demande, après traitement réel.

La surveillance existante suit le job `leads`, son dernier succès et ses erreurs. Les paramètres d'alerte et le mot de passe SMTP restent dans les fichiers privés LWS. Le SMTP utilise TLS et le compte de messagerie LWS existant.

## État et limites vérifiés

Trois demandes réelles ont été importées ; le test technique est exclu. Tests fonctionnels et base de données réussis sur LWS. Une première alerte a été reçue dans Gmail, initialement en spam, puis remise dans INBOX et marquée importante et étoilée. Un filtre permanent anti-spam n'a pas été installé : la session navigateur Gmail nécessite une connexion.

Le passage automatique du 6 octobre à 13:51:01 Europe/Paris a terminé avec l'état ok et zéro échec. Aucun message client ni commande fournisseur n'a été envoyé. La consultation Allopneus et la préparation de propositions sont encore supervisées ; cet agent n'effectue pas seul une collecte continue des prix fournisseur.
