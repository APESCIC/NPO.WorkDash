import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cleanup, show } from '@/routes/admin/installation';
import type { BreadcrumbItem } from '@/types';

type Props = {
    installation: {
        id: string | null;
        installedVersion: string | null;
        installedAt: string | null;
        version: string;
    };
    cron: {
        line: string;
        runner: 'cron' | 'worker';
        lastSeen: string | null;
        stale: boolean;
    };
    installerFiles: string[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Installation', href: show() }];

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('en-GB', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : 'Never';
}

export default function Installation({
    installation,
    cron,
    installerFiles,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Installation" />
            <div className="flex max-w-3xl flex-col gap-8 p-4">
                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title="Installation"
                        description="Version and identity of this installation"
                    />
                    <dl className="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">
                            Running version
                        </dt>
                        <dd>{installation.version}</dd>
                        <dt className="text-muted-foreground">
                            Installed version
                        </dt>
                        <dd>{installation.installedVersion ?? 'Unknown'}</dd>
                        <dt className="text-muted-foreground">Installed</dt>
                        <dd>{formatDate(installation.installedAt)}</dd>
                        <dt className="text-muted-foreground">
                            Installation ID
                        </dt>
                        <dd className="font-mono text-xs">
                            {installation.id ?? 'Unknown'}
                        </dd>
                    </dl>
                </section>

                <section className="grid gap-4">
                    <Heading
                        variant="small"
                        title="Scheduled tasks"
                        description={
                            cron.stale
                                ? 'Cron has not run for 10 minutes. Reminders, emails and background jobs are paused.'
                                : 'Cron is running.'
                        }
                    />
                    <dl className="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">Last run</dt>
                        <dd data-test="cron-last-seen">
                            {formatDate(cron.lastSeen)}
                        </dd>
                        <dt className="text-muted-foreground">
                            Background jobs
                        </dt>
                        <dd>
                            {cron.runner === 'cron'
                                ? 'Run by the per-minute cron job'
                                : 'Run by a supervised queue worker'}
                        </dd>
                    </dl>
                    <code className="rounded-md bg-muted p-3 font-mono text-xs break-all">
                        {cron.line}
                    </code>
                </section>

                {installerFiles.length > 0 && (
                    <section className="grid gap-4">
                        <Heading
                            variant="small"
                            title="Installer files"
                            description={`Still in the web folder: ${installerFiles.join(', ')}. They are not needed any more.`}
                        />
                        <Form {...cleanup.form()}>
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    type="submit"
                                    disabled={processing}
                                >
                                    Delete installer files
                                </Button>
                            )}
                        </Form>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
