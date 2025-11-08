export interface StageChangeInput {
  cmsId: string;
  stage: string;
  details?: string;
}

export interface ComplianceInput {
  cmsId: string;
  site: string;
}

export interface SchedulePublishInput {
  cmsId: string;
  publishAt: number;
}

export interface PublishContentInput {
  cmsId: string;
  targetSites: string[];
}

export interface ContentActivities {
  recordStageChange(payload: StageChangeInput): Promise<void>;
  runComplianceCheck(payload: ComplianceInput): Promise<void>;
  schedulePublish(payload: SchedulePublishInput): Promise<void>;
  publishContent(payload: PublishContentInput): Promise<void>;
}
