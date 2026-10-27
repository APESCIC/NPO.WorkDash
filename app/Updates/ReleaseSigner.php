<?php

namespace App\Updates;

use ParagonIE_Sodium_Compat as Sodium;

final class ReleaseSigner
{
    /**
     * Detached Ed25519 signature of a file, base64 encoded.
     *
     * @param  string  $secretKey  base64 libsodium secret key (64 bytes)
     */
    public static function sign(string $path, string $secretKey): string
    {
        $key = base64_decode(trim($secretKey), true);

        if ($key === false || strlen($key) !== Sodium::CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidRelease('The signing key is not a base64 Ed25519 secret key.');
        }

        return base64_encode(Sodium::crypto_sign_detached(self::read($path), $key));
    }

    public static function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new InvalidRelease("Cannot read {$path}.");
        }

        return $contents;
    }
}
