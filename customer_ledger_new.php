<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('layout.php');

$page_title = "Customer Ledger - NEW";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];

if ($success_message) {
    unset($_SESSION['success_message']);
}

// Get customers for dropdown
try {
    $stmt = $conn->prepare("SELECT id, name, phone, address FROM customers ORDER BY name ASC");
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    $customers = [];
}

// Initialize variables
$selected_customer_id = $_POST['customer_id'] ?? $_GET['customer_id'] ?? '';
$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? date('Y-m-d');
$ledger_entries = [];
$customer_info = null;
$opening_balance = 0;
$closing_balance = 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' || !empty($selected_customer_id)) {
    try {
        $customer_id = (int)$selected_customer_id;
        
        if ($customer_id <= 0) {
            throw new Exception("Please select a customer");
        }
        
        // Get customer information
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $customer_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$customer_info) {
            throw new Exception("Customer not found");
        }
        
        // Calculate opening balance (transactions before from_date)
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'invoice' THEN total_amount ELSE 0 END), 0) as total_debit,
                COALESCE(SUM(CASE WHEN type = 'payment' THEN payment_amount ELSE 0 END), 0) as total_credit
            FROM (
                SELECT 'invoice' as type, total_amount, 0 as payment_amount, invoice_date as transaction_date
                FROM service_invoices 
                WHERE customer_id = ? AND invoice_date < ?
                
                UNION ALL
                
                SELECT 'payment' as type, 0 as total_amount, payment_amount, DATE(created_at) as transaction_date
                FROM service_invoice_payments 
                WHERE customer_id = ? AND DATE(created_at) < ?
            ) as transactions
        ");
        $stmt->bind_param("isis", $customer_id, $from_date, $customer_id, $from_date);
        $stmt->execute();
        $opening_result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $opening_balance = $opening_result['total_debit'] - $opening_result['total_credit'];
        
        // Get invoices for the selected period
        $stmt = $conn->prepare("
            SELECT 
                'invoice' as type,
                id,
                invoice_no as reference_no,
                invoice_date as transaction_date,
                total_amount as debit_amount,
                0 as credit_amount,
                CONCAT('Invoice #', invoice_no) as description,
                created_at
            FROM service_invoices 
            WHERE customer_id = ? AND invoice_date BETWEEN ? AND ?
            ORDER BY invoice_date ASC, created_at ASC
        ");
        $stmt->bind_param("iss", $customer_id, $from_date, $to_date);
        $stmt->execute();
        $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get payments for the selected period
        $stmt = $conn->prepare("
            SELECT 
                'payment' as type,
                id,
                CONCAT('PAY', LPAD(id, 6, '0')) as reference_no,
                DATE(created_at) as transaction_date,
                0 as debit_amount,
                payment_amount as credit_amount,
                CONCAT('Payment - ', payment_method) as description,
                created_at
            FROM service_invoice_payments 
            WHERE customer_id = ? AND DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("iss", $customer_id, $from_date, $to_date);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Debug output
        error_log("LEDGER DEBUG - Customer: {$customer_id}, Invoices: " . count($invoices) . ", Payments: " . count($payments));
        
        // Merge invoices and payments
        $ledger_entries = array_merge($invoices, $payments);
        
        // Sort by transaction_date and created_at
        usort($ledger_entries, function($a, $b) {
            $dateCompare = strcmp($a['transaction_date'], $b['transaction_date']);
            if ($dateCompare === 0) {
                return strcmp($a['created_at'], $b['created_at']);
            }
            return $dateCompare;
        });
        
        // Calculate running balance and closing balance
        $running_balance = $opening_balance;
        $ledger_entries_with_balance = [];
        foreach ($ledger_entries as $entry) {
            $running_balance += $entry['debit_amount'] - $entry['credit_amount'];
            $new_entry = $entry;
            $new_entry['balance'] = $running_balance;
            $ledger_entries_with_balance[] = $new_entry;
        }
        $ledger_entries = $ledger_entries_with_balance;
        $closing_balance = $running_balance;
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $ledger_entries = [];
        $customer_info = null;
    }
}
?>

