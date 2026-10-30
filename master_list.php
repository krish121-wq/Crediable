<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

session_start();
include('layout.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$show_success = isset($_SESSION['product_saved']) && $_SESSION['product_saved'] === true;
if ($show_success) {
    unset($_SESSION['product_saved']);
}

// Fetch categories for dropdown
$user_id = $_SESSION['user_id'];
$categories_list = [];
$cat_result = $conn->query("SELECT id, category_name FROM categories WHERE created_by = $user_id AND status = 1 ORDER BY category_name ASC");
if ($cat_result) {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $categories_list[] = $cat_row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product – CREDIBLE</title>
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
        --danger: #dc2626;
        --bg: #f4f8fb;
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

    body {
        font-family: 'Inter', sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 13.5px;
    }

    .page-wrap {
        margin-top: 60px;
        margin-left: 260px;
        padding: 22px 24px 48px;
        transition: var(--t);
    }
    @media (max-width: 992px) { .page-wrap { margin-left: 0; padding: 16px 14px 40px; } }

    .page-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 20px 26px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px; margin-bottom: 20px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
    }
    .page-hero::before {
        content: ''; position: absolute;
        right: -40px; top: -40px;
        width: 160px; height: 160px;
        border-radius: 50%; background: rgba(255,255,255,.06);
    }
    .hero-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.25rem; font-weight: 800; color: #fff;
    }
    .hero-sub { color: rgba(255,255,255,.72); font-size: .8rem; margin-top: 2px; }
    .hero-badge {
        background: rgba(255,255,255,.15);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 50px; padding: 6px 14px;
        color: #fff; font-size: .75rem; font-weight: 600;
        display: flex; align-items: center; gap: 6px; white-space: nowrap;
    }

    .section-card {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        margin-bottom: 16px;
        overflow: hidden;
        transition: var(--t);
    }
    .section-card:hover { box-shadow: var(--shadow-md); }

    .sec-head {
        display: flex; align-items: center; gap: 10px;
        padding: 13px 18px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
    }
    .sec-icon {
        width: 34px; height: 34px; border-radius: 9px;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .85rem; flex-shrink: 0;
        box-shadow: 0 3px 10px rgba(2,132,199,.22);
    }
    .sec-head h2 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: .88rem; font-weight: 700; color: var(--brand);
    }
    .sec-head p { font-size: .72rem; color: var(--text-muted); margin-top: 1px; }
    .sec-body { padding: 16px 18px; }

    .fg { display: grid; gap: 14px; }
    .fg.c3 { grid-template-columns: repeat(3,1fr); }
    .fg.c2 { grid-template-columns: repeat(2,1fr); }
    .fg.c4 { grid-template-columns: repeat(4,1fr); }
    .fg.c1 { grid-template-columns: 1fr; }
    @media (max-width: 860px) { .fg.c3, .fg.c4 { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 560px) { .fg.c3, .fg.c2, .fg.c4 { grid-template-columns: 1fr; } }

    .fg + .fg { margin-top: 14px; }

    .field { display: flex; flex-direction: column; gap: 4px; }
    .field label {
        font-size: .72rem; font-weight: 700;
        color: var(--text-mid); text-transform: uppercase;
        letter-spacing: .35px;
        display: flex; align-items: center; gap: 4px;
    }
    .req { color: var(--danger); }

    .field input,
    .field select,
    .field textarea {
        padding: 9px 11px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: .88rem; font-family: 'Inter', sans-serif;
        color: var(--text); background: #fafafa;
        outline: none; transition: var(--t);
        width: 100%;
    }
    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        border-color: var(--brand);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    }
    .field input.is-invalid { border-color: var(--danger); }
    .field textarea { resize: vertical; min-height: 72px; }
    .field select {
        appearance: none; cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239590a8'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 10px center;
        background-size: 16px; padding-right: 32px;
    }

    .field input[readonly] {
        background: #eef2f6 !important;
        color: var(--text-mid);
        cursor: not-allowed;
        border-color: #dde3ea;
    }
    .calc-label { font-size: .67rem; color: var(--brand); font-weight: 600; margin-top: 2px; }

    .gst-summary {
        background: linear-gradient(135deg, var(--brand-light), #fff);
        border: 1px solid rgba(2,132,199,.18);
        border-radius: var(--radius-sm);
        padding: 12px 14px;
        margin-top: 12px;
    }
    .gst-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px dashed rgba(2,132,199,.14);
    }
    .gst-row:last-child { border-bottom: none; }
    .gst-label { font-size: .72rem; font-weight: 600; color: var(--text-mid); }
    .gst-value { font-size: .8rem; font-weight: 700; }
    .gst-value.sgst { color: #1d4ed8; }
    .gst-value.cgst { color: #0b1220; }
    .gst-value.igst { color: #0369a1; }

    .price-summary {
        background: linear-gradient(135deg, var(--brand-light), #fff);
        border: 1px solid rgba(2,132,199,.18);
        border-radius: var(--radius-sm);
        padding: 12px 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .ps-item { display: flex; flex-direction: column; gap: 3px; }
    .ps-label { font-size: .68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .3px; }
    .ps-val {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.05rem; font-weight: 800; color: var(--brand);
    }
    .ps-val.sell { color: var(--success); }

    .gst-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: var(--brand-light); color: var(--brand);
        border-radius: 50px; padding: 3px 10px;
        font-size: .72rem; font-weight: 700;
        border: 1px solid rgba(2,132,199,.18);
    }

    .alert-custom {
        border-radius: var(--radius-sm);
        padding: 11px 14px;
        margin-bottom: 16px;
        display: flex; align-items: center; gap: 10px;
        font-size: .85rem; font-weight: 500;
        animation: slideIn .3s ease;
    }
    @keyframes slideIn { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
    .alert-danger-c  { background: #fef2f2; border: 1px solid #fca5a5; color: var(--danger); }
    .alert-success-c { background: #f0fdf4; border: 1px solid #86efac; color: var(--success); }

    .action-bar {
        position: sticky; bottom: 18px; z-index: 80;
        display: flex; justify-content: center; gap: 12px;
        padding: 13px 20px;
        background: rgba(255,255,255,.88);
        backdrop-filter: blur(12px);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .btn-primary-c {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff !important; border: none;
        padding: 10px 24px; border-radius: 50px;
        font-size: .85rem; font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        display: inline-flex; align-items: center; gap: 7px;
        cursor: pointer; transition: var(--t);
        box-shadow: 0 5px 16px rgba(2,132,199,.30);
        text-decoration: none;
    }
    .btn-primary-c:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(2,132,199,.40); }
    .btn-primary-c:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-ghost-c {
        background: transparent; color: var(--text-mid);
        border: 1.5px solid var(--border);
        padding: 9px 18px; border-radius: 50px;
        font-size: .82rem; font-weight: 600;
        display: inline-flex; align-items: center; gap: 6px;
        cursor: pointer; transition: var(--t); text-decoration: none;
    }
    .btn-ghost-c:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }

    .modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); }
    .modal-hdr {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff; border-radius: var(--radius-md) var(--radius-md) 0 0;
        padding: 16px 20px;
    }
    .modal-footer { border-top: 1px solid var(--border); padding: 13px 20px; }

    .htile {
        background: var(--bg); border-radius: var(--radius-sm);
        padding: 10px 12px; margin-bottom: 8px;
        display: flex; gap: 10px; align-items: flex-start;
        font-size: .81rem; color: var(--text-mid); line-height: 1.5;
    }
    .htile i { color: var(--brand); margin-top: 2px; font-size: .85rem; flex-shrink: 0; }

    .tax-type-toggle {
        display: flex; gap: 10px; margin-bottom: 12px;
    }
    .tax-type-btn {
        flex: 1; text-align: center;
        padding: 8px 12px; border-radius: 50px;
        background: var(--bg); border: 1.5px solid var(--border);
        cursor: pointer; font-size: .75rem; font-weight: 600;
        transition: var(--t);
    }
    .tax-type-btn i { margin-right: 6px; }
    .tax-type-btn.active {
        background: var(--brand); color: #fff; border-color: var(--brand);
    }
    .tax-type-btn.active i { color: #fff; }

    /* Loading Spinner */
    .spinner-small {
        width: 16px;
        height: 16px;
        border: 2px solid #fff;
        border-top: 2px solid transparent;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        display: inline-block;
        margin-right: 6px;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @media (max-width: 480px) {
        .page-hero { flex-direction: column; align-items: flex-start; }
        .sec-body { padding: 12px 14px; }
    }
    </style>
</head>
<body>

<div class="page-wrap">

    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-box-open me-2" style="opacity:.8"></i>Add Product</div>
            <div class="hero-sub">Fill in the details to add a new product to your catalog</div>
        </div>
        <div class="hero-badge"><i class="fas fa-plus-circle"></i> New Product</div>
    </div>

    <div id="errorAlert" class="alert-custom alert-danger-c" style="display:none">
        <i class="fas fa-exclamation-circle"></i><span id="errorMsg">Please fix the errors below.</span>
    </div>

    <form method="POST" action="save-product.php" id="productForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- SECTION 1: IDENTITY -->
        <div class="section-card">
            <div class="sec-head">
                <div class="sec-icon"><i class="fas fa-tag"></i></div>
                <div>
                    <h2>Product Identity</h2>
                    <p>Name, code and description</p>
                </div>
            </div>
            <div class="sec-body">
                <div class="fg c2" style="margin-bottom:14px">
                    <div class="field">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="mb-0"><i class="fas fa-layer-group"></i> Category <span class="req">*</span></label>
                            <a href="categories-list.php" target="_blank" style="font-size: 0.72rem; color: var(--brand); font-weight: 700; text-decoration: none;">
                                <i class="fas fa-plus-circle me-1"></i>Add Category
                            </a>
                        </div>
                        <select name="category_id" id="categoryId" required>
                            <option value="" disabled selected>Select Category</option>
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($categories_list)): ?>
                            <span class="calc-label" style="color:#dc2626"><i class="fas fa-exclamation-triangle me-1"></i>No categories found. <a href="categories-list.php" target="_blank">Add Category first</a></span>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-cube"></i> Product Name <span class="req">*</span></label>
                        <input type="text" name="product_name" id="productName" placeholder="e.g. Blue Bristle Brush Set" required>
                    </div>
                </div>
                <div class="fg c2">
                    <div class="field">
                        <label><i class="fas fa-barcode"></i> Product Code <span class="req">*</span></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="product_code" id="productCode" placeholder="e.g. PRD-001" required style="flex: 1; min-width: 0;">
                            <button type="button" id="assignCodeBtn" class="btn-ghost-c" style="padding: 0 14px; border-radius: var(--radius-sm); white-space: nowrap;"><i class="fas fa-magic"></i> Assign Code</button>
                        </div>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-hashtag"></i> Design Number <span class="req">*</span></label>
                        <input type="text" name="product_design_code" id="productDesignCode" placeholder="e.g. DES-2024-A" required>
                    </div>
                </div>

                <div class="fg c1" style="margin-top:14px">
                    <div class="field">
                        <label><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="product_description" id="productDescription" placeholder="Optional — brief product description"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: CLASSIFICATION & TAX -->
        <div class="section-card">
            <div class="sec-head">
                <div class="sec-icon"><i class="fas fa-sliders"></i></div>
                <div>
                    <h2>Classification & Tax</h2>
                    <p>Unit, HSN code and GST rates</p>
                </div>
            </div>
            <div class="sec-body">
                <div class="fg c3">
                    <div class="field">
                        <label><i class="fas fa-ruler"></i> Unit <span class="req">*</span></label>
                        <select name="unit" id="unit" required>
                            <option value="" disabled selected>Select Unit</option>
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
                    <div class="field">
                        <label><i class="fas fa-code"></i> HSN Code</label>
                        <input type="text" name="hsn_code" id="hsnCode" placeholder="e.g. 3215">
                        <span class="calc-label" id="hsnHint" style="display:none"><i class="fas fa-magic me-1"></i>GST rate auto-detected</span>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-percent"></i> Tax Rate</label>
                        <select id="taxRateDropdown" onchange="handleTaxRateDropdown()">
                            <option value="None">None</option>
                            <option value="IGST@0%">IGST@0%</option>
                            <option value="GST@0%">GST@0%</option>
                            <option value="IGST@0.25%">IGST@0.25%</option>
                            <option value="GST@0.25%">GST@0.25%</option>
                            <option value="IGST@3%">IGST@3%</option>
                            <option value="GST@3%">GST@3%</option>
                            <option value="IGST@5%">IGST@5%</option>
                            <option value="GST@5%">GST@5%</option>
                            <option value="IGST@12%">IGST@12%</option>
                            <option value="GST@12%">GST@12%</option>
                            <option value="IGST@18%">IGST@18%</option>
                            <option value="GST@18%">GST@18%</option>
                            <option value="IGST@28%">IGST@28%</option>
                            <option value="GST@28%">GST@28%</option>
                            <option value="Exempt">Exempt</option>
                        </select>
                        <input type="hidden" name="gst_percentage" id="gstPercentage" value="0.00">
                    </div>
                </div>

                <!-- Tax Type Toggle -->
                <div style="margin: 12px 0 8px;">
                    <div class="tax-type-toggle">
                        <div class="tax-type-btn active" onclick="setTaxType('intra')">
                            <i class="fas fa-building"></i> Intra-State (CGST + SGST)
                        </div>
                        <div class="tax-type-btn" onclick="setTaxType('inter')">
                            <i class="fas fa-truck"></i> Inter-State (IGST)
                        </div>
                    </div>
                </div>

                <!-- GST Breakup Fields -->
                <div class="fg c3" id="gstBreakupFields">
                    <div class="field" id="sgstField">
                        <label><i class="fas fa-building"></i> SGST (%)</label>
                        <input type="number" step="0.01" name="sgst" id="sgstRate" value="0.00" placeholder="0.00" readonly style="background:#eef2f6">
                    </div>
                    <div class="field" id="cgstField">
                        <label><i class="fas fa-building"></i> CGST (%)</label>
                        <input type="number" step="0.01" name="cgst" id="cgstRate" value="0.00" placeholder="0.00" readonly style="background:#eef2f6">
                    </div>
                    <div class="field" id="igstField">
                        <label><i class="fas fa-truck"></i> IGST (%)</label>
                        <input type="number" step="0.01" name="igst" id="igstRate" value="0.00" placeholder="0.00" readonly style="background:#eef2f6">
                    </div>
                </div>

                <!-- GST Summary Display -->
                <div class="gst-summary" id="gstSummary" style="display:none">
                    <div class="gst-row">
                        <span class="gst-label">Total GST:</span>
                        <span class="gst-value" id="summaryTotalGST">0%</span>
                    </div>
                    <div class="gst-row" id="summarySGSTRow" style="display:none">
                        <span class="gst-label">SGST:</span>
                        <span class="gst-value sgst" id="summarySGST">0%</span>
                    </div>
                    <div class="gst-row" id="summaryCGSTRow" style="display:none">
                        <span class="gst-label">CGST:</span>
                        <span class="gst-value cgst" id="summaryCGST">0%</span>
                    </div>
                    <div class="gst-row" id="summaryIGSTRow" style="display:none">
                        <span class="gst-label">IGST:</span>
                        <span class="gst-value igst" id="summaryIGST">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PRICING -->
        <div class="section-card">
            <div class="sec-head">
                <div class="sec-icon" style="background:linear-gradient(135deg,#16a34a,#15803d)"><i class="fas fa-indian-rupee-sign"></i></div>
                <div>
                    <h2>Pricing</h2>
                    <p>Enter exclusive prices — inclusive calculated automatically</p>
                </div>
                <span id="gstBadgeDisplay" style="margin-left:auto"></span>
            </div>
            <div class="sec-body">
                <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:10px">
                    <i class="fas fa-keyboard me-1"></i>Enter Exclusive Prices (Without GST)
                </div>
                <div class="fg c2">
                    <div class="field">
                        <label><i class="fas fa-cart-shopping"></i> Purchase Price (Excl. GST)</label>
                        <input type="number" step="0.01" name="purchase_price_excl" id="purchasePriceExcl" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label><i class="fas fa-tag"></i> Sell Price (Excl. GST)</label>
                        <input type="number" step="0.01" name="sell_price_excl" id="sellPriceExcl" placeholder="0.00">
                    </div>
                </div>

                <div style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin:14px 0 10px">
                    <i class="fas fa-calculator me-1"></i>Auto-Calculated Inclusive Prices (With GST)
                </div>
                <div class="fg c2" style="margin-bottom:16px">
                    <div class="field">
                        <label><i class="fas fa-cart-shopping"></i> Purchase Price (Incl. GST) <span class="req">*</span></label>
                        <input type="number" step="0.01" name="purchase_price" id="purchasePrice" readonly required placeholder="Auto">
                        <span class="calc-label"><i class="fas fa-lock me-1"></i>Auto-calculated</span>
                    </div>
                    <div class="field">
                        <label><i class="fas fa-tag"></i> Sell Price (Incl. GST) <span class="req">*</span></label>
                        <input type="number" step="0.01" name="sell_price" id="sellPrice" readonly required placeholder="Auto">
                        <span class="calc-label"><i class="fas fa-lock me-1"></i>Auto-calculated</span>
                    </div>
                </div>

                <div class="price-summary" id="priceSummary" style="display:none">
                    <div class="ps-item">
                        <div class="ps-label"><i class="fas fa-cart-shopping me-1"></i>Purchase</div>
                        <div class="ps-val" id="ps_pur">₹0.00</div>
                        <div class="ps-sub">Incl. GST</div>
                    </div>
                    <div class="ps-item">
                        <div class="ps-label"><i class="fas fa-tag me-1"></i>Selling</div>
                        <div class="ps-val sell" id="ps_sell">₹0.00</div>
                        <div class="ps-sub">Incl. GST</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <button type="button" class="btn-ghost-c" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-circle-question"></i> Help
            </button>
            <button type="button" class="btn-ghost-c" onclick="resetProductForm()">
                <i class="fas fa-rotate-left"></i> Reset
            </button>
            <button type="submit" class="btn-primary-c" id="saveButton">
                <i class="fas fa-check-circle"></i> Save Product
            </button>
        </div>

    </form>
</div>

<!-- SUCCESS MODAL -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-hdr" style="background:linear-gradient(135deg,var(--success),#15803d)">
                <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0">
                    <i class="fas fa-check-circle me-2"></i>Product Saved!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div style="width:60px;height:60px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.8rem;color:var(--success)">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h5 style="font-weight:700;margin-bottom:6px">Product saved successfully!</h5>
                <p style="color:var(--text-muted);font-size:.85rem">Your product has been added to the catalog.</p>
            </div>
            <div class="modal-footer justify-content-center" style="gap:10px">
                <button type="button" class="btn-ghost-c" id="successAddAnotherBtn">
                    <i class="fas fa-plus-circle"></i> Add Another Product
                </button>
                <button type="button" class="btn-primary-c" id="successViewListBtn">
                    <i class="fas fa-list"></i> View Product List
                </button>
            </div>
        </div>
    </div>
</div>

<!-- HELP MODAL -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-hdr">
                <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0">
                    <i class="fas fa-circle-question me-2"></i>How to Add Products
                </h5>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:16px 20px">
                <div class="htile"><i class="fas fa-star-of-life"></i><div><strong>Required:</strong> Product Name, Code, Design Number, Unit, and both prices are mandatory.</div></div>
                <div class="htile"><i class="fas fa-barcode"></i><div><strong>HSN Code</strong> — Entering it auto-detects the GST rate for common categories.</div></div>
                <div class="htile"><i class="fas fa-calculator"></i><div><strong>Pricing</strong> — Enter exclusive prices. Inclusive (with GST) prices are auto-calculated.</div></div>
                <div class="htile"><i class="fas fa-exchange-alt"></i><div><strong>Tax Type</strong> — Select Intra-State (CGST+SGST) for local sales or Inter-State (IGST) for other states.</div></div>
                <div class="htile"><i class="fas fa-fingerprint"></i><div><strong>Uniqueness</strong> — Product Name, Code, and Design Number must be unique across all products.</div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary-c" data-bs-dismiss="modal" style="padding:8px 18px;font-size:.82rem">Got it</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentTaxType = 'intra';
let isSubmitting = false;

// Function to update GST breakup based on total GST and tax type
function updateGSTBreakup() {
    const totalGST = parseFloat($('#gstPercentage').val()) || 0;
    
    if (currentTaxType === 'intra') {
        const half = totalGST / 2;
        $('#sgstRate').val(half.toFixed(2));
        $('#cgstRate').val(half.toFixed(2));
        $('#igstRate').val('0.00');
    } else {
        $('#sgstRate').val('0.00');
        $('#cgstRate').val('0.00');
        $('#igstRate').val(totalGST.toFixed(2));
    }
    
    updateGSTSummary();
    calcIncPrices();
}

function setTaxType(type) {
    currentTaxType = type;
    
    $('.tax-type-btn').removeClass('active');
    $(`.tax-type-btn`).each(function() {
        if ((type === 'intra' && $(this).text().includes('Intra')) ||
            (type === 'inter' && $(this).text().includes('Inter'))) {
            $(this).addClass('active');
        }
    });
    
    updateGSTBreakup();
}

function updateGSTSummary() {
    const totalGST = parseFloat($('#gstPercentage').val()) || 0;
    const sgst = parseFloat($('#sgstRate').val()) || 0;
    const cgst = parseFloat($('#cgstRate').val()) || 0;
    const igst = parseFloat($('#igstRate').val()) || 0;
    
    $('#summaryTotalGST').text(totalGST.toFixed(2) + '%');
    
    if (sgst > 0 || cgst > 0) {
        $('#summarySGSTRow').show();
        $('#summaryCGSTRow').show();
        $('#summaryIGSTRow').hide();
        $('#summarySGST').text(sgst.toFixed(2) + '%');
        $('#summaryCGST').text(cgst.toFixed(2) + '%');
    } else if (igst > 0) {
        $('#summarySGSTRow').hide();
        $('#summaryCGSTRow').hide();
        $('#summaryIGSTRow').show();
        $('#summaryIGST').text(igst.toFixed(2) + '%');
    } else {
        $('#summarySGSTRow').hide();
        $('#summaryCGSTRow').hide();
        $('#summaryIGSTRow').hide();
    }
    
    if (totalGST > 0) {
        $('#gstSummary').show();
    } else {
        $('#gstSummary').hide();
    }
}

function calcIncPrices() {
    const gst = parseFloat($('#gstPercentage').val()) || 0;
    const purExcl = parseFloat($('#purchasePriceExcl').val());
    const sellExcl = parseFloat($('#sellPriceExcl').val());

    const purInc = isNaN(purExcl) ? '' : (purExcl + purExcl * (gst / 100)).toFixed(2);
    const sellInc = isNaN(sellExcl) ? '' : (sellExcl + sellExcl * (gst / 100)).toFixed(2);

    $('#purchasePrice').val(purInc);
    $('#sellPrice').val(sellInc);

    if (purInc || sellInc) {
        $('#ps_pur').text('₹' + (purInc || '0.00'));
        $('#ps_sell').text('₹' + (sellInc || '0.00'));
        $('#priceSummary').show();
    } else {
        $('#priceSummary').hide();
    }

    if (gst > 0) {
        $('#gstBadgeDisplay').html('<span class="gst-badge"><i class="fas fa-percent"></i> GST ' + gst + '%</span>');
    } else {
        $('#gstBadgeDisplay').html('');
    }
}

// Event listeners
$('#gstPercentage').on('input', function() {
    this.value = (parseFloat(this.value) || 0).toFixed(2);
    updateGSTBreakup();
});

$('#purchasePriceExcl, #sellPriceExcl').on('input change', calcIncPrices);

// HSN Code auto-detection
const hsnRates = {
    '01':0,'02':0,'03':0,'04':0,'05':0,'06':0,'07':0,'08':0,'09':0,'10':0,
    '11':0,'12':0,'13':0,'14':0,'15':0,'16':0,'17':0,'18':0,'19':0,'20':0,
    '21':0,'22':0,'23':0,'24':0,'25':0,'26':0,'27':0,'28':0,'29':0,'30':0,
    '31':5,'32':18,'33':18,'34':18,'35':18,'36':12,'37':12,'38':18,'39':18,
    '40':5,'41':5,'42':12,'43':12,'44':12,'45':12,'46':12,'47':12,'48':12,
    '49':12,'50':5,'51':5,'52':5,'53':5,'54':5,'55':5,'56':12,'57':12,
    '58':12,'59':12,'60':12,'61':12,'62':12,'63':12,'64':18,'65':12,'66':18,
    '67':18,'68':28,'69':12,'70':28,'71':3,'72':18,'73':18,'74':18,'75':18,
    '76':18,'78':18,'79':18,'80':18,'81':18,'82':18,'83':18,'84':28,'85':28,
    '86':28,'87':28,'88':18,'89':5,'90':18,'91':18,'92':18,'93':28,'94':28,
    '95':28,'96':18,'97':12,'98':0,'99':18
};

$('#hsnCode').on('change', function () {
    const hsn = $(this).val().trim();
    if (hsn.length >= 2) {
        const prefix = hsn.substring(0, 2);
        const rate = hsnRates[prefix] !== undefined ? hsnRates[prefix] : 0;
        $('#gstPercentage').val(rate.toFixed(2));
        
        let typePrefix = currentTaxType === 'inter' ? 'IGST@' : 'GST@';
        let optionVal = typePrefix + rate + (Number.isInteger(rate) ? '' : '') + '%';
        if (rate === 0) optionVal = 'None';
        
        if ($(`#taxRateDropdown option[value="${optionVal}"]`).length) {
            $('#taxRateDropdown').val(optionVal);
        } else {
            $('#taxRateDropdown').val('None');
        }
        
        $('#hsnHint').show();
        updateGSTBreakup();
    } else {
        $('#hsnHint').hide();
    }
});

// Uniqueness check
function checkUnique(field, value) {
    return new Promise(resolve => {
        $.ajax({
            url: 'check-product-uniqueness.php', type: 'POST',
            data: { field: field, value: value, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' }, 
            dataType: 'json',
            success: function(r) { resolve(r.exists); },
            error: function() { resolve(true); }
        });
    });
}

async function validateAndSubmit() {
    // Prevent multiple submissions
    if (isSubmitting) {
        return false;
    }
    
    const errors = [];
    
    if (!$('#unit').val()) errors.push('Unit is required.');
    if (!$('#purchasePriceExcl').val() || !$('#sellPriceExcl').val())
        errors.push('Please enter both exclusive prices.');
    
    const totalGST = parseFloat($('#gstPercentage').val()) || 0;
    const sgst = parseFloat($('#sgstRate').val()) || 0;
    const cgst = parseFloat($('#cgstRate').val()) || 0;
    const igst = parseFloat($('#igstRate').val()) || 0;
    
    if (totalGST > 0) {
        if (currentTaxType === 'intra') {
            if (Math.abs((sgst + cgst) - totalGST) > 0.01) {
                errors.push('SGST + CGST should equal Total GST (' + totalGST + '%)');
            }
        } else {
            if (Math.abs(igst - totalGST) > 0.01) {
                errors.push('IGST should equal Total GST (' + totalGST + '%)');
            }
        }
    }

    const [nEx, cEx, dEx] = await Promise.all([
        checkUnique('product_name', $('#productName').val()),
        checkUnique('product_code', $('#productCode').val()),
        checkUnique('product_design_code', $('#productDesignCode').val())
    ]);
    
    if (nEx) errors.push('Product Name already exists.');
    if (cEx) errors.push('Product Code already exists.');
    if (dEx) errors.push('Product Design Code already exists.');

    if (errors.length) {
        $('#errorMsg').text(errors.join(' '));
        $('#errorAlert').show();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return false;
    }
    
    $('#errorAlert').hide();
    
    // Show loading state
    isSubmitting = true;
    const $btn = $('#saveButton');
    const originalText = $btn.html();
    $btn.html('<span class="spinner-small"></span> Saving...').prop('disabled', true);
    
    // Submit the form
    $('#productForm')[0].submit();
    return true;
}

// Success modal handlers
$(document).ready(function() {
    <?php if ($show_success): ?>
    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
    <?php endif; ?>
    
    // Add Another Product button
    $('#successAddAnotherBtn').on('click', function() {
        resetProductForm();
        bootstrap.Modal.getInstance(document.getElementById('successModal')).hide();
        $('#productName').focus();
    });
    
    // View Product List button
    $('#successViewListBtn').on('click', function() {
        window.location.href = 'fetch_master_list.php';
    });
});

$('#productForm').on('submit', function (e) { 
    e.preventDefault(); 
    validateAndSubmit(); 
});

// Enter key navigation
const fields = [
    '#productName','#productCode','#productDesignCode','#productDescription',
    '#unit','#hsnCode','#gstPercentage','#purchasePriceExcl','#sellPriceExcl','#saveButton'
];

$('#productForm').on('keydown', 'input,textarea,select', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const idx = fields.indexOf('#' + this.id);
    if (idx >= 0 && idx < fields.length - 1) { 
        $(fields[idx + 1]).focus(); 
    } else { 
        validateAndSubmit(); 
    }
});

// Unsaved changes warning
let changed = false;
$('#productForm').on('change keyup', 'input,textarea,select', function() { 
    changed = true; 
});
$('#productForm').on('submit', function() { 
    changed = false; 
});
window.onbeforeunload = function() { 
    return changed ? true : undefined; 
};

// Confirm leave modal
if (!$('#confirmLeaveModal').length) {
    $('body').append(`
    <div class="modal fade" id="confirmLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-hdr" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
                    <h5 class="modal-title" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Unsaved Changes
                    </h5>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding:20px;font-size:.88rem;color:var(--text-mid)">
                    You have unsaved changes. Are you sure you want to leave?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-ghost-c" data-bs-dismiss="modal">Stay</button>
                    <button type="button" id="confirmLeave" class="btn-primary-c" style="background:linear-gradient(135deg,#ef4444,#b91c1c);border:none">Leave</button>
                </div>
            </div>
        </div>
    </div>
    `);
}

window.addEventListener('popstate', function() {
    if (changed) {
        history.pushState(null, null, location.href);
        new bootstrap.Modal('#confirmLeaveModal').show();
    }
});
history.pushState(null, null, location.href);

$('#confirmLeave').on('click', function() { 
    changed = false; 
    history.back(); 
});

function resetProductForm() {
    document.getElementById('productForm').reset();
    $('#gstPercentage').val('0.00');
    $('#taxRateDropdown').val('None');
    $('#purchasePrice').val('');
    $('#sellPrice').val('');
    $('#priceSummary').hide();
    $('#gstBadgeDisplay').html('');
    $('#hsnHint').hide();
    $('#errorAlert').hide();
    $('#sgstRate').val('0.00');
    $('#cgstRate').val('0.00');
    $('#igstRate').val('0.00');
    $('#gstSummary').hide();
    currentTaxType = 'intra';
    $('.tax-type-btn').removeClass('active');
    $('.tax-type-btn:first').addClass('active');
    isSubmitting = false;
    $('#saveButton').html('<i class="fas fa-check-circle"></i> Save Product').prop('disabled', false);
}

// Assign Code Logic
$('#assignCodeBtn').on('click', async function() {
    const btn = $(this);
    const originalText = btn.html();
    btn.html('<span class="spinner-small" style="width:14px;height:14px;border-width:1.5px;vertical-align:middle;margin-right:4px;"></span>Assigning...').prop('disabled', true);
    
    let isUnique = false;
    let code = '';
    
    try {
        while (!isUnique) {
            // Generate a 4-digit code (1000 - 9999)
            code = Math.floor(1000 + Math.random() * 9000).toString();
            // Check if it exists in the database
            isUnique = !(await checkUnique('product_code', code));
        }
        
        $('#productCode').val(code).removeClass('is-invalid');
        changed = true;
    } catch (e) {
        console.error("Error generating code:", e);
    } finally {
        btn.html(originalText).prop('disabled', false);
    }
});

// Initial calculation
updateGSTBreakup();

function handleTaxRateDropdown() {
    const val = $('#taxRateDropdown').val();
    let gst = 0;
    
    if (val === 'None' || val === 'Exempt') {
        gst = 0;
    } else {
        const parts = val.split('@');
        const type = parts[0]; 
        gst = parseFloat(parts[1].replace('%', ''));
        
        if (type === 'IGST') {
            setTaxType('inter');
        } else {
            setTaxType('intra');
        }
    }
    
    $('#gstPercentage').val(gst.toFixed(2));
    updateGSTBreakup();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>