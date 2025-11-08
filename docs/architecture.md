# Temporal CMS Orchestration: Architecture & Namespace Setup

## 1. Goals & Scope
- Provide a reusable foundation for orchestrating long-lived editorial workflows spanning Drupal and WordPress.
- Showcase Temporal strengths: durability, visibility, retries, human-in-the-loop coordination, and polyglot worker support.
- Keep CMS-specific code thin; isolate business logic inside Temporal workflows and activities.
- Support both local development (Docker Compose) and deployment to a managed Temporal environment.

## 2. High-Level Architecture
```
Drupal Module ─┐         ┌─► Translation Service
               │         │
WordPress Plugin ─┤   ┌──┴──┐     Compliance Service
               │   │ Worker │◄─► Notification Service
CMS DB/Webhooks ─┘   └──┬──┘
                        │
                   Temporal Cluster
                        │
                  Temporal Web / tctl
```
- Drupal and WordPress emit content events (create/update, review outcome) and display workflow state inside their admin UIs.
- A shared worker service (Go or TypeScript/NestJS) hosts workflows such as `ContentLifecycleWorkflow`, `TranslationSubWorkflow`, and activities for integration points.
- Supporting microservices (translation, compliance, notifications) are invoked via Temporal activities with heartbeat + retry policies.
- Operators and editors observe and control executions through Temporal Web, CMS dashboards (Queries), and approval links (Signals).

## 3. Temporal Building Blocks
| Component | Purpose | Notes |
| --- | --- | --- |
| Namespace `cms-orchestration` | Logical tenancy boundary for all workflows | Mirrors across environments (dev, staging, prod).
| Task Queues | `cms.workflow.queue`, `cms.activity.queue`, `cms.highprio.queue` | Separate workflow vs. activity throughput, reserve high-priority lane for urgent remediations.
| Workflows | `ContentLifecycleWorkflow`, `ComplianceReviewWorkflow`, `PublishAndSyncWorkflow`, `TranslationSubWorkflow` | All workflows idempotent, emit Search Attributes for CMS entity IDs.
| Activities | `PushDraftToTranslation`, `PollTranslation`, `RunComplianceChecks`, `ScheduleCMSPublish`, `NotifyStakeholders` | Activities carry CMS/site context to reach Drupal/WP REST APIs.
| Signals | `translationComplete`, `approvalGranted`, `publishNow` | Initiated by CMS UIs or external services.
| Queries | `currentStage`, `awaitingAction`, `linkedCmsUrl` | Used by CMS dashboard widgets to render live status.
| Cron | `BackfillPublishSync` | Periodically verifies published content across CMSs and restarts workflows if drift occurs.

## 4. Service Responsibilities
### 4.1 Worker Orchestration Service
- Hosts workflow/activity code, compiled per language runtime.
- Connects to Temporal via mTLS; loads namespace + task queue metadata from config.
- Exposes REST endpoint `/signals/:workflowId/:signal` so CMS modules can send approvals without Temporal SDK dependency.
- Publishes metrics (Prometheus/OpenTelemetry) for queue latency, activity failures, and human task SLA breaches.

### 4.2 Drupal Module
- Hooks into entity save and transition events to start or signal workflows via REST proxy.
- Stores Temporal workflow IDs in entity fields to correlate subsequent actions.
- Provides a block/panel that queries workflow status (calls worker REST endpoint that runs a Temporal Query) and surfaces pending actions.
- Includes a Drush command `drush temporal:resync <entity_id>` for manual remediation.

### 4.3 WordPress Plugin
- Listens to `save_post`/`transition_post_status` hooks; mirrors Drupal behavior.
- Provides WP-CLI commands for replays/resyncs.
- Adds an admin metabox showing current Temporal stage and buttons for approvals or expedite publish.

### 4.4 Translation Microservice
- Wraps an external translation vendor API (or mock) with retryable Temporal activities.
- Emits signals back to workflows when human translators finish work (webhook → worker REST → Temporal Signal).

### 4.5 Compliance Service
- Runs policy checks (PII scan, legal phrases, taxonomy alignment) in parallel activities inside `ComplianceReviewWorkflow`.
- Escalates to humans by scheduling `approvalGranted` signals when compliance officers approve in Drupal/WP.

