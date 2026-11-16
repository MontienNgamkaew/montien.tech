<?php
declare(strict_types=1);

// Install OUTSIDE public_html. The dedicated CI key is forced to this command.
// No application bootstrap, migrations, git checkout, rsync, or deletions.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
umask(0077);
const DEPLOY_FILES = ['api/update_profile.php', 'pnpman/api/manage_personnel.php', 'pnpman/api/update_settings.php', 'pnp-academix/teacher/submit.php', 'pnp-academix/admin/settings.php'];

function readJson(string $path): array
{
    return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
function writeJson(string $path, array $value): void
{
    $temp = $path . '.' . bin2hex(random_bytes(8)) . '.pending';
    if (file_put_contents($temp, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false || !rename($temp, $path)) {
        throw new RuntimeException('Cannot save private deployment record.');
    }
}
function makeDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot prepare private directory.');
    }
}
function hashes(string $root): array
{
    $result = [];
    foreach (DEPLOY_FILES as $relative) {
        $file = $root . '/' . $relative;
        if (!is_file($file) || realpath($file) !== $file || is_link($file)) {
            throw new RuntimeException('Required code file missing or linked: ' . $relative);
        }
        $result[$relative] = hash_file('sha256', $file);
    }
    return $result;
}
function currentState(array $config, string $stateFile): array
{
    $state = is_file($stateFile) ? readJson($stateFile) : ['ref' => $config['initial_ref'], 'hashes' => $config['initial_hashes'], 'release' => null];
    if (hashes($config['webroot']) !== $state['hashes']) {
        throw new RuntimeException('Live code has drifted. Stop and review; nothing overwritten.');
    }
    return $state;
}
function runCommand(array $command, string $log): void
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Command failed; inspect the private release log.');
    }
}
function validateRelease(array $config, string $directory, array $state): array
{
    $manifest = readJson($directory . '/payload/manifest.json');
    if (($manifest['format'] ?? null) !== 1 || !preg_match('/^[a-f0-9]{40}$/D', $manifest['commit'] ?? '') || ($manifest['expected_ref'] ?? '') !== $state['ref']) {
        throw new RuntimeException('Release format or expected current version does not match.');
    }
    if (array_keys($manifest['hashes'] ?? []) !== DEPLOY_FILES || hashes($directory . '/payload') !== $manifest['hashes']) {
        throw new RuntimeException('Payload file allowlist/checksums do not match.');
    }
    foreach (DEPLOY_FILES as $relative) {
        if (!is_writable(dirname($config['webroot'] . '/' . $relative))) {
            throw new RuntimeException('Destination is not writable: ' . $relative);
        }
        runCommand([PHP_BINARY, '-l', $directory . '/payload/' . $relative], $directory . '/lint.log');
    }
    return $manifest;
}

/** Prepare all files before replacing any, and restore originals on an apply failure. */
function installFiles(string $root, string $source, string $private, array $expected, array $desired): void
{
    if (hashes($root) !== $expected || hashes($source) !== $desired) {
        throw new RuntimeException('Code changed before install. Nothing overwritten.');
    }
    makeDirectory($private);
    foreach (DEPLOY_FILES as $relative) {
        foreach (['before', 'next'] as $kind) {
            makeDirectory(dirname($private . '/' . $kind . '/' . $relative));
        }
        if (!copy($root . '/' . $relative, $private . '/before/' . $relative) || !copy($source . '/' . $relative, $private . '/next/' . $relative)) {
            throw new RuntimeException('Cannot prepare code copies. Nothing installed.');
        }
        chmod($private . '/next/' . $relative, fileperms($root . '/' . $relative) & 0777);
    }
    if (hashes($private . '/before') !== $expected || hashes($private . '/next') !== $desired) {
        throw new RuntimeException('Prepared copies failed checksum verification.');
    }
    $installed = [];
    try {
        foreach (DEPLOY_FILES as $relative) {
            if (hash_file('sha256', $root . '/' . $relative) !== $expected[$relative]) {
                throw new RuntimeException('Live code changed during install.');
            }
            if (!rename($private . '/next/' . $relative, $root . '/' . $relative)) {
                throw new RuntimeException('Atomic file replacement failed.');
            }
            $installed[] = $relative;
        }
        if (hashes($root) !== $desired) {
            throw new RuntimeException('Post-install checksum failed.');
        }
    } catch (Throwable $error) {
        foreach (array_reverse($installed) as $relative) {
            if (hash_file('sha256', $root . '/' . $relative) !== $desired[$relative]) {
                throw new RuntimeException('Concurrent code edit detected; recovery requires review.');
            }
            // Keep original recovery copies: rename a fresh private copy into place.
            $recovery = $private . '/recovery-' . bin2hex(random_bytes(8));
            if (!copy($private . '/before/' . $relative, $recovery)) {
                throw new RuntimeException('Cannot prepare recovery copy.');
            }
            chmod($recovery, fileperms($root . '/' . $relative) & 0777);
            if (!rename($recovery, $root . '/' . $relative)) {
                throw new RuntimeException('Recovery needs administrator intervention.');
            }
        }
        throw $error;
    }
}

