<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "GSTR-2 Reports";
$success_message = '';
$error_message = '';
$debug_messages = [];
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Initialize filter variables
$filter_type = $_POST['filter_type'] ?? 'monthly';
$selected_month = $_POST['month'] ?? date('Y-m');
$selected_year = $_POST['year'] ?? date('Y');
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$timezone = new DateTimeZone('Asia/Kolkata');

// Initialize variables to prevent undefined errors
$sanitized_purchases = [];
$summary_data = [];
$total_taxable = 0;
$total_tax = 0;
$total_price = 0;

// Build query based on filter - FIXED COLUMN NAMES
try {
    $query = "
        SELECT p.id, p.product_name, p.product_code, p.quantity, p.sell_price, 
               p.purchase_price, p.gst_percentage, p.created_at,
               p.unit, p.hsn_code
        FROM products p
        WHERE 1=1  -- Removed created_by condition and category joins
    ";
    $params = [];
    $param_types = "";

    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(p.created_at, '%Y-%m') = ?";
        $params[] = $selected_month;
        $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(p.created_at) = ?";
        $params[] = $selected_year;
        $param_types .= "i";
    } elseif ($filter_type === 'custom') {
        if (empty($start_date) || empty($end_date)) {
            throw new Exception("Start date and end date are required for custom filter.");
        }
        $start = new DateTime($start_date, $timezone);
        $end = new DateTime($end_date, $timezone);
        if ($start > $end) {
            throw new Exception("Start date cannot be after end date.");
        }
        $query .= " AND p.created_at BETWEEN ? AND ?";
        $params[] = $start->format('Y-m-d 00:00:00');
        $params[] = $end->format('Y-m-d 23:59:59');
        $param_types .= "ss";
    }

    $query .= " ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Sanitize purchase data and calculate totals - UPDATED CALCULATION
    $sanitized_purchases = array_map(function($purchase) {
        // Calculate tax for purchases - purchase_price includes GST
        $total_tax_rate = $purchase['gst_percentage'];
        // Calculate taxable value by removing GST from purchase_price
        $taxable_value = ($purchase['purchase_price'] * 100) / (100 + $total_tax_rate);
        $taxable_value = $taxable_value * $purchase['quantity'];
        // Calculate tax amount on the taxable value
        $tax_amount = $taxable_value * ($total_tax_rate / 100);
        $total_price = $purchase['purchase_price'] * $purchase['quantity'];
        
        return [
            'id' => (int)$purchase['id'],
            'product_name' => htmlspecialchars($purchase['product_name'], ENT_QUOTES, 'UTF-8'),
            'product_code' => htmlspecialchars($purchase['product_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'unit' => htmlspecialchars($purchase['unit'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'hsn_code' => htmlspecialchars($purchase['hsn_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'quantity' => (int)$purchase['quantity'],
            'price_per_piece' => (float)$purchase['sell_price'],
            'tax_rate' => (float)$total_tax_rate,
            
            'taxable_value' => (float)$taxable_value,
            'tax_amount' => (float)$tax_amount,
            'total_price' => (float)$total_price,
            'created_at' => $purchase['created_at']
        ];
    }, $purchases);

    // Calculate summary by tax rate
    $summary_data = [];
    foreach ($sanitized_purchases as $purchase) {
        $tax_rate = (int)$purchase['tax_rate'];
        if (!isset($summary_data[$tax_rate])) {
            $summary_data[$tax_rate] = [
                'total_invoices' => 0,
                'total_taxable_value' => 0,
                'total_tax' => 0,
                'total_price' => 0
            ];
        }
        $summary_data[$tax_rate]['total_invoices'] += 1;
        $summary_data[$tax_rate]['total_taxable_value'] += $purchase['taxable_value'];
        $summary_data[$tax_rate]['total_tax'] += $purchase['tax_amount'];
        $summary_data[$tax_rate]['total_price'] += $purchase['total_price'];
        $total_taxable += $purchase['taxable_value'];
        $total_tax += $purchase['tax_amount'];
        $total_price += $purchase['total_price'];
    }
} catch (Exception $e) {
    $error_message = "Error fetching purchase data: " . $e->getMessage();
    error_log("GSTR-2 Fetch Error: " . $e->getMessage());
}
?>

<!-- IMPORTING FONTS & ICONS -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ================= GLOBAL TOKENS ================= */
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
    --text:        #0b1220;
    --muted:       #64748b;
    --border:      #e2e8f0;
    --grey:        #f1f5f9;
    
    --radius-sm:   7px;
    --radius-md:   12px;
    --radius-lg:   18px;
    
    --shadow-sm:   0 2px 8px rgba(2,132,199,.07);
    --shadow-md:   0 8px 24px rgba(2,132,199,.11);
    --shadow-lg:   0 16px 48px rgba(2,132,199,.16);
    --transition:  all .25s cubic-bezier(.4,0,.2,1);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    margin: 0;
    color: var(--text);
    font-size: 14px;
}

