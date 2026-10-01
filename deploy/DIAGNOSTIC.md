# Diagnostic avant activation

Le diagnostic doit produire PASS / WARN / FAIL sans demander au client de comprendre la technique.

Contrôles:
1. configuration client lisible
2. secrets absents du dépôt
3. accès email
4. accès agenda
5. accès téléphone si activé
6. accès WhatsApp si activé
7. accès e-commerce si activé
8. écriture du journal
9. file d'attente disponible
10. heartbeat superviseur
11. test événement fictif de bout en bout
12. test anti-doublon
13. test retry
14. test escalade humaine

Activation uniquement si les contrôles critiques sont PASS.
