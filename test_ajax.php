<?php
$ch = curl_init('http://localhost/Crediable/sales.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'action' => 'manage_terms',
    'operation' => 'add',
    'title' => 'CURL Title',
    'content' => 'CURL Content'
]);
// We need to pass the session cookie.
// But we don't have it. We can just disable the session check temporarily in sales.php? No, we shouldn't edit sales.php if not needed.
// Instead, I can just create a session in PHP CLI, save the cookie, and pass it.
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';
session_write_close();
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response:\n";
echo $response;
?>
