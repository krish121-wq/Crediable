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

$page_title = "GSTR-1 Reports";
$success_message = '';
$error_message = '';
$debug_messages = [];
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

$filter_type = $_POST['filter_type'] ?? 'monthly';
$selected_month = $_POST['month'] ?? date('Y-m');
$selected_year = $_POST['year'] ?? date('Y');
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$timezone = new DateTimeZone('Asia/Kolkata');

$sanitized_sales = [];
$total_tax = 0;
$total_price = 0;

// Build query based on filter
try {
    $query = "
        SELECT s.id, s.customer_name, s.customer_phone, s.customer_address, s.quantity, s.total_price, s.created_at,
               p.product_name, p.sell_price as price_per_piece, p.gst_percentage, p.id as product_id,
               c.company_gst as customer_gst
        FROM sales s
        JOIN products p ON s.product_id = p.id
        LEFT JOIN company_details c ON 1=1
        WHERE s.created_by = ?
    ";
    $params = [$created_by_id];
    $param_types = "i";

    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(s.created_at, '%Y-%m') = ?";
        $params[] = $selected_month;
        $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(s.created_at) = ?";
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
        $query .= " AND s.created_at BETWEEN ? AND ?";
        $params[] = $start->format('Y-m-d 00:00:00');
        $params[] = $end->format('Y-m-d 23:59:59');
        $param_types .= "ss";
    }

    $query .= " ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $sales = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    // Group sales by invoice (same customer and created_at)
    $grouped_sales = [];
    foreach ($sales as $sale) {
        $key = $sale['customer_name'] . '|' . $sale['customer_phone'] . '|' . $sale['customer_address'] . '|' . $sale['created_at'];
        if (!isset($grouped_sales[$key])) {
            $grouped_sales[$key] = [
                'ids' => [],
                'customer_name' => $sale['customer_name'],
                'customer_phone' => $sale['customer_phone'],
                'customer_address' => $sale['customer_address'],
                'customer_gst' => $sale['customer_gst'],
                'created_at' => $sale['created_at'],
                'products' => [],
                'total_price' => 0
            ];
        }
        
        // Calculate the pre-tax value
        $tax_rate = $sale['gst_percentage'] / 100;
        $taxable_value = ($sale['price_per_piece'] * $sale['quantity']) / (1 + $tax_rate);
        $tax_amount = $taxable_value * $tax_rate;
        
        $grouped_sales[$key]['ids'][] = $sale['id'];
        $grouped_sales[$key]['products'][] = [
            'product_id' => $sale['product_id'],
            'product_name' => $sale['product_name'],
            'quantity' => $sale['quantity'],
            'price_per_piece' => $sale['price_per_piece'],
            'tax_rate' => $tax_rate,
            'taxable_value' => $taxable_value,
            'tax_amount' => $tax_amount,
            'total_price' => $sale['total_price']
        ];
        $grouped_sales[$key]['total_price'] += $sale['total_price'];
        $total_tax += $tax_amount;
        $total_price += $sale['total_price'];
    }

    // Sanitize sales data for display and export
    $sanitized_sales = array_map(function($sale) {
        return [
            'ids' => $sale['ids'],
            'customer_name' => htmlspecialchars($sale['customer_name'], ENT_QUOTES, 'UTF-8'),
            'customer_phone' => htmlspecialchars($sale['customer_phone'], ENT_QUOTES, 'UTF-8'),
            'customer_address' => htmlspecialchars(str_replace(["\r", "\n"], ' ', $sale['customer_address']), ENT_QUOTES, 'UTF-8'),
            'customer_gst' => htmlspecialchars($sale['customer_gst'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'products' => array_map(function($prod) {
                return [
                    'product_id' => (int)$prod['product_id'],
                    'product_name' => htmlspecialchars($prod['product_name'], ENT_QUOTES, 'UTF-8'),
                    'quantity' => (int)$prod['quantity'],
                    'price_per_piece' => (float)$prod['price_per_piece'],
                    'tax_rate' => (float)$prod['tax_rate'],
                    'taxable_value' => (float)$prod['taxable_value'],
                    'tax_amount' => (float)$prod['tax_amount'],
                    'total_price' => (float)$prod['total_price']
                ];
            }, $sale['products']),
            'total_price' => (float)$sale['total_price'],
            'created_at' => $sale['created_at']
        ];
    }, array_values($grouped_sales));

} catch (Exception $e) {
    $error_message = "Error fetching sales data: " . $e->getMessage();
    error_log("GSTR-1 Fetch Error: " . $e->getMessage());
    file_put_contents('debug.log', "GSTR-1 Fetch Error: " . $e->getMessage() . " | Line: " . $e->getLine() . "\n", FILE_APPEND);
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

/* ================= GLOBAL ================= */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
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

@media (max-width: 992px) {
    .content { margin-left: 0; padding: 16px 14px; }
}

/* ================= HERO SECTION ================= */
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
    content: ''; position: absolute;
    right: -50px; top: -50px;
    width: 200px; height: 200px;
    border-radius: 50%; background: rgba(255,255,255,.06);
}
.hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.45rem; font-weight: 800; color: #fff; margin:0 0 4px 0;
}
.hero-sub { color: rgba(255,255,255,.75); font-size: 0.85rem; margin:0; font-weight: 500;}
.hero-actions { position: relative; z-index: 10; }

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

