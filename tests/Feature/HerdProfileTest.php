<?php

function herdProfile(): array
{
    return json_decode((string) file_get_contents(base_path('docs/herd/profile.json')), true, flags: JSON_THROW_ON_ERROR);
}

function herdEnvExample(): array
{
    $values = [];
    foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
            $values[$match[1]] = trim($match[2], '"');
        }
    }

    return $values;
}

test('the env example uses the Herd profile values', function () {
    $profile = herdProfile();
    $env = herdEnvExample();

    expect($env['APP_ENV'])->toBe('local')
        ->and($env['APP_URL'])->toBe($profile['appUrl'])
        ->and($env['DB_HOST'])->toBe($profile['database']['host'])
        ->and((int) $env['DB_PORT'])->toBe($profile['database']['port'])
        ->and($env['DB_DATABASE'])->toBe($profile['database']['name'])
        ->and($env['DB_DATABASE'])->toEndWith('_local')
        ->and($env['DB_USERNAME'])->toBe($profile['database']['username'])
        ->and($env['DB_PASSWORD'])->toBe('')
        ->and($env['MAIL_MAILER'])->toBe($profile['mail']['mailer'])
        ->and($env['MAIL_HOST'])->toBe($profile['mail']['host'])
        ->and((int) $env['MAIL_PORT'])->toBe($profile['mail']['port']);
});

test('the tests use the profile test database', function () {
    expect((string) file_get_contents(base_path('phpunit.xml')))
        ->toContain('<env name="DB_DATABASE" value="'.herdProfile()['database']['testing'].'"/>');
});

test('the Herd site and Vite port match the profile', function () {
    $profile = herdProfile();

    expect($profile['hostname'])->toBe($profile['herdSite'].'.test')
        ->and($profile['appUrl'])->toBe('http://'.$profile['hostname'])
        ->and($profile['phpIsolated'])->toBeTrue()
        ->and((string) file_get_contents(base_path('vite.config.ts')))->toMatch('/port:\s*'.$profile['devServerPort'].',/')
        ->toContain('strictPort: true');
});

test('the profile PHP is the version composer.json allows', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require']['php'])->toBe('^'.herdProfile()['php']);
});

test('public/.htaccess is safe under FastCGI', function () {
    $depth = 0;
    $outside = [];
    foreach (file(base_path('public/.htaccess'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*<IfModule\s+(php_module|mod_php\d*\.c)\s*>/i', $line) === 1) {
            $depth++;
        } elseif ($depth > 0 && preg_match('#^\s*</IfModule>#i', $line) === 1) {
            $depth--;
        } elseif ($depth === 0 && preg_match('/^\s*php_(value|flag|admin_value|admin_flag)\b/i', $line) === 1) {
            $outside[] = trim($line);
        }
    }

    expect($outside)->toBe([])
        ->and((string) file_get_contents(base_path('public/.htaccess')))->toContain('HTTP_AUTHORIZATION');
});
