<?php
require 'config.php';
$res = $conn->query("DESCRIBE purchases");
while($r = $res->fetch_assoc()) {
    print_r($r);
}
