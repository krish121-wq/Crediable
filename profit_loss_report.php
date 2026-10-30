<?php
require_once 'config.php';


if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}


$start_date = date('Y-m-01');
$end_date = date('Y-m-t');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? $start_date;
    $end_date = $_POST['end_date'] ?? $end_date;
}



$invoice_total = 0;
$cash_bill_total = 0;
$service_invoice_total = 0;

$report_data = [
    'revenue' => 0,
    'cogs' => 0,
    'gross_profit' => 0,
    'expenses' => 0,
    'net_profit' => 0,
    'expense_breakdown' => []
];

try {
    
    $start = $start_date . ' 00:00:00';
$end   = $end_date . ' 23:59:59';
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

  
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    
    
  
    if (in_array('invoices', $tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE created_at BETWEEN '$start' AND '$end'");
        $row = $result->fetch_assoc();
        
    } else {
        
    }
    
  
    if (in_array('cash_bills', $tables)) {
        $result = $conn->query("SELECT COUNT(*) as count FROM cash_bills WHERE created_at BETWEEN '$start' AND '$end'");
        $row = $result->fetch_assoc();
        
    } else {
        
    }
    
    
    

    
    $query = "SELECT COALESCE(SUM(total_amount), 0) as invoice_total
              FROM invoices 
              WHERE created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $invoice_result = $stmt->get_result()->fetch_assoc();
    $invoice_total = (float)($invoice_result['invoice_total'] ?? 0);
    
    
    
    


    
    // Method 1: Direct sum 
    $query = "SELECT COALESCE(SUM(total_amount), 0) as direct_total 
              FROM cash_bills 
              WHERE created_at BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $direct_total = (float)($stmt->get_result()->fetch_assoc()['direct_total'] ?? 0);
    
    // Method 2: Get individual records and sum manually
$query = "SELECT COALESCE(SUM(total_amount), 0) as cash_total 
          FROM cash_bills 
          WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$cash_bill_total = (float)($stmt->get_result()->fetch_assoc()['cash_total'] ?? 0);


// Get service invoices total
$query = "SELECT COALESCE(SUM(total_amount), 0) as service_invoice_total 
          FROM service_invoices 
          WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $start, $end);
$stmt->execute();
$service_invoice_total = (float)($stmt->get_result()->fetch_assoc()['service_invoice_total'] ?? 0);
    
    
    
    $report_data['revenue'] = $invoice_total + $cash_bill_total + $service_invoice_total;
    
  


  
   $start = $start_date . ' 00:00:00';
$end   = $end_date   . ' 23:59:59';

$query = "
    SELECT COALESCE(SUM(cogs), 0) AS total_cogs
    FROM (
        SELECT p.purchase_price * s.quantity AS cogs
        FROM sales s
        JOIN products p ON s.product_id = p.id
        WHERE s.created_at BETWEEN ? AND ?

        UNION ALL

        SELECT p.purchase_price * c.quantity AS cogs
        FROM cash_bill_items c
        JOIN products p ON c.product_id = p.id
        WHERE c.created_at BETWEEN ? AND ?
    ) AS combined
";

$stmt = $conn->prepare($query);
$stmt->bind_param('ssss', $start, $end, $start, $end);
$stmt->execute();

$result = $stmt->get_result();
$cogs_data = $result->fetch_assoc();

$report_data['cogs'] = (float) $cogs_data['total_cogs'];


    
    $report_data['gross_profit'] = $report_data['revenue'] - $report_data['cogs'];

   
    $query = "SELECT COALESCE(SUM(net_salary + pf_employer_contribution + esi_employer_contribution), 0) as payroll_expenses
              FROM payroll_records 
              WHERE DATE(payroll_month) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $payroll_data = $result->fetch_assoc();
    $payroll_expenses = $payroll_data['payroll_expenses'] ?? 0;
    $report_data['expenses'] += $payroll_expenses;
    $report_data['expense_breakdown']['Payroll'] = $payroll_expenses;

 
    $query = "SELECT COALESCE(SUM(amount), 0) as other_expenses
              FROM bank_transactions 
              WHERE transaction_type = 'expense' 
              AND DATE(transaction_date) BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense_data = $result->fetch_assoc();
    $other_expenses = $expense_data['other_expenses'] ?? 0;
    $report_data['expenses'] += $other_expenses;
    $report_data['expense_breakdown']['Other Expenses'] = $other_expenses;

    // Get expenses from expenses table
    $query = "SELECT COALESCE(SUM(amount), 0) as direct_expenses
              FROM expenses 
              WHERE expense_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $direct_expenses = $result->fetch_assoc();
    $expenses_amount = $direct_expenses['direct_expenses'] ?? 0;
    $report_data['expenses'] += $expenses_amount;
    $report_data['expense_breakdown']['Direct Expenses'] = $expenses_amount;

  
    $report_data['net_profit'] = $report_data['gross_profit'] - $report_data['expenses'];

} catch (Exception $e) {
    $_SESSION['error_message'] = "Error generating report: " . $e->getMessage();
}

