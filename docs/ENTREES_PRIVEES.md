# Entrées réelles et journal privé

Le récepteur reçoit des événements signés sur `POST /events` et conserve le corps
intégral dans `private/events.sqlite3`, fichier ignoré par Git et limité aux droits
du compte local. Il répond par un identifiant opaque. Aucune tâche client ni
réponse du journal ne doit être commise ou affichée dans un journal GitHub Actions.

## Démarrage local

Générer un secret aléatoire de 32 octets minimum et le placer dans
`VP_WEBHOOK_SECRET` sur la machine qui exécute le service, puis lancer :

```bash
python -m src.intake --host 127.0.0.1 --port 8765
python -m src.intake --summary
```

La commande `--summary` produit une vue sans coordonnées ni messages ; elle est
destinée à une consultation privée et ne prouve aucune réponse au client.
Le serveur écoute uniquement sur la machine locale par défaut. Pour Make ou
WhatsApp, il faut un hôte HTTPS public avec un relais qui signe les octets exacts
du JSON envoyé, puis transmet `X-VP-Signature: sha256=<HMAC-SHA256 hex>`.
Exemple de corps :

```json
{"event_id":"make-12345","source":"make","kind":"message","message":"..."}
```

Sources autorisées : `make`, `contact`, `telephone`, `whatsapp`, `atelier`.
Types : `message`, `appel`, `rendez_vous`, `produit`, `media`.
`event_id` doit être stable pour chaque événement : sa répétition renvoie la même
tâche sans doublon. Les détails du client restent uniquement dans le journal
privé. Ne pas exposer directement le serveur HTTP à Internet sans terminaison
TLS, contrôle d'accès et gestion opérationnelle des sauvegardes privées.

## État des connexions

Le récepteur local et le journal sont prêts. Aucune source externe ne pointe
encore vers lui. Le planning et Shopify n'ont pas encore de lecture ni d'écriture.
Leur raccordement utilisera les accès officiels et un journal de résultat privé.
