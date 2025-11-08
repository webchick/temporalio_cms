import { readFileSync } from 'node:fs';
import { config as loadEnv } from 'dotenv';
import { z } from 'zod';

loadEnv();

const schema = z.object({
  TEMPORAL_ADDRESS: z.string().nonempty().default('localhost:7233'),
  TEMPORAL_NAMESPACE: z.string().nonempty().default('cms-orchestration-dev'),
  TEMPORAL_TLS_CERT_PATH: z.string().optional(),
  TEMPORAL_TLS_KEY_PATH: z.string().optional(),
  TEMPORAL_TLS_CA_PATH: z.string().optional(),
  WORKFLOW_TASK_QUEUE: z.string().nonempty().default('cms.workflow.queue'),
  ACTIVITY_TASK_QUEUE: z.string().nonempty().default('cms.activity.queue'),
  REST_PORT: z.coerce.number().default(4000),
  LOG_LEVEL: z.string().nonempty().default('info')
});

const env = schema.parse(process.env);

const tlsOptions =
  env.TEMPORAL_TLS_CERT_PATH && env.TEMPORAL_TLS_KEY_PATH
    ? {
        clientCertPair: {
          crt: readFileSync(env.TEMPORAL_TLS_CERT_PATH),
          key: readFileSync(env.TEMPORAL_TLS_KEY_PATH)
        },
        serverRootCACertificate: env.TEMPORAL_TLS_CA_PATH
          ? readFileSync(env.TEMPORAL_TLS_CA_PATH)
          : undefined
      }
    : undefined;

export const appConfig = {
  temporalAddress: env.TEMPORAL_ADDRESS,
  temporalNamespace: env.TEMPORAL_NAMESPACE,
  workflowTaskQueue: env.WORKFLOW_TASK_QUEUE,
  activityTaskQueue: env.ACTIVITY_TASK_QUEUE,
  restPort: env.REST_PORT,
  logLevel: env.LOG_LEVEL,
  tlsOptions
};
export type AppConfig = typeof appConfig;
