"""Audit local et sans effet de bord des outils de production vidéo.

Commande : python -m src.video_preflight
Ne télécharge rien, ne contacte aucune API, ne publie rien.
"""
import json
import os
from pathlib import Path
import platform
import shutil
import subprocess


TOOLS = ("python", "ffmpeg", "ffprobe", "kdenlive", "piper", "git", "nvidia-smi")


def tool_status(name):
    path = shutil.which(name)
    return {"available": bool(path), "executable": path}


def graphics_status():
    nvidia = shutil.which("nvidia-smi")
    if not nvidia:
        return {"nvidia_smi_available": False, "gpu_details": "non_verifies"}
    try:
        proc = subprocess.run(
            [nvidia, "--query-gpu=name,memory.total", "--format=csv,noheader"],
            capture_output=True, text=True, timeout=8, check=False,
        )
        if proc.returncode == 0:
            return {"nvidia_smi_available": True, "gpu_details": proc.stdout.strip()[:500]}
    except (OSError, subprocess.TimeoutExpired):
        pass
    return {"nvidia_smi_available": True, "gpu_details": "lecture_impossible"}


def audit():
    # Ne pas parcourir les dossiers privés, ni collecter les variables d'environnement.
    return {
        "host_os": platform.system(),
        "host_architecture": platform.machine(),
        "tools": {name: tool_status(name) for name in TOOLS},
        "graphics": graphics_status(),
        "agnes_api_key_configured": bool(os.environ.get("AGNES_API_KEY")),
        "notes": [
            "La presence d'un executable ne prouve pas qu'il fonctionne.",
            "ComfyUI peut etre installe sans commande globale : detection non concluante.",
            "La cle Agnes est seulement signalee comme presente/absente, jamais affichee.",
            "Aucune verification des credits Agnes ni des licences des modeles n'est effectuee.",
            "Aucun rendu ni publication n'est declenche.",
        ],
    }


def main():
    print(json.dumps(audit(), ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
