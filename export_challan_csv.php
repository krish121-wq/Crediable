<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$challan_id = (int)($_GET['challan_id'] ?? 0);

if (!$challan_id) {
    die("Invalid challan ID");
}

try {
    // Fetch challan details
    $stmt = $conn->prepare("
        SELECT dc.*, 
               CASE 
                   WHEN admin_users.id IS NOT NULL THEN 'Admin'
                   WHEN staff_users.name IS NOT NULL THEN staff_users.name
                   ELSE 'Unknown'
               END AS created_by_name
        FROM delivery_challans dc
        LEFT JOIN users admin_users ON dc.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON dc.created_by = staff_users.id
        WHERE dc.id = ?
    ");
    $stmt->bind_param("i", $challan_id);
    $stmt->execute();
    $challan = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$challan) {
        die("Challan not found");
    }
    
    // Fetch challan items
    $stmt = $conn->prepare("
        SELECT * FROM delivery_challan_items 
        WHERE challan_id = ? 
        ORDER BY id
    ");
    $stmt->bind_param("i", $challan_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Set headers for CSV download
    $filename = "delivery_challan_" . $challan['challan_no'] . "_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add challan header information
    fputcsv($output, ['DELIVERY CHALLAN EXPORT']);
    fputcsv($output, ['']);
    fputcsv($output, ['Challan Information']);
    fputcsv($output, ['Supplier Name', $challan['supplier_name']]);
    fputcsv($output, ['Challan No', $challan['challan_no']]);
    fputcsv($output, ['Challan Date', $challan['challan_date']]);
    fputcsv($output, ['Invoice No', $challan['invoice_no'] ?: 'N/A']);
    fputcsv($output, ['Party No', $challan['party_no'] ?: 'N/A']);
    fputcsv($output, ['Created By', $challan['created_by_name']]);
    fputcsv($output, ['Created At', $challan['created_at']]);
    
    if ($challan['additional_notes']) {
        fputcsv($output, ['Additional Notes', $challan['additional_notes']]);
    }
    
    fputcsv($output, ['']);
    fputcsv($output, ['CHALLAN ITEMS']);
    
    // Add CSV headers for items
    fputcsv($output, [
        'Product Name',
        'Batch No',
        'Expiry Date',
        'Quantity',
        'Unit Price',
        'GST Rate (%)',
        'Amount',
        'GST Amount',
        'Total Amount'
    ]);
    
    // Add items data
    $totalAmount = 0;
    foreach ($items as $item) {
        fputcsv($output, [
            $item['product_name'],
            $item['batch_no'] ?: 'N/A',
            $item['expiry_date'] ?: 'N/A',
            $item['quantity'],
            number_format($item['unit_price'], 2),
            number_format($item['gst_rate'], 2),
            number_format($item['amount'], 2),
            number_format($item['gst_amount'], 2),
            number_format($item['total_amount'], 2)
        ]);
        $totalAmount += $item['total_amount'];
    }
    
    // Add total
    fputcsv($output, ['']);
    fputcsv($output, ['', '', '', '', '', '', '', 'TOTAL:', number_format($totalAmount, 2)]);
    
    fclose($output);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$conn->close();
?>
