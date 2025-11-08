import pino from 'pino';
import { appConfig } from './config';

export const logger = pino({ level: appConfig.logLevel });
