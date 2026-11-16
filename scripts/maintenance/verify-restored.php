<?php
declare(strict_types=1);

// Read-only verification of an isolated restore; never load application config.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
if ($argc !== 7) {
    fwrite(STDERR, "Usage: php verify-restored.php SOCKET DATADIR BACKUP_DIR RESTORED_WEBROOT RESTORED_UPLOADS EXPECTED_COUNTS_JSON\n");
    exit(1);
}
[$script, $socket, $dataDir, $backup, $webroot, $uploads, $expectedFile] = $argv;
foreach ([$dataDir, $backup, $webroot, $uploads] as $directory) {
    if (!is_dir($directory)) {
        throw new RuntimeException('Required directory missing.');
    }
}
$pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (realpath((string)$pdo->query('SELECT @@datadir')->fetchColumn()) !== realpath($dataDir)
    || (int)$pdo->query('SELECT @@skip_networking')->fetchColumn() !== 1) {
    throw new RuntimeException('Refusing to inspect a server outside the isolated datadir or with TCP enabled.');
}
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();
$manifest = json_decode(file_get_contents($backup . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$expected = json_decode(file_get_contents($expectedFile), true, 512, JSON_THROW_ON_ERROR);
$quote = static fn(string $identifier): string => '`' . str_replace('`', '``', $identifier) . '`';
$report = ['server_version' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'databases' => [], 'file_references' => []];
foreach ($manifest['databases'] as $label => $database) {
    $name = $database['database'];
    $tables = [];
    foreach ($database['tables'] as $table) {
        if ($table['TABLE_TYPE'] !== 'BASE TABLE') {
            continue;
        }
        $tableName = $table['TABLE_NAME'];
        $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $quote($name) . '.' . $quote($tableName))->fetchColumn();
        $expectedCount = $expected[$label][$tableName] ?? null;
        $tables[$tableName] = ['rows' => $count, 'dump_rows' => $expectedCount, 'match' => $expectedCount === $count];
    }
    $query = $pdo->prepare('SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION');
    $query->execute([$name]);
    $constraints = [];
    foreach ($query as $fk) {
        $constraints[$fk['TABLE_NAME'] . '/' . $fk['CONSTRAINT_NAME']][] = $fk;
    }
    $integrity = [];
    foreach ($constraints as $key => $columns) {
        $join = $nonNull = [];
        foreach ($columns as $column) {
            $join[] = 'c.' . $quote($column['COLUMN_NAME']) . '=p.' . $quote($column['REFERENCED_COLUMN_NAME']);
            $nonNull[] = 'c.' . $quote($column['COLUMN_NAME']) . ' IS NOT NULL';
        }
        $first = $columns[0];
        $sql = 'SELECT COUNT(*) FROM ' . $quote($name) . '.' . $quote($first['TABLE_NAME']) . ' c LEFT JOIN ' . $quote($name) . '.' . $quote($first['REFERENCED_TABLE_NAME']) . ' p ON ' . implode(' AND ', $join) . ' WHERE ' . implode(' AND ', $nonNull) . ' AND p.' . $quote($first['REFERENCED_COLUMN_NAME']) . ' IS NULL';
        $integrity[$key] = (int)$pdo->query($sql)->fetchColumn();
    }
    $report['databases'][$label] = ['tables' => $tables, 'foreign_key_orphans' => $integrity];
}

$names = array_map(static fn(array $entry): string => $entry['database'], $manifest['databases']);
$inspect = static function (string $label, string $sql, array $roots) use ($pdo, &$report): void {
    $counts = ['references' => 0, 'found' => 0, 'missing' => 0, 'external' => 0, 'unsafe' => 0, 'found_by_location' => []];
    foreach ($pdo->query($sql) as $row) {
        $path = (string)$row[0];
        if ($path === '') {
            continue;
        }
        $counts['references']++;
        if (preg_match('~^(https?://|data:)~i', $path)) {
            $counts['external']++;
            continue;
        }
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            $counts['unsafe']++;
            continue;
        }
        $found = false;
        foreach ($roots as $location => $root) {
            if (is_file($root . '/' . ltrim($path, '/'))) {
                $counts['found']++;
                $counts['found_by_location'][$location] = ($counts['found_by_location'][$location] ?? 0) + 1;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $counts['missing']++;
        }
    }
    $report['file_references'][$label] = $counts;
};
$inspect('academix_submissions', 'SELECT file_path FROM ' . $quote($names['academix']) . '.submissions WHERE file_path IS NOT NULL', ['persistent' => $uploads . '/pnp-academix', 'legacy' => $webroot . '/pnp-academix']);
$inspect('academix_branding', "SELECT meta_value FROM " . $quote($names['academix']) . ".branding_settings WHERE meta_key='logo_path'", ['persistent' => $uploads . '/pnp-academix', 'legacy' => $webroot . '/pnp-academix']);
$inspect('portal_and_man_avatars', 'SELECT avatar_path FROM ' . $quote($names['portal']) . '.users WHERE avatar_path IS NOT NULL', ['portal' => $webroot, 'man_legacy' => $webroot . '/pnpman', 'man_persistent' => $uploads . '/pnpman']);
$inspect('man_branding', 'SELECT logo_path FROM ' . $quote($names['portal']) . '.college_settings WHERE logo_path IS NOT NULL', ['persistent' => $uploads . '/pnpman', 'legacy' => $webroot . '/pnpman']);
foreach (['pdf_path', 'report_photo_path', 'report_photo_path_2'] as $column) {
    $inspect('pnpgo_' . $column, 'SELECT ' . $quote($column) . ' FROM ' . $quote($names['pnpgo']) . '.requisitions WHERE ' . $quote($column) . ' IS NOT NULL', ['storage' => $webroot . '/pnp-go']);
}
$inspect('pnpgo_signatures', 'SELECT signature_path FROM ' . $quote($names['pnpgo']) . '.users WHERE signature_path IS NOT NULL', ['storage' => $webroot . '/pnp-go']);
$pdo->rollBack();
$databasePassed = true;
foreach ($report['databases'] as $database) {
    foreach ($database['tables'] as $table) {
        $databasePassed = $databasePassed && $table['match'];
    }
    $databasePassed = $databasePassed && array_sum($database['foreign_key_orphans']) === 0;
}
$filesPassed = true;
foreach ($report['file_references'] as $counts) {
    $filesPassed = $filesPassed && $counts['missing'] === 0 && $counts['unsafe'] === 0;
}
$report['database_integrity_passed'] = $databasePassed;
$report['file_references_complete'] = $filesPassed;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!$databasePassed ? 2 : (!$filesPassed ? 3 : 0));
