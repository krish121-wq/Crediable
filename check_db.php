<?php
require 'config.php';
$res = $conn->query('SELECT id, product_name, quantity FROM products');
$data = $res->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>
