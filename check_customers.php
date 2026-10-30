<?php
require 'config.php';
$res = $conn->query("DESCRIBE customers");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
