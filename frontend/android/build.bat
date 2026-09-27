@echo off
echo 🚀 Android Development Setup Script
if not exist gradle-wrapper.properties (
    echo ⚠️  gradle-wrapper.properties not found
    echo 📦 Creating Gradle wrapper...
    ./gradlew wrapper --gradle-version=8.14.3
) else (
    echo ✅ Gradle wrapper configuration found
    if not exist gradle-wrapper.jar (
        echo 📦 Gradle wrapper jar missing, recreating...
        ./gradlew wrapper --gradle-version=8.14.3
    ) else (
        echo ✅ Gradle wrapper jar exists - using cached version
    )
)

echo.
echo ✅ Android development environment setup complete!
echo 📋 Available commands:
echo   ./gradlew assembleRelease
@echo   ./gradlew lint
@echo   ./gradlew test
@echo   ./gradlew clean
@echo   etc.
