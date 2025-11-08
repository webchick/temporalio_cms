# Temporal CMS Worker

Temporal worker + REST proxy scaffold for the Drupal/WordPress orchestration use case.

## Prerequisites
- Node.js 18+
- Temporal server running locally (`temporal server start-dev` or docker-compose) with namespace `cms-orchestration-dev` registered (see `docs/architecture.md`).

## Setup
```bash
cd worker
cp .env.example .env
npm install
```
Adjust `.env` if you are targeting a different Temporal address/namespace or need mTLS.

## Running locally
In two terminals:
```bash
npm run start:worker   # boots Temporal worker
npm run start:proxy    # boots REST proxy on http://localhost:4000
```
You can also run both together:
```bash
npm run dev
```

## Manual test flow
1. **Start a workflow**
   ```bash
   curl -X POST http://localhost:4000/workflows \
     -H 'Content-Type: application/json' \
     -d '{
       "cmsId": "12345",
       "site": "drupal",
       "locales": ["fr_CA", "es_MX"],
       "launchTimestampMs": '$(($(date +%s%3N) + 30000))'
     }'
   ```
   Capture the `workflowId` from the response.
2. **Complete translations**
   ```bash
   curl -X POST http://localhost:4000/signals/<workflowId>/translationComplete \
     -H 'Content-Type: application/json' \
     -d '{"locale": "fr_CA"}'
   curl -X POST http://localhost:4000/signals/<workflowId>/translationComplete \
     -H 'Content-Type: application/json' \
     -d '{"locale": "es_MX"}'
   ```
3. **Approve content**
   ```bash
   curl -X POST http://localhost:4000/signals/<workflowId>/approvalGranted
   ```
4. **Expedite publish (optional)**
   ```bash
   curl -X POST http://localhost:4000/signals/<workflowId>/publishNow
   ```
5. **Check status**
   ```bash
   curl http://localhost:4000/workflows/<workflowId>/status
   ```

Use Temporal Web (`http://localhost:8233` if using `temporal server start-dev`) to observe the workflow history.
