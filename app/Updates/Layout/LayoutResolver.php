<?php

namespace App\Updates\Layout;

use App\Install\ReleaseLayout;
use App\Models\Installation;
use Throwable;

final class LayoutResolver
{
    /**
     * The layout this app runs from, or null when it cannot be updated in
     * place (for example a git checkout).
     */
    public static function detect(?string $basePath = null): ?ReleaseLayoutDriver
    {
        $root = ReleaseLayout::root($basePath ?? base_path());

        if ($root === null) {
            return null;
        }

        if (is_file($root.'/current.php')) {
            return new PointerFileLayout($root, self::webRoots($root));
        }

        if (is_link($root.'/workdash')) {
            return new SymlinkLayout($root, $root.'/workdash');
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function webRoots(string $root): array
    {
        try {
            $recorded = Installation::query()->value('web_root');
        } catch (Throwable) {
            $recorded = null;
        }

        return array_values(array_unique(array_filter([
            is_file($root.'/public/index.php') ? $root.'/public' : null,
            is_string($recorded) && is_dir($recorded) ? rtrim($recorded, '/\\') : null,
        ])));
    }
}