<style>
    :root {
        --primary: #571F5A;
        --primary-light: #7A2E80;
        --success: #22c55e;
        --danger: #ef4444;
        --warning: #f5650b;
        --text-dark: #1A202C;
        --text-light: #ffffff;
        --text-muted: #6B7280;
        --bg-main: #F4F5F7;
        --card-bg: #ffffff;
        --border: #E5E7EB;
        --radius: 8px;
        --shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg-main);
        color: var(--text-dark);
        font-size: 14.2px;
        line-height: 1.5;
    }

    .content {
        margin-top: 65px;
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 65px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: var(--text-light);
        padding: 18px 24px;
        border-radius: var(--radius);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-title h4 {
        font-size: 1.55rem;
        font-weight: 700;
        margin: 0;
    }

    .page-title h6 {
        font-size: 0.92rem;
        opacity: 0.85;
        margin-top: 4px;
    }

    .page-btn-group {
        display: flex;
        gap: 14px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-section {
        background: var(--card-bg);
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 15px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-dark);
        font-size: 14px;
    }

    .form-control {
        padding: 10px 12px;
        border: 2px solid var(--border);
        border-radius: 6px;
        font-size: 14.2px;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(87,31,90,0.12);
        outline: none;
    }

    .btn-filter {
        background: linear-gradient(135deg, var(--primary), var(--primary-light));
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        height: fit-content;
    }

    .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(87,31,90,0.3);
    }

    .customer-info {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        padding: 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 20px;
    }

    .customer-info h5 {
        font-size: 1.2rem;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .customer-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
    }

    .customer-detail {
        display: flex;
        flex-direction: column;
    }

    .customer-detail label {
        font-size: 0.85rem;
        opacity: 0.8;
        margin-bottom: 2px;
    }

    .customer-detail span {
        font-size: 1rem;
        font-weight: 500;
    }

    .balance-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .balance-card {
        padding: 18px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        color: white;
    }

    .balance-card.opening {
        background: linear-gradient(135deg, #667eea, #764ba2);
    }

    .balance-card.closing {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }

    .balance-label {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }

    .balance-amount {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .table-responsive {
        background: var(--card-bg);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background: var(--primary);
        color: white;
        padding: 12px 10px;
        font-size: 12.8px;
        font-weight: 600;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
    }

    .table td {
        padding: 14px 12px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: 14.2px;
    }

    .table tr:hover {
        background: #f9fafb;
    }

    .table .debit {
        color: var(--danger);
        font-weight: 600;
    }

    .table .credit {
        color: var(--success);
        font-weight: 600;
    }

    .table .balance {
        font-weight: 700;
        color: var(--primary);
    }

    .reference-no {
        font-family: 'Courier New', monospace;
        background: #f3f4f6;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.9rem;
    }

    .type-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .type-badge.invoice {
        background: #fef3c7;
        color: #92400e;
    }

    .type-badge.payment {
        background: #d1fae5;
        color: #065f46;
    }

    .alert-success, .alert-danger {
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 18px;
        font-weight: 500;
    }

    .alert-success { background: var(--success); color: white; }
    .alert-danger  { background: var(--danger); color: white; }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    @media (max-width: 1024px) {
        .content {
            margin-left: 0;
            padding: 18px;
        }
    }

    @media (max-width: 768px) {
        .content {
            padding: 14px 12px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-row {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .customer-details {
            grid-template-columns: 1fr;
        }
        
        .balance-cards {
            grid-template-columns: 1fr;
        }
        
        .table th, .table td {
            padding: 10px 8px;
            font-size: 13px;
        }
    }

    @media (max-width: 480px) {
        .content {
            padding: 12px 10px;
        }
        
        .customer-info {
            padding: 15px;
        }
        
        .balance-card {
            padding: 15px;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>Customer Ledger - NEW</h4>
            <h6>View customer transaction history and balances</h6>
        </div>
        <div class="page-btn-group">
            <a href="dashboard.php" class="btn btn-custom btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="post" action="customer_ledger_new.php">
            <div class="filter-row">
                <div class="form-group">
                    <label for="customer_id" class="form-label">Select Customer *</label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <option value="">Choose Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($selected_customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name'] . ' - ' . $customer['phone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div class="form-group">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-search me-1"></i>Show Ledger
                </button>
            </div>
        </form>
    </div>

    <?php if ($customer_info): ?>
        <!-- Customer Information -->
        <div class="customer-info">
            <h5><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($customer_info['name']); ?></h5>
            <div class="customer-details">
                <div class="customer-detail">
                    <label>Phone</label>
                    <span><?php echo htmlspecialchars($customer_info['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="customer-detail">
                    <label>Address</label>
                    <span><?php echo htmlspecialchars($customer_info['address'] ?? 'N/A'); ?></span>
                </div>
                <div class="customer-detail">
                    <label>State</label>
                    <span><?php echo htmlspecialchars($customer_info['state'] ?? 'N/A'); ?></span>
                </div>
                <div class="customer-detail">
                    <label>GSTIN</label>
                    <span><?php echo htmlspecialchars($customer_info['gstin'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Balance Cards -->
        <div class="balance-cards">
            <div class="balance-card opening">
                <div class="balance-label">Opening Balance</div>
                <div class="balance-amount">₹ <?php echo number_format(abs($opening_balance), 2); ?></div>
                <small><?php echo $opening_balance >= 0 ? 'Receivable' : 'Payable'; ?></small>
            </div>
            <div class="balance-card closing">
                <div class="balance-label">Closing Balance</div>
                <div class="balance-amount">₹ <?php echo number_format(abs($closing_balance), 2); ?></div>
                <small><?php echo $closing_balance >= 0 ? 'Receivable' : 'Payable'; ?></small>
            </div>
        </div>

        <!-- Debug Info -->
        <div style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px; font-family: monospace; font-size: 12px;">
            <strong>DEBUG INFO:</strong> 
            Invoices Found: <?php echo count($invoices ?? []); ?> | 
            Payments Found: <?php echo count($payments ?? []); ?> | 
            Total Entries: <?php echo count($ledger_entries); ?>
        </div>

        <!-- Ledger Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference No.</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ledger_entries)): ?>
                        <?php foreach ($ledger_entries as $entry): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($entry['transaction_date'])); ?></td>
                                <td>
                                    <span class="type-badge <?php echo $entry['type']; ?>">
                                        <?php echo $entry['type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="reference-no">
                                        <?php echo htmlspecialchars($entry['reference_no']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($entry['description']); ?></td>
                                <td class="debit">
                                    <?php echo $entry['debit_amount'] > 0 ? number_format($entry['debit_amount'], 2) : '-'; ?>
                                </td>
                                <td class="credit">
                                    <?php echo $entry['credit_amount'] > 0 ? number_format($entry['credit_amount'], 2) : '-'; ?>
                                </td>
                                <td class="balance">
                                    ₹ <?php echo number_format(abs($entry['balance']), 2); ?>
                                    <small>(<?php echo $entry['balance'] >= 0 ? 'Dr' : 'Cr'; ?>)</small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No transactions found for the selected period</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Auto-hide success message
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 3000);
    }
    
    // Set date constraints
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('to_date').max = today;
    document.getElementById('from_date').max = today;
    
    // Auto-adjust from date when to date changes
    document.getElementById('to_date').addEventListener('change', function() {
        const fromDate = document.getElementById('from_date');
        if (fromDate.value > this.value) {
            fromDate.value = this.value;
        }
    });
});
</script>

<?php
ob_end_flush();
?>
