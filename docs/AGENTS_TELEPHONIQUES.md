# Agents d'accueil téléphonique — préparation

État au 29 septembre 2026 : les deux numéros Keyyo sonnent, mais aucun agent vocal IA ne décroche encore. Le 03 72 73 98 33 (Transmalin) distribue au 03 72 73 98 34 (Vosges Pneus) ; le libellé Transmalin s'affiche sur le poste. Un test du 34 a abouti au répondeur enregistré Transmalin. Ne pas présenter le simple renommage des lignes ou le récepteur d'événements comme un agent vocal.

## Condition de mise en service

L'offre du 33 est actuellement « Numéro d'accueil » ; l'option « Mon Standard Intelligent » n'apparaît pas dans les types de profil. D'après la documentation Keyyo, cette offre n'est pas compatible sans migration et souscription de l'option IA. Le 34 est une ligne fixe personnelle, pas un second standard IA. Faire chiffrer la solution pour **deux parcours identifiés** avant tout engagement ; ne pas souscrire sans accord sur le coût. Autre architecture possible : renvoi des deux numéros vers un service vocal tiers avec infrastructure SIP et séparation stricte, après validation technique et financière. Une notification CTI HTTP ne transporte pas la voix et ne peut pas décrocher à la place de l'agent.

## Accueil Vosges Pneus — 03 72 73 98 34

Message initial proposé : « Bonjour, vous êtes chez Vosges Pneus à Thaon-les-Vosges. Je suis l'assistant d'accueil. Dites-moi ce dont vous avez besoin. »

- Pneus : demander la dimension complète inscrite sur le flanc, la saison souhaitée, la quantité et un moyen de rappel. Reformuler la dimension.
- Montage, équilibrage ou gardiennage : demander le véhicule, la dimension des pneus, le service et le créneau souhaité. Ne jamais confirmer un rendez-vous sans écriture et vérification dans l'agenda.
- Pièces : demander la référence ou les détails du véhicule, l'état recherché et un moyen de rappel. Ne pas annoncer un stock ou une compatibilité non vérifiés.
- Urgence ou réclamation : transmettre à Adam ou à une personne disponible selon un chemin de transfert testé ; sinon enregistrer une demande de rappel. Ne pas promettre une réponse immédiate sans contrôle.
- Ne mentionner ni missions Transmalin, ni chauffeurs, ni dossiers médicaux.
- À la fin : récapituler la demande, demander l'accord pour transmettre les coordonnées, puis créer une tâche privée Vosges Pneus avec origine « appel » et statut « nouveau ». Garder les données clients hors de GitHub.

## Accueil Transmalin — 03 72 73 98 33

Message initial proposé : « Bonjour, vous êtes chez Transmalin. Je suis l'assistant d'accueil. S'agit-il d'une mission de transport, d'un suivi de mission ou d'une autre demande ? »

- Mission : relever l'organisme demandeur, la nature générale, le lieu, la date et l'heure souhaitées, la contrainte de délai et le moyen de rappel. Répéter les lieux et horaires. Ne jamais accepter une mission ou promettre un délai sans validation de disponibilité.
- Suivi : demander une référence de mission et le contact professionnel. Ne pas divulguer l'état d'une mission à un tiers non identifié.
- Transport de santé : ne pas demander de diagnostic, de nom de patient ni de détail médical dans un accueil général. Si ces informations sont nécessaires au processus réel, définir un canal privé et des règles d'accès adaptées avant activation.
- Demande urgente : tenter le transfert vers la personne d'astreinte confirmée ; à défaut, signaler honnêtement que l'appel sera transmis. Ne jamais prétendre joindre les secours ou garantir une prise en charge.
- Ne mentionner ni pneus, ni stock ou prestations Vosges Pneus.
- Enregistrer les demandes dans un journal privé Transmalin distinct. Aucun événement Transmalin dans la base VOSGES PNEUS.

## Vérifications avant bascule

1. Confirmer le coût et la compatibilité de la solution vocale retenue pour chaque numéro
2. Tester un appel externe vers le 33 et le 34 : identité annoncée, compréhension, transfert, échec, fin d'appel
3. Vérifier que la destination et le journal identifient le numéro **composé par l'appelant**, y compris quand le 33 transite par le 34
4. Vérifier la création d'une tâche dans la bonne entreprise sans coordonnées dans les journaux techniques
5. Garder un chemin humain de secours tant que l'agent n'a pas répondu avec succès dans ces tests
6. Basculer les appels vers l'agent seulement après ces contrôles, puis tester la messagerie de secours propre à chaque activité
