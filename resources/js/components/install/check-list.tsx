import { CircleAlert, CircleCheck, CircleX } from 'lucide-react';
import type { CheckItem, CheckStatus } from '@/types';

const panels = {
    cpanel: 'cPanel',
    plesk: 'Plesk',
    directadmin: 'DirectAdmin',
} as const;

function StatusIcon({ status }: { status: CheckStatus }) {
    switch (status) {
        case 'pass':
            return (
                <CircleCheck
                    className="size-4 shrink-0 text-green-600"
                    aria-label="Pass"
                />
            );
        case 'warn':
            return (
                <CircleAlert
                    className="size-4 shrink-0 text-amber-500"
                    aria-label="Warning"
                />
            );
        case 'fail':
            return (
                <CircleX
                    className="size-4 shrink-0 text-destructive"
                    aria-label="Fail"
                />
            );
        default: {
            const unhandled: never = status;

            return unhandled;
        }
    }
}

export function CheckList({ checks }: { checks: CheckItem[] }) {
    return (
        <ul className="divide-y rounded-lg border text-sm">
            {checks.map((check) => (
                <li
                    key={check.key}
                    className="flex gap-3 p-3"
                    data-status={check.status}
                >
                    <StatusIcon status={check.status} />
                    <div className="grid gap-1">
                        <span className="font-medium">{check.label}</span>
                        <span className="text-muted-foreground">
                            {check.detail}
                        </span>
                        {check.status !== 'pass' &&
                            Object.keys(check.hints).length > 0 && (
                                <dl className="mt-1 grid gap-1 text-xs">
                                    {(
                                        Object.keys(panels) as Array<
                                            keyof typeof panels
                                        >
                                    ).map(
                                        (panel) =>
                                            check.hints[panel] && (
                                                <div key={panel}>
                                                    <dt className="inline font-medium">
                                                        {panels[panel]}:{' '}
                                                    </dt>
                                                    <dd className="inline text-muted-foreground">
                                                        {check.hints[panel]}
                                                    </dd>
                                                </div>
                                            ),
                                    )}
                                </dl>
                            )}
                    </div>
                </li>
            ))}
        </ul>
    );
}