function installAndRecord(string $root, string $source, string $private, array $expected, array $desired, string $stateFile, array $nextState): void
{
    installFiles($root, $source, $private, $expected, $desired);
    try {
        writeJson($stateFile, $nextState);
    } catch (Throwable $error) {
        // If recording the version fails, do not leave new code behind with an old state.
        installFiles($root, $private . '/before', $private . '/state-recovery', $desired, $expected);
        throw $error;
    }
}

try {
    $config = readJson(__DIR__ . '/config.json');
    foreach (['webroot', 'releases', 'backups'] as $key) {
        if (realpath($config[$key]) !== $config[$key]) {
            throw new RuntimeException('Configured directories must exist and be canonical.');
        }
    }
    foreach ([__DIR__, $config['releases'], $config['backups']] as $privateRoot) {
        if ($privateRoot === $config['webroot'] || str_starts_with($privateRoot, $config['webroot'] . '/')) {
            throw new RuntimeException('Control/releases/backups must be outside webroot.');
        }
    }
    $command = (string)getenv('SSH_ORIGINAL_COMMAND');
    if (!preg_match('/^pnp-deploy (status|upload|check|backup|deploy|rollback)(?: ([A-Za-z0-9][A-Za-z0-9_-]{0,90}))?$/D', $command, $match)) {
        throw new RuntimeException('Only the PNP deployment protocol is permitted.');
    }
    $action = $match[1];
    $id = $match[2] ?? '';
    if (($action === 'status') !== ($id === '')) {
        throw new RuntimeException('Invalid release identifier.');
    }
    $lock = fopen(__DIR__ . '/deployment.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Cannot lock deployment.');
    }
    $stateFile = __DIR__ . '/state.json';
    $state = currentState($config, $stateFile);
    if ($action === 'status') {
        echo json_encode(['ok' => true, 'deployed_ref' => $state['ref'], 'release' => $state['release'], 'hashes' => $state['hashes']]) . PHP_EOL;
        exit;
    }
    $directory = $config['releases'] . '/' . $id;
    if ($action === 'upload') {
        if (file_exists($directory) || !mkdir($directory, 0700)) {
            throw new RuntimeException('Release identifier already exists; never overwrite a staged release.');
        }
        $archivePath = $directory . '/payload.tar';
        $output = fopen($archivePath, 'x');
        if (!$output) {
            throw new RuntimeException('Cannot create payload archive.');
        }
        $size = stream_copy_to_stream(STDIN, $output, 4194305);
        fclose($output);
        if (!$size || $size > 4194304) {
            throw new RuntimeException('Payload exceeds 4 MiB limit or is empty.');
        }
        $archive = new PharData($archivePath);
        $seen = [];
        $total = 0;
        foreach (new RecursiveIteratorIterator($archive) as $name => $entry) {
            $prefix = 'phar://' . $archivePath . '/';
            if (!str_starts_with($name, $prefix)) {
                throw new RuntimeException('Invalid archive entry.');
            }
            $relative = substr($name, strlen($prefix));
            if (!in_array($relative, array_merge(DEPLOY_FILES, ['manifest.json']), true) || !$entry->isFile() || $entry->isLink()) {
                throw new RuntimeException('Archive contains an unapproved file.');
            }
            $total += $entry->getSize();
            if ($total > 4194304) {
                throw new RuntimeException('Unpacked payload exceeds limit.');
            }
            makeDirectory(dirname($directory . '/payload/' . $relative));
            if (file_put_contents($directory . '/payload/' . $relative, $entry->getContent()) !== $entry->getSize()) {
                throw new RuntimeException('Cannot stage payload.');
            }
            $seen[] = $relative;
        }
        $wanted = array_merge(DEPLOY_FILES, ['manifest.json']);
        sort($seen);
        sort($wanted);
        if ($seen !== $wanted) {
            throw new RuntimeException('Archive must contain exactly five PHP files and a manifest.');
        }
    }
    if (!is_dir($directory) || realpath($directory) !== $directory) {
        throw new RuntimeException('Release missing or linked.');
    }
    if ($action === 'rollback') {
        if ($state['release'] !== $id) {
            throw new RuntimeException('Rollback is allowed only for the currently deployed release.');
        }
        $previous = readJson($directory . '/previous-state.json');
        $beforeEnv = hash_file('sha256', $config['webroot'] . '/.env');
        installAndRecord($config['webroot'], $directory . '/installation/before', $directory . '/rollback-' . bin2hex(random_bytes(8)), $state['hashes'], $previous['hashes'], $stateFile, $previous);
        if (hash_file('sha256', $config['webroot'] . '/.env') !== $beforeEnv) {
            throw new RuntimeException('Environment changed concurrently; investigate.');
        }
        echo json_encode(['ok' => true, 'action' => 'rollback', 'deployed_ref' => $previous['ref'], 'release' => $previous['release']]) . PHP_EOL;
        exit;
    }
    $manifest = validateRelease($config, $directory, $state);
    if ($action === 'backup') {
        $destination = $config['backups'] . '/' . $id;
        runCommand([PHP_BINARY, __DIR__ . '/backup.php', $config['webroot'], $destination], $directory . '/backup.log');
        $backupManifest = readJson($destination . '/manifest.json');
        if (($backupManifest['status'] ?? '') !== 'archive_checks_passed_restore_not_tested') {
            throw new RuntimeException('Backup did not complete.');
        }
        foreach (file($destination . '/SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (!preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.-]+)$/D', $line, $parts) || hash_file('sha256', $destination . '/' . $parts[2]) !== $parts[1]) {
                throw new RuntimeException('Backup checksum failed.');
            }
        }
        writeJson($directory . '/backup-marker.json', ['state' => $state, 'created' => time(), 'path' => $destination]);
    }
    if ($action === 'deploy') {
        $marker = readJson($directory . '/backup-marker.json');
        if ($marker['state'] !== $state || time() - $marker['created'] > 1800 || file_exists($directory . '/installation')) {
            throw new RuntimeException('A fresh matching backup and an unused installation are required.');
        }
        $beforeEnv = hash_file('sha256', $config['webroot'] . '/.env');
        writeJson($directory . '/previous-state.json', $state);
        installAndRecord($config['webroot'], $directory . '/payload', $directory . '/installation', $state['hashes'], $manifest['hashes'], $stateFile, ['ref' => $manifest['commit'], 'hashes' => $manifest['hashes'], 'release' => $id]);
        if (hash_file('sha256', $config['webroot'] . '/.env') !== $beforeEnv) {
            throw new RuntimeException('Environment changed concurrently; investigate.');
        }
    }
    echo json_encode(['ok' => true, 'action' => $action, 'release' => $id, 'commit' => $manifest['commit'], 'files' => count(DEPLOY_FILES), 'deployed' => $action === 'deploy']) . PHP_EOL;
} catch (Throwable $error) {
    // No SQL, credentials, user paths or backup contents in CI logs.
    fwrite(STDERR, 'Deployment stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
