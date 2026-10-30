<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include('layout.php');
$page_title = "Reports Hub";

// Ensure current_balance column exists in customers table
try {
    $conn->query("ALTER TABLE customers ADD COLUMN IF NOT EXISTS current_balance DECIMAL(10,2) DEFAULT 0.00");
} catch (Exception $e) {}

// Filters
$report_cat  = $_GET['cat'] ?? 'transaction'; // transaction, party, item, gst, accounting
$report_type = $_GET['type'] ?? 'sale';       // sale, purchase, daybook, all_tx, profit_loss, bill_profit, cash_flow, trial_balance, balance_sheet, party_statement, party_pnl, all_parties, stock_summary, gstr1, gstr2, gstr3

$from_date   = $_GET['from_date'] ?? date('Y-01-01');
$to_date     = $_GET['to_date'] ?? date('Y-m-d');
$party_filter = (int)($_GET['party_id'] ?? 0);

// Fetch Company Details
$company = [
    'company_name' => 'Credible Business Suite',
    'company_address' => '',
    'company_gst' => ''
];
$comp_res = $conn->query("SELECT company_name, company_address, company_gst FROM company_details LIMIT 1");
if ($comp_res && $comp_res->num_rows > 0) {
    $company = array_merge($company, $comp_res->fetch_assoc());
}

// Data containers & totals
$report_rows = [];
$summary_totals = [
    'paid' => 0,
    'unpaid' => 0,
    'total' => 0,
    'count' => 0
];

