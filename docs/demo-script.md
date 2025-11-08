# Demo Script: Temporal CMS Orchestration End-to-End

This script walks a presenter through demonstrating the integrated Drupal + WordPress workflow powered by Temporal. Assume namespaces are provisioned (`cms-orchestration-dev` locally, `cms-orchestration` for staging) and the orchestration worker plus CMS modules are deployed.

## 1. Setup & Intro (2 min)
1. Show the Temporal Web dashboard filtered by Search Attribute `stage = awaitingAction` to highlight existing executions.
2. Explain the cast:
   - Drupal = primary authoring experience.
   - WordPress = downstream marketing site receiving approved content.
   - Temporal worker handles orchestration, translation, compliance, notifications.
3. Point out supporting services in the architecture diagram (translation, compliance, notification microservices).

## 2. Create Content in Drupal (4 min)
1. In Drupal, create a new “Global Launch” article with English source content and metadata (launch date, priority, locales: fr_CA, es_MX, de_DE).
2. When saving, narrate how the Drupal module issues `SignalWithStart` via the worker REST proxy, seeding `ContentLifecycleWorkflow` with CMS IDs and due dates.
3. Flip to Temporal Web to show the new workflow execution with Search Attributes (`cmsId`, `site=drupal`, `stage=translation`). Emphasize deterministic history and retries.

## 3. Translation Fan-Out (3 min)
1. In the worker logs or dashboard, show that three `TranslationSubWorkflow` executions spun up—one per locale.
2. Trigger a simulated translator completion for fr_CA via the translation UI (webhook) to show how signals resume child workflows.
3. In Temporal Web, highlight one child workflow completing, while others are still running. Point out automatic retries for the es_MX vendor API (force a failure, then show retry succeeding).

## 4. Compliance Review & Human Approval (5 min)
1. Once all translations finish, show `ComplianceReviewWorkflow` running parallel activities (PII scan, taxonomy check, legal review) on `cms.highprio.queue`.
2. Force a compliance failure (e.g., taxonomy mismatch) to demonstrate how Temporal records the failure and surfaces it back to Drupal.
3. In Drupal, open the node edit form. The Temporal status block should show “Compliance issue: taxonomy mismatch” with a CTA to fix metadata.
4. After correcting the taxonomy, click “Re-run checks” (sends `approvalGranted` signal). Show Temporal workflow moving to `stage=awaitingApproval`.
5. Have a reviewer approve via Drupal UI; capture that this is just another signal. Temporal workflow transitions to scheduling publish.

## 5. Publish & WordPress Sync (4 min)
1. Show `PublishAndSyncWorkflow` scheduling the publish for the configured launch time. Use Temporal Web to demonstrate pending timer.
2. Fast-forward (manually trigger `publishNow` signal). Activities call Drupal/WordPress REST APIs, confirm WordPress post creation, and run the cron-based backfill to validate success.
3. Switch to WordPress admin: show the synced post with translation metadata and status widget referencing Temporal Query results.

## 6. Observability & Operations (3 min)
1. Highlight Prometheus/Grafana dashboard displaying task queue latency, activity retries, time-in-stage metrics.
2. Demonstrate Temporal Web “Stacked History View” or trace search to find all executions for `cmsId = 12345`.
3. Run `drush temporal:resync 12345` (or `wp temporal resync 12345`) to show operator tooling. Emphasize deterministic replays & ability to fix drift.

## 7. Failure Drill & Recovery (4 min)
1. Simulate worker outage by pausing worker deployment; show Temporal persisting state and backlog building safely.
2. Restart worker; highlight how workflows resume exactly where they left off without data loss.
3. Showcase an activity heartbeat timeout—demonstrate Temporal auto-retries and surfaced alerts (Slack/email).

## 8. Wrap-Up (2 min)
1. Recap key benefits: durable human-in-the-loop orchestration, cross-CMS consistency, observability, and safe failure handling.
2. Point audience to repo structure (worker service, Drupal module, WordPress plugin) and namespace bootstrap scripts for trying it themselves.
