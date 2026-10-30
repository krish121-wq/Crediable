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
        $debit_note_no = trim($_POST['debit_note_no'] ?? '');
        
        if (empty($debit_note_no)) {
            throw new Exception("Please provide a valid Debit Note Number.");
        }
        
        // First, try to fetch from purchase_return_records table
        // This is a simplified approach - in a real system, you'd have a dedicated debit_notes table
        $stmt = $conn->prepare("
            SELECT 
                prr.original_bill_no,
                prr.return_note,
                prr.created_at,
                prr.created_by,
                p.name as supplier_name,
                p.entry_no as invoice_no,
                p.date as purchase_date,
                CASE 
                    WHEN admin_users.id IS NOT NULL THEN 'Admin'
                    WHEN staff_users.name IS NOT NULL THEN staff_users.name
                    ELSE 'Unknown'
                END AS created_by_name
            FROM purchase_return_records prr
            LEFT JOIN purchases p ON prr.original_bill_no = p.entry_no
            LEFT JOIN users admin_users ON prr.created_by = admin_users.id
            LEFT JOIN create_users staff_users ON prr.created_by = staff_users.id
            WHERE prr.original_bill_no = ? OR prr.id = ?
            LIMIT 1
        ");
        
        // Try to match by debit note number (could be bill number or return ID)
        $stmt->bind_param("ss", $debit_note_no, $debit_note_no);
        $stmt->execute();
        $debit_note = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$debit_note) {
            throw new Exception("Debit note not found. Please check the debit note number.");
        }
        
        // Fetch return items
        $stmt = $conn->prepare("
            SELECT 
                prr.product_id,
                prr.return_quantity,
                prr.unit_price,
                prr.return_amount,
                prr.return_note as reason,
                prod.product_name,
                prod.hsn_code,
                pi.quantity as quantity_purchased,
                pi.purchase_price,
                pi.amount as original_amount
            FROM purchase_return_records prr
            LEFT JOIN products prod ON prr.product_id = prod.id
            LEFT JOIN purchase_items pi ON prr.product_id = pi.product_id 
                AND pi.purchase_id = (SELECT id FROM purchases WHERE entry_no = prr.original_bill_no LIMIT 1)
            WHERE prr.original_bill_no = ?
            ORDER BY prr.id
        ");
        $stmt->bind_param("s", $debit_note['original_bill_no']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Format the response data
        $response_data = [
            'supplier_name' => $debit_note['supplier_name'] ?? 'N/A',
            'return_no' => $debit_note['original_bill_no'] ?? 'N/A',
            'debit_note_no' => $debit_note_no,
            'return_date' => date('Y-m-d', strtotime($debit_note['created_at'])),
            'invoice_no' => $debit_note['invoice_no'] ?? 'N/A',
            'refund_type' => 'Product Return',
            'return_status' => 'Processed',
            'notes' => $debit_note['return_note'] ?? '',
            'items' => []
        ];
        
        // Format items
        foreach ($items as $item) {
            $response_data['items'][] = [
                'product_name' => $item['product_name'] ?? 'N/A',
                'batch_no' => 'N/A', // Not stored in current system
                'expiry_date' => 'N/A', // Not stored in current system
                'quantity_purchased' => $item['quantity_purchased'] ?? 0,
                'unit_price' => number_format($item['unit_price'] ?? 0, 2),
                'gst_rate' => '0.00', // Not stored in current system
                'return_quantity' => $item['return_quantity'] ?? 0,
                'reason' => $item['reason'] ?? 'Return',
                'amount' => number_format($item['return_amount'] ?? 0, 2)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>
