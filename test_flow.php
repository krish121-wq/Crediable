<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'manage_terms';
$_POST['operation'] = 'add';
$_POST['title'] = 'Test API Flow';
$_POST['content'] = 'Test Content';
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';

// Override exit and header to see what happens
function my_exit() { echo "\n[EXIT CALLED]\n"; }
// Can't redefine exit(), but we can do string replacement on sales.php in memory to debug it.

$code = file_get_contents('sales.php');
$code = str_replace('exit;', 'echo "[EXIT];"; return;', $code);
$code = str_replace('header(', '//header(', $code);
// Remove <?php
$code = preg_replace('/^<\?php/', '', $code);
eval($code);
?>
