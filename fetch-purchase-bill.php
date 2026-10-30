<?php
require_once 'config.php';

header('Content-Type: application/json');

$purchase_bill_id = $_POST['purchase_bill_id'] ?? '';

if (empty($purchase_bill_id)) {
    echo json_encode(['error' => 'Purchase Bill ID is required']);
    exit;
}

// Fetch bill details
$stmt = $conn->prepare("
    SELECT id, name, entry_no, party_no, invoice_no, date, dt, type, supplier_gstin, recipient_gstin, gross_amount, cgst, sgst, igst, total_discount, round_off, net_amount
    FROM purchase_bills
    WHERE entry_no = ?
");
$stmt->bind_param("s", $purchase_bill_id);
$stmt->execute();
$bill_result = $stmt->get_result();
$bill = $bill_result->fetch_assoc();
$stmt->close();

if (!$bill) {
    echo json_encode(['error' => 'Purchase Bill ID not found']);
    exit;
}

// Fetch bill items
$stmt = $conn->prepare("
    SELECT id, hsn_code, product_name, per_piece_price, qty, discount, amount
    FROM purchase_items
    WHERE purchase_bill_id = ?
");
$stmt->bind_param("i", $bill['id']);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];
while ($row = $items_result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

echo json_encode([
    'bill' => $bill,
    'items' => $items
]);
$conn->close();
?>