#!/usr/bin/env bash
set -euo pipefail
COMPOSE_FILES=("-f" "temporal/docker-compose.yml" "-f" "docker-compose.overlay.yml")
exec docker compose "${COMPOSE_FILES[@]}" "$@"
