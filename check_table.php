<?php
require 'config.php';
$res = $conn->query("SELECT * FROM terms_conditions");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
