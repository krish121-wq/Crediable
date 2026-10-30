<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['sidebar_open'])) $_SESSION['sidebar_open'] = true;
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=purchase-stock.php"); exit();
}

$page_title = "Purchase Entries";
$success_message = '';
$error_message   = '';

if (isset($_GET['success']) && $_GET['success'] == '1')
    $success_message = "Purchase entry updated successfully.";

$records_per_page = 10;
$page       = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $total_records = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM purchases"))['total'];
    $result        = mysqli_query($conn, "SELECT id, party_no, name, date, entry_no, net_amount FROM purchases ORDER BY created_at DESC LIMIT $start_from, $records_per_page");
    $total_pages   = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    $result = null; $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?> — Grow Biller</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
:root{
    --brand:#571F5A;--brand-dark:#3d1240;--brand-mid:#6e2a73;--brand-light:#f3e8f5;
    --success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--danger-bg:#fee2e2;
    --warning:#d97706;--warning-bg:#fef3c7;--info:#0284c7;--info-bg:#e0f2fe;
    --bg:#f5f4f8;--card:#ffffff;--border:#e4e2ea;--text:#1a1625;--text-mid:#4b4565;--text-muted:#9590a8;
    --radius-sm:8px;--radius-md:14px;--radius-lg:20px;
    --shadow-sm:0 2px 8px rgba(87,31,90,.07);--shadow-md:0 8px 28px rgba(87,31,90,.11);--shadow-lg:0 16px 48px rgba(87,31,90,.16);
    --transition:all .25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
.page-wrap{margin-top:70px;margin-left:260px;padding:28px 24px 60px;transition:var(--transition)}
@media(max-width:992px){.page-wrap{margin-left:0;padding:18px 14px 50px}}

.page-hero{background:linear-gradient(135deg,var(--brand) 0%,var(--brand-mid) 55%,#9b3fa0 100%);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06)}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:#fff}
.hero-sub{color:rgba(255,255,255,.75);font-size:.85rem;margin-top:3px}
.hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:relative;z-index:1}

.btn-hero{color:#fff;border:2px solid rgba(255,255,255,.5);padding:9px 20px;border-radius:50px;font-size:.83rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);text-decoration:none;white-space:nowrap;background:rgba(255,255,255,.15)}
.btn-hero:hover{background:rgba(255,255,255,.28);border-color:#fff;color:#fff;transform:translateY(-1px)}
.btn-hero.solid{background:rgba(255,255,255,.9);color:var(--brand);border-color:#fff}
.btn-hero.solid:hover{background:#fff;color:var(--brand)}

.alert-top{border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:500;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5}

.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden}
.table-scroll{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;padding:13px 14px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;text-align:left;border:none}
.data-table tbody tr{border-bottom:1px solid #f0eef6;transition:background .18s;cursor:pointer}
.data-table tbody tr:last-child{border-bottom:none}
.data-table tbody tr:hover{background:#faf9fc}
.data-table td{padding:14px 14px;vertical-align:middle;font-size:.88rem}

.bill-num{font-family:monospace;font-size:.82rem;background:var(--brand-light);color:var(--brand);border-radius:6px;padding:4px 9px;font-weight:700;white-space:nowrap}
.btn-del{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--danger),#b91c1c);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;transition:var(--transition);box-shadow:0 3px 8px rgba(239,68,68,.25)}
.btn-del:hover{transform:scale(1.1)}

/* PAGINATION */
.pagination-wrap{display:flex;align-items:center;justify-content:center;gap:6px;padding:16px;flex-wrap:wrap}
.pg-btn{padding:7px 13px;border-radius:8px;border:1.5px solid var(--border);background:#fafafa;color:var(--text-mid);font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:var(--transition)}
.pg-btn:hover,.pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand)}

/* MODAL */
.modal-content{border-radius:var(--radius-md);border:none;box-shadow:var(--shadow-lg);overflow:hidden}
.modal-hdr{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;padding:18px 22px;display:flex;align-items:center;justify-content:space-between}
.modal-hdr h5{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;margin:0}
.modal-body{padding:22px;max-height:72vh;overflow-y:auto}
.modal-footer{border-top:1px solid var(--border);padding:14px 22px}

.f-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-mid);margin-bottom:5px;display:block}
.f-control,.f-select{border-radius:var(--radius-sm);border:1.5px solid var(--border);padding:9px 12px;font-size:.88rem;background:#fafafa;transition:var(--transition);width:100%;color:var(--text);font-family:'Inter',sans-serif}
.f-control:focus,.f-select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(87,31,90,.1);outline:none;background:#fff}
.f-control[readonly]{background:#f3f4f6;color:var(--text-muted)}
.f-group{margin-bottom:14px}

.items-wrap{overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--border);margin-top:10px}
.items-table{width:100%;border-collapse:collapse;font-size:.82rem;min-width:650px}
.items-table thead th{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;padding:10px 8px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;text-align:center;border:none}
.items-table tbody tr{border-bottom:1px solid #f0eef6}
.items-table tbody tr:hover{background:#faf9fc}
.items-table td{padding:7px 6px;vertical-align:middle}
.items-table input,.items-table select{width:100%;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:.8rem;transition:var(--transition);background:#fafafa}
.items-table input:focus,.items-table select:focus{border-color:var(--brand);outline:none;background:#fff}
.items-table input[readonly]{background:#f3f4f6;color:var(--text-muted)}

.section-label{font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:.5px;margin:18px 0 10px;display:flex;align-items:center;gap:8px}
.section-label::after{content:'';flex:1;height:1px;background:linear-gradient(to right,rgba(87,31,90,.2),transparent)}

.totals-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px}
@media(max-width:600px){.totals-grid{grid-template-columns:repeat(2,1fr)}}
.total-item{background:var(--brand-light);border:1px solid rgba(87,31,90,.15);border-radius:var(--radius-sm);padding:12px}
.total-label{font-size:.68rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px}
.total-value{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--brand)}

.btn-brand{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border:none;padding:10px 22px;border-radius:50px;font-size:.85rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);box-shadow:0 4px 14px rgba(87,31,90,.28)}
.btn-brand:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(87,31,90,.38)}
.btn-edit-toggle{background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;border:none;padding:10px 22px;border-radius:50px;font-size:.85rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition)}
.btn-edit-toggle:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(14,165,233,.3)}
.btn-remove{background:linear-gradient(135deg,var(--danger),#b91c1c);color:#fff;border:none;padding:5px 9px;border-radius:6px;font-size:.75rem;cursor:pointer;transition:var(--transition);display:none}
.btn-remove:hover{transform:scale(1.08)}
.btn-add-row-sm{background:var(--brand-light);border:1.5px dashed rgba(87,31,90,.35);color:var(--brand);padding:7px 16px;border-radius:8px;font-size:.8rem;font-weight:700;cursor:pointer;display:none;align-items:center;gap:6px;transition:var(--transition);margin-top:10px}
.btn-add-row-sm:hover{background:rgba(87,31,90,.1)}

.no-data{text-align:center;padding:60px 20px;color:var(--text-muted)}
.no-data-icon{font-size:2.5rem;opacity:.3;margin-bottom:12px}

.select2-container--default .select2-selection--single{height:36px!important;border:1.5px solid var(--border)!important;border-radius:6px!important;background:#fafafa!important}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:34px!important;font-size:.83rem;padding-left:10px}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:34px!important}
.select2-container--default .select2-results__option--highlighted[aria-selected]{background:var(--brand)!important}
.select2-dropdown{border:1.5px solid var(--border)!important;border-radius:8px!important;box-shadow:var(--shadow-md)!important}

@media(max-width:640px){
.items-table thead{display:none}
.items-table tr{display:block;margin-bottom:12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px;background:var(--card)}
.items-table td{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0eef6;font-size:.8rem}
.items-table td:last-child{border-bottom:none}
.items-table td::before{content:attr(data-label);font-weight:700;font-size:.68rem;color:var(--brand);text-transform:uppercase;min-width:40%;flex-shrink:0}
}
</style>
</head>
<body>
<?php include('layout.php'); ?>

<div class="page-wrap">
    <!-- HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-boxes me-2" style="opacity:.85"></i>Purchase Entries</div>
            <div class="hero-sub">View and manage all supplier purchase orders</div>
        </div>
        <div class="hero-actions">
            <a href="purchase_entry.php" class="btn-hero solid"><i class="fas fa-plus-circle"></i> Add Purchase</a>
            <a href="master_list.php"    class="btn-hero"><i class="fas fa-box"></i> Add Item</a>
            <button class="btn-hero" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="fas fa-question-circle"></i> Help</button>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert-top success" id="successAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- TABLE CARD -->
    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table" id="mainTable">
                <thead>
                    <tr>
                        <th>Party No.</th>
                        <th>Purchase Bill ID</th>
                        <th>Supplier Name</th>
                        <th>Date</th>
                        <th>Net Amount</th>
                        <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff'): ?>
                        <th style="text-align:center">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($result && mysqli_num_rows($result) > 0):
                    while ($row = mysqli_fetch_assoc($result)):
                        $rid = (int)$row['id'];
                ?>
                <tr data-id="<?php echo $rid; ?>">
                    <td style="font-weight:600;color:var(--text-mid)"><?php echo htmlspecialchars($row['party_no']); ?></td>
                    <td><span class="bill-num"><?php echo htmlspecialchars($row['entry_no']); ?></span></td>
                    <td style="font-weight:500"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td style="color:var(--text-muted);font-size:.82rem"><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                    <td style="font-weight:700;color:var(--brand)">₹<?php echo number_format($row['net_amount'], 2); ?></td>
                    <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff'): ?>
                    <td style="text-align:center">
                        <button class="btn-del delete-purchase" data-id="<?php echo $rid; ?>" onclick="event.stopPropagation()"><i class="fas fa-trash"></i></button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="6">
                        <div class="no-data"><div class="no-data-icon"><i class="fas fa-boxes"></i></div><div style="font-weight:600;margin-bottom:6px">No purchase entries yet</div><div style="font-size:.82rem">Click <strong>Add Purchase</strong> to get started.</div></div>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>" class="pg-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="pg-btn <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>" class="pg-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- VIEW/EDIT MODAL -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-hdr">
                <h5><i class="fas fa-file-invoice me-2"></i>Purchase Entry Details</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editPurchaseForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" id="view_id">

                    <div class="section-label"><i class="fas fa-building"></i> Supplier Info</div>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="f-group"><label class="f-label">Supplier Name <span style="color:var(--danger)">*</span></label><input type="text" name="name" id="view_name" class="f-control" required readonly></div></div>
                        <div class="col-md-4"><div class="f-group"><label class="f-label">Purchase Bill ID</label><input type="text" name="entry_no" id="view_entry_no" class="f-control" readonly></div></div>
                        <div class="col-md-4"><div class="f-group"><label class="f-label">Party No <span style="color:var(--danger)">*</span></label><input type="text" name="party_no" id="view_party_no" class="f-control" required readonly></div></div>
                        <div class="col-md-3"><div class="f-group"><label class="f-label">Date <span style="color:var(--danger)">*</span></label><input type="date" name="date" id="view_date" class="f-control" required readonly></div></div>
                        <div class="col-md-3"><div class="f-group"><label class="f-label">Bill Date</label><input type="date" name="bill_date" id="view_bill_date" class="f-control" readonly></div></div>
                        <div class="col-md-3"><div class="f-group"><label class="f-label">Supplier GSTIN</label><input type="text" name="supplier_gstin" id="view_supplier_gstin" class="f-control" readonly></div></div>
                        <div class="col-md-3"><div class="f-group"><label class="f-label">Recipient GSTIN</label><input type="text" name="recipient_gstin" id="view_recipient_gstin" class="f-control" readonly></div></div>
                        <div class="col-md-4"><div class="f-group"><label class="f-label">Type <span style="color:var(--danger)">*</span></label><select name="type" id="view_type" class="f-select" required disabled><option value="Cash">Cash</option><option value="Credit">Credit</option><option value="Online">Online</option></select></div></div>
                    </div>

                    <div class="section-label" style="margin-top:20px"><i class="fas fa-boxes"></i> Items</div>
                    <div class="items-wrap">
                        <table class="items-table">
                            <thead><tr><th>Product</th><th>HSN</th><th>Price</th><th>Qty</th><th>Disc%</th><th>GST%</th><th>Amount</th><th></th></tr></thead>
                            <tbody id="view_items"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-add-row-sm" id="addRowButton"><i class="fas fa-plus"></i> Add Row</button>

                    <div class="section-label" style="margin-top:16px"><i class="fas fa-calculator"></i> Bill Summary</div>
                    <div class="totals-grid">
                        <div class="total-item"><div class="total-label">Gross Amount</div><input type="number" step="0.01" name="gross_amount" id="view_gross_amount" class="f-control" readonly style="margin-top:4px"></div>
                        <div class="total-item"><div class="total-label">Total Discount</div><input type="number" step="0.01" name="total_discount" id="view_total_discount" class="f-control" readonly style="margin-top:4px"></div>
                        <div class="total-item"><div class="total-label">Round Off</div><input type="number" step="0.01" name="round_off" id="view_round_off" class="f-control" readonly style="margin-top:4px"></div>
                        <div class="total-item" style="background:linear-gradient(135deg,var(--brand-light),#ede0f5)"><div class="total-label" style="color:var(--brand)">Net Amount</div><input type="number" step="0.01" name="net_amount" id="view_net_amount" class="f-control" readonly style="margin-top:4px;font-weight:800;color:var(--brand)"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="gap:10px">
                <button class="btn-edit-toggle" id="editButton"><i class="fas fa-pen me-1"></i> Edit</button>
                <button class="btn-brand" id="printBarcodeButton"><i class="fas fa-barcode"></i> Print Barcode</button>
                <button class="btn-brand" style="background:linear-gradient(135deg,#6b7280,#4b5563)" data-bs-dismiss="modal"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    </div>
</div>

<!-- SUCCESS MODAL -->
<div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-hdr"><h5><i class="fas fa-check-circle me-2"></i>Updated!</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" style="text-align:center;padding:28px">
                <div style="width:60px;height:60px;background:var(--success-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.6rem;color:var(--success)"><i class="fas fa-check"></i></div>
                <p style="font-weight:600">Purchase entry updated successfully!</p>
            </div>
            <div class="modal-footer" style="justify-content:center"><button class="btn-brand" id="successOkBtn">OK</button></div>
        </div>
    </div>
</div>

<!-- HELP MODAL -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-hdr"><h5><i class="fas fa-question-circle me-2"></i>Help</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" style="padding:22px">
                <ul style="list-style:none;padding:0;margin:0">
                    <?php foreach([
                        ['Click any row','Opens purchase details in a modal viewer'],
                        ['Edit button','Unlocks all fields for editing'],
                        ['Save button','Saves changes to the purchase entry'],
                        ['Add Row','Add new product rows in edit mode'],
                        ['Delete button','Permanently removes the purchase entry'],
                    ] as $h): ?>
                    <li style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)">
                        <div style="width:28px;height:28px;background:var(--brand-light);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-arrow-right" style="color:var(--brand);font-size:.7rem"></i></div>
                        <div><div style="font-weight:700;font-size:.85rem"><?php echo $h[0]; ?></div><div style="font-size:.78rem;color:var(--text-muted);margin-top:2px"><?php echo $h[1]; ?></div></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="modal-footer"><button class="btn-brand" data-bs-dismiss="modal">Got it!</button></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
    // Auto-hide success
    var sa = document.getElementById('successAlert');
    if (sa) setTimeout(()=>{sa.style.transition='opacity .5s';sa.style.opacity='0';setTimeout(()=>sa.remove(),500);},4000);

    // Select2 init for modal product rows
    function initSelect2(el){
        try {
            el.select2({
                placeholder:'Select Product',allowClear:true,width:'100%',dropdownParent:$('#viewModal'),
                ajax:{url:'fetch-products.php',dataType:'json',type:'GET',delay:250,
                    data:p=>({term:p.term||''}),
                    processResults:data=>({results:data.map(i=>({id:i.id,text:i.product_name,hsn_code:i.hsn_code,purchase_price:parseFloat(i.purchase_price||0),gst_percentage:parseFloat(i.gst_percentage||i.gst_rate||0)}))}),
                    cache:true},minimumInputLength:0});
        } catch(e){ console.error('Select2 init error',e); }
    }

    // Row click → open view modal
    $(document).on('click','#mainTable tbody tr',function(){
        var id=$(this).data('id');
        if(!id||isNaN(id)) return;
        $.ajax({url:'ajax_purchase.php',method:'POST',data:{view_id:id,csrf_token:'<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'},dataType:'json',
            success:function(r){
                if(r.error){alert('Error: '+r.error);return;}
                $('#view_id').val(r.id||'');
                $('#view_party_no').val(r.party_no||'');
                $('#view_name').val(r.name||'');
                $('#view_date').val(r.date||'');
                $('#view_entry_no').val(r.entry_no||'');
                $('#view_type').val(r.type||'');
                $('#view_bill_date').val(r.bill_date||'');
                $('#view_supplier_gstin').val(r.supplier_gstin||'');
                $('#view_recipient_gstin').val(r.recipient_gstin||'');
                $('#view_gross_amount').val(parseFloat(r.gross_amount||0).toFixed(2));
                $('#view_total_discount').val(parseFloat(r.total_discount||0).toFixed(2));
                $('#view_round_off').val(parseFloat(r.round_off||0).toFixed(2));
                $('#view_net_amount').val(parseFloat(r.net_amount||0).toFixed(2));
                var html='';
                (r.items||[]).forEach((it,ix)=>{
                    html+=`<tr>
                        <td data-label="Product"><select name="product_id[]" class="product-select" data-index="${ix}" disabled><option value="${it.product_id}" selected>${it.product_name||''}</option></select></td>
                        <td data-label="HSN"><input type="text" name="hsn_code[]" value="${it.hsn_code||''}" readonly></td>
                        <td data-label="Price"><input type="number" step="0.01" name="purchase_price[]" value="${parseFloat(it.purchase_price||0).toFixed(2)}" readonly></td>
                        <td data-label="Qty"><input type="number" name="qty[]" class="quantity" value="${it.quantity||0}" data-original="${it.quantity||0}" readonly></td>
                        <td data-label="Disc%"><input type="number" step="0.01" name="discount[]" class="discount" value="${parseFloat(it.discount||0).toFixed(2)}" readonly></td>
                        <td data-label="GST%"><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" value="${parseFloat(it.gst_percentage||it.gst_rate||0).toFixed(2)}" readonly></td>
                        <td data-label="Amount"><input type="number" step="0.01" name="amount[]" class="amount" value="${parseFloat(it.amount||0).toFixed(2)}" readonly></td>
                        <td><button type="button" class="btn-remove"><i class="fas fa-trash"></i></button></td>
                    </tr>`;
                });
                $('#view_items').html(html);
                $('.product-select').each(function(){initSelect2($(this));});
                // Reset to view mode
                $('#editButton').html('<i class="fas fa-pen me-1"></i> Edit').css('background','linear-gradient(135deg,#0ea5e9,#0284c7)');
                $('#addRowButton').hide();
                $('.btn-remove').hide();
                $('#editPurchaseForm input,#editPurchaseForm select').prop('readonly',true).prop('disabled',true);
                new bootstrap.Modal(document.getElementById('viewModal'),{backdrop:'static'}).show();
            },
            error:function(){ alert('Error fetching purchase details.'); }
        });
    });

    $(document).on('click','.remove-row,.btn-del,.btn-close',function(e){e.stopPropagation();});

    // Edit / Save toggle
    $('#editButton').on('click',function(){
        if ($(this).html().includes('Edit')) {
            // Switch to edit mode
            $('#editPurchaseForm input').not('[name="entry_no"]').prop('readonly',false).prop('disabled',false);
            $('#editPurchaseForm select').prop('readonly',false).prop('disabled',false);
            $('.gst-rate').prop('readonly',true);
            $('.product-select').each(function(){$(this).prop('disabled',false);$(this).select2('destroy');initSelect2($(this));});
            $('.btn-remove').show();
            $('#addRowButton').css('display','inline-flex');
            $(this).html('<i class="fas fa-save me-1"></i> Save').css('background','linear-gradient(135deg,var(--success),#15803d)');
        } else {
            // Validate & save
            var ok=true;
            $('#editPurchaseForm input[required],#editPurchaseForm select[required]').each(function(){
                if(!$(this).val()){ok=false;$(this).css('border-color','var(--danger)');}
                else $(this).css('border-color','var(--border)');
            });
            if ($('#view_items .product-select').filter(function(){return $(this).val();}).length===0){ok=false;alert('At least one product must be selected.');}
            if(!ok) return;
            $.ajax({url:'ajax_purchase.php',method:'POST',data:$('#editPurchaseForm').serializeArray().concat([{name:'edit_purchase',value:true}]),dataType:'json',
                success:function(r){
                    if(r.error){alert('Error: '+r.error);return;}
                    bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                    setTimeout(()=>{window.location.href='purchase-stock.php?success=1';},1200);
                },error:function(){alert('Error updating purchase entry.');}
            });
        }
    });

    // Product select change in edit modal
    $('#view_items').on('change','.product-select',function(){
        var row=$(this).closest('tr');
        var sel=$(this).select2('data')[0];
        if(sel&&sel.id){
            row.find('[name="hsn_code[]"]').val(sel.hsn_code||'');
            row.find('[name="purchase_price[]"]').val(parseFloat(sel.purchase_price||0).toFixed(2));
            row.find('[name="gst_rate[]"]').val(parseFloat(sel.gst_percentage||0).toFixed(2));
            if(!row.find('.quantity').val()||row.find('.quantity').val()=='0') row.find('.quantity').val('1');
        }
        calcAmount(row); calcFooter();
    });

    $('#addRowButton').on('click',function(){
        var row=$('<tr><td data-label="Product"><select name="product_id[]" class="product-select"></select></td><td data-label="HSN"><input type="text" name="hsn_code[]" readonly></td><td data-label="Price"><input type="number" step="0.01" name="purchase_price[]" readonly></td><td data-label="Qty"><input type="number" name="qty[]" class="quantity" value="0"></td><td data-label="Disc%"><input type="number" step="0.01" name="discount[]" class="discount" value="0"></td><td data-label="GST%"><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" readonly></td><td data-label="Amount"><input type="number" step="0.01" name="amount[]" class="amount" value="0" readonly></td><td><button type="button" class="btn-remove" style="display:block"><i class="fas fa-trash"></i></button></td></tr>');
        $('#view_items').append(row);
        initSelect2(row.find('.product-select'));
        calcFooter();
    });

    $('#view_items').on('click','.btn-remove',function(){
        if($('#view_items tr').length>1){$(this).closest('tr').find('.product-select').select2('destroy');$(this).closest('tr').remove();calcFooter();}
        else alert('At least one item is required.');
    });

    function calcAmount(row){
        var price=parseFloat(row.find('[name="purchase_price[]"]').val())||0;
        var qty=parseFloat(row.find('.quantity').val())||0;
        var disc=parseFloat(row.find('.discount').val())||0;
        var amt=(qty*price)*(1-disc/100);
        row.find('.amount').val(amt.toFixed(2));
        return amt;
    }

    function calcFooter(){
        var gross=0,disc=0,inc=0;
        $('#view_items tr').each(function(){
            var row=$(this);
            var price=parseFloat(row.find('[name="purchase_price[]"]').val())||0;
            var qty=parseFloat(row.find('.quantity').val())||0;
            var d=parseFloat(row.find('.discount').val())||0;
            var gst=parseFloat(row.find('.gst-rate').val())||0;
            var amt=calcAmount(row);
            inc+=amt;
            disc+=qty*price*d/100;
            gross+=gst>0?amt/(1+gst/100):amt;
        });
        var net=Math.round(inc),ro=net-inc;
        $('#view_gross_amount').val(gross.toFixed(2));
        $('#view_total_discount').val(disc.toFixed(2));
        $('#view_round_off').val(ro.toFixed(2));
        $('#view_net_amount').val(net.toFixed(2));
    }

    $('#view_items').on('input change','.quantity,.discount',function(){
        calcAmount($(this).closest('tr')); calcFooter();
    });

    // Delete
    $(document).on('click','.delete-purchase',function(e){
        e.stopPropagation();
        if(!confirm('Delete this purchase entry? This action cannot be undone.')) return;
        var id=$(this).data('id');
        var $row=$(this).closest('tr');
        $.ajax({url:'delete-purchase.php',type:'POST',data:{id:id,csrf_token:'<?php echo $_SESSION['csrf_token']; ?>'},dataType:'json',
            success:function(r){
                if(r.success) $row.fadeOut(350,function(){$(this).remove();});
                else alert('Error: '+(r.error||'Unknown error'));
            },error:function(){alert('Delete failed. Please try again.');}
        });
    });

    $('#successOkBtn').on('click',function(){window.location.href='purchase-stock.php';});
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>