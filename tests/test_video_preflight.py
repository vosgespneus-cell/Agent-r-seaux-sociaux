"""Tests sans GPU, sans reseau et sans publication du diagnostic video."""
import unittest
from unittest.mock import patch
from src import video_preflight


class VideoPreflightTests(unittest.TestCase):
    @patch("src.video_preflight.shutil.which", return_value=None)
    def test_missing_tool_is_reported(self, _which):
        self.assertEqual(video_preflight.tool_status("ffmpeg")["available"], False)

    @patch("src.video_preflight.shutil.which", return_value=None)
    def test_no_nvidia_is_unknown_not_no_gpu(self, _which):
        result = video_preflight.graphics_status()
        self.assertEqual(result["gpu_details"], "non_verifies")

    @patch("src.video_preflight.graphics_status", return_value={"gpu_details": "non_verifies"})
    @patch("src.video_preflight.shutil.which", return_value=None)
    @patch.dict("os.environ", {"AGNES_API_KEY": "secret-for-test"}, clear=False)
    def test_secret_value_is_never_reported(self, _which, _graphics):
        result = video_preflight.audit()
        self.assertTrue(result["agnes_api_key_configured"])
        self.assertNotIn("secret-for-test", str(result))


if __name__ == "__main__":
    unittest.main()
