<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'manage_terms';
$_POST['operation'] = 'add';
$_POST['title'] = 'Test';
$_POST['content'] = 'Test Content';
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';

ob_start();
require 'sales.php';
$output = ob_get_clean();
echo "OUTPUT:\n" . $output;
?>
