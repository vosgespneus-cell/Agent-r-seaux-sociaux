# Finalisation LWS — 11 novembre 2026

## Exécuté et vérifié

- Sauvegarde ciblée privée : home/nina_finalisation_20261111/ (0700), fichiers 0600, quatre sources originales et crontab avant intervention. manual_tasks.before.json conserve les deux lignes MySQL avant clôture. La sauvegarde globale du matin n'a pas été écrasée.
- Nouveau module privé home/vp_delivery_guard.php : 12 destinataires en échec suspendus, plus l'adresse de remplacement non vérifiée. Normalisation casse/espaces. Liste conservée sur LWS et dans la mémoire privée.
- vp_leads.php : refus de transport avant lecture des identifiants SMTP.
- vp_lead_followup.php : exclusion avant requête/envoi; aucun renvoi automatique d'une tentative déjà enregistrée, y compris failed (ancienne reprise possible après 900 secondes supprimée). accepted, sending et uncertain restent protégés.
- vp_pneus_review/client_intake.php : exclusion avant création du devis et appel mail; reçu suppressed persistant en mode --run pour ne pas retraiter la même source.
- vp_pneus_review/client_bridge.php : les deux devis clients déjà traités manuellement sont ignorés avant tout appel Shopify. Aucune déclaration de paiement.
- Deux tâches clients rapprochées par identifiant de devis, adresse et tâche, sauvegardées puis passées de review à closed. Relecture SQL : les deux closed, compteur review 11 → 9.
- Crontab strictement inchangée (comparaison de contenu). Superviseur du matin conservé : reprises supplémentaires worker seulement, état error explicite avec fin, 3/24 h et 1800 secondes minimum.
- Cron observé après déploiement : CLIENT_BRIDGE_RESULT saved=2, created=0, held=2, errors=0, manually_handled=2, invoices_sent=0, supplier_orders=0. Intake : already_attempted=4, sent_attempts=0, errors=0.
- Les deux held correspondent aux anciens tests #D10/#D11; les clients traités sont #D12/#D13. Ne pas confondre ces ensembles.

## Tests

- Syntaxe PHP : module et quatre scripts validés avant déploiement; remplacement atomique, empreintes comparées.
- DELIVERY_GUARD_OK : 13 adresses, 39 variantes, 156 vérifications répétées, 4 identifiants protégés, send_calls=0, network=0.
- OUTBOUND_DEDUP_OK : accepted/sending/uncertain/failed, 16 vérifications SQLite en mémoire, send_calls=0.
- Ces deux tests ont également réussi en chargeant le fichier réellement déployé.
- CLIENT_SELF_TEST_OK : 12 cas, sans réseau ni base.
- BRIDGE_SELF_TEST_OK : 7 cas, sans paiement.
- Résultat final privé : home/nina_finalisation_20261111/final_verify.json, relevé du 11/11 à 11h47 Paris.

## Empreintes SHA-256 déployées

| Fichier | SHA-256 |
|---|---|
| vp_delivery_guard.php | c4599d90507ae4741b688d0a866147f7a8add61054f1ff6baaebdac1b0b8c3bd |
| vp_leads.php | 2f7ee8141fa5bf17db7529e4a87666a62c92345f831fb09fd2c52fe04739a2b1 |
| vp_lead_followup.php | 7d605d861aa1ab892fd1e6fc06cc4b7d98ad7e723b7900017a9c4ccb45c686cd |
| vp_pneus_review/client_intake.php | 14d1f810012cc53cacd92fb6869232d4ba3eaada9131e43071501c278b209011 |
| vp_pneus_review/client_bridge.php | fe763fa345f32c00310553c0b5bf199cbb4b4940d51c492dfe3d336c97fc1266 |

## Prospection : origine et portée

Les en-têtes des messages examinés indiquent un envoi Gmail API HTTPREST depuis Gmail, pas une preuve de campagne exécutée par LWS. Les sources récupérées sont des CSV de prospection constitués le 10 novembre à partir de guides/annuaires professionnels. La liste nommée 100 prospects contient 117 lignes; une même adresse est présente deux fois sous deux noms. Quatre fichiers existants ont été mis à jour avec exclusions, motif, date et référence Gmail, en conservant toutes les anciennes colonnes et lignes (4/9/9/4 lignes exclues). Aucun nouvel envoi.

Les douze non-distributions ont été lues intégralement : cinq destinataires invalides, une boîte pleine, trois domaines NXDOMAIN, un refus de relais, une boîte indisponible avec code 5.7.0, une boîte désactivée. Ne pas assimiler tous les cas à une entreprise disparue. Les automatismes ChatGPT examinés ne comportent pas de campagne récurrente de prospection identifiée. La protection LWS et les CSV ne constituent pas un blocage Gmail universel ou un contrôle d'un expéditeur tiers inconnu.

## Restant ouvert / bloqué

