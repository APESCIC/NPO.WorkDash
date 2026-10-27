<?php

namespace App\Updates;

use ParagonIE_Sodium_Compat as Sodium;
use SodiumException;

final class ReleaseVerifier
{
    public function __construct(private readonly string $publicKey) {}

    public static function fromConfig(): self
    {
        return new self((string) config('updates.public_key'));
    }

    /**
     * Throws unless the file matches the expected SHA-256 and carries a valid
     * signature from the release key.
     */
    public function verify(string $path, string $signature, ?string $sha256 = null): void
    {
        if ($sha256 !== null && ! hash_equals(strtolower(trim($sha256)), (string) hash_file('sha256', $path))) {
            throw new InvalidRelease('The release checksum does not match.');
        }

        $key = base64_decode($this->publicKey, true);
        $sig = base64_decode(trim($signature), true);

        if ($key === false || strlen($key) !== Sodium::CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidRelease('The configured release public key is invalid.');
        }

        if ($sig === false || strlen($sig) !== Sodium::CRYPTO_SIGN_BYTES) {
            throw new InvalidRelease('The release signature is malformed.');
        }

        try {
            $valid = Sodium::crypto_sign_verify_detached($sig, ReleaseSigner::read($path), $key);
        } catch (SodiumException) {
            $valid = false;
        }

        if (! $valid) {
            throw new InvalidRelease('The release signature is not valid for this file.');
        }
    }
}
