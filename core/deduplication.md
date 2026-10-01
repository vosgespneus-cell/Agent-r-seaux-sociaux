# Anti-doublon

Chaque entrée reçoit un event_id interne et conserve son identifiant source.

Clé de déduplication recommandée:
business + source + source_id.

Si source_id est absent:
empreinte temporelle + type + identifiant client + empreinte du contenu.

Avant toute action externe, vérifier qu'une action équivalente n'a pas déjà été marquée completed.

Les confirmations, publications, rendez-vous et réponses client doivent utiliser une clé d'idempotence afin qu'un retry ne provoque jamais une double action.
