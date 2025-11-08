# WordPress Temporal CMS Plugin

Shim plugin that mirrors the Drupal module: starts Temporal workflows when WordPress posts are created/published, stores the workflow ID on the post, and exposes status/actions in the editor.

## Installation
1. Copy `wordpress/wp-temporal-cms` into your WordPress `wp-content/plugins` directory.
2. Activate “Temporal CMS Sync” from the Plugins screen.
3. Visit Settings → Temporal CMS to configure the REST proxy URL, site identifier, locales, and which post types should trigger workflows.

## Features
- Automatically POSTs to the Temporal REST proxy when monitored post types are saved/published (configurable triggers).
- Saves the returned workflow ID in post meta (`_temporal_workflow_id`).
- Adds a meta box on the post edit screen showing workflow ID, stage, pending locales, and quick action buttons (translation complete per locale, approve, publish now). Buttons emit signals via signed admin-post URLs.
- Provides a thin HTTP client wrapper that reuses the same REST contract as the Drupal module.

## Manual test flow
1. Ensure the Temporal worker and REST proxy described in `worker/README.md` are running locally.
2. Configure the plugin to monitor the “Post” type and point at the local proxy.
3. Create a new post (or publish an existing draft). After saving, check the Custom Fields panel or run `wp post meta get <ID> _temporal_workflow_id` to see the linked workflow ID.
4. Use the meta box buttons or the curl commands to drive translation/approval/publish signals and refresh the editor to see status updates.
