<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$filter_type      = $_GET['filter_type']      ?? 'monthly';
$selected_month   = $_GET['month']            ?? date('Y-m');
$selected_year    = $_GET['year']             ?? date('Y');
$start_date       = $_GET['start_date']       ?? '';
$end_date         = $_GET['end_date']         ?? '';
$bank_account_id  = (int)($_GET['bank_account_id'] ?? 0);
$timezone         = new DateTimeZone('Asia/Kolkata');

try {
    $query = "SELECT bt.id, bt.bank_account_id, bt.transaction_type, bt.amount, bt.transaction_date, bt.description,
               bt.cheque_number, bt.e_payment_reference, bt.reference_type, bt.created_at,
               ba.bank_name, ba.account_number
        FROM bank_transactions bt
        JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.created_by = ? AND bt.status = 'completed'";
    $params = [$user_id];
    $param_types = "i";

    if ($bank_account_id) {
        $query .= " AND bt.bank_account_id = ?";
        $params[] = $bank_account_id; $param_types .= "i";
    }
    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(bt.transaction_date,'%Y-%m') = ?";
        $params[] = $selected_month; $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(bt.transaction_date) = ?";
        $params[] = (int)$selected_year; $param_types .= "i";
    } elseif ($filter_type === 'custom') {
        if (!empty($start_date) && !empty($end_date)) {
            $s = new DateTime($start_date, $timezone);
            $e = new DateTime($end_date, $timezone);
            $query .= " AND bt.transaction_date BETWEEN ? AND ?";
            $params[] = $s->format('Y-m-d 00:00:00');
            $params[] = $e->format('Y-m-d 23:59:59');
            $param_types .= "ss";
        }
    }

    $query .= " ORDER BY bt.transaction_date ASC, bt.id ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bank_ledger_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Bank Name', 'Account Number', 'Date', 'Type', 'Description', 'Reference', 'Debit', 'Credit', 'Balance', 'Created On'));

    $balance = 0;
    while ($row = $result->fetch_assoc()) {
        $balance += ($row['transaction_type'] === 'credit' ? $row['amount'] : -$row['amount']);
        $tdate = (new DateTime($row['transaction_date'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('d M Y');
        $cat   = (new DateTime($row['created_at'],      new DateTimeZone('UTC')))->setTimezone($timezone)->format('d M Y, h:i A');
        $ref   = $row['cheque_number'] ?: ($row['e_payment_reference'] ?: '-');
        $dr    = $row['transaction_type'] === 'debit'  ? number_format($row['amount'], 2) : '';
        $cr    = $row['transaction_type'] === 'credit' ? number_format($row['amount'], 2) : '';
        
        fputcsv($output, array($row['bank_name'], $row['account_number'], $tdate, ucfirst($row['transaction_type']), $row['description'], $ref, $dr, $cr, number_format($balance, 2), $cat));
    }
    
    fclose($output);
    $stmt->close();
    exit();

} catch (Exception $e) {
    die("Error generating CSV: " . htmlspecialchars($e->getMessage()));
}
?>
