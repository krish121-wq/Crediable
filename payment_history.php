<?php
require_once 'config.php';

// Start session and output buffering before including layout
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();
include('layout.php');

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = '';

// Fetch payment history with related invoice details
try {
    $sql = "SELECT rp.*, i.customer_name, i.customer_phone, i.total_amount as invoice_amount,
                   COALESCE(u.full_name, cu.name) as received_by,
                   rp.created_by as created_by_id
            FROM received_payments rp
            JOIN invoices i ON rp.invoice_id = i.id
            LEFT JOIN users u ON rp.created_by = u.id
            LEFT JOIN create_users cu ON rp.created_by = cu.id
            WHERE 1=1 ";
    
    // If user is staff, only show payments they created
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff') {
        $sql .= " AND rp.created_by = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        // For admins, show all payments
        $res = $conn->query($sql);
    }
    
    $payments = [];
    if ($res) {
        $payments = $res->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $error_message = 'Failed to fetch payment history: ' . $e->getMessage();
}
?>

<!-- IMPORTING FONTS & ICONS -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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

/* Reset */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', system-ui, sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
}

/* Layout */
.content {
    margin-top: 70px;
    margin-left: 260px;
    padding: 24px 28px 60px;
    min-height: calc(100vh - 70px);
    transition: var(--transition);
}

.content-full { margin-left: 0; }

@media (max-width: 992px) {
    .content { margin-left: 0; padding: 16px 14px; }
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
.hero-actions { position: relative; z-index: 10; }

/* Top Button */
.btn-custom {
    background: transparent;
    color: #fff !important;
    border: 1.5px solid rgba(255,255,255,0.4);
    padding: 9px 18px;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: inline-flex; align-items: center; gap: 8px;
    cursor: pointer; transition: var(--transition);
    text-decoration: none; white-space: nowrap;
}
.btn-custom:hover {
    background: rgba(255,255,255,0.15);
    border-color: #fff;
}

/* Card & Table */
.card {
    background: var(--card);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.table-responsive { overflow-x: auto; }

.table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
.table th {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    padding: 14px 16px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    white-space: nowrap;
    border: none;
}
.table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
    font-size: 0.88rem;
    color: var(--text);
}
.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover { background: #f8fafc; }

.amount-text { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; color: var(--success); }
.invoice-badge { font-family: monospace; background: var(--bg); padding: 4px 8px; border-radius: 6px; color: var(--text-mid); font-weight: 700; }

/* Badge (Payment Method) */
.payment-method-badge {
    padding: 4px 10px;
    border-radius: 50px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    background: var(--brand-light);
    color: var(--brand);
    white-space: nowrap;
    letter-spacing: 0.5px;
}

/* Action Buttons */
.action-container { display: flex; gap: 6px; justify-content: flex-start; align-items: center; }
.action-btn {
    width: 34px; height: 34px; border-radius: 9px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.82rem; transition: var(--transition);
    box-shadow: 0 2px 8px rgba(0,0,0,.12); border: none; cursor: pointer; text-decoration: none; flex-shrink: 0;
}
.action-btn:hover { transform: translateY(-3px) scale(1.1); color: #fff; }
.action-btn.print { background: linear-gradient(135deg, #10B981, #059669); }
.action-btn::after {
    content: attr(data-title);
    position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
    background: #0b1220; color: #fff; font-size: 0.68rem; padding: 4px 8px; border-radius: 5px;
    white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity .2s; pointer-events: none; z-index: 30;
}
.action-btn:hover::after { opacity: 1; visibility: visible; }

/* Alerts */
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

/* Responsive */
@media (max-width: 768px) {
    .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
    .hero-actions .btn-custom { width: 100%; justify-content: center; }
    .table th, .table td { padding: 12px 10px; font-size: 0.8rem; }
}
</style>

<div class="content <?php echo isset($is_restricted) && !$is_restricted ? 'content-full' : ''; ?>">
    
    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-history me-2" style="opacity: 0.85;"></i>Received Payment History</h4>
            <h6>View all received payments with details</h6>
        </div>
        <div class="hero-actions">
            <a href="cash_payment_history.php" class="btn-custom">
                <i class="fas fa-file-invoice-dollar"></i> Cash Bill Payment History
            </a>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- TABLE CARD -->
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice ID</th>
                        <th>Payment ID</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Received By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <i class="fas fa-search-dollar" style="font-size: 32px; opacity: 0.3; margin-bottom: 10px; display:block;"></i>
                                No payment records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><span class="invoice-badge">#<?php echo htmlspecialchars($payment['invoice_id']); ?></span></td>
                                <td><span class="invoice-badge">#<?php echo htmlspecialchars($payment['id']); ?></span></td>
                                <td style="color: var(--text-mid);"><?php echo date('d M Y', strtotime($payment['created_at'])); ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($payment['customer_name']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($payment['customer_phone'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="amount-text">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                <td>
                                    <span class="payment-method-badge">
                                        <?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="background: <?php echo ($payment['received_by'] ?? '') === 'Admin' ? 'var(--brand-dark)' : 'var(--success)'; ?>; color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">
                                        <?php echo htmlspecialchars($payment['received_by']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-container">
                                        <a href="print_receipt.php?id=<?php echo $payment['id']; ?>&type=in" target="_blank" class="action-btn print" data-title="Print Receipt">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Get the buffered content and output it
$content = ob_get_clean();
echo $content;
?>