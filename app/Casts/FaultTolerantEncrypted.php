<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts on write, but never throws on read.
 *
 * The built-in 'encrypted' cast throws Illuminate\Contracts\Encryption\DecryptException
 * on ANY value it cannot decrypt -- including a plaintext value left over from before
 * the cast was introduced. Since a bulk re-encrypt command (charges:encrypt-credentials)
 * is meant to run only after deploy, every request in the window between "cast goes live"
 * and "re-encrypt command finishes" would fatal on read of an un-migrated row. That is an
 * actual outage, not a theoretical data-safety concern.
 *
 * This cast removes that window entirely:
 * - get(): try to decrypt. If it fails (DecryptException) or the value is empty, return the
 *   raw stored value unchanged -- i.e. an old plaintext row reads back exactly as it always
 *   did, no throw, no data loss, no behavior change for callers.
 * - set(): always encrypt via Crypt::encryptString() (null passes through unchanged), so
 *   every write from this point forward produces ciphertext.
 *
 * Net effect: reads are always safe (old plaintext or new ciphertext both work) whether or
 * not the re-encrypt command has run yet; writes always upgrade the stored value to
 * ciphertext. The re-encrypt command converts existing rows at its own pace -- it is not
 * forced to run atomically with the deploy.
 */
class FaultTolerantEncrypted implements CastsAttributes
{
    /**
     * @param  Model  $model
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Not ciphertext we can decrypt (most likely: pre-existing plaintext
            // written before this cast existed). Return as-is rather than throw.
            return $value;
        }
    }

    /**
     * @param  Model  $model
     * @param  mixed  $value
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
