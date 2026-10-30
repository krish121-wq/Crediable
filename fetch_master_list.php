<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['sidebar_open']))  { $_SESSION['sidebar_open'] = true; }
if (!isset($_SESSION['csrf_token']))    { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=fetch_master_list.php");
    exit();
}

$stmt = $conn->prepare("SELECT id, product_name, product_code, product_design_code, product_description, hsn_code, unit, quantity, purchase_price, sell_price, gst_percentage, COALESCE(cgst, 0) as cgst, COALESCE(sgst, 0) as sgst, COALESCE(igst, 0) as igst, category_id FROM products ORDER BY product_name");
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch categories for dropdown
$user_id = $_SESSION['user_id'];
$categories_list = [];
$cat_result = $conn->query("SELECT id, category_name FROM categories WHERE created_by = $user_id AND status = 1 ORDER BY category_name ASC");
if ($cat_result) {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}


foreach ($products as &$p) {
    $gst_pct = floatval($p['gst_percentage']);
    if ($gst_pct > 0) {
        if ($p['sgst'] == 0 && $p['cgst'] == 0 && $p['igst'] == 0) {
            $p['sgst'] = $gst_pct / 2;
            $p['cgst'] = $gst_pct / 2;
            $p['igst'] = 0;
        }
        $p['sgst'] = round($p['sgst'], 2);
        $p['cgst'] = round($p['cgst'], 2);
        $p['igst'] = round($p['igst'], 2);
    }
}
unset($p);

