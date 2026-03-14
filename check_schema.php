<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$tables = ['empresas', 'estabelecimentos', 'socios'];
foreach ($tables as $table) {
    echo "--- Table: $table ---\n";
    try {
        $q = $db->query("SHOW CREATE TABLE $table");
        $r = $q->fetch();
        echo $r['Create Table'] . "\n\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}
