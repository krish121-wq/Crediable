<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    if (session_status() == PHP_SESSION_NONE) session_start();
    header('Location: login.php');
    exit();
}

include('layout.php');

$page_title        = "Customer Ledger";
$success_message   = $_SESSION['success_message'] ?? '';
$error_message     = '';
$user_type         = $_SESSION['user_type'] ?? 'admin';
$created_by_id     = (int)$_SESSION['user_id'];

if ($success_message) unset($_SESSION['success_message']);

// Fetch all customers
try {
    $stmt = $conn->prepare("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC");
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    $customers     = [];
}

$selected_customer_id = $_POST['customer_id'] ?? $_GET['customer_id'] ?? '';
$from_date            = $_POST['from_date']  ?? $_GET['from_date']  ?? date('Y-m-01');
$to_date              = $_POST['to_date']    ?? $_GET['to_date']    ?? date('Y-m-d');

$ledger_entries  = [];
$customer_info   = null;
$opening_balance = 0;
$closing_balance = 0;
$total_debit     = 0;
$total_credit    = 0;

// ── LEDGER LOGIC (Includes BOTH Product Sales AND Service Invoices) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($selected_customer_id)) {
    try {
        $customer_id = (int)$selected_customer_id;
        if ($customer_id <= 0) throw new Exception("Please select a customer.");

        // Fetch customer info
        $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $customer_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$customer_info) throw new Exception("Customer not found.");

        $cname = $customer_info['name'];

        // ── OPENING BALANCE (before from_date) ──
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) AS total_debit
            FROM (
                SELECT i.total_amount, i.created_at
                FROM invoices i
                WHERE i.customer_name = ? AND DATE(i.created_at) < ?
                UNION ALL
                SELECT si.total_amount, si.created_at
                FROM service_invoices si
                WHERE si.customer_name = ? AND DATE(si.created_at) < ?
            ) AS all_invoices
        ");
        $stmt->bind_param("ssss", $cname, $from_date, $cname, $from_date);
        $stmt->execute();
        $ob_debit = (float)$stmt->get_result()->fetch_assoc()['total_debit'];
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_credit
            FROM (
                SELECT rp.amount, rp.created_at
                FROM received_payments rp
                INNER JOIN invoices i ON rp.invoice_id = i.id
                WHERE i.customer_name = ? AND DATE(rp.created_at) < ?
                UNION ALL
                SELECT sip.payment_amount, sip.created_at
                FROM service_invoice_payments sip
                INNER JOIN service_invoices si ON sip.invoice_id = si.id
                WHERE si.customer_name = ? AND DATE(sip.created_at) < ?
            ) AS all_payments
        ");
        $stmt->bind_param("ssss", $cname, $from_date, $cname, $from_date);
        $stmt->execute();
        $ob_credit = (float)$stmt->get_result()->fetch_assoc()['total_credit'];
        $stmt->close();

        $opening_balance = $ob_debit - $ob_credit;

        // ── PRODUCT INVOICES ──
        $stmt = $conn->prepare("
            SELECT
                'product_invoice' AS type,
                i.id AS ref_id,
                CONCAT('INV-', LPAD(i.id, 5, '0')) AS reference_no,
                DATE(i.created_at) AS transaction_date,
                i.total_amount AS debit_amount,
                0 AS credit_amount,
                CONCAT('Product Sale - ', GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ', ')) AS description,
                i.created_at
            FROM invoices i
            LEFT JOIN sales s ON s.invoice_id = i.id
            LEFT JOIN products p ON s.product_id = p.id
            WHERE i.customer_name = ? AND DATE(i.created_at) BETWEEN ? AND ?
            GROUP BY i.id
            ORDER BY i.created_at ASC
        ");
        $stmt->bind_param("sss", $cname, $from_date, $to_date);
        $stmt->execute();
        $product_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ── SERVICE INVOICES ──
        $stmt = $conn->prepare("
            SELECT
                'service_invoice' AS type,
                si.id AS ref_id,
                COALESCE(si.invoice_no, CONCAT('SINV-', LPAD(si.id, 5, '0'))) AS reference_no,
                DATE(si.created_at) AS transaction_date,
                si.total_amount AS debit_amount,
                0 AS credit_amount,
                CONCAT('Service Invoice - ', (SELECT COUNT(*) FROM service_invoice_items WHERE invoice_id = si.id), ' service(s)') AS description,
                si.created_at
            FROM service_invoices si
            WHERE si.customer_name = ? AND DATE(si.created_at) BETWEEN ? AND ?
            ORDER BY si.created_at ASC
        ");
        $stmt->bind_param("sss", $cname, $from_date, $to_date);
        $stmt->execute();
        $service_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ── PRODUCT PAYMENTS ──
        $stmt = $conn->prepare("
            SELECT
                'product_payment' AS type,
                rp.id AS ref_id,
                CONCAT('RCPT-', LPAD(rp.id, 5, '0')) AS reference_no,
                DATE(rp.created_at) AS transaction_date,
                0 AS debit_amount,
                rp.amount AS credit_amount,
                CONCAT('Payment Received - ', rp.payment_method, ' | Invoice #', rp.invoice_id) AS description,
                rp.created_at
            FROM received_payments rp
            INNER JOIN invoices i ON rp.invoice_id = i.id
            WHERE i.customer_name = ? AND DATE(rp.created_at) BETWEEN ? AND ?
            ORDER BY rp.created_at ASC
        ");
        $stmt->bind_param("sss", $cname, $from_date, $to_date);
        $stmt->execute();
        $product_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // ── SERVICE PAYMENTS ──
        $stmt = $conn->prepare("
            SELECT
                'service_payment' AS type,
                sip.id AS ref_id,
                CONCAT('SRCPT-', LPAD(sip.id, 5, '0')) AS reference_no,
                DATE(sip.created_at) AS transaction_date,
                0 AS debit_amount,
                sip.payment_amount AS credit_amount,
                CONCAT('Payment Received - ', sip.payment_method, ' | Service Invoice #', sip.invoice_id) AS description,
                sip.created_at
            FROM service_invoice_payments sip
            INNER JOIN service_invoices si ON sip.invoice_id = si.id
            WHERE si.customer_name = ? AND DATE(sip.created_at) BETWEEN ? AND ?
            ORDER BY sip.created_at ASC
        ");
        $stmt->bind_param("sss", $cname, $from_date, $to_date);
        $stmt->execute();
        $service_payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Merge all entries
        $ledger_entries = array_merge($product_invoices, $service_invoices, $product_payments, $service_payments);
        usort($ledger_entries, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

        // Calculate running balance
        $running = $opening_balance;
        foreach ($ledger_entries as &$entry) {
            $running += $entry['debit_amount'] - $entry['credit_amount'];
            $entry['balance'] = $running;
        }
        unset($entry);
        $closing_balance = $running;

        $total_debit  = array_sum(array_column($ledger_entries, 'debit_amount'));
        $total_credit = array_sum(array_column($ledger_entries, 'credit_amount'));

    } catch (Exception $e) {
        $error_message  = $e->getMessage();
        $ledger_entries = [];
        $customer_info  = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger — CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
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
            --purple:      #8b5cf6;
            --purple-bg:   #ede9fe;
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
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }

        .page-wrap { margin-top: 70px; margin-left: 260px; padding: 28px 32px 60px; transition: var(--transition); }
        @media(max-width:992px){ .page-wrap { margin-left:0; padding: 20px; } }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: var(--shadow-lg);
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-left { position: relative; z-index: 1; }
        .hero-greeting { font-size: 0.85rem; color: rgba(255,255,255,0.75); letter-spacing: 0.5px; margin-bottom: 6px; }
        .hero-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.8rem; font-weight: 800; color: #fff; margin-bottom: 6px; }
        .hero-sub { color: rgba(255,255,255,0.7); font-size: 0.9rem; }
        .hero-right { display: flex; gap: 15px; position: relative; z-index: 1; }

        /* Filter Card */
        .filter-card {
            background: var(--card);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        .filter-grid { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 16px; align-items: end; }
        @media(max-width:768px){ .filter-grid { grid-template-columns: 1fr; gap: 12px; } }
        .f-label { font-size: 0.75rem; font-weight: 700; color: var(--text-mid); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block; }
        .f-control {
            width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: var(--radius-sm);
            font-size: 0.9rem; font-family: 'Inter', sans-serif; color: var(--text); background: #fafafa;
            outline: none; transition: var(--transition);
        }
        .f-control:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(2,132,199,0.12); }
        .btn-search {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff; border: none; padding: 11px 28px; border-radius: var(--radius-sm);
            font-size: 0.9rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: var(--transition);
            box-shadow: 0 4px 14px rgba(2,132,199,0.25);
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(2,132,199,0.35); }

        /* Customer Info Card */
        .customer-card {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            border-radius: var(--radius-md);
            padding: 22px 28px;
            margin-bottom: 24px;
            color: #fff;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .cust-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
        }
        .cust-name { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
        .cust-meta { font-size: 0.85rem; color: rgba(255,255,255,0.8); }
        .cust-period {
            margin-left: auto;
            background: rgba(255,255,255,0.15);
            border-radius: 10px;
            padding: 10px 18px;
            text-align: right;
            backdrop-filter: blur(4px);
        }
        .cust-period-label { font-size: 0.7rem; opacity: 0.8; letter-spacing: 0.5px; }
        .cust-period-val { font-size: 0.95rem; font-weight: 700; margin-top: 3px; }

        /* Balance Cards */
        .balance-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px; }
        @media(max-width:900px){ .balance-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width:500px){ .balance-grid { grid-template-columns: 1fr; } }
        
        .bal-card {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        .bal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 4px 0 0 4px;
        }
        .bal-card.ob::before { background: linear-gradient(to bottom, var(--info), #1d4ed8); }
        .bal-card.dr::before { background: linear-gradient(to bottom, var(--danger), #b91c1c); }
        .bal-card.cr::before { background: linear-gradient(to bottom, var(--success), #059669); }
        .bal-card.cb::before { background: linear-gradient(to bottom, var(--brand), var(--brand-mid)); }
        .bal-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .bal-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 12px;
        }
        .bal-icon.ob { background: var(--info-bg); color: var(--info); }
        .bal-icon.dr { background: var(--danger-bg); color: var(--danger); }
        .bal-icon.cr { background: var(--success-bg); color: var(--success); }
        .bal-icon.cb { background: var(--brand-light); color: var(--brand); }
        .bal-val { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.4rem; font-weight: 800; line-height: 1.2; }
        .bal-label { font-size: 0.7rem; color: var(--text-muted); margin-top: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .bal-dr { color: var(--danger); }
        .bal-cr { color: var(--success); }
        .bal-tag { font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 5px; margin-left: 6px; }
        .bal-tag.dr { background: var(--danger-bg); color: var(--danger); }
        .bal-tag.cr { background: var(--success-bg); color: var(--success); }

        /* Ledger Table */
        .table-card {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .table-header {
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            border-bottom: 1px solid var(--border);
        }
        .table-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; font-weight: 800; color: var(--brand); display: flex; align-items: center; gap: 10px; }
        .table-scroll { overflow-x: auto; }
        .ledger-table { width: 100%; border-collapse: collapse; }
        .ledger-table thead th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 14px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .ledger-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.2s; }
        .ledger-table tbody tr:hover { background: #f8fafc; }
        .ledger-table td { padding: 14px 16px; vertical-align: middle; font-size: 0.85rem; }
        .ledger-table .opening-row td { background: #fef9c3; font-weight: 700; }
        .ledger-table .closing-row td { background: var(--brand-light); font-weight: 700; }

        .type-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .type-badge.product_invoice { background: var(--warning-bg); color: var(--warning); }
        .type-badge.service_invoice { background: var(--info-bg); color: var(--info); }
        .type-badge.product_payment { background: var(--success-bg); color: var(--success); }
        .type-badge.service_payment { background: var(--success-bg); color: var(--success); }

        .ref-no {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            background: var(--brand-light);
            color: var(--brand);
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 700;
            white-space: nowrap;
        }
        .amt-debit { color: var(--danger); font-weight: 700; }
        .amt-credit { color: var(--success); font-weight: 700; }
        
        .btn-print {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,165,233,0.3); }

        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-icon { font-size: 3rem; opacity: 0.3; margin-bottom: 16px; }

        /* Select2 */
        .select2-container--default .select2-selection--single { height: 44px !important; border: 1.5px solid var(--border) !important; border-radius: 10px !important; background: #fafafa !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px !important; padding-left: 14px; font-size: 0.9rem; }
        .select2-dropdown { border: 1.5px solid var(--border) !important; border-radius: 10px !important; }

        @media print {
            .hero-banner, .filter-card, .btn-print, .no-print { display: none !important; }
            .page-wrap { margin: 0; padding: 10px; }
            .table-card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
<?php?>

<div class="page-wrap">
    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-left">
            <div class="hero-greeting"><i class="fas fa-chart-line me-1"></i> Financial Overview</div>
            <div class="hero-title"><i class="fas fa-book-open me-2"></i>Customer Ledger</div>
            <div class="hero-sub">Complete transaction history - Products & Services</div>
        </div>
        <div class="hero-right">
            <a href="sales.php" style="background:rgba(255,255,255,0.15);color:#fff;padding:10px 20px;border-radius:10px;text-decoration:none;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-arrow-left"></i> Back to Sales
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="border-radius: 12px;">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="border-radius: 12px;">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="post" action="customer_ledger.php">
            <div class="filter-grid">
                <div>
                    <label class="f-label"><i class="fas fa-user me-1"></i>Select Customer <span class="text-danger">*</span></label>
                    <select class="f-control" id="customer_id" name="customer_id" required style="width:100%">
                        <option value="">Search or select a customer...</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($selected_customer_id == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                                <?php if (!empty($c['phone'])): ?> — <?php echo htmlspecialchars($c['phone']); ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="f-label"><i class="fas fa-calendar-alt me-1"></i>From Date</label>
                    <input type="date" class="f-control" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>
                <div>
                    <label class="f-label"><i class="fas fa-calendar-check me-1"></i>To Date</label>
                    <input type="date" class="f-control" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>
                <div>
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Show Ledger</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($customer_info): ?>
    <!-- Customer Info Card -->
    <div class="customer-card">
        <div class="cust-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div>
            <div class="cust-name"><?php echo htmlspecialchars($customer_info['name']); ?></div>
            <div class="cust-meta">
                <?php if (!empty($customer_info['phone'])): ?><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer_info['phone']); ?><?php endif; ?>
                <?php if (!empty($customer_info['address'])): ?> &nbsp;|&nbsp; <i class="fas fa-location-dot me-1"></i><?php echo htmlspecialchars($customer_info['address']); ?><?php endif; ?>
                <?php if (!empty($customer_info['state'])): ?> &nbsp;|&nbsp; <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($customer_info['state']); ?><?php endif; ?>
            </div>
        </div>
        <div class="cust-period">
            <div class="cust-period-label"><i class="fas fa-calendar-week me-1"></i>Statement Period</div>
            <div class="cust-period-val"><?php echo date('d M Y', strtotime($from_date)); ?> – <?php echo date('d M Y', strtotime($to_date)); ?></div>
        </div>
    </div>

    <!-- Balance Summary Cards -->
    <div class="balance-grid">
        <div class="bal-card ob">
            <div class="bal-icon ob"><i class="fas fa-arrow-right"></i></div>
            <div class="bal-val <?php echo $opening_balance >= 0 ? 'bal-dr' : 'bal-cr'; ?>">
                ₹<?php echo number_format(abs($opening_balance), 2); ?>
                <span class="bal-tag <?php echo $opening_balance >= 0 ? 'dr' : 'cr'; ?>"><?php echo $opening_balance >= 0 ? 'DR' : 'CR'; ?></span>
            </div>
            <div class="bal-label">Opening Balance</div>
        </div>
        <div class="bal-card dr">
            <div class="bal-icon dr"><i class="fas fa-file-invoice"></i></div>
            <div class="bal-val" style="color:var(--danger)">₹<?php echo number_format($total_debit, 2); ?></div>
            <div class="bal-label">Total Debit (Invoiced)</div>
        </div>
        <div class="bal-card cr">
            <div class="bal-icon cr"><i class="fas fa-money-bill-wave"></i></div>
            <div class="bal-val" style="color:var(--success)">₹<?php echo number_format($total_credit, 2); ?></div>
            <div class="bal-label">Total Credit (Received)</div>
        </div>
        <div class="bal-card cb">
            <div class="bal-icon cb"><i class="fas fa-chart-line"></i></div>
            <div class="bal-val <?php echo $closing_balance >= 0 ? 'bal-dr' : 'bal-cr'; ?>">
                ₹<?php echo number_format(abs($closing_balance), 2); ?>
                <span class="bal-tag <?php echo $closing_balance >= 0 ? 'dr' : 'cr'; ?>"><?php echo $closing_balance >= 0 ? 'DR' : 'CR'; ?></span>
            </div>
            <div class="bal-label">Closing Balance</div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title"><i class="fas fa-table-list"></i> Transaction Ledger</div>
            <div>
                <button class="btn-print no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Statement</button>
            </div>
        </div>
        <div class="table-scroll">
            <table class="ledger-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Transaction Type</th>
                        <th>Reference No.</th>
                        <th>Description</th>
                        <th style="text-align:right">Debit (DR)</th>
                        <th style="text-align:right">Credit (CR)</th>
                        <th style="text-align:right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening Balance Row -->
                    <tr class="opening-row">
                        <td colspan="7" style="text-align:right;padding-right:20px;">
                            <i class="fas fa-arrow-right me-1"></i>Opening Balance
                        </td>
                        <td style="text-align:right">
                            <span class="<?php echo $opening_balance >= 0 ? 'bal-dr' : 'bal-cr'; ?>">
                                ₹<?php echo number_format(abs($opening_balance), 2); ?>
                                <span class="bal-tag <?php echo $opening_balance >= 0 ? 'dr' : 'cr'; ?>"><?php echo $opening_balance >= 0 ? 'DR' : 'CR'; ?></span>
                            </span>
                        </td>
                    </tr>

                    <?php if (!empty($ledger_entries)): ?>
                        <?php 
                        $counter = 1;
                        foreach ($ledger_entries as $entry): 
                            $type_class = '';
                            $type_icon = '';
                            $type_label = '';
                            
                            if ($entry['type'] == 'product_invoice') {
                                $type_class = 'product_invoice';
                                $type_icon = 'fa-box';
                                $type_label = 'Product Sale';
                            } elseif ($entry['type'] == 'service_invoice') {
                                $type_class = 'service_invoice';
                                $type_icon = 'fa-file-alt';
                                $type_label = 'Service Invoice';
                            } elseif ($entry['type'] == 'product_payment') {
                                $type_class = 'product_payment';
                                $type_icon = 'fa-hand-holding-usd';
                                $type_label = 'Payment';
                            } elseif ($entry['type'] == 'service_payment') {
                                $type_class = 'service_payment';
                                $type_icon = 'fa-hand-holding-usd';
                                $type_label = 'Payment';
                            }
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?php echo $counter++; ?></td>
                            <td style="white-space:nowrap">
                                <?php echo date('d M Y', strtotime($entry['transaction_date'])); ?>
                                <div style="font-size:0.7rem;color:var(--text-muted)"><?php echo date('h:i A', strtotime($entry['created_at'])); ?></div>
                            </td>
                            <td>
                                <span class="type-badge <?php echo $type_class; ?>">
                                    <i class="fas <?php echo $type_icon; ?> me-1"></i> <?php echo $type_label; ?>
                                </span>
                            </td>
                            <td><span class="ref-no"><?php echo htmlspecialchars($entry['reference_no']); ?></span></td>
                            <td style="max-width:300px;"><?php echo htmlspecialchars(substr($entry['description'] ?? '', 0, 80)); ?></td>
                            <td style="text-align:right">
                                <?php if ($entry['debit_amount'] > 0): ?>
                                    <span class="amt-debit"><i class="fas fa-arrow-down me-1"></i>₹<?php echo number_format($entry['debit_amount'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right">
                                <?php if ($entry['credit_amount'] > 0): ?>
                                    <span class="amt-credit"><i class="fas fa-arrow-up me-1"></i>₹<?php echo number_format($entry['credit_amount'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                <?php
                                $bal = $entry['balance'];
                                $bal_class = $bal >= 0 ? 'bal-dr' : 'bal-cr';
                                $bal_tag = $bal >= 0 ? 'DR' : 'CR';
                                ?>
                                <span class="<?php echo $bal_class; ?>">
                                    <i class="fas fa-rupee-sign me-1"></i><?php echo number_format(abs($bal), 2); ?>
                                    <span class="bal-tag <?php echo strtolower($bal_tag); ?>"><?php echo $bal_tag; ?></span>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                                    <div style="font-weight:700;font-size:1rem;">No Transactions Found</div>
                                    <div style="font-size:0.85rem;margin-top:4px;">No invoices or payments for this period.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <!-- Closing Balance Row -->
                    <tr class="closing-row">
                        <td colspan="5" style="text-align:right;padding-right:20px;">
                            <i class="fas fa-flag-checkered me-1"></i>Closing Balance
                        </td>
                        <td style="text-align:right;color:var(--danger);font-weight:800">
                            <i class="fas fa-arrow-down me-1"></i>₹<?php echo number_format($total_debit, 2); ?>
                        </td>
                        <td style="text-align:right;color:var(--success);font-weight:800">
                            <i class="fas fa-arrow-up me-1"></i>₹<?php echo number_format($total_credit, 2); ?>
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <span class="<?php echo $closing_balance >= 0 ? 'bal-dr' : 'bal-cr'; ?>" style="font-size:0.95rem">
                                <i class="fas fa-rupee-sign me-1"></i><?php echo number_format(abs($closing_balance), 2); ?>
                                <span class="bal-tag <?php echo $closing_balance >= 0 ? 'dr' : 'cr'; ?>"><?php echo $closing_balance >= 0 ? 'DR' : 'CR'; ?></span>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    $('#customer_id').select2({
        placeholder: 'Search or select a customer...',
        allowClear: true,
        width: '100%'
    });

    const today = new Date().toISOString().split('T')[0];
    $('input[name="to_date"]').attr('max', today);
    $('input[name="from_date"]').attr('max', today);

    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert').fadeOut(500, function() { $(this).remove(); });
    }, 5000);
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>