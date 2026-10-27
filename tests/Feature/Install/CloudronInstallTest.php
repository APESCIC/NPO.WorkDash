<?php

beforeEach(function () {
    $this->uninstall();
    useTemporaryEnvironmentFile();
});

test('on Cloudron an uninstalled app hides the web wizard and explains the terminal install', function () {
    config(['install.web_wizard' => false]);

    $this->get('/install')->assertNotFound();
    $this->post('/install/welcome')->assertNotFound();
    $this->get('/')->assertStatus(503)->assertSee('php artisan app:install');
    $this->get('/login')->assertStatus(503);
});

test('other hosts still get the web wizard', function () {
    config(['install.web_wizard' => true]);

    $this->get('/')->assertRedirect('/install');
    $this->get('/install')->assertOk();
});

test('the web wizard defaults to off on Cloudron and on elsewhere', function () {
    $cloudron = withEnvironment(['INSTALL_WEB_WIZARD' => null, 'CLOUDRON' => '1'], fn () => (require config_path('install.php'))['web_wizard']);
    $shared = withEnvironment(['INSTALL_WEB_WIZARD' => null, 'CLOUDRON' => null], fn () => (require config_path('install.php'))['web_wizard']);

    expect($cloudron)->toBeFalse()->and($shared)->toBeTrue();
});
