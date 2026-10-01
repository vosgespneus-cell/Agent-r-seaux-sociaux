"""Privacy-safe readiness report for a client installation."""

CRITICAL = ("configuration","runtime","storage","supervisor")

def readiness(checks):
    failed = [name for name in CRITICAL if checks.get(name) != "pass"]
    warnings = [name for name, value in checks.items() if value == "warn"]
    return {
        "ready": not failed,
        "critical_failures": failed,
        "warnings": warnings,
        "checks": checks,
    }

def connector_summary(connector_health):
    return {
        name: result.get("status", "unknown")
        for name, result in connector_health.items()
    }
