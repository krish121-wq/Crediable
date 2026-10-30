<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('layout.php');

$page_title = "Received Payments History";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];

if ($success_message) {
    unset($_SESSION['success_message']);
}


$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? date('Y-m-d');
$customer_filter = $_POST['customer_filter'] ?? $_GET['customer_filter'] ?? '';
$payments = [];
$total_received = 0;


try {
    $stmt = $conn->prepare("SELECT id, name, phone FROM customers ORDER BY name ASC");
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    $customers = [];
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' || !empty($from_date)) {
    try {
     
        $query = "
            SELECT 
                sip.*,
                si.invoice_no,
                si.customer_name,
                si.customer_phone,
                c.name as customer_name_from_customers,
                c.phone as customer_phone_from_customers,
                c.address as customer_address,
                CASE
                    WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                    WHEN staff_users.name IS NOT NULL THEN staff_users.name
                    ELSE 'Unknown'
                END AS received_by_name
            FROM service_invoice_payments sip
            LEFT JOIN service_invoices si ON sip.invoice_id = si.id
            LEFT JOIN customers c ON sip.customer_id = c.id
            LEFT JOIN users admin_users ON sip.created_by = admin_users.id
            LEFT JOIN create_users staff_users ON sip.created_by = staff_users.id
            WHERE DATE(sip.created_at) BETWEEN ? AND ?
        ";
        
        $params = [$from_date, $to_date];
        $types = "ss";
        
       
        if (!empty($customer_filter)) {
            $query .= " AND sip.customer_id = ?";
            $params[] = $customer_filter;
            $types .= "i";
        }
        
     
        if ($user_type !== 'admin') {
            $query .= " AND sip.created_by = ?";
            $params[] = $created_by_id;
            $types .= "i";
        }
        
        $query .= " ORDER BY sip.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
       
        $total_received = array_sum(array_column($payments, 'payment_amount'));
        
    } catch (Exception $e) {
        $error_message = "Error fetching payments: " . $e->getMessage();
        $payments = [];
    }
}
?>

