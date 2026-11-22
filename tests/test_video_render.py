"""Tests de validation du moteur video sans FFmpeg ni publication."""
import tempfile
import unittest
from pathlib import Path
from src.video_render import validate_manifest


class VideoRenderTests(unittest.TestCase):
    def test_refuses_empty_clips(self):
        with self.assertRaises(ValueError):
            validate_manifest({"clips": [], "technical_review_approved": True, "rights_approved": True}, Path("."))

    def test_refuses_missing_technical_review(self):
        with tempfile.TemporaryDirectory() as root:
            p = Path(root) / "clip.mp4"
            p.write_bytes(b"placeholder")
            with self.assertRaisesRegex(ValueError, "Validation technique"):
                validate_manifest({"clips": [{"path": "clip.mp4"}], "rights_approved": True}, Path(root))

    def test_refuses_missing_rights(self):
        with tempfile.TemporaryDirectory() as root:
            p = Path(root) / "clip.mp4"
            p.write_bytes(b"placeholder")
            with self.assertRaisesRegex(ValueError, "Droits"):
                validate_manifest({"clips": [{"path": "clip.mp4"}], "technical_review_approved": True}, Path(root))

    def test_accepts_verified_paths_without_rendering(self):
        with tempfile.TemporaryDirectory() as root:
            p = Path(root) / "clip.mp4"
            p.write_bytes(b"placeholder")
            clips, voice = validate_manifest({
                "clips": [{"path": "clip.mp4"}],
                "technical_review_approved": True,
                "rights_approved": True,
            }, Path(root))
            self.assertEqual(clips, [p.resolve()])
            self.assertIsNone(voice)


if __name__ == "__main__":
    unittest.main()
