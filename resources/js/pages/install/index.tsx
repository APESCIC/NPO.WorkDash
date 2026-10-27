import { Form, Head, router } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { CheckList } from '@/components/install/check-list';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InstallLayout from '@/layouts/install-layout';
import { store } from '@/routes/install';
import type { CheckItem, CheckStatus, InstallStep } from '@/types';

type Props = {
    step: InstallStep;
    steps: InstallStep[];
    guidesUrl: string;
    checks?: CheckItem[];
    summary?: CheckStatus;
    database?: {
        host: string;
        port: string;
        database: string;
        username: string;
    };
    total?: number;
    ran?: number;
    url?: string;
    timezone?: string;
    timezones?: string[];
    cronLine?: string;
    queueRunner?: 'cron' | 'worker';
    installerFiles?: string[];
};

const titles: Record<InstallStep, string> = {
    welcome: 'Install NPO.WorkDash',
    requirements: 'Server requirements',
    permissions: 'Folder permissions',
    database: 'Database',
    migrate: 'Setting up the database',
    app: 'Site address and timezone',
    mail: 'Email',
    admin: 'Super Admin account',
    workspace: 'First workspace',
    cron: 'Scheduled tasks (cron)',
    finish: 'Ready to finish',
};

const selectClassName =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

function Continue({
    processing,
    label = 'Continue',
}: {
    processing: boolean;
    label?: string;
}) {
    return (
        <Button type="submit" className="w-full" disabled={processing}>
            {processing && <Spinner />}
            {label}
        </Button>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Welcome({ guidesUrl }: { guidesUrl: string }) {
    return (
        <Form {...store.form('welcome')} className="grid gap-6">
            {({ processing }) => (
                <>
                    <div className="grid gap-3 text-sm text-muted-foreground">
                        <p>
                            This wizard checks your server, connects the
                            database, creates your Super Admin account and your
                            charity's first workspace. Each step saves as you
                            go, so you can refresh or come back later.
                        </p>
                        <p>
                            Have your database name, user and password ready
                            (create them in your control panel first).{' '}
                            <TextLink href={guidesUrl} target="_blank">
                                Step-by-step guides
                            </TextLink>{' '}
                            cover cPanel, Plesk, DirectAdmin, Cloudron and VPS.
                        </p>
                    </div>
                    <Continue processing={processing} label="Start" />
                </>
            )}
        </Form>
    );
}

function Checks({
    step,
    checks,
    summary,
}: {
    step: 'requirements' | 'permissions';
    checks: CheckItem[];
    summary: CheckStatus;
}) {
    return (
        <Form {...store.form(step)} className="grid gap-6">
            {({ processing, errors }) => (
                <>
                    <CheckList checks={checks} />
                    <InputError message={errors.checks} />
                    {summary === 'fail' ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.reload()}
                        >
                            Check again
                        </Button>
                    ) : (
                        <Continue processing={processing} />
                    )}
                </>
            )}
        </Form>
    );
}

function Database({ database }: { database: NonNullable<Props['database']> }) {
    return (
        <Form
            {...store.form('database')}
            className="grid gap-6"
            resetOnError={['password']}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <Field id="host" label="Host" error={errors.host}>
                                <Input
                                    id="host"
                                    name="host"
                                    defaultValue={database.host || 'localhost'}
                                    required
                                />
                            </Field>
                        </div>
                        <Field id="port" label="Port" error={errors.port}>
                            <Input
                                id="port"
                                name="port"
                                type="number"
                                defaultValue={database.port || '3306'}
                                required
                            />
                        </Field>
                    </div>
                    <Field
                        id="database"
                        label="Database name"
                        error={errors.database}
                    >
                        <Input
                            id="database"
                            name="database"
                            defaultValue={database.database}
                            required
                        />
                    </Field>
                    <Field
                        id="username"
                        label="Database user"
                        error={errors.username}
                    >
                        <Input
                            id="username"
                            name="username"
                            defaultValue={database.username}
                            autoComplete="off"
                            required
                        />
                    </Field>
                    <Field
                        id="password"
                        label="Database password"
                        error={errors.password}
                    >
                        <PasswordInput
                            id="password"
                            name="password"
                            autoComplete="off"
                        />
                    </Field>
                    {errors.confirm_not_empty && (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="confirm_not_empty"
                                    name="confirm_not_empty"
                                    value="1"
                                />
                                <Label htmlFor="confirm_not_empty">
                                    Install into this database anyway
                                </Label>
                            </div>
                            <InputError message={errors.confirm_not_empty} />
                        </div>
                    )}
                    <Continue
                        processing={processing}
                        label="Test connection and continue"
                    />
                </>
            )}
        </Form>
    );
}

