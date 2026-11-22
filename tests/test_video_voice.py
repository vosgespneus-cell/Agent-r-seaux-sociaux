"""Tests de l'adaptateur vocal Piper, sans lancer de synthese."""
import tempfile
import unittest
from pathlib import Path
from src.video_voice import voice_command


class VoiceTests(unittest.TestCase):
    def test_builds_offline_command(self):
        with tempfile.TemporaryDirectory() as folder:
            base = Path(folder)
            (base / "narration.txt").write_text("Bonjour, voici le filtre.", encoding="utf-8")
            (base / "fr_FR.onnx").write_bytes(b"test")
            command, source, output = voice_command(
                base / "narration.txt", base / "fr_FR.onnx",
                base / "narration.wav", executable="piper"
            )
            self.assertEqual(command[0], "piper")
            self.assertIn("--model", command)
            self.assertEqual(source.name, "narration.txt")
            self.assertEqual(output.suffix, ".wav")

    def test_refuses_empty_script(self):
        with tempfile.TemporaryDirectory() as folder:
            base = Path(folder)
            (base / "narration.txt").write_text(" ", encoding="utf-8")
            (base / "fr_FR.onnx").write_bytes(b"test")
            with self.assertRaises(ValueError):
                voice_command(base / "narration.txt", base / "fr_FR.onnx",
                              base / "narration.wav", executable="piper")

    def test_refuses_missing_model(self):
        with tempfile.TemporaryDirectory() as folder:
            base = Path(folder)
            (base / "narration.txt").write_text("Bonjour", encoding="utf-8")
            with self.assertRaises(ValueError):
                voice_command(base / "narration.txt", base / "missing.onnx",
                              base / "narration.wav", executable="piper")


if __name__ == "__main__":
    unittest.main()
