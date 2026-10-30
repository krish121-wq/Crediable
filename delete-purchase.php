<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access. Please log in.']);
    http_response_code(401);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    http_response_code(403);
    exit;
}

// Get the purchase ID
$purchase_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($purchase_id <= 0) {
    echo json_encode(['error' => 'Invalid purchase ID']);
    exit;
}

try {
    $conn->begin_transaction();

    // First, get the purchase items to update product quantities
    $query = "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $purchase_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Update product quantities
    while ($row = $result->fetch_assoc()) {
        $update_query = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('ii', $row['quantity'], $row['product_id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $stmt->close();

    // Delete purchase items
    $query = "DELETE FROM purchase_items WHERE purchase_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $purchase_id);
    $stmt->execute();
    $stmt->close();

    // Delete the purchase
    $query = "DELETE FROM purchases WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $purchase_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("No purchase found with ID: " . $purchase_id);
    }
    
    $stmt->close();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting purchase: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to delete purchase: ' . $e->getMessage()]);
    http_response_code(500);
}

$conn->close();