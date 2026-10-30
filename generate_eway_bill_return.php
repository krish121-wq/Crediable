<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$purchase_bill_id = $_POST['purchase_bill_id'] ?? '';
$supplier_gstin = $_POST['supplier_gstin'] ?? '';
$recipient_gstin = $_POST['recipient_gstin'] ?? '';
$place_of_dispatch = $_POST['place_of_dispatch'] ?? '';
$place_of_delivery = $_POST['place_of_delivery'] ?? '';
$transport_mode = $_POST['transport_mode'] ?? '';
$distance = $_POST['distance'] ?? 0;
$vehicle_no = $_POST['vehicle_no'] ?? '';
$transporter_id = $_POST['transporter_id'] ?? '';
$items = json_decode($_POST['items'] ?? '[]', true);
$return_no = $_POST['return_no'] ?? '';
$user_id = (int)$_SESSION['user_id'];

if (empty($purchase_bill_id) || empty($supplier_gstin) || empty($recipient_gstin) || empty($transport_mode) || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

$conn->begin_transaction();

try {
    // Calculate total return amount
    $total_return_amount = 0;
    foreach ($items as $item) {
        $return_qty = $item['return_qty'] ?? 0;
        $unit_price = $item['unit_price'] ?? 0;
        $gst_rate = $item['gst_rate'] ?? 0;
        $base_amount = $return_qty * $unit_price;
        $gst_amount = $base_amount * ($gst_rate / 100);
        $total_return_amount += $base_amount + $gst_amount;
    }

    // Validate taxable value
    if ($total_return_amount < 50000) {
        throw new Exception('e-Way Bill is only required for goods valued above ₹50,000');
    }

    // Generate dummy e-Way Bill Number (replace with API call in production)
    $eway_bill_no = "EWB-RET-" . date("Ymd") . "-" . rand(100000, 999999);

    // Save to purchase_returns (if not already saved)
    $query = "SELECT id FROM purchase_returns WHERE return_no = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('si', $return_no, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $return_id = $result->fetch_assoc()['id'] ?? null;
    $stmt->close();

    if (!$return_id) {
        $query = "INSERT INTO purchase_returns (purchase_bill_id, user_id, return_no, debit_note_no, return_date, refund_type, return_status, return_notes, items, total_amount, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $debit_note_no = "DN-" . $return_no;
        $return_date = $_POST['return_date'] ?? date('Y-m-d');
        $refund_type = $_POST['refund_type'] ?? 'Credit';
        $return_status = 'Pending';
        $return_notes = $_POST['return_notes'] ?? '';
        $items_json = json_encode($items);
        $stmt->bind_param('iisssssssd', $purchase_bill_id, $user_id, $return_no, $debit_note_no, $return_date, $refund_type, $return_status, $return_notes, $items_json, $total_return_amount);
        $stmt->execute();
        $return_id = $conn->insert_id;
        $stmt->close();
    }

    // Save to eway_bills
    $query = "INSERT INTO eway_bills (purchase_return_id, user_id, eway_bill_no, supplier_gstin, recipient_gstin, 
              place_of_dispatch, place_of_delivery, transport_mode, distance, vehicle_no, transporter_id, created_at)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iissssssdss', $return_id, $user_id, $eway_bill_no, $supplier_gstin, $recipient_gstin,
        $place_of_dispatch, $place_of_delivery, $transport_mode, $distance, $vehicle_no, $transporter_id);
    $stmt->execute();
    $stmt->close();

    // Prepare JSON for e-Way Bill portal
    $json_data = [
        'supplyType' => 'O', // Outward supply for returns
        'subSupplyType' => '8', // Return
        'docType' => 'OTH', // Other (for returns)
        'docNo' => $return_no,
        'docDate' => date('d/m/Y', strtotime($return_date)),
        'fromGstin' => $recipient_gstin,
        'toGstin' => $supplier_gstin,
        'fromTrdName' => 'Your Business Name', // Replace with actual
        'toTrdName' => $_POST['supplier_name'] ?? '',
        'fromAddr1' => $place_of_dispatch,
        'toAddr1' => $place_of_delivery,
        'transType' => $transport_mode === 'Road' ? '1' : '2', // 1=Road, 2=Others
        'transMode' => $transport_mode,
        'distance' => (float)$distance,
        'vehicleNo' => $vehicle_no,
        'transId' => $transporter_id,
        'itemList' => []
    ];

    foreach ($items as $item) {
        if ($item['return_qty'] > 0) {
            $json_data['itemList'][] = [
                'productName' => $item['product_name'],
                'hsnCode' => $item['hsn_code'] ?? '',
                'quantity' => $item['return_qty'],
                'taxableAmount' => $item['return_qty'] * $item['unit_price'],
                'cgstRate' => $item['gst_rate'] / 2,
                'sgstRate' => $item['gst_rate'] / 2
            ];
        }
    }

    $conn->commit();

    // Save JSON file
    $json_file = "eway_bill_return_{$return_no}.json";
    file_put_contents($json_file, json_encode($json_data, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'eway_bill_no' => $eway_bill_no, 'json_file' => $json_file, 'items' => $items]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>