<?php

namespace App\Console\Commands;

use App\Models\Charge;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * One-time migration command: encrypts existing plaintext gateway credentials
 * (api_key only -- see App\Models\Charge for why tran_portal_password and
 * terminal_resource_key are not cast/encrypted yet) on the charges table now
 * that Charge::$casts marks this column with the FaultTolerantEncrypted cast.
 *
 * IDEMPOTENT: for every candidate value this command reads the RAW column value
 * straight from the database (bypassing the Eloquent cast) and attempts
 * Crypt::decryptString() on it. If that succeeds, the value is already
 * encrypted ciphertext and is left untouched. Only values that fail to decrypt
 * (i.e. genuine plaintext) are re-saved through the model, which routes them
 * through the cast's mutator and encrypts them on write.
 *
 * Safe to re-run. No new column is added/required. Also safe to run BEFORE this
 * has fully rolled out, since FaultTolerantEncrypted never throws on read of a
 * plaintext row -- unlike the built-in 'encrypted' cast.
 *
 * Run any time you suspect plaintext api_key rows exist (e.g. after a data import).
 */
class EncryptChargeCredentials extends Command
{
    protected $signature = 'charges:encrypt-credentials {--dry-run : List rows that would be encrypted without writing anything}';

    protected $description = 'Encrypt plaintext api_key values on the charges table (idempotent, safe to re-run)';

    /**
     * @var array<int, string>
     */
    private array $columns = ['api_key'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $encryptedCount = 0;
        $alreadyEncryptedCount = 0;
        $skippedEmptyCount = 0;
        $rowsTouched = 0;

        DB::table('charges')
            ->select(array_merge(['id'], $this->columns))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$encryptedCount, &$alreadyEncryptedCount, &$skippedEmptyCount, &$rowsTouched, $dryRun) {
                foreach ($rows as $row) {
                    $plaintextValues = [];

                    foreach ($this->columns as $column) {
                        $raw = $row->{$column} ?? null;

                        if ($raw === null || $raw === '') {
                            $skippedEmptyCount++;

                            continue;
                        }

                        if ($this->isAlreadyEncrypted($raw)) {
                            $alreadyEncryptedCount++;

                            continue;
                        }

                        $plaintextValues[$column] = $raw;
                        $encryptedCount++;
                    }

                    if (empty($plaintextValues)) {
                        continue;
                    }

                    $rowsTouched++;

                    if ($dryRun) {
                        $this->line("Would encrypt Charge ID {$row->id}: ".implode(', ', array_keys($plaintextValues)));

                        continue;
                    }

                    // Load the model, but only assign the plaintext columns we
                    // actually found -- assigning routes them through the
                    // 'encrypted' cast's mutator so they are written back as
                    // ciphertext. Columns we never touch are left exactly as
                    // they are (avoids triggering a decrypt on the untouched
                    // ones via the model's magic accessor).
                    $charge = Charge::find($row->id);

                    if ($charge === null) {
                        continue;
                    }

                    foreach ($plaintextValues as $column => $value) {
                        $charge->{$column} = $value;
                    }

                    $charge->save();

                    $this->info("Encrypted Charge ID {$row->id}: ".implode(', ', array_keys($plaintextValues)));
                }
            });

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '').
            "Rows touched: {$rowsTouched}. Values encrypted: {$encryptedCount}. ".
            "Already encrypted (skipped): {$alreadyEncryptedCount}. Empty (skipped): {$skippedEmptyCount}.");

        return self::SUCCESS;
    }

    /**
     * A value is treated as "already encrypted" only if Laravel's encrypter can
     * successfully decrypt it. Any failure (DecryptException, or any other
     * throwable from a malformed payload) is treated as plaintext.
     */
    private function isAlreadyEncrypted(string $raw): bool
    {
        try {
            Crypt::decryptString($raw);

            return true;
        } catch (DecryptException $e) {
            return false;
        } catch (\Throwable $e) {
            // Malformed/unexpected payload shape -- treat as plaintext rather
            // than risk skipping a value that genuinely needs encrypting.
            return false;
        }
    }
}
