@echo off
echo 🚀 Angular Frontend Setup Script
if not exist package.json (
    echo ❌ package.json not found - please ensure you're in the frontend directory
    exit /b 1
)

if not exist node_modules (
    echo 📦 Installing npm dependencies...
    npm install
) else (
    echo ⚠️  Node modules found - using cached dependencies
)

echo.
echo ✅ Angular frontend setup complete!
echo 📋 Available commands:
echo   npm start
@echo   npm run build
@echo   npm run build:prod
@echo   npm run lint
@echo   npm test
@echo   etc.