/* ================= CONTENT ================= */
.content {
    margin-top: 70px;
    margin-left: 260px;
    padding: 24px 28px 60px;
    min-height: calc(100vh - 70px);
    transition: var(--transition);
}

.content-full { margin-left: 0; }

@media (max-width: 1024px) {
    .content { margin-left: 0; padding: 18px; }
}

@media (max-width: 768px) {
    .content { padding: 16px 12px; }
}

/* ================= PAGE HERO ================= */
.page-hero {
    background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
    flex-wrap: wrap;
}
.page-hero::before {
    content: ''; position: absolute; right: -50px; top: -50px;
    width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,.06);
}
.page-title h4 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.45rem; font-weight: 800; color: #fff; margin: 0 0 4px 0;
}
.page-title h6 { color: rgba(255,255,255,.75); font-size: 0.85rem; margin: 0; font-weight: 500; }
.hero-actions { position: relative; z-index: 10; }

/* EXPORT BUTTON */
.export-btn {
    background: linear-gradient(135deg, var(--success), #059669);
    color: #fff;
    padding: 10px 22px;
    border-radius: 50px;
    border: none;
    font-size: 0.88rem;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(22,163,74,.25);
    white-space: nowrap;
}
.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(22,163,74,.35);
}

/* ================= CARD ================= */
.card {
    background: var(--card);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-body {
    padding: 24px;
}

/* ================= FILTER (DESKTOP) ================= */
.filter-form {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    background: var(--bg);
    padding: 16px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
}

.filter-form .form-group {
    display: flex;
    flex-direction: column;
    min-width: 180px;
    flex: 1;
}

.filter-form label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 6px;
    color: var(--text-mid);
}

.form-control {
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    color: var(--text);
    background: #fff;
    transition: var(--transition);
    width: 100%;
}

.form-control:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    outline: none;
}

.btn-custom {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    padding: 11px 24px;
    border-radius: var(--radius-sm);
    border: none;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    height: fit-content;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(2,132,199,.25);
    white-space: nowrap;
}
.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(2,132,199,.35);
}

/* ================= TABLE ================= */
.table-responsive {
    overflow-x: auto;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
}

/* 🎯 MAIN FIX: EXACT COLUMN WIDTHS */
.table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed; /* This forces the browser to respect width limits */
    min-width: 1300px;
}

/* HEADER */
.table th {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 14px 12px;
    border: none;
    text-align: left;
}

/* BODY */
.table td {
    padding: 12px;
    font-size: 0.88rem;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: middle;
}

.table th, .table td { 
    white-space: nowrap; 
    overflow: hidden; 
    text-overflow: ellipsis; 
}

/* 🎯 SPECIFIC COLUMN WIDTHS FOR GSTR-2 TABLE */
#gstr2Table th:nth-child(1), #gstr2Table td:nth-child(1) { width: 110px; } /* Purchase ID */
#gstr2Table th:nth-child(2), #gstr2Table td:nth-child(2) { width: 100px; } /* Date */
#gstr2Table th:nth-child(3), #gstr2Table td:nth-child(3) { 
    width: 220px; /* Product Name Fixed Width */
    white-space: normal;
    word-break: break-word;
    font-weight: 600;
    color: var(--text);
    line-height: 1.4;
}
#gstr2Table th:nth-child(4), #gstr2Table td:nth-child(4) { width: 120px; } /* Product Code */
#gstr2Table th:nth-child(5), #gstr2Table td:nth-child(5) { width: 100px; } /* HSN */
#gstr2Table th:nth-child(6), #gstr2Table td:nth-child(6) { width: 80px; }  /* Unit */
#gstr2Table th:nth-child(7), #gstr2Table td:nth-child(7) { width: 90px; text-align: center; }  /* Qty */
#gstr2Table th:nth-child(8), #gstr2Table td:nth-child(8) { width: 130px; } /* Taxable */
#gstr2Table th:nth-child(9), #gstr2Table td:nth-child(9) { width: 90px; text-align: center; } /* Tax Rate */
#gstr2Table th:nth-child(10), #gstr2Table td:nth-child(10) { width: 120px; } /* Tax Amt */
#gstr2Table th:nth-child(11), #gstr2Table td:nth-child(11) { width: 130px; color: var(--success); font-weight: 800; } /* Total Price */
#gstr2Table th:nth-child(12), #gstr2Table td:nth-child(12) { width: 100px; text-align: center; } /* ITC Eligible */

