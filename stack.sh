#!/usr/bin/env bash
# This wrapper keeps Temporal's upstream compose (via submodule) and our overlay
# in sync, handling bootstrap + logging in one place.

# Set strict error handling.
set -euo pipefail

# Establish logging.
LOG_FILE="${STACK_LOG:-$(pwd)/stack.log}"
mkdir -p "$(dirname "$LOG_FILE")"
: >"$LOG_FILE"

log() {
  printf '[%s] %s\n' "$(date +"%Y-%m-%dT%H:%M:%S%z")" "$*" | tee -a "$LOG_FILE" >&2
}

# Pipe commands to log file.
run_quiet() {
  log "Running: $*"
  "$@" >>"$LOG_FILE" 2>&1
}

# Temporal has some hankiness so we lean on their docker compose setup.
# Pull in the official Temporal docker-compose so we stay aligned with upstream.
log "Initializing Temporal docker-compose submodule"
run_quiet git submodule update --init --recursive

# Combine both the base and overlay docker-compose files.
BASE_FILES=(-f docker/temporal/docker-compose.yml)
ALL_FILES=(-f docker/temporal/docker-compose.yml -f docker/docker-compose.overlay.yml)

# Figure out which command we're doing based on the first script argument.
# Valid commands:
# - up = docker compose up --build
# - down = docker compose down
# - reset = docker compose down --volumes --remove-orphans + recreate namespace


CMD=up
if [[ $# -gt 0 ]]; then
  CMD=$1
  shift
fi
ARGS=("$@")
RESET=0
if [[ "$CMD" == "reset" ]]; then
  CMD="down"
  RESET=1
fi

namespace_bootstrap() {
  local cli=(docker compose "${BASE_FILES[@]}" exec -T temporal-admin-tools temporal operator)
  if run_quiet "${cli[@]}" namespace describe cms-orchestration-dev; then
    log "Namespace cms-orchestration-dev already exists"
  else
    log "Creating namespace cms-orchestration-dev"
    run_quiet "${cli[@]}" namespace create --namespace cms-orchestration-dev --retention 168h
  fi

  for entry in \
    cmsId:Keyword \
    site:Keyword \
    stage:Keyword \
    dueDate:Datetime \
    priority:Int; do
    IFS=':' read -r name type <<<"$entry"
    log "Ensuring search attribute $name ($type)"
    docker compose "${BASE_FILES[@]}" exec -T temporal-admin-tools \
      temporal operator search-attribute create --name "$name" --type "$type" >>"$LOG_FILE" 2>&1 || true
  done
}

wait_for_temporal_api() {
  log "Waiting for Temporal API to accept CLI connections"
  for attempt in {1..30}; do
    if docker compose "${BASE_FILES[@]}" exec -T temporal-admin-tools \
         temporal operator cluster health >/dev/null 2>&1; then
      log "Temporal API is reachable"
      return 0
    fi
    sleep 2
  done
  log "Temporal API did not become reachable in time" >&2
  return 1
}

if [[ "$CMD" == up ]]; then
  log "Starting core Temporal services (waiting for health checks)"
  run_quiet docker compose "${BASE_FILES[@]}" up --build -d --wait temporal temporal-admin-tools postgresql elasticsearch
  if wait_for_temporal_api; then
    log "Bootstrapping namespace & search attributes"

    namespace_bootstrap || log "Warning: namespace bootstrap failed" >&2
  else
    log "Skipping namespace bootstrap because Temporal API was unavailable" >&2
  fi
fi

extra=()
if [[ "$RESET" -eq 1 && "$CMD" == "down" ]]; then
ARGS=("$@")
RESET=0  log "Reset requested: removing containers, networks, and volumes"
  extra+=(--volumes --remove-orphans)
fi

log "Executing docker compose $CMD ${ARGS[*]:-}"
compose_cmd=("${ALL_FILES[@]}" "$CMD")
if (( ${#extra[@]} )); then
  compose_cmd+=("${extra[@]}")
fi
if (( ${#ARGS[@]} )); then
  compose_cmd+=("${ARGS[@]}")
fi
exec docker compose "${compose_cmd[@]}"
