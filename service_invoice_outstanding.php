<?php
// Helper function to safely escape HTML with null check
function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

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

$page_title = "Outstanding Service Invoices";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];

if ($success_message) {
    unset($_SESSION['success_message']);
}

try {
    $stmt = $conn->prepare("
        SELECT si.*, 
               c.id AS customer_id,
               CASE
                   WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                   WHEN staff_users.name IS NOT NULL THEN staff_users.name
                   ELSE 'Unknown'
               END AS created_by_name,
               (si.total_amount - si.paid_amount) AS outstanding_amount
        FROM service_invoices si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN users admin_users ON si.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON si.created_by = staff_users.id
        WHERE si.total_amount > si.paid_amount
        " . ($user_type !== 'admin' ? "AND si.created_by = ?" : "") . "
        ORDER BY si.created_at DESC
    ");
    
    if ($user_type !== 'admin') {
        $stmt->bind_param("i", $created_by_id);
    }
    $stmt->execute();
    
    $outstanding_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate total outstanding
    $total_outstanding = array_sum(array_column($outstanding_invoices, 'outstanding_amount'));
    
} catch (Exception $e) {
    $error_message = "Error fetching outstanding invoices: " . $e->getMessage();
    $outstanding_invoices = [];
    $total_outstanding = 0;
}

// Handle payment settlement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['settle_payment'])) {
    try {
        $invoice_id = (int)$_POST['invoice_id'];
        $customer_id = (int)$_POST['customer_id'];
        $payment_method = trim($_POST['payment_method'] ?? '');
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        $payment_notes = trim($_POST['payment_notes'] ?? '');
        
        if ($invoice_id <= 0 || $customer_id <= 0 || empty($payment_method) || $payment_amount <= 0) {
            throw new Exception("All required fields must be filled and payment amount must be greater than 0.");
        }
        
        // Check if user has permission to settle this invoice
        if ($user_type !== 'admin') {
            $stmt = $conn->prepare("SELECT created_by FROM service_invoices WHERE id = ?");
            $stmt->bind_param("i", $invoice_id);
            $stmt->execute();
            $invoice_check = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$invoice_check || $invoice_check['created_by'] != $created_by_id) {
                throw new Exception("You don't have permission to settle this invoice");
            }
        }
        
        // Get current invoice details
        $stmt = $conn->prepare("SELECT total_amount, paid_amount FROM service_invoices WHERE id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$invoice) {
            throw new Exception("Invoice not found");
        }
        
        $outstanding_amount = $invoice['total_amount'] - $invoice['paid_amount'];
        
        if ($payment_amount > $outstanding_amount) {
            throw new Exception("Payment amount cannot exceed outstanding amount of ₹" . number_format($outstanding_amount, 2));
        }
        
        $conn->begin_transaction();
        
        // Create payment record
        $stmt = $conn->prepare("INSERT INTO service_invoice_payments (invoice_id, customer_id, payment_method, payment_amount, payment_notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisdsi", $invoice_id, $customer_id, $payment_method, $payment_amount, $payment_notes, $created_by_id);
        $stmt->execute();
        $stmt->close();
        
        // Update invoice paid amount
        $new_paid_amount = $invoice['paid_amount'] + $payment_amount;
        $stmt = $conn->prepare("UPDATE service_invoices SET paid_amount = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $new_paid_amount, $invoice_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $_SESSION['success_message'] = "Payment of ₹" . number_format($payment_amount, 2) . " received successfully.";
        header("Location: service_invoice_outstanding.php");
        exit();
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno === 0) {
            $conn->rollback();
        }
        $error_message = $e->getMessage();
        error_log("Payment Settlement Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outstanding Service Invoices — CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

        /* Content Area */
        .content {
            margin-top: 65px;
            margin-left: 260px;
            padding: 24px 28px 60px;
            min-height: calc(100vh - 65px);
            transition: var(--transition);
        }

        .content-full { margin-left: 0; }

        @media (max-width: 992px) {
            .content { margin-left: 0; padding: 16px 14px; }
        }

        /* Page Hero */
        .page-hero {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            color: #fff;
            padding: 22px 26px;
            border-radius: var(--radius-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
            flex-wrap: wrap;
            gap: 14px;
            position: relative;
            overflow: hidden;
        }

        .page-hero::before {
            content: ''; position: absolute;
            right: -50px; top: -50px;
            width: 200px; height: 200px;
            border-radius: 50%; background: rgba(255,255,255,.06);
        }

        .page-title h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            margin: 0 0 4px 0;
        }

        .page-title h6 {
            font-size: 0.85rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 500;
        }

        .page-btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            z-index: 10;
        }

        /* Outstanding Summary Card */
        .summary-card {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: white;
            padding: 22px 26px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .summary-info h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-amount {
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .summary-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            backdrop-filter: blur(4px);
        }

        /* Table Card */
        .card {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
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

        /* Outstanding Amount Badge */
        .outstanding-amount {
            background: var(--danger-bg);
            color: var(--danger);
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.85rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: inline-flex;
            border: 1px solid #fca5a5;
            white-space: nowrap;
        }

        /* Badge NA */
        .badge-na {
            background: #f1f5f9;
            color: var(--text-muted);
            padding: 3px 9px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 600;
        }

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

        /* Action Buttons */
        .action-container {
            display: flex;
            gap: 6px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.82rem;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            position: relative;
            border: none;
            cursor: pointer;
            text-decoration: none;
            flex-shrink: 0;
        }

        .action-btn:hover {
            transform: translateY(-3px) scale(1.1);
            color: white;
        }

        .action-btn.print { background: linear-gradient(135deg, #10B981, #059669); }
        .action-btn.view { background: linear-gradient(135deg, var(--info), #1d4ed8); }
        .action-btn.settle { background: linear-gradient(135deg, var(--success), #059669); }

        /* Tooltip */
        .action-btn::after {
            content: attr(data-title);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #0b1220;
            color: white;
            font-size: 0.68rem;
            padding: 4px 8px;
            border-radius: 5px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
            pointer-events: none;
            z-index: 30;
        }
        .action-btn:hover::after { opacity: 1; visibility: visible; }

        /* Button Custom */
        .btn-custom {
            background: transparent;
            color: #fff !important;
            border: 1.5px solid rgba(255,255,255,0.4);
            padding: 9px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
            cursor: pointer; transition: var(--transition);
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-custom:hover {
            background: rgba(255,255,255,0.15);
            border-color: #fff;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: var(--radius-md);
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: white;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            padding: 18px 22px;
            border: none;
        }

        .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
        }
        .btn-close-white { filter: invert(1); opacity: 0.8; }

        .modal-body { padding: 22px; }

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            gap: 10px;
        }

        .form-label {
            font-weight: 700;
            font-size: 0.75rem;
            color: var(--text-mid);
            margin-bottom: 6px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .required-star { color: var(--danger); }

        .form-control {
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: var(--transition);
            width: 100%;
            background: #fafafa;
            color: var(--text);
            outline: none;
        }
        .form-control[readonly] { background: #eef2f6; color: var(--text-muted); cursor: not-allowed; }

        .form-control:focus:not([readonly]) {
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            box-shadow: 0 4px 14px rgba(22,163,74,0.25);
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(22,163,74,0.35);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
            .summary-card { flex-direction: column; text-align: center; }
            .table th, .table td { padding: 12px 10px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="content <?php echo isset($is_restricted) && !$is_restricted ? 'content-full' : ''; ?>">
    
    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-file-invoice-dollar me-2" style="opacity:.85"></i>Outstanding Service Invoices</h4>
            <h6>View all pending service invoices</h6>
        </div>
        <div class="page-btn-group">
            <a href="service_invoice.php" class="btn-custom">
                <i class="fas fa-arrow-left"></i> Back to All Invoices
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-check-circle"></i>
            <?php echo safeHtml($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo safeHtml($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Outstanding Summary -->
    <div class="summary-card">
        <div class="summary-info">
            <h5>Total Outstanding Amount</h5>
            <div class="summary-amount">₹ <?php echo number_format($total_outstanding, 2); ?></div>
        </div>
        <div class="summary-count">
            <i class="fas fa-file-invoice me-1"></i> <?php echo count($outstanding_invoices); ?> Invoice(s)
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table" id="outstandingInvoiceTable">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Invoice Date</th>
                        <th>Customer Name</th>
                        <th>Customer Phone</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Outstanding</th>
                        <th>Created At</th>
                        <th>Created By</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($outstanding_invoices)): ?>
                        <?php foreach ($outstanding_invoices as $invoice): ?>
                            <tr>
                                <td><span style="font-family: monospace; background: var(--bg); padding: 3px 6px; border-radius: 4px; color: var(--text-mid); font-weight: 600;"><?php echo safeHtml($invoice['invoice_no'] ?? 'N/A'); ?></span></td>
                                <td><?php echo !empty($invoice['invoice_date']) ? date('d/m/y', strtotime($invoice['invoice_date'])) : 'N/A'; ?></td>
                                <td><strong><?php echo safeHtml($invoice['customer_name'] ?? ''); ?></strong></td>
                                <td><?php echo !empty($invoice['customer_phone']) ? '<i class="fas fa-phone me-1 text-muted" style="font-size:0.7rem"></i>'.safeHtml($invoice['customer_phone']) : '<span class="badge-na">N/A</span>'; ?></td>
                                <td style="font-weight: 700; color: var(--text);">₹ <?php echo number_format($invoice['total_amount'] ?? 0, 2); ?></td>
                                <td style="color: var(--success); font-weight: 600;">₹ <?php echo number_format($invoice['paid_amount'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="outstanding-amount">
                                        ₹ <?php echo number_format($invoice['outstanding_amount'] ?? 0, 2); ?>
                                    </span>
                                </td>
                                <td><?php echo (new DateTime($invoice['created_at'] ?? 'now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d/m/y'); ?></td>
                                <td>
                                    <span style="background: <?php echo ($invoice['created_by_name'] ?? '') === 'Admin' ? 'var(--brand-dark)' : 'var(--success)'; ?>; color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight: 700;">
                                        <?php echo safeHtml($invoice['created_by_name'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-container">
                                        <a href="service_invoice_print.php?id=<?php echo $invoice['id']; ?>" 
                                           target="_blank" 
                                           class="action-btn print" 
                                           data-title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="service_inv_view.php?id=<?php echo $invoice['id']; ?>" 
                                           class="action-btn view" 
                                           data-title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="action-btn settle" 
                                                data-title="Settle Payment"
                                                onclick="openSettleModal(<?php echo $invoice['id']; ?>, '<?php echo safeHtml($invoice['invoice_no'] ?? 'Invoice #' . $invoice['id']); ?>', <?php echo $invoice['outstanding_amount'] ?? 0; ?>, <?php echo $invoice['customer_id'] ?? 0; ?>)">
                                            <i class="fas fa-hand-holding-usd"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="empty-row">
                            <td colspan="10" style="text-align: center; padding: 50px 20px; color: var(--text-muted);">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: var(--success); margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text);">All Caught Up!</div>
                                <div style="font-size: 0.85rem; margin-top: 4px;">No outstanding service invoices found.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Settlement Modal -->
<div class="modal fade" id="settlePaymentModal" tabindex="-1" aria-labelledby="settlePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="settlePaymentModalLabel">
                    <i class="fas fa-hand-holding-usd me-2"></i> Settle Payment
                </h4>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="settlePaymentForm" action="service_invoice_outstanding.php" method="post">
                <div class="modal-body">
                    <input type="hidden" id="invoice_id" name="invoice_id">
                    <input type="hidden" id="customer_id" name="customer_id">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Invoice Number</label>
                        <input type="text" class="form-control" id="modal_invoice_no" readonly>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Outstanding Amount</label>
                        <input type="text" class="form-control" id="modal_outstanding" readonly style="color: var(--danger); font-weight: 700; background: #fee2e2; border-color: #fca5a5;">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Payment Method <span class="required-star">*</span></label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="UPI">UPI</option>
                            <option value="Card">Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Payment Amount (₹) <span class="required-star">*</span></label>
                        <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                               min="0.01" step="0.01" required placeholder="Enter payment amount">
                        <small class="text-muted" style="font-size: 0.75rem; margin-top: 4px; display: block;">Maximum amount: ₹ <span id="max_amount" style="font-weight: 700; color: var(--text);">0.00</span></small>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2" 
                                  placeholder="Add any payment notes or reference"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" style="background: transparent; color: var(--text-mid); border: 1.5px solid var(--border); border-radius: 50px; font-weight: 600; padding: 9px 20px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="settle_payment" class="btn-submit">
                        <i class="fas fa-check"></i> Receive Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Auto-hide success message
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function() {
            successAlert.style.opacity = '0';
            setTimeout(function() {
                if (successAlert) successAlert.remove();
            }, 500);
        }, 5000);
    }
});

// Open Settle Modal Function
function openSettleModal(invoiceId, invoiceNo, outstandingAmount, customerId) {
    document.getElementById('invoice_id').value = invoiceId;
    document.getElementById('customer_id').value = customerId;
    document.getElementById('modal_invoice_no').value = invoiceNo;
    document.getElementById('modal_outstanding').value = '₹ ' + parseFloat(outstandingAmount).toFixed(2);
    document.getElementById('max_amount').innerHTML = parseFloat(outstandingAmount).toFixed(2);
    
    // Setup the amount input field constraints
    const paymentInput = document.getElementById('payment_amount');
    paymentInput.max = outstandingAmount;
    paymentInput.value = parseFloat(outstandingAmount).toFixed(2);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('settlePaymentModal'));
    modal.show();
}
</script>

</body>
</html>