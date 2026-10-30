<?php
require_once 'config.php';

header('Content-Type: application/json');

$field = $_POST['field'] ?? '';
$value = $_POST['value'] ?? '';

if (empty($field) || empty($value)) {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE $field = ?");
$stmt->bind_param("s", $value);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode(['exists' => $result['count'] > 0]);
$conn->close();
?>