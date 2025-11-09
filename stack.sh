#!/usr/bin/env bash
set -euo pipefail

LOG_FILE="${STACK_LOG:-$(pwd)/stack.log}"
mkdir -p "$(dirname "$LOG_FILE")"
: >"$LOG_FILE"

log() {
  printf '[%s] %s\n' "$(date +"%Y-%m-%dT%H:%M:%S%z")" "$*" | tee -a "$LOG_FILE" >&2
}

run_quiet() {
  log "Running: $*"
  "$@" >>"$LOG_FILE" 2>&1
}

log "Initializing Temporal docker-compose submodule"
run_quiet git submodule update --init --recursive

BASE_FILES=(-f docker/temporal/docker-compose.yml)
ALL_FILES=(-f docker/temporal/docker-compose.yml -f docker/docker-compose.overlay.yml)

CMD=${1:-up}
shift $(( $# > 0 ? 1 : 0 ))

WIPE=0
ARGS=()
for arg in "$@"; do
  case "$arg" in
    --wipe|--reset)
      WIPE=1
      ;;
    *)
      ARGS+=("$arg")
      ;;
  esac
done

if [[ "$CMD" == "reset" ]]; then
  CMD="down"
  WIPE=1
fi

wait_for_temporal() {
  log "Waiting for Temporal frontend"
  for attempt in {1..60}; do
    if docker compose "${BASE_FILES[@]}" exec -T temporal-admin-tools \
         temporal operator cluster health >/dev/null 2>&1; then
      log "Temporal frontend is ready"
      return 0
    fi
    sleep 2
  done
  log "Temporal did not become ready in time" >&2
  return 1
}

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

wait_for_admin_tools() {
  log "Waiting for temporal-admin-tools container"
  for attempt in {1..30}; do
    if docker compose "${BASE_FILES[@]}" exec -T temporal-admin-tools true >/dev/null 2>&1; then
      log "temporal-admin-tools is ready"
      return 0
    fi
    sleep 2
  done
  log "temporal-admin-tools did not become ready in time" >&2
  return 1
}

if [[ "$CMD" == up ]]; then
  log "Starting core Temporal services"
  run_quiet docker compose "${BASE_FILES[@]}" up -d temporal temporal-admin-tools postgresql elasticsearch
  if wait_for_admin_tools && wait_for_temporal; then
    log "Bootstrapping namespace & search attributes"
    namespace_bootstrap || log "Warning: namespace bootstrap failed" >&2
  else
    log "Skipping namespace bootstrap because admin tools or Temporal were not ready" >&2
  fi
fi

extra=()
if [[ "$WIPE" -eq 1 && "$CMD" == "down" ]]; then
  log "Wipe requested: removing containers, networks, and volumes"
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
