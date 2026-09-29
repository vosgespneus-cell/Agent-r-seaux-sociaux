# Cerveau IA — runtime LWS

Le superviseur utilise l'API Responses d'OpenAI avec une sortie JSON Schema stricte.

## Rôle
Le modèle classe et propose une décision. Le code garde le dernier mot.

Sortie :
- task_type
- agent
- priority
- action_mode
- reason
- next_actions

## Défense en profondeur
Même si le modèle demande `auto`, le garde local peut imposer `draft` ou `approval_required`.

Toujours soumis à approbation :
- dépense
- remboursement
- suppression de compte
- suppression de données client
- engagement juridique
- changement d'identifiants

Une décision urgente n'est jamais exécutée automatiquement dans cette V1.

## Secrets
La clé API OpenAI doit vivre dans un fichier privé LWS hors htdocs. Le fichier exemple du dépôt ne contient aucune clé.

## Coût
Le routage par défaut utilise un modèle Luna à faible coût. Les escalades vers un modèle plus puissant seront explicites et limitées.
