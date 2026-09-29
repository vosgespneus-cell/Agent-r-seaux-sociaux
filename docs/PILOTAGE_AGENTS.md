# Pilotage commun VOSGES PNEUS

## État au 29 septembre 2026

Le dépôt prépare des brouillons sociaux. Sur LWS, le récepteur signé `/events`, le journal MySQL et le worker sont opérationnels. Jotform alimente déjà le journal et une tâche accueil. Un appel synthétique signé a validé le routage vers une tâche téléphone (202, doublon 200, signature erronée 401). Aucun opérateur téléphonique n'envoie encore de vrais appels ; le calendrier, Shopify et les réseaux ne sont pas raccordés à ce worker. Le contrôle privé `vp_status.php` exclut les essais synthétiques de ses totaux opérationnels.

## Chaîne de travail

1. Une source autorisée crée une tâche identifiée avec titre, origine et agent responsable
2. L'agent métier prépare une décision ou un brouillon avec ses éléments vérifiables
3. La supervision vérifie les informations, les blocages et les preuves de résultat
4. Le connecteur autorisé agit sur le service externe seulement lorsque l'accès est prêt
5. Le résultat revient dans un journal privé ; le tableau public ne contient que des références non personnelles

## Agents prévus

| Agent | Travail | Connexion nécessaire |
| --- | --- | --- |
| accueil | qualifier les messages et demandes | messagerie et WhatsApp |
| telephone | noter les appels, préparer rappels | Keo/Keyyo ou autre accès téléphonie |
| planning | proposer et confirmer les créneaux | agenda |
| produits | fournisseurs, fiches pneus et pièces | Shopify, flux fournisseurs |
| stock | cohérence des quantités et références | inventaire |
| marketing | recherche d'offres et choix des sujets | indicateurs autorisés |
| communication | brouillons, médias, publications | Meta, TikTok, YouTube |
| gestion | suivi des commandes et indicateurs | Shopify, comptabilité autorisée |
| supervision | vérifier blocages, preuves, erreurs | registre et journaux |

Le dépôt étant public, **aucune donnée client ni secret** n'y est enregistré. Les vidéos lourdes, les appels, les messages et les données personnelles restent dans des services privés autorisés. Les clés et jetons de connexion vont dans les secrets du service d'exécution, jamais dans un commit.

## Règles de statut

`nouveau` → `en_cours` → `a_verifier` → `termine`, avec `bloque` si une dépendance manque. Pour une publication, une fiche Shopify, un rappel ou un rendez-vous, le statut `termine` exige une preuve de résultat adaptée. Le superviseur actuel contrôle le schéma et ces preuves ; il ne certifie pas que le lien externe fonctionne.

## Priorités d'intégration

1. Registre et contrôle en lecture seule
2. Réception de sources et journal privé des événements
3. Planning et demandes clients avec connexion officielle
4. Shopify et stock avec garde-fous factuels
5. Publications canal par canal avec vérification des liens
6. Téléphonie en temps réel par webhook ou service adapté, distinct des tâches GitHub programmées

Les tâches planifiées GitHub ne sont pas garanties à la seconde et un appel entrant exige un service permanent. Aucun coût externe ou abonnement nouveau n'est engagé par ce socle.

## Exécuter le contrôle

`python -m src.supervisor data/tasks.example.json`