// Handle Excel Export after data is calculated
if (isset($_POST['export_excel'])) {
    exportToExcel($report_data, $invoice_total, $cash_bill_total, $service_invoice_total, $start_date, $end_date);
    exit();
}

// Excel Export Function
function exportToExcel($report_data, $invoice_total, $cash_bill_total, $service_invoice_total, $start_date, $end_date) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="profit_loss_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['Profit & Loss Statement']);
    fputcsv($output, ['Period: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, []);
    
    // Revenue Section
    fputcsv($output, ['Revenue', 'Amount']);
    fputcsv($output, ['Sales Invoices', $invoice_total ?? 0]);
    fputcsv($output, ['Cash Sales', $cash_bill_total ?? 0]);
    fputcsv($output, ['Service Invoices', $service_invoice_total ?? 0]);
    fputcsv($output, ['Total Revenue', $report_data['revenue'] ?? 0]);
    fputcsv($output, []);
    
    // COGS and Gross Profit
    fputcsv($output, ['Cost of Goods Sold', $report_data['cogs'] ?? 0]);
    fputcsv($output, ['Gross Profit', $report_data['gross_profit'] ?? 0]);
    fputcsv($output, []);
    
    // Expenses Section
    fputcsv($output, ['Operating Expenses', 'Amount']);
    $expense_breakdown = $report_data['expense_breakdown'] ?? [];
    foreach ($expense_breakdown as $category => $amount) {
        fputcsv($output, [$category, $amount]);
    }
    fputcsv($output, ['Total Expenses', $report_data['expenses'] ?? 0]);
    fputcsv($output, []);
    
    // Net Profit/Loss
    $net_profit = $report_data['net_profit'] ?? 0;
    $profit_loss_type = ($net_profit >= 0) ? 'Net Profit' : 'Net Loss';
    fputcsv($output, [$profit_loss_type, abs($net_profit)]);
    
    fclose($output);
    exit();
}


include('layout.php'); //

?>

<style>
:root {
    /* MAIN BACKGROUND */
    --canvas-cream: #f4f8fb;

    /* PRIMARY BLUE THEME (matches GSTR-3B) */
    --billing-base: #0284c7;     /* main color */
    --billing-hover: #0ea5e9;    /* lighter hover */
    --billing-active: #0b1220;   /* darker click */

    /* TEXT COLORS */
    --text-primary: #0b1220;
    --text-secondary: #475569;
    --text-light: #FFFFFF;

    /* UI COLORS */
    --card-bg: #FFFFFF;
    --grey-light: #e2e8f0;

    /* STATUS COLORS */
    --success: #16a34a;
    --danger: #dc2626;

    /* ACCENT (same blue family) */
    --accent: #0284c7;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--canvas-cream);
    color: var(--text-primary);
}

.content {
    margin-top: 70px;
    margin-left: 260px;
    padding: 20px;
    min-height: calc(100vh - 70px);
    background: var(--canvas-cream);
    transition: margin-left 0.3s ease;
    overflow-x: auto;
}

.content-full {
    margin-left: 0;
    margin-right: 0;
    max-width: 100%;
    padding: 20px;
}

.sidebar-hidden ~ .content {
    margin-left: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
    background: var(--billing-base);
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.2);
    color: var(--text-light);
}

