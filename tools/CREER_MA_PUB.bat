@echo off
setlocal EnableExtensions
title VOSGES PNEUS - CREER MA PUB
color 0A
echo ============================================================
echo                 VOSGES PNEUS
echo                    CREER MA PUB
echo ============================================================
echo.
if "%~1"=="" (
 echo Glissez votre video source MP4/MOV sur ce fichier.
 echo.
 pause
 exit /b 1
)
set "IN=%~1"
set "ROOT=%~dp0"
set "OUTDIR=%~dp1VOSGES_PNEUS_EXPORTS"
if not exist "%OUTDIR%" mkdir "%OUTDIR%"
for /f "tokens=1-4 delims=/ " %%a in ("%date%") do set "D=%%d%%c%%b"
set "OUT=%OUTDIR%\VOSGES_PNEUS_%D%_%RANDOM%.mp4"

where ffmpeg >nul 2>nul
if errorlevel 1 (
 echo [ERREUR] FFmpeg n'est pas disponible dans le PATH.
 echo Lancez d'abord l'installation des outils video.
 pause
 exit /b 1
)

echo [1/4] Analyse de la video...
where ffprobe >nul 2>nul && ffprobe -v error -show_entries format=duration -of default=nw=1:nk=1 "%IN%"

echo [2/4] Recadrage vertical 9:16 et normalisation...
ffmpeg -y -i "%IN%" -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280,fps=25" -c:v libx264 -preset veryfast -crf 21 -c:a aac -b:a 160k -ar 48000 -movflags +faststart "%OUT%"
if errorlevel 1 goto :error

echo [3/4] Verification du fichier final...
where ffprobe >nul 2>nul && ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height,r_frame_rate -of default=noprint_wrappers=1 "%OUT%"

echo [4/4] Termine.
echo.
echo ============================================================
echo VIDEO PRETE :
echo %OUT%
echo ============================================================
echo.
echo Etape suivante : sous-titres / voix / logo selon le projet.
start "" explorer.exe /select,"%OUT%"
pause
exit /b 0

:error
echo.
echo [ERREUR] La creation a echoue. Aucun fichier source n'a ete modifie.
pause
exit /b 1