function Migrate({ total, ran }: { total: number; ran: number }) {
    const [failed, setFailed] = useState(false);
    const inFlight = useRef(false);
    const percent = total > 0 ? Math.round((ran / total) * 100) : 0;

    useEffect(() => {
        if (failed || inFlight.current) {
            return;
        }

        inFlight.current = true;
        router.post(
            store.url('migrate'),
            {},
            {
                preserveScroll: true,
                onError: () => setFailed(true),
                onFinish: () => {
                    inFlight.current = false;
                },
            },
        );
    }, [ran, failed]);

    return (
        <div className="grid gap-4">
            <div
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={total}
                aria-valuenow={ran}
            >
                <div
                    className="h-full bg-primary transition-all"
                    style={{ width: `${percent}%` }}
                />
            </div>
            <p className="text-center text-sm text-muted-foreground">
                {ran} of {total} database changes applied. One runs per request,
                so this works on hosts with short time limits.
            </p>
            {failed && (
                <Button type="button" onClick={() => setFailed(false)}>
                    Retry
                </Button>
            )}
        </div>
    );
}

function Application({
    url,
    timezone,
    timezones,
}: {
    url: string;
    timezone: string;
    timezones: string[];
}) {
    return (
        <Form {...store.form('app')} className="grid gap-6">
            {({ processing, errors }) => (
                <>
                    <Field id="url" label="Site address" error={errors.url}>
                        <Input
                            id="url"
                            name="url"
                            type="url"
                            defaultValue={url}
                            required
                        />
                    </Field>
                    <Field
                        id="timezone"
                        label="Timezone"
                        error={errors.timezone}
                    >
                        <select
                            id="timezone"
                            name="timezone"
                            defaultValue={timezone}
                            className={selectClassName}
                        >
                            {timezones.map((zone) => (
                                <option key={zone} value={zone}>
                                    {zone}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Continue processing={processing} />
                </>
            )}
        </Form>
    );
}

function Mail() {
    const [mailer, setMailer] = useState<'smtp' | 'log'>('smtp');

    return (
        <Form {...store.form('mail')} className="grid gap-6">
            {({ processing, errors }) => (
                <>
                    <Field id="mailer" label="Send email" error={errors.mailer}>
                        <select
                            id="mailer"
                            name="mailer"
                            value={mailer}
                            onChange={(event) =>
                                setMailer(event.target.value as 'smtp' | 'log')
                            }
                            className={selectClassName}
                        >
                            <option value="smtp">
                                Through an SMTP server (recommended)
                            </option>
                            <option value="log">
                                Not yet: write emails to the log
                            </option>
                        </select>
                    </Field>
                    {mailer === 'smtp' && (
                        <>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="sm:col-span-2">
                                    <Field
                                        id="mail_host"
                                        label="SMTP host"
                                        error={errors.host}
                                    >
                                        <Input
                                            id="mail_host"
                                            name="host"
                                            required
                                        />
                                    </Field>
                                </div>
                                <Field
                                    id="mail_port"
                                    label="Port"
                                    error={errors.port}
                                >
                                    <Input
                                        id="mail_port"
                                        name="port"
                                        type="number"
                                        defaultValue="587"
                                        required
                                    />
                                </Field>
                            </div>
                            <Field
                                id="mail_scheme"
                                label="Encryption"
                                error={errors.scheme}
                            >
                                <select
                                    id="mail_scheme"
                                    name="scheme"
                                    defaultValue="smtp"
                                    className={selectClassName}
                                >
                                    <option value="smtp">
                                        STARTTLS (usually port 587)
                                    </option>
                                    <option value="smtps">
                                        SSL/TLS (usually port 465)
                                    </option>
                                </select>
                            </Field>
                            <Field
                                id="mail_username"
                                label="SMTP user"
                                error={errors.username}
                            >
                                <Input
                                    id="mail_username"
                                    name="username"
                                    autoComplete="off"
                                />
                            </Field>
                            <Field
                                id="mail_password"
                                label="SMTP password"
                                error={errors.password}
                            >
                                <PasswordInput
                                    id="mail_password"
                                    name="password"
                                    autoComplete="off"
                                />
                            </Field>
                            <Field
                                id="from_address"
                                label="Send from address"
                                error={errors.from_address}
                            >
                                <Input
                                    id="from_address"
                                    name="from_address"
                                    type="email"
                                    required
                                />
                            </Field>
                            <Field
                                id="test_to"
                                label="Send a test email to"
                                error={errors.test_to}
                            >
                                <Input
                                    id="test_to"
                                    name="test_to"
                                    type="email"
                                    required
                                />
                            </Field>
                        </>
                    )}
                    <Continue
                        processing={processing}
                        label={
                            mailer === 'smtp'
                                ? 'Send test email and continue'
                                : 'Continue'
                        }
                    />
                </>
            )}
        </Form>
    );
}

function Admin() {
    return (
        <Form
            {...store.form('admin')}
            className="grid gap-6"
            resetOnError={['password', 'password_confirmation']}
        >
            {({ processing, errors }) => (
                <>
                    <p className="text-sm text-muted-foreground">
                        The Super Admin manages the whole installation. There is
                        no default account: choose a long password (at least 12
                        characters, and not one found in a known data breach).
                        You will set up two-factor authentication when you first
                        sign in.
                    </p>
                    <Field id="name" label="Name" error={errors.name}>
                        <Input id="name" name="name" required />
                    </Field>
                    <Field
                        id="email"
                        label="Email address"
                        error={errors.email}
                    >
                        <Input id="email" name="email" type="email" required />
                    </Field>
                    <Field
                        id="admin_password"
                        label="Password"
                        error={errors.password}
                    >
                        <PasswordInput
                            id="admin_password"
                            name="password"
                            autoComplete="new-password"
                            required
                        />
                    </Field>
                    <Field
                        id="password_confirmation"
                        label="Confirm password"
                        error={errors.password_confirmation}
                    >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            autoComplete="new-password"
                            required
                        />
                    </Field>
                    <Continue
                        processing={processing}
                        label="Create Super Admin"
                    />
                </>
            )}
        </Form>
    );
}

function Workspace() {
    return (
        <Form {...store.form('workspace')} className="grid gap-6">
            {({ processing, errors }) => (
                <>
                    <p className="text-sm text-muted-foreground">
                        A workspace holds one charity's data. You can rename it
                        and add more later.
                    </p>
                    <Field
                        id="workspace_name"
                        label="Charity or organisation name"
                        error={errors.name}
                    >
                        <Input id="workspace_name" name="name" required />
                    </Field>
                    <Continue
                        processing={processing}
                        label="Create workspace"
                    />
                </>
            )}
        </Form>
    );
}

function Cron({
    cronLine,
    queueRunner,
    guidesUrl,
}: {
    cronLine: string;
    queueRunner: 'cron' | 'worker';
    guidesUrl: string;
}) {
    const [copied, setCopied] = useState(false);

    return (
        <Form {...store.form('cron')} className="grid gap-6">
            {({ processing }) => (
                <>
                    <p className="text-sm text-muted-foreground">
                        Add this line as a cron job that runs every minute
                        (cPanel: Cron Jobs; Plesk: Scheduled Tasks; DirectAdmin:
                        Cron Jobs).{' '}
                        {queueRunner === 'cron'
                            ? 'It runs reminders, emails and background jobs; nothing else needs to stay running.'
                            : 'Background jobs are handled by a separate queue worker on this server.'}
                    </p>
                    <div className="flex items-start gap-2">
                        <code
                            className="flex-1 rounded-md bg-muted p-3 font-mono text-xs break-all"
                            data-test="cron-line"
                        >
                            {cronLine}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Copy the cron line"
                            onClick={() => {
                                void navigator.clipboard
                                    ?.writeText(cronLine)
                                    .then(() => setCopied(true));
                            }}
                        >
                            <Copy />
                        </Button>
                    </div>
                    {copied && (
                        <p className="text-xs text-muted-foreground">Copied.</p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        If the Super Admin area later warns that cron is not
                        running, check the PHP path in this line.{' '}
                        <TextLink href={guidesUrl} target="_blank">
                            Troubleshooting
                        </TextLink>
                    </p>
                    <Continue
                        processing={processing}
                        label="I've added the cron job"
                    />
                </>
            )}
        </Form>
    );
}

function Finish({ installerFiles }: { installerFiles: string[] }) {
    return (
        <Form {...store.form('finish')} className="grid gap-6">
            {({ processing }) => (
                <>
                    <p className="text-sm text-muted-foreground">
                        Finishing locks the installer: every install page will
                        return "not found" and the installer cannot run again.
                    </p>
                    {installerFiles.length > 0 && (
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="remove_installer_files"
                                name="remove_installer_files"
                                value="1"
                                defaultChecked
                            />
                            <Label
                                htmlFor="remove_installer_files"
                                className="leading-snug"
                            >
                                Also delete the installer files from the web
                                folder ({installerFiles.join(', ')})
                            </Label>
                        </div>
                    )}
                    <Continue
                        processing={processing}
                        label="Finish installation"
                    />
                </>
            )}
        </Form>
    );
}

function StepBody(props: Props) {
    const { step } = props;

    switch (step) {
        case 'welcome':
            return <Welcome guidesUrl={props.guidesUrl} />;
        case 'requirements':
        case 'permissions':
            return (
                <Checks
                    step={step}
                    checks={props.checks ?? []}
                    summary={props.summary ?? 'pass'}
                />
            );
        case 'database':
            return (
                <Database
                    database={
                        props.database ?? {
                            host: '',
                            port: '',
                            database: '',
                            username: '',
                        }
                    }
                />
            );
        case 'migrate':
            return <Migrate total={props.total ?? 0} ran={props.ran ?? 0} />;
        case 'app':
            return (
                <Application
                    url={props.url ?? ''}
                    timezone={props.timezone ?? 'Europe/London'}
                    timezones={props.timezones ?? []}
                />
            );
        case 'mail':
            return <Mail />;
        case 'admin':
            return <Admin />;
        case 'workspace':
            return <Workspace />;
        case 'cron':
            return (
                <Cron
                    cronLine={props.cronLine ?? ''}
                    queueRunner={props.queueRunner ?? 'cron'}
                    guidesUrl={props.guidesUrl}
                />
            );
        case 'finish':
            return <Finish installerFiles={props.installerFiles ?? []} />;
        default: {
            const unhandled: never = step;

            return unhandled;
        }
    }
}

export default function Install(props: Props) {
    const position = props.steps.indexOf(props.step) + 1;

    return (
        <InstallLayout
            title={titles[props.step]}
            description={`Step ${position} of ${props.steps.length}`}
        >
            <Head title={`Install: ${titles[props.step]}`} />
            <StepBody {...props} />
        </InstallLayout>
    );
}
