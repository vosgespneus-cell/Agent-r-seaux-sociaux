"""Deterministic routing for normalized VOSGES PNEUS events.

No provider call is made here. The router only chooses the responsible agent
and safe next action so it can be tested without credentials.
"""

ROUTES = {
    "appointment": ("planning-agent", "create_appointment"),
    "appointment_request": ("planning-agent", "create_appointment"),
    "call": ("phone-agent", "create_task"),
    "order": ("commerce-agent", "customer_update"),
    "shopify_event": ("commerce-agent", "create_task"),
    "lead": ("sales-agent", "follow_up"),
    "product_intake": ("product-agent", "create_task"),
    "part_photo": ("product-agent", "create_task"),
    "product_reference": ("product-agent", "create_task"),
    "stock_event": ("shopify-agent", "prepare_stock_update"),
    "shopify_draft_task": ("shopify-agent", "prepare_product_draft"),
    "campaign_request": ("marketing-agent", "create_task"),
    "video_asset": ("marketing-agent", "create_task"),
    "publication_task": ("social-publisher-agent", "publish"),
    "heartbeat": ("monitor-agent", "create_task"),
    "agent_result": ("monitor-agent", "create_task"),
    "runtime_error": ("monitor-agent", "alert"),
    "alert": ("supervisor", "alert"),
    "task": ("supervisor", "create_task"),
}


def route_event(event: dict, connector_states: dict | None = None) -> dict:
    """Return routing information without executing an external action."""
    event_type = event.get("type")
    source = event.get("source")

    if event_type == "message":
        if source == "whatsapp":
            agent, action = "whatsapp-agent", "reply"
        else:
            agent, action = "customer-service-agent", "reply"
    else:
        agent, action = ROUTES.get(event_type, ("supervisor", "create_task"))

    requires_human = False
    execution_mode = "draft"

    if action == "publish":
        channel = event.get("payload", {}).get("channel")
        state = (connector_states or {}).get(channel, {})
        if not state.get("live_actions", False):
            execution_mode = "draft"
            requires_human = True
        else:
            execution_mode = "live"

    if source == "whatsapp" and agent == "whatsapp-agent":
        state = (connector_states or {}).get("whatsapp", {})
        if not state.get("live_actions", False):
            execution_mode = "draft"

    return {
        "agent": agent,
        "action": action,
        "requires_human": requires_human,
        "execution_mode": execution_mode,
    }
