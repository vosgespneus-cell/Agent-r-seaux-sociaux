@echo off
setlocal
title VOSGES PNEUS - Fabrique a publicites
if "%~1"=="" (
 echo Glissez une video MP4 ou MOV sur ce fichier.
 pause
 exit /b 1
)
set "IN=%~1"
set "OUTDIR=%~dp1output_vosges_pneus"
if not exist "%OUTDIR%" mkdir "%OUTDIR%"
set "OUT=%OUTDIR%\VOSGES_PNEUS_%RANDOM%.mp4"
where ffmpeg >nul 2>nul
if errorlevel 1 (
 echo [ERREUR] FFmpeg n'est pas disponible dans le PATH.
 pause
 exit /b 1
)
echo Creation d'une version verticale 9:16...
ffmpeg -y -i "%IN%" -vf "scale=720:1280:force_original_aspect_ratio=increase,crop=720:1280,fps=25" -c:v libx264 -preset veryfast -crf 21 -c:a aac -b:a 160k -movflags +faststart "%OUT%"
if errorlevel 1 (
 echo [ERREUR] Conversion impossible.
 pause
 exit /b 1
)
echo.
echo TERMINE :
echo %OUT%
pause