.table tbody tr:hover { background: #f8fafc; }

/* TOTAL ROW */
.total-row {
    background: var(--brand-light) !important;
}
.total-row td {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    color: var(--brand) !important;
    font-size: 0.95rem;
    white-space: nowrap !important;
}

/* SUMMARY TABLE */
.summary-table h4 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--brand-dark);
    margin-top: 30px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ================= ALERTS ================= */
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

/* ================= MOBILE PERFECT FIX ================= */
@media (max-width: 768px) {
    .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
    .hero-actions .export-btn { width: 100%; justify-content: center; }

    .filter-form {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .filter-form .form-group { width: 100%; }
    .filter-form .btn-custom { width: 100%; height: 44px; margin-top: 8px; justify-content: center; }

    /* Table scroll container */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table { min-width: 1300px; } /* Ensures mobile still scrolls correctly */

    .table th, .table td { padding: 12px 10px; font-size: 0.8rem; }
}

/* SCROLLBAR */
.table-responsive::-webkit-scrollbar { height: 8px; }
.table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.table-responsive::-webkit-scrollbar-track { background: #f1f5f9; }
</style>
</head>
<body>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    
    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-file-invoice me-2" style="opacity:0.85;"></i>GSTR-2 Reports</h4>
            <h6>Generate and download GSTR-2 purchase reports</h6>
        </div>
        <div class="hero-actions">
            <button class="export-btn" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i>
                <span>Export to Excel</span>
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Filter Form -->
            <form class="filter-form" method="post">
                <div class="form-group">
                    <label for="filter_type">Filter By</label>
                    <select class="form-control" id="filter_type" name="filter_type" onchange="toggleFilterFields()">
                        <option value="monthly" <?php echo $filter_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $filter_type === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="form-group" id="month_field" style="<?php echo $filter_type !== 'monthly' ? 'display:none;' : ''; ?>">
                    <label for="month">Select Month</label>
                    <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
                </div>
                
                <div class="form-group" id="year_field" style="<?php echo $filter_type !== 'yearly' ? 'display:none;' : ''; ?>">
                    <label for="year">Select Year</label>
                    <select class="form-control" id="year" name="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group" id="start_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    <label for="start_date">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="form-group" id="end_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    <label for="end_date">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-custom">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table datatable" id="gstr2Table">
                    <thead>
                        <tr>
                            <th>Purchase ID</th>
                            <th>Date</th>
                            <th>Product Name</th>
                            <th>Product Code</th>
                            <th>HSN Code</th>
                            <th>Unit</th>
                            <th style="text-align:center;">Quantity</th>
                            <th>Taxable Value</th>
                            <th style="text-align:center;">Tax Rate</th>
                            <th>Tax Amount</th>
                            <th>Total Price</th>
                            <th style="text-align:center;">ITC Eligible</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sanitized_purchases)): ?>
                            <?php foreach ($sanitized_purchases as $purchase): ?>
                            <tr>
                                <td><span style="font-family: monospace; background: var(--bg); padding: 3px 6px; border-radius: 4px; color: var(--text-mid); font-weight: 700;"><?php echo $purchase['id']; ?></span></td>
                                <td><?php echo date('d-m-Y', strtotime($purchase['created_at'])); ?></td>
                                <td><?php echo $purchase['product_name']; ?></td>
                                <td><?php echo $purchase['product_code']; ?></td>
                                <td><span style="background:var(--bg); padding:3px 8px; border-radius:4px; font-size:0.75rem; color:var(--text-mid);"><?php echo $purchase['hsn_code']; ?></span></td>
                                <td><?php echo $purchase['unit']; ?></td>
                                <td style="text-align:center;"><span style="background:var(--bg); padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo $purchase['quantity']; ?></span></td>
                                <td>₹<?php echo number_format($purchase['taxable_value'],2); ?></td>
                                <td style="text-align:center;"><?php echo $purchase['tax_rate']; ?>%</td>
                                <td>₹<?php echo number_format($purchase['tax_amount'],2); ?></td>
                                <td>₹<?php echo number_format($purchase['total_price'],2); ?></td>
                                <td style="text-align:center;">
                                    <?php if($purchase['tax_rate'] == 0): ?>
                                        <span style="background: var(--danger-bg); color: var(--danger); padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 700;">No</span>
                                    <?php else: ?>
                                        <span style="background: var(--success-bg); color: var(--success); padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 700;">Yes</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="7" style="text-align: right; padding-right: 20px;">GRAND TOTAL</td>
                                <td>₹<?php echo number_format($total_taxable, 2); ?></td>
                                <td></td>
                                <td>₹<?php echo number_format($total_tax, 2); ?></td>
                                <td>₹<?php echo number_format($total_price, 2); ?></td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; opacity: 0.3; margin-bottom: 12px; display:block;"></i>
                                    <div style="font-weight: 600; font-size: 1rem; color: var(--text);">No purchases found for the selected period.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary Table -->
            <div class="summary-table">
                <h4><i class="fas fa-chart-pie"></i> Summary by Tax Rate</h4>
                <div class="table-responsive">
                    <table class="table datatable" style="min-width:800px;">
                        <thead>
                            <tr>
                                <th>Tax Rate</th>
                                <th>Total Invoices</th>
                                <th>Total Taxable Value</th>
                                <th>Total GST</th>
                                <th>Total Amount</th>
                                <th style="text-align:center;">ITC Eligible</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tax_rates = [0, 5, 8, 10, 12];
                            foreach ($tax_rates as $rate) {
                                $data = $summary_data[$rate] ?? [
                                    'total_invoices' => 0,
                                    'total_taxable_value' => 0,
                                    'total_tax' => 0,
                                    'total_price' => 0
                                ];
                                ?>
                                <tr>
                                    <td><span style="background:var(--bg); padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo $rate == 0 ? 'None' : $rate . '%'; ?></span></td>
                                    <td><?php echo $data['total_invoices']; ?></td>
                                    <td>₹<?php echo number_format($data['total_taxable_value'], 2); ?></td>
                                    <td>₹<?php echo number_format($data['total_tax'], 2); ?></td>
                                    <td style="font-weight:700; color:var(--text);">₹<?php echo number_format($data['total_price'], 2); ?></td>
                                    <td style="text-align:center;">
                                        <?php if($rate == 0): ?>
                                            <span style="background: var(--danger-bg); color: var(--danger); padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 700;">No</span>
                                        <?php else: ?>
                                            <span style="background: var(--success-bg); color: var(--success); padding: 2px 8px; border-radius: 50px; font-size: 0.7rem; font-weight: 700;">Yes</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Toggle filter fields based on filter type
    window.toggleFilterFields = function() {
        const filterType = $('#filter_type').val();
        $('#month_field').toggle(filterType === 'monthly');
        $('#year_field').toggle(filterType === 'yearly');
        $('#start_date_field').toggle(filterType === 'custom');
        $('#end_date_field').toggle(filterType === 'custom');
    };

    // Export to Excel
    window.exportToExcel = function() {
        try {
            const table = document.getElementById('gstr2Table');
            const rows = table.querySelectorAll('tr');
            let csvContent = "Purchase ID,Date,Product Name,Product Code,HSN Code,Unit,Quantity,Taxable Value,Tax Rate,Tax Amount,Total Price,ITC Eligible\n";

            let totalTaxable = 0, totalTax = 0, totalPrice = 0;
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].querySelectorAll('td');
                if (cols.length < 12) continue; // Skip empty or invalid rows
                const rowData = [
                    cols[0].innerText.replace(/,/g, ''),
                    cols[1].innerText.replace(/,/g, ''),
                    cols[2].innerText.replace(/,/g, ''),
                    cols[3].innerText.replace(/,/g, ''),
                    cols[4].innerText.replace(/,/g, ''),
                    cols[5].innerText.replace(/,/g, ''),
                    cols[6].innerText.replace(/,/g, ''),
                    cols[7].innerText.replace(/,/g, '').replace('₹', ''),
                    cols[8].innerText.replace(/,/g, ''),
                    cols[9].innerText.replace(/,/g, '').replace('₹', ''),
                    cols[10].innerText.replace(/,/g, '').replace('₹', ''),
                    cols[11].innerText.replace(/,/g, '')
                ];
                csvContent += rowData.join(',') + '\n';
                if (i < rows.length - 1) { // Exclude total row from calculations
                    totalTaxable += parseFloat(cols[7].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                    totalTax += parseFloat(cols[9].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                    totalPrice += parseFloat(cols[10].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                }
            }

            // Append totals row
            csvContent += `,,,,,,Total,${totalTaxable.toFixed(2)},,${totalTax.toFixed(2)},${totalPrice.toFixed(2)},\n`;

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'gstr2_report.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (e) {
            console.error('Error in exportToExcel:', e);
            alert('Failed to export to Excel. Check console for details.');
        }
    };
});
</script>

<?php
ob_end_flush();
?>