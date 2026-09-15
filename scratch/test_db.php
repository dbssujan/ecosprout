<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
$pdo = Database::getConnection();
$tables = ['users','categories','plants','services','service_bookings','workshops','workshop_registrations','orders','order_items','inquiries','blog_posts'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $rows = $pdo->query("DESCRIBE `$t`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "  {$row['Field']} | {$row['Type']} | Null:{$row['Null']} | Key:{$row['Key']} | Default:" . ($row['Default'] ?? 'NULL') . "\n";
    }
    echo "\n";
}

