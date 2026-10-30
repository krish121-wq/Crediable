<?php
session_start();
require_once 'config.php';


header('Content-Type: application/json');

$purchase_bill_id = $_POST['purchase_bill_id'] ?? '';
$user_id = (int)$_SESSION['user_id'];

if (empty($purchase_bill_id)) {
    echo json_encode(['success' => false, 'message' => 'Purchase Bill ID is required']);
    exit;
}

try {
    // Fetch purchase bill
    $query = "SELECT id, name AS supplier_name, entry_no, date, party_no, total_amount, products
              FROM purchase_bills WHERE id = ? AND created_at IS NOT NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $purchase_bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill = $result->fetch_assoc();
    $stmt->close();

    if (!$bill) {
        echo json_encode(['success' => false, 'message' => 'Purchase bill not found']);
        exit;
    }

    // Parse products JSON
    $products = json_decode($bill['products'], true) ?: [];
    $items = [];

    if (empty($products)) {
        // Fallback to legacy fields
        if ($bill['product'] && $bill['qty'] && $bill['amount']) {
            $items[] = [
                'id' => $bill['id'],
                'product_name' => $bill['product'],
                'batch_no' => $bill['piece'] ?: '',
                'expiry_date' => '',
                'quantity' => $bill['qty'] ?: 0,
                'unit_price' => ($bill['qty'] > 0) ? $bill['amount'] / $bill['qty'] : 0,
                'gst_rate' => 0,
                'hsn_code' => ''
            ];
        }
    } else {
        foreach ($products as $item) {
            // Fetch HSN code
            $query = "SELECT hsn_code FROM products WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $item['product_id']);
            $stmt->execute();
            $hsn = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $items[] = [
                'id' => $item['product_id'] ?? $bill['id'],
                'product_name' => $item['product_name'] ?? '',
                'batch_no' => $item['batch_no'] ?? '',
                'expiry_date' => $item['expiry_date'] ?? '',
                'quantity' => $item['qty'] ?? 0,
                'unit_price' => $item['per_piece_price'] ?? 0,
                'gst_rate' => $item['gst_rate'] ?? 0,
                'hsn_code' => $hsn['hsn_code'] ?? ''
            ];
        }
    }

    $data = [
        'supplier_name' => $bill['supplier_name'],
        'entry_no' => $bill['entry_no'],
        'date' => $bill['date'],
        'party_no' => $bill['party_no'] ?: '',
        'total_amount' => $bill['total_amount'] ?: 0,
        'items' => $items
    ];

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items found for this purchase bill']);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching purchase details: ' . $e->getMessage()]);
}

$conn->close();
?>