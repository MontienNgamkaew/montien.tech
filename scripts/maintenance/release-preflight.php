<?php
declare(strict_types=1);

// Read-only release check; never loads application configuration or database code.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php release-preflight.php WEBROOT RELEASE_DIRECTORY\n");
    exit(1);
}
$root = realpath($argv[1]);
$release = realpath($argv[2]);
if (!$root || !$release || $root === $release || str_starts_with($release, $root . '/')) {
    throw new RuntimeException('Release directory must be outside the webroot.');
}
$allowed = ['api/update_profile.php', 'pnpman/api/manage_personnel.php', 'pnpman/api/update_settings.php', 'pnp-academix/teacher/submit.php', 'pnp-academix/admin/settings.php'];
$manifest = json_decode(file_get_contents($release . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$paths = array_column($manifest['files'], 'path');
sort($allowed);
sort($paths);
if ($allowed !== $paths || $manifest['database_changes'] !== false || $manifest['env_changes'] !== false || $manifest['upload_changes'] !== false) {
    throw new RuntimeException('Manifest exceeds the approved five-file release scope.');
}
$passed = true;
$results = [];
foreach ($manifest['files'] as $file) {
    $path = $file['path'];
    $current = $root . '/' . $path;
    $payload = $release . '/payload/' . $path;
    $rollback = $release . '/rollback/' . $path;
    foreach ([$current, $payload, $rollback] as $candidate) {
        $expectedBase = $candidate === $current ? $root : $release;
        $resolved = realpath($candidate);
        if (!$resolved || !is_file($resolved) || is_link($candidate) || !str_starts_with($resolved, $expectedBase . '/')) {
            throw new RuntimeException('Missing file or path outside expected root: ' . $path);
        }
    }
    $row = [
        'path' => $path,
        'production_matches_base' => hash_file('sha256', $current) === $file['before_sha256'],
        'payload_matches_manifest' => hash_file('sha256', $payload) === $file['after_sha256'],
        'rollback_matches_base' => hash_file('sha256', $rollback) === $file['before_sha256'],
    ];
    $process = proc_open([PHP_BINARY, '-l', $payload], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start PHP syntax check.');
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $row['php_syntax_passed'] = proc_close($process) === 0;
    $passed = $passed && $row['production_matches_base'] && $row['payload_matches_manifest'] && $row['rollback_matches_base'] && $row['php_syntax_passed'];
    $results[] = $row;
}
echo json_encode(['release' => $manifest['release'], 'php_version' => PHP_VERSION, 'preflight_passed' => $passed, 'deployed' => false, 'files' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($passed ? 0 : 2);
