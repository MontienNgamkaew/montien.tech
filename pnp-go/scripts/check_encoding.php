<?php

require dirname(__DIR__) . '/bootstrap.php';

$db = Database::connection();
$columns = $db->query(
    "SELECT TABLE_NAME, COLUMN_NAME
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'carrequest'
       AND DATA_TYPE IN ('char', 'varchar', 'text', 'enum')
     ORDER BY TABLE_NAME, ORDINAL_POSITION"
)->fetchAll();

$found = 0;
$pattern = '(à|Ó|Ã|Â|�|[?]{4,})';

foreach ($columns as $column) {
    $tableName = $column['TABLE_NAME'];
    $columnName = $column['COLUMN_NAME'];
    $sql = sprintf(
        'SELECT `%s` AS value FROM `%s` WHERE `%s` REGEXP :pattern LIMIT 10',
        str_replace('`', '``', $columnName),
        str_replace('`', '``', $tableName),
        str_replace('`', '``', $columnName)
    );

    $statement = $db->prepare($sql);
    $statement->execute(['pattern' => $pattern]);

    foreach ($statement->fetchAll() as $row) {
        $found++;
        echo $tableName . '.' . $columnName . ' = ' . $row['value'] . PHP_EOL;
    }
}

echo $found === 0 ? 'DB_TEXT_OK' . PHP_EOL : 'FOUND=' . $found . PHP_EOL;
