# Temporal CMS Orchestration Sandbox

This repo explores how Temporal can coordinate complex editorial workflows across Drupal and WordPress. It contains three layers:

1. **Full-featured orchestration sample** – the `worker/` service, Drupal module, and WordPress plugin that power the end-to-end demo (translation → compliance → publish).
2. **Adapters** – CMS-specific modules/plugins that start workflows from UI events and surface live status/actions to editors.
3. **Infrastructure scaffolding** – Docker/Dev Container plans (`docker/`, `.devcontainer/`) to spin up Temporal + worker + CMS instances, plus a `simple-demo/` directory outlining a smaller “Scheduled Publish” tutorial.

## Quickstart

Run the following:

```
./stack.sh up --build 
```

Once the containers settle you can visit each CMS directly:

- Drupal CMS (auto-installed with its recipe + the Temporal adapter): <http://localhost:8082> — default admin login `admin / admin` (configurable via `DRUPAL_ADMIN_*` in `docker/docker-compose.overlay.yml`).
- WordPress (standard first-run installer): <http://localhost:8081>.

Load up the Temporal Web UI to keep an eye on progress:

http://localhost:8080/namespaces/cms-orchestration-dev/workflows

Run the following curl commands to simulate a full workflow request:

```
# Start a new content publishing workflow.
curl -X POST http://localhost:4000/workflows \                                         
  -H 'Content-Type: application/json' \
  -d '{
    "cmsId": "demo-1",
    "site": "drupal",
    "locales": ["fr_CA","es_MX"],
    "launchTimestampMs": '"$(($(date +%s)*1000 + 60000))"'
  }'
```

(Make note of the workflowID, and replace it in the URLs below.)

```
# Verify translations.
curl -X POST http://localhost:4000/signals/<workflowId>/translationComplete \          
  -H 'Content-Type: application/json' \
  -d '{"locale":"fr_CA"}'
    
curl -X POST http://localhost:4000/signals/<workflowId>/translationComplete \
  -H 'Content-Type: application/json' \
  -d '{"locale":"es_MX"}'        
```

```
# Mark content as approved.
curl -X POST http://localhost:4000/signals/content-demo-1-mhqw1r16/approvalGranted 
```

```
# (optional) Bypass the timer and publish directly.
curl -X POST http://localhost:4000/signals/content-demo-1-mhqw1r16/publishNow 
```

---

## Repository Map

| Path | Description |
| --- | --- |
| `worker/` | TypeScript Temporal worker + REST proxy (content lifecycle workflow, REST APIs for CMS adapters). |
| `drupal/modules/custom/temporal_cms/` | Drupal module that starts workflows, shows status, exposes approval buttons, and stores workflow IDs. |
| `wordpress/wp-temporal-cms/` | WordPress plugin mirroring the Drupal integration (meta box, settings page, signals). |
| `docs/` | Architecture overview (`architecture.md`) and demo script (`demo-script.md`). |
| `scripts/` | Namespace/search-attribute bootstrap helper. |
| `docker/` | Docker stack + helper script (`stack.sh`) that reuses the official Temporal docker-compose via git submodule and builds Drupal CMS/WordPress adapter containers. |
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

## Docker Demo Stack

The `docker/` folder contains everything needed to run Temporal, the worker + REST proxy, Drupal, and WordPress together:

1. **Temporal submodule** – we pull in <https://github.com/temporalio/docker-compose> as `docker/temporal/`. It provides the official Temporal/Postgres/Elasticsearch/UI services. The helper script automatically initializes this submodule the first time you run it, but you can also do it manually with:

   ```bash
   git submodule update --init --recursive
   ```

2. **Overlay services** – `docker/docker-compose.overlay.yml` defines our worker, REST proxy, Drupal, and WordPress containers. Drupal is built from `docker/drupal-cms/Dockerfile`, which bakes the Drupal CMS distribution into the image. All services attach to the same `temporal-network` created by the upstream compose file.
   - The Drupal container waits for `drupal-db`, then runs `drush site:install drupal_cms_installer` automatically with the credentials defined in the compose file (see the `DRUPAL_*` variables to override site name/admin login).
   - WordPress still uses its regular first-run wizard; you can complete it at <http://localhost:8081>.

3. **One-command runner** – `./stack.sh` (at the repo root) wraps the combined compose invocation:

   ```bash
   ./stack.sh up --build        # start everything
   ./stack.sh down              # stop/remove containers
   ```

  After the stack is up, visit:
  - Temporal UI: `http://localhost:8080` (or the port you remap it to)
  - Drupal CMS: `http://localhost:8082`
  - WordPress: `http://localhost:8081`
  - Worker REST proxy: `http://localhost:4000`

`.devcontainer/devcontainer.json` still lets VS Code start the same compose stack and drop you inside the worker container for local hacking.

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
