import json
from pathlib import Path
from .models import ContentRequest
from .validator import can_auto_publish

def load_request(path: str) -> ContentRequest:
    return ContentRequest.model_validate_json(Path(path).read_text(encoding="utf-8"))

def prepare(path: str) -> dict:
    req = load_request(path)
    allowed, missing = can_auto_publish(req)
    return {
        "title": req.title,
        "channels": req.channels,
        "publication_allowed": allowed,
        "missing_fields": missing,
        "mode": "ready" if allowed else "draft"
    }

if __name__ == "__main__":
    import sys
    print(json.dumps(prepare(sys.argv[1]), ensure_ascii=False, indent=2))
