<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['sidebar_open'])) { $_SESSION['sidebar_open'] = true; }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=purchase_entry.php");
    exit();
}

$entry_no = "BILL-" . date("Ymd") . "-" . rand(100, 999);
$success_message = isset($_GET['success']) && $_GET['success'] == '1' ? "Purchase entry saved successfully!" : '';

$products = [];
$result = $conn->query("SELECT id, product_name, hsn_code, purchase_price, gst_percentage FROM products ORDER BY product_name");
if ($result) { while ($row = $result->fetch_assoc()) { $products[] = $row; } }

$suppliers = [];
$result = $conn->query("SELECT id, name, phone, email, address, city, state, pincode, gstin FROM suppliers ORDER BY name");
if ($result) { while ($row = $result->fetch_assoc()) { $suppliers[] = $row; } }

include('layout.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Entry — CREDIBLE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}

.page-wrap{margin-top:70px;margin-left:260px;padding:28px 24px 60px;transition:var(--transition); min-height:calc(100vh - 70px);}
@media(max-width:992px){.page-wrap{margin-left:0;padding:18px 14px 50px}}

/* HERO */
.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06)}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:#fff; margin:0;}
.hero-sub{color:rgba(255,255,255,.75);font-size:.85rem;margin-top:4px}
.hero-badge{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:50px;padding:8px 18px;color:#fff;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:6px; backdrop-filter:blur(4px); z-index: 10;}

/* CARDS */
.pcard{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);margin-bottom:20px;overflow:hidden;box-shadow:var(--shadow-sm);transition:var(--transition)}
.pcard:hover{box-shadow:var(--shadow-md)}
.pcard-header{background:linear-gradient(90deg,var(--brand-light),#fff);padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.pcard-header h5{font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;font-weight:800;color:var(--brand);margin:0; text-transform: uppercase; letter-spacing: 0.5px;}
.pcard-body{padding:20px}
@media(max-width:768px){.pcard-body{padding:14px}}

/* FORM */
.f-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-mid);margin-bottom:5px;display:block}
.f-control,.f-select{border-radius:var(--radius-sm);border:1.5px solid var(--border);padding:9px 12px;font-size:.88rem;background:#fafafa;transition:var(--transition);width:100%;color:var(--text);font-family:'Inter',sans-serif}
.f-control:focus,.f-select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.12);outline:none;background:#fff}
.f-control[readonly]{background:#eef2f6;color:var(--text-muted);cursor:not-allowed; border-color: #dde3ea;}
.f-group{margin-bottom:14px}

/* ITEMS TABLE */
.items-wrap{overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--border)}
.items-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:700px}
.items-table thead th{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:12px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;border:none;text-align:center}
.items-table tbody tr{border-bottom:1px solid var(--border);transition:background .18s}
.items-table tbody tr:last-child{border-bottom:none;}
.items-table tbody tr:hover{background:#f8fafc}
.items-table td{padding:10px 8px;vertical-align:middle}
.items-table input,.items-table select{width:100%;padding:8px 9px;border:1.5px solid var(--border);border-radius:6px;font-size:.8rem;font-family:'Inter',sans-serif;transition:var(--transition);background:#fafafa}
.items-table input:focus,.items-table select:focus{border-color:var(--brand);box-shadow:0 0 0 2px rgba(2,132,199,.12);outline:none;background:#fff}
.items-table input[readonly]{background:#eef2f6;color:var(--text-muted); border-color: #dde3ea;}

/* TOTALS */
.totals-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:16px}
@media(max-width:768px){.totals-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:400px){.totals-grid{grid-template-columns:1fr 1fr}}
.total-item{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px; transition: var(--transition);}
.total-label{font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.total-value{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;color:var(--text)}

/* BUTTONS */
.btn-brand{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;border:none;padding:10px 24px;border-radius:50px;font-size:.88rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);box-shadow:0 4px 14px rgba(2,132,199,.25)}
.btn-brand:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(2,132,199,.35)}
.btn-add-row{background:linear-gradient(135deg,var(--success),#059669);color:#fff;border:none;padding:8px 18px;border-radius:50px;font-size:.8rem;font-weight:700;margin-top:12px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--transition)}
.btn-add-row:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(22,163,74,.3)}
.btn-remove{background:linear-gradient(135deg,var(--danger),#b91c1c);color:#fff;border:none;padding:6px 10px;border-radius:7px;font-size:.75rem;cursor:pointer;transition:var(--transition); box-shadow:0 2px 6px rgba(239,68,68,.25);}
.btn-remove:hover{transform:scale(1.08); box-shadow:0 4px 10px rgba(239,68,68,.35);}
.btn-help{background:transparent;color:var(--text-mid);border:1.5px solid var(--border);padding:9px 18px;border-radius:50px;font-size:.85rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--transition);}
.btn-help:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-light);}

/* ALERT */
.alert-success-top{border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:500;background:var(--success-bg);color:var(--success);border:1px solid #86efac;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

/* SELECT2 */
.select2-container--default .select2-selection--single{height:38px!important;border:1.5px solid var(--border)!important;border-radius:var(--radius-sm)!important;background:#fafafa!important; transition: var(--transition);}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:36px!important;font-size:.88rem;padding-left:12px;color:var(--text)!important;font-weight:500!important}
.select2-container--default .select2-selection--single .select2-selection__placeholder{color: var(--text-muted) !important;}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:36px!important}
.select2-container--default .select2-results__option--highlighted[aria-selected]{background:var(--brand)!important}
.select2-dropdown{border:1.5px solid var(--border)!important;border-radius:var(--radius-sm)!important;box-shadow:var(--shadow-md)!important}
.select2-container--default.select2-container--focus .select2-selection--single{border-color:var(--brand)!important;box-shadow:0 0 0 3px rgba(2,132,199,.12)!important; background:#fff!important;}
.select2-results__option{padding:8px 12px;font-size:.83rem}

/* MODALS */
.modal-content{border-radius:var(--radius-md);border:none;overflow:hidden;box-shadow:var(--shadow-lg);}
.modal-header-grad{background:linear-gradient(135deg,var(--brand-dark),var(--brand));padding:18px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;}
.modal-header-grad h5{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;margin:0; font-size: 1.05rem;}
.modal-body{padding:22px;}
.modal-footer{padding:14px 22px;border-top:1px solid var(--border); justify-content:center; gap:10px;}
.btn-close-white{filter:invert(1); opacity:0.8;}

/* MOBILE TABLE STACK */
@media(max-width:640px){
.items-table thead{display:none}
.items-table tr{display:block;margin-bottom:14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:12px;background:var(--card)}
.items-table td{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border)}
.items-table td:last-child{border-bottom:none}
.items-table td::before{content:attr(data-label);font-weight:700;font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;min-width:35%;flex-shrink:0}
.items-table input,.items-table select{width: 60%;}
}
</style>
</head>
<body>

<div class="page-wrap">

    <!-- HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-shopping-cart me-2" style="opacity:.85"></i>Purchase Entry</div>
            <div class="hero-sub">Create new purchase order with supplier and products</div>
        </div>
        <div class="hero-badge"><i class="fas fa-truck"></i> Supplier Purchase</div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert-success-top"><i class="fas fa-check-circle"></i><?php echo $success_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="save-purchase.php" id="purchaseForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <!-- Supplier Details -->
        <div class="pcard">
            <div class="pcard-header"><i class="fas fa-building" style="color:var(--brand)"></i><h5>Supplier Details</h5></div>
            <div class="pcard-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="f-group">
                            <label class="f-label">Supplier <span style="color:var(--danger)">*</span></label>
                            <select name="supplier_id" id="supplierSelect" class="f-select" style="width:100%">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($s['name']); ?>"
                                        data-phone="<?php echo $s['phone']; ?>"
                                        data-email="<?php echo $s['email']; ?>"
                                        data-address="<?php echo $s['address']; ?>"
                                        data-city="<?php echo $s['city']; ?>"
                                        data-state="<?php echo $s['state']; ?>"
                                        data-pincode="<?php echo $s['pincode']; ?>"
                                        data-gstin="<?php echo $s['gstin']; ?>">
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="f-group"><label class="f-label">Bill ID</label><input type="text" name="entry_no" class="f-control" value="<?php echo $entry_no; ?>" readonly></div>
                    </div>
                    <div class="col-md-2">
                        <div class="f-group"><label class="f-label">Party No <span style="color:var(--danger)">*</span></label><input type="text" name="party_no" class="f-control" required></div>
                    </div>
                    <div class="col-md-2">
                        <div class="f-group"><label class="f-label">Date <span style="color:var(--danger)">*</span></label><input type="date" name="date" class="f-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                    </div>
                    <div class="col-md-2">
                        <div class="f-group">
                            <label class="f-label">Type <span style="color:var(--danger)">*</span></label>
                            <select name="type" class="f-select" required>
                                <option value="">Select</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit">Credit</option>
                                <option value="Online">Online</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <div class="f-group"><label class="f-label">Bill Date</label><input type="date" name="dt" class="f-control"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="f-group"><label class="f-label">Supplier GSTIN</label><input type="text" name="supplier_gstin" id="supplierGstinField" class="f-control"></div>
                    </div>
                    <div class="col-md-3">
                        <div class="f-group"><label class="f-label">Recipient GSTIN</label><input type="text" name="recipient_gstin" class="f-control"></div>
                    </div>
                </div>
                <input type="hidden" name="name"             id="supplierName">
                <input type="hidden" name="supplier_phone"   id="supplierPhone">
                <input type="hidden" name="supplier_email"   id="supplierEmail">
                <input type="hidden" name="supplier_address" id="supplierAddress">
                <input type="hidden" name="supplier_city"    id="supplierCity">
                <input type="hidden" name="supplier_state"   id="supplierState">
                <input type="hidden" name="supplier_pincode" id="supplierPincode">
            </div>
        </div>

        <!-- Products -->
        <div class="pcard">
            <div class="pcard-header"><i class="fas fa-boxes" style="color:var(--brand)"></i><h5>Products</h5></div>
            <div class="pcard-body">
                <div class="items-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width:30%">Product Name</th>
                                <th style="width:9%">HSN Code</th>
                                <th style="width:12%">Price (₹)</th>
                                <th style="width:7%">Qty</th>
                                <th style="width:12%">Serials</th>
                                <th style="width:8%">Disc %</th>
                                <th style="width:8%">GST %</th>
                                <th style="width:10%">Amount (₹)</th>
                                <th style="width:4%">Del</th>
                            </tr>
                        </thead>
                        <tbody id="productRows">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                            <tr>
                                <td data-label="Product Name">
                                    <select name="product_id[]" class="product-select" style="width:100%">
                                        <option value="">— Select Product —</option>
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?php echo $p['id']; ?>"
                                                data-hsn="<?php echo htmlspecialchars($p['hsn_code']); ?>"
                                                data-price="<?php echo $p['purchase_price']; ?>"
                                                data-gst="<?php echo $p['gst_percentage']; ?>">
                                                <?php echo htmlspecialchars($p['product_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td data-label="HSN Code"><input type="text" name="hsn_code[]" readonly></td>
                                <td data-label="Price"><input type="number" step="0.01" name="purchase_price[]" readonly></td>
                                <td data-label="Qty"><input type="number" name="qty[]" class="qty" value="1" min="1"></td>
                                <td data-label="Serials">
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" name="serials[]" class="serials-hidden">
                                        <button type="button" class="btn btn-outline-secondary btn-sm btn-serials w-100" style="border-color: var(--border); background: #fafafa; text-align: left; padding: 7px 9px; font-size: .8rem; color: var(--text-muted);"><i class="fas fa-barcode"></i> Add Serials</button>
                                    </div>
                                </td>
                                <td data-label="Disc%"><input type="number" step="0.01" name="discount[]" class="discount" value="0"></td>
                                <td data-label="GST%"><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" readonly></td>
                                <td data-label="Amount"><input type="number" step="0.01" name="amount[]" class="amount" readonly></td>
                                <td data-label="Del"><button type="button" class="btn-remove"><i class="fas fa-trash"></i></button></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" id="addRowBtn" class="btn-add-row"><i class="fas fa-plus"></i> Add Product</button>
            </div>
        </div>

        <!-- Bill Summary -->
        <div class="pcard">
            <div class="pcard-header"><i class="fas fa-calculator" style="color:var(--brand)"></i><h5>Bill Summary</h5></div>
            <div class="pcard-body">
                <div class="totals-grid">
                    <div class="total-item">
                        <div class="total-label">Gross Amount</div>
                        <div class="total-value" id="grossAmountDisplay">₹0.00</div>
                        <input type="hidden" name="gross_amount" id="grossAmount">
                    </div>
                    <div class="total-item">
                        <div class="total-label">Total Discount</div>
                        <div class="total-value" id="totalDiscountDisplay" style="color: var(--success);">₹0.00</div>
                        <input type="hidden" name="total_discount" id="totalDiscount">
                    </div>
                    <div class="total-item">
                        <div class="total-label">Round Off</div>
                        <div class="total-value" id="roundOffDisplay" style="color: var(--text-muted);">₹0.00</div>
                        <input type="hidden" name="round_off" id="roundOff">
                    </div>
                    <div class="total-item" style="background:linear-gradient(135deg,var(--brand-light),#fff);border-color:rgba(2,132,199,.3)">
                        <div class="total-label" style="color:var(--brand)">Net Amount</div>
                        <div class="total-value" id="netAmountDisplay" style="font-size:1.3rem; color:var(--brand)">₹0.00</div>
                        <input type="hidden" name="net_amount" id="netAmount">
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap">
            <button type="button" class="btn-help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="fas fa-question-circle"></i> Help</button>
            <button type="submit" class="btn-brand"><i class="fas fa-save"></i> Save Purchase</button>
        </div>
    </form>
</div>

<!-- Serial Number Modal -->
<div class="modal fade" id="serialModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-grad" style="padding: 12px 18px;">
                <h5><i class="fas fa-barcode me-2"></i>Purchase Item - Serial No.</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p id="serialProductName" style="font-weight: 600; margin-bottom: 8px; color: var(--text-mid);"></p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 0.85rem; font-weight: 600;">Enter Serial No.:</span>
                    <span id="serialCountDisplay" style="font-size: 0.8rem; font-weight: 600; color: var(--brand);">0/1 Entered</span>
                </div>
                <div class="input-group mb-3">
                    <input type="text" id="serialInput" class="form-control" placeholder="Enter/Scan">
                    <button class="btn btn-primary" id="addSerialBtn" style="background: var(--brand); border-color: var(--brand);">Add</button>
                </div>
                <ul id="serialList" class="list-group mb-0" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">
                    <!-- Serial items will go here -->
                </ul>
            </div>
            <div class="modal-footer" style="padding: 10px 16px; justify-content: flex-end;">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveSerialBtn" style="background: var(--brand); border-color: var(--brand);">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-grad">
                <h5><i class="fas fa-check-circle me-2"></i>Purchase Saved</h5>
            </div>
            <div class="modal-body text-center py-4">
                <div style="width:64px;height:64px;background:var(--success-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.8rem;color:var(--success)"><i class="fas fa-check"></i></div>
                <p style="font-weight:600;color:var(--text); font-size:1.05rem;">Purchase entry saved successfully!</p>
            </div>
            <div class="modal-footer">
                <button class="btn-brand" id="successOkBtn">OK — Go to Purchases</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-grad">
                <h5><i class="fas fa-question-circle me-2"></i>Help & Shortcuts</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:22px">
                <ul style="list-style:none;padding:0;margin:0">
                    <?php foreach ([
                        ['Search Product','Type product name to search in dropdown'],
                        ['Select Product','Click on product name — HSN, Price, GST auto-fill'],
                        ['Adjust Qty/Disc','Edit quantity and discount per row'],
                        ['Add Row','Click Add Product button for more items'],
                        ['Save','Click Save Purchase when done'],
                    ] as $h): ?>
                    <li style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)">
                        <div style="width:30px;height:30px;background:var(--brand-light);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-arrow-right" style="color:var(--brand);font-size:.75rem"></i></div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem;color:var(--text)"><?php echo $h[0]; ?></div>
                            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px"><?php echo $h[1]; ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="modal-footer">
                <button class="btn-brand" style="width: 100%; justify-content:center;" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    var products = <?php echo json_encode($products); ?>;
    var productMap = {};
    products.forEach(p => productMap[p.id] = p);

    // Supplier select2
    $('#supplierSelect').select2({ placeholder:'Search supplier…', allowClear:true, width:'100%' })
        .on('change', function(){
            var opt = $(this).find('option:selected');
            if (opt.val()) {
                $('#supplierName').val(opt.data('name'));
                $('#supplierPhone').val(opt.data('phone'));
                $('#supplierEmail').val(opt.data('email'));
                $('#supplierAddress').val(opt.data('address'));
                $('#supplierCity').val(opt.data('city'));
                $('#supplierState').val(opt.data('state'));
                $('#supplierPincode').val(opt.data('pincode'));
                $('#supplierGstinField').val(opt.data('gstin'));
            } else {
                $('#supplierName,#supplierPhone,#supplierEmail,#supplierAddress,#supplierCity,#supplierState,#supplierPincode,#supplierGstinField').val('');
            }
        });

    function initSelect2(sel) {
        if (!sel.attr('id')) sel.attr('id', 'prod_sel_' + Math.floor(Math.random() * 1000000));
        if (sel.hasClass('select2-hidden-accessible')) sel.select2('destroy');
        sel.select2({ placeholder:'Search product…', allowClear:true, width:'100%' })
            .off('change.prod').on('change.prod', function(){
                var row = $(this).closest('tr');
                var pid = $(this).val();
                if (pid && productMap[pid]) {
                    var p = productMap[pid];
                    row.find('[name="hsn_code[]"]').val(p.hsn_code || '');
                    row.find('[name="purchase_price[]"]').val(p.purchase_price || 0);
                    row.find('[name="gst_rate[]"]').val(p.gst_percentage || 0);
                    if (!row.find('.qty').val()) row.find('.qty').val('1');
                } else {
                    row.find('[name="hsn_code[]"],[name="purchase_price[]"],[name="gst_rate[]"]').val('');
                    row.find('.qty').val('1');
                    row.find('.discount').val('0');
                }
                calcRow(row); calcTotal();
            });
    }

    function calcRow(row) {
        var qty   = parseFloat(row.find('.qty').val()) || 0;
        var price = parseFloat(row.find('[name="purchase_price[]"]').val()) || 0;
        var disc  = parseFloat(row.find('.discount').val()) || 0;
        var sub   = qty * price;
        row.find('.amount').val((sub - sub * disc / 100).toFixed(2));
    }

    function calcTotal() {
        var gross = 0, disc = 0, inc = 0;
        $('#productRows tr').each(function(){
            calcRow($(this));
            var amount = parseFloat($(this).find('.amount').val()) || 0;
            var gst    = parseFloat($(this).find('.gst-rate').val()) || 0;
            var qty    = parseFloat($(this).find('.qty').val()) || 0;
            var price  = parseFloat($(this).find('[name="purchase_price[]"]').val()) || 0;
            var d      = parseFloat($(this).find('.discount').val()) || 0;
            inc  += amount;
            disc += qty * price * d / 100;
            gross += gst > 0 ? amount / (1 + gst/100) : amount;
        });
        var net = Math.round(inc), ro = net - inc;
        $('#grossAmount').val(gross.toFixed(2));
        $('#totalDiscount').val(disc.toFixed(2));
        $('#roundOff').val(ro.toFixed(2));
        $('#netAmount').val(net.toFixed(2));
        $('#grossAmountDisplay').text('₹'+gross.toFixed(2));
        $('#totalDiscountDisplay').text('₹'+disc.toFixed(2));
        $('#roundOffDisplay').text('₹'+ro.toFixed(2));
        $('#netAmountDisplay').text('₹'+net.toFixed(2));
    }

    $('#productRows .product-select').each(function(){ initSelect2($(this)); });
    
    $('#productRows tr').each(function(){
        var row = $(this);
        row.find('.qty,.discount').off('input').on('input', function(){ calcRow(row); calcTotal(); });
    });

    $('#addRowBtn').on('click', function(){
        var lastRow = $('#productRows tr:last');
        var lastSel = lastRow.find('.product-select');
        if (lastSel.hasClass('select2-hidden-accessible')) lastSel.select2('destroy');
        
        var row = lastRow.clone();
        initSelect2(lastSel); // re-init original
        
        row.find('input').val('');
        row.find('.qty').val('1');
        row.find('.discount').val('0');
        row.find('.btn-serials').html('<i class="fas fa-barcode"></i> Add Serials').css('color', 'var(--text-muted)');
        
        row.removeAttr('data-select2-id');
        row.find('*').removeAttr('data-select2-id');
        
        $('#productRows').append(row);
        initSelect2(row.find('.product-select'));
        row.find('.qty,.discount').off('input').on('input', function(){ calcRow(row); calcTotal(); });
    });

    $(document).on('click', '.btn-remove', function(){
        if ($('#productRows tr').length > 1) { $(this).closest('tr').remove(); calcTotal(); }
        else alert('At least one product row is required.');
    });

    // Serial Modal Logic
    var currentSerialRow = null;
    var tempSerials = [];
    var targetQty = 0;

    $(document).on('click', '.btn-serials', function() {
        currentSerialRow = $(this).closest('tr');
        var productSelect = currentSerialRow.find('.product-select');
        var productId = productSelect.val();
        
        if (!productId) {
            alert('Please select a product first.');
            return;
        }
        
        var productName = productSelect.find('option:selected').text();
        targetQty = parseInt(currentSerialRow.find('.qty').val()) || 1;
        
        var existingSerials = currentSerialRow.find('.serials-hidden').val();
        tempSerials = existingSerials ? existingSerials.split(',') : [];
        
        $('#serialProductName').text(productName);
        $('#serialInput').val('');
        updateSerialUI();
        
        var modal = new bootstrap.Modal(document.getElementById('serialModal'));
        modal.show();
        
        setTimeout(() => $('#serialInput').focus(), 500);
    });

    function updateSerialUI() {
        $('#serialCountDisplay').text(`${tempSerials.length}/${targetQty} Entered`);
        
        var listHtml = '';
        tempSerials.forEach((sn, index) => {
            listHtml += `<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
                ${sn}
                <button type="button" class="btn btn-sm text-danger remove-serial-btn" data-index="${index}" style="padding: 0; background: none; border: none;"><i class="fas fa-times"></i></button>
            </li>`;
        });
        $('#serialList').html(listHtml);
        
        if (tempSerials.length >= targetQty) {
            $('#serialInput').prop('disabled', true);
            $('#addSerialBtn').prop('disabled', true);
        } else {
            $('#serialInput').prop('disabled', false);
            $('#addSerialBtn').prop('disabled', false);
        }
    }

    $('#addSerialBtn').on('click', function() {
        var sn = $('#serialInput').val().trim();
        if (!sn) return;
        
        if (tempSerials.includes(sn)) {
            alert('This serial number is already added.');
            return;
        }
        
        if (tempSerials.length < targetQty) {
            tempSerials.push(sn);
            $('#serialInput').val('');
            updateSerialUI();
            $('#serialInput').focus();
        }
    });

    $('#serialInput').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $('#addSerialBtn').click();
        }
    });

    $(document).on('click', '.remove-serial-btn', function() {
        var index = $(this).data('index');
        tempSerials.splice(index, 1);
        updateSerialUI();
    });

    $('#saveSerialBtn').on('click', function() {
        if (tempSerials.length > 0 && tempSerials.length !== targetQty) {
             if(!confirm(`You have entered ${tempSerials.length} out of ${targetQty} serial numbers. Are you sure you want to save?`)) {
                 return;
             }
        }
        
        currentSerialRow.find('.serials-hidden').val(tempSerials.join(','));
        
        var btn = currentSerialRow.find('.btn-serials');
        if (tempSerials.length > 0) {
            btn.html(`<i class="fas fa-check text-success"></i> ${tempSerials.length} Added`).css('color', 'var(--text)');
        } else {
            btn.html('<i class="fas fa-barcode"></i> Add Serials').css('color', 'var(--text-muted)');
        }
        
        bootstrap.Modal.getInstance(document.getElementById('serialModal')).hide();
    });
    
    // Update targetQty on qty change if there are serials
    $(document).on('change input', '.qty', function() {
        var row = $(this).closest('tr');
        var newQty = parseInt($(this).val()) || 1;
        var existing = row.find('.serials-hidden').val();
        var arr = existing ? existing.split(',') : [];
        if (arr.length > newQty) {
            // Trim serials if qty is reduced
            arr = arr.slice(0, newQty);
            row.find('.serials-hidden').val(arr.join(','));
            row.find('.btn-serials').html(`<i class="fas fa-check text-success"></i> ${arr.length} Added`).css('color', 'var(--text)');
        }
    });

    $('#purchaseForm').on('submit', function(e){
        if (!$('#supplierSelect').val()) { e.preventDefault(); alert('Please select a supplier.'); return false; }
        var has = false;
        $('#productRows tr').each(function(){ if ($(this).find('.product-select').val()) has = true; });
        if (!has) { e.preventDefault(); alert('Please add at least one product.'); return false; }
    });

    <?php if ($success_message): ?>
    new bootstrap.Modal(document.getElementById('successModal')).show();
    $('#successOkBtn').on('click', function(){ window.location.href = 'purchase-stock.php'; });
    <?php endif; ?>
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>