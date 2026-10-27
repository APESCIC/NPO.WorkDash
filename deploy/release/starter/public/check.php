<?php

use App\Install\RequirementsChecker;

/*
 * Standalone server check. Upload the web folder and open check.php before
 * installing. Uses the same checker as the install wizard and app:check.
 * Deliberately written for old PHP versions so it can report them.
 */

$root = is_file(__DIR__.'/workdash-path.php') ? require __DIR__.'/workdash-path.php' : null;

foreach ([$root, __DIR__.'/../npo-workdash', dirname(dirname(__DIR__)).'/npo-workdash'] as $candidate) {
    if (is_string($candidate) && is_file($candidate.'/current.php')) {
        $root = $candidate;
        break;
    }
}

if (is_string($root) && is_file($root.'/shared/storage/installed.lock')) {
    http_response_code(404);
    exit;
}

require __DIR__.'/RequirementsChecker.php';

$https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

$checker = new RequirementsChecker([
    'base_path' => __DIR__,
    'app_url' => ($https ? 'https://' : 'http://').$host,
    'writable' => is_string($root) ? [
        'npo-workdash/shared' => $root.'/shared',
        'npo-workdash/shared/storage' => $root.'/shared/storage',
        'npo-workdash/releases' => $root.'/releases',
    ] : [],
]);

$items = $checker->run();
$summary = RequirementsChecker::summary($items);
$colours = ['pass' => '#15803d', 'warn' => '#b45309', 'fail' => '#b91c1c'];
$labels = ['cpanel' => 'cPanel', 'plesk' => 'Plesk', 'directadmin' => 'DirectAdmin'];

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NPO.WorkDash server check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 48rem; margin: 2rem auto; padding: 0 1rem; color: #111; }
        li { list-style: none; border-bottom: 1px solid #e5e5e5; padding: .75rem 0; }
        ul { padding: 0; }
        .status { display: inline-block; min-width: 3.5rem; font-weight: 600; text-transform: uppercase; font-size: .75rem; }
        .detail, .hint { color: #555; font-size: .875rem; margin: .25rem 0 0 3.75rem; }
    </style>
</head>
<body>
    <h1>NPO.WorkDash server check</h1>
    <p>Overall: <strong style="color: <?php echo $colours[$summary]; ?>"><?php echo strtoupper($summary); ?></strong>.
        <?php echo $summary === 'fail' ? 'Fix the failed items, then reload this page.' : 'This server can run NPO.WorkDash. Open /install to continue.'; ?></p>
    <p>The database version is checked by the install wizard once you enter the database details.</p>
    <ul>
        <?php foreach ($items as $item) { ?>
            <li data-status="<?php echo $item['status']; ?>">
                <span class="status" style="color: <?php echo $colours[$item['status']]; ?>"><?php echo $item['status']; ?></span>
                <?php echo htmlspecialchars($item['label']); ?>
                <div class="detail"><?php echo htmlspecialchars($item['detail']); ?></div>
                <?php foreach ($item['hints'] as $panel => $hint) { ?>
                    <div class="hint"><strong><?php echo $labels[$panel]; ?>:</strong> <?php echo htmlspecialchars($hint); ?></div>
                <?php } ?>
            </li>
        <?php } ?>
    </ul>
</body>
</html>
