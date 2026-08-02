<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Phase 33 — safe .env key upsert for the Admin SMTP Settings page.
 *
 * Admin-configured mail values (MAIL_MAILER / MAIL_HOST / MAIL_PORT /
 * MAIL_USERNAME / MAIL_PASSWORD / MAIL_SCHEME / MAIL_FROM_ADDRESS /
 * MAIL_FROM_NAME) are written straight into `.env` per the design
 * decision ("Save to .env file"). This helper is the ONLY place that
 * touches the file, so quoting + preservation rules live here once:
 *
 *   - Existing lines are preserved verbatim unless their KEY matches an
 *     update — comments, blank lines and unrelated variables survive.
 *   - Values containing whitespace, quotes, hashes or backslashes are
 *     written double-quoted (dotenv parses those literally). Everything
 *     else is written bare.
 *   - A value of `null` is written as `null` (dotenv's "unset" form),
 *     which Laravel's env() reads back as null — the desired state for
 *     an optional key like MAIL_SCHEME or MAIL_PASSWORD.
 *   - Missing keys are APPENDED at the end of the file.
 *
 * Not concurrency-hardened (two simultaneous saves could race), but the
 * Admin Settings page is single-operator and each save is a quick
 * read-modify-write; a lockFile() guard is intentionally skipped for
 * simplicity in this admin-only surface.
 */
final class EnvWriter
{
    /**
     * @param  string  $path  Absolute path to the target env file.
     */
    public function __construct(private readonly string $path)
    {
    }

    /**
     * Upsert `$values` (key => rawValue|null) into the env file.
     *
     * @param  array<string, string|null>  $values
     */
    public function set(array $values): void
    {
        $path = $this->path;

        $lines = file_exists($path)
            // FILE_IGNORE_NEW_LINES strips only `\n`, NOT `\r` — on a CRLF
            // file every line would keep a stray `\r`, and rejoining with
            // PHP_EOL (CRLF on Windows) would double the line endings and
            // corrupt the file on the SECOND save. Normalise with rtrim.
            ? array_map(fn (string $l) => rtrim($l, "\r\n"), file($path, FILE_IGNORE_NEW_LINES))
            : [];

        $existingKeys = [];
        foreach ($lines as $i => $line) {
            foreach ($values as $key => $value) {
                if (preg_match('/^\s*' . preg_quote($key, '/') . '=/', $line)) {
                    $lines[$i] = $this->formatLine($key, $value);
                    $existingKeys[$key] = true;
                }
            }
        }

        foreach ($values as $key => $value) {
            if (! isset($existingKeys[$key])) {
                $lines[] = $this->formatLine($key, $value);
            }
        }

        // Always write LF (Laravel/.env convention) regardless of platform.
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    /**
     * Format `KEY=value` with dotenv-compatible quoting.
     */
    private function formatLine(string $key, ?string $value): string
    {
        if ($value === null) {
            return "{$key}=null";
        }

        // Quote only when dotenv would otherwise mangle the value:
        // whitespace, quotes, # (comment start), backslashes, ${env refs}.
        if (preg_match('/[\s"#\\\\]/', $value) || Str::contains($value, '${')) {
            // Escape double quotes + backslashes inside the quoted value.
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return "{$key}=\"{$escaped}\"";
        }

        return "{$key}={$value}";
    }
}
