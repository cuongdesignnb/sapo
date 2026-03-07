@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM Load config
set CONFIG_FILE=%~dp0agent.config.bat
if not exist "%CONFIG_FILE%" (
  echo Config not found: %CONFIG_FILE%
  exit /b 1
)
call "%CONFIG_FILE%"

echo.
echo ========================================
echo TESTING CONNECTION TO VPS
echo ========================================
echo SERVER_URL: %SERVER_URL%
echo.

REM Test 1: Simple GET to /api/test
echo [TEST 1] GET /api/test
curl -v -X GET "%SERVER_URL%/api/test" 2>&1
echo.
echo.

REM Test 2: POST to push-logs with minimal data
echo [TEST 2] POST /api/attendance-agent/push-logs (empty body)
curl -v -X POST "%SERVER_URL%/api/attendance-agent/push-logs" -H "Content-Type: application/json" -d "{}" 2>&1
echo.
echo.

REM Test 3: Check what IP we resolve to
echo [TEST 3] DNS Resolution
nslookup app.cuongdesign.net 2>&1
echo.

pause
