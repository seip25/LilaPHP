#!/bin/bash
# Move to the project root directory
cd "$(dirname "$0")/../.."

COMMAND=$1

# Ensure writable directories exist and have proper permissions on the host
mkdir -p app/logs app/cache
chmod -R 777 app/logs app/cache

if [ "$COMMAND" = "start" ]; then
    echo "Starting production containers..."
    docker compose -f app/docker/prod/docker-compose.yml up -d
elif [ "$COMMAND" = "stop" ]; then
    echo "Stopping production containers..."
    docker compose -f app/docker/prod/docker-compose.yml down
elif [ "$COMMAND" = "build" ]; then
    echo "Building and starting production containers..."
    docker compose -f app/docker/prod/docker-compose.yml up -d --build
elif [ "$COMMAND" = "restart" ]; then
    echo "Stopping production containers..."
    docker compose -f app/docker/prod/docker-compose.yml down
    echo "Starting production containers..."
    docker compose -f app/docker/prod/docker-compose.yml up -d --build
elif [ "$COMMAND" = "logs" ]; then
    echo "Showing production containers logs..."
    docker compose -f app/docker/prod/docker-compose.yml logs
else
    echo "Usage: ./app/docker/prod.sh [start|stop|build|restart|logs]"
fi
