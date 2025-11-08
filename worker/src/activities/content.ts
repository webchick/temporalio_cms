import { setTimeout as sleep } from 'node:timers/promises';
import { logger } from '../logger';
import type {
  ComplianceInput,
  ContentActivities,
  PublishContentInput,
  SchedulePublishInput,
  StageChangeInput
} from './types';

const LATENCY_MS = 250;

async function simulateLatency() {
  await sleep(LATENCY_MS);
}

export const contentActivities: ContentActivities = {
  async recordStageChange(payload: StageChangeInput) {
    logger.info({ payload }, 'Stage change recorded');
    await simulateLatency();
  },
  async runComplianceCheck(payload: ComplianceInput) {
    logger.info({ payload }, 'Running compliance check');
    await simulateLatency();
  },
  async schedulePublish(payload: SchedulePublishInput) {
    logger.info({ payload }, 'Scheduling publish window');
    await simulateLatency();
  },
  async publishContent(payload: PublishContentInput) {
    logger.info({ payload }, 'Publishing content to target sites');
    await simulateLatency();
  }
};
