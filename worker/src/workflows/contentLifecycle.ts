import {
  condition,
  defineQuery,
  defineSignal,
  proxyActivities,
  setHandler,
  sleep,
  workflowInfo
} from '@temporalio/workflow';
import type { ContentActivities } from '../activities';

export interface ContentLifecycleInput {
  cmsId: string;
  site: 'drupal' | 'wordpress';
  locales: string[];
  launchTimestampMs?: number;
  priority?: number;
  requestedBy?: string;
}

export interface ContentLifecycleResult {
  cmsId: string;
  finalStage: string;
}

export const CONTENT_LIFECYCLE_SIGNAL_NAMES = {
  translationComplete: 'translationComplete',
  approvalGranted: 'approvalGranted',
  publishNow: 'publishNow'
} as const;

export const CONTENT_LIFECYCLE_QUERY_NAMES = {
  currentStage: 'currentStage',
  pendingLocales: 'pendingLocales'
} as const;

type SignalName = typeof CONTENT_LIFECYCLE_SIGNAL_NAMES[keyof typeof CONTENT_LIFECYCLE_SIGNAL_NAMES];

type QueryName = typeof CONTENT_LIFECYCLE_QUERY_NAMES[keyof typeof CONTENT_LIFECYCLE_QUERY_NAMES];

const activities = proxyActivities<ContentActivities>({
  startToCloseTimeout: '1 minute'
});

const translationCompleteSignal = defineSignal<[string]>(CONTENT_LIFECYCLE_SIGNAL_NAMES.translationComplete);
const approvalGrantedSignal = defineSignal<void>(CONTENT_LIFECYCLE_SIGNAL_NAMES.approvalGranted);
const publishNowSignal = defineSignal<void>(CONTENT_LIFECYCLE_SIGNAL_NAMES.publishNow);

const currentStageQuery = defineQuery<string>(CONTENT_LIFECYCLE_QUERY_NAMES.currentStage);
const pendingLocalesQuery = defineQuery<string[]>(CONTENT_LIFECYCLE_QUERY_NAMES.pendingLocales);

export async function ContentLifecycleWorkflow(
  input: ContentLifecycleInput
): Promise<ContentLifecycleResult> {
  const locales = input.locales ?? [];
  let stage = 'translation';
  const completedLocales = new Set<string>();
  let approvalGranted = false;
  let publishGateOpen = false;

  setHandler(translationCompleteSignal, (locale: string) => {
    if (locales.includes(locale)) {
      completedLocales.add(locale);
    }
  });

  setHandler(approvalGrantedSignal, () => {
    approvalGranted = true;
  });

  setHandler(publishNowSignal, () => {
    publishGateOpen = true;
  });

  setHandler(currentStageQuery, () => stage);

  setHandler(pendingLocalesQuery, () => locales.filter((locale) => !completedLocales.has(locale)));

  await activities.recordStageChange({ cmsId: input.cmsId, stage, details: 'Awaiting translations' });

  await condition(() => completedLocales.size >= locales.length);

  stage = 'compliance';
  await activities.recordStageChange({ cmsId: input.cmsId, stage });
  await activities.runComplianceCheck({ cmsId: input.cmsId, site: input.site });

  stage = 'awaitingApproval';
  await activities.recordStageChange({ cmsId: input.cmsId, stage });
  await condition(() => approvalGranted);

  stage = 'scheduled';
  const now = workflowInfo().currentTime;
  const publishAt = input.launchTimestampMs ?? now;
  await activities.schedulePublish({ cmsId: input.cmsId, publishAt });

  const publishWindow = (async () => {
    if (publishAt > now) {
      await sleep(publishAt - now);
    }
    publishGateOpen = true;
  })();

  await Promise.race([publishWindow, condition(() => publishGateOpen)]);

  stage = 'publishing';
  await activities.recordStageChange({ cmsId: input.cmsId, stage });
  await activities.publishContent({ cmsId: input.cmsId, targetSites: ['drupal', 'wordpress'] });

  stage = 'completed';
  await activities.recordStageChange({ cmsId: input.cmsId, stage, details: 'Workflow complete' });

  return { cmsId: input.cmsId, finalStage: stage };
}

export type ContentLifecycleSignal = SignalName;
export type ContentLifecycleQuery = QueryName;
