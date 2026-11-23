<?php

/*
 * dot-queue-precheck: is there anything in this app's database queue? One cheap PDO query, no framework boot.
 *
 *   php dot-queue-precheck.php /home/user/<host>/<app>
 *
 * Exit 10  the queue table exists and is EMPTY: the cron runner may skip `queue:work` for this app this minute.
 * Anything else (0 jobs waiting, 20 queue is not the database driver, 2 and above: not sure) means RUN the worker.
 * It is deliberately fail-open: any doubt, missing file, unusual setting or database error makes the runner run
 * the worker exactly as it did before this tool existed. It never prints a secret.
 */

declare(strict_types=1);

const EMPTY_QUEUE = 10;
const RUN_JOBS_WAITING = 0;
const RUN_NOT_DATABASE_QUEUE = 20;
const RUN_UNSURE = 2;

function unsure(string $why): never
{
    fwrite(STDOUT, "run:{$why}\n");
    exit(RUN_UNSURE);
}

/** @return array<string, string> */
function readEnv(string $file): array
{
    $out = [];
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $out;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
            $quote = $val[0];
            $end = strpos($val, $quote, 1);
            $val = $end === false ? substr($val, 1) : substr($val, 1, $end - 1);
        } else {
            $hash = strpos($val, ' #');
            if ($hash !== false) {
                $val = rtrim(substr($val, 0, $hash));
            }
        }
        $out[$key] = $val;
    }

    return $out;
}

$dir = $argv[1] ?? '';
if ($dir === '' || ! is_dir($dir)) {
    unsure('no_app_dir');
}
$env = readEnv(rtrim($dir, '/').'/.env');
if ($env === []) {
    unsure('no_env');
}
$get = static fn (string $k, string $default = ''): string => (string) ($env[$k] ?? $default);

if ($get('QUEUE_CONNECTION', 'database') !== 'database') {
    fwrite(STDOUT, "run:queue_driver_not_database\n");
    exit(RUN_NOT_DATABASE_QUEUE);
}
if ($get('DB_QUEUE_CONNECTION') !== '' || $get('DB_PREFIX') !== '') {
    unsure('custom_queue_connection_or_prefix');
}
foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET', 'DB_CONNECTION'] as $k) {
    if (str_contains($get($k), '${')) {
        unsure('env_interpolation');
    }
}
$table = $get('DB_QUEUE_TABLE', 'jobs');
if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
    unsure('odd_table_name');
}

$driver = strtolower($get('DB_CONNECTION'));
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3];

try {
    if ($driver === 'mysql' || $driver === 'mariadb') {
        $socket = $get('DB_SOCKET');
        $dsn = $socket !== ''
            ? 'mysql:unix_socket='.$socket.';dbname='.$get('DB_DATABASE')
            : 'mysql:host='.$get('DB_HOST', '127.0.0.1').';port='.$get('DB_PORT', '3306').';dbname='.$get('DB_DATABASE');
        $pdo = new PDO($dsn, $get('DB_USERNAME'), $get('DB_PASSWORD'), $options);
        $sql = "SELECT 1 FROM `{$table}` LIMIT 1";
    } elseif ($driver === 'pgsql') {
        $dsn = 'pgsql:host='.$get('DB_HOST', '127.0.0.1').';port='.$get('DB_PORT', '5432').';dbname='.$get('DB_DATABASE');
        $pdo = new PDO($dsn, $get('DB_USERNAME'), $get('DB_PASSWORD'), $options);
        $sql = "SELECT 1 FROM \"{$table}\" LIMIT 1";
    } elseif ($driver === 'sqlite') {
        $path = $get('DB_DATABASE');
        if ($path === '' || $path === ':memory:') {
            unsure('sqlite_no_file');
        }
        if ($path[0] !== '/') {
            $path = rtrim($dir, '/').'/'.$path;
        }
        if (! is_file($path)) {
            unsure('sqlite_file_missing');
        }
        $pdo = new PDO('sqlite:'.$path, null, null, $options);
        $sql = "SELECT 1 FROM \"{$table}\" LIMIT 1";
    } else {
        unsure('unknown_driver');
    }

    $waiting = $pdo->query($sql)->fetchColumn() !== false;
} catch (Throwable) {
    unsure('database_error');
}

if ($waiting) {
    fwrite(STDOUT, "run:jobs_waiting\n");
    exit(RUN_JOBS_WAITING);
}
fwrite(STDOUT, "skip:queue_empty\n");
exit(EMPTY_QUEUE);
