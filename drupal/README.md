# Drupal Temporal CMS Module

Shim module that emits Temporal workflow requests when Drupal content changes and shows status blocks.

## Installation
1. Copy `drupal/modules/custom/temporal_cms` into your Drupal codebase (or add this repo as a submodule). The path assumes Drupal’s standard docroot.
2. Clear caches: `drush cr`.
3. Enable the module: `drush en temporal_cms`.
4. A base field `temporal_workflow_id` is attached to all nodes automatically; no manual config needed.
5. Place the “Temporal workflow status” block on the node sidebar (Structure → Block layout) so editors can see workflow state and access action buttons.

## Configuration
1. Visit Configuration → Web services → Temporal CMS.
2. Set the REST proxy base URL (default `http://localhost:4000`).
3. Choose which content types should auto-start workflows and whether to trigger on create and/or publish.
4. Provide default locales and site identifier; these values become part of the payload sent to the worker.

## How it works
- On node creation (or publish transition) for monitored content types, the module POSTs to `/workflows` on the Temporal REST proxy, saves the workflow ID into the node’s `temporal_workflow_id` base field, and also keeps a key/value entry for legacy nodes.
- The Workflow Status block uses `/workflows/{id}/status` to render the current stage and pending locales, and surfaces action buttons (translation complete per locale, approve, publish now). Links include CSRF tokens, so actions are safe for logged-in editors.
- Editors can still send signals manually via `/temporal/signal/{nid}?signal=approvalGranted&token=...` if you need to wire custom buttons; include the token shown in rendered block URLs.

## Manual test flow
1. Enable module, configure it to watch a content type (e.g., “Article”).
2. Ensure the worker/proxy from `worker/README.md` are running.
3. Create a new Article. After save, check `drush entity:read node 1 --fields=temporal_workflow_id` (replace 1) or view the node in the UI—you should see the workflow ID stored on the entity.
4. Visit the node; the status block should show stage `translation`. Use the built-in action buttons (or curl commands from the worker README) to mark translations complete, approve, or publish now, and refresh to see status updates.