.page-title h4 {
    font-weight: 700;
    color: var(--text-light);
    margin: 0;
    font-size: 1.75rem;
}

.page-title h6 {
    font-weight: 400;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
    font-size: 1rem;
}

.page-btn-group {
    display: flex;
    gap: 10px;
    align-items: center;
}
.btn-custom {
    background: var(--billing-base);
    border: none;
    color: var(--text-light);
    font-weight: 600;
    padding: 10px 22px;
    border-radius: 6px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.2);
}

.btn-custom:hover {
    background: var(--billing-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(2, 132, 199, 0.3);
}

.btn-custom:active {
    background: var(--billing-active);
    transform: translateY(0);
}

.btn-custom i {
    font-size: 1.1rem;
}

.btn-settled {
    background: #0284c7;              /* Bootstrap success green */
    border-color: rgba(255,255,255,0.4);
    color: #fff;
    font-size: 14px;
   padding: 12px 28px; 
    width: 175px;   }
    
    .btn-settled:hover {
    background: #0284c7;
    border-color: rgba(255, 255, 255, 0.5);
    color: #fff;
}
.export-btn {
    background: var(--billing-base);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: var(--text-light);
    font-weight: 600;
    font-size: 1rem;
    padding: 10px 20px;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.export-btn:hover {
    background: var(--billing-hover);
    color: var(--text-light);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);
}

.export-btn:active {
    background: var(--billing-active);
    transform: translateY(0);
}

.export-btn i {
    font-size: 1.1rem;
}

.export-btn span {
    font-size: 0.9rem;
    font-weight: 500;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 8px;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.table th, .table td {
    vertical-align: middle;
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--grey-light);
}

.table th {
    background: #e0f2fe;
    color: #0284c7;
}

.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: #f3f4f6;
}

.action-container {
    display: flex;
    gap: 8px;
    padding: 5px 10px;
    background: var(--card-bg);
    border: 1px solid var(--grey-light);
    border-radius: 6px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    width: 120px;
    justify-content: center;
}

.action-btn {
    color: var(--text-secondary);
    font-size: 1.1rem;
    transition: all 0.2s ease;
    text-decoration: none;
}

.action-btn:hover {
    color: var(--billing-base);
    transform: scale(1.1);
}

.action-btn.print {
    color: var(--success);
}

.action-btn.whatsapp {
    color: #25D366;
}

.action-btn.view {
    color: var(--accent);
}

.action-btn.edit {
    color: #d97706;
}

.action-btn.delete {
    color: var(--danger);
}

.alert-success {
    background: var(--success);
    color: var(--text-light);
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
    opacity: 1;
    transition: opacity 0.5s ease-out;
}

.alert-success.hide {
    opacity: 0;
    height: 0;
    padding: 0;
    margin: 0;
    overflow: hidden;
}

.alert-danger {
    background: var(--danger);
    color: var(--text-light);
    padding: 12px;
    margin-bottom: 20px;
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
}

.form-group {
    margin-bottom: 18px;
}

.form-control {
    border-radius: 6px;
    border: 1px solid var(--grey-light);
    padding: 10px 12px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    color: var(--text-primary);
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
}

.form-control::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.product-row {
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin-bottom: 15px;
    background: var(--card-bg);
    padding: 12px;
    border-radius: 6px;
    border: 1px solid var(--grey-light);
}

.product-row select, .product-row input {
    height: 40px;
    padding: 8px 12px;
}

