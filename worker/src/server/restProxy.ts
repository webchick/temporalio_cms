import express from 'express';
import type { WorkflowHandle } from '@temporalio/client';
import { Client, Connection } from '@temporalio/client';
import { appConfig } from '../config';
import { logger } from '../logger';
import {
  CONTENT_LIFECYCLE_QUERY_NAMES,
  CONTENT_LIFECYCLE_SIGNAL_NAMES,
  ContentLifecycleInput,
  ContentLifecycleWorkflow
} from '../workflows';

async function buildClient(): Promise<Client> {
  const connection = await Connection.connect({
    address: appConfig.temporalAddress,
    tls: appConfig.tlsOptions
  });

  return new Client({
    connection,
    namespace: appConfig.temporalNamespace
  });
}

function ensureArray(value: unknown): string[] {
  return Array.isArray(value) ? (value as string[]) : [];
}

async function startServer() {
  const client = await buildClient();
  const app = express();
  app.use(express.json());

  app.get('/healthz', (_req, res) => {
    res.status(200).json({ status: 'ok' });
  });

  app.post('/workflows', async (req, res) => {
    try {
      const body = req.body as Partial<ContentLifecycleInput> & { workflowId?: string };
      if (!body?.cmsId) {
        return res.status(400).json({ error: 'cmsId is required' });
      }

      const locales = ensureArray(body.locales);
      if (locales.length === 0) {
        locales.push('en');
      }

      const input: ContentLifecycleInput = {
        cmsId: body.cmsId,
        site: body.site ?? 'drupal',
        locales,
        launchTimestampMs: body.launchTimestampMs,
        priority: body.priority,
        requestedBy: body.requestedBy
      };

      const workflowId =
        body.workflowId ?? `content-${input.cmsId}-${Date.now().toString(36)}`;

      const handle = await client.workflow.start(ContentLifecycleWorkflow, {
        args: [input],
        taskQueue: appConfig.workflowTaskQueue,
        workflowId
      });

      const result = {
        workflowId: handle.workflowId,
        runId: handle.firstExecutionRunId
      };
      logger.info(result, 'Workflow started');
      return res.status(202).json(result);
    } catch (error) {
      logger.error(error, 'Failed to start workflow');
      return res.status(500).json({ error: 'Failed to start workflow' });
    }
  });

  app.post('/signals/:workflowId/:signalName', async (req, res) => {
    const { workflowId, signalName } = req.params;
    try {
      const handle = client.workflow.getHandle(workflowId);
      await dispatchSignal(handle, signalName, req.body);
      return res.status(202).json({ workflowId, signal: signalName });
    } catch (error) {
      logger.error(error, 'Failed to dispatch signal');
      return res.status(500).json({ error: 'Failed to dispatch signal' });
    }
  });

  app.get('/workflows/:workflowId/status', async (req, res) => {
    const { workflowId } = req.params;
    try {
      const handle = client.workflow.getHandle(workflowId);
      const [stage, pendingLocales] = await Promise.all([
        handle.query(CONTENT_LIFECYCLE_QUERY_NAMES.currentStage),
        handle.query(CONTENT_LIFECYCLE_QUERY_NAMES.pendingLocales)
      ]);
      return res.json({ workflowId, stage, pendingLocales });
    } catch (error) {
      logger.error(error, 'Failed to query workflow status');
      return res.status(500).json({ error: 'Failed to query workflow status' });
    }
  });

  app.listen(appConfig.restPort, () => {
    logger.info({ port: appConfig.restPort }, 'REST proxy listening');
  });
}

async function dispatchSignal(
  handle: WorkflowHandle<typeof ContentLifecycleWorkflow>,
  signalName: string,
  body: any
) {
  switch (signalName) {
    case CONTENT_LIFECYCLE_SIGNAL_NAMES.translationComplete:
      if (!body?.locale) {
        throw new Error('locale is required for translationComplete signal');
      }
      return handle.signal(signalName, body.locale);
    case CONTENT_LIFECYCLE_SIGNAL_NAMES.approvalGranted:
      return handle.signal(signalName);
    case CONTENT_LIFECYCLE_SIGNAL_NAMES.publishNow:
      return handle.signal(signalName);
    default:
      throw new Error(`Unsupported signal: ${signalName}`);
  }
}

startServer().catch((error) => {
  logger.error(error, 'REST proxy failed');
  process.exit(1);
});
