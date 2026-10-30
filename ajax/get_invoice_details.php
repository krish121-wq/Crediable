<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

$invoice_id = trim($_GET['invoice_id'] ?? '');

if (empty($invoice_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice ID is required']);
    exit();
}

try {
    
    $stmt = $conn->prepare("
        SELECT id as invoice_id, customer_name, customer_phone, customer_address, 
                total_amount
        FROM invoices 
        WHERE id = ?
    ");
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
        exit();
    }
    
   
    $stmt = $conn->prepare("
        SELECT s.id, s.product_id, s.quantity, s.price_per_piece, s.discount_amount, 
               s.total_price, p.product_name, p.hsn_code, p.gst_percentage
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE s.invoice_id = ?
        ORDER BY s.id
    ");
    $stmt->bind_param("s", $invoice_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'invoice' => $invoice,
            'items' => $items
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
