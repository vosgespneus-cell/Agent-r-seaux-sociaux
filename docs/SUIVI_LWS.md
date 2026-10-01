# Suivi privé des agents LWS

Le dépôt conserve le code. LWS exécute les agents et conserve leurs états privés.

## Installation

Installer `vp_run.php` et `vp_monitor.php` dans le même répertoire privé que les
agents et leurs configurations, hors de `htdocs`, avec des permissions `0600`.
Conserver une copie exacte de la crontab avant tout changement. Remplacer seulement
l'appel du fichier agent par `vp_run.php mail`, `jotform`, `worker`, `ai` ou
`calendar`, sans changer les horaires, enchaînements ni redirections existants.
Ajouter un passage de `vp_monitor.php --save` toutes les cinq minutes.

Sur cet hébergement, la console et cron peuvent charger des versions et des
configurations PHP différentes. Le lecteur IMAP exige l'extension IMAP ; Jotform
et l'analyse IA exigent cURL ; le suivi exige PDO MySQL. Utiliser explicitement le
binaire PHP 8.3 et son fichier `php.ini`, le répertoire des fichiers `.ini` via
`PHP_INI_SCAN_DIR`, et le répertoire des extensions. Vérifier ces chemins dans
l'environnement réel de cron : un succès dans la console ne suffit pas.
Le runner transmet au processus enfant le binaire, le `php.ini` et le répertoire
des extensions du processus parent, et laisse l'environnement de scan hérité.
Il conserve dans le heartbeat la version, le chemin du `php.ini` et la présence
IMAP/cURL et de `proc_open` pour diagnostiquer un écart. Un échec de lancement
conserve uniquement la classe de l'erreur, jamais son message.
Les sorties utilisent `php://stdout` et `php://stderr` pour rester compatibles
avec les contextes CLI dans lesquels les constantes STDOUT/STDERR sont absentes. Aucun contenu de configuration n'est copié.

Le runner accepte une liste fixe de programmes, empêche deux passages simultanés
du même agent et conserve uniquement les heures, le code de sortie et le nombre
d'échecs observés depuis son installation. Il ne conserve pas la sortie des agents
dans le suivi ; elle suit les redirections déjà présentes dans la crontab.

## Vérification

```bash
php -l vp_run.php
php -l vp_monitor.php
php vp_run.php --selftest
php vp_monitor.php --section agents
php vp_monitor.php --section mail
php vp_monitor.php --section ai
php vp_monitor.php --save
```

Le self-test lance seulement des processus de test sans appeler les agents réels.
Il vérifie réussite, échec, état trop ancien, absence de mesure, concurrence et
permissions. `vp_monitor.php` lit uniquement les compteurs des tables existantes ;
il ne lit pas les corps de mail, noms ou coordonnées clients.

`--save` remplace atomiquement `vp_monitor.json` et `vp_monitor.html`, permissions
`0600`, dans le répertoire privé. Aucun endpoint public de consultation n'est
installé. Un HTML téléchargé est un instantané : il ne se met pas à jour tout seul.

## Interprétation

| État | Signification |
| --- | --- |
| `unknown` | Aucun passage encore enregistré |
| `running` | Passage démarré, résultat encore attendu |
| `ok` | Dernier processus terminé avec le code zéro |
| `error` | Dernier processus terminé en échec |
| `stale` | Dernière mesure antérieure à quinze minutes |

Un processus terminé ne prouve pas à lui seul la réussite métier. Certains agents
peuvent sortir sans travail quand ils sont désactivés ou occupés. Une préparation
de rendez-vous réussie ne prouve pas sa réception dans Google Agenda : vérifier le
pont Google et l'événement réel. La limite IA mesurée est un nombre de demandes,
pas un plafond monétaire ni le solde OpenAI.

Les demandes invalides et rendez-vous à vérifier restent visibles dans les tables
privées. Les alertes sont des états locaux ; aucune notification email, WhatsApp
ou SMS n'est envoyée par ces modules. Un compte rendu ne doit jamais annoncer un
canal actif uniquement parce que son code est installé.

## Retour arrière

Restaurer la copie précédente de la crontab pour retrouver les appels directs.
Les tables métier, rendez-vous, configurations et secrets ne sont pas modifiés par
ce retour arrière. Conserver les snapshots privés si un diagnostic est nécessaire.
