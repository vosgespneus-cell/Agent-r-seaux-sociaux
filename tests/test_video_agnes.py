import unittest

from src.video_agnes import build_request


class VideoRequestTests(unittest.TestCase):
    def test_preview_payload_is_flash_720p(self):
        payload = build_request("Un pneu en atelier", 5)
        self.assertEqual(payload["model"], "agnes-video-2.5-flash")
        self.assertEqual(payload["mode"], "text")
        self.assertEqual(payload["size"], "720P")
        self.assertEqual(payload["aspect_ratio"], "9:16")

    def test_image_uses_keyframe_mode(self):
        payload = build_request("Rotation lente", 6, "https://example.com/pneu.jpg")
        self.assertEqual(payload["first_frame"], "https://example.com/pneu.jpg")
        self.assertEqual(payload["mode"], "keyframe")

    def test_invalid_duration_and_non_public_image_are_rejected(self):
        with self.assertRaises(ValueError):
            build_request("Scène", 13)
        with self.assertRaises(ValueError):
            build_request("Scène", 5, "file:///photo.jpg")


if __name__ == "__main__":
    unittest.main()
