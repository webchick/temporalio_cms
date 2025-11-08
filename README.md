# Temporal CMS Orchestration Sandbox

This repo explores how Temporal can coordinate complex editorial workflows across Drupal and WordPress. It contains three layers:

1. **Full-featured orchestration sample** – the `worker/` service, Drupal module, and WordPress plugin that power the end-to-end demo (translation → compliance → publish).
2. **Adapters** – CMS-specific modules/plugins that start workflows from UI events and surface live status/actions to editors.
3. **Infrastructure scaffolding** – Docker/Dev Container plans (`docker/`, `.devcontainer/`) to spin up Temporal + worker + CMS instances, plus a `simple-demo/` directory outlining a smaller “Scheduled Publish” tutorial.

---

## Repository Map

| Path | Description |
| --- | --- |
| `worker/` | TypeScript Temporal worker + REST proxy (content lifecycle workflow, REST APIs for CMS adapters). |
| `drupal/modules/custom/temporal_cms/` | Drupal module that starts workflows, shows status, exposes approval buttons, and stores workflow IDs. |
| `wordpress/wp-temporal-cms/` | WordPress plugin mirroring the Drupal integration (meta box, settings page, signals). |
| `docs/` | Architecture overview (`architecture.md`) and demo script (`demo-script.md`). |
| `scripts/` | Namespace/search-attribute bootstrap helper. |
| `docker/` | Early docker-compose + Dockerfiles for a one-command demo (Temporal, worker, Drupal, WordPress). |
| `.devcontainer/` | Dev Container config pointing at the docker-compose stack. |
| `simple-demo/` | Blueprint for a minimal Temporal + Drupal “Scheduled Publish” tutorial. |

---

## Quick Start (manual)

1. **Prereqs**
   - Node.js 18+
   - Temporal CLI (`temporal`), Docker (optional)
   - Drupal 10+ and/or WordPress 6.5+ instances if testing adapters locally

2. **Bootstrap Temporal namespace**
   ```bash
   export TEMPORAL_CONFIG_FILE="$(pwd)/config/temporal.env.toml"
   export TEMPORAL_PROFILE=cms-dev
   temporal config list
   ./scripts/bootstrap-namespace.sh
   ```

3. **Run worker + proxy**
   ```bash
   cd worker
   cp .env.example .env   # adjust namespace/address as needed
   npm install
   npm run start:worker
   npm run start:proxy
   ```

4. **Integrate CMS**
   - Drupal: copy `drupal/modules/custom/temporal_cms` into your project, `drush en temporal_cms`, configure the module (REST URL, monitored content types), place the status block.
   - WordPress: copy `wordpress/wp-temporal-cms` into `wp-content/plugins`, activate “Temporal CMS Sync”, configure Settings → Temporal CMS.
   - Create/publish content → watch Temporal Web (`http://localhost:8088`) and the CMS status blocks react as workflows progress.

See `docs/demo-script.md` for a step-by-step showcase.

---

## Docker / Dev Container (WIP)

`docker/docker-compose.yml` outlines a future “one command” stack: Temporal server, Temporal Web, worker/proxy, Drupal + Postgres, WordPress + MySQL. The Dockerfiles currently bake the adapters into the CMS images; finishing work includes:

1. Worker Dockerfile + hot-reload.
2. Drupal/WP install scripts & sample data seed.
3. Automatic namespace bootstrap + Temporal env config at container startup.

`.devcontainer/devcontainer.json` lets VS Code start the compose stack and attach to the worker container for local hacking.

---

## Minimal Tutorial Track

`simple-demo/` captures a stripped-down example (“Scheduled Publish assurance”) suitable for blog/tutorial content. It reuses the same architectural patterns (Temporal workflow + CMS adapter) but focuses on one timer-based use case. Build it when you need an ultra-short demo.

---

## Contributing / Next Steps

1. Flesh out the Docker stack for a seamless demo experience.
2. Implement the `simple-demo` worker/module for tutorial content.
3. Add WP-CLI/Drush commands for resyncs/replays, plus automated tests for workflows and adapters.
4. Expand Temporal workflows with real integrations (translation providers, compliance checks) as needed.

PRs and issues welcome!
