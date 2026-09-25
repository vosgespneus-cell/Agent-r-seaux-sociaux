from typing import Any, Literal
from pydantic import BaseModel, Field

class ContentRequest(BaseModel):
    type: str
    title: str
    facts: dict[str, Any] = Field(default_factory=dict)
    assets: list[str] = Field(default_factory=list)
    channels: list[str] = Field(default_factory=list)
    cta: str | None = None
    notes: str | None = None

class ChannelPost(BaseModel):
    channel: str
    text: str
    status: Literal["draft", "ready", "blocked"] = "draft"
    missing_fields: list[str] = Field(default_factory=list)
