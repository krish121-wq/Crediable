<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
include 'layout.php';

$user_type     = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_challan'])) {
    try {
        $conn->autocommit(false);
        $supplier_name    = trim($_POST['supplier_name']);
        $challan_no       = trim($_POST['challan_no']);
        $challan_date     = trim($_POST['challan_date']);
        $invoice_no       = trim($_POST['invoice_no']);
        $party_no         = trim($_POST['party_no']);
        $additional_notes = trim($_POST['additional_notes']);
        if (empty($supplier_name)||empty($challan_no)||empty($challan_date))
            throw new Exception("Please fill all required fields.");
        $stmt = $conn->prepare("INSERT INTO delivery_challans (supplier_name,challan_no,challan_date,invoice_no,party_no,additional_notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $stmt->bind_param("ssssssi",$supplier_name,$challan_no,$challan_date,$invoice_no,$party_no,$additional_notes,$created_by_id);
        if (!$stmt->execute()) throw new Exception("Failed to create delivery challan: ".$stmt->error);
        $challan_id = $conn->insert_id; $stmt->close();
        if (isset($_POST['product_name'])&&is_array($_POST['product_name'])) {
            for ($i=0;$i<count($_POST['product_name']);$i++) {
                $pname  = trim($_POST['product_name'][$i]);
                $batch  = trim($_POST['batch_no'][$i]);
                $expiry = trim($_POST['expiry_date'][$i]);
                $qty    = (int)$_POST['quantity'][$i];
                $uprice = (float)$_POST['unit_price'][$i];
                $gst    = (float)$_POST['gst_rate'][$i];
                if (empty($pname)||$qty<=0) continue;
                $amount = $qty*$uprice; $gst_amt = $amount*$gst/100; $total = $amount+$gst_amt;
                $stmt = $conn->prepare("INSERT INTO delivery_challan_items (challan_id,product_name,batch_no,expiry_date,quantity,unit_price,gst_rate,amount,gst_amount,total_amount) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("isssiddddd",$challan_id,$pname,$batch,$expiry,$qty,$uprice,$gst,$amount,$gst_amt,$total);
                if (!$stmt->execute()) throw new Exception("Failed to add item: ".$stmt->error);
                $stmt->close();
            }
        }
        $conn->commit();
        $success_message = "Delivery challan created successfully!";
    } catch (Exception $e) { $conn->rollback(); $error_message = $e->getMessage(); }
    finally { $conn->autocommit(true); }
}

try {
    $stmt = $conn->prepare("SELECT dc.*,CASE WHEN au.id IS NOT NULL THEN 'Admin' WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name,COUNT(dci.id) as item_count,SUM(dci.total_amount) as total_value FROM delivery_challans dc LEFT JOIN delivery_challan_items dci ON dc.id=dci.challan_id LEFT JOIN users au ON dc.created_by=au.id LEFT JOIN create_users cu ON dc.created_by=cu.id GROUP BY dc.id ORDER BY dc.created_at DESC LIMIT 50");
    $stmt->execute(); $challans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Exception $e) { $challans = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delivery Challan — CREDIBLE</title>
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

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
}

.page-wrap {
    margin-top: 70px;
    margin-left: 260px;
    padding: 28px 24px 60px;
    transition: var(--transition);
    min-height: calc(100vh - 70px);
}

@media(max-width:992px) {
    .page-wrap { margin-left: 0; padding: 18px 14px 50px; }
}

/* ── PAGE HERO ── */
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
    font-size: 1.5rem;
    font-weight: 800;
    color: #fff;
}
.hero-sub {
    color: rgba(255,255,255,.75);
    font-size: .85rem;
    margin-top: 3px;
}
.hero-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    position: relative;
    z-index: 1;
}

