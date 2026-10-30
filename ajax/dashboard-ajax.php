<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include database configuration
require_once('../config.php');

// Check if connection exists
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection not established'),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // Get period filter and custom dates (if any)
    $period = $_GET['period'] ?? 'Today';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;

    // Calculate date condition based on period
    $date_condition = getDateCondition($period, $start_date, $end_date);

    // Initialize response data
    $dashboardData = [];

    // 1. Calculate Total Sales
    $query = "
        SELECT 
            COALESCE(SUM(s.total_price), 0) as total_sales,
            COUNT(*) as sales_count
        FROM sales s 
        WHERE 1=1 $date_condition
    ";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('Sales query failed: ' . $conn->error);
    }
    $salesData = $result->fetch_assoc();
    $dashboardData['total_sales'] = $salesData['total_sales'];
    $dashboardData['sales_count'] = $salesData['sales_count'];

    // 2. Calculate Total Purchases
    $purchase_date_condition = str_replace('s.created_at', 'p.created_at', $date_condition);
    $query = "
        SELECT 
            COALESCE(SUM(p.net_amount), 0) as total_purchase,
            COUNT(*) as purchase_count
        FROM purchases p 
        WHERE 1=1 $purchase_date_condition
    ";
    $result = $conn->query($query);
    if (!$result) {
        throw new Exception('Purchases query failed: ' . $conn->error);
    }
    $purchaseData = $result->fetch_assoc();
    $dashboardData['total_purchase'] = $purchaseData['total_purchase'];
    $dashboardData['purchase_count'] = $purchaseData['purchase_count'];

    // 3. Calculate Profit (Sales - Purchases)
    $dashboardData['profit'] = $dashboardData['total_sales'] - $dashboardData['total_purchase'];

    // 4. Calculate Total Outstanding (from invoices table)
    $query = "
        SELECT 
            COALESCE(SUM(i.total_amount - IFNULL(rp.total_paid, 0)), 0) as total_outstanding
        FROM invoices i
        LEFT JOIN (
            SELECT 
                invoice_id, 
                SUM(amount) as total_paid 
            FROM received_payments 
            GROUP BY invoice_id
        ) rp ON i.id = rp.invoice_id
        WHERE (i.total_amount - IFNULL(rp.total_paid, 0)) > 0
    ";
    $result = $conn->query($query);
    if ($result) {
        $outstandingData = $result->fetch_assoc();
        $dashboardData['invoice_due'] = $outstandingData['total_outstanding'];
    } else {
        $dashboardData['invoice_due'] = 0;
    }

    // 5. Calculate Total Expenses (from payroll_records and other expenses)
    $days = getPeriodDays($period);
    $query = "
        SELECT COALESCE(SUM(net_salary), 0) as payroll_expenses
        FROM payroll_records 
        WHERE status = 'processed'
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    ";
    $result = $conn->query($query);
    if ($result) {
        $payrollData = $result->fetch_assoc();
        $payroll_expenses = $payrollData['payroll_expenses'];
    } else {
        $payroll_expenses = 0;
    }

    // Add bank transactions as expenses (debit transactions)
    $query = "
        SELECT COALESCE(SUM(amount), 0) as bank_expenses
        FROM bank_transactions 
        WHERE transaction_type = 'debit' AND status = 'completed'
        AND DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    ";
    $result = $conn->query($query);
    if ($result) {
        $bankExpensesData = $result->fetch_assoc();
        $bank_expenses = $bankExpensesData['bank_expenses'];
    } else {
        $bank_expenses = 0;
    }

    $dashboardData['total_expenses'] = $payroll_expenses + $bank_expenses;

    // 6. Calculate Total Payment Returns (credit transactions from bank)
    $query = "
        SELECT COALESCE(SUM(amount), 0) as payment_returns
        FROM bank_transactions 
        WHERE transaction_type = 'credit' AND status = 'completed'
        AND DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
    ";
    $result = $conn->query($query);
    if ($result) {
        $paymentReturnsData = $result->fetch_assoc();
        $dashboardData['total_payment_returns'] = $paymentReturnsData['payment_returns'];
    } else {
        $dashboardData['total_payment_returns'] = 0;
    }

    // 7. Sales Returns (if you have a sales_returns table, otherwise set to 0)
    $dashboardData['total_sales_return'] = 0;

    // 8. Purchase Returns (if you have a purchase_returns table, otherwise set to 0)
   // 8. Calculate Total Purchase Returns
$purchase_return_date_condition = str_replace('s.created_at', 'created_at', $date_condition);
$query = "
    SELECT COALESCE(SUM(return_amount), 0) as total_purchase_return
    FROM purchase_return_records 
    WHERE 1=1 $purchase_return_date_condition
