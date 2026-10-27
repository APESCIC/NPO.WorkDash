export type InstallStep =
    | 'welcome'
    | 'requirements'
    | 'permissions'
    | 'database'
    | 'migrate'
    | 'app'
    | 'mail'
    | 'admin'
    | 'workspace'
    | 'cron'
    | 'finish';

export type CheckStatus = 'pass' | 'warn' | 'fail';

export type CheckItem = {
    key: string;
    label: string;
    status: CheckStatus;
    detail: string;
    hints?: Partial<Record<'cpanel' | 'plesk' | 'directadmin', string>>;
};

export type UpdateStatus =
    'pending' | 'running' | 'succeeded' | 'failed' | 'rolled_back';

export type UpdateRun = {
    id: number;
    status: UpdateStatus;
    fromVersion: string;
    toVersion: string;
    source: 'web' | 'cli';
    startedBy: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    durationSeconds: number | null;
    error: string | null;
    steps: Array<{
        position: number;
        name: string;
        status: 'pending' | 'running' | 'done' | 'failed';
        output: string | null;
    }>;
};

export type ReleaseEntry = {
    version: string;
    channel: 'stable' | 'beta';
    released_at: string;
    changelog: string;
    size: number;
};
