```php
<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("Session user_id not set. Redirecting to login.");
    header("Location: login.php");
    exit();
}



$page_title = "MIS Reports";
$debug_messages = [];
$success_message = '';
$error_message = '';
$admin_id = (int)$_SESSION['user_id'];
$debug_messages[] = "Session user_id: $admin_id";

// Fetch company details
try {
    $result = $conn->query("SELECT company_name, company_address, company_gst, company_logo FROM company_details LIMIT 1");
    $company = $result->fetch_assoc();
    if (!$company) {
        $company = [
            'company_name' => 'Grow Biller Pvt Ltd',
            'company_address' => 'Baghmari Road',
            'company_gst' => '22AAAAA0000A1Z5',
            'company_logo' => ''
        ];
        $debug_messages[] = "No company details found, using defaults.";
    } else {
        $debug_messages[] = "Fetched company details.";
    }
} catch (Exception $e) {
    $error_message = "Error fetching company details: " . $e->getMessage();
    error_log("Company Details Fetch Error: " . $e->getMessage());
    $debug_messages[] = "Company Details Fetch Error: " . $e->getMessage();
}

// Handle form submission
$report_type = $_POST['report_type'] ?? 'profit_loss';
$report_month = $_POST['report_month'] ?? date('Y-m');
$report_data = [];

try {
    $date_obj = new DateTime($report_month . '-01');
    $year = $date_obj->format('Y');
    $month = $date_obj->format('m');
    $last_day = $date_obj->format('Y-m-t');

    if ($report_type == 'profit_loss') {
        $result = $conn->query("
            SELECT
                SUM(s.total_price) as revenue,
                SUM(pr.gross_salary + pr.pf_employer_contribution + pr.esi_employer_contribution) as payroll_expenses,
                SUM(p.total_price_without_gst * s.quantity / p.quantity) as cogs,
                SUM(CASE WHEN bt.transaction_type = 'debit' THEN bt.amount ELSE 0 END) as other_expenses,
                (SUM(s.total_price) - (SUM(pr.gross_salary + pr.pf_employer_contribution + pr.esi_employer_contribution) +
                                       COALESCE(SUM(p.total_price_without_gst * s.quantity / p.quantity), 0) +
                                       SUM(CASE WHEN bt.transaction_type = 'debit' THEN bt.amount ELSE 0 END))) as net_profit
            FROM sales s
            LEFT JOIN products p ON s.product_id = p.id
            LEFT JOIN payroll_records pr ON YEAR(s.created_at) = YEAR(pr.payroll_month)
                AND MONTH(s.created_at) = MONTH(pr.payroll_month)
            LEFT JOIN bank_transactions bt ON YEAR(bt.transaction_date) = YEAR(s.created_at)
                AND MONTH(bt.transaction_date) = MONTH(s.created_at)
            WHERE YEAR(s.created_at) = $year AND MONTH(s.created_at) = $month
        ");
        $report_data = $result->fetch_assoc();
        $debug_messages[] = "Fetched Profit & Loss data for $report_month";
    } elseif ($report_type == 'balance_sheet') {
        $result = $conn->query("
            SELECT
                SUM(CASE WHEN bt.transaction_type = 'credit' THEN bt.amount ELSE -bt.amount END) as cash_balance,
                SUM(CASE WHEN rp.type = 'receivable' AND rp.status = 'pending' THEN rp.amount ELSE 0 END) as receivables,
                SUM(CASE WHEN rp.type = 'payable' AND rp.status = 'pending' THEN rp.amount ELSE 0 END) as payables,
                SUM(pr.pf_employee_contribution + pr.pf_employer_contribution +
                    pr.esi_employee_contribution + pr.esi_employer_contribution) as pf_esi_liabilities
            FROM bank_transactions bt
            LEFT JOIN receivables_payables rp ON rp.due_date <= '$last_day'
            LEFT JOIN payroll_records pr ON pr.payroll_month <= '$last_day'
        ");
        $report_data = $result->fetch_assoc();
        $debug_messages[] = "Fetched Balance Sheet data for $last_day";
    } elseif ($report_type == 'cash_flow') {
        $result = $conn->query("
            SELECT
                SUM(s.total_price) as sales_inflow,
                SUM(pr.net_salary) as salary_outflow,
                SUM(CASE WHEN bt.transaction_type = 'credit' THEN bt.amount ELSE 0 END) as other_inflows,
                SUM(CASE WHEN bt.transaction_type = 'debit' THEN bt.amount ELSE 0 END) as other_outflows
            FROM sales s
            LEFT JOIN payroll_records pr ON YEAR(s.created_at) = YEAR(pr.payroll_month)
                AND MONTH(s.created_at) = MONTH(pr.payroll_month)
            LEFT JOIN bank_transactions bt ON YEAR(bt.transaction_date) = YEAR(s.created_at)
                AND MONTH(bt.transaction_date) = MONTH(s.created_at)
            WHERE YEAR(s.created_at) = $year AND MONTH(s.created_at) = $month
        ");
        $report_data = $result->fetch_assoc();
        $debug_messages[] = "Fetched Cash Flow data for $report_month";
    } elseif ($report_type == 'fund_flow') {
        $result = $conn->query("
            SELECT
                SUM(s.total_price) as sources,
                SUM(pr.net_salary) as uses,
                SUM(CASE WHEN rp.type = 'receivable' THEN rp.amount ELSE -rp.amount END) as net_working_capital
            FROM sales s
            LEFT JOIN payroll_records pr ON YEAR(s.created_at) = YEAR(pr.payroll_month)
                AND MONTH(s.created_at) = MONTH(pr.payroll_month)
            LEFT JOIN receivables_payables rp ON rp.due_date <= '$last_day'
            WHERE YEAR(s.created_at) = $year AND MONTH(s.created_at) = $month
        ");
        $report_data = $result->fetch_assoc();
        $debug_messages[] = "Fetched Fund Flow data for $report_month";
    }
} catch (Exception $e) {
    $error_message = "Error fetching report data: " . $e->getMessage();
    error_log("Report Fetch Error: " . $e->getMessage());
    $debug_messages[] = "Report Fetch Error: " . $e->getMessage();
}

file_put_contents('debug.log', implode("\n", $debug_messages) . "\n", FILE_APPEND);
?>

<style>
    :root {
        --primary: #007bff;
        --secondary: #6c757d;
        --background: #f5f7fa;
        --card-bg: #ffffff;
        --grey-light: #e9ecef;
        --text-primary: #333;
        --text-secondary: #6c757d;
        --orange: #fe9f43;
        --red: #ff3300;
        --green: #00b328;
    }

    .content {
        margin-top: 80px;
        margin-left: 250px;
        padding: 20px 30px;
        min-height: calc(100vh - 112px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .page-title h4 {
        font-weight: 700;
        margin: 0;
    }

    .page-title h6 {
        font-weight: 400;
        color: var(--text-secondary);
        margin: 0;
    }

    .page-btn {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-custom {
        background-color: var(--orange) !important;
        border-color: var(--orange) !important;
    }

    .btn-custom:hover {
        background-color: #e68f3b !important;
        border-color: #e68f3b !important;
    }

    .report-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .report-table th, .report-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid var(--grey-light);
    }

    .report-table th {
        background: var(--grey-light);
    }

    .alert-success {
        background-color: var(--green);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .alert-danger {
        background-color: var(--red);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 15px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .page-btn {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>MIS Reports</h4>
            <h6>Financial and Operational Insights</h6>
        </div>
        <div class="page-btn">
            <a href="#" class="btn btn-custom btn-sm" onclick="exportToPDF()"><i class="fas fa-file-pdf me-1"></i>Export to PDF</a>
            <a href="#" class="btn btn-custom btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel me-1"></i>Export to Excel</a>
            <a href="#" class="btn btn-custom btn-sm" onclick="shareViaWhatsApp()"><i class="fab fa-whatsapp me-1"></i>Share via WhatsApp</a>
        </div>
    </div>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <form method="post" action="mis_reports.php">
                <div class="row">
                    <div class="col-md-6">
                        <label for="report_type" class="form-label">Report Type</label>
                        <select class="form-control" id="report_type" name="report_type">
                            <option value="profit_loss" <?php echo $report_type == 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss Statement</option>
                            <option value="balance_sheet" <?php echo $report_type == 'balance_sheet' ? 'selected' : ''; ?>>Balance Sheet</option>
                            <option value="cash_flow" <?php echo $report_type == 'cash_flow' ? 'selected' : ''; ?>>Cash Flow Statement</option>
                            <option value="fund_flow" <?php echo $report_type == 'fund_flow' ? 'selected' : ''; ?>>Fund Flow Statement</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="report_month" class="form-label">Month</label>
                        <input type="month" class="form-control" id="report_month" name="report_month" value="<?php echo htmlspecialchars($report_month); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-custom btn-block">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <?php if ($report_type == 'profit_loss'): ?>
                <h5>Profit & Loss Statement for <?php echo $date_obj->format('F Y'); ?></h5>
                <table class="report-table" id="reportTable">
                    <tr><th colspan="2"><?php echo htmlspecialchars($company['company_name']); ?></th></tr>
                    <tr><th>Revenue</th><th>Amount (₹)</th></tr>
                    <tr><td>Sales Revenue</td><td><?php echo number_format($report_data['revenue'] ?? 0, 2); ?></td></tr>
                    <tr><th>Expenses</th><th></th></tr>
                    <tr><td>Payroll Expenses</td><td><?php echo number_format($report_data['payroll_expenses'] ?? 0, 2); ?></td></tr>
                    <tr><td>Cost of Goods Sold</td><td><?php echo number_format($report_data['cogs'] ?? 0, 2); ?></td></tr>
                    <tr><td>Other Expenses</td><td><?php echo number_format($report_data['other_expenses'] ?? 0, 2); ?></td></tr>
                    <tr><th>Net Profit</th><td><?php echo number_format($report_data['net_profit'] ?? 0, 2); ?></td></tr>
                </table>
            <?php elseif ($report_type == 'balance_sheet'): ?>
                <h5>Balance Sheet as of <?php echo $date_obj->format('F j, Y'); ?></h5>
                <table class="report-table" id="reportTable">
                    <tr><th colspan="2"><?php echo htmlspecialchars($company['company_name']); ?></th></tr>
                    <tr><th>Assets</th><th>Amount (₹)</th></tr>
                    <tr><td>Cash Balance</td><td><?php echo number_format($report_data['cash_balance'] ?? 0, 2); ?></td></tr>
                    <tr><td>Receivables</td><td><?php echo number_format($report_data['receivables'] ?? 0, 2); ?></td></tr>
                    <tr><th>Total Assets</th><td><?php echo number_format(($report_data['cash_balance'] ?? 0) + ($report_data['receivables'] ?? 0), 2); ?></td></tr>
                    <tr><th>Liabilities</th><th></th></tr>
                    <tr><td>Payables</td><td><?php echo number_format($report_data['payables'] ?? 0, 2); ?></td></tr>
                    <tr><td>PF/ESI Liabilities</td><td><?php echo number_format($report_data['pf_esi_liabilities'] ?? 0, 2); ?></td></tr>
                    <tr><th>Total Liabilities</th><td><?php echo number_format(($report_data['payables'] ?? 0) + ($report_data['pf_esi_liabilities'] ?? 0), 2); ?></td></tr>
                    <tr><th>Equity</th><td><?php echo number_format(($report_data['cash_balance'] ?? 0) + ($report_data['receivables'] ?? 0) - (($report_data['payables'] ?? 0) + ($report_data['pf_esi_liabilities'] ?? 0)), 2); ?></td></tr>
                </table>
            <?php elseif ($report_type == 'cash_flow'): ?>
                <h5>Cash Flow Statement for <?php echo $date_obj->format('F Y'); ?></h5>
                <table class="report-table" id="reportTable">
                    <tr><th colspan="2"><?php echo htmlspecialchars($company['company_name']); ?></th></tr>
                    <tr><th>Cash Inflows</th><th>Amount (₹)</th></tr>
                    <tr><td>Sales Inflow</td><td><?php echo number_format($report_data['sales_inflow'] ?? 0, 2); ?></td></tr>
                    <tr><td>Other Inflows</td><td><?php echo number_format($report_data['other_inflows'] ?? 0, 2); ?></td></tr>
                    <tr><th>Cash Outflows</th><th></th></tr>
                    <tr><td>Salary Outflow</td><td><?php echo number_format($report_data['salary_outflow'] ?? 0, 2); ?></td></tr>
                    <tr><td>Other Outflows</td><td><?php echo number_format($report_data['other_outflows'] ?? 0, 2); ?></td></tr>
                    <tr><th>Net Cash Flow</th><td><?php echo number_format(($report_data['sales_inflow'] ?? 0) + ($report_data['other_inflows'] ?? 0) - (($report_data['salary_outflow'] ?? 0) + ($report_data['other_outflows'] ?? 0)), 2); ?></td></tr>
                </table>
            <?php elseif ($report_type == 'fund_flow'): ?>
                <h5>Fund Flow Statement for <?php echo $date_obj->format('F Y'); ?></h5>
                <table class="report-table" id="reportTable">
                    <tr><th colspan="2"><?php echo htmlspecialchars($company['company_name']); ?></th></tr>
                    <tr><th>Sources</th><th>Amount (₹)</th></tr>
                    <tr><td>Sales</td><td><?php echo number_format($report_data['sources'] ?? 0, 2); ?></td></tr>
                    <tr><th>Uses</th><th></th></tr>
                    <tr><td>Salaries</td><td><?php echo number_format($report_data['uses'] ?? 0, 2); ?></td></tr>
                    <tr><th>Net Working Capital Change</th><td><?php echo number_format($report_data['net_working_capital'] ?? 0, 2); ?></td></tr>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({ html: '#reportTable' });
    doc.save('mis_report_<?php echo $report_type; ?>.pdf');
}

function exportToExcel() {
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.table_to_book(table, { sheet: "MIS Report" });
    XLSX.writeFile(wb, 'mis_report_<?php echo $report_type; ?>.xlsx');
}

function shareViaWhatsApp() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({ html: '#reportTable' });
    const pdfBlob = doc.output('blob');
    const pdfUrl = URL.createObjectURL(pdfBlob);
    const message = encodeURIComponent('MIS Report: <?php echo $report_type; ?> for <?php echo $date_obj->format('F Y'); ?> from <?php echo htmlspecialchars($company['company_name']); ?>. Download: ' + pdfUrl);
    const whatsappUrl = 'https://api.whatsapp.com/send?text=' + message;
    window.open(whatsappUrl, '_blank');
}
</script>

<?php
ob_end_flush();
?>
