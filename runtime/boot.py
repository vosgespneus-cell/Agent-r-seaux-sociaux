"""Protected boot decision for an autonomous installation."""
from installer.diagnostic import readiness

def boot_mode(checks):
    report=readiness(checks)
    if not report["ready"]:
        return {"mode":"protected","real_actions":False,"report":report}
    return {"mode":"dry_run","real_actions":False,"report":report}

def may_enable_real_actions(boot, explicit_activation=False):
    return bool(
        boot.get("report",{}).get("ready")
        and boot.get("mode") in ("dry_run","active")
        and explicit_activation
    )
