@echo off
setlocal
title VOSGES PNEUS - Installation outils video locaux
echo ============================================================
echo VOSGES PNEUS - Installation outils video locaux
echo ============================================================
echo.

where python >nul 2>nul
if errorlevel 1 (
  echo [ERREUR] Python n'est pas disponible dans le PATH.
  echo Installez Python puis relancez ce fichier.
  pause
  exit /b 1
)

where ffmpeg >nul 2>nul
if errorlevel 1 (
  echo [ATTENTION] FFmpeg n'est pas dans le PATH.
  echo Kinocut en a besoin. Le kit VOSGES PNEUS peut deja contenir FFmpeg localement,
  echo mais Kinocut attend normalement la commande ffmpeg dans le PATH.
  echo.
)

echo [1/2] Mise a jour de pip...
python -m pip install --upgrade pip
if errorlevel 1 goto :error

echo.
echo [2/2] Installation / mise a jour de Kinocut...
python -m pip install --upgrade kinocut
if errorlevel 1 goto :error

echo.
echo Verification Kinocut...
kino doctor
echo.
echo ============================================================
echo Installation terminee.
echo Si kino doctor signale seulement des fonctions IA optionnelles,
echo le montage video de base peut quand meme fonctionner.
echo ============================================================
pause
exit /b 0

:error
echo.
echo [ERREUR] L'installation n'a pas pu se terminer.
echo Copiez le message affiche et envoyez-le dans ChatGPT.
pause
exit /b 1
