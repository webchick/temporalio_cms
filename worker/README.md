# Temporal CMS Worker

Temporal worker + REST proxy scaffold for the Drupal/WordPress orchestration use case.

## Prerequisites
- [Node.js](https://nodejs.org/en/download) 18+ (`node -v`)
- [Temporal CLI](https://docs.temporal.io/cli) (`brew install temporal`)
- Temporal server running locally (`temporal server start-dev` or docker-compose)
- Register namespace `cms-orchestration-dev` (see `docs/architecture.md`). 
  `temporal operator namespace create --namespace cms-orchestration-dev` after the server starts.
  
For install help, follow the [Temporal TypeScript local setup guide](https://docs.temporal.io/develop/typescript/set-up-your-local-typescript) or the [docker-compose repo](https://github.com/temporalio/docker-compose).

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
   # now +30 seconds in ms
   LAUNCH_TS=$(($(date +%s)*1000 + 30000))
   curl -X POST http://localhost:4000/workflows \
     -H 'Content-Type: application/json' \
     -d '{
       "cmsId": "12345",
       "site": "drupal",
       "locales": ["fr_CA", "es_MX"],
       "launchTimestampMs": '"$LAUNCH_TS"'
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
