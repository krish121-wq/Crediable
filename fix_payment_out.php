<?php
$content = file_get_contents('payment_out.php');

$content = str_replace('made_payments.php', 'payment_out.php', $content);
$content = str_replace('customer balance will be reversed', 'supplier balance will be recalculated', $content);
$content = str_replace('valid customer, payment method', 'valid supplier, payment method', $content);

file_put_contents('payment_out.php', $content);
echo "Fixed payment_out.php\n";
