@echo off
setlocal
title VOSGES PNEUS - QMM AutoEdit
echo ============================================================
echo VOSGES PNEUS - Installation QMM AutoEdit
echo CPU uniquement - aucun GPU NVIDIA requis
echo ============================================================
echo.
where git >nul 2>nul || (echo [ERREUR] Git n'est pas installe ou pas dans le PATH.& pause & exit /b 1)
where python >nul 2>nul || (echo [ERREUR] Python n'est pas dans le PATH.& pause & exit /b 1)
where ffmpeg >nul 2>nul || echo [ATTENTION] FFmpeg n'est pas dans le PATH. Ajoutez-le avant utilisation.
set "DEST=%USERPROFILE%\VosgesPneusTools\qmm-autoedit"
if not exist "%USERPROFILE%\VosgesPneusTools" mkdir "%USERPROFILE%\VosgesPneusTools"
if exist "%DEST%\.git" (
  echo Mise a jour de QMM AutoEdit...
  git -C "%DEST%" pull
) else (
  echo Telechargement de QMM AutoEdit...
  git clone https://github.com/respectfulnrespected59-source/qmm-autoedit.git "%DEST%"
)
if errorlevel 1 goto :error
cd /d "%DEST%"
python -m pip install --upgrade pip
if exist requirements.txt python -m pip install -r requirements.txt
echo.
echo ============================================================
echo QMM AutoEdit est pret dans :
echo %DEST%
echo ============================================================
echo.
echo Fonctions utiles : suppression des silences, format vertical 9:16,
echo sous-titres karaoke, selection automatique d'un extrait dynamique.
pause
exit /b 0
:error
echo [ERREUR] Installation interrompue.
pause
exit /b 1
