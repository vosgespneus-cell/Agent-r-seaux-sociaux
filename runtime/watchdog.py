"""Runtime watchdog: turn technical health into operator alerts."""

def inspect_health(health, thresholds=None):
    thresholds = thresholds or {"queue_depth": 25, "waiting_human": 10, "failed": 1}
    alerts = []

    if health.get("status") == "down":
        alerts.append({"severity":"urgent","code":"runtime_down"})
    elif health.get("status") == "degraded":
        alerts.append({"severity":"high","code":"runtime_degraded"})

    for key in ("queue_depth","waiting_human","failed"):
        value = int(health.get(key, 0) or 0)
        if value > thresholds[key]:
            alerts.append({
                "severity":"high" if key != "waiting_human" else "normal",
                "code":key + "_threshold",
                "value":value,
                "threshold":thresholds[key],
            })

    for name, connector in (health.get("connectors") or {}).items():
        status = connector.get("status") if isinstance(connector, dict) else connector
        if status in ("down","degraded"):
            alerts.append({
                "severity":"high" if status == "down" else "normal",
                "code":"connector_" + status,
                "connector":name,
            })

    return alerts

def needs_attention(alerts):
    return bool(alerts)
