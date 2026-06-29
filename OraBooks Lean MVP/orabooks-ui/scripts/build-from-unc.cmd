@echo off
setlocal
node "%~dp0build-direct.mjs"
exit /b %ERRORLEVEL%
