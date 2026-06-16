@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo Not a Git repository.
    pause
    exit /b 1
)

echo nul >> .gitignore 2>nul
git rm --cached -f --ignore-unmatch nul 2>nul

echo === Real-time Git Push ===
echo Watching for changes every 5 seconds...
echo Press Ctrl+C to stop.

:LOOP
git add . 2>nul
git diff --quiet HEAD
if errorlevel 1 (
    for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format 'yyyy-MM-dd HH:mm:ss'"') do set "DT=%%i"
    echo [!DT!] Changes detected. Committing...
    git commit -m "Auto commit: !DT!"
    if errorlevel 1 (
        echo [ERROR] Commit failed.
    ) else (
        echo Pushing to remote...
        git push
        if errorlevel 1 (
            echo [ERROR] Push failed.
        ) else (
            echo Push successful.
        )
        echo.
    )
)

timeout /t 5 /nobreak >nul
goto :LOOP
