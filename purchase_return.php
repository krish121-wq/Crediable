<?php
session_start();
require_once 'config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* ---------------- LOGIN CHECK FIRST ---------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* ---------------- ALL POST LOGIC FIRST ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {

    // your full logic here

    header("Location: purchase_return.php?success=1");
    exit();
}

/* ---------------- ONLY AFTER LOGIC ---------------- */
include 'layout.php';

// Get user information
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Handle form submission for processing returns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    try {
        $conn->autocommit(false);
        
        $bill_id = trim($_POST['bill_id']);
        $return_note = trim($_POST['return_note']);
        $selected_items = $_POST['selected_items'] ?? [];
        
        if (empty($selected_items)) {
            throw new Exception("Please select at least one item to return.");
        }
        
        if (empty($return_note)) {
            throw new Exception("Please provide a reason for the return.");
        }
        
        // Verify bill exists
        $stmt = $conn->prepare("SELECT id, name as supplier_name FROM purchases WHERE entry_no = ?");
        $stmt->bind_param("s", $bill_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$bill) {
            throw new Exception("Purchase bill not found.");
        }
        
        foreach ($selected_items as $item_id) {
            $return_quantity = (int)$_POST['return_qty_' . $item_id];
            
            if ($return_quantity <= 0) {
                continue;
            }
            
            // Get original purchase item details
            $stmt = $conn->prepare("
                SELECT pi.product_id, pi.quantity, pi.purchase_price, pi.amount, p.product_name
                FROM purchase_items pi
                JOIN products p ON pi.product_id = p.id
                WHERE pi.id = ? AND pi.purchase_id = ?
            ");
            $stmt->bind_param("ii", $item_id, $bill['id']);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$item) {
                throw new Exception("Purchase item not found.");
            }
            
            if ($return_quantity > $item['quantity']) {
                throw new Exception("Return quantity cannot exceed purchased quantity for " . $item['product_name']);
            }
            
            $return_amount = $return_quantity * $item['purchase_price'];
            
            // Create return entry
            $stmt = $conn->prepare("
                INSERT INTO purchase_return_records (
                    original_bill_no, product_id, return_quantity, unit_price, return_amount, 
                    return_note, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("siiddsi", $bill_id, $item['product_id'], $return_quantity, 
                            $item['purchase_price'], $return_amount, $return_note, $created_by_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create return entry: " . $stmt->error);
            }
            $stmt->close();
            
            // Update product stock (subtract returned quantity)
            $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $stmt->bind_param("ii", $return_quantity, $item['product_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product stock: " . $stmt->error);
            }
            $stmt->close();
        }
        
       $conn->commit();

// store message in session
$_SESSION['success_message'] = "Purchase return processed successfully!";

// PREVENT RE-SUBMIT ON REFRESH
header("Location: purchase_return.php?success=1");
exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    } finally {
        $conn->autocommit(true);
    }
}

// Fetch purchase returns for display
try {
    $stmt = $conn->prepare("
        SELECT prr.*, pur.name as supplier_name, p.product_name,
               CASE 
                   WHEN admin_users.id IS NOT NULL THEN 'Admin'
                   WHEN staff_users.name IS NOT NULL THEN staff_users.name
                   ELSE 'Unknown'
               END AS created_by_name
        FROM purchase_return_records prr
        JOIN purchases pur ON prr.original_bill_no = pur.entry_no
        JOIN products p ON prr.product_id = p.id
        LEFT JOIN users admin_users ON prr.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON prr.created_by = staff_users.id
        ORDER BY prr.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $returns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $returns = [];
    error_log("Error fetching returns: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Purchase Return — CREDIBLE</title>
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
    --text:        #0b1220;
    --border:      #e2e8f0;
    --text-mid:    #475569;
    --text-muted:  #94a3b8;

    --sidebar-width: 260px;
    --navbar-height: 70px;

    --radius-sm:   7px;
    --radius-md:   12px;
    --radius-lg:   18px;
    
    --shadow-sm:   0 2px 8px rgba(2,132,199,.07);
    --shadow-md:   0 8px 24px rgba(2,132,199,.11);
    --shadow-lg:   0 16px 48px rgba(2,132,199,.16);
    --transition:  all .25s cubic-bezier(.4,0,.2,1);
}

/* RESET */
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: "Inter", sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
    overflow-x: hidden;
}

/* ================= MAIN FIX (IMPORTANT) ================= */
.main-content {
    margin-left: var(--sidebar-width);
    margin-top: var(--navbar-height);
    padding: 24px 28px 60px;
    transition: var(--transition);
    width: calc(100% - var(--sidebar-width));
    min-height: calc(100vh - var(--navbar-height));
}

/* When sidebar hidden */
.sidebar.sidebar-hidden ~ .main-content {
    margin-left: 0;
    width: 100%;
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
    content: ''; position: absolute;
    right: -50px; top: -50px;
    width: 200px; height: 200px;
    border-radius: 50%; background: rgba(255,255,255,.06);
}
.hero-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.45rem; font-weight: 800; color: #fff; margin:0 0 4px 0;
}
.hero-sub { color: rgba(255,255,255,.75); font-size: 0.85rem; margin:0; }

.hero-actions {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.15);
    padding: 8px 18px;
    border-radius: 50px;
    border: 1px solid rgba(255,255,255,0.25);
    color: #fff;
    backdrop-filter: blur(4px);
    z-index: 10;
}

/* ================= CARDS ================= */
.card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    background: linear-gradient(90deg, var(--brand-light), #fff);
    color: var(--brand);
    padding: 16px 20px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1rem;
    font-weight: 800;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
}

.card-body { padding: 20px; }

/* ================= TABLE ================= */
.table-responsive {
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    overflow-x: auto;
}

.table {
    font-size: 14px;
    width: 100%;
    margin-bottom: 0;
    border-collapse: collapse;
}

.table th {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    font-size: 0.72rem;
    padding: 14px 16px;
    text-transform: uppercase;
    white-space: nowrap;
    letter-spacing: 0.5px;
    font-weight: 700;
    border: none;
}

.table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.table tbody tr:last-child td { border-bottom: none; }
.table tbody tr:hover { background: #f8fafc; }

/* ================= FORM ================= */
.form-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--text-mid);
    margin-bottom: 6px;
}