.remove-product {
    color: var(--danger);
    cursor: pointer;
    font-size: 1.3rem;
    transition: color 0.2s ease;
    align-self: center;
}

.remove-product:hover {
    color: #dc2626;
}

.search-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: center;
}

.search-input-group {
    position: relative;
    display: flex;
    align-items: center;
    max-width: 600px;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 15px;
    color: var(--text-secondary);
    font-size: 16px;
    z-index: 2;
}

.search-input {
    padding: 12px 50px 12px 45px;
    border: 2px solid var(--grey-light);
    border-radius: 25px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.search-input:focus {
    border-color: var(--billing-base);
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    outline: none;
}

.btn-clear-search {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 14px;
    padding: 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
    display: none;
}

.btn-clear-search:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.btn-clear-search.show {
    display: block;
}

.search-results-info {
    font-size: 13px;
    color: var(--text-primary);
    font-weight: 500;
}

.search-results-info #totalRecords {
    color: var(--billing-base);
    font-weight: 600;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.no-results i {
    font-size: 48px;
    color: var(--grey-light);
    margin-bottom: 15px;
}

.highlight {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

.card {
    margin-bottom: 20px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border: 1px solid #eee;
}

.card-body {
    color: var(--text-primary);
}

.badge {
    font-weight: 600;
}

.modal-content {
    border-radius: 10px;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    border: none;
}

.modal-header {
    border-bottom: 1px solid var(--grey-light);
    padding: 20px 30px;
    background: var(--card-bg);
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.close {
    font-size: 1.2rem;
    padding: 5px 10px;
    border-radius: 50%;
    transition: background-color 0.3s ease;
    background: var(--danger);
    color: var(--text-light);
}

.close:hover {
    background-color: #dc2626;
}

.modal-body {
    padding: 25px 30px;
    background: var(--canvas-cream);
    color: var(--text-primary);
}

.modal-body label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    display: block;
}

.modal-footer {
    border-top: 1px solid var(--grey-light);
    padding: 15px 30px;
    background: var(--card-bg);
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
    justify-content: space-between;
}

.modal-footer .btn {
    padding: 10px 25px;
    font-weight: 500;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.modal-footer .btn-secondary {
    background-color: var(--grey-light);
    color: var(--text-primary);
    border: none;
}

.modal-footer .btn-secondary:hover {
    background-color: #d1d5db;
}

.modal-footer .btn-custom {
    background: var(--billing-base);
    color: var(--text-light);
    border: none;
}

.modal-footer .btn-custom:hover {
    background: var(--billing-hover);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
}

#addCashBillModal .product-row {
    background: var(--card-bg);
    padding: 15px;
    border-radius: 6px;
    border: 1px solid var(--grey-light);
}

#addCashBillModal .product-row .form-group {
    margin-bottom: 0;
}

#addCashBillModal #addProduct {
    margin-top: 15px;
    padding: 8px 20px;
    background: var(--billing-base);
    color: var(--text-light);
    border: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

#addCashBillModal #addProduct:hover {
    background: var(--billing-hover);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
}

/* Invoice Styles for Print/WhatsApp */
.thermal-bill {
    width: 80mm;
    max-width: 300px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid var(--grey-light);
    padding: 8px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.3;
    color: var(--text-primary);
}

.bill-header {
    text-align: center;
    border-bottom: 1px dashed var(--text-primary);
    padding-bottom: 8px;
    margin-bottom: 8px;
}

.company-name {
    font-size: 16px;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 2px;
    color: var(--billing-base);
}

.company-address, .company-gst {
    font-size: 10px;
    margin-bottom: 4px;
    color: var(--text-secondary);
}

.bill-info {
    text-align: center;
    margin-bottom: 8px;
    font-size: 11px;
    color: var(--text-primary);
}

.bill-divider {
    border-bottom: 1px dashed var(--text-primary);
    margin: 8px 0;
}

.customer-info {
    margin-bottom: 8px;
    font-size: 10px;
    color: var(--text-primary);
}

