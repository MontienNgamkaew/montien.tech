<?php
require_once 'c:/xampp/htdocs/pnp-academic/config.php';
$depts = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != ''")->fetchAll(PDO::FETCH_COLUMN);
echo "Departments:\n";
print_r($depts);
