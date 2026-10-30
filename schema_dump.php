<?php
require_once 'config.php';
$tables = ['invoices', 'products'];
foreach($tables as $table) {
    echo "TABLE $table\n";
    $res = $conn->query("DESCRIBE $table");
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "\n";
}
// Find item tables
$res = $conn->query("SHOW TABLES LIKE '%items%'");
while($row = $res->fetch_row()) {
    $t = $row[0];
    echo "TABLE $t\n";
    $r2 = $conn->query("DESCRIBE $t");
    while($row2 = $r2->fetch_assoc()) {
        echo $row2['Field'] . " - " . $row2['Type'] . "\n";
    }
    echo "\n";
}
?>