.customer-info div {
    margin-bottom: 1px;
}

.items-header {
    border-top: 1px solid var(--text-primary);
    border-bottom: 1px solid var(--text-primary);
    padding: 2px 0;
    font-weight: bold;
    font-size: 10px;
    text-align: left;
    color: var(--billing-base);
}

.item-row {
    padding: 2px 0;
    font-size: 10px;
    border-bottom: 1px dotted #ccc;
}

.item-name {
    font-weight: bold;
    margin-bottom: 1px;
    color: var(--text-primary);
}

.item-details {
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: var(--text-secondary);
}

.total-section {
    border-top: 1px solid var(--text-primary);
    padding-top: 4px;
    margin-top: 8px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    margin-bottom: 2px;
    color: var(--text-primary);
}

.grand-total {
    display: flex;
    justify-content: space-between;
    font-weight: bold;
    font-size: 12px;
    border-top: 1px solid var(--text-primary);
    border-bottom: 1px solid var(--text-primary);
    padding: 4px 0;
    margin: 4px 0;
    color: var(--billing-base);
}

.bill-footer {
    text-align: center;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--text-primary);
    font-size: 9px;
    color: var(--text-secondary);
}

.thank-you {
    font-weight: bold;
    margin-bottom: 4px;
    font-size: 11px;
    color: var(--billing-base);
}

.print-button {
    background: var(--billing-base);
    color: var(--text-light);
    padding: 8px 16px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    margin: 10px auto;
    border-radius: 4px;
    font-size: 12px;
    border: none;
    cursor: pointer;
}

.print-button:hover {
    background: var(--billing-hover);
}

@media print {
    body {
        background: white;
        padding: 0;
        margin: 0;
    }
    .thermal-bill {
        border: none;
        max-width: none;
        width: 80mm;
        margin: 0;
        padding: 4px;
    }
    .print-button {
        display: none;
    }
}

@media (max-width: 992px) {
    .content {
        margin-left: 0;
        margin-top: 64px;
        padding: 15px;
        width: 100%;
    }
    .content-full {
        margin-left: 0;
        margin-right: 0;
        max-width: 100%;
        padding: 15px;
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 15px;
    }
}

@media (max-width: 768px) {
    .content {
        margin-top: 60px;
        padding: 10px;
    }
    .page-header {
        padding: 15px;
        margin-bottom: 15px;
    }
    .page-btn-group {
        flex-direction: column;
        width: 100%;
    }
    .btn-custom, .export-btn {
        width: 100%;
        text-align: center;
        padding: 10px;
    }
    .export-btn span {
        font-size: 0.8rem;
    }
    .action-container {
        flex-direction: column;
        width: 80px;
        padding: 5px;
        margin-top: 5px;
    }
    .action-btn {
        font-size: 1rem;
    }
    .search-input-group {
        max-width: 100%;
    }
    .search-input {
        font-size: 16px;
    }
    .search-container {
        align-items: stretch;
    }
    .search-results-info {
        text-align: center;
        margin-top: 5px;
    }
    .product-row {
        flex-direction: column;
        gap: 10px;
    }
}

  @media print {
        .no-print { display: none; }
        body { font-size: 12px; }
        .table { margin-bottom: 0; }
    }
 
    
    .pnl-report * {
    box-sizing: border-box;
}

.pnl-report {
    --primary: #0284c7;
    --primary-hover: #0ea5e9;
    --border: #e2e8f0;
    --bg: #f4f8fb;
    --success: #16a34a;
    --danger: #dc2626;
}
/* ONLY INSIDE PNL PAGE */
.pnl-report .card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.pnl-report .card-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    color: #fff;
    padding: 18px;
}

.pnl-report .card-body {
    padding: 20px;
}

.pnl-report .form-control {
    height: 42px;
    border: 1px solid var(--border);
    border-radius: 6px;
}