// Execute Queries based on report_type
try {
    if ($report_type === 'sale') {
        $sql = "SELECT 'Sale' as tx_type, id as doc_no, DATE(created_at) as doc_date, customer_name as party_name, 'Cash/Bank' as payment_mode, total_amount as total_amt, total_amount as paid_amount, 0 as balance_amt, 'Completed' as status
                FROM invoices 
                WHERE (DATE(created_at) BETWEEN '$from_date' AND '$to_date') OR ('$from_date' <= DATE(created_at) AND '$to_date' >= DATE(created_at))
                ORDER BY id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['unpaid'] += max(0, floatval($row['balance_amt']));
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'purchase') {
        $sql = "SELECT 'Purchase' as tx_type, id as doc_no, DATE(created_at) as doc_date, name as party_name, 'Bank' as payment_mode, net_amount as total_amt, net_amount as paid_amount, 0 as balance_amt, 'Completed' as status
                FROM purchases 
                WHERE (DATE(created_at) BETWEEN '$from_date' AND '$to_date') OR (date BETWEEN '$from_date' AND '$to_date')
                ORDER BY id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['unpaid'] += max(0, floatval($row['balance_amt']));
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'daybook') {
        $sql1 = "SELECT 'Sale' as tx_type, CONCAT('INV-', id) as doc_no, DATE(created_at) as doc_date, customer_name as party_name, 'Cash' as payment_mode, total_amount as total_amt, total_amount as paid_amount, 'Completed' as status FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
        $res1 = $conn->query($sql1);
        if ($res1) {
            while ($row = $res1->fetch_assoc()) {
                $row['balance_amt'] = 0;
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
            }
        }
        $sql2 = "SELECT 'Expense' as tx_type, CONCAT('EXP-', id) as doc_no, expense_date as doc_date, expense_category as party_name, payment_method as payment_mode, amount as total_amt, amount as paid_amount, 'Paid' as status FROM expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date'";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $row['balance_amt'] = 0;
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
            }
        }
    } elseif ($report_type === 'all_tx') {
        // All Transactions fetched via separate queries for maximum compatibility
        $sql1 = "SELECT 'Sale Invoice' as tx_type, CONCAT('INV-', id) as doc_no, DATE(created_at) as doc_date, customer_name as party_name, 'Cash/Bank' as payment_mode, total_amount as total_amt, total_amount as paid_amount FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
        $res1 = $conn->query($sql1);
        if ($res1) {
            while ($row = $res1->fetch_assoc()) {
                $row['balance_amt'] = 0;
                $row['status'] = 'Completed';
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['count']++;
            }
        }
        $sql2 = "SELECT 'Purchase' as tx_type, CONCAT('PUR-', id) as doc_no, DATE(created_at) as doc_date, name as party_name, 'Bank' as payment_mode, net_amount as total_amt, net_amount as paid_amount FROM purchases WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $row['balance_amt'] = 0;
                $row['status'] = 'Completed';
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['count']++;
            }
        }
        $sql3 = "SELECT 'Expense' as tx_type, CONCAT('EXP-', id) as doc_no, expense_date as doc_date, expense_category as party_name, payment_method as payment_mode, amount as total_amt, amount as paid_amount FROM expenses WHERE expense_date BETWEEN '$from_date' AND '$to_date'";
        $res3 = $conn->query($sql3);
        if ($res3) {
            while ($row = $res3->fetch_assoc()) {
                $row['balance_amt'] = 0;
                $row['status'] = 'Paid';
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'all_parties') {
        $sql = "SELECT name as party_name, phone, email, current_balance FROM customers ORDER BY name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $report_rows[] = [
                    'tx_type' => 'Customer',
                    'doc_no' => $row['phone'] ?: 'N/A',
                    'doc_date' => date('Y-m-d'),
                    'party_name' => $row['party_name'],
                    'payment_mode' => $row['email'] ?: 'N/A',
                    'total_amt' => floatval($row['current_balance']),
                    'paid_amount' => 0,
                    'balance_amt' => floatval($row['current_balance']),
                    'status' => 'Active'
                ];
                $summary_totals['total'] += floatval($row['current_balance']);
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'stock_summary') {
        $sql = "SELECT product_name, quantity as current_stock, sell_price as purchase_price FROM products ORDER BY product_name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $val = floatval($row['current_stock']) * floatval($row['purchase_price']);
                $report_rows[] = [
                    'tx_type' => 'Item',
                    'doc_no' => 'PROD-ITEM',
                    'doc_date' => date('Y-m-d'),
                    'party_name' => $row['product_name'],
                    'payment_mode' => 'Qty: ' . $row['current_stock'],
                    'total_amt' => $val,
                    'paid_amount' => $val,
                    'balance_amt' => 0,
                    'status' => 'In Stock'
                ];
                $summary_totals['total'] += $val;
                $summary_totals['paid'] += $val;
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'tds_rec') {
        // TDS Receivable Report (Deducted on Sales Invoices)
        $sql = "SELECT 'TDS Rec' as tx_type, CONCAT('INV-', id) as doc_no, DATE(created_at) as doc_date, customer_name as party_name, CONCAT('TDS ', tds_percent, '%') as payment_mode, total_amount as total_amt, tds_amount as paid_amount, (total_amount - tds_amount) as balance_amt, 'Receivable' as status FROM invoices WHERE tds_percent > 0 AND DATE(created_at) BETWEEN '$from_date' AND '$to_date' ORDER BY id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['unpaid'] += floatval($row['balance_amt']);
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'tds_pay') {
        // TDS Payable Report (Deducted on Purchases / Payments)
        $sql = "SELECT 'TDS Pay' as tx_type, CONCAT('PUR-', id) as doc_no, DATE(created_at) as doc_date, name as party_name, 'TDS Payable' as payment_mode, net_amount as total_amt, (net_amount * 0.01) as paid_amount, (net_amount * 0.99) as balance_amt, 'Payable' as status FROM purchases WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date' ORDER BY id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $report_rows[] = $row;
                $summary_totals['total'] += floatval($row['total_amt']);
                $summary_totals['paid'] += floatval($row['paid_amount']);
                $summary_totals['unpaid'] += floatval($row['balance_amt']);
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'item_serials') {
        $sql = "SELECT ps.serial_number, ps.status, ps.created_at, p.product_name, p.sell_price, 
                i.invoice_no, i.created_at as sale_date 
                FROM product_serials ps 
                JOIN products p ON ps.product_id = p.id 
                LEFT JOIN invoices i ON ps.invoice_id = i.id 
                ORDER BY p.product_name, ps.created_at DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $doc_no = $row['invoice_no'] ?: 'PURCHASE';
                $report_rows[] = [
                    'tx_type' => 'Serial',
                    'doc_no' => $doc_no,
                    'doc_date' => $row['sale_date'] ? date('Y-m-d', strtotime($row['sale_date'])) : date('Y-m-d', strtotime($row['created_at'])),
                    'party_name' => $row['product_name'],
                    'payment_mode' => 'SN: ' . $row['serial_number'],
                    'total_amt' => floatval($row['sell_price']),
                    'paid_amount' => 0,
                    'balance_amt' => 0,
                    'status' => ucfirst(str_replace('_', ' ', $row['status']))
                ];
                $summary_totals['count']++;
            }
        }
    } elseif ($report_type === 'bill_profit') {
        $sql = "SELECT i.id, i.invoice_no, i.created_at, i.customer_name, i.total_amount as revenue, 
                SUM(s.quantity * p.purchase_price) as cogs 
                FROM invoices i 
                JOIN sales s ON i.id = s.invoice_id 
                JOIN products p ON s.product_id = p.id 
                WHERE DATE(i.created_at) BETWEEN '$from_date' AND '$to_date' 
                GROUP BY i.id ORDER BY i.id DESC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rev = floatval($row['revenue']);
                $cogs = floatval($row['cogs']);
                $profit = $rev - $cogs;
                $report_rows[] = [
                    'tx_type' => 'Bill P&L',
                    'doc_no' => $row['invoice_no'],
                    'doc_date' => date('Y-m-d', strtotime($row['created_at'])),
                    'party_name' => $row['customer_name'],
                    'payment_mode' => 'COGS: ₹' . number_format($cogs, 2),
                    'total_amt' => $rev,
                    'paid_amount' => $profit,
                    'balance_amt' => $profit,
                    'status' => ($profit >= 0) ? 'Profit' : 'Loss'
                ];
                $summary_totals['total'] += $rev;
                $summary_totals['paid'] += $profit;
                $summary_totals['count']++;
            }
        }
    } else {
        $report_type = 'profit_loss';
        $sales_total = $conn->query("SELECT SUM(total_amount) as st FROM invoices WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();
        $purch_total = $conn->query("SELECT SUM(net_amount) as pt FROM purchases WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();
        
        $st = floatval($sales_total['st'] ?? 0);
        $pt = floatval($purch_total['pt'] ?? 0);
        $pnl = $st - $pt;
        
        $summary_totals['total'] = $st;
        $summary_totals['paid'] = $pt;
        $summary_totals['unpaid'] = $pnl;
        
        $report_rows[] = [
            'tx_type' => 'Summary',
            'doc_no' => 'P&L Statement',
            'doc_date' => $from_date . ' to ' . $to_date,
            'party_name' => 'Total Sales Inflow: ₹' . number_format($st, 2),
            'payment_mode' => 'Total Purchase Outflow: ₹' . number_format($pt, 2),
            'total_amt' => $st,
            'paid_amount' => $pt,
            'balance_amt' => $pnl,
            'status' => ($pnl >= 0) ? 'Net Profit' : 'Net Loss'
        ];
    }
} catch (Exception $e) {
    // Gracefully handle query error
}
?>

<div class="gb-page-wrap">
    <style>
        .gb-page-wrap { padding: 18px 20px 40px !important; }
        .reports-container { display: flex; gap: 16px; min-height: 82vh; }
        .reports-sidebar { width: 230px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 12px 0; flex-shrink: 0; box-shadow: 0 4px 18px rgba(0,0,0,0.03); }
        .rpt-group-title { font-size: 0.68rem; font-weight: 800; text-transform: uppercase; color: #64748b; padding: 10px 16px 4px; letter-spacing: 0.8px; }
        .rpt-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 16px; font-size: 0.84rem; color: #334155; text-decoration: none; font-weight: 500; transition: all 0.2s; border-left: 3px solid transparent; }
        .rpt-item:hover { background: #f0f9ff; color: #0284c7; padding-left: 19px; }
        .rpt-item.active { background: #e0f2fe; color: #0284c7; font-weight: 700; border-left-color: #0284c7; }
        .reports-main { flex: 1; min-width: 0; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px 22px; box-shadow: 0 4px 18px rgba(0,0,0,0.03); }
        .stat-card { border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .stat-paid { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; color: #166534; }
        .stat-unpaid { background: linear-gradient(135deg, #eff6ff, #dbeafe); border: 1px solid #93c5fd; color: #1e40af; }
        .stat-total { background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #fde68a; color: #92400e; }
        .stat-title { font-size: 0.7rem; text-transform: uppercase; font-weight: 700; opacity: 0.85; letter-spacing: 0.5px; }
        .stat-value { font-size: 1.25rem; font-weight: 800; margin-top: 1px; }
        .table th, .table td { padding: 10px 12px !important; font-size: 0.84rem !important; }
        @media print {
            #gb-navbar, #gb-sidebar, .reports-sidebar, .filter-row, .btn-print-hide { display: none !important; }
            .gb-page-wrap { margin: 0 !important; padding: 0 !important; }
            .reports-main { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>

    <div class="reports-container">
        <!-- Sidebar Navigation -->
        <div class="reports-sidebar">
            <div class="rpt-group-title"><i class="fas fa-exchange-alt me-1"></i> Transaction Reports</div>
            <a href="reports.php?cat=transaction&type=sale" class="rpt-item <?= ($report_type === 'sale') ? 'active' : '' ?>">Sale Reports <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=transaction&type=purchase" class="rpt-item <?= ($report_type === 'purchase') ? 'active' : '' ?>">Purchase Bills <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=transaction&type=daybook" class="rpt-item <?= ($report_type === 'daybook') ? 'active' : '' ?>">Day Book <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=transaction&type=all_tx" class="rpt-item <?= ($report_type === 'all_tx') ? 'active' : '' ?>">All Transactions <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=transaction&type=profit_loss" class="rpt-item <?= ($report_type === 'profit_loss') ? 'active' : '' ?>">Profit And Loss <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=transaction&type=bill_profit" class="rpt-item <?= ($report_type === 'bill_profit') ? 'active' : '' ?>">Bill-wise P&L <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>

            <div class="rpt-group-title mt-3"><i class="fas fa-users me-1"></i> Party Reports</div>
            <a href="reports.php?cat=party&type=all_parties" class="rpt-item <?= ($report_type === 'all_parties') ? 'active' : '' ?>">All Parties Summary <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="customer_ledger.php" class="rpt-item">Party Statement Ledger <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>

            <div class="rpt-group-title mt-3"><i class="fas fa-boxes me-1"></i> Item & Stock Reports</div>
            <a href="reports.php?cat=item&type=stock_summary" class="rpt-item <?= ($report_type === 'stock_summary') ? 'active' : '' ?>">Stock Summary <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=item&type=item_serials" class="rpt-item <?= ($report_type === 'item_serials') ? 'active' : '' ?>">Item Serial Numbers <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="low-stock.php" class="rpt-item">Low Stock Warning <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>

            <div class="rpt-group-title mt-3"><i class="fas fa-calculator me-1"></i> GST & Tax Reports</div>
            <a href="reports.php?cat=tax&type=tds_rec" class="rpt-item <?= ($report_type === 'tds_rec') ? 'active' : '' ?>">TDS Receivable <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="reports.php?cat=tax&type=tds_pay" class="rpt-item <?= ($report_type === 'tds_pay') ? 'active' : '' ?>">TDS Payable <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i></a>
            <a href="gstr1.php" class="rpt-item">GSTR-1 Report <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>
            <a href="gstr2.php" class="rpt-item">GSTR-2A Report <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>
            <a href="gstr3.php" class="rpt-item">GSTR-3B Report <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>
            <a href="trial_balance.php" class="rpt-item">Trial Balance <i class="fas fa-external-link-alt text-muted" style="font-size:0.7rem"></i></a>
        </div>

        <!-- Main Content Area -->
        <div class="reports-main">
            <div class="d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                <div>
                    <h4 class="fw-bold mb-1" style="color: #0f172a; text-transform: capitalize;"><?= str_replace('_', ' ', $report_type) ?> Report</h4>
                    <p class="text-muted small mb-0"><?= htmlspecialchars($company['company_name']) ?> — Statement overview</p>
                </div>
                <div class="btn-print-hide d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm rounded-3 shadow-sm" onclick="exportReportToExcel()" style="font-weight:600"><i class="fas fa-file-excel me-1"></i> Export to Excel</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-3" onclick="window.print()" style="font-weight:600"><i class="fas fa-print me-1"></i> Print / PDF</button>
                </div>
            </div>

            <!-- Date & Filter Row -->
            <form method="GET" action="reports.php" class="filter-row row g-3 mb-4 align-items-end">
                <input type="hidden" name="cat" value="<?= htmlspecialchars($report_cat) ?>">
                <input type="hidden" name="type" value="<?= htmlspecialchars($report_type) ?>">
                
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= htmlspecialchars($from_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= htmlspecialchars($to_date) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm rounded-3 w-100"><i class="fas fa-filter me-1"></i> Apply Filter</button>
                </div>
            </form>

            <!-- Summary KPI Bar -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card stat-paid">
                        <i class="fas fa-check-circle fa-2x"></i>
                        <div>
                            <div class="stat-title">Total Paid / Inflow</div>
                            <div class="stat-value">₹<?= number_format($summary_totals['paid'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-unpaid">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                        <div>
                            <div class="stat-title">Unpaid / Balance</div>
                            <div class="stat-value">₹<?= number_format($summary_totals['unpaid'], 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card stat-total">
                        <i class="fas fa-wallet fa-2x"></i>
                        <div>
                            <div class="stat-title">Net Total Value</div>
                            <div class="stat-value">₹<?= number_format($summary_totals['total'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <?php if ($report_type === 'item_serials'): ?>
                <!-- Vyapar Style Serial Report UI -->
                <div class="mb-3 d-flex gap-3 align-items-center">
                    <input type="text" id="serialSearchInput" class="form-control" placeholder="Item name" style="max-width: 300px;">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="inStockCheckbox">
                        <label class="form-check-label text-muted small fw-semibold" for="inStockCheckbox">
                            Show only serial numbers in stock
                        </label>
                    </div>
                </div>

                <div class="accordion" id="serialAccordion">
                    <?php 
                    // Group rows by product
                    $grouped = [];
                    foreach ($report_rows as $row) {
                        $prod = $row['party_name'];
                        if (!isset($grouped[$prod])) $grouped[$prod] = [];
                        $grouped[$prod][] = $row;
                    }
                    $acc_index = 0;
                    foreach ($grouped as $prod_name => $serials): 
                        $acc_index++;
                        $header_id = 'heading' . $acc_index;
                        $collapse_id = 'collapse' . $acc_index;
                    ?>
                    <div class="accordion-item serial-acc-item mb-2" style="border: 1px solid #e2e8f0; border-radius: 7px; overflow: hidden; background: #fff;">
                        <h2 class="accordion-header" id="<?= $header_id ?>">
                            <button class="accordion-button collapsed shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" style="background: #f8fafc; color: #0b1220; font-weight: 600; padding: 12px 16px; font-size: 0.9rem; border: none;">
                                <?= htmlspecialchars($prod_name) ?> <span class="badge bg-secondary ms-2"><?= count($serials) ?></span>
                            </button>
                        </h2>
                        <div id="<?= $collapse_id ?>" class="accordion-collapse collapse" data-bs-parent="#serialAccordion">
                            <div class="accordion-body p-0">
                                <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3">Serial No.</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Doc #</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($serials as $s): ?>
                                        <tr class="serial-row" data-status="<?= htmlspecialchars($s['status']) ?>">
                                            <td class="fw-semibold text-primary ps-3"><?= str_replace('SN: ', '', $s['payment_mode']) ?></td>
                                            <td>
                                                <?php
                                                    $st = strtolower($s['status']);
                                                    $bg = ($st == 'in stock' || $st == 'available') ? 'success' : (($st == 'sold' || $st == 'completed') ? 'secondary' : 'warning');
                                                ?>
                                                <span class="badge bg-<?= $bg ?>-subtle text-<?= $bg ?> border border-<?= $bg ?>"><?= htmlspecialchars($s['status']) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($s['doc_date']) ?></td>
                                            <td><?= htmlspecialchars($s['doc_no']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($grouped)): ?>
                        <div class="text-center py-4 text-muted border rounded bg-light">No serial numbers found.</div>
                    <?php endif; ?>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const searchInput = document.getElementById('serialSearchInput');
                        const inStockCheckbox = document.getElementById('inStockCheckbox');
                        
                        function filterSerials() {
                            const term = searchInput.value.toLowerCase();
                            const onlyInStock = inStockCheckbox.checked;
                            
                            document.querySelectorAll('.serial-acc-item').forEach(item => {
                                const prodName = item.querySelector('.accordion-button').textContent.toLowerCase();
                                let hasVisibleRow = false;
                                
                                item.querySelectorAll('.serial-row').forEach(row => {
                                    const status = row.getAttribute('data-status').toLowerCase();
                                    const isInStock = (status.includes('in stock') || status.includes('available') || status.includes('active'));
                                    
                                    let showRow = true;
                                    if (onlyInStock && !isInStock) showRow = false;
                                    
                                    if (showRow) {
                                        row.style.display = '';
                                        hasVisibleRow = true;
                                    } else {
                                        row.style.display = 'none';
                                    }
                                });
                                
                                let showItem = prodName.includes(term);
                                if (onlyInStock && !hasVisibleRow) showItem = false;
                                
                                item.style.display = showItem ? '' : 'none';
                            });
                        }
                        
                        if(searchInput) searchInput.addEventListener('input', filterSerials);
                        if(inStockCheckbox) inStockCheckbox.addEventListener('change', filterSerials);
                    });
                </script>

            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="reportsDataTable">
                        <thead class="table-light">
                            <tr style="font-size:0.82rem; text-transform:uppercase; letter-spacing:0.5px;">
                                <th>Date</th>
                                <th>Tx Type / Doc #</th>
                                <th>Party / Particulars</th>
                                <th>Payment Mode</th>
                                <th class="text-end">Total Amount</th>
                                <th class="text-end">Paid Amount</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody style="font-size:0.88rem;">
                            <?php if (empty($report_rows)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No report data found for selected period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($report_rows as $row): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= htmlspecialchars($row['doc_date']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['tx_type']) ?></span>
                                            <div class="fw-semibold text-primary"><?= htmlspecialchars($row['doc_no']) ?></div>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($row['party_name']) ?></td>
                                        <td><?= htmlspecialchars($row['payment_mode'] ?: 'N/A') ?></td>
                                        <td class="text-end fw-semibold">₹<?= number_format($row['total_amt'], 2) ?></td>
                                        <td class="text-end text-success">₹<?= number_format($row['paid_amount'], 2) ?></td>
                                        <td class="text-end text-danger">₹<?= number_format($row['balance_amt'], 2) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= ($row['balance_amt'] <= 0) ? 'bg-success-subtle text-success border border-success' : 'bg-warning-subtle text-warning border border-warning' ?>">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function exportReportToExcel() {
    const table = document.getElementById('reportsDataTable');
    if (!table) return;

    try {
        if (typeof XLSX !== 'undefined') {
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, "Report");
            const filename = "<?= $report_type ?>_report_" + new Date().toISOString().slice(0,10) + ".xlsx";
            XLSX.writeFile(wb, filename);
            return;
        }
    } catch(err) {
        console.error("XLSX export fallback:", err);
    }
    exportReportToCSV();
}

function exportReportToCSV() {
    const table = document.getElementById('reportsDataTable');
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""');
            row.push('"' + data.trim() + '"');
        }
        csv.push(row.join(','));
    }
    const csvString = csv.join('\n');
    const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = "<?= $report_type ?>_report_" + new Date().toISOString().slice(0,10) + ".csv";
    link.click();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
