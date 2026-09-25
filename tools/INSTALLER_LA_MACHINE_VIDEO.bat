@echo off
setlocal
title VOSGES PNEUS - Installation machine video
echo ============================================================
echo VOSGES PNEUS - PREPARATION MACHINE VIDEO
echo ============================================================
echo.
echo Ce lanceur installe uniquement les briques legeres adaptees au PC.
echo Aucun modele IA lourd n'est installe.
echo.
call "%~dp0INSTALL_VIDEO_TOOLS_WINDOWS.bat"
echo.
call "%~dp0INSTALL_QMM_AUTOEDIT_WINDOWS.bat"
echo.
echo ============================================================
echo FIN DE LA PREPARATION
echo Utilisez ensuite CREER_MA_PUB.bat
echo ============================================================
pause
