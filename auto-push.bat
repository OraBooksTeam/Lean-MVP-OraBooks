@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"

echo === Auto Git Push ===

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo Not in a Git repository.
    pause
    exit /b 1
)

git rm --cached -f --ignore-unmatch nul
git add -A 2>nul

set HAS_CHANGES=0
for /f "delims=" %%i in ('git status --porcelain 2^>nul') do set HAS_CHANGES=1

if "!HAS_CHANGES!"=="0" (
    echo No changes to commit.
    goto PUSH
)

for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"') do set "DT=%%i"
git commit -m "Auto commit: !DT!"
if errorlevel 1 (
    echo Commit skipped.
    goto PUSH
)
echo Committed.

:PUSH
echo Pushing to remote...
git push
if errorlevel 1 (
    echo [ERROR] Push failed.
) else (
    echo Push successful.
)

pause