/* BUTTONS */
.btn-hero {
    color: #fff;
    border: 2px solid rgba(255,255,255,.5);
    padding: 10px 22px;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    transition: var(--transition);
    background: rgba(255,255,255,.15);
    text-decoration: none;
}
.btn-hero:hover, .btn-hero:focus {
    background: rgba(255,255,255,.28);
    border-color: #fff;
    color: #fff;
    transform: translateY(-1px);
}
.btn-hero.solid {
    background: rgba(255,255,255,.9);
    color: var(--brand-dark);
    border-color: #fff;
}
.btn-hero.solid:hover {
    background: #fff;
    color: var(--brand);
}
.btn-brand {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    border: none;
    padding: 10px 22px;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    transition: var(--transition);
    box-shadow: 0 4px 14px rgba(2,132,199,.28);
}
.btn-brand:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(2,132,199,.38);
    color: #fff;
}
.btn-secondary-sm {
    background: #fafafa;
    border: 1.5px solid var(--border);
    color: var(--text-mid);
    padding: 9px 18px;
    border-radius: 50px;
    font-size: .83rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}
.btn-secondary-sm:hover {
    border-color: var(--brand);
    color: var(--brand);
}
.btn-add-item {
    background: var(--brand-light);
    border: 1.5px dashed rgba(2,132,199,.35);
    color: var(--brand);
    padding: 8px 16px;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    margin-top: 10px;
}
.btn-add-item:hover {
    background: rgba(2,132,199,.1);
}
.btn-remove-item {
    background: linear-gradient(135deg, var(--danger), #b91c1c);
    color: #fff;
    border: none;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: .75rem;
    cursor: pointer;
    transition: var(--transition);
}
.btn-remove-item:hover {
    transform: scale(1.08);
}

/* ALERTS */
.alert-top {
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: .88rem;
    font-weight: 500;
    animation: slideDown .3s ease;
}
@keyframes slideDown { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
.alert-top.success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
.alert-top.danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #fca5a5; }

/* TABLE & CARDS */
.table-card {
    background: var(--card);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    margin-bottom: 20px;
}
.table-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, var(--brand-light), #fff);
}
.table-card-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .95rem;
    font-weight: 800;
    color: var(--brand);
    display: flex;
    align-items: center;
    gap: 8px;
}
.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    padding: 13px 14px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
    border: none;
}
.data-table tbody tr { border-bottom: 1px solid var(--border); transition: background .18s; }
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fafc; }
.data-table td { padding: 13px 14px; vertical-align: middle; font-size: .88rem; }

/* BADGES */
.challan-badge {
    font-family: monospace;
    font-size: .82rem;
    background: var(--brand-light);
    color: var(--brand);
    border-radius: 6px;
    padding: 4px 9px;
    font-weight: 700;
}
.badge-cnt {
    background: var(--info-bg);
    color: var(--info);
    border-radius: 50px;
    padding: 3px 9px;
    font-size: .72rem;
    font-weight: 700;
}
.badge-by {
    background: var(--brand-light);
    color: var(--brand);
    border-radius: 50px;
    padding: 3px 9px;
    font-size: .72rem;
    font-weight: 600;
}

