# Docker & Dev Container Scaffold

This directory sketches a future “one command” demo environment. It isn’t wired up yet, but the pieces below outline how we will package Temporal, worker/proxy, Drupal, and WordPress:

## Services (docker-compose)
- Temporal server + PostgreSQL (auto-setup image)
- Temporal Web UI
- Node worker + REST proxy (built from `worker/`)
- Drupal + Postgres, copying the `temporal_cms` module into the container
- WordPress + MySQL, copying the `wp-temporal-cms` plugin

## Dockerfiles
- `Dockerfile.drupal`, `Dockerfile.wordpress` define simple layer-on-top images that bake in our custom code
- Worker reuses its existing Dockerfile (to be added later) to run `npm run start:worker` / `start:proxy`

## Dev Container config
- `.devcontainer/devcontainer.json` launches the compose stack and opens VS Code in the worker container for local hacking while everything else runs alongside.

## Next steps
1. Flesh out the worker Dockerfile (multi-stage build) and mount local source files for hot reloads.
2. Add entrypoint scripts that install Drupal/WordPress, enable modules/plugins, and seed sample content automatically.
3. Wrap the namespace bootstrap + Temporal env config so the Temporal container registers `cms-orchestration-dev` on startup.
4. Document `docker compose up --build` workflow plus fallback instructions for local-only setups.
