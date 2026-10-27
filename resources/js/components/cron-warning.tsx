import { Link, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { show } from '@/routes/admin/installation';

export function CronWarning() {
    const { cronStale } = usePage<{ cronStale?: boolean }>().props;

    if (!cronStale) {
        return null;
    }

    return (
        <div className="px-4 pt-4">
            <Alert variant="destructive" data-test="cron-warning">
                <TriangleAlert />
                <AlertTitle>Scheduled tasks are not running</AlertTitle>
                <AlertDescription>
                    <p>
                        Cron has not run for 10 minutes, so reminders, emails
                        and background jobs are paused.{' '}
                        <Link href={show()} className="underline">
                            Check the cron job
                        </Link>
                    </p>
                </AlertDescription>
            </Alert>
        </div>
    );
}
