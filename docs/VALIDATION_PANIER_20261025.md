# Validation du panier — 25 octobre 2026

Le récapitulatif privé attendait une actualisation manuelle après l'enregistrement des options. Un ancien lien Shopify restait également visible après une modification non enregistrée.

Le correctif ajoute un contrôle de disponibilité du panier toutes les cinq secondes (arrêt après six minutes), lié à la session et au snapshot des options déjà contrôlé par la page. Il suspend cette attente et masque le lien dès qu'un champ est modifié. L'endpoint ne renvoie ni identité ni URL de paiement. Les autorisations et destinataires d'essai restent inchangés.

## Validation effectuée sur LWS

- Syntaxe PHP de la page de validation : correcte.
- 108 combinaisons de tarifs : correctes (13–16 pouces : 15 €, 17 pouces : 18 €, 18 pouces et plus : 22 €, valve 3 € et recyclage 2,50 € par pneu monté, gardiennage 30 € par paire).
- Auto-test existant du bridge : 7 cas réussis.
- Nouveau cas fictif indépendant : 4 pneus à 46,03 €, montage 60 €, valves 12 €, recyclage 10 €, 2 paires en gardiennage 60 € ; total Shopify 326,12 € TTC, cinq lignes visibles à l'écran de paiement.
- Modification du même cas : 2 pneus sans montage, 1 paire en gardiennage ; total 122,06 €. Retour à toutes les options : 326,12 €. Même lien de checkout, zéro création supplémentaire lors des mises à jour.
- Apparition automatique du lien après traitement, sans actualisation manuelle : observée.
- Lien masqué dès modification de quantité non enregistrée : observé.
- Comparaison à la sauvegarde antérieure : 12 enregistrements précédents, dont 6 sélections, strictement inchangés. Les cinq demandes clôturées n'ont pas été relancées.
- Aucun paiement, aucune facture envoyée et aucune commande fournisseur déclenchés.

Le correctif a été validé sur une page séparée puis activé sur le récapitulatif privé, avec sauvegarde préalable. La tâche existante traite les essais toutes les cinq minutes.

## Limite bloquante du parcours public

Le catalogue public utilise le module B2C Camodo/Pneus100 v9.9. Les réglages du compte consultés proposent l'affichage en iframe/lien et l'envoi des demandes de devis par courriel. Les domaines VOSGES PNEUS sont correctement autorisés. Aucun raccordement direct de la sélection au panier Shopify n'a été identifié dans ces réglages.

Le traitement LWS existant reste limité aux adresses de test autorisées et au marqueur de test. Ses données proviennent d'un devis reçu par email, et sa source n'est pas authentifiée par le parseur actuel. La vérification du stock fournisseur, des frais et des modalités de remise reste requise avant activation commerciale.

Le parcours public n'est donc pas validé de bout en bout. Aucun élargissement de diffusion ni activation commerciale n'a été réalisé. Les confirmations post-paiement n'ont pas été testées pendant cette intervention.

## Script

À placer dans le répertoire privé `home` de l'installation LWS existante. `--stage` prépare une page de validation ; `--activate` installe cette page après validation manuelle ; `--verify` contrôle les tarifs et compare les enregistrements à la sauvegarde `vp_pneus_review/review.before-20261025.sqlite`. Ne pas exécuter `--activate` sans valider préalablement la page séparée. Ce script ne contient aucun secret.
