<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$url = 'http://localhost/Crediable/sales.php';
$data = array(
    'action' => 'manage_terms',
    'operation' => 'add',
    'title' => 'Test Term',
    'content' => 'Test Content'
);

$options = array(
    'http' => array(
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    )
);

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Response:\n";
var_dump($result);
?>
