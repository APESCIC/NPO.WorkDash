import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { CheckList } from '@/components/install/check-list';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { index, rollback, start, tick } from '@/routes/admin/updates';
import type {
    BreadcrumbItem,
    CheckItem,
    ReleaseEntry,
    UpdateRun,
    UpdateStatus,
} from '@/types';

type Props = {
    version: string;
    channel: string;
    release: ReleaseEntry | null;
    releaseError: string | null;
    preflight: CheckItem[];
    activeRun: UpdateRun | null;
    history: UpdateRun[];
    canRollback: boolean;
    requiresCode: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Updates', href: index() }];

const stepLabels: Record<string, string> = {
    preflight: 'Pre-flight checks',
    backup: 'Back up database and files',
    download: 'Download release',
    verify: 'Verify checksum and signature',
    extract: 'Extract release',
    maintenance_on: 'Maintenance mode on',
    swap: 'Switch to the new release',
    migrate: 'Update the database',
    health: 'Health check',
    maintenance_off: 'Maintenance mode off',
    cleanup: 'Clean up',
};

function statusLabel(status: UpdateStatus): string {
    switch (status) {
        case 'pending':
            return 'Waiting';
        case 'running':
            return 'Running';
        case 'succeeded':
            return 'Succeeded';
        case 'failed':
            return 'Failed';
        case 'rolled_back':
            return 'Rolled back';
        default: {
            const unhandled: never = status;

            return unhandled;
        }
    }
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '';
}

function ConfirmFields({
    requiresCode,
    errors,
}: {
    requiresCode: boolean;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="password">Your password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autoComplete="current-password"
                    required
                />
                <InputError message={errors.password} />
            </div>
            {requiresCode && (
                <div className="grid gap-2">
                    <Label htmlFor="code">Two-factor code</Label>
                    <Input
                        id="code"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        required
                    />
                    <InputError message={errors.code} />
                </div>
            )}
        </div>
    );
}

function ActiveRun({ run }: { run: UpdateRun }) {
    const inFlight = useRef(false);
    const progress = run.steps.filter((step) => step.status === 'done').length;

    useEffect(() => {
        if (inFlight.current) {
            return;
        }

        inFlight.current = true;
        router.post(
            tick.url(run.id),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    inFlight.current = false;
                },
            },
        );
    }, [run]);

    return (
        <section className="grid gap-4" data-test="active-update">
            <Heading
                variant="small"
                title={`Updating to ${run.toVersion}`}
                description="Keep this page open. If it closes, open it again and the update carries on where it stopped."
            />
            <ol className="grid gap-2 text-sm">
                {run.steps.map((step) => (
                    <li key={step.position} className="flex items-center gap-2">
                        {step.status === 'running' ? (
                            <Spinner />
                        ) : (
                            <span
                                className={
                                    step.status === 'done'
                                        ? 'text-green-600'
                                        : step.status === 'failed'
                                          ? 'text-destructive'
                                          : 'text-muted-foreground'
                                }
                            >
                                {step.status === 'done'
                                    ? '✓'
                                    : step.status === 'failed'
                                      ? '✗'
                                      : '•'}
                            </span>
                        )}
                        {stepLabels[step.name] ?? step.name}
                    </li>
                ))}
            </ol>
            <p className="text-sm text-muted-foreground">
                {progress} of {run.steps.length} steps done.
            </p>
        </section>
    );
}

export default function Updates({
    version,
    channel,
    release,
    releaseError,
    preflight,
    activeRun,
    history,
    canRollback,
    requiresCode,
}: Props) {
    const preflightFails = preflight.some((item) => item.status === 'fail');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Updates" />
            <div className="flex max-w-3xl flex-col gap-8 p-4">
                <Heading
                    title="Updates"
                    description={`Running ${version} on the ${channel} channel. Updates only start when you click Update now.`}
                />

                {activeRun ? (
                    <ActiveRun run={activeRun} />
                ) : release ? (
                    <section className="grid gap-4" data-test="available">
                        <Heading
                            variant="small"
                            title={`Version ${release.version} is available`}
                            description={`Released ${formatDate(release.released_at)}`}
                        />
                        <pre className="max-h-72 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap">
                            {release.changelog}
                        </pre>
                        <CheckList checks={preflight} />
                        <Form {...start.form()} className="grid gap-4">
                            {({ processing, errors }) => (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        The site goes into maintenance mode for
                                        a minute or two. A backup is taken
                                        first, and the update rolls back on its
                                        own if anything fails.
                                    </p>
                                    <ConfirmFields
                                        requiresCode={requiresCode}
                                        errors={errors}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing || preflightFails}
                                    >
                                        {processing && <Spinner />}
                                        Update now
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {releaseError ?? 'NPO.WorkDash is up to date.'}
                    </p>
                )}

                {canRollback && !activeRun && (
                    <section className="grid gap-4">
                        <Heading
                            variant="small"
                            title="Roll back the last update"
                            description="Switches back to the previous release and restores the database backup if the update changed the database."
                        />
                        <Form {...rollback.form()} className="grid gap-4">
                            {({ processing, errors }) => (
                                <>
                                    <ConfirmFields
                                        requiresCode={requiresCode}
                                        errors={errors}
                                    />
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        Roll back
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>
                )}

                <section className="grid gap-4">
                    <Heading variant="small" title="History" />
                    {history.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No updates yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border text-sm">
                            {history.map((run) => (
                                <li key={run.id} className="grid gap-1 p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {run.fromVersion} to {run.toVersion}
                                        </span>
                                        <Badge
                                            variant={
                                                run.status === 'succeeded'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {statusLabel(run.status)}
                                        </Badge>
                                    </div>
                                    <span className="text-muted-foreground">
                                        {formatDate(run.startedAt)} from the{' '}
                                        {run.source === 'cli'
                                            ? 'command line'
                                            : 'web'}
                                        {run.startedBy
                                            ? ` by ${run.startedBy}`
                                            : ''}
                                        {run.durationSeconds !== null
                                            ? `, ${run.durationSeconds}s`
                                            : ''}
                                    </span>
                                    {run.error && (
                                        <span className="text-destructive">
                                            {run.error}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
