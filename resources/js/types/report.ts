export type ReportGeneration = {
    status: 'queued' | 'ready' | 'failed';
    failure_message: string | null;
};
