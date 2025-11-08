#!/usr/bin/env bash
set -euo pipefail

# Ensure the Temporal submodule is initialized before running compose.
git submodule update --init --recursive >/dev/null 2>&1

COMPOSE_FILES=(
  "-f" "docker/temporal/docker-compose.yml"
  "-f" "docker/docker-compose.overlay.yml"
)
exec docker compose "${COMPOSE_FILES[@]}" "$@"
