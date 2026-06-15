@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"

echo === Auto Git Push ===

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Not in a Git repository.
    pause
    exit /b 1
)

set GIT_EXE=git
for /f "delims=" %%i in ('where git 2^>nul') do set "GIT_EXE=%%i"

for /f "tokens=*" %%i in ('"%GIT_EXE%" remote get-url origin 2^>nul') do set "REMOTE_URL=%%i"

set HAS_CHANGES=0
for /f "delims=" %%i in ('git status --porcelain 2^>nul') do set HAS_CHANGES=1

git rm --cached -f --ignore-unmatch nul
git add -A 2>nul

if "!HAS_CHANGES!"=="0" (
    echo No changes to commit.
    goto :PUSH
)

for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"') do set "DT=%%i"
git commit -m "Auto commit: !DT!"

:PUSH
echo Pushing to: !REMOTE_URL!
git push
if errorlevel 1 (
    echo.
    if "!REMOTE_URL!"=="git@github.com:OraBooksTeam/Lean-MVP-OraBooks.git" (
        echo [HINT] SSH push failed. Either:
        echo   1. Add an SSH key to GitHub: ^(start-ssh-agent; ssh-add path^)
        echo   2. Or switch to HTTPS:
        echo      git remote set-url origin https://github.com/OraBooksTeam/Lean-MVP-OraBooks.git
    ) else (
        echo [ERROR] Push failed. Check network, credentials, and repo permissions.
    )
) else (
    echo Push successful.
)

pause
