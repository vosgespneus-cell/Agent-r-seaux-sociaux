# Gardiennage — 26 octobre 2026

Décision Adam : 30 € TTC par paire pour une saison de six mois.

Appliqué sur LWS : les deux mentions de durée dans htdocs/agents.vosgespneus.com/pneus-essai.php et le titre de ligne dans home/vp_pneus_review/shopify_bridge.php. Les tarifs et le traitement des commandes restent inchangés. Sauvegardes privées : home/vp_pneus_review/page.before-storage-20261026.php et shopify_bridge.php.before-storage-20261026. Intervention sous les verrous intake-test.lock et shopify-bridge.lock ; syntaxe des deux fichiers préparés vérifiée avant remplacement.

Contrôles exécutés après changement : bridge 7 cas, garde-fou paiement 17 cas, matrice tarifaire 108 cas, comparaison des anciens enregistrements 12 lignes / 6 sélections inchangées. Aucun appel de paiement ni envoi d'email pendant ces contrôles. Le dernier journal automatique consulté indiquait 5 paniers inchangés, 0 création, 0 erreur et 0 facture envoyée ; ce journal ne valide pas la mise à jour des brouillons après changement de libellé.

Le nouveau libellé s'applique aux futurs paniers. Les brouillons expirés ne sont pas modifiés par le bridge ; #D6 n'a pas été modifié lors de cette intervention. Les confirmations après paiement et le parcours public fournisseur restent à valider. Les avertissements opcache restent non corrigés. Le bridge privé omet explicitement email et customerId dans son entrée et son autotest exige cette omission : aucune association client n'a été ajoutée.
