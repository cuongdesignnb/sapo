@echo off
REM === Attendance Agent Config ===
REM Copy this file to: agent.config.bat and edit values

set SERVER_URL=https://app.cuongdesign.net
set DEVICE_ID=1
set DEVICE_IP=192.168.1.222
set DEVICE_PORT=4370
set AGENT_SECRET=CHANGE_ME

REM Optional
set TIMEOUT=5
set INSECURE=0
set CA_BUNDLE=
set HTTP_TIMEOUT=20
set CONNECT_TIMEOUT=10
set IP_RESOLVE=v4
