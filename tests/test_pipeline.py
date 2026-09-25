import json
import tempfile
import unittest
from pathlib import Path

from src.main import prepare


class PipelineTests(unittest.TestCase):
    def run_case(self, payload):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "request.json"
            path.write_text(json.dumps(payload), encoding="utf-8")
            return prepare(str(path))

    def test_example_is_blocked(self):
        result = prepare("data/content.example.json")
        self.assertEqual(result["mode"], "blocked")
        self.assertFalse(result["publication_allowed"])
        self.assertIn("price", result["missing_fields"])

    def test_verified_piece_produces_factual_drafts(self):
        result = self.run_case({
            "type": "piece_auto", "title": "Phare d'occasion",
            "facts": {"price": "45 €", "reference": "ABC123", "condition": "occasion",
                      "availability": "en stock", "compatibility": "modèle X",
                      "compatibility_verified": True},
            "channels": ["facebook", "instagram"], "cta": "Contactez VOSGES PNEUS",
        })
        self.assertEqual(result["mode"], "ready_for_review")
        self.assertEqual(len(result["posts"]), 2)
        self.assertIn("45 €", result["posts"][0]["text"])
        self.assertFalse(result["publication_allowed"])

    def test_unverified_compatibility_and_marketplace_blocked(self):
        result = self.run_case({
            "type": "piece_auto", "title": "Phare", "facts": {
                "price": "45 €", "reference": "ABC", "condition": "occasion",
                "availability": "en stock", "compatibility": "Citroën C5"},
            "channels": ["marketplace"],
        })
        self.assertIn("compatibility_verified", result["missing_fields"])
        self.assertIn("channel: marketplace", result["missing_fields"])
        self.assertNotIn("Citroën C5", result["posts"][0]["text"])

    def test_video_needs_asset(self):
        result = self.run_case({"type": "video", "title": "Test moteur",
                                "facts": {"description": "Essai de démarrage"},
                                "channels": ["tiktok"]})
        self.assertIn("assets", result["missing_fields"])


if __name__ == "__main__":
    unittest.main()