.form-control, .form-select {
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    background: #fafafa;
    transition: var(--transition);
}

.form-control:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
    outline: none;
    background: #fff;
}

/* ================= BUTTON ================= */
.btn-custom {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    border: none;
    padding: 10px 22px;
    font-size: 0.88rem;
    border-radius: 50px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(2,132,199,0.25);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(2,132,199,0.35);
    color: #fff;
}

/* ================= BADGE ================= */
.badge {
    font-size: 0.72rem;
    padding: 4px 9px;
    border-radius: 6px;
    font-weight: 700;
    font-family: monospace;
    letter-spacing: 0.5px;
}

.badge-brand { background: var(--brand-light); color: var(--brand); }
.badge-warning { background: var(--warning-bg); color: var(--warning); }
.badge-secondary { background: #f1f5f9; color: var(--text-mid); font-family: 'Inter', sans-serif;}

/* ================= ALERT ================= */
.alert {
    font-size: 0.88rem;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    font-weight: 500;
    border: none;
    margin-bottom: 20px;
}
.alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
.alert-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #fca5a5; }

/* ================= BILL CARD ================= */
.bill-details-card {
    background: var(--bg);
    border: 1px solid var(--border);
    padding: 20px;
    border-radius: var(--radius-md);
    margin-bottom: 20px;
}

.custom-input {
    border-radius: var(--radius-sm); 
    border: 1.5px solid var(--border); 
    padding: 10px; 
    font-size: 0.9rem; 
    background: #fafafa;
}

/* ================= RESPONSIVE ================= */
@media (max-width: 992px) {
    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 16px 14px;
    }
}

