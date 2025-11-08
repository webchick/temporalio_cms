# Minimal Drupal + Temporal Orchestrator

This scaffold captures the smallest end-to-end example for showcasing Temporal with a CMS: a “Scheduled Publish Assured Delivery” flow that waits until a launch date and then instructs Drupal (or WordPress) to publish + notify.

## Goals
- Keep Temporal workflows generic so any CMS can plug in.
- Make each CMS adapter tiny: emit workflow start, show status, expose approval/publish buttons.
- Ship runnable local demo via Docker Compose (Temporal server, worker/proxy, Drupal, optional WordPress).

## Repository Layout
```
simple-demo/
├── worker/                 # Temporal workflows/activities + REST proxy
├── adapters/
│   ├── drupal/             # Drupal module emitting workflow starts
│   └── wordpress/          # WordPress plugin (optional)
└── infrastructure/
    └── docker-compose.yml  # Temporal + worker + Drupal containers
```

## Workflow Concept
1. Drupal saves a node with field `field_launch_at` (datetime) and optional reviewers.
2. Drupal module POSTs to worker REST proxy; worker starts `ScheduledPublishWorkflow` with CMS metadata + timestamp.
3. Workflow waits until launch timestamp (Temporal timer). Before timer fires it can:
   - Accept `approve` signal from Drupal UI (optional gate).
   - Cancel/reschedule if Drupal sends `reschedule` signal.
4. When timer fires, workflow calls Drupal REST endpoint to publish node and send notifications.
5. Drupal status block queries workflow to show `Pending → AwaitingApproval → Scheduled → Published`.

## Worker Requirements
- One workflow (`ScheduledPublishWorkflow`) with:
  - Signals: `approvalGranted`, `reschedule(timestamp)`.
  - Query: `currentStage` for UI.
  - Activities: `schedulePublish`, `publishNode` (calls Drupal REST), optional `notifyStakeholders`.
- REST proxy exposing `/workflows`, `/signals/:id/:signal`, `/workflows/:id/status`.

## Drupal Adapter Requirements
- Base field `field_temporal_workflow_id`.
- Form config: REST endpoint, auto-start triggers (create/publish), default lead time.
- Event subscriber: on node save with future `field_launch_at`, POST to worker.
- Block/metabox: show workflow stage, provide approve/reschedule buttons.
- REST endpoint for worker to call when publish timer fires (updates node status, logs event).

## WordPress Adapter (optional)
- Mirrors Drupal via plugin hooking into `save_post` and meta box.

## Infrastructure
`infrastructure/docker-compose.yml` should define:
- Temporal server (auto-setup) + Temporal Web
- Worker container + REST proxy (Node image)
- Drupal container + DB (e.g., Postgres) with module mounted
- Optional WordPress container + DB

## Next Steps
1. Copy the existing worker scaffold (`/worker`) into `simple-demo/worker` and rename the workflow.
2. Build trimmed-down Drupal module (reuse concepts from `drupal/modules/custom/temporal_cms` but remove translation/compliance bits).
3. Flesh out `infrastructure/docker-compose.yml` using the scaffold under `docker/` as reference, but only enable Temporal, worker/proxy, and Drupal.
4. Document tutorial-friendly steps in this README: install requirements, run `docker compose up`, create a node, watch Temporal fire the publish.
