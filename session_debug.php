<?php
// Test session and login status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<h2>Session Debug</h2>";
echo "<h3>Current Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session Status:</h3>";
echo "Session Status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Set test session for admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'admin';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo "<div class='success'>✅ Test admin session created</div>";
} else {
    echo "<div class='info'>ℹ️ Session already exists</div>";
}

// Force refresh CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
echo "<div style='color: orange;'>🔄 CSRF token refreshed: " . $_SESSION['csrf_token'] . "</div>";

echo "<h3>Links:</h3>";
echo "<a href='create-user.php'>Go to Staff Management</a><br>";
echo "<a href='debug_create_user.php'>Go to Debug Page</a><br>";
echo "<a href='login.php'>Go to Login</a><br>";

echo "</body></html>";
?>
