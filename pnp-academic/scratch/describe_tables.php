<?php
require 'config.php';

echo "=== TABLES ===\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "- $table\n";
    $columns = $pdo->query("DESCRIBE `$table`")->fetchAll();
    foreach ($columns as $col) {
        echo "  * {$col['Field']} ({$col['Type']}) - Null: {$col['Null']}, Key: {$col['Key']}, Default: {$col['Default']}\n";
    }
}
