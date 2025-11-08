# Docker & Dev Container Scaffold

This directory now contains a runnable (developer-oriented) compose stack that brings up Temporal, the Node worker + REST proxy, Drupal, and WordPress. It still requires a few manual steps inside the CMS containers, but it’s a solid starting point for an “all-in-one” demo.

## Services
- Temporal stack comes from `temporal/docker-compose.yml` (official repo). We layer our services via `docker-compose.overlay.yml`.
- `worker` / `rest-proxy`: Node container built from `../worker`, running `npm run start:worker` / `start:proxy` with live mounts.
- `drupal` + `drupal-db`: Drupal 10 (Apache) with the custom module bind-mounted, backed by MySQL 8.0.
- `wordpress` + `wordpress-db`: WordPress (Apache) with the custom plugin bind-mounted, backed by MySQL.

## Usage
```bash
cd docker
docker compose \
  -f temporal/docker-compose.yml \
  -f docker-compose.overlay.yml \
  up --build
```

Then:
1. Temporal Web is at <http://localhost:8088>; run `../scripts/bootstrap-namespace.sh` once (from repo root) to register `cms-orchestration-dev`.
2. Drupal: visit <http://localhost:8080>, complete the installer (DB driver MySQL, host `drupal-db`, database `drupal`, user/password `drupal`). Enable the “Temporal CMS” module via the UI or `drush en temporal_cms`. Configure the module to point at `http://rest-proxy:4000`.
3. WordPress: visit <http://localhost:8081>, follow the installer (DB host `wordpress-db`, user/password `wordpress`, DB name `wordpress`). Activate “Temporal CMS Sync” and configure Settings → Temporal CMS to use `http://rest-proxy:4000`.
4. Create content in either CMS and watch workflows progress in Temporal Web.

The worker container mounts `../worker`, so local code edits are reflected immediately; `node_modules` live in a named volume to avoid host pollution.

## Next steps
1. Add install scripts (Drush + WP-CLI) so the CMS containers auto-install + enable modules on first boot.
2. Integrate the namespace/search-attribute bootstrap into a container entrypoint so Temporal is ready without manual CLI calls.
3. Seed sample content and screenshots for the demo script.
4. Optionally expose Traefik/HTTPS for friendlier URLs.

## Dev Container
`.devcontainer/devcontainer.json` consumes the same compose file so VS Code Dev Containers can open inside the worker service while the rest of the stack runs alongside.
