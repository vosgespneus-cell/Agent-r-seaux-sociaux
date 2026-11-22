"""Assembler de VRAIS plans video autorises avec FFmpeg, sans IA payante.

Usage:
 python -m src.video_render --manifest production.json --output episode.mp4
Manifeste:
 {"clips":[{"path":"media/plan1.mp4"},{"path":"media/plan2.mp4"}],
  "voice":"media/narration.wav"}
Refuse les fichiers absents et les plans non video. Ne publie jamais.
"""
import argparse
import json
from pathlib import Path
import shutil
import subprocess
import tempfile


def validate_manifest(data, base):
    clips = data.get("clips")
    if not isinstance(clips, list) or not clips:
        raise ValueError("Au moins un vrai plan video est obligatoire")
    if not data.get("technical_review_approved", False):
        raise ValueError("Validation technique obligatoire")
    if not data.get("rights_approved", False):
        raise ValueError("Droits des medias non valides")
    resolved = []
    for item in clips:
        p = (base / item["path"]).resolve()
        if not p.is_file() or p.suffix.lower() not in (".mp4", ".mov", ".mkv", ".webm"):
            raise ValueError(f"Plan video absent ou non pris en charge: {p}")
        resolved.append(p)
    voice = data.get("voice")
    audio = (base / voice).resolve() if voice else None
    if audio and (not audio.is_file() or audio.suffix.lower() not in (".wav", ".mp3", ".m4a")):
        raise ValueError("Narration audio absente ou invalide")
    return resolved, audio


def render(manifest, output):
    if not shutil.which("ffmpeg") or not shutil.which("ffprobe"):
        raise RuntimeError("FFmpeg et ffprobe doivent etre installes")
    manifest = Path(manifest).resolve()
    data = json.loads(manifest.read_text(encoding="utf-8"))
    clips, voice = validate_manifest(data, manifest.parent)
    output = Path(output).resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="vp_video_") as temp:
        temp = Path(temp)
        normalized = []
        for i, clip in enumerate(clips):
            dest = temp / f"scene_{i:03d}.mp4"
            subprocess.run([
                "ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                "-i", str(clip),
                "-vf", "scale=1920:1080:force_original_aspect_ratio=decrease,"
                       "pad=1920:1080:(ow-iw)/2:(oh-ih)/2,fps=25,format=yuv420p",
                "-an", "-c:v", "libx264", "-preset", "medium", "-crf", "20",
                str(dest)
            ], check=True)
            normalized.append(dest)
        # FFmpeg concat file: use generated paths in private temp directory only.
        concat = temp / "clips.txt"
        concat.write_text("".join("file '"+str(p).replace("'", "'\\''")+"'\n" for p in normalized), encoding="utf-8")
        args = ["ffmpeg", "-hide_banner", "-loglevel", "error", "-y",
                "-f", "concat", "-safe", "0", "-i", str(concat)]
        if voice:
            args += ["-i", str(voice), "-map", "0:v:0", "-map", "1:a:0",
                     "-c:v", "libx264", "-preset", "medium", "-crf", "20",
                     "-af", "apad", "-c:a", "aac", "-b:a", "192k", "-shortest"]
        else:
            args += ["-map", "0:v:0", "-c:v", "libx264", "-preset", "medium", "-crf", "20"]
        args += ["-movflags", "+faststart", str(output)]
        subprocess.run(args, check=True)
    return output


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    print(f"Video locale creee : {render(args.manifest, args.output)} (NON publiee)")


if __name__ == "__main__":
    main()
