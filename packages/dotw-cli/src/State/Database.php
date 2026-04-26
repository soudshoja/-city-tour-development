<?php

declare(strict_types=1);

namespace Dotw\Cli\State;

/**
 * SQLite state database for the DOTW CLI.
 *
 * Opened at ~/.dotw-cli/state.db (path from config).
 * migrate() is idempotent — safe to call on every command boot.
 *
 * Tables:
 *   prebooks          — tracks getRooms blocking results + 3min expiry
 *   bookings          — confirmed DOTW bookings
 *   accounting_entries — local COA ledger (journal entries)
 */
class Database
{
    private \PDO $pdo;

    public function __construct(string $path)
    {
        $path = $this->expandTilde($path);
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new \PDO("sqlite:{$path}", options: [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec('PRAGMA foreign_keys=ON;');
    }

    /** Run schema migrations (idempotent). */
    public function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS prebooks (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                prebook_key   TEXT    NOT NULL UNIQUE,
                hotel_id      TEXT    NOT NULL,
                hotel_name    TEXT,
                check_in      TEXT    NOT NULL,
                check_out     TEXT    NOT NULL,
                room_type_code TEXT   NOT NULL,
                rate_basis    INTEGER NOT NULL,
                allocation_details TEXT NOT NULL,
                currency      TEXT    NOT NULL,
                total_fare    REAL    NOT NULL,
                markup_fare   REAL,
                raw_rooms_json TEXT,
                expires_at    TEXT    NOT NULL,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS bookings (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                prebook_key   TEXT    NOT NULL,
                booking_ref   TEXT,
                hotel_id      TEXT    NOT NULL,
                hotel_name    TEXT,
                check_in      TEXT    NOT NULL,
                check_out     TEXT    NOT NULL,
                currency      TEXT    NOT NULL,
                total_fare    REAL    NOT NULL,
                track         TEXT    NOT NULL DEFAULT 'b2b',
                status        TEXT    NOT NULL DEFAULT 'confirmed',
                raw_response_json TEXT,
                guest_details_json TEXT,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
                updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS accounting_entries (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_ref   TEXT    NOT NULL,
                entry_type    TEXT    NOT NULL,
                account_code  TEXT    NOT NULL,
                account_name  TEXT    NOT NULL,
                debit         REAL    NOT NULL DEFAULT 0,
                credit        REAL    NOT NULL DEFAULT 0,
                currency      TEXT    NOT NULL DEFAULT 'KWD',
                description   TEXT,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
            );
        SQL);
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }

    private function expandTilde(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? getenv('HOME') ?? '';
            return $home . substr($path, 1);
        }
        return $path;
    }
}
