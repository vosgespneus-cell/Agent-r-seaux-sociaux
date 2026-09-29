# Dispatch IA

Le modèle ne lance jamais directement une intégration externe.

1. Le superviseur IA retourne une décision structurée
2. `SupervisorDecisionGuard` applique les règles non négociables
3. `AIDecisionDispatcher` traduit l'agent en action interne
4. La contrainte unique de `vp_actions` évite les doublons
5. Une décision `approval_required` devient `blocked` / tâche `a_verifier`
6. Les autres décisions entrent dans la file `pending`
7. L'executor et les adaptateurs prennent ensuite le relais

## Informations manquantes
`NeedsInformation` utilise une liste blanche de champs métier. Une clé, un mot de passe ou une donnée libre inconnue ne peut pas être transformé automatiquement en question au client.

## Principe
IA = décision proposée.  
Code = politique et contrôle.  
Adaptateur = action externe.  
Preuve = fin réelle de l'action.
