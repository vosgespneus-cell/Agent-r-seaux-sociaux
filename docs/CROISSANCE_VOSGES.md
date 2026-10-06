# Pilotage commercial VOSGES PNEUS — 4 octobre 2026

## But
Transformer les moyens existants — atelier, mécanicien, salariés, espace de stockage, pièces et Shopify — en demandes traitées, rendez-vous réalisés et encaissements. Aucun abonnement ni budget publicitaire nouveau. Le revenu n'est jamais confondu avec la marge, un devis ou un rendez-vous.

## Trois circuits à développer en premier
1. Atelier : diagnostic mécanique annoncé à 40 € / une heure selon la consigne d'Adam, montage et équilibrage, puis devis réparation après diagnostic. Le calendrier donne la disponibilité, le mécanicien confirme la capacité réelle et l'approvisionnement. Une réparation ne se réalise pas nécessairement dans le créneau de diagnostic.
2. Pneus et gardiennage : proposer le gardiennage quand le client demande un changement saisonnier, avec son accord. Identifier le train, son état, sa position et la période contractuelle. Les anciens tarifs déclarés sont 30 € la paire / 60 € les quatre : durée et conditions à préciser avant offre publique. Montage : clarifier la frontière 16 pouces et la différence avec le tarif Allopneus affiché à 15 €.
3. Pièces disponibles : commencer par les pièces déjà démontées et identifiables. Aucun achat de stock supplémentaire. Photos réelles, référence, état, compatibilité documentée, quantité, prix et coût de préparation/expédition avant publication. Retirer ou bloquer la disponibilité dès la vente pour éviter une double vente.

## Circuit commun
Entrée téléphone / formulaire / mail -> demande privée identifiée -> qualification -> rendez-vous ou devis -> confirmation -> prestation / livraison -> paiement avec référence -> suivi.
Une demande ne doit pas disparaître après l'appel. Conserver son statut, son responsable et sa prochaine action. Une même demande sur plusieurs canaux doit être rapprochée sans supprimer arbitrairement les demandes distinctes.

## Organisation proposée
Adam : arbitrage des exceptions, prix et capacité réelle
Mécanicien : diagnostic, devis, validation des pièces et compatibilités
Salarié affecté par Adam : trois photos par pièce, étiquette, rangement et préparation
Accueil IA : qualification et prise de rendez-vous sur le planning autorisé
Produits/stock : fiches vérifiées et synchronisation des quantités
Communication : publication ciblée reliée à une offre réellement disponible
Gestion : demandes sans réponse, devis en attente et paiements
Supervision : défauts techniques, résultats et travail restant
Ne pas supposer que les salariés précédemment disponibles sont encore libres aujourd'hui.

## Cadence proposée
Chaque matin : connexions, demandes nouvelles, devis sans progression depuis 24 h, capacité du jour
Milieu de journée : vérifier rendez-vous, stock et demandes non traitées
Fin de journée : encaissements prouvés, prestations réalisées, ventes de pièces, temps passé et blocages
Lot initial : dix pièces existantes complètes plutôt que cent fiches incomplètes
Communication : montage/gardiennage et pièces prouvées ; les tutoriels réutilisent les vraies interventions. Ne pas prendre les vues pour des ventes.

## Mesures
Demandes par canal, délai de réponse, devis envoyés, rendez-vous confirmés puis réalisés, ventes payées, encaissements, marge après coûts connus, impayés et heures atelier consommées. Les indicateurs indisponibles restent inconnus, jamais zéro par défaut.
Exemple arithmétique uniquement : deux diagnostics à 40 € encaissés = 80 € ; deux gardiennages de quatre pneus à 60 € encaissés = 120 €. Total 200 € de recettes, sans prévision ni estimation de bénéfice. Aucun volume garanti.

## Module livré et limites
`src/revenue_pilot.py` lit un registre JSON privé et propose des tâches prioritaires compatibles avec les agents du superviseur. Les défauts et conflits de tarifs passent avant la promotion. Il ne contient aucun client et ne réalise aucun envoi externe.
Commande : `python -m src.revenue_pilot /chemin/prive/registre-commercial.json`
Champs : `leads` (id, status, updated_at avec fuseau), `products` (id, stock et preuves), `receipts` (id, amount_eur, status, payment_reference), `connectors` (id, stale/error), `tariff_conflicts`, `calendar_verified`, `workshop_capacity_verified`, `free_slots`.
Statuts demandes terminées : won/lost/cancelled ; new qualifie, autres ouverts depuis 24 h préparent le suivi. Pièces : reference_verified, condition_verified, price_verified, compatibility_verified, real_photos, live_url.
Le classement est une liste de travail, pas une estimation automatique de rentabilité. L'intégration des registres LWS, Shopify et téléphone reste à effectuer après observation de leurs formats réels. Ne pas installer un second standard téléphonique ni remplacer le routage Keyyo.
Les coordonnées, paiements et demandes réels restent sur les serveurs privés. Seul le code et les procédures vont dans GitHub. La collecte des données du jour, le déploiement permanent, les publications et les suivis clients ne sont pas activés par cette livraison.

## Ordre de mise en service
1. Lire le suivi privé LWS et contrôler les demandes réellement reçues
2. Raccorder le registre commercial au journal téléphone et aux entrées mail/formulaire, avec identifiant commun
3. Lire le stock Shopify, identifier dix pièces prêtes et supprimer les incohérences avant publication
4. Harmoniser les tarifs directs/partenaires et conditions de gardiennage
5. Brancher le pilotage sur le lancement existant et vérifier un cas complet jusqu'au paiement
6. Développer les offres et médias selon les demandes qui produisent réellement des recettes

Sources consultées : dépôt VOSGES PNEUS (registre des agents, supervision, règles d'action), site demande-pneus et gardiennage, fiche partenaire Allopneus ; informations d'Adam. Pas de promesse de résultat financier.
