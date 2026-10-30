<?php
header('Content-Type: application/json');
require_once '../config.php';

$period = $_GET['period'] ?? 'Last 30 Days';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

// Get date range
$end = new DateTime();
$start = new DateTime();

if ($period === 'Custom' && $start_date && $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
} else {
    switch ($period) {
        case 'Today':
            $start = new DateTime('today');
            $end = new DateTime('today');
            break;
        case 'Yesterday':
            $start = new DateTime('yesterday');
            $end = new DateTime('yesterday');
            break;
        case 'Last 7 Days':
            $start = new DateTime('-7 days');
            break;
        case 'Last 30 Days':
            $start = new DateTime('-30 days');
            break;
        case 'Last 6 Months':
            $start = new DateTime('-180 days');
            break;
        default:
            $start = new DateTime('-30 days');
    }
}

// Generate date labels
$labels = [];
$revenueData = [];
$current = clone $start;

while ($current <= $end) {
    $labels[] = $current->format('d M');
    
    $dateStr = $current->format('Y-m-d');
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_price), 0) as daily_revenue FROM sales WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $dateStr);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $revenueData[] = floatval($result['daily_revenue']);
    $stmt->close();
    
    $current->modify('+1 day');
}

// Get payment method totals
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN LOWER(payment_method) = 'cash' THEN amount ELSE 0 END), 0) as cash_total,
        COALESCE(SUM(CASE WHEN LOWER(payment_method) IN ('upi', 'card', 'bank', 'online') THEN amount ELSE 0 END), 0) as online_total
    FROM received_payments
");
$stmt->execute();
$paymentData = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success' => true,
    'revenue_labels' => $labels,
    'revenue_data' => $revenueData,
    'cash_total' => floatval($paymentData['cash_total']),
    'online_total' => floatval($paymentData['online_total']),
    'period' => $period
]);
?>