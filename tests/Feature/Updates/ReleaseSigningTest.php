<?php

use App\Updates\InvalidRelease;
use App\Updates\ReleaseSigner;
use App\Updates\ReleaseVerifier;
use Illuminate\Support\Facades\File;
use ParagonIE_Sodium_Compat as Sodium;

beforeEach(function () {
    $pair = Sodium::crypto_sign_keypair();
    $this->secretKey = base64_encode(Sodium::crypto_sign_secretkey($pair));
    $this->publicKey = base64_encode(Sodium::crypto_sign_publickey($pair));

    $this->dir = storage_path('framework/testing/signing-'.uniqid());
    File::ensureDirectoryExists($this->dir);
    $this->zip = "{$this->dir}/npo-workdash-9.9.9.zip";
    file_put_contents($this->zip, random_bytes(4096));
});

afterEach(fn () => File::deleteDirectory($this->dir));

test('a signed release with the right checksum verifies', function () {
    $signature = ReleaseSigner::sign($this->zip, $this->secretKey);

    (new ReleaseVerifier($this->publicKey))->verify($this->zip, $signature, hash_file('sha256', $this->zip));
})->throwsNoExceptions();

test('a zip with one modified byte is rejected', function () {
    $signature = ReleaseSigner::sign($this->zip, $this->secretKey);

    $bytes = file_get_contents($this->zip);
    $bytes[100] = chr(ord($bytes[100]) ^ 0x01);
    file_put_contents($this->zip, $bytes);

    (new ReleaseVerifier($this->publicKey))->verify($this->zip, $signature);
})->throws(InvalidRelease::class, 'signature is not valid');

test('a signature from another key is rejected', function () {
    $other = base64_encode(Sodium::crypto_sign_secretkey(Sodium::crypto_sign_keypair()));

    (new ReleaseVerifier($this->publicKey))->verify($this->zip, ReleaseSigner::sign($this->zip, $other));
})->throws(InvalidRelease::class, 'signature is not valid');

test('a checksum mismatch is rejected before the signature is checked', function () {
    $signature = ReleaseSigner::sign($this->zip, $this->secretKey);

    (new ReleaseVerifier($this->publicKey))->verify($this->zip, $signature, str_repeat('0', 64));
})->throws(InvalidRelease::class, 'checksum does not match');

test('a malformed signature is rejected', function () {
    (new ReleaseVerifier($this->publicKey))->verify($this->zip, 'not-a-signature');
})->throws(InvalidRelease::class, 'malformed');

test('the configured release public key is a valid Ed25519 key', function () {
    expect(strlen((string) base64_decode(config('updates.public_key'), true)))->toBe(Sodium::CRYPTO_SIGN_PUBLICKEYBYTES);
});

test('release:sign writes a checksum and a signature the verifier accepts', function () {
    putenv("RELEASE_SIGNING_KEY={$this->secretKey}");

    try {
        $this->artisan('release:sign', ['file' => $this->zip])->assertSuccessful();
    } finally {
        putenv('RELEASE_SIGNING_KEY');
    }

    [$sha256] = explode('  ', (string) file_get_contents("{$this->zip}.sha256"));

    (new ReleaseVerifier($this->publicKey))->verify($this->zip, (string) file_get_contents("{$this->zip}.sig"), $sha256);
});

test('release:verify accepts a signed zip and rejects a tampered one', function () {
    config(['updates.public_key' => $this->publicKey]);
    file_put_contents("{$this->zip}.sig", ReleaseSigner::sign($this->zip, $this->secretKey));
    file_put_contents("{$this->zip}.sha256", hash_file('sha256', $this->zip).'  '.basename($this->zip));

    $this->artisan('release:verify', ['file' => $this->zip])->assertSuccessful();

    file_put_contents($this->zip, 'tampered', FILE_APPEND);

    $this->artisan('release:verify', ['file' => $this->zip])->assertFailed();
});

test('release:sign fails without a signing key', function () {
    putenv('RELEASE_SIGNING_KEY');

    $this->artisan('release:sign', ['file' => $this->zip])->assertFailed();
});

test('no private signing key is committed to the repository', function () {
    $tracked = array_filter(explode("\n", (string) shell_exec('git -C '.escapeshellarg(base_path()).' ls-files')));

    foreach ($tracked as $path) {
        $full = base_path($path);

        if (! is_file($full) || filesize($full) > 512 * 1024) {
            continue;
        }

        expect((string) file_get_contents($full))->not->toMatch('/RELEASE_SIGNING_KEY\s*[:=]\s*[A-Za-z0-9+\/]{80,}={0,2}/');
    }
});
