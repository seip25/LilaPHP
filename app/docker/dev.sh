#!/bin/bash
# Move to the project root directory
cd "$(dirname "$0")/../.."

COMMAND=$1

# Ensure writable directories exist and have proper permissions on the host
mkdir -p app/logs app/cache
chmod -R 777 app/logs app/cache

if [ "$COMMAND" = "start" ]; then
    echo "Starting development containers..."
    docker compose -f app/docker/dev/docker-compose.yml up -d
elif [ "$COMMAND" = "stop" ]; then
    echo "Stopping development containers..."
    docker compose -f app/docker/dev/docker-compose.yml down
elif [ "$COMMAND" = "build" ]; then
    echo "Building and starting development containers..."
    docker compose -f app/docker/dev/docker-compose.yml up -d --build
elif [ "$COMMAND" = "restart" ]; then
    echo "Stopping development containers..."
    docker compose -f app/docker/dev/docker-compose.yml down
    echo "Starting development containers..."
    docker compose -f app/docker/dev/docker-compose.yml up -d --build
elif [ "$COMMAND" = "logs" ]; then
    echo "Showing development containers logs..."
    docker compose -f app/docker/dev/docker-compose.yml logs
else
    echo "Usage: ./app/docker/dev.sh [start|stop|build|restart|logs]"
fi
