"""Tests de l'orchestration Piper + FFmpeg, sans outil externe ni publication."""
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from src.video_production import produce


class VideoProductionTests(unittest.TestCase):
    def test_requires_approved_media_before_voice(self):
        with tempfile.TemporaryDirectory() as root:
            folder = Path(root)
            manifest = folder / "production.json"
            manifest.write_text(json.dumps({"clips": [], "narration_text": "narration.txt"}), encoding="utf-8")
            with patch("src.video_production.synthesize") as synth:
                with self.assertRaises(ValueError):
                    produce(manifest, folder / "voice.onnx", folder / "episode.mp4")
                synth.assert_not_called()

    def test_wires_voice_to_renderer_without_touching_original(self):
        with tempfile.TemporaryDirectory() as root:
            folder = Path(root)
            (folder / "scene.mp4").write_bytes(b"fake-video")
            (folder / "narration.txt").write_text("Bonjour", encoding="utf-8")
            manifest = folder / "production.json"
            original = {
                "clips": [{"path": "scene.mp4"}],
                "narration_text": "narration.txt",
                "technical_review_approved": True,
                "rights_approved": True,
            }
            manifest.write_text(json.dumps(original), encoding="utf-8")
            output = folder / "episode.mp4"

            def fake_voice(_text, _model, audio):
                Path(audio).write_bytes(b"fake-wav")
                return audio

            def fake_render(generated_manifest, result):
                generated = json.loads(Path(generated_manifest).read_text(encoding="utf-8"))
                self.assertEqual(generated["voice"], "narration_piper.wav")
                self.assertEqual(generated["clips"], original["clips"])
                self.assertEqual(Path(result), output.resolve())
                return output

            with patch("src.video_production.synthesize", side_effect=fake_voice), patch(
                "src.video_production.render", side_effect=fake_render
            ):
                self.assertEqual(produce(manifest, folder / "voice.onnx", output), output)
            self.assertEqual(json.loads(manifest.read_text(encoding="utf-8")), original)
            self.assertFalse((folder / "production_with_voice.generated.json").exists())


if __name__ == "__main__":
    unittest.main()
