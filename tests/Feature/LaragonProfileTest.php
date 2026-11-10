<?php

function laragonProfile(): array
{
    return json_decode((string) file_get_contents(base_path('docs/laragon/profile.json')), true, flags: JSON_THROW_ON_ERROR);
}

function laragonEnvExample(): array
{
    $values = [];
    foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $match) === 1) {
            $values[$match[1]] = trim($match[2], '"');
        }
    }

    return $values;
}

test('the env example uses the Laragon profile values', function () {
    $profile = laragonProfile();
    $env = laragonEnvExample();

    expect($env['APP_ENV'])->toBe('local')
        ->and($env['APP_URL'])->toBe($profile['appUrl'])
        ->and($env['DB_HOST'])->toBe($profile['database']['host'])
        ->and((int) $env['DB_PORT'])->toBe($profile['database']['port'])
        ->and($env['DB_DATABASE'])->toBe($profile['database']['name'])
        ->and($env['DB_DATABASE'])->toEndWith('_local')
        ->and($env['DB_USERNAME'])->toBe($profile['database']['username'])
        ->and($env['DB_PASSWORD'])->toBe('');
});

test('the tests use the profile test database', function () {
    expect((string) file_get_contents(base_path('phpunit.xml')))
        ->toContain('<env name="DB_DATABASE" value="'.laragonProfile()['database']['testing'].'"/>');
});

test('the folder and Vite port match the profile', function () {
    $profile = laragonProfile();

    expect($profile['hostname'])->toBe($profile['project'].'.test')
        ->and($profile['folder'])->toBe('C:\\laragon\\www\\'.$profile['project'])
        ->and((string) file_get_contents(base_path('vite.config.ts')))->toMatch('/port:\s*'.$profile['devServerPort'].',/')
        ->toContain('strictPort: true');
});

test('the vhost runs the profile PHP through FastCGI', function () {
    $profile = laragonProfile();
    $vhost = (string) file_get_contents(base_path($profile['siteVhost']));

    expect($profile['apacheHandler'])->toBe('fcgid')
        ->and($profile['phpBuild'])->toStartWith('php-'.$profile['php'].'.')
        ->and($vhost)->toContain('define WORKDASH_PHP "C:/laragon/bin/php/'.$profile['phpBuild'].'"')
        ->toContain('FcgidWrapper "${WORKDASH_PHP}/php-cgi.exe" .php')
        ->toContain('define ROOT "C:/laragon/www/'.$profile['project'].'/public"');
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
