# Essai du relais par email — 22 octobre 2026

Le devis Camodo UID 828, reçu à 11:02:45 Europe/Paris, contient quatre WEST LAKE ZuperEco Z-107 205/55 R16 91V, 45,70 EUR TTC par pneu, 182,80 EUR hors prestations. Son import privé sur LWS et son affichage navigateur ont été vérifiés. Montage/valves/recyclage ajoute 82 EUR pour quatre pneus ; total arithmétique hors autres frais : 264,80 EUR.

review_notify.php est installé dans /home/vp_pneus_review avec permissions 0600. Contrôle SHA-256 et syntaxe PHP réussis. Cinq auto-tests réussis, puis simulation sur le devis réel avec vérification de l'adresse propriétaire. Un seul envoi d'essai a été explicitement autorisé par le propriétaire et accepté par PHP mail() sur LWS : NOTIFICATION_ACCEPTED delivery_unverified=1 payment_enabled=0 sends=1. L'acceptation par le transport ne prouve pas la livraison ; confirmation du destinataire encore nécessaire.

Le message est marqué ESSAI PRIVE, ne réserve rien et ne permet aucun paiement. Le lien expire après une heure. Le script est CLI uniquement, limité à un destinataire propriétaire vérifié et un UID précis par configuration privée. Le journal SQLite refuse les tentatives répétées sur le même devis ; une erreur ou un résultat incertain ne provoque aucun nouvel envoi automatique. Aucun secret, lien personnel ou coordonnée du client n'est enregistré dans le dépôt.

Ce travail n'active PAS un service automatique pour tous les clients. Aucun cron de notification n'a été créé. Le raccordement IMAP continu, la validation de provenance des mails, la gestion des doublons sémantiques entre plusieurs UID, les liens expirés, les sauvegardes des options précédemment bloquées, l'achat fournisseur, Shopify et le paiement restent à valider avant production.

Inspection Camodo : les rubriques Paramètres, Votre nom de domaine, Modèle et Code source ne présentent aucun réglage de redirection après envoi. Le module propose iframe ou lien externe. Aucune configuration Camodo n'a été modifiée. La navigation d'administration a perdu sa session dans l'iframe, puis a fonctionné dans une page autonome. Cela étaye une piste cookies mais ne démontre pas la cause du retour au formulaire après envoi.
