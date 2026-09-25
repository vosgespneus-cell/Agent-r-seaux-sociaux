@echo off
setlocal
title VOSGES PNEUS - Controle video
if "%~1"=="" (
 echo Glissez une video sur ce fichier.
 pause
 exit /b 1
)
where ffprobe >nul 2>nul || (echo [ERREUR] ffprobe n'est pas disponible dans le PATH.& pause & exit /b 1)
echo ============================================================
echo CONTROLE TECHNIQUE VIDEO
echo ============================================================
ffprobe -v error -show_entries format=filename,duration,size,bit_rate -show_entries stream=codec_name,width,height,r_frame_rate,sample_rate,channels -of default=noprint_wrappers=1 "%~1"
echo.
echo Cible reseaux : MP4 H.264, 720x1280 ou 1080x1920, vertical 9:16.
pause
