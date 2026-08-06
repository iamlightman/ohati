@echo off
echo ===================================================
echo     Ohati App - Automated GitHub Mass Uploader
echo ===================================================
echo.
set /p REPO_URL="Enter your GitHub Repository URL (e.g. https://github.com/username/ohati-app.git): "

if "%REPO_URL%"=="" (
    echo Error: No URL provided. Please create a repository on GitHub first!
    pause
    exit /b
)

echo.
echo Initializing Git repository...
git init
git config user.name "Ohati Developer"
git config user.email "developer@ohati.com"

echo.
echo Adding all project files...
git add .

echo.
echo Committing project files...
git commit -m "Full Ohati App Release with Android AAB/APK & iOS Cloud Build"

echo.
echo Setting branch to main...
git branch -M main

echo.
echo Connecting to GitHub repository...
git remote remove origin >nul 2>&1
git remote add origin %REPO_URL%

echo.
echo Pushing all files to GitHub...
git push -u origin main

echo.
echo ===================================================
echo     SUCCESS! Project uploaded to GitHub!
echo ===================================================
pause
