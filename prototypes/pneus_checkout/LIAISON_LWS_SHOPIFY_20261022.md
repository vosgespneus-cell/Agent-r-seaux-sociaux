# Liaison LWS–Shopify installée et vérifiée en essai

Le 22 octobre 2026, l'exploitant a explicitement autorisé le transfert du secret de l'application « VOSGES PNEUS - commandes pneus » vers son hébergement LWS, hors dossier public, pour les brouillons et la vérification des commandes. Après une première saisie annulée, il a retrouvé lui-même le secret puis l'a saisi par l'interface confidentielle browserAuth. La réponse visible a confirmé la connexion. Aucune valeur secrète n'a été lue, journalisée ou ajoutée au dépôt.

## Installation
- shopify_bridge.php est installé hors racine web, mode 0600.
- Les fichiers d'accès et de jeton sont privés, modes 0600 ; la fenêtre de configuration a été refermée (expires_at=0).
- Accès limité aux scopes read_orders / read_draft_orders / write_draft_orders ; tout scope supplémentaire provoque un refus.
- Renouvellement du jeton avant expiration, via le mécanisme client_credentials documenté par Shopify ; HTTPS vérifié, hôte boutique fixé, aucune redirection HTTP suivie, réponse bornée et erreurs expurgées.
- La tâche Cron d'essai existante, toutes les cinq minutes, appelle désormais la liaison après la relève mail. Aucun nouveau cron et aucun abonnement.
- La page privée affiche les états de rapprochement lorsqu'un lien valide est ouvert ; le lien historique a expiré avant la vérification visuelle de ce nouvel affichage. Syntaxe PHP validée, contrôle visuel de cette bannière encore à faire sur un lien valide.

## Vérifications réelles
La connexion serveur a retrouvé le brouillon D2 et sa commande de test #1003 :

```text
BRIDGE_RESULT {"saved":1,"created":0,"reconciled":1,"unchanged":0,"held":0,"errors":0} payment_enabled=0 invoices_sent=0
```

Le renouvellement a été forcé par expiration du cache local, sans afficher le jeton. L'appel suivant a réussi sans doublon ; le cache renouvelé est valide. Le fichier de configuration initiale est fermé.

Passage Cron effectif à 14:15:03, heure de Paris :

```text
BRIDGE_RESULT {"saved":1,"created":0,"reconciled":0,"unchanged":1,"held":0,"errors":0} payment_enabled=0 invoices_sent=0
```

Le journal d'erreurs est vide. Ces résultats prouvent la communication autonome de LWS avec Shopify, le renouvellement et le rapprochement programmé de la commande existante. La branche de création automatique d'un nouveau brouillon depuis LWS est installée mais n'a pas encore été exercée par un nouveau devis sauvegardé.

Validation : syntaxe PHP sans erreur, sept auto-tests PHP réussis ; 22 tests Python et sept tests JavaScript réussis. Les opérations GraphQL ont été soumises à validation ; le validateur connecté délègue la validation à l'exécution. Le rapprochement réel a réussi via l'API.

## Protections et limites
Le traitement reste limité aux deux adresses d'essai de l'exploitant et aux sources owner-test. Il lit les sélections sauvegardées, calcule les lignes et compare les totaux EUR/TTC avant toute création privée. Un journal local et une recherche Shopify évitent les doublons ; une tentative incertaine, un total divergent, des choix modifiés ou un devis expiré sont retenus pour revue. La création d'un nouveau brouillon expiré est interdite.

Aucune facture envoyée par ce traitement ; aucun lien payable diffusé ; aucune commande fournisseur ; aucun paiement déclenché par le connecteur. Les brouillons d'essai ne définissent pas une politique de livraison ou retrait. La page publique Shopify et son thème sont préservés.

Le paiement simulé et la confirmation #1003 ont été vérifiés auparavant ; Shopify Payments a été remis en mode réel. L'ouverture générale reste conditionnée à l'authenticité des mails, au stock fournisseur actualisé, aux frais et délais, aux modalités de remise, à la durée du gardiennage et au test d'un nouveau devis traversant automatiquement la liaison. La liaison active en essai n'est pas le parcours commercial complet en production.
