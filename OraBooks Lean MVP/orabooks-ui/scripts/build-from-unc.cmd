@echo off
setlocal
set "UNC=%~dp0.."
set "UNC=%UNC:~0,-1%"
subst Z: /D >nul 2>&1
subst Z: "%UNC%"
if errorlevel 1 (
  echo Failed to map drive Z: to %UNC%
  exit /b 1
)
pushd Z:\
call npm run build
set "ERR=%ERRORLEVEL%"
popd
subst Z: /D >nul 2>&1
exit /b %ERR%