- 9 tâches review : publicité/engagement (2), demandes à qualifier (4), échanges de réseaux montage (2), enquête satisfaction support (1). Pas de clôture globale.
- 1 rendez-vous review : référence 71726705, début 10/11/2026 18h Paris, fin absente. Exécution et rapprochement Agenda non attestés. Ne pas créer un nouvel événement.
- Les 2 anciens essais held restent retenus. Aucune relance ni création de devis.
- Jotform : derniers passages JOTFORM_SYNC_OK, anciens HTTP 0 / cURL 28 encore présents sans horodatage par ligne. Aucun incident nouveau attribué à ces lignes.
- Superviseur toujours attention, 2 incidents fonctionnels : ne pas annoncer tout au vert.
- Source complète de quatre adresses absentes des CSV récupérés et identité exacte du client applicatif Gmail non établies.
- Les cinq fichiers modifiés sont sauvegardés sur LWS; ce document versionne les preuves, pas leurs sources complètes. Ne pas présenter GitHub comme sauvegarde intégrale actualisée.
- Pas de paiement réel, commande fournisseur, campagne, nouvelle dépense ou activation chauffeurs.

## Retour arrière

Restaurer les quatre scripts originaux depuis home/nina_finalisation_20261111/ vers leurs emplacements respectifs, puis vérifier la syntaxe. Conserver le module de garde tant qu'un script le charge. Pour les deux statuts MySQL, utiliser les lignes exactes de manual_tasks.before.json dans une transaction ciblée, après nouvelle vérification d'absence d'évolution; ne pas restaurer globalement la base. Les CSV conservent leur identité et leur historique de versions.


## Contrôle complémentaire e-mails — 11 novembre 2026, vers 12h15 Paris

### Vérifié en lecture sur la production

- Les cinq empreintes SHA-256 ci-dessus sont toujours identiques. Aucun redéploiement ni changement de crontab. Empreinte de la crontab courante et de la sauvegarde ciblée identique : 2f8e471bddc7afebc1867f71ff01f332b7e431203ef53ffea9231952cce74688.
- Derniers passages observés : mail 12h10, leads 12h14, state=ok et exit_code=0. Les journaux montrent MAIL_SYNC_OK count=0 et LEAD_RUN added=0 pending=0. Cela ne teste pas une nouvelle demande réelle. Les compteurs historiques failures restent 46 et 31.
- Fichiers d'erreur mail/leads/superviseur : dernières modifications respectivement 30/10, 22/10, 22/10. Derniers cycles superviseur : checked=10, retries=0, incidents=2. Pas de certification globale de santé.
- Recherche textuelle Gmail API dans les PHP et shell de home : aucun fichier correspondant à gmail.googleapis, gmailapi, HTTPREST ou googleapis.com/auth/gmail. Résultat limité à ces chemins, extensions et motifs, pas une preuve d'absence de toute intégration.
- Trois workflows du dépôt lus : python-check.yml, runtime-tests.yml, supervision.yml. Ils exécutent des tests et exemples ; aucun envoi Gmail ni déclenchement planifié de campagne dans ces définitions.

### Délivrabilité

- DNS vosgespneus.com : SPF unique observé « v=spf1 mx:vosgespneus.com a:mail.vosgespneus.com a:mailphp.lws-hosting.com -all » ; DKIM publié au sélecteur dkim ; DMARC « v=DMARC1; p=quarantine; ». Aucun DNS modifié.
- Un message réel de contact@vosgespneus.com vers la boîte de contrôle Gmail, daté du 10/11, a été relu : SPF/DKIM/DMARC pass et étiquette INBOX. C'est une preuve sur cet exemple, pas sur les messages clients et pas sur l'onglet Principal.
- Gmail.com : SPF redirect=_spf.google.com ; DMARC p=none, sp=quarantine. La signature Gmail d'un message effectivement livré reste à vérifier ; aucun nouveau test envoyé.
- Un original de campagne confirme HTTPREST et le numéro de projet 77377267392. Ce numéro ne suffit pas à attribuer une application OAuth. Identité de l'application toujours inconnue.
- Recherches Gmail après le 11/11 : aucun envoyé ni nouveau mailer-daemon retourné au moment de la requête. Recherche ciblée de STOP/désinscription depuis le 10/11 : pas de demande identifiée dans les résultats ; ce n'est pas un contrôle exhaustif des oppositions ni une validation du mécanisme automatique.

### Correction réellement appliquée

- Automatisation existante de coordination mise à jour sans modifier sa cadence ni créer de tâche : suspension explicite de la prospection VOSGES PNEUS jusqu'à levée par Adam, interdiction de renvoyer les tentatives failed/incertaines, respect des exclusions et STOP. Les réponses légitimes clients restent permises avec leurs protections. Réponse de mise à jour confirmée avec le texte enregistré.
- Aucune permission révoquée, aucun prospect contacté, aucun code de production changé. Les tests anti-doublons déjà validés n'ont pas été rejoués inutilement.

### Blocage réel et limites

Le compte Google est déconnecté dans le navigateur ; la liste des applications autorisées exige une authentification utilisateur. Le connecteur Gmail permet la lecture des mails mais ne fournit pas cette administration OAuth. LWS reste accessible. Le blocage universel des campagnes Gmail ne peut pas être certifié tant que l'expéditeur n'est pas identifié et contrôlé. Les domaines et adresses professionnels supplémentaires, le mécanisme STOP de bout en bout, et la livraison actuelle sur boîtes de contrôle restent à vérifier. Ne pas annoncer « sécurisation définitive terminée ».
