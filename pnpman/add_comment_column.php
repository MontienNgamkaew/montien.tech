<?php
$host = '127.0.0.1';
$db   = 'pnpman_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if comment column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM assignments LIKE 'comment'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE assignments ADD COLUMN comment VARCHAR(255) DEFAULT NULL");
        echo "Added 'comment' column to assignments table.\n";
    } else {
        echo "'comment' column already exists.\n";
    }
    
    echo "Done!\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