";
$result = $conn->query($query);
if ($result) {
    $purchaseReturnData = $result->fetch_assoc();
    $dashboardData['total_purchase_return'] = $purchaseReturnData['total_purchase_return'];
} else {
    $dashboardData['total_purchase_return'] = 0;
}

    // 9. Count unique suppliers (from purchases table)
    $query = "
        SELECT COUNT(DISTINCT name) as suppliers_count
        FROM purchases
    ";
    $result = $conn->query($query);
    if ($result) {
        $suppliersData = $result->fetch_assoc();
        $dashboardData['suppliers_count'] = $suppliersData['suppliers_count'];
    } else {
        $dashboardData['suppliers_count'] = 0;
    }

    // 10. Calculate Total Online Received (from received_payments table)
    // 10. Calculate Total Online Received (from received_payments table)
$received_payments_date_condition = str_replace('s.created_at', 'created_at', $date_condition);
$query = "
    SELECT COALESCE(SUM(amount), 0) as total_online_received
    FROM received_payments 
    WHERE LOWER(payment_method) IN ('upi', 'card', 'bank')
    $received_payments_date_condition
";
    $result = $conn->query($query);
    if ($result) {
        $onlineReceivedData = $result->fetch_assoc();
        $dashboardData['total_online_received'] = $onlineReceivedData['total_online_received'];
    } else {
        $dashboardData['total_online_received'] = 0;
    }
    
    // 10.1 Calculate Total Cash Received (from received_payments table)
$query = "
    SELECT COALESCE(SUM(amount), 0) as total_cash_received
    FROM received_payments 
    WHERE LOWER(payment_method) = 'cash'
    $received_payments_date_condition
";
    $result = $conn->query($query);
    if ($result) {
        $cashReceivedData = $result->fetch_assoc();
        $dashboardData['total_cash_received'] = $cashReceivedData['total_cash_received'];
    } else {
        $dashboardData['total_cash_received'] = 0;
    }

    // 11. Count unique customers (from sales table)
    $query = "
        SELECT COUNT(DISTINCT customer_name) as customers_count
        FROM sales
    ";
    $result = $conn->query($query);
    if ($result) {
        $customersData = $result->fetch_assoc();
        $dashboardData['customers_count'] = $customersData['customers_count'];
    } else {
        $dashboardData['customers_count'] = 0;
    }

    // 11.1 Calculate Total Cash Bills Received
    $cash_bill_date_condition = str_replace('s.created_at', 'created_at', $date_condition);
    $query = "
        SELECT COALESCE(SUM(total_amount), 0) as total_cash_bills
        FROM cash_bills 
        WHERE 1=1 $cash_bill_date_condition
    ";
    $result = $conn->query($query);
    if ($result) {
        $cashBillsData = $result->fetch_assoc();
        $dashboardData['total_cash_bills'] = $cashBillsData['total_cash_bills'];
    } else {
        $dashboardData['total_cash_bills'] = 0;
    }
    
    // 11.2 Total orders count (sales + purchases)
    $dashboardData['orders_count'] = $dashboardData['sales_count'] + $dashboardData['purchase_count'];

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $dashboardData,
        'period' => $period,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    // Return error response for any mysqli or general errors
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}


/**
 * Get date condition for SQL queries based on period
 */
function getDateCondition($period, $start_date = null, $end_date = null) {
    switch ($period) {
        case 'Custom':
            if ($start_date && $end_date) {
                // Sanitize dates to prevent SQL injection
                $start_date = date('Y-m-d', strtotime($start_date));
                $end_date = date('Y-m-d', strtotime($end_date));
                return " AND s.created_at BETWEEN '$start_date' AND '$end_date'";
            }
            
            // Fallback to Today if custom dates are not provided
            return " AND DATE(s.created_at) = CURDATE()";
        case 'Today':
            return " AND DATE(s.created_at) = CURDATE()";
        case 'Yesterday':
            return " AND DATE(s.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        case 'Last 7 Days':
            return " AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        case 'Last 30 Days':
            return " AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        case 'Last 6 Months':
            return " AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)";
        default:
            return " AND DATE(s.created_at) = CURDATE()";
    }
}

/**
 * Convert period to number of days (for expenses and payment returns)
 */
function getPeriodDays($period) {
    switch ($period) {
        case 'Custom':
            return 30; // Default to 30 days for custom, adjust if needed
        case 'Today':
            return 1;
        case 'Yesterday':
            return 1;
        case 'Last 7 Days':
            return 7;
        case 'Last 30 Days':
            return 30;
        case 'Last 6 Months':
            return 180;
        default:
            return 1;
    }
}
?>