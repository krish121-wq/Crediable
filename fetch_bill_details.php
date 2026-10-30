<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get user information
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $bill_id = trim($_POST['bill_id'] ?? '');

        if (!$bill_id) {
            throw new Exception("Please provide a valid Bill Number.");
        }

        // Check if user has permission to access this bill from purchases table
        if ($user_type === 'admin') {
            // Admin can access all bills
            $stmt = $conn->prepare("
                SELECT p.id, p.name as supplier_name, p.party_no, p.entry_no, p.date, p.created_at
                FROM purchases p
                WHERE p.entry_no = ?
            ");
            $stmt->bind_param("s", $bill_id);
        } else {
            // Staff can access all bills (assuming no created_by field in purchases)
            $stmt = $conn->prepare("
                SELECT p.id, p.name as supplier_name, p.party_no, p.entry_no, p.date, p.created_at
                FROM purchases p
                WHERE p.entry_no = ?
            ");
            $stmt->bind_param("s", $bill_id);
        }

        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bill) {
            throw new Exception("Purchase bill not found or you don't have permission to access this bill.");
        }

        // Fetch bill items from purchase_items table
        $stmt = $conn->prepare("
            SELECT pi.id, pi.product_id, pi.quantity, pi.purchase_price, pi.amount,
                   p.product_name, p.hsn_code
            FROM purchase_items pi
            JOIN products p ON pi.product_id = p.id
            WHERE pi.purchase_id = ?
            ORDER BY p.product_name
        ");
        $stmt->bind_param("i", $bill['id']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($items)) {
            throw new Exception("No items found for this bill.");
        }
        
        // Group items by product (in case same product appears multiple times)
        $grouped_items = [];
        foreach ($items as $item) {
            $key = $item['product_id'];
            if (isset($grouped_items[$key])) {
                $grouped_items[$key]['quantity'] += $item['quantity'];
                $grouped_items[$key]['amount'] += $item['amount'];
            } else {
                $grouped_items[$key] = $item;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'bill' => $bill,
                'items' => array_values($grouped_items)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Fetch Bill Details Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