$is_staff = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff';
$total_products = count($products);
$total_qty = array_sum(array_column($products, 'quantity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Product List – Credible</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --brand: #0284c7;
        --brand-dark: #0b1220;
        --brand-mid: #0ea5e9;
        --brand-light: #e0f2fe;
        --success: #16a34a;
        --success-bg: #dcfce7;
        --danger: #dc2626;
        --danger-bg: #fee2e2;
        --warning: #d97706;
        --warning-bg: #fef3c7;
        --info: #0284c7;
        --info-bg: #e0f2fe;
        --bg: #f5f8fb;
        --card: #ffffff;
        --border: #e2e8f0;
        --text: #0b1220;
        --text-mid: #475569;
        --text-muted: #94a3b8;
        --radius-sm: 7px;
        --radius-md: 12px;
        --radius-lg: 18px;
        --shadow-sm: 0 2px 8px rgba(2,132,199,.07);
        --shadow-md: 0 8px 24px rgba(2,132,199,.11);
        --shadow-lg: 0 16px 48px rgba(2,132,199,.16);
        --t: all .22s cubic-bezier(.4,0,.2,1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 13.5px; }

    .page-wrap {
        margin-top: 64px;
        margin-left: 260px;
        padding: 22px 24px 48px;
        min-height: calc(100vh - 64px);
        transition: var(--t);
    }
    @media (max-width: 992px) { .page-wrap { margin-left: 0; padding: 16px 14px 40px; } }

    .page-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 20px 26px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px; margin-bottom: 20px;
        box-shadow: var(--shadow-lg); position: relative; overflow: hidden; flex-wrap: wrap;
    }
    .page-hero::before {
        content:''; position:absolute;
        right:-40px; top:-40px;
        width:160px; height:160px;
        border-radius:50%; background:rgba(255,255,255,.06);
    }
    .hero-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:1.25rem; font-weight:800; color:#fff; }
    .hero-sub { color:rgba(255,255,255,.72); font-size:.8rem; margin-top:2px; }
    .hero-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

    .stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    @media (max-width: 640px) { .stats-row { grid-template-columns: repeat(3, 1fr); gap: 8px; } }
    .stat-card {
        background: var(--card); border-radius: var(--radius-md);
        border: 1px solid var(--border); padding: 14px 16px;
        display: flex; align-items: center; gap: 12px;
        box-shadow: var(--shadow-sm); transition: var(--t);
    }
    .stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .stat-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem; flex-shrink: 0;
    }
    .stat-icon.purple { background: var(--brand-light); color: var(--brand); }
    .stat-icon.blue { background: var(--info-bg); color: var(--info); }
    .stat-icon.green { background: var(--success-bg); color: var(--success); }
    .stat-val { font-family:'Plus Jakarta Sans',sans-serif; font-size:1.15rem; font-weight:800; color:var(--brand); }
    .stat-label { font-size:.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.3px; font-weight:600; margin-top:2px; }

    .toolbar {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        padding: 12px 16px;
        margin-bottom: 16px;
        display: flex; align-items: center; gap: 12px;
        box-shadow: var(--shadow-sm); flex-wrap: wrap;
    }
    .search-wrap { position: relative; flex: 1; min-width: 180px; }
    .search-wrap .si { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:.82rem; }
    .search-input {
        width: 100%; padding: 9px 32px 9px 34px;
        border: 1.5px solid var(--border); border-radius: 50px;
        font-size: .85rem; background: #fafafa; color: var(--text);
        outline: none; transition: var(--t);
    }
    .search-input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(2,132,199,.12); }
    .search-clear { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; display:none; }
    .search-clear.show { display:block; }

    .category-filter-wrap { 
        position: relative; 
        min-width: 220px; 
    }
    .category-filter-wrap .ci { 
        position: absolute; 
        left: 14px; 
        top: 50%; 
        transform: translateY(-50%); 
        color: var(--brand); 
        font-size: .85rem; 
        pointer-events: none; 
    }
    .category-select {
        width: 100%; 
        height: 42px;
        padding: 8px 36px 8px 36px;
        border: 1.5px solid #cbd5e1; 
        border-radius: 50px;
        font-size: .85rem; 
        font-weight: 600;
        font-family: 'Inter', sans-serif;
        background: #ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%230284c7' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") no-repeat right 14px center / 12px;
        color: var(--text);
        outline: none; 
        transition: var(--t); 
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        box-shadow: 0 2px 6px rgba(0,0,0,.03);
    }
    .category-select:hover {
        border-color: var(--brand);
        box-shadow: 0 3px 10px rgba(2,132,199,.10);
    }
    .category-select:focus { 
        border-color: var(--brand); 
        background-color: #fff; 
        box-shadow: 0 0 0 3px rgba(2,132,199,.15); 
    }

    .toolbar-info { font-size:.78rem; color:var(--text-muted); white-space:nowrap; }

    .table-card {
        background: var(--card); border-radius: var(--radius-md);
        border: 1px solid var(--border); box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    .table-scroll { overflow-x: auto; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table thead th {
        background: linear-gradient(135deg, var(--brand), var(--brand-mid));
        color: #fff; padding: 11px 12px;
        font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px;
        white-space: nowrap; text-align: left; border: none;
    }
    .data-table thead th.center { text-align: center; }
    .data-table tbody tr { border-bottom: 1px solid #f0eef6; transition: background .15s; }
    .data-table tbody tr:last-child { border-bottom: none; }
    .data-table tbody tr:hover { background: #faf9fc; }
    .data-table td { padding: 11px 12px; vertical-align: middle; font-size: .85rem; }
    .data-table td.center { text-align: center; }

    .prod-cell { display: flex; align-items: center; gap: 9px; }
    .prod-avatar {
        width: 32px; height: 32px; border-radius: 8px;
        background: linear-gradient(135deg, var(--brand), var(--brand-mid));
        color: #fff; font-weight: 800; font-size: .75rem;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .prod-name { font-weight: 600; font-size: .88rem; }
    .prod-desc { font-size: .72rem; color: var(--text-muted); margin-top: 1px; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .code-chip {
        font-family: monospace; font-size: .78rem; letter-spacing: .3px;
        background: #f5f4f8; border: 1px solid var(--border);
        border-radius: 5px; padding: 2px 7px; color: var(--text-mid);
        white-space: nowrap;
    }

    .unit-badge {
        background: var(--info-bg); color: var(--info);
        border-radius: 50px; padding: 3px 9px;
        font-size: .7rem; font-weight: 700;
        text-transform: uppercase; white-space: nowrap;
    }

    .qty-cell { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:.92rem; color:var(--brand); }
    .qty-cell.low { color: var(--danger); }
    .qty-cell.zero { color: var(--danger); }

    .gst-chip {
        background: var(--brand-light); color: var(--brand);
        border-radius: 50px; padding: 3px 8px;
        font-size: .7rem; font-weight: 700; white-space: nowrap;
    }
    
    .gst-breakup {
        font-size: .6rem;
        color: var(--text-muted);
        margin-top: 3px;
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .gst-breakup span {
        padding: 1px 4px;
        border-radius: 3px;
    }
    .gst-sgst { background: #dbeafe; color: #1d4ed8; }
    .gst-cgst { background: #fce7f3; color: #9d174d; }
    .gst-igst { background: #fef3c7; color: #92400e; }

    .price-cell { font-weight: 600; font-size: .88rem; color: var(--text); }
    .price-cell.muted { color: var(--text-muted); font-size: .8rem; }
    .price-cell.sell { color: var(--success); font-weight: 700; }

    .action-group { display:flex; gap:5px; align-items:center; justify-content:center; }
    .act-btn {
        width: 30px; height: 30px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        font-size: .78rem; color: #fff; border: none; cursor: pointer;
        transition: var(--t); text-decoration: none; flex-shrink: 0;
        position: relative;
    }
    .act-btn:hover { transform: translateY(-2px) scale(1.12); }
    .act-btn.edit { background: linear-gradient(135deg,var(--brand),var(--brand-mid)); box-shadow:0 3px 10px rgba(87,31,90,.22); }
    .act-btn.barcode { background: linear-gradient(135deg,#374151,#111827); box-shadow:0 3px 10px rgba(0,0,0,.18); }
    .act-btn.del { background: linear-gradient(135deg,#ef4444,#b91c1c); box-shadow:0 3px 10px rgba(239,68,68,.22); }
    .act-btn::after {
        content: attr(data-tip);
        position: absolute; bottom: calc(100% + 5px); left:50%; transform:translateX(-50%);
        background: #1a1625; color:#fff; font-size:.65rem; padding:3px 7px;
        border-radius:5px; white-space:nowrap; opacity:0; pointer-events:none; transition:opacity .2s;
    }
    .act-btn:hover::after { opacity:1; }

    .btn-secondary-c {
        background: rgba(255, 255, 255, 0.16);
        backdrop-filter: blur(8px);
        color: #fff !important;
        border: 1.5px solid rgba(255, 255, 255, 0.45);
        padding: 9px 18px;
        border-radius: 50px;
        font-size: .83rem;
        font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: var(--t);
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-secondary-c:hover {
        background: rgba(255, 255, 255, 0.28);
        border-color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
    }

    .btn-primary-c {
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: #fff !important; border: none;
        padding: 9px 20px; border-radius: 50px;
        font-size: .83rem; font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        display: inline-flex; align-items: center; gap: 6px;
        cursor: pointer; transition: var(--t);
        box-shadow: 0 4px 14px rgba(2,132,199,.26);
        text-decoration: none; white-space: nowrap;
    }
    .btn-primary-c:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(2,132,199,.36); }

    .modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); }
    .modal-hdr {
        background: linear-gradient(135deg, var(--brand), var(--brand-mid));
        color: #fff; border-radius: var(--radius-md) var(--radius-md) 0 0;
        padding: 15px 18px;
    }
    .modal-hdr .modal-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:.95rem; }
    .modal-body { max-height: 55vh; overflow-y: auto; padding: 18px 20px; }
    .modal-footer { border-top: 1px solid var(--border); padding: 12px 18px; background: #fff; position: sticky; bottom: 0; z-index: 10; }

    .mfield { margin-bottom: 13px; }
    .mfield label { display:block; font-size:.69rem; font-weight:700; color:var(--text-mid); text-transform:uppercase; letter-spacing:.3px; margin-bottom:5px; }
    .mfield input, .mfield textarea, .mfield select {
        width:100%; padding:9px 11px;
        border:1.5px solid var(--border); border-radius:var(--radius-sm);
        font-size:.87rem; font-family:'Inter',sans-serif; color:var(--text);
        background:#fafafa; outline:none; transition:var(--t);
    }
    .mfield input:focus, .mfield textarea:focus, .mfield select:focus {
        border-color:var(--brand); background:#fff;
        box-shadow: 0 0 0 3px rgba(87,31,90,.09);
    }
    .mfield input[readonly], .mfield textarea[readonly] { background:#f0eef6; color:var(--text-mid); cursor:not-allowed; }
    .mfield .calc-note { font-size:.68rem; color:var(--brand); margin-top:3px; font-weight:600; }

    .price-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:480px) { .price-row { grid-template-columns:1fr; } }

    .empty-row td { text-align:center; padding:50px 20px; color:var(--text-muted); }
    .empty-icon { font-size:2.2rem; opacity:.3; margin-bottom:10px; }

    .del-confirm-icon {
        width:56px; height:56px; background:var(--danger-bg);
        border-radius:50%; display:flex; align-items:center; justify-content:center;
        margin:0 auto 14px; font-size:1.6rem; color:var(--danger);
    }

    .toast-msg {
        position: fixed; top:20px; right:20px; z-index:9999;
        padding:12px 18px; border-radius:var(--radius-sm);
        font-size:.85rem; font-weight:600;
        display:flex; align-items:center; gap:9px;
        box-shadow: var(--shadow-md);
        animation: toastIn .3s ease;
    }
    @keyframes toastIn { from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)} }
    .toast-msg.success { background:#f0fdf4; border:1px solid #86efac; color:var(--success); }
    .toast-msg.danger { background:var(--danger-bg); border:1px solid #fca5a5; color:var(--danger); }

    @media (max-width: 768px) {
        .data-table thead { display: none; }
        .data-table tbody tr {
            display: block; margin-bottom: 12px;
            border: 1px solid var(--border); border-radius: var(--radius-md);
            padding: 12px 14px; background: var(--card); box-shadow: var(--shadow-sm);
        }
        .data-table td {
            display: flex; justify-content: space-between; align-items: center;
            padding: 6px 0; border-bottom: 1px solid #f5f4f8;
            font-size:.83rem;
        }
        .data-table td:last-child { border-bottom: none; }
        .data-table td::before {
            content: attr(data-label);
            font-weight: 700; color: var(--text-muted);
            font-size: .68rem; text-transform: uppercase; letter-spacing: .3px;
        }
        .data-table td.center { justify-content: space-between; }
    }

    @media (max-width: 500px) {
        .stats-row { grid-template-columns: 1fr; }
        .page-hero { flex-direction: column; align-items: flex-start; }
    }
    </style>
</head>
<body>
<?php include('layout.php'); ?>

<div class="page-wrap">

    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-boxes-stacked me-2" style="opacity:.8"></i>Product List</div>
            <div class="hero-sub">Manage your complete product catalog</div>
        </div>
        <div class="hero-actions">
            <a href="export_products_csv.php" class="btn-secondary-c">
                <i class="fas fa-file-csv"></i> Export
            </a>
            <a href="#" class="btn-secondary-c" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-upload"></i> Import
            </a>
            <?php if (!$is_staff): ?>
            <a href="categories-list.php" class="btn-secondary-c">
                <i class="fas fa-tags"></i> Add Category
            </a>
            <a href="master_list.php" class="btn-primary-c">
                <i class="fas fa-plus-circle"></i> Add Product
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <div class="toast-msg success" id="successToast">
            <i class="fas fa-check-circle"></i> Product saved successfully!
        </div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <div class="stat-val"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">Products</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-cubes"></i></div>
            <div>
                <div class="stat-val"><?php echo number_format($total_qty); ?></div>
                <div class="stat-label">Total Stock</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
            <div>
                <div class="stat-val"><?php echo number_format(count(array_filter($products, function($p) { return $p['quantity'] > 0; }))); ?></div>
                <div class="stat-label">In Stock</div>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search si"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by name, code, HSN…" autocomplete="off">
            <button type="button" id="clearSearch" class="search-clear" title="Clear"><i class="fas fa-times"></i></button>
        </div>
        <div class="category-filter-wrap">
            <i class="fas fa-filter ci"></i>
            <select id="categoryFilter" class="category-select" title="Filter by Category">
                <option value="">All Categories</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="toolbar-info">
            Showing <strong id="visibleCount"><?php echo $total_products; ?></strong> of <?php echo $total_products; ?> products
        </div>
    </div>

    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table" id="productsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Code</th>
                        <th>Design</th>
                        <th>HSN</th>
                        <th class="center">Unit</th>
                        <th class="center">Stock</th>
                        <th class="center">GST</th>
                        <th>Pur. Ex.</th>
                        <th>Pur. Inc.</th>
                        <th>Sell Ex.</th>
                        <th>Sell Inc.</th>
                        <?php if (!$is_staff): ?><th class="center">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($products)): ?>
                    <tr class="empty-row">
                        <td colspan="<?php echo $is_staff ? 11 : 12; ?>">
                            <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                            <div style="font-weight:600;margin-bottom:5px">No products yet</div>
                            <div style="font-size:.8rem">Click <strong>Add Product</strong> to add your first product.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p):
                        $gst_pct  = floatval($p['gst_percentage']);
                        $gst_mult = 1 + ($gst_pct / 100);
                        $pur_inc  = floatval($p['purchase_price']);
                        $pur_ex   = $gst_mult > 0 ? $pur_inc / $gst_mult : 0;
                        $sell_inc = floatval($p['sell_price']);
                        $sell_ex  = $gst_mult > 0 ? $sell_inc / $gst_mult : 0;
                        $qty      = (int)$p['quantity'];
                        $initials = strtoupper(substr($p['product_name'], 0, 2));
                        $qtyClass = $qty == 0 ? 'zero' : ($qty <= 5 ? 'low' : '');
                        $searchVal = strtolower($p['product_name'].' '.$p['product_code'].' '.($p['hsn_code']??'').' '.$p['product_design_code']);
                        
                        $sgst = floatval($p['sgst']);
                        $cgst = floatval($p['cgst']);
                        $igst = floatval($p['igst']);
                    ?>
                    <tr data-id="<?php echo $p['id']; ?>" data-category="<?php echo htmlspecialchars($p['category_id'] ?? ''); ?>" data-search="<?php echo htmlspecialchars($searchVal); ?>" style="cursor:pointer">
                        <td data-label="Product">
                            <div class="prod-cell">
                                <div class="prod-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="prod-name"><?php echo htmlspecialchars($p['product_name']); ?></div>
                                    <?php if (!empty($p['product_description'])): ?>
                                        <div class="prod-desc"><?php echo htmlspecialchars($p['product_description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td data-label="Code"><span class="code-chip"><?php echo htmlspecialchars($p['product_code']); ?></span></td>
                        <td data-label="Design"><span class="code-chip"><?php echo htmlspecialchars($p['product_design_code']); ?></span></td>
                        <td data-label="HSN"><?php echo htmlspecialchars($p['hsn_code'] ?? '—'); ?></td>
                        <td data-label="Unit" class="center"><span class="unit-badge"><?php echo htmlspecialchars($p['unit'] ?? '—'); ?></span></td>
                        <td data-label="Stock" class="center"><span class="qty-cell <?php echo $qtyClass; ?>"><?php echo number_format($qty); ?></span></td>
                        <td data-label="GST" class="center">
                            <span class="gst-chip"><?php echo number_format($gst_pct, 2); ?>%</span>
                            <?php if ($gst_pct > 0): ?>
                            <div class="gst-breakup">
                                <?php if ($sgst > 0): ?>
                                    <span class="gst-sgst">SGST <?php echo number_format($sgst, 2); ?>%</span>
                                <?php endif; ?>
                                <?php if ($cgst > 0): ?>
                                    <span class="gst-cgst">CGST <?php echo number_format($cgst, 2); ?>%</span>
                                <?php endif; ?>
                                <?php if ($igst > 0): ?>
                                    <span class="gst-igst">IGST <?php echo number_format($igst, 2); ?>%</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Pur. Ex." class="price-cell muted">₹<?php echo number_format($pur_ex, 2); ?></td>
                        <td data-label="Pur. Inc." class="price-cell">₹<?php echo number_format($pur_inc, 2); ?></td>
                        <td data-label="Sell Ex." class="price-cell muted">₹<?php echo number_format($sell_ex, 2); ?></td>
                        <td data-label="Sell Inc." class="price-cell sell">₹<?php echo number_format($sell_inc, 2); ?></td>
                        <?php if (!$is_staff): ?>
                        <td data-label="Actions" class="center">
                            <div class="action-group">
                                <button class="act-btn edit" data-id="<?php echo $p['id']; ?>" data-tip="Edit" onclick="event.stopPropagation(); openEditModal(<?php echo $p['id']; ?>)"><i class="fas fa-pen"></i></button>
                                <a href="print_barcode.php?id=<?php echo $p['id']; ?>" class="act-btn barcode" data-tip="Barcode" onclick="event.stopPropagation()" target="_blank"><i class="fas fa-barcode"></i></a>
                                <button class="act-btn del" data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['product_name']); ?>" data-tip="Delete" onclick="event.stopPropagation()"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- EDIT PRODUCT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-hdr">
                <div class="modal-title"><i class="fas fa-edit me-2"></i>Edit Product</div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProductForm" method="POST" action="ajax_fetch_master_product.php">
                <div class="modal-body" style="padding:18px 20px">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="edit_product" value="1">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                        <div class="mfield">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="mb-0">Category <span style="color:var(--danger)">*</span></label>
                                <a href="categories-list.php" target="_blank" style="font-size: 0.72rem; color: var(--info); font-weight: 700; text-decoration: none;">
                                    <i class="fas fa-plus-circle me-1"></i>Add Category
                                </a>
                            </div>
                            <select name="category_id" id="edit_category_id" required>
                                <option value="" disabled selected>Select Category</option>
                                <?php foreach ($categories_list as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mfield">
                            <label>Product Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="product_name" id="edit_product_name" required>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="mfield">
                            <label>Product Code <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="product_code" id="edit_product_code" required>
                        </div>
                        <div class="mfield">
                            <label>Design Code <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="product_design_code" id="edit_product_design_code" required>
                        </div>
                    </div>


                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                        <div class="mfield">
                            <label>HSN Code</label>
                            <input type="text" name="hsn_code" id="edit_hsn_code">
                        </div>
                        <div class="mfield">
                            <label>Unit <span style="color:var(--danger)">*</span></label>
                            <select name="unit" id="edit_unit" required>
                                <option value="Piece">Piece</option>
                                <option value="KG">KG</option>
                                <option value="Liter">Liter</option>
                                <option value="ML">ML</option>
                                <option value="Gram">Gram</option>
                                <option value="Box">Box</option>
                                <option value="Dozen">Dozen</option>
                                <option value="Meter">Meter</option>
                            </select>
                        </div>
                        <div class="mfield">
                            <label>GST (%)</label>
                            <input type="number" step="0.01" name="gst_percentage" id="edit_gst_percentage" onchange="updateEditGST()">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                        <div class="mfield">
                            <label>SGST (%)</label>
                            <input type="number" step="0.01" name="sgst" id="edit_sgst" readonly style="background:#f0eef6">
                        </div>
                        <div class="mfield">
                            <label>CGST (%)</label>
                            <input type="number" step="0.01" name="cgst" id="edit_cgst" readonly style="background:#f0eef6">
                        </div>
                        <div class="mfield">
                            <label>IGST (%)</label>
                            <input type="number" step="0.01" name="igst" id="edit_igst">
                        </div>
                    </div>

                    <div class="mfield">
                        <label>Description</label>
                        <textarea name="product_description" id="edit_product_description" rows="2"></textarea>
                    </div>

                    <div style="border-top:1px solid var(--border);margin:14px 0 14px"></div>

                    <div style="font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:10px"><i class="fas fa-calculator me-1"></i>Pricing</div>

                    <div class="price-row" style="margin-bottom:10px">
                        <div class="mfield" style="margin:0">
                            <label>Purchase Price (Incl. GST) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" name="purchase_price" id="edit_purchase_price" required>
                        </div>
                        <div class="mfield" style="margin:0">
                            <label>Sell Price (Incl. GST) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" name="sell_price" id="edit_sell_price" required>
                        </div>
                    </div>
                    
                    <div style="border-top:1px solid var(--border);margin:14px 0 14px"></div>
                    <div style="font-size:.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:10px"><i class="fas fa-boxes me-1"></i>Inventory</div>
                    
                    <div class="price-row" style="margin-bottom:10px">
                        <div class="mfield" style="margin:0">
                            <label>Stock Quantity <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="1" min="0" name="quantity" id="edit_quantity" required>
                            <div class="calc-note"><i class="fas fa-info-circle me-1"></i>Current stock in hand</div>
                        </div>
                        <div class="mfield" style="margin:0">
                            <label>&nbsp;</label>
                            <div>&nbsp;</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-primary-c" style="padding:8px 18px;font-size:.82rem">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-hdr" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
                <div class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" style="padding:26px 20px">
                <div class="del-confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h6 style="font-weight:700;margin-bottom:6px">Delete this product?</h6>
                <div style="font-size:.85rem;color:var(--danger);font-weight:700;margin-bottom:6px" id="deleteProductName"></div>
                <p style="color:var(--text-muted);font-size:.82rem">This cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center" style="gap:10px">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDeleteBtn" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;padding:8px 20px;border-radius:8px;font-weight:700;cursor:pointer;font-size:.85rem"><i class="fas fa-trash me-1"></i>Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function () {
    const rows = $('#productsTable tbody tr');

    function applyFilters() {
        const q = ($('#searchInput').val() || '').toLowerCase().trim();
        const cat = $('#categoryFilter').val();
        let vis = 0;

        rows.each(function () {
            // Ignore empty-row
            if ($(this).hasClass('empty-row')) return;

            const d = ($(this).data('search') || '').toString().toLowerCase();
            const itemCat = ($(this).data('category') !== undefined && $(this).data('category') !== null) ? $(this).data('category').toString() : '';

            const matchSearch = !q || d.includes(q);
            const matchCat = !cat || itemCat === cat.toString();

            const show = matchSearch && matchCat;
            $(this).toggle(show);
            if (show) vis++;
        });

        $('#visibleCount').text(vis);
        $('#clearSearch').toggleClass('show', q.length > 0);
    }

    $('#searchInput').on('input', applyFilters);
    $('#categoryFilter').on('change', applyFilters);

    $('#clearSearch').on('click', function () {
        $('#searchInput').val('');
        applyFilters();
        this.classList.remove('show');
    });

    let delId = null;
    $(document).on('click', '.act-btn.del', function () {
        delId = $(this).data('id');
        $('#deleteProductName').text($(this).data('name'));
        new bootstrap.Modal('#deleteModal').show();
    });

    $('#confirmDeleteBtn').on('click', function () {
        $.post('ajax_fetch_master_product.php', {
            delete_id: delId,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        }, function (r) {
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                $(`tr[data-id="${delId}"]`).fadeOut(350, function () { $(this).remove(); });
                showToast('Product deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(r.error || 'Delete failed', 'danger');
            }
        }, 'json');
    });

    function showToast(msg, type) {
        const t = $(`<div class="toast-msg ${type}"><i class="fas fa-${type==='success'?'check-circle':'exclamation-circle'}"></i>${msg}</div>`);
        $('body').append(t);
        setTimeout(() => { t.css({ opacity: 0, transition: 'opacity .4s' }); setTimeout(() => t.remove(), 400); }, 3000);
    }

    const st = document.getElementById('successToast');
    if (st) setTimeout(() => { st.style.opacity='0'; st.style.transition='opacity .5s'; setTimeout(()=>st.remove(),500); }, 3500);

    // Intercept Edit Product Form submit via AJAX
    $('#editProductForm').on('submit', function (e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.post('ajax_fetch_master_product.php', formData, function (response) {
            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                showToast('Product updated successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.error || 'Update failed', 'danger');
            }
        }, 'json').fail(function (xhr, status, error) {
            showToast('Error updating product: ' + error, 'danger');
        });
    });
});


function openEditModal(productId) {
    console.log("Opening edit for product ID:", productId);
    
    $.post('ajax_fetch_master_product.php', {
        view_id: productId
    }, function (data) {
        console.log("Response received:", data);
        
        if (data.error) {
            alert(data.error);
            return;
        }
        
        $('#edit_id').val(data.id);
        $('#edit_category_id').val(data.category_id || '');
        $('#edit_product_name').val(data.product_name);
        $('#edit_product_code').val(data.product_code);

        $('#edit_product_design_code').val(data.product_design_code);
        $('#edit_hsn_code').val(data.hsn_code || '');
        $('#edit_product_description').val(data.product_description || '');
        $('#edit_unit').val(data.unit || 'Piece');
        $('#edit_quantity').val(data.quantity);
        $('#edit_gst_percentage').val(parseFloat(data.gst_percentage || 0).toFixed(2));
        $('#edit_sgst').val(parseFloat(data.sgst || 0).toFixed(2));
        $('#edit_cgst').val(parseFloat(data.cgst || 0).toFixed(2));
        $('#edit_igst').val(parseFloat(data.igst || 0).toFixed(2));
        $('#edit_purchase_price').val(parseFloat(data.purchase_price || 0).toFixed(2));
        $('#edit_sell_price').val(parseFloat(data.sell_price || 0).toFixed(2));
        
        $('#editModal').modal('show');
    }, 'json').fail(function(xhr, status, error) {
        console.log("AJAX Error:", error);
        console.log("Response Text:", xhr.responseText);
        alert('Failed to load product data. Check console for details.');
    });
}

function updateEditGST() {
    let gst = parseFloat($('#edit_gst_percentage').val()) || 0;
    let igst = parseFloat($('#edit_igst').val()) || 0;
    
    if (igst > 0) {
        $('#edit_sgst').val('0.00');
        $('#edit_cgst').val('0.00');
    } else {
        let half = (gst / 2).toFixed(2);
        $('#edit_sgst').val(half);
        $('#edit_cgst').val(half);
    }
}

$('#edit_igst').on('change', function() {
    let igst = parseFloat($(this).val()) || 0;
    if (igst > 0) {
        $('#edit_gst_percentage').val(igst.toFixed(2));
        $('#edit_sgst').val('0.00');
        $('#edit_cgst').val('0.00');
    } else {
        let gst = parseFloat($('#edit_gst_percentage').val()) || 0;
        let half = (gst / 2).toFixed(2);
        $('#edit_sgst').val(half);
        $('#edit_cgst').val(half);
    }
});
</script>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none; border-radius:var(--radius-md); box-shadow:var(--shadow-lg);">
            <div class="modal-header" style="background:var(--brand); color:#fff;">
                <div class="page-title">
                    <h5 style="margin:0; font-weight:700;"><i class="fas fa-upload me-2"></i>Import Products</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="import_products.php" method="post" enctype="multipart/form-data">
                <div class="modal-body" style="padding:20px;">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600;">Upload CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <div class="mb-2">
                        <a href="import_products.php?download_sample=1" class="text-primary" style="text-decoration:none; font-size:0.85rem; font-weight:500;"><i class="fas fa-download me-1"></i> Download Sample CSV Format</a>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:var(--brand); border:none;">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
<?php ob_end_flush(); ?>