# Déploiement LWS — pipeline Produits

Ne pas remplacer le worker actuel avant d'avoir installé les nouvelles tables.

Ordre sûr :
1. sauvegarde de la base
2. importer `actions.sql` si nécessaire
3. importer `product_intake.sql`
4. importer `product_drafts.sql`
5. importer `product_pipeline_v2.sql`
6. copier les nouveaux fichiers PHP privés hors `htdocs`
7. exécuter `preflight.php`
8. seulement si le résultat est `VP_PREFLIGHT_OK`, remplacer le worker
9. lancer manuellement le worker
10. vérifier health/recovery avant d'activer ou modifier les cron

Le preflight ne modifie aucune donnée. Si une table manque, il retourne son nom et le worker existant reste en place.
