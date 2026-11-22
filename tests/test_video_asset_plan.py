"""Controle hors reseau du plan de medias et des verrous de publication."""
import unittest
from src.video_asset_plan import create_plan


class AssetPlanTests(unittest.TestCase):
    def test_scene_count_matches_episode(self):
        from src.youtube_weekly import build
        for week in (1, 2, 3):
            self.assertEqual(len(create_plan(week)["shots"]), len(build(week)["scenes"]))

    def test_no_unverified_media_is_approved(self):
        for week in (1, 2, 3):
            plan = create_plan(week)
            self.assertFalse(plan["release_gate"]["publish_allowed"])
            self.assertTrue(all(not shot["technical_accuracy_checked"] for shot in plan["shots"]))
            self.assertTrue(all(not shot["usage_rights_checked"] for shot in plan["shots"]))

    def test_unknown_week_is_rejected(self):
        with self.assertRaises(ValueError):
            create_plan(99)


if __name__ == "__main__":
    unittest.main()
