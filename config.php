<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Replace with your MySQL username
define('DB_PASS', ''); // Replace with your MySQL password
define('DB_NAME', 'u463638053_credible_bill');

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
?>