# Supervision silencieuse

Le système autonome ne doit pas demander une intervention humaine à chaque étape.

Le résumé privé `daily_summary.php` expose uniquement des compteurs :
- nouvelles tâches
- tâches bloquées
- actions en retry/failed
- entrées produits en attente d'information
- brouillons Shopify créés aujourd'hui
- brouillons créés sans preuve
- éléments de file morte

Aucun nom, téléphone, email, message, photo, clé ou contenu client n'est imprimé.

Principe : si tout fonctionne, la machine reste silencieuse. Elle remonte uniquement ce qui nécessite réellement une décision ou une correction.