### 4.6 Notification Service
- Sends Slack/email notifications using resilient activities with exponential backoff.
- Uses Temporal Search Attributes to tailor messages per site, language, or priority level.

## 5. Execution Flow (ContentLifecycleWorkflow)
1. **Start**: Drupal/WP saves content → module calls REST proxy → `SignalWithStart` kicks off workflow with CMS metadata.
2. **Translation fan-out**: Workflow spawns a `TranslationSubWorkflow` per target locale; waits on child completions with saga compensation if deadlines hit.
3. **Compliance gate**: Once translations return, workflow invokes `ComplianceReviewWorkflow` that runs parallel activities; failures route to `cms.highprio.queue` workers for rapid handling.
4. **Human approval**: Workflow blocks on `approvalGranted` signal. CMS UIs expose approve/reject buttons wired to workflow signals.
5. **Publish & sync**: `PublishAndSyncWorkflow` schedules publishes at the requested time; activities call CMS REST APIs and verify results via queries/cron backfills.
6. **Observability**: Workflow updates Search Attributes (`cmsId`, `site`, `stage`, `dueDate`). Temporal Web dashboards filter on these attributes, and CMS UI queries display statuses.

## 6. Namespace Setup Plan
### 6.1 Local Development Namespace (`cms-orchestration-dev`)
1. **Bootstrap Temporal cluster** using Docker Compose:
   ```bash
   git clone https://github.com/temporalio/docker-compose temporal-local
   cd temporal-local
   docker compose up -d
   ```
2. **Create namespace**:
   ```bash
   tctl --ns default namespace register --global_namespace false --retention 7
   tctl namespace update --namespace default --rename cms-orchestration-dev
   ```
   _Alternatively_: `tctl namespace register cms-orchestration-dev --retention 7`.
3. **Deploy worker** pointing to `localhost:7233`, namespace `cms-orchestration-dev`, task queues defined above.
4. **Configure CMS modules** with env vars (`TEMPORAL_NAMESPACE`, `TEMPORAL_ENDPOINT`, `WORKFLOW_TASK_QUEUE`).
5. **Temporal Web** runs at `http://localhost:8088`; filters for `cmsId` Search Attribute for debugging.

### 6.2 Shared Staging/Prod Namespace (`cms-orchestration`)
1. **Provision Temporal Cloud** (or self-hosted cluster) with TLS enabled; request namespace `cms-orchestration` via provider console.
2. **Store certificates** in secret manager (AWS Secrets Manager / Vault). Worker service mounts cert + key for mTLS connections.
3. **Define namespace configuration**:
   - Retention: 30 days staging, 90 days prod.
   - Archival: enable history archival to S3/GCS bucket (`temporal-cms-archive`).
   - Custom Search Attributes: `cmsId` (Keyword), `site` (Keyword), `stage` (Keyword), `dueDate` (Datetime), `priority` (Int).
4. **Access Control**:
   - Use Temporal Cloud SSO (Okta) with roles: `cms-admin`, `cms-reviewer`, `observer`.
   - API keys scoped to worker pods; CMS modules interact only via REST proxy → worker signals.
5. **Disaster Recovery**: configure namespace replication (Temporal Cloud multi-region) or xDC clusters for self-hosted; document failover runbooks.
6. **Monitoring/Alerts**: wire Temporal Cloud metrics to Grafana/Datadog; alert on task queue backlog, workflow stuck in `awaitingAction`, activity retries > threshold.

### 6.3 Automation Scripts
- Add a `scripts/namespace-bootstrap.sh` to codify `tctl` commands and Search Attribute registration (future milestone).
- Integrate with CI (GitHub Actions) to ensure namespace schema stays in sync via Temporal Cloud API.

## 7. Next Steps
1. Scaffold worker service repository with SDK boilerplate and Signal/Query handlers.
2. Create Drupal and WordPress integration modules/plugins with configuration forms for Temporal endpoints.
3. Implement namespace bootstrap script + IaC (Terraform) for Temporal Cloud resources.
4. Draft failure-mode runbooks (activity retry storms, stuck signals, namespace failover).
