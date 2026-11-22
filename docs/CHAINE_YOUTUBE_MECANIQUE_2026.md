# VOSGES PNEUS — Chaîne YouTube mécanique IA
Date de décision : 22 novembre 2026
Statut : ligne éditoriale approuvée par Adam ; automatisation et publication vidéo non encore validées.

## Promesse de la chaîne
Une explication mécanique claire et utile chaque semaine, en français, pour automobilistes curieux et bricoleurs avertis. Éducation avant promotion. Identité : VOSGES PNEUS — La mécanique expliquée simplement.

## Cadence
- 1 tutoriel YouTube de 4 à 7 minutes par semaine, quand la qualité et la vérification technique le permettent.
- 2 à 3 extraits verticaux de 20 à 45 secondes tirés du tutoriel.
- Description structurée : modèle concerné, niveau de difficulté, outils, limites, avertissements, chapitres et question à la communauté.
- Appel à l'action sobre : s'abonner, commenter la prochaine panne à expliquer, contacter VOSGES PNEUS pour pneus et pièces.

## Calendrier des six premiers épisodes
1. Filtre à huile : comment identifier le bon filtre et comprendre la vidange (vérifier couple, joint, quantité et procédure par véhicule).
2. Rotule à la presse : reconnaître la pièce et comprendre les règles de sécurité (ne jamais montrer de montage de presse improvisé ; contrôle par professionnel).
3. Disques et plaquettes : signes d'usure et quand consulter un professionnel.
4. Perte de puissance moteur : causes possibles, sans diagnostic définitif à distance.
5. Batterie ou alternateur : indices et vérifications simples sans manipulations électriques dangereuses.
6. Pneus : usure intérieure, extérieure, centrale ; pression, géométrie, suspension.

## Production automatisée cible, à implémenter et tester
1. Planification hebdomadaire locale Windows Task Scheduler (sans Make).
2. Sujet depuis ce calendrier et vérification des informations techniques sur des sources fiables.
3. Scénario français ; découpage en scènes ; narration TTS locale libre de droits.
4. Illustrations/schémas pédagogiques exacts ; préférer vraies photos autorisées et graphiques explicatifs à des gestes IA inventés.
5. FFmpeg : montage, sous-titres, générique et export MP4 16:9 + extraits 9:16.
6. Contrôles bloquants : lisibilité, audio, droits d'usage, cohérence technique, mentions IA et risques.
7. Publier via connecteurs autorisés et journaliser URL/statut. Ne jamais déclarer publiée une vidéo sans preuve d'upload.
8. Mesurer vues, abonnés, commentaires et demandes clients ; choisir les futurs thèmes d'après les questions reçues.

## Contraintes non négociables
- Aucun nouvel abonnement ; ne pas utiliser Make ou crédits IA payants.
- Réutiliser le dépôt et les scripts existants ; auditer les outils locaux avant d'annoncer l'autonomie.
- Aucun secret, donnée client, clé API ou identifiant dans GitHub.
- Les procédures varient selon les véhicules : pas de couples de serrage ou méthodes universelles inventés.
- Presse, freinage, levage, suspension : ne publier que des gestes sûrs, vérifiés ; ne pas laisser un générateur visuel inventer les opérations.
- Respecter les règles des plateformes sur les contenus synthétiques et la monétisation ; aucune garantie de revenu.
- Ne pas détourner Sam de sa mission urgente TRANSMALIN sans coordination.

## Premier épisode : dossier de préparation
**Titre :** Filtre à huile : 5 erreurs à éviter avant de faire sa vidange
**Accroche :** « Un filtre à huile paraît simple à remplacer. Pourtant, un mauvais joint ou une mauvaise référence peut coûter cher au moteur. »
**Plan :** (00:00) pourquoi le filtre compte ; (00:35) vérifier modèle et motorisation ; (01:20) préparer l'espace et respecter la sécurité ; (02:05) distinguer filtre vissé et cartouche ; (03:00) joint, portée et consignes constructeur ; (04:00) huile et élimination réglementaire ; (05:00) contrôle des fuites, niveau et message final.
**CTA :** « Quel entretien voulez-vous qu'on explique la semaine prochaine ? »
**Important :** ceci est un plan éditorial, pas une démonstration technique validée ni une vidéo produite.
