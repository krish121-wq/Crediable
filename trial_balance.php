<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['sidebar_open'])) {
    $_SESSION['sidebar_open'] = true;
}

require_once 'config.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=trial_balance.php");
    exit();
}

$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-01');
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');
$trial_balance_data = [];
$total_debit = 0;
$total_credit = 0;
$show_results = false;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $show_results = true;
    
    try {
     
        $ledger_accounts = [];
        
        // Sales accounts (Revenue - Credit)
        $sales_result = $conn->query("
            SELECT 'Sales Revenue' as account_name, SUM(total_price) as balance, 'Credit' as account_type
            FROM sales 
            WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
        ");
        $sales_row = $sales_result->fetch_assoc();
        if ($sales_row && $sales_row['balance'] > 0) {
            $ledger_accounts[] = $sales_row;
        }
        
        // Cash Bill Revenue (Credit)
        $cash_bill_result = $conn->query("
            SELECT 'Cash Bill Revenue' as account_name, SUM(total_amount) as balance, 'Credit' as account_type
            FROM cash_bills 
            WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
        ");
        $cash_bill_row = $cash_bill_result->fetch_assoc();
        if ($cash_bill_row && $cash_bill_row['balance'] > 0) {
            $ledger_accounts[] = $cash_bill_row;
        }
        
        // Service Revenue (Credit)
        $service_result = $conn->query("
            SELECT 'Service Revenue' as account_name, SUM(total_amount) as balance, 'Credit' as account_type
            FROM service_invoices 
            WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date'
        ");
        $service_row = $service_result->fetch_assoc();
        if ($service_row && $service_row['balance'] > 0) {
            $ledger_accounts[] = $service_row;
        }
        
        // Purchase accounts (Expense - Debit)
        $purchase_result = $conn->query("
            SELECT 'Purchases' as account_name, SUM(net_amount) as balance, 'Debit' as account_type
            FROM purchases 
            WHERE DATE(date) BETWEEN '$from_date' AND '$to_date'
        ");
        $purchase_row = $purchase_result->fetch_assoc();
        if ($purchase_row && $purchase_row['balance'] > 0) {
            $ledger_accounts[] = $purchase_row;
        }
        
        // Cash/Bank accounts (Asset - Debit)
        $cash_in_result = $conn->query("
            SELECT 'Received Amount' as account_name, 
                   (SELECT COALESCE(SUM(amount), 0) FROM received_payments WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date') +
                   (SELECT COALESCE(SUM(amount), 0) FROM cash_bill_payments WHERE DATE(payment_date) BETWEEN '$from_date' AND '$to_date') +
                   (SELECT COALESCE(SUM(payment_amount), 0) FROM service_invoice_payments WHERE DATE(created_at) BETWEEN '$from_date' AND '$to_date') as balance, 
                   'Debit' as account_type
        ");
        $cash_in_row = $cash_in_result->fetch_assoc();
        if ($cash_in_row && $cash_in_row['balance'] > 0) {
            $ledger_accounts[] = $cash_in_row;
        }
        
        // Payroll Expenses (Debit)
        $payroll_result = $conn->query("
            SELECT 'Payroll Expenses' as account_name, 
                   COALESCE(SUM(net_salary + pf_employer_contribution + esi_employer_contribution), 0) as balance, 
                   'Debit' as account_type
            FROM payroll_records 
            WHERE DATE(payroll_month) BETWEEN '$from_date' AND '$to_date'
        ");
        $payroll_row = $payroll_result->fetch_assoc();
        if ($payroll_row && $payroll_row['balance'] > 0) {
            $ledger_accounts[] = $payroll_row;
        }
        
        // Expenses (Debit)
        $expense_result = $conn->query("
            SELECT 'Other Expenses' as account_name, SUM(amount) as balance, 'Debit' as account_type
            FROM expenses 
            WHERE DATE(expense_date) BETWEEN '$from_date' AND '$to_date'
        ");
        $expense_row = $expense_result->fetch_assoc();
        if ($expense_row && $expense_row['balance'] > 0) {
            $ledger_accounts[] = $expense_row;
        }
        
        // Accounts Receivable (Asset - Debit)
        $receivable_result = $conn->query("
            SELECT 'Accounts Receivable (sales)' as account_name, 
                   SUM(outstanding) as balance, 
                   'Debit' as account_type
            FROM (
                SELECT s.invoice_id, 
                       s.total_price as total_sale,
                       COALESCE(SUM(rp.amount), 0) as paid_amount,
                       (s.total_price - COALESCE(SUM(rp.amount), 0)) as outstanding
                FROM sales s
                LEFT JOIN received_payments rp ON s.invoice_id = rp.invoice_id
                WHERE DATE(s.created_at) BETWEEN '$from_date' AND '$to_date'
                GROUP BY s.invoice_id, s.total_price
                HAVING total_sale > COALESCE(SUM(rp.amount), 0)
            ) as receivables
        ");
        $receivable_row = $receivable_result->fetch_assoc();
        if ($receivable_row && $receivable_row['balance'] > 0) {
            $ledger_accounts[] = $receivable_row;
        }
        
        // Accounts Receivable for Cash Bills (Asset - Debit)
        $cash_bill_receivable_result = $conn->query("
            SELECT 'Accounts Receivable (Cash Bill)' as account_name, 
                   SUM(outstanding) as balance, 
                   'Debit' as account_type
            FROM (
                SELECT cb.id, 
                       cb.total_amount as total_bill,
                       COALESCE(SUM(cbp.amount), 0) as paid_amount,
                       (cb.total_amount - COALESCE(SUM(cbp.amount), 0)) as outstanding
                FROM cash_bills cb
                LEFT JOIN cash_bill_payments cbp ON cb.id = cbp.cash_bill_id
                WHERE DATE(cb.created_at) BETWEEN '$from_date' AND '$to_date'
                GROUP BY cb.id, cb.total_amount
                HAVING total_bill > COALESCE(SUM(cbp.amount), 0)
            ) as cash_bill_receivables
        ");
        $cash_bill_receivable_row = $cash_bill_receivable_result->fetch_assoc();
        if ($cash_bill_receivable_row && $cash_bill_receivable_row['balance'] > 0) {
            $ledger_accounts[] = $cash_bill_receivable_row;
        }
        
        // Accounts Receivable for Service Invoices (Asset - Debit)
        $service_receivable_result = $conn->query("
            SELECT 'Accounts Receivable (Service)' as account_name, 
                   SUM(outstanding) as balance, 
                   'Debit' as account_type
            FROM (
                SELECT si.id, 
                       si.total_amount as total_invoice,
                       COALESCE(SUM(sip.payment_amount), 0) as paid_amount,
                       (si.total_amount - COALESCE(SUM(sip.payment_amount), 0)) as outstanding
                FROM service_invoices si
                LEFT JOIN service_invoice_payments sip ON si.id = sip.invoice_id
                WHERE DATE(si.created_at) BETWEEN '$from_date' AND '$to_date'
                GROUP BY si.id, si.total_amount
                HAVING total_invoice > COALESCE(SUM(sip.payment_amount), 0)
            ) as service_receivables
        ");
        $service_receivable_row = $service_receivable_result->fetch_assoc();
        if ($service_receivable_row && $service_receivable_row['balance'] > 0) {
            $ledger_accounts[] = $service_receivable_row;
        }
        
        // Accounts Payable (Liability - Credit)
        $payable_result = $conn->query("
            SELECT 'Accounts Payable' as account_name, 
                   SUM(net_amount) as balance, 
                   'Credit' as account_type
            FROM purchases 
            WHERE DATE(date) BETWEEN '$from_date' AND '$to_date'
            AND type = 'Credit'
        ");
        $payable_row = $payable_result->fetch_assoc();
        if ($payable_row && $payable_row['balance'] > 0) {
            $ledger_accounts[] = $payable_row;
        }
        
        // Calculate totals
        foreach ($ledger_accounts as $account) {
            if ($account['account_type'] === 'Debit') {
                $total_debit += $account['balance'];
            } else {
                $total_credit += $account['balance'];
            }
        }
        
        $trial_balance_data = $ledger_accounts;
        
    } catch (Exception $e) {
        $error_message = "Error generating trial balance: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Trial Balance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0284c7;
            --secondary: #0b1220;
            --text-primary: #0b1220;
            --text-muted: #475569;
            --card-bg: #FFFFFF;
            --main-bg: #f4f8fb;
            --border: #e2e8f0;
            --success: #16a34a;
            --danger: #dc2626;
            --radius: 4px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--main-bg);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
        }

        /* Layout */
        .trial-balance {
            margin-left: 280px;
            margin-top: 60px;
            padding: 12px 16px;
            min-height: calc(100vh - 60px);
        }

        .trial-balance-full {
            margin-left: 0;
        }

        .content {
            background: var(--card-bg);
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(2,132,199,0.1);
            padding: 16px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .content h3 {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Form Section */
        .date-filter {
            background: #f4f8fb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .date-filter h4 {
            color: var(--secondary);
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-size: 13px;
        }

        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            height: 38px;
        }

        .btn-generate {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            height: 38px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-generate:hover {
            background: #0b1220;
            transform: translateY(-1px);
        }

        /* Trial Balance Table */
        .trial-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid var(--border);
            font-size: 14px;
            margin-top: 20px;
        }

        .trial-table th {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            padding: 12px 10px;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            border: 1px solid var(--secondary);
        }

        .trial-table td {
            padding: 10px 12px;
            border: 1px solid var(--border);
            vertical-align: middle;
        }

        .trial-table .account-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .trial-table .debit-amount, .trial-table .credit-amount {
            text-align: right;
            font-weight: 500;
            font-family: 'Courier New', monospace;
        }

        .trial-table .debit-amount {
            color: var(--danger);
        }

        .trial-table .credit-amount {
            color: var(--success);
        }

        .trial-table .total-row {
            background: #e0f2fe;
            font-weight: 700;
            font-size: 15px;
        }

        .trial-table .total-row td {
            border-top: 2px solid var(--secondary);
        }

        .trial-table .total-amount {
            font-size: 16px;
            font-weight: 700;
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-size: 16px;
        }

        .no-results i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }

        /* Print Styles */
        @media print {
            .date-filter, .btn-generate {
                display: none !important;
            }
            .trial-balance {
                margin: 0;
                padding: 0;
            }
            .content {
                box-shadow: none;
                border: none;
                padding: 10px;
            }
            .trial-table {
                font-size: 12px;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .trial-balance {
                margin-left: 0;
                padding: 10px 12px;
            }
            .sidebar-toggle {
                display: block;
            }
            .content {
                padding: 12px;
            }
        }

        @media (max-width: 768px) {
            .content h3 {
                font-size: 1.3rem;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .btn-generate {
                width: 100%;
                justify-content: center;
            }
            
            .trial-table {
                font-size: 12px;
            }
            
            .trial-table th, .trial-table td {
                padding: 8px 6px;
            }
            
            .trial-table .account-name {
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .trial-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .date-filter {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<?php include('layout.php'); ?>
<button class="sidebar-toggle" id="sidebarToggle">Menu</button>

<div class="trial-balance">
    <div class="content">
        <h3><i class="fas fa-balance-scale"></i> TRIAL BALANCE</h3>

        <!-- Date Filter Form -->
        <div class="date-filter">
            <h4><i class="fas fa-calendar-alt"></i> Select Date Range</h4>
            <form method="POST" id="trialBalanceForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="from_date">From Date</label>
                        <input type="date" name="from_date" id="from_date" value="<?php echo $from_date; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="to_date">To Date</label>
                        <input type="date" name="to_date" id="to_date" value="<?php echo $to_date; ?>" required>
                    </div>
                    <button type="submit" name="generate_report" class="btn-generate">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <?php if ($show_results): ?>
            <?php if (!empty($trial_balance_data)): ?>
                <!-- Trial Balance Table -->
                <table class="trial-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 45%;">Account Name</th>
                            <th style="width: 25%;">Debit (₹)</th>
                            <th style="width: 25%;">Credit (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $serial = 1;
                        foreach ($trial_balance_data as $account): 
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 600;"><?php echo $serial++; ?></td>
                            <td class="account-name"><?php echo htmlspecialchars($account['account_name']); ?></td>
                            <td class="debit-amount">
                                <?php echo $account['account_type'] === 'Debit' ? number_format($account['balance'], 2) : ''; ?>
                            </td>
                            <td class="credit-amount">
                                <?php echo $account['account_type'] === 'Credit' ? number_format($account['balance'], 2) : ''; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <!-- Total Row -->
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right; font-weight: 700;">TOTAL</td>
                            <td class="debit-amount total-amount">₹<?php echo number_format($total_debit, 2); ?></td>
                            <td class="credit-amount total-amount">₹<?php echo number_format($total_credit, 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Balance Check -->
                <div style="margin-top: 20px; padding: 15px; background: #f4f8fb; border-radius: 6px; text-align: center;">
                    <?php 
                    $difference = abs($total_debit - $total_credit);
                    if ($difference < 0.01): 
                    ?>
                        <div style="color: var(--success); font-weight: 600;">
                            <i class="fas fa-check-circle"></i> Trial Balance is Balanced! 
                            <span style="font-size: 12px;">(Difference: ₹<?php echo number_format($difference, 2); ?>)</span>
                        </div>
                    <?php else: ?>
                        <div style="color: var(--danger); font-weight: 600;">
                            <i class="fas fa-exclamation-triangle"></i> Trial Balance is NOT Balanced! 
                            <span style="font-size: 12px;">(Difference: ₹<?php echo number_format($difference, 2); ?>)</span>
                        </div>
                    <?php endif; ?>
                </div>

                
                

            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-inbox"></i>
                    <h5>No transactions found</h5>
                    <p>There are no transactions in the selected date range.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div style="background: #fee2e2; color: var(--danger); padding: 15px; border-radius: 6px; margin-top: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Sidebar Toggle Logic
    function toggleSidebar() {
        if ($(window).width() <= 992) {
            $('.sidebar').addClass('sidebar-hidden').removeClass('active');
            $('.trial-balance').addClass('trial-balance-full');
            $('#sidebarToggle').show();
        } else {
            $('.sidebar').removeClass('sidebar-hidden').addClass('active');
            $('.trial-balance').removeClass('trial-balance-full');
            $('#sidebarToggle').hide();
        }
    }
    
    toggleSidebar();
    $(window).on('resize', toggleSidebar);
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('sidebar-hidden active');
        $('.trial-balance').toggleClass('trial-balance-full');
    });

    // Set max date to today
    $('#to_date').attr('max', new Date().toISOString().split('T')[0]);
    $('#from_date').attr('max', new Date().toISOString().split('T')[0]);

    // Ensure from_date is not after to_date
    $('#from_date').on('change', function() {
        $('#to_date').attr('min', $(this).val());
    });

    $('#to_date').on('change', function() {
        $('#from_date').attr('max', $(this).val());
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>