/* ================= FILTER ================= */
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
}

.form-control:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    outline: none;
}

/* ================= BUTTONS ================= */
.btn-custom {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    padding: 10px 22px;
    border-radius: 50px;
    border: none;
    font-size: 0.88rem;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(2,132,199,.25);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(2,132,199,.35);
}

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
}
.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(22,163,74,.35);
}

/* ================= TABLE ================= */
.table-responsive {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
}

/* MOST IMPORTANT FIX */
.table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;   /* same layout like desktop */
    table-layout: fixed; /* fixed column width */
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

/* COLUMN WIDTH CONTROL (MAIN FIX) */
.table th:nth-child(1), .table td:nth-child(1) { width: 120px; } /* Invoice */
.table th:nth-child(2), .table td:nth-child(2) { width: 100px; } /* Date */
.table th:nth-child(3), .table td:nth-child(3) { width: 180px; font-weight: 600; color: var(--text); } /* Customer */
.table th:nth-child(4), .table td:nth-child(4) { width: 130px; font-family: monospace; font-size: 0.8rem; } /* GST */
.table th:nth-child(5), .table td:nth-child(5) {
    width: 220px;               /* Product Name FIX */
    white-space: normal;
    word-break: break-word;
}
.table th:nth-child(6), .table td:nth-child(6) { width: 80px; text-align: center; }  /* Qty */
.table th:nth-child(7), .table td:nth-child(7) { width: 130px; } /* Taxable */
.table th:nth-child(8), .table td:nth-child(8) { width: 90px; text-align: center; } /* Rate */
.table th:nth-child(9), .table td:nth-child(9) { width: 120px; } /* Tax */
.table th:nth-child(10), .table td:nth-child(10) { width: 130px; font-weight: 700; color: var(--success); } /* Total */

