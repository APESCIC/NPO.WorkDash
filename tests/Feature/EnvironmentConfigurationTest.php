<?php

test('.env.example documents every environment variable used in config', function () {
    $documented = collect(file(base_path('.env.example')))
        ->map(fn (string $line) => preg_match('/^\s*#?\s*([A-Z][A-Z0-9_]*)=/', $line, $m) ? $m[1] : null)
        ->filter()
        ->unique();

    $used = collect(glob(config_path('*.php')))
        ->flatMap(function (string $file) {
            preg_match_all("/env\(\s*'([A-Z][A-Z0-9_]*)'/", (string) file_get_contents($file), $matches);

            return $matches[1];
        })
        ->unique()
        // Provided by the Cloudron platform, never set by hand.
        ->reject(fn (string $key) => $key === 'CLOUDRON' || str_starts_with($key, 'CLOUDRON_'));

    expect($used->diff($documented)->values()->all())->toBe([]);
});

test('the database connection falls back to the Cloudron MySQL addon variables', function () {
    $mysql = withEnvironment([
        'DB_HOST' => null,
        'DB_PORT' => null,
        'DB_DATABASE' => null,
        'DB_USERNAME' => null,
        'DB_PASSWORD' => null,
        'CLOUDRON_MYSQL_HOST' => 'mysql',
        'CLOUDRON_MYSQL_PORT' => '3306',
        'CLOUDRON_MYSQL_DATABASE' => 'cloudron_db',
        'CLOUDRON_MYSQL_USERNAME' => 'cloudron_user',
        'CLOUDRON_MYSQL_PASSWORD' => 'cloudron_secret',
    ], fn () => (require config_path('database.php'))['connections']['mysql']);

    expect($mysql)
        ->host->toBe('mysql')
        ->database->toBe('cloudron_db')
        ->username->toBe('cloudron_user')
        ->password->toBe('cloudron_secret');
});

test('explicit DB_ variables take precedence over the Cloudron variables', function () {
    $mysql = withEnvironment([
        'DB_DATABASE' => 'explicit_db',
        'CLOUDRON_MYSQL_DATABASE' => 'cloudron_db',
    ], fn () => (require config_path('database.php'))['connections']['mysql']);

    expect($mysql['database'])->toBe('explicit_db');
});

test('mail falls back to the Cloudron sendmail addon variables', function () {
    $mail = withEnvironment([
        'MAIL_MAILER' => null,
        'MAIL_HOST' => null,
        'MAIL_PORT' => null,
        'MAIL_USERNAME' => null,
        'MAIL_PASSWORD' => null,
        'MAIL_FROM_ADDRESS' => null,
        'CLOUDRON_MAIL_SMTP_SERVER' => 'mail',
        'CLOUDRON_MAIL_SMTP_PORT' => '2525',
        'CLOUDRON_MAIL_SMTP_USERNAME' => 'app@example.test',
        'CLOUDRON_MAIL_SMTP_PASSWORD' => 'smtp_secret',
        'CLOUDRON_MAIL_FROM' => 'app@example.test',
    ], fn () => require config_path('mail.php'));

    expect($mail['default'])->toBe('smtp')
        ->and($mail['mailers']['smtp'])
        ->host->toBe('mail')
        ->port->toBe('2525')
        ->username->toBe('app@example.test')
        ->password->toBe('smtp_secret')
        ->and($mail['from']['address'])->toBe('app@example.test');
});

test('the database connection defaults to MySQL', function () {
    $default = withEnvironment(
        ['DB_CONNECTION' => null],
        fn () => (require config_path('database.php'))['default'],
    );

    expect($default)->toBe('mysql');
});
