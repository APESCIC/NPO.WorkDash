import { usePage } from '@inertiajs/react';

export function AppVersion() {
    const { version } = usePage().props;

    return (
        <p
            className="px-2 text-xs text-neutral-500 group-data-[collapsible=icon]:hidden dark:text-neutral-400"
            data-test="app-version"
        >
            v{version}
        </p>
    );
}
