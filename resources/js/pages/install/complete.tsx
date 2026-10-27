import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import InstallLayout from '@/layouts/install-layout';

type Props = {
    loginUrl: string;
    installerFiles: string[];
};

export default function InstallComplete({ loginUrl, installerFiles }: Props) {
    return (
        <InstallLayout
            title="NPO.WorkDash is installed"
            description="The installer is now locked."
        >
            <Head title="Installation complete" />
            <div className="grid gap-6">
                <p className="text-sm text-muted-foreground">
                    Sign in with your Super Admin account. You will be asked to
                    set up two-factor authentication first.
                </p>
                {installerFiles.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        These installer files are still in the web folder:{' '}
                        {installerFiles.join(', ')}. Delete them in your file
                        manager, or from the Installation page in the Super
                        Admin area.
                    </p>
                )}
                <Button asChild className="w-full">
                    <a href={loginUrl}>Sign in</a>
                </Button>
            </div>
        </InstallLayout>
    );
}
