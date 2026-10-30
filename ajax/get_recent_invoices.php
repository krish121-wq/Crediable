<?php
header('Content-Type: application/json');
require_once '../config.php';

try {
    $period = $_GET['period'] ?? 'Today';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    
    // Build date condition
    $dateCondition = '';
    if ($period === 'Custom' && $start_date && $end_date) {
        $dateCondition = "AND DATE(i.created_at) BETWEEN '$start_date' AND '$end_date'";
    } else {
        switch ($period) {
            case 'Today':
                $dateCondition = "AND DATE(i.created_at) = CURDATE()";
                break;
            case 'Yesterday':
                $dateCondition = "AND DATE(i.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'Last 7 Days':
                $dateCondition = "AND DATE(i.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'Last 30 Days':
                $dateCondition = "AND DATE(i.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'Last 6 Months':
                $dateCondition = "AND DATE(i.created_at) >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
                break;
            default:
                $dateCondition = "AND DATE(i.created_at) = CURDATE()";
        }
    }
    
    // Fetch last 10 invoices
    $query = "
        SELECT 
            i.id as invoice_id,
            i.customer_name,
            i.customer_phone,
            i.total_amount,
            i.created_at,
            COALESCE(rp.paid_amount, 0) as paid_amount,
            CASE 
                WHEN COALESCE(rp.paid_amount, 0) >= i.total_amount THEN 'paid'
                WHEN COALESCE(rp.paid_amount, 0) > 0 THEN 'partial'
                ELSE 'pending'
            END as payment_status,
            (SELECT COUNT(*) FROM sales WHERE invoice_id = i.id) as product_count
        FROM invoices i
        LEFT JOIN (
            SELECT invoice_id, SUM(amount) as paid_amount
            FROM received_payments
            GROUP BY invoice_id
        ) rp ON i.id = rp.invoice_id
        WHERE 1=1 $dateCondition
        ORDER BY i.created_at DESC
        LIMIT 10
    ";
    
    $result = $conn->query($query);
    $invoices = [];
    
    while ($row = $result->fetch_assoc()) {
        $invoices[] = [
            'invoice_id' => $row['invoice_id'],
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'customer_phone_raw' => preg_replace('/[^0-9]/', '', $row['customer_phone']),
            'total_amount' => floatval($row['total_amount']),
            'paid_amount' => floatval($row['paid_amount']),
            'payment_status' => $row['payment_status'],
            'product_count' => $row['product_count'],
            'date' => date('d M Y', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $invoices,
        'count' => count($invoices)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>