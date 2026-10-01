# Adapters

Les adapters traduisent les systèmes externes vers le langage interne.

Entrée:
- email
- calendar
- phone
- whatsapp
- shopify

Sortie:
- email sender
- calendar writer
- phone callback/task
- whatsapp sender
- shopify action

Règle: aucune logique métier spécifique au fournisseur dans le superviseur.

Chaque adapter doit exposer:
health_check()
read_events()
execute_action()
normalize_error()

Les secrets sont fournis à l'exécution par variables d'environnement ou gestionnaire de secrets.
