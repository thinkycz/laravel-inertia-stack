<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

/**
 * Real-path-prefix check, with empty-string guards.
 *
 * @param array<int, string> $roots
 */
function env_arch_path_starts_with(string $file, array $roots): bool
{
    foreach ($roots as $root) {
        if ($root !== '' && \str_starts_with($file, $root)) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve a path list to real paths, dropping anything that cannot
 * be resolved on disk. Callers can pass plain paths and still get
 * prefix matches that survive symlink / `..` quirks.
 *
 * @param array<int, string> $paths
 *
 * @return array<int, string>
 */
function env_arch_resolve_paths(array $paths): array
{
    $resolved = [];

    foreach ($paths as $path) {
        $real = \realpath($path);
        if ($real !== false) {
            $resolved[] = $real;
        }
    }

    return $resolved;
}

\arch('Env is never called outside config files — it is unsafe after config:cache', function (): void {
    $forbidden = [
        'use Thinkycz\\LaravelCore\\Support\\Env;',
        'Env::inject(',
        '\\Env::inject(',
    ];

    // The env wrapper obviously references its own class.
    //
    // `bootstrap/app.php` is a documented exception: the
    // `withMiddleware` callback is registered via `afterResolving('kernel', ...)`,
    // which fires the moment the HTTP/console kernel is instantiated —
    // *before* the `LoadConfiguration` bootstrapper has run. Reading
    // `Config::inject()` from there panics on `Typer::assertInstance`
    // for the not-yet-bound `Illuminate\Config\Repository`. The
    // `withSchedule` and `withExceptions` callbacks fire later, so
    // those read from `Config::inject()` cleanly. The middleware
    // callback has to read the env directly, and the value is the
    // trust-proxies whitelist — a safe `parseNullableString` so a
    // missing env var only downgrades to "trust no proxies".
    $allowedFiles = [
        \base_path('packages/thinkycz/laravel-core/src/Support/Env.php'),
        \base_path('bootstrap/app.php'),
    ];

    // Config files are the only place where raw env reads are allowed;
    // their return value is baked into the config cache snapshot.
    //
    // The dedicated trait test exercises the env wrapper directly.
    //
    // The architecture tests under tests/Architecture reference the
    // forbidden needles in their `arch()` payloads by design — they
    // are the rule, not subject to it.
    $allowedDirs = [
        \base_path('config'),
        \base_path('tests/Unit/Env'),
        \base_path('tests/Architecture'),
    ];

    $skipDirs = [
        \base_path('vendor'),
        \base_path('node_modules'),
        \base_path('bootstrap/cache'),
        \base_path('storage'),
    ];

    $allowedFileRealPaths = \env_arch_resolve_paths($allowedFiles);
    $allowedDirRealPaths = \env_arch_resolve_paths($allowedDirs);
    $skipDirRealPaths = \env_arch_resolve_paths($skipDirs);

    $violations = [];

    foreach (\arch_php_files(\base_path()) as $file) {
        if (\env_arch_path_starts_with($file, $skipDirRealPaths)) {
            continue;
        }

        $real = \realpath($file) ?: $file;
        if (\in_array($real, $allowedFileRealPaths, true)) {
            continue;
        }
        if (\env_arch_path_starts_with($real, $allowedDirRealPaths)) {
            continue;
        }

        $contents = (string) \file_get_contents($file);

        foreach ($forbidden as $needle) {
            if (\str_contains($contents, $needle)) {
                $violations[] = $real . ' contains forbidden ' . $needle;
            }
        }
    }

    \expect($violations)
        ->toBe([], "Env is called outside config files (config:cache unsafe).\n" . \implode("\n", $violations));
});