@media (max-width: 768px) {
    .page-hero { flex-direction: column; align-items: stretch; text-align: left; }
    .table td, .table th { padding: 12px 10px; font-size: 0.8rem;}
    .btn-custom { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<!-- Main Content Area -->
<div class="main-content">
    
    <!-- HERO SECTION -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-undo-alt me-2"></i>Purchase Return</div>
            <p class="hero-sub">Return purchased items with proper documentation</p>
        </div>
        <div class="hero-actions">
            <i class="fas fa-user-circle fa-lg me-2"></i>
            <div style="line-height:1.2;">
                <small style="font-size:0.7rem; opacity:0.8;">Logged in as</small><br>
                <strong style="font-size:0.85rem; font-weight:700;"><?php echo ucfirst($user_type); ?></strong>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        Purchase return processed successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($error_message) && !empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Bill Search Section -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-search me-2"></i>Search Purchase Bill
        </div>
        <div class="card-body">
            <form id="billSearchForm">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="search_bill_id" class="form-label">
                            <i class="fas fa-receipt me-1"></i>Bill Number
                        </label>
                        <input type="text" class="form-control" id="search_bill_id" 
                               placeholder="Enter bill number (e.g., BILL-20250905-101)" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn-custom w-100">
                            <i class="fas fa-search me-2"></i>Search Bill
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bill Details Section -->
    <div class="card" id="billDetailsCard" style="display: none;">
        <div class="card-header">
            <i class="fas fa-file-invoice me-2"></i>Bill Details
        </div>
        <div class="card-body" id="billDetailsContent" style="padding: 0;">
            <!-- Bill details will be loaded here via AJAX -->
        </div>
    </div>

    <!-- Purchase Returns History -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-history me-2"></i>Recent Returns
        </div>
        <div class="card-body" style="padding: 20px;">
            <?php if (empty($returns)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3" style="opacity:0.3; color:var(--brand)"></i>
                    <p style="font-weight:600; font-size:1rem; color:var(--text); margin-bottom:4px;">No purchase returns found.</p>
                    <small>Process a return using the search form above.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Return ID</th>
                                <th>Bill Number</th>
                                <th>Supplier</th>
                                <th>Product</th>
                                <th>Return Qty</th>
                                <th>Return Amount</th>
                                <th>Return Note</th>
                                <th>Created By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns as $return): ?>
                                <tr>
                                    <td><span class="badge badge-brand">#<?php echo $return['id']; ?></span></td>
                                    <td><span class="badge" style="background:var(--info-bg); color:var(--info);"><?php echo htmlspecialchars($return['original_bill_no']); ?></span></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($return['supplier_name']); ?></td>
                                    <td><?php echo htmlspecialchars($return['product_name']); ?></td>
                                    <td><span class="badge badge-warning"><?php echo $return['return_quantity']; ?></span></td>
                                    <td style="color:var(--brand); font-weight:800;">₹<?php echo number_format($return['return_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(substr($return['return_note'], 0, 50)) . (strlen($return['return_note']) > 50 ? '...' : ''); ?></td>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($return['created_by_name']); ?></span></td>
                                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo date('d M Y, H:i', strtotime($return['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div> <!-- End main-content -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // Handle bill search
        $('#billSearchForm').on('submit', function(e) {
            e.preventDefault();

            const billId = $('#search_bill_id').val().trim();
            console.log('Searching for bill:', billId);

            if (!billId) {
                alert('Please enter a bill number');
                return;
            }

            // Show loading
            $('#billDetailsContent').html(`
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x mb-3" style="color:var(--brand);"></i>
                    <p style="color:var(--text-muted); font-weight:600;">Loading bill details...</p>
                </div>
            `);
            $('#billDetailsCard').show();

            // AJAX call to fetch bill details
            $.ajax({
                url: 'fetch_bill_details.php',
                method: 'POST',
                data: { bill_id: billId },
                dataType: 'json',
                success: function(response) {
                    console.log('AJAX response:', response);
                    if (response.success) {
                        displayBillDetails(response.data);
                    } else {
                        $('#billDetailsContent').html(`
                            <div style="padding:20px;">
                                <div class="alert alert-danger" style="margin:0;">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    ${response.message}
                                </div>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', xhr.responseText);
                    $('#billDetailsContent').html(`
                        <div style="padding:20px;">
                            <div class="alert alert-danger" style="margin:0;">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error loading bill details: ${error}
                                <br><small style="opacity:0.8;">Response: ${xhr.responseText}</small>
                            </div>
                        </div>
                    `);
                }
            });
        });

        // Function to display bill details
        function displayBillDetails(data) {
            const bill = data.bill;
            const items = data.items;

            let html = `
                <div style="padding: 20px;">
                    <div class="bill-details-card mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 style="color:var(--brand); font-weight:800; font-family:'Plus Jakarta Sans',sans-serif; margin-bottom:15px;"><i class="fas fa-file-invoice me-2"></i>Bill Information</h5>
                                <table class="table table-borderless" style="margin:0;">
                                    <tr><td style="color:var(--text-mid); font-weight:600; width:120px; padding:6px 0;">Bill Number:</td><td style="padding:6px 0;"><span style="font-family:monospace; background:var(--brand-light); color:var(--brand); padding:3px 8px; border-radius:6px; font-weight:700;">${bill.entry_no}</span></td></tr>
                                    <tr><td style="color:var(--text-mid); font-weight:600; padding:6px 0;">Supplier:</td><td style="padding:6px 0; font-weight:700;">${bill.supplier_name}</td></tr>
                                    <tr><td style="color:var(--text-mid); font-weight:600; padding:6px 0;">Party No:</td><td style="padding:6px 0;">${bill.party_no}</td></tr>
                                    <tr><td style="color:var(--text-mid); font-weight:600; padding:6px 0;">Date:</td><td style="padding:6px 0;">${bill.date}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6 mt-3 mt-md-0">
                                <h5 style="color:var(--brand); font-weight:800; font-family:'Plus Jakarta Sans',sans-serif; margin-bottom:15px;"><i class="fas fa-info-circle me-2"></i>Return Information</h5>
                                <p style="color:var(--text-muted); font-size:0.85rem;">Select items below and adjust quantities to process the return correctly into your inventory.</p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="returnForm">
                        <input type="hidden" name="bill_id" value="${bill.entry_no}">
                        <input type="hidden" name="process_return" value="1">

                        <div class="row">
                            <div class="col-md-8">
                                <h5 style="font-weight:800; color:var(--brand-dark); font-family:'Plus Jakarta Sans',sans-serif; margin-bottom:15px;"><i class="fas fa-boxes me-2"></i>Items to Return</h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th width="50" style="text-align:center;">Select</th>
                                                <th>Product</th>
                                                <th>HSN Code</th>
                                                <th style="text-align:center;">Available Qty</th>
                                                <th>Unit Price</th>
                                                <th style="width:100px;">Return Qty</th>
                                                <th>Return Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
            `;

            items.forEach(function(item) {
                html += `
                                            <tr>
                                                <td style="text-align:center;">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input class="form-check-input item-checkbox" type="checkbox" style="cursor:pointer;"
                                                               name="selected_items[]" value="${item.id}" id="item_${item.id}">
                                                    </div>
                                                </td>
                                                <td><strong style="color:var(--text);">${item.product_name}</strong></td>
                                                <td><span style="background:var(--bg); padding:3px 8px; border-radius:4px; font-size:0.75rem; color:var(--text-mid);">${item.hsn_code || 'N/A'}</span></td>
                                                <td style="text-align:center;"><span class="badge" style="background:var(--success-bg); color:var(--success); font-size:0.8rem;">${item.quantity}</span></td>
                                                <td><strong style="color:var(--text);">₹${parseFloat(item.purchase_price).toFixed(2)}</strong></td>
                                                <td>
                                                    <input type="number" class="form-control return-qty" style="padding:6px 10px; font-size:0.85rem;"
                                                           name="return_qty_${item.id}" min="0" max="${item.quantity}"
                                                           value="0" data-price="${item.purchase_price}">
                                                </td>
                                                <td class="return-amount"><strong style="color:var(--brand);">₹0.00</strong></td>
                                            </tr>
                `;
            });

            html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mt-4 mt-md-0" style="margin-bottom:0; box-shadow:none;">
                                    <div class="card-header" style="background:linear-gradient(90deg, var(--brand-light), #fff); padding:16px 20px; border-bottom:1px solid var(--border);">
                                        <h6 style="margin:0; font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; color:var(--brand); font-size:0.95rem;"><i class="fas fa-calculator me-2"></i>Return Summary</h6>
                                    </div>
                                    <div class="card-body" style="padding:20px;">
                                        <div class="mb-4">
                                            <label style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-mid); display:block; margin-bottom:5px;">Total Return Amount</label>
                                            <div id="totalReturnAmount" style="font-size:1.8rem; font-weight:800; color:var(--brand); font-family:'Plus Jakarta Sans',sans-serif;">₹0.00</div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="return_note" style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-mid); display:block; margin-bottom:6px;">
                                                <i class="fas fa-sticky-note me-1"></i>Return Reason <span style="color:var(--danger)">*</span>
                                            </label>
                                            <textarea class="form-control" name="return_note" id="return_note"
                                                      rows="4" placeholder="Enter reason for return..." required></textarea>
                                        </div>

                                        <button type="submit" class="btn-custom w-100 justify-content-center" id="processReturnBtn" disabled>
                                            <i class="fas fa-check-circle me-2"></i>Process Return
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            `;

            $('#billDetailsContent').html(html);

            // Handle checkbox changes
            $('.item-checkbox').on('change', function() {
                const row = $(this).closest('tr');
                const returnQtyInput = row.find('.return-qty');

                if ($(this).is(':checked')) {
                    if (returnQtyInput.val() == 0) {
                        returnQtyInput.val(1);
                    }
                    returnQtyInput.css('border-color', 'var(--success)');
                } else {
                    returnQtyInput.val(0);
                    returnQtyInput.css('border-color', 'var(--border)');
                }
                updateReturnAmount();
                updateProcessButton();
            });

            // Handle quantity changes
            $('.return-qty').on('input', function() {
                const checkbox = $(this).closest('tr').find('.item-checkbox');
                const qty = parseInt($(this).val()) || 0;

                if (qty > 0) {
                    checkbox.prop('checked', true);
                    $(this).css('border-color', 'var(--success)');
                } else {
                    checkbox.prop('checked', false);
                    $(this).css('border-color', 'var(--border)');
                }

                updateReturnAmount();
                updateProcessButton();
            });

            function updateReturnAmount() {
                let totalAmount = 0;
                $('.return-qty').each(function() {
                    const row = $(this).closest('tr');
                    const qty = parseInt($(this).val()) || 0;
                    const price = parseFloat($(this).data('price')) || 0;
                    const amount = qty * price;
                    row.find('.return-amount').html('<strong style="color:var(--brand);">₹' + amount.toFixed(2) + '</strong>');
                    if (qty > 0) {
                        totalAmount += amount;
                    }
                });
                $('#totalReturnAmount').text('₹' + totalAmount.toFixed(2));
            }

            function updateProcessButton() {
                let hasValidItems = false;
                $('.return-qty').each(function() {
                    if (parseInt($(this).val()) > 0) {
                        hasValidItems = true;
                        return false; // break the loop
                    }
                });
                $('#processReturnBtn').prop('disabled', !hasValidItems);
            }
        }
    });
    
    $(document).on('submit', '#returnForm', function () {
        $('#processReturnBtn')
            .prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
    });
</script>
</body>
</html>