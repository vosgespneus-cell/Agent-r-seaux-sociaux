# Boucle de vie des tâches

Une tâche n'est plus considérée terminée simplement parce qu'un agent l'a routée.

Pour les actions externes, la séquence obligatoire devient :

`nouveau -> en_cours -> action running -> appel externe -> relecture -> preuve vérifiée -> action done -> tâche termine`

Si l'appel ou la vérification échoue :
- erreur temporaire : action `retry`, tâche non terminée
- erreur permanente : action `failed`, copie minimale dans `vp_dead_letters`, tâche `bloque`

La file morte ne contient pas le message client ni le secret d'API. Elle conserve uniquement les identifiants techniques et la raison courte nécessaire au diagnostic.
