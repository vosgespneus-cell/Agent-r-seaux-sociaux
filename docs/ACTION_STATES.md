# États des actions

- pending : prête à être prise
- running : actuellement verrouillée par un worker
- waiting : attend son pipeline spécialisé normal
- retry : erreur temporaire, nouvel essai programmé
- done : terminée avec preuve
- failed : échec permanent
- blocked : intervention/politique nécessaire

`waiting` n'est pas une anomalie. Téléphone, Planning et Produits peuvent y rester pendant que leur pipeline spécialisé travaille. Les alertes doivent viser `failed`, `blocked`, les retries trop anciens et les preuves manquantes.