/* ACTION BTNS */
.act-btn {
    width: 30px; height: 30px;
    border-radius: 7px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .75rem; color: #fff;
    border: none; cursor: pointer; transition: var(--transition);
    margin: 0 2px; text-decoration: none;
}
.act-btn:hover { transform: translateY(-2px) scale(1.1); color: #fff; }
.act-btn.view   { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
.act-btn.print  { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
.act-btn.export { background: linear-gradient(135deg, var(--success), #15803d); }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.empty-icon  { font-size: 2.5rem; opacity: .3; margin-bottom: 12px; }

/* MODALS */
.modal-content {
    border-radius: var(--radius-md);
    border: none;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.modal-hdr {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff;
    padding: 18px 22px;
    display: flex; align-items: center; justify-content: space-between;
}
.modal-hdr h5 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; margin: 0; }

/* ✅ ERROR SOLVED HERE: 
   Set max-height dynamically relative to the screen (100vh) minus the header, footer, and margins space (220px). 
   This guarantees the footer is ALWAYS visible on screen and ONLY the body content scrolls. */
.modal-body { 
    padding: 22px; 
    max-height: calc(100vh - 220px); 
    overflow-y: auto; 
}

.modal-footer { border-top: 1px solid var(--border); padding: 14px 22px; }
.btn-close-white { filter: invert(1); opacity: 0.8;}

/* FORM ELEMENTS INSIDE MODAL */
.pcard {
    background: var(--card);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 14px;
}
.pcard-hdr {
    background: linear-gradient(90deg, var(--brand-light), #fff);
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .82rem; font-weight: 800;
    color: var(--brand);
    display: flex; align-items: center; gap: 7px;
}
.pcard-body { padding: 16px; }

.f-label {
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px;
    color: var(--text-mid); margin-bottom: 5px; display: block;
}
.f-control, .f-select {
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border);
    padding: 9px 12px;
    font-size: .88rem;
    background: #fafafa;
    transition: var(--transition);
    width: 100%;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    outline: none;
}
.f-control:focus, .f-select:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    background: #fff;
}
.f-group { margin-bottom: 14px; }

/* MODAL ITEMS TABLE */
.items-wrap { overflow-x: auto; border-radius: var(--radius-sm); border: 1px solid var(--border); }
.items-tbl { width: 100%; border-collapse: collapse; min-width: 600px; }
.items-tbl thead th {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: #fff; padding: 10px 8px; font-size: .7rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .4px; text-align: center; border: none;
}
.items-tbl tbody tr { border-bottom: 1px solid var(--border); transition: background .18s; }
.items-tbl tbody tr:last-child { border-bottom: none;}
.items-tbl tbody tr:hover { background: #f8fafc; }
.items-tbl td { padding: 7px 6px; vertical-align: middle; }
.items-tbl input {
    width: 100%; padding: 7px 9px;
    border: 1.5px solid var(--border); border-radius: 6px;
    font-size: .82rem; transition: var(--transition); background: #fafafa;
}
.items-tbl input:focus { border-color: var(--brand); outline: none; background: #fff; box-shadow: 0 0 0 2px rgba(2,132,199,.12); }

@media print {
    .page-hero, .table-card, .no-print, .modal-footer { display: none !important; }
    .page-wrap { margin: 0; padding: 10px; }
}
</style>
</head>
<body>
<?php?>
<div class="page-wrap">

    <!-- HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-truck me-2" style="opacity:.85"></i>Delivery Challan</div>
            <div class="hero-sub">Create and manage delivery challans for shipments</div>
        </div>
        <div class="hero-actions">
            <button class="btn-hero solid no-print" data-bs-toggle="modal" data-bs-target="#createChallanModal"><i class="fas fa-plus-circle"></i> Create New Challan</button>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert-top success" id="sAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- LIST TABLE -->
    <div class="table-card no-print">
        <div class="table-card-header flex-wrap gap-2">
            <div class="table-card-title"><i class="fas fa-list"></i> All Delivery Challans</div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div style="position:relative; min-width:240px;">
                    <i class="fas fa-search" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.8rem;"></i>
                    <input type="text" id="challanSearchInput" placeholder="Search challan, supplier, invoice…" style="padding:6px 14px 6px 32px; border-radius:50px; border:1.5px solid var(--border); font-size:0.82rem; outline:none; width:100%; transition:var(--transition); background:#fafafa;" onkeyup="filterChallanTable()">
                </div>
                <span style="font-size:.78rem;color:var(--text-muted)"><strong id="challanCount"><?php echo count($challans); ?></strong> records</span>
            </div>
        </div>
        <?php if (empty($challans)): ?>
        <div class="empty-state"><div class="empty-icon"><i class="fas fa-truck"></i></div><div style="font-weight:600;margin-bottom:6px">No challans yet</div><div style="font-size:.82rem">Click <strong>Create New Challan</strong> to get started.</div></div>
        <?php else: ?>
        <div class="table-scroll">
            <table class="data-table" id="challansTable">
                <thead><tr><th>Challan No</th><th>Supplier</th><th>Date</th><th>Invoice No</th><th>Items</th><th>Total Value</th><th>Created By</th><th style="text-align:center">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($challans as $c): ?>
                <tr data-search="<?php echo strtolower(htmlspecialchars($c['challan_no'].' '.$c['supplier_name'].' '.$c['invoice_no'].' '.$c['created_by_name'])); ?>">
                    <td><span class="challan-badge"><?php echo htmlspecialchars($c['challan_no']); ?></span></td>
                    <td style="font-weight:600"><?php echo htmlspecialchars($c['supplier_name']); ?></td>
                    <td style="color:var(--text-muted);font-size:.82rem"><?php echo date('d M Y', strtotime($c['challan_date'])); ?></td>
                    <td style="color:var(--text-mid)"><?php echo htmlspecialchars($c['invoice_no'] ?: '—'); ?></td>
                    <td><span class="badge-cnt"><?php echo $c['item_count']; ?> items</span></td>
                    <td style="font-weight:800;color:var(--brand)">₹<?php echo number_format($c['total_value'] ?: 0, 2); ?></td>
                    <td><span class="badge-by"><?php echo htmlspecialchars($c['created_by_name']); ?></span></td>
                    <td style="text-align:center">
                        <button class="act-btn view" onclick="viewChallan(<?php echo $c['id']; ?>)" title="View"><i class="fas fa-eye"></i></button>
                        <button class="act-btn print" onclick="printChallan(<?php echo $c['id']; ?>)" title="Print"><i class="fas fa-print"></i></button>
                        <a href="export_challan_csv.php?challan_id=<?php echo $c['id']; ?>" class="act-btn export" title="Export CSV"><i class="fas fa-file-csv"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- CHALLAN VIEW AREA (for print) -->
    <div id="challanViewArea" style="display:none"></div>
</div>

<!-- CREATE CHALLAN MODAL -->
<div class="modal fade" id="createChallanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-hdr">
                <h5><i class="fas fa-plus-circle me-2"></i>Create New Delivery Challan</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createChallanForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="pcard">
                                <div class="pcard-hdr"><i class="fas fa-info-circle"></i> Challan Information</div>
                                <div class="pcard-body">
                                    <div class="f-group"><label class="f-label">Supplier Name <span style="color:var(--danger)">*</span></label><input type="text" class="f-control" name="supplier_name" required></div>
                                    <div class="f-group"><label class="f-label">Challan No <span style="color:var(--danger)">*</span></label><input type="text" class="f-control" name="challan_no" required></div>
                                    <div class="f-group"><label class="f-label">Challan Date <span style="color:var(--danger)">*</span></label><input type="date" class="f-control" name="challan_date" id="challan_date" required></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="pcard">
                                <div class="pcard-hdr"><i class="fas fa-file-invoice"></i> Additional Details</div>
                                <div class="pcard-body">
                                    <div class="f-group"><label class="f-label">Invoice No</label><input type="text" class="f-control" name="invoice_no"></div>
                                    <div class="f-group"><label class="f-label">Party No</label><input type="text" class="f-control" name="party_no"></div>
                                    <div class="f-group"><label class="f-label">Notes</label><textarea class="f-control" name="additional_notes" rows="3" style="resize:vertical"></textarea></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pcard">
                        <div class="pcard-hdr" style="justify-content:space-between">
                            <span><i class="fas fa-boxes"></i> Challan Items</span>
                            <button type="button" class="btn-add-item" onclick="addChallanItem()" style="margin-top:0;padding:5px 12px"><i class="fas fa-plus"></i> Add Item</button>
                        </div>
                        <div class="pcard-body">
                            <div class="items-wrap">
                                <table class="items-tbl">
                                    <thead><tr><th>Product Name</th><th>Batch No</th><th>Expiry</th><th>Qty</th><th>Unit Price</th><th>GST%</th><th>Amount</th><th>Del</th></tr></thead>
                                    <tbody id="challanItemsBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="gap:10px">
                    <button type="button" class="btn-secondary-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_challan" class="btn-brand"><i class="fas fa-save"></i> Create Challan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function(){
    var sa = document.getElementById('sAlert');
    if(sa) setTimeout(()=>{sa.style.transition='opacity .5s';sa.style.opacity='0';setTimeout(()=>sa.remove(),500);},4000);
    var d=new Date().toISOString().split('T')[0];
    document.getElementById('challan_date').value=d;
    $('#createChallanModal').on('shown.bs.modal',function(){
        if($('#challanItemsBody tr').length===0) addChallanItem();
    });
});

var ic=0;
function addChallanItem(){
    ic++;
    $('#challanItemsBody').append(`<tr id="ci_${ic}">
        <td><input type="text" class="f-control" name="product_name[]" placeholder="Product name" required style="min-width:140px"></td>
        <td><input type="text" class="f-control" name="batch_no[]" placeholder="Batch no" style="min-width:90px"></td>
        <td><input type="date" class="f-control" name="expiry_date[]" style="min-width:120px"></td>
        <td><input type="number" class="f-control quantity" name="quantity[]" min="1" value="1" onchange="calcAmt(${ic})" style="min-width:60px" required></td>
        <td><input type="number" class="f-control unit-price" name="unit_price[]" min="0" step="0.01" value="0" onchange="calcAmt(${ic})" style="min-width:90px" required></td>
        <td><input type="number" class="f-control gst-rate" name="gst_rate[]" min="0" max="100" step="0.01" value="0" onchange="calcAmt(${ic})" style="min-width:70px"></td>
        <td><input type="text" class="f-control row-amount" readonly placeholder="₹0.00" style="min-width:90px;background:#f3f4f6;border-color:#dde3ea;"></td>
        <td style="text-align:center;"><button type="button" class="btn-remove-item" onclick="removeChallanItem(${ic})"><i class="fas fa-trash"></i></button></td>
    </tr>`);
}
function removeChallanItem(id){
    $(`#ci_${id}`).remove();
    if($('#challanItemsBody tr').length===0) addChallanItem();
}
function calcAmt(id){
    var row=$(`#ci_${id}`);
    var qty=parseFloat(row.find('.quantity').val())||0;
    var up=parseFloat(row.find('.unit-price').val())||0;
    var gst=parseFloat(row.find('.gst-rate').val())||0;
    var amt=qty*up; var total=amt+amt*gst/100;
    row.find('.row-amount').val('₹'+total.toFixed(2));
}

function viewChallan(id){
    $('#challanViewArea').html('<div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--brand)"></i></div>').show();
    $.ajax({url:'fetch_challan_details.php',method:'POST',data:{challan_id:id},dataType:'json',
        success:function(r){
            if(r.success) displayChallan(r.data);
            else $('#challanViewArea').html('<div class="alert-top danger"><i class="fas fa-exclamation-circle"></i>'+r.message+'</div>');
        },error:function(){ $('#challanViewArea').html('<div class="alert-top danger"><i class="fas fa-exclamation-circle"></i>Error loading challan.</div>'); }
    });
}
function printChallan(id){
    window.open('print_delivery_challan.php?id=' + id, '_blank');
}
function displayChallan(data){
    var c=data.challan,items=data.items,total=0,rows='';
    items.forEach(it=>{
        total+=parseFloat(it.total_amount);
        rows+=`<tr><td>${it.product_name}</td><td>${it.batch_no||'—'}</td><td>${it.expiry_date||'—'}</td><td style="text-align:center">${it.quantity}</td><td style="text-align:right">₹${parseFloat(it.unit_price).toFixed(2)}</td><td style="text-align:center">${it.gst_rate}%</td><td style="text-align:right;font-weight:700">₹${parseFloat(it.total_amount).toFixed(2)}</td></tr>`;
    });
    $('#challanViewArea').html(`<div style="background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);padding:28px;box-shadow:var(--shadow-sm)">
        <div style="text-align:center;border-bottom:2px solid var(--brand);padding-bottom:16px;margin-bottom:20px">
            <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--brand);margin:0">DELIVERY CHALLAN</h2>
            <p style="color:var(--text-muted);margin:4px 0 0;font-size:.85rem">Professional Delivery Documentation</p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px">
            <div style="background:var(--brand-light);border-left:3px solid var(--brand);border-radius:var(--radius-sm);padding:14px">
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--brand);margin-bottom:8px"><i class="fas fa-building me-2"></i>Supplier Info</div>
                <div style="font-size:.85rem;color:var(--text-mid)"><strong>Name:</strong> ${c.supplier_name}</div>
                <div style="font-size:.85rem;color:var(--text-mid);margin-top:4px"><strong>Party No:</strong> ${c.party_no||'—'}</div>
            </div>
            <div style="background:var(--brand-light);border-left:3px solid var(--brand);border-radius:var(--radius-sm);padding:14px">
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--brand);margin-bottom:8px"><i class="fas fa-file-alt me-2"></i>Challan Details</div>
                <div style="font-size:.85rem;color:var(--text-mid)"><strong>Challan No:</strong> ${c.challan_no}</div>
                <div style="font-size:.85rem;color:var(--text-mid);margin-top:4px"><strong>Date:</strong> ${new Date(c.challan_date).toLocaleDateString('en-IN')}</div>
                <div style="font-size:.85rem;color:var(--text-mid);margin-top:4px"><strong>Invoice No:</strong> ${c.invoice_no||'—'}</div>
            </div>
        </div>
        <div style="overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--border)">
            <table style="width:100%;border-collapse:collapse;min-width:550px">
                <thead><tr style="background:linear-gradient(135deg,var(--brand-dark),var(--brand))"><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase">Product</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase">Batch</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase">Expiry</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;text-align:center">Qty</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;text-align:right">Unit Price</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;text-align:center">GST%</th><th style="color:#fff;padding:11px 10px;font-size:.7rem;font-weight:700;text-transform:uppercase;text-align:right">Amount</th></tr></thead>
                <tbody style="font-size:.85rem">${rows}</tbody>
                <tfoot><tr style="background:var(--brand-light)"><td colspan="6" style="text-align:right;padding:11px 10px;font-weight:700;color:var(--brand)">Total Amount</td><td style="text-align:right;padding:11px 10px;font-weight:800;color:var(--brand);font-size:1rem">₹${total.toFixed(2)}</td></tr></tfoot>
            </table>
        </div>
        ${c.additional_notes?`<div style="margin-top:16px;background:var(--info-bg);border-radius:var(--radius-sm);padding:12px 16px;font-size:.85rem;color:var(--info)"><i class="fas fa-sticky-note me-2"></i>${c.additional_notes}</div>`:''}
        <div class="no-print" style="display:flex;gap:10px;margin-top:18px;justify-content:center">
            <button class="btn-brand" onclick="printChallan(${c.id})"><i class="fas fa-print"></i> Print</button>
            <a href="export_challan_csv.php?challan_id=${c.id}" class="btn-brand" style="text-decoration:none"><i class="fas fa-file-csv"></i> Export CSV</a>
        </div>
    </div>`);
}

function filterChallanTable() {
    const q = ($('#challanSearchInput').val() || '').toLowerCase().trim();
    let vis = 0;
    $('#challansTable tbody tr').each(function () {
        const d = ($(this).data('search') || '').toString().toLowerCase();
        const show = !q || d.includes(q);
        $(this).toggle(show);
        if (show) vis++;
    });
    $('#challanCount').text(vis);
}
</script>
</body>
</html>