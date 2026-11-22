"""Synthese vocale locale avec Piper, uniquement si le moteur et la voix sont installes.

Exemple:
 python -m src.video_voice --text narration.txt --model voix/fr_FR.onnx --output narration.wav

Aucun telechargement, aucune API, aucune publication. La licence du modele
vocal doit etre verifiee independamment avant usage commercial.
"""
import argparse
from pathlib import Path
import shutil
import subprocess


def voice_command(text_path, model_path, output_path, executable=None):
    engine = executable or shutil.which("piper")
    if not engine:
        raise RuntimeError("Piper non installe ou absent du PATH")
    source = Path(text_path).resolve()
    model = Path(model_path).resolve()
    destination = Path(output_path).resolve()
    if not source.is_file() or not source.read_text(encoding="utf-8").strip():
        raise ValueError("Narration absente ou vide")
    if not model.is_file() or model.suffix.lower() != ".onnx":
        raise ValueError("Modele Piper .onnx absent")
    if destination.suffix.lower() != ".wav":
        raise ValueError("La sortie doit etre un WAV")
    return [str(engine), "--model", str(model), "--output_file", str(destination)], source, destination


def synthesize(text_path, model_path, output_path, executable=None):
    command, source, destination = voice_command(
        text_path, model_path, output_path, executable
    )
    destination.parent.mkdir(parents=True, exist_ok=True)
    # Aucun shell, aucune execution distante. Le texte passe en entree standard.
    with source.open("r", encoding="utf-8") as narration:
        subprocess.run(command, stdin=narration, check=True, timeout=900)
    if not destination.is_file() or destination.stat().st_size == 0:
        raise RuntimeError("Piper n'a pas cree de piste audio valide")
    return destination


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--text", required=True)
    parser.add_argument("--model", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    print(f"Voix generee localement : {synthesize(args.text, args.model, args.output)}")


if __name__ == "__main__":
    main()
