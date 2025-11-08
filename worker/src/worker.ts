import { Connection } from '@temporalio/client';
import { Runtime, Worker } from '@temporalio/worker';
import { appConfig } from './config';
import { contentActivities } from './activities';
import { logger } from './logger';

Runtime.install({ logger: undefined });

async function run() {
  const connection = await Connection.connect({
    address: appConfig.temporalAddress,
    tls: appConfig.tlsOptions
  });

  const worker = await Worker.create({
    workflowsPath: require.resolve('./workflows'),
    activities: contentActivities,
    taskQueue: appConfig.workflowTaskQueue,
    namespace: appConfig.temporalNamespace,
    connection
  });

  logger.info(
    {
      namespace: appConfig.temporalNamespace,
      taskQueue: appConfig.workflowTaskQueue
    },
    'Temporal worker online'
  );

  await worker.run();
}

run().catch((err) => {
  logger.error(err, 'Worker failed');
  process.exit(1);
});
