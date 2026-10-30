<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'config.php';

$title = 'Test Title';
$content = 'Test Content';
$created_by_id = 1;

$stmt = $conn->prepare("INSERT INTO terms_conditions (title, content, created_by) VALUES (?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("ssi", $title, $content, $created_by_id);
    if ($stmt->execute()) {
        echo "SUCCESS: ID " . $stmt->insert_id;
    } else {
        echo "EXECUTE ERROR: " . $stmt->error;
    }
} else {
    echo "PREPARE ERROR: " . $conn->error;
}
?>
