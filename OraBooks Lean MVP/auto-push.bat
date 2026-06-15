@echo off
setlocal enabledelayedexpansion

set "REPO_DIR=%~dp0"
cd /d "%REPO_DIR%"

echo === Auto Git Push ===

set HAS_CHANGES=0
for /f "delims=" %%i in ('git status --porcelain') do (
    set HAS_CHANGES=1
    goto :found_changes
)
:found_changes

if "!HAS_CHANGES!"=="0" (
    echo No changes detected. Nothing to push.
    pause
    exit /b 0
)

echo Changes detected. Staging files...
git add .
if !errorlevel! neq 0 (
    echo [ERROR] git add failed.
    pause
    exit /b 1
)

for /f "delims=" %%i in ('powershell -Command "Get-Date -Format ''yyyy-MM-dd HH:mm:ss''"') do set DATETIME=%%i
git commit -m "Auto commit: !DATETIME!"
if !errorlevel! neq 0 (
    echo [ERROR] Commit failed.
    pause
    exit /b 1
)
echo Committed successfully.

echo Pushing to remote...
git push
if !errorlevel! neq 0 (
    echo [ERROR] Push failed (likely 403).
    echo Hint: Your current Git credentials lack push access.
    echo Fix: use SSH (git@github.com:OraBooksTeam/Lean-MVP-OraBooks.git)
    echo      or authenticate with a user that has push permissions.
) else (
    echo Push successful.
)

pause
