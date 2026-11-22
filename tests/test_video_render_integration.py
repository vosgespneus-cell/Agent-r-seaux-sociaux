"""Test d'integration du montage FFmpeg avec plans de TEST synthetiques.

Usage: python -m tests.test_video_render_integration
Cree des motifs animes FFmpeg, pas des gestes mecaniques; aucune publication.
"""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from src.video_render import render


@unittest.skipUnless(shutil.which("ffmpeg") and shutil.which("ffprobe"), "FFmpeg/ffprobe absents")
class VideoRenderIntegrationTests(unittest.TestCase):
    def test_two_moving_clips_and_voice(self):
        with tempfile.TemporaryDirectory(prefix="vp_render_test_") as root:
            folder = Path(root)
            for index in (1, 2):
                subprocess.run([
                    "ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                    "-f", "lavfi", "-i", f"testsrc2=size=320x180:rate=25:duration=1",
                    "-an", "-c:v", "mpeg4", str(folder / f"clip{index}.mp4"),
                ], check=True)
            subprocess.run([
                "ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                "-f", "lavfi", "-i", "sine=frequency=440:duration=3",
                "-c:a", "pcm_s16le", str(folder / "narration.wav")
            ], check=True)
            manifest = {
                "clips": [{"path": "clip1.mp4"}, {"path": "clip2.mp4"}],
                "voice": "narration.wav",
                "technical_review_approved": True,
                "rights_approved": True
            }
            manifest_path = folder / "production.json"
            manifest_path.write_text(json.dumps(manifest), encoding="utf-8")
            output = render(manifest_path, folder / "episode.mp4")
            self.assertTrue(output.is_file())
            self.assertGreater(output.stat().st_size, 1000)
            result = subprocess.run([
                "ffprobe", "-v", "error", "-show_entries",
                "stream=codec_type,width,height", "-of", "json", str(output)
            ], capture_output=True, text=True, check=True)
            streams = json.loads(result.stdout)["streams"]
            self.assertTrue(any(s.get("codec_type") == "video" and s.get("width") == 1920 and s.get("height") == 1080 for s in streams))
            self.assertTrue(any(s.get("codec_type") == "audio" for s in streams))


if __name__ == "__main__":
    unittest.main()