<!-- IMPORTING FONTS & ICONS -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    /* ============================================================
       DESIGN TOKENS — matches global CREDIBLE theme
    ============================================================ */
    :root {
        --brand:       #0284c7;
        --brand-dark:  #0b1220;
        --brand-mid:   #0ea5e9;
        --brand-light: #e0f2fe;
        --success:     #16a34a;
        --success-bg:  #dcfce7;
        --danger:      #dc2626;
        --danger-bg:   #fee2e2;
        --warning:     #d97706;
        --warning-bg:  #fef3c7;
        --info:        #0284c7;
        --info-bg:     #e0f2fe;
        --bg:          #f4f8fb;
        --card:        #ffffff;
        --border:      #e2e8f0;
        --text:        #0b1220;
        --text-mid:    #475569;
        --text-muted:  #94a3b8;
        --radius-sm:   7px;
        --radius-md:   12px;
        --radius-lg:   18px;
        --shadow-sm:   0 2px 8px rgba(2,132,199,.07);
        --shadow-md:   0 8px 24px rgba(2,132,199,.11);
        --shadow-lg:   0 16px 48px rgba(2,132,199,.16);
        --transition:  all .25s cubic-bezier(.4,0,.2,1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 14.2px;
        line-height: 1.5;
    }

    .content {
        margin-top: 65px;
        margin-left: 260px;
        padding: 24px 28px 60px;
        min-height: calc(100vh - 65px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    /* ── PAGE HERO ── */
    .page-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
        flex-wrap: wrap;
    }
    .page-hero::before {
        content: ''; position: absolute;
        right: -50px; top: -50px;
        width: 200px; height: 200px;
        border-radius: 50%; background: rgba(255,255,255,.06);
    }
    .page-title h4 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.45rem; font-weight: 800; color: #fff;
        margin: 0 0 4px 0;
    }
    .page-title h6 { color: rgba(255,255,255,.75); font-size: 0.85rem; margin: 0; font-weight: 500; }
    .page-btn-group { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; position: relative; z-index: 10; }

    /* FILTER SECTION */
    .filter-section {
        background: var(--card);
        padding: 24px;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        margin-bottom: 24px;
    }

    .filter-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1.5fr auto;
        gap: 16px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        font-weight: 700;
        margin-bottom: 6px;
        color: var(--text-mid);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .form-control {
        padding: 10px 14px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        transition: var(--transition);
        background: #fafafa;
        font-family: 'Inter', sans-serif;
        color: var(--text);
        width: 100%;
        outline: none;
    }

    .form-control:focus {
        border-color: var(--brand);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
    }

    .btn-filter {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: white;
        border: none;
        padding: 11px 24px;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: var(--transition);
        height: fit-content;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(2,132,199,0.25);
    }

    .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 22px rgba(2,132,199,0.35);
    }

    /* SUMMARY CARDS */
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 24px;
    }

    .summary-card {
        padding: 22px 26px;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border: none;
    }

    .summary-card.total {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .summary-card.count {
        background: linear-gradient(135deg, var(--brand-mid), var(--brand));
    }

    .summary-label {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .summary-value {
        font-size: 1.8rem;
        font-weight: 800;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    /* TABLE */
    .table-responsive {
        background: var(--card);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: white;
        padding: 14px 16px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        text-align: left;
        white-space: nowrap;
        letter-spacing: 0.5px;
        border: none;
    }

    .table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: 0.88rem;
    }

    .table tbody tr:last-child td { border-bottom: none; }
    .table tr:hover { background: #f8fafc; }

    .table .amount {
        color: var(--success);
        font-weight: 800;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .payment-method-badge {
        padding: 4px 10px;
        border-radius: 50px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        background: var(--brand-light);
        color: var(--brand);
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .invoice-ref {
        font-family: 'Courier New', monospace;
        background: #f1f5f9;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-mid);
        white-space: nowrap;
    }

    .customer-info-cell { max-width: 200px; }
    .customer-name { font-weight: 600; color: var(--text); }
    .customer-phone { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }

    /* ALERTS */
    .alert {
        border-radius: var(--radius-sm);
        padding: 12px 16px;
        margin-bottom: 20px;
        font-weight: 500;
        display: flex; align-items: center; gap: 10px;
        font-size: 0.88rem;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
    .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #fca5a5; }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
    .empty-state i {
        font-size: 3.5rem;
        margin-bottom: 15px;
        opacity: 0.3;
        color: var(--brand);
    }

    @media (max-width: 1024px) {
        .content { margin-left: 0; padding: 18px; }
    }

    @media (max-width: 768px) {
        .content { padding: 16px 12px; }
        .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
        .filter-row { grid-template-columns: 1fr; gap: 12px; }
        .summary-cards { grid-template-columns: 1fr; }
        .table th, .table td { padding: 12px 10px; font-size: 0.8rem; }
        .customer-info-cell { max-width: 150px; }
        .btn-filter { width: 100%; justify-content: center; }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-history me-2" style="opacity: 0.85;"></i>Received Payments History</h4>
            <h6>View all received payments from customers</h6>
        </div>
        <div class="page-btn-group">
            <!-- Add any top-level buttons here if required -->
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="post" action="received_payments_history.php">
            <div class="filter-row">
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
                <div class="form-group">
                    <label for="customer_filter" class="form-label">Customer</label>
                    <select class="form-control" id="customer_filter" name="customer_filter">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>" 
                                    <?php echo ($customer_filter == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name'] . ' - ' . $customer['phone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($payments)): ?>
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card total">
                <div class="summary-label">Total Received</div>
                <div class="summary-value">₹ <?php echo number_format($total_received, 2); ?></div>
            </div>
            <div class="summary-card count">
                <div class="summary-label">Total Payments</div>
                <div class="summary-value"><?php echo count($payments); ?></div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment ID</th>
                        <th>Customer</th>
                        <th>Invoice No</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th>Received By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <span class="invoice-ref">
                                    PAY-<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?>
                                </span>
                            </td>
                            <td class="customer-info-cell">
                                <div class="customer-name">
                                    <?php echo htmlspecialchars($payment['customer_name_from_customers'] ?? $payment['customer_name'] ?? 'N/A'); ?>
                                </div>
                                <div class="customer-phone">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($payment['customer_phone_from_customers'] ?? $payment['customer_phone'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($payment['invoice_no']): ?>
                                    <span class="invoice-ref">
                                        <?php echo htmlspecialchars($payment['invoice_no']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="payment-method-badge">
                                    <?php echo htmlspecialchars($payment['payment_method']); ?>
                                </span>
                            </td>
                            <td class="amount">
                                ₹ <?php echo number_format($payment['payment_amount'], 2); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($payment['payment_notes'] ?? '-'); ?>
                            </td>
                            <td>
                                <span style="background: <?php echo ($payment['received_by_name'] ?? '') === 'Admin' ? 'var(--brand-dark)' : 'var(--success)'; ?>; color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">
                                    <?php echo htmlspecialchars($payment['received_by_name']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text);">No payments found</div>
                <div style="font-size: 0.85rem; margin-top: 4px;">Try adjusting the date range or customer filter.</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Auto-hide success message
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            setTimeout(() => {
                if (successAlert) successAlert.remove();
            }, 500);
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