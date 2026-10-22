# Relais de devis : exécution planifiée vérifiée

Le 22 octobre 2026, la sixième tâche Cron LWS a été enregistrée, active, toutes les cinq minutes. Les cinq tâches existantes sont préservées. Aucun nouvel abonnement ni paiement.

## Périmètre installé
- Réception en lecture seule de la boîte atelier existante.
- Essais uniquement : deux adresses appartenant à l'exploitant, commentaire contenant le mot « test », UIDs à partir du dernier essai connu et identité de boîte vérifiée.
- Journal des tentatives, protection contre les doublons, verrou de processus et limites de débit. Une tentative incertaine n'est pas renvoyée automatiquement.
- Aucun achat fournisseur, création de commande Shopify ou paiement.
- Le relais manuel demeure désactivé hors de la fenêtre d'un envoi de test.

## Preuve du déclenchement automatique
Journal privé observé après reconnexion à LWS : 22 octobre 2026 à 13:05:03, heure de Paris.

```text
INTAKE_TEST_RESULT {"scanned":1,"already_attempted":1,"ineligible":0,"recent_duplicate":0,"eligible":0,"sent_attempts":0,"errors":0} mode=--run-tests payment_enabled=0
```

Le fichier d'erreurs est vide. Le réglage du relais manuel est false. Ce résultat prouve le déclenchement planifié et la reconnaissance d'un devis déjà traité. Il ne constitue pas encore la preuve d'un nouvel envoi déclenché automatiquement après l'arrivée d'un nouveau devis.

## Ouverture du lien
La correction masque l'ancienne sélection pendant l'ouverture, traite les changements de fragment et masque les choix si le lien est invalide. Vérification navigateur : le lien du devis reçu affiche quatre Austone 205/55 R16 à 46,03 EUR, total 184,12 EUR, montage facultatif et gardiennage. Un lien invalide masque ensuite la sélection précédente. Aucun enregistrement d'options n'a été soumis.

Validation : 22 tests Python et 7 tests JavaScript réussis ; auto-tests PHP d'import et de relève réussis sur LWS avant installation.

## Conditions avant ouverture aux clients
Le parcours complet reste inachevé et non publié. Les refus automatiques précédents concernant l'enregistrement des options dans le navigateur et le transfert du secret Shopify n'ont pas été contournés. Il reste à valider la sauvegarde des options et l'intégration au panier, l'authenticité des mails pour une diffusion générale, le stock, les frais et délais fournisseur, la période de gardiennage et le paiement de bout en bout. Le relais est limité aux essais ; ne pas le présenter comme un service client en production.
