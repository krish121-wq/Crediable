<?php
require 'config.php';
$res = $conn->query("SHOW TABLES");
while($r = $res->fetch_array()) {
    echo $r[0]."\n";
}
echo "--- SUPPLIERS SCHEMA ---\n";
$res = $conn->query("DESCRIBE suppliers");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
