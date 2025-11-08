#!/usr/bin/env bash
set -euo pipefail

NAMESPACE="${1:-cms-orchestration-dev}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
RETENTION_SPEC="${RETENTION_SPEC:-${RETENTION_DAYS}d}"

if ! command -v temporal >/dev/null 2>&1; then
  echo "Temporal CLI (temporal) is required. Install instructions: https://docs.temporal.io/cli" >&2
  exit 1
fi

echo "Using Temporal target: ${TEMPORAL_ADDRESS:-localhost:7233}" >&2
if [[ -n "${TEMPORAL_PROFILE:-}" ]]; then
  echo "Temporal profile: ${TEMPORAL_PROFILE}" >&2
fi

echo "Ensuring namespace '${NAMESPACE}' exists (retention ${RETENTION_SPEC})..."
if temporal operator namespace describe --namespace "$NAMESPACE" >/dev/null 2>&1; then
  echo "Namespace '${NAMESPACE}' already exists."
else
  temporal operator namespace create \
    --namespace "$NAMESPACE" \
    --retention "$RETENTION_SPEC"
  echo "Namespace '${NAMESPACE}' created."
fi

while IFS=':' read -r name type; do
  [[ -z "$name" ]] && continue
  echo "Ensuring search attribute '${name}' (${type})..."
  if output=$(temporal operator search-attribute create --name "$name" --type "$type" 2>&1); then
    echo "$output"
  else
    if grep -qi 'AlreadyExists' <<<"$output"; then
      echo "Search attribute '${name}' already exists."
    else
      echo "$output" >&2
      exit 1
    fi
  fi
done <<'EOF_ATTRS'
cmsId:Keyword
site:Keyword
stage:Keyword
dueDate:Datetime
priority:Int
EOF_ATTRS

echo "Namespace bootstrap complete."
