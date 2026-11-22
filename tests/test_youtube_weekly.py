"""Tests hors réseau du préparateur YouTube."""
import unittest
from src.youtube_weekly import build

class YoutubeWeeklyTests(unittest.TestCase):
    def test_three_episodes(self):
        for week in (1, 2, 3):
            with self.subTest(week=week):
                item = build(week)
                self.assertGreaterEqual(len(item["scenes"]), 5)
                self.assertEqual(len(item["shorts"]), 3)
                self.assertFalse(item["publication_allowed"])
                self.assertFalse(item["youtube_uploaded"])
                self.assertFalse(item["technical_review_approved"])
                self.assertFalse(item["visual_rights_approved"])

    def test_invalid_week_rejected(self):
        for week in (0, 4, -1):
            with self.assertRaises(ValueError):
                build(week)

    def test_safety_warning_present(self):
        for week in (1, 2, 3):
            item = build(week)
            self.assertTrue(item["scenes"][-1]["narration"])
            self.assertIn("sécurité", item["scenes"][-1]["visual"].lower())

if __name__ == "__main__":
    unittest.main()
