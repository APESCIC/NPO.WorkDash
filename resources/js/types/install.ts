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
    hints: Partial<Record<'cpanel' | 'plesk' | 'directadmin', string>>;
};
