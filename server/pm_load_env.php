<?php
/**
 * Minimal .env loader — no composer/vendor needed.
 * Upload to Hostinger premind/ as: pm_load_env.php
 *
 * Reads server/.env (KEY=VALUE per line, # comments, optional quotes) into
 * getenv()/$_ENV. Never overwrites a real environment variable the host
 * already set — .env is a fallback for local/PHP dev, not an override.
 *
 * The .env file itself must never be reachable over HTTP — see the
 * <FilesMatch "^\.env"> rule in server/.htaccess. Requesting a .env file
 * directly is the single most common way these leak in the wild (most web
 * servers serve unknown extensions as plain text by default, unlike .php
 * files which always execute instead of being shown as source).
 */
function pm_load_dotenv(string $path): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    if (!is_file($path) || !is_readable($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) continue;
        if (getenv($key) !== false) continue; // don't clobber a real env var

        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

/** Read one .env / environment value, or $default if missing/blank. */
function pm_env(string $key, string $default = ''): string {
    $val = getenv($key);
    if ($val === false || trim((string)$val) === '') return $default;
    return trim((string)$val);
}