/* ROW HOVER */
.table tbody tr:hover { background: #f8fafc; }

/* TOTAL ROW */
.total-row {
    background: var(--brand-light) !important;
}
.total-row td {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    color: var(--brand);
    font-size: 0.95rem;
}

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

/* ================= MOBILE ================= */
@media (max-width: 768px) {
    .content { padding: 16px 12px; }
    
    .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
    .hero-actions .export-btn { width: 100%; justify-content: center; }

    .filter-form {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    .filter-form .form-group { width: 100%; }
    .filter-form .btn-custom { width: 100%; height: 44px; margin-top: 8px; }

    /* KEEP TABLE SAME LIKE DESKTOP (Allows scroll) */
    .table { min-width: 1200px; }
}

/* SCROLLBAR */
.table-responsive::-webkit-scrollbar { height: 8px; }
.table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.table-responsive::-webkit-scrollbar-track { background: #f1f5f9; }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    
    <!-- PAGE HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-file-invoice-dollar me-2"></i>GSTR-1 Reports</div>
            <div class="hero-sub">Generate and download accurate GSTR-1 sales reports</div>
        </div>
        <div class="hero-actions">
            <button class="export-btn" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
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
                
                <div class="form-group apply-btn">
                    <button type="submit" class="btn-custom">
                        <i class="fas fa-filter me-2"></i> Apply Filter
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table datatable" id="gstr1Table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>GSTIN</th>
                            <th>Product Name</th>
                            <th style="text-align:center;">Quantity</th>
                            <th>Taxable Value</th>
                            <th style="text-align:center;">Tax Rate</th>
                            <th>Tax Amount</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sanitized_sales)): ?>
                            <?php foreach ($sanitized_sales as $sale): ?>
                                <?php foreach ($sale['products'] as $index => $product): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo count($sale['products']); ?>" style="color:var(--text-muted); font-weight:600;">
                                                <?php echo htmlspecialchars(implode('-', $sale['ids'])); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo (new DateTime($sale['created_at'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('d-m-Y'); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo htmlspecialchars($sale['customer_name']); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo htmlspecialchars($sale['customer_gst']); ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td style="text-align:center;"><span style="background:var(--bg); padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo $product['quantity']; ?></span></td>
                                        <td>₹<?php echo number_format($product['taxable_value'], 2); ?></td>
                                        <td style="text-align:center;"><?php echo number_format($product['tax_rate'] * 100, 1); ?>%</td>
                                        <td>₹<?php echo number_format($product['tax_amount'], 2); ?></td>
                                        <td class="amount">₹<?php echo number_format($product['total_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="6" style="text-align: right; padding-right: 20px;">GRAND TOTAL</td>
                                <td>₹<?php echo number_format(array_sum(array_map(function($sale) {
                                    return array_sum(array_map(function($prod) { return $prod['taxable_value']; }, $sale['products']));
                                }, $sanitized_sales)), 2); ?></td>
                                <td></td>
                                <td>₹<?php echo number_format($total_tax, 2); ?></td>
                                <td>₹<?php echo number_format($total_price, 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 60px 20px; color: var(--text-muted);">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; opacity: 0.3; margin-bottom: 12px; display:block;"></i>
                                    <div style="font-weight: 600; font-size: 1rem; color: var(--text);">No sales found for the selected period.</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            const table = document.getElementById('gstr1Table');
            const rows = table.querySelectorAll('tr');
            let csvContent = "Invoice ID,Date,Customer Name,GSTIN,Product Name,Quantity,Taxable Value,Tax Rate,Tax Amount,Total Price\n";

            let totalTaxable = 0, totalTax = 0, totalPrice = 0;
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].querySelectorAll('td');
                if (cols.length < 10) continue; // Skip empty or invalid rows
                const rowData = [
                    cols[0].innerText.replace(/,/g, ''),
                    cols[1].innerText.replace(/,/g, ''),
                    cols[2].innerText.replace(/,/g, ''),
                    cols[3].innerText.replace(/,/g, ''),
                    cols[4].innerText.replace(/,/g, ''),
                    cols[5].innerText.replace(/,/g, ''),
                    cols[6].innerText.replace(/,/g, '').replace('₹', ''),
                    cols[7].innerText.replace(/,/g, ''),
                    cols[8].innerText.replace(/,/g, '').replace('₹', ''),
                    cols[9].innerText.replace(/,/g, '').replace('₹', '')
                ];
                csvContent += rowData.join(',') + '\n';
                if (i < rows.length - 1) { // Exclude total row from calculations
                    totalTaxable += parseFloat(cols[6].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                    totalTax += parseFloat(cols[8].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                    totalPrice += parseFloat(cols[9].innerText.replace(/,/g, '').replace('₹', '')) || 0;
                }
            }

            // Append totals row
            csvContent += `,,,,,Total,${totalTaxable.toFixed(2)},,${totalTax.toFixed(2)},${totalPrice.toFixed(2)}\n`;

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'gstr1_report_credible.csv');
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