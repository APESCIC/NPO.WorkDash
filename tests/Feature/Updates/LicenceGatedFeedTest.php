<?php

use App\Models\Licence;
use App\Updates\UpdateFeed;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $entry = [
        'version' => '0.2.0',
        'channel' => 'stable',
        'released_at' => '2026-10-27T00:00:00+00:00',
        'changelog' => '- Release',
        'size' => 10,
        'sha256' => hash('sha256', 'x'),
        'download_url' => 'https://github.com/APESCIC/NPO.WorkDash/releases/download/v0.2.0/npo-workdash-0.2.0.zip',
        'signature_url' => 'https://github.com/APESCIC/NPO.WorkDash/releases/download/v0.2.0/npo-workdash-0.2.0.zip.sig',
        'requirements' => config('updates.requirements'),
        'min_update_from' => '0.1.0',
    ];

    Http::fake(['*' => Http::response(UpdateFeed::build([$entry], 'stable', '2026-10-27T00:00:00+00:00'))]);
});

test('licence checks are off by default', function () {
    expect(config('updates.licence.required'))->toBeFalse();
});

test('with the switch off the feed serves download links without a key', function () {
    config(['updates.licence.required' => false]);

    $this->getJson(route('updates.feed', 'stable'))
        ->assertOk()
        ->assertJsonPath('releases.0.download_url', fn (string $url) => str_ends_with($url, 'npo-workdash-0.2.0.zip'))
        ->assertJsonMissingPath('licence');
});

test('with the switch on a missing key gets no download link', function () {
    config(['updates.licence.required' => true]);

    $response = $this->getJson(route('updates.feed', 'stable'))
        ->assertOk()
        ->assertJsonPath('licence.status', 'missing')
        ->assertJsonPath('releases.0.version', '0.2.0')
        ->assertJsonMissingPath('releases.0.download_url')
        ->assertJsonMissingPath('releases.0.signature_url');

    expect(UpdateFeed::errors($response->json()))->toBe([]);
});

test('with the switch on an expired key gets no download link', function () {
    config(['updates.licence.required' => true]);
    Licence::factory()->forKey('expired-key')->expired()->create();

    $this->getJson(route('updates.feed', 'stable'), ['X-Licence-Key' => 'expired-key'])
        ->assertOk()
        ->assertJsonPath('licence.status', 'expired')
        ->assertJsonMissingPath('releases.0.download_url');
});

test('with the switch on a valid key gets download links', function () {
    config(['updates.licence.required' => true]);
    Licence::factory()->forKey('valid-key')->create();

    $this->getJson(route('updates.feed', 'stable'), ['X-Licence-Key' => 'valid-key', 'X-Installation-Id' => 'install-1'])
        ->assertOk()
        ->assertJsonPath('licence.status', 'valid')
        ->assertJsonPath('releases.0.download_url', fn (string $url) => str_ends_with($url, '.zip'));
});

test('a revoked or unknown key is invalid', function () {
    Licence::factory()->forKey('revoked-key')->create(['revoked_at' => now()]);

    expect(Licence::statusFor('revoked-key'))->toBe('invalid')
        ->and(Licence::statusFor('unknown-key'))->toBe('invalid')
        ->and(Licence::statusFor(null))->toBe('missing');
});

test('unknown channels return 404', function () {
    $this->getJson('/updates/feed/nightly')->assertNotFound();
});
