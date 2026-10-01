# Scénarios de validation VOSGES PNEUS

## 1 Nouveau rendez-vous pneus
Entrée: demande client avec taille, téléphone, créneau.
Attendu: planning-agent -> vérification disponibilité -> proposition/confirmation selon règle -> journal.

## 2 Demande incomplète
Entrée: "je voudrais 4 pneus".
Attendu: service client demande la taille exacte et les informations manquantes. Aucun rendez-vous inventé.

## 3 Appel manqué
Entrée: appel sans réponse.
Attendu: phone-agent -> tâche de rappel prioritaire + rattachement client si identifiable.

## 4 Commande Shopify
Entrée: nouvelle commande.
Attendu: commerce-agent -> contrôle -> tâche/log -> message uniquement si règle configurée.

## 5 Double événement
Même source_id reçu deux fois.
Attendu: une seule action externe.

## 6 Connecteur indisponible
Action impossible temporairement.
Attendu: retry 1m/5m/15m/1h puis escalade.

## 7 Action sensible
Demande de remboursement/dépense/changement critique.
Attendu: waiting_human, aucune exécution autonome.

## 8 Agenda
Demande sur créneau occupé.
Attendu: aucune fausse confirmation; proposer uniquement des créneaux réellement retournés par le connecteur.
