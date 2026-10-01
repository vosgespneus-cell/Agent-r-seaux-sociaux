"""Privacy-safe daily operational summary."""

def build(metrics, connector_health=None):
    connector_health = connector_health or {}
    return {
        "inputs": int(metrics.get("inputs", 0)),
        "actions_prepared": int(metrics.get("actions_prepared", 0)),
        "actions_proven": int(metrics.get("actions_proven", 0)),
        "waiting_human": int(metrics.get("waiting_human", 0)),
        "errors": int(metrics.get("errors", 0)),
        "connectors": {
            name: (value.get("status","unknown") if isinstance(value,dict) else value)
            for name, value in connector_health.items()
        },
    }

def operator_line(report):
    return (
        f"Entrees {report['inputs']} | "
        f"Executees avec preuve {report['actions_proven']} | "
        f"A verifier {report['waiting_human']} | "
        f"Erreurs {report['errors']}"
    )
