<?php
declare(strict_types=1);

// CLI only. Never load application database/bootstrap files: they run migrations.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
umask(0077);
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php backup.php REPO_ROOT NEW_BACKUP_DIRECTORY\n");
    exit(1);
}
$root = realpath($argv[1]);
$parent = realpath(dirname($argv[2]));
if (!$root || !$parent || !is_file($root . '/.env') || file_exists($argv[2])) {
    throw new RuntimeException('Require existing repo/.env, existing backup parent, and a NEW destination.');
}
$destination = $parent . '/' . basename($argv[2]);
require_once $root . '/api/env.php';
$uploads = realpath((string)env('UPLOAD_BASE_PATH', $root . '/pnp_uploads'));
if (!$uploads) {
    throw new RuntimeException('Upload storage does not exist; backup aborted.');
}
foreach ([$root, $uploads] as $source) {
    if ($destination === $source || str_starts_with($destination, $source . '/')) {
        throw new RuntimeException('Backup must be outside source directories.');
    }
}
if (!mkdir($destination, 0700)) {
    throw new RuntimeException('Cannot create new backup directory.');
}

function runBackupCommand(array $command, string $output, string $errors, ?array $environment = null): void
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $errors, 'a']], $pipes, null, $environment);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Backup command failed; inspect protected errors.log. Do not use this set for restore.');
    }
}

$manifest = [
    'started_utc' => gmdate(DATE_ATOM),
    'status' => 'incomplete',
    'repo_root' => $root,
    'uploads_root' => $uploads,
    'consistency' => 'Live backup: separate DB transactions and file archive times; not one atomic application snapshot.',
    'restore_tested' => false,
    'databases' => [],
];
file_put_contents($destination . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
try {
    runBackupCommand(['git', '-C', $root, 'rev-parse', 'HEAD'], $destination . '/commit.txt', $destination . '/errors.log');
    runBackupCommand(['git', '-C', $root, 'status', '--short', '--branch'], $destination . '/git-status.txt', $destination . '/errors.log');
    $databases = [
        'portal' => ['DB_', 'u651170081_pnp_portal'],
        'pnpgo' => ['PNPGO_DB_', 'u651170081_pnpgo'],
        'academix' => ['ACADEMIX_DB_', 'u651170081_pnp_academix'],
    ];
    foreach ($databases as $label => [$prefix, $defaultName]) {
        $name = (string)env($prefix . 'NAME', $defaultName);
        $user = (string)env($prefix . 'USER', $defaultName);
        $host = (string)env($prefix . 'HOST', 'localhost');
        $port = (int)env($prefix . 'PORT', 3306);
        $password = (string)env($prefix . 'PASS', '');
        if ($password === '' || !preg_match('/^[A-Za-z0-9_]+$/D', $name)) {
            throw new RuntimeException('Database configuration missing or unsupported for ' . $label);
        }
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME');
        $stmt->execute([$name]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$tables) {
            throw new RuntimeException('No database tables found for ' . $label);
        }
        // Protected option file: hosting wrappers may discard MYSQL_PWD.
        // Keep it inside the private backup (already contains .env); never print it.
        $escapeOption = static fn(string $value): string => '"' . str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $value) . '"';
        $optionFile = $destination . '/' . $label . '-client.cnf';
        file_put_contents($optionFile, "[client]\npassword=" . $escapeOption($password) . "\n");
        chmod($optionFile, 0600);
        // Match PDO's localhost socket behavior; Hostinger TCP localhost may resolve to ::1.
        $protocol = $host === 'localhost' ? 'SOCKET' : 'TCP';
        $connectionOptions = $host === 'localhost'
            ? ['--socket=' . (ini_get('pdo_mysql.default_socket') ?: '/var/lib/mysql/mysql.sock')]
            : ['--port=' . $port];
        runBackupCommand(array_merge(['mysqldump', '--defaults-file=' . $optionFile, '--protocol=' . $protocol, '--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob', '--default-character-set=utf8mb4', '--routines', '--events', '--triggers', '--host=' . $host, '--user=' . $user], $connectionOptions, ['--databases', $name]), $destination . '/' . $label . '.sql', $destination . '/errors.log');
        unset($password, $pdo);
        if (filesize($destination . '/' . $label . '.sql') < 100) {
            throw new RuntimeException('Dump unexpectedly small for ' . $label);
        }
        $manifest['databases'][$label] = ['database' => $name, 'tables' => $tables];
    }
    runBackupCommand(['tar', '-czf', $destination . '/webroot.tar.gz', '-C', dirname($root), basename($root)], $destination . '/archive-webroot.log', $destination . '/errors.log');
    runBackupCommand(['tar', '-czf', $destination . '/persistent-uploads.tar.gz', '-C', dirname($uploads), basename($uploads)], $destination . '/archive-uploads.log', $destination . '/errors.log');
    foreach (['webroot.tar.gz', 'persistent-uploads.tar.gz'] as $archive) {
        runBackupCommand(['gzip', '-t', $destination . '/' . $archive], $destination . '/verify-' . $archive . '.log', $destination . '/errors.log');
        runBackupCommand(['tar', '-tzf', $destination . '/' . $archive], $destination . '/' . $archive . '.contents.txt', $destination . '/errors.log');
    }
    $manifest['status'] = 'archive_checks_passed_restore_not_tested';
    $manifest['finished_utc'] = gmdate(DATE_ATOM);
    file_put_contents($destination . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $checksums = '';
    foreach (glob($destination . '/*') as $file) {
        if (is_file($file)) {
            $checksums .= hash_file('sha256', $file) . '  ' . basename($file) . "\n";
        }
    }
    file_put_contents($destination . '/SHA256SUMS', $checksums);
    echo "Backup archives verified: $destination\nRestore and DB/file reference checks remain required.\n";
} catch (Throwable $error) {
    // Keep partial files for diagnosis; never delete source data or partial backups.
    fwrite(STDERR, "Backup incomplete. Protected destination: $destination\n");
    exit(1);
}
