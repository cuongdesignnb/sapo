@echo off
setlocal EnableExtensions EnableDelayedExpansion

REM Load config
set CONFIG_FILE=%~dp0agent.config.bat
if not exist "%CONFIG_FILE%" (
  echo Config not found: %CONFIG_FILE%
  echo Copy agent.config.example.bat to agent.config.bat and edit it.
  exit /b 1
)
call "%CONFIG_FILE%"

echo Using config: %CONFIG_FILE%
echo SERVER_URL=%SERVER_URL%
echo DEVICE_ID=%DEVICE_ID%
echo DEVICE_IP=%DEVICE_IP%
echo DEVICE_PORT=%DEVICE_PORT%
echo TIMEOUT=%TIMEOUT%
echo INSECURE=%INSECURE%
echo CA_BUNDLE=%CA_BUNDLE%
echo HTTP_TIMEOUT=%HTTP_TIMEOUT%
echo CONNECT_TIMEOUT=%CONNECT_TIMEOUT%

REM Check PHP
where php >nul 2>nul
if errorlevel 1 (
  echo PHP not found in PATH. Please install PHP 8.2+ and add php.exe to PATH.
  exit /b 1
)

REM Check vendor
if not exist "%~dp0vendor\autoload.php" (
  echo vendor not found. Run: composer install
  exit /b 1
)

REM Validate config
if "%SERVER_URL%"=="" goto missing
if "%DEVICE_ID%"=="" goto missing
if "%DEVICE_IP%"=="" goto missing
if "%AGENT_SECRET%"=="" goto missing

REM Run agent
php "%~dp0agent.php" --server=%SERVER_URL% --device-id=%DEVICE_ID% --device-ip=%DEVICE_IP% --port=%DEVICE_PORT% --secret=%AGENT_SECRET% --timeout=%TIMEOUT% --insecure=%INSECURE% --ca-bundle=%CA_BUNDLE% --http-timeout=%HTTP_TIMEOUT% --connect-timeout=%CONNECT_TIMEOUT% --ip-resolve=%IP_RESOLVE%
exit /b %ERRORLEVEL%

:missing
echo Missing config values in agent.config.bat
exit /b 1
