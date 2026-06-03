@echo off
cd %~dp0\..\..

SET COMMAND=%1

IF NOT EXIST "app\logs" mkdir "app\logs"
IF NOT EXIST "app\cache" mkdir "app\cache"

IF "%COMMAND%"=="start" (
    echo Starting development containers...
    docker compose -f app/docker/dev/docker-compose.yml up -d
) ELSE IF "%COMMAND%"=="stop" (
    echo Stopping development containers...
    docker compose -f app/docker/dev/docker-compose.yml down
) ELSE IF "%COMMAND%"=="build" (
    echo Building and starting development containers...
    docker compose -f app/docker/dev/docker-compose.yml up -d --build
)
ELSE IF "%COMMAND%"=="restart" (
    echo Stopping development containers...
    docker compose -f app/docker/dev/docker-compose.yml down
    echo Starting development containers...
    docker compose -f app/docker/dev/docker-compose.yml up -d --build
)
ELSE IF "%COMMAND%"=="logs" (
    echo Showing development containers logs...
    docker compose -f app/docker/dev/docker-compose.yml logs
) ELSE (
    echo Usage: app\docker\dev.bat [start^|stop^|build^|restart^|logs]
)
