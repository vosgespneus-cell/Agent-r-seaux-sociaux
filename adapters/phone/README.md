# Phone adapter — Keyyo SIP → Asterisk → VOSGES PNEUS agents

## Etat valide le 2 octobre 2026

La ligne VOSGES PNEUS a ete enregistree avec succes en SIP sur un softphone Windows et un appel entrant reel a atteint le PC. Cette validation prouve le chemin Keyyo → SIP → PC. MicroSIP reste un outil de secours et de diagnostic.

## Architecture cible

Client → Keyyo SIP → Asterisk local (WSL2/Ubuntu) → phone-agent → supervisor → calendrier / taches / transfert humain

Asterisk doit devenir le point d'entree du standard. Le phone-agent ne doit jamais recevoir directement les secrets SIP.

## Installation locale restante

1. Installer WSL2 et Ubuntu sur le PC Windows
2. Installer Asterisk dans Ubuntu
3. Stocker les identifiants SIP Keyyo uniquement dans la configuration locale protegee
4. Enregistrer Asterisk sur Keyyo
5. Tester un appel entrant vers une extension de test avant toute reponse IA
6. Ajouter le moteur voix et le phone-agent
7. Ajouter le transfert humain et le mode secours
8. Activer le demarrage automatique seulement apres validation des tests

## Regles d'exploitation

- Aucun mot de passe SIP, cle API, numero client ou enregistrement audio dans ce depot public
- Aucun engagement financier par l'agent vocal
- En cas de doute, demande humaine ou situation non prise en charge : transfert humain
- Si l'IA ou un service dependance est indisponible : fallback vers un humain ou une destination de secours, jamais une boucle d'appels
- MicroSIP peut rester installe pour diagnostiquer la ligne, mais ne doit pas partager simultanement le meme compte SIP avec Asterisk sans validation du comportement Keyyo

## Etat de mise en service

Le transport SIP jusqu'au PC est valide. Asterisk et l'agent vocal ne sont pas encore en production. `live_actions` doit rester a `false` jusqu'a un test de bout en bout concluant.