.pnl-report .btn-primary {
    background: var(--primary);
    border: none;
    color: #fff;
}

.pnl-report .table {
    width: 100%;
    border-collapse: collapse;
}

.pnl-report .table th {
    background: #f1f5f9;
    padding: 12px;
}

.pnl-report .table td {
    padding: 12px;
}

.pnl-report .table-primary {
    background: #e0f2fe;
}

.pnl-report .table-success {
    background: #dcfce7;
}

.pnl-report .table-danger {
    background: #fee2e2;
}

.pnl-report .text-right {
    text-align: right;
}

.pnl-report .text-center {
    text-align: center;
}

.btn-custom {
    background: var(--billing-base);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 22px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);
}

.btn-custom:hover {
    background: var(--billing-hover);
    box-shadow: 0 6px 15px rgba(2, 132, 199, 0.4);
}

.btn-success {
    background: #16a34a;
    border: none;
    color: #fff;
    border-radius: 6px;
    padding: 10px 22px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(22, 163, 74, 0.3);
    transition: all 0.25s ease;
}

.btn-success:hover {
    background: #15803d;
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(22, 163, 74, 0.4);
}

.btn-success:active {
    background: #166534;
    transform: translateY(0);
}
</style>
<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="pnl-report">

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Profit & Loss Report</h4>
                </div>
                <div class="card-body">
              
                    <form method="POST" class="mb-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-custom">Generate Report</button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" name="export_excel" value="1" class="btn btn-success">
                                    <i class="fas fa-file-excel"></i> Export to Excel
                                </button>
                            </div>
                        </div>
                    </form>

               
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th colspan="2" class="text-center">
                                    <h4>Profit & Loss Statement</h4>
                                    <p><?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                                </th>
                            </tr>
                            
                           
                            <tr class="table-primary">
                                <th>Revenue</th>
                                <th class="text-right">₹<?php echo number_format($report_data['revenue'], 2); ?></th>
                            </tr>
                            <tr>
                                <td style="padding-left: 30px;"> Sales Invoices</td>
                                <td class="text-right">₹<?php echo number_format($invoice_total, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="padding-left: 30px;"> Cash Sales</td>
                                <td class="text-right">₹<?php echo number_format($cash_bill_total, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="padding-left: 30px;"> Service Invoices</td>
                                <td class="text-right">₹<?php echo number_format($service_invoice_total, 2); ?></td>
                            </tr>
                            
                            <!-- COGS Section -->
                            <tr>
                                <td>Cost of Goods Sold</td>
                                <td class="text-right"><?php echo number_format($report_data['cogs'], 2); ?></td>
                            </tr>
                            
                            <!-- Gross Profit -->
                            <tr class="table-success">
                                <th>Gross Profit</th>
                                <th class="text-right"><?php echo number_format($report_data['gross_profit'], 2); ?></th>
                            </tr>
                            
                            <!-- Expenses Section -->
                            <tr class="table-primary">
                                <th>Operating Expenses</th>
                                <th class="text-right"><?php echo number_format($report_data['expenses'], 2); ?></th>
                            </tr>
                            
                            <!-- Expense Breakdown -->
                            <?php foreach ($report_data['expense_breakdown'] as $category => $amount): ?>
                            <tr>
                                <td>&nbsp;&nbsp;&nbsp;&nbsp;<?php echo htmlspecialchars($category); ?></td>
                                <td class="text-right"><?php echo number_format($amount, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Net Profit -->
                            <tr class="table-<?php echo ($report_data['net_profit'] >= 0) ? 'success' : 'danger'; ?>">
                                <th>Net <?php echo ($report_data['net_profit'] >= 0) ? 'Profit' : 'Loss'; ?></th>
                                <th class="text-right"><?php echo number_format(abs($report_data['net_profit']), 2); ?></th>
                            </tr>
                        </table>
                    </div>
                    
                        </div>
</div>
                    
                </div>
            </div>
        </div>
    </div>
</div>