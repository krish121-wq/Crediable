<?php
require 'config.php';
$res = $conn->query("SELECT * FROM payment_receipts");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
$res = $conn->query("SELECT * FROM received_payments WHERE receipt_id IS NOT NULL");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
echo "Done.\n";
