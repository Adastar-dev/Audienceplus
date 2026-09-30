@echo off
rem Double-cliquer apres chaque changement de Wi-Fi : met a jour l adresse du PC pour Jitsi et les QR codes.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0reseau-local.ps1" %*
pause
