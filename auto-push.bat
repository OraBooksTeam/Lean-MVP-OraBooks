@echo off
setlocal

cd /d "%~dp0"

echo === Auto Git Push ===

for /f "delims=" %%i in ('git rev-parse --show-toplevel 2^>nul') do set "GIT_ROOT=%%i"
if not defined GIT_ROOT (
    echo [ERROR] Not in a Git repository.
    pause
    exit /b 1
)

cd /d "!GIT_ROOT!"

git add --ignore-errors -A .
git rm --cached -f nul 2>nul

set CHANGES=0
git diff --cached --quiet && git diff --quiet
if errorlevel 1 set CHANGES=1

if !CHANGES! equ 0 (
    echo No changes detected. Nothing to push.
    pause
    exit /b 0
)

for /f "delims=" %%i in ('powershell -Command "Get-Date -Format ""yyyy-MM-dd HH:mm:ss"""') do set DATETIME=%%i
git commit -m "Update: !DATETIME!"
if errorlevel 1 (
    echo Commit skipped (likely nothing to commit yet).
) else (
    echo Committed.
)

echo Pushing to remote...
git push
if errorlevel 1 (
    echo [ERROR] Push failed. Check remote URL or permissions.
) else (
    echo Push successful.
)

pause
