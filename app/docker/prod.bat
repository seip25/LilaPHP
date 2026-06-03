@echo off
cd %~dp0\..\..

SET COMMAND=%1

IF NOT EXIST "app\logs" mkdir "app\logs"
IF NOT EXIST "app\cache" mkdir "app\cache"

IF "%COMMAND%"=="start" (
    echo Starting production containers...
    docker compose -f app/docker/prod/docker-compose.yml up -d
) ELSE IF "%COMMAND%"=="stop" (
    echo Stopping production containers...
    docker compose -f app/docker/prod/docker-compose.yml down
) ELSE IF "%COMMAND%"=="build" (
    echo Building and starting production containers...
    docker compose -f app/docker/prod/docker-compose.yml up -d --build
) 

 ELSE IF "%COMMAND%"=="restart" (
    echo Stopping production containers...
    docker compose -f app/docker/prod/docker-compose.yml down
    echo Starting production containers...
    docker compose -f app/docker/prod/docker-compose.yml up -d --build
) 
 ELSE IF    "%COMMAND%"=="logs" (
    echo Showing production containers logs...
    docker compose -f app/docker/prod/docker-compose.yml logs
) ELSE (    
    echo Usage: app\docker\prod.bat [start^|stop^|build^|restart^|logs]
)
