"""Tests du generateur de sous-titres hors ligne."""
import unittest
from src.video_subtitles import make_srt, stamp


class SubtitleTests(unittest.TestCase):
    def test_timestamp(self):
        self.assertEqual(stamp(62.125), "00:01:02,125")

    def test_two_scenes(self):
        result = make_srt([
            {"duration_seconds": 2, "text": "Bonjour"},
            {"duration_seconds": 3.5, "text": "Filtre a huile"},
        ])
        self.assertIn("00:00:00,000 --> 00:00:02,000", result)
        self.assertIn("00:00:02,000 --> 00:00:05,500", result)
        self.assertIn("Filtre a huile", result)

    def test_empty_rejected(self):
        with self.assertRaises(ValueError):
            make_srt([])

    def test_invalid_duration_rejected(self):
        with self.assertRaises(ValueError):
            make_srt([{"duration_seconds": 0, "text": "Erreur"}])


if __name__ == "__main__":
    unittest.main()
