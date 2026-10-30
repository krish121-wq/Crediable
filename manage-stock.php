<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure columns exist
try { $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS quantity_alert INT DEFAULT 5"); } catch (Exception $e) {}

$user_id   = (int)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'admin';
$page_title = "Manage Stock";

$success_msg = "";
$error_msg   = "";

// Handle quick stock update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    try {
        $id             = (int)($_POST['id'] ?? 0);
        $quantity       = (int)($_POST['quantity'] ?? 0);
        $quantity_alert = (int)($_POST['quantity_alert'] ?? 5);

        if ($id <= 0) throw new Exception("Invalid product selected.");
        if ($quantity < 0) throw new Exception("Quantity cannot be negative.");
        if ($quantity_alert < 0) $quantity_alert = 0;

        $stmt = $conn->prepare("UPDATE products SET quantity = ?, quantity_alert = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("iii", $quantity, $quantity_alert, $id);
        if (!$stmt->execute()) throw new Exception("Database update failed: " . $stmt->error);
        $stmt->close();

        $success_msg = "Stock updated successfully!";
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}

// Fetch all inventory products
$where = ($user_type === 'admin') ? "1=1" : "(p.created_by = $user_id OR p.created_by IS NULL OR p.created_by = 0)";
$query = "SELECT p.id, p.product_name, p.product_code, p.unit, p.quantity, 
                 COALESCE(p.quantity_alert, 5) AS quantity_alert, 
                 p.sell_price, p.purchase_price, 
                 c.category_name, b.brand_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN brands b ON p.brand_id = b.id 
          WHERE $where 
          ORDER BY p.product_name ASC";

$result = $conn->query($query);
$products = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

$total_products = count($products);
$total_units    = array_sum(array_column($products, 'quantity'));
$low_stock_cnt  = 0;
$out_stock_cnt  = 0;
foreach ($products as $p) {
    $q = (int)$p['quantity'];
    $qa = (int)$p['quantity_alert'];
    if ($q == 0) $out_stock_cnt++;
    elseif ($q <= $qa) $low_stock_cnt++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Stock – Credible</title>
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
    --bg: #f4f8fb;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #0b1220;
    --text-mid: #475569;
    --text-muted: #94a3b8;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --shadow-sm: 0 2px 8px rgba(2,132,199,.06);
    --shadow-md: 0 8px 24px rgba(2,132,199,.10);
    --shadow-lg: 0 16px 48px rgba(2,132,199,.14);
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

/* Hero Banner */
.page-hero {
    background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
    border-radius: var(--radius-lg);
    padding: 20px 26px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; margin-bottom: 20px;
    box-shadow: var(--shadow-lg); position: relative; overflow: hidden; flex-wrap: wrap;
}
.hero-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.25rem; color: #fff; display: flex; align-items: center; }
.hero-sub { color: rgba(255,255,255,.82); font-size: .8rem; margin-top: 2px; }
.hero-actions { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }

.btn-hero {
    background: rgba(255, 255, 255, 0.16);
    backdrop-filter: blur(8px);
    color: #fff !important;
    border: 1.5px solid rgba(255, 255, 255, 0.45);
    padding: 8px 16px;
    border-radius: 50px;
    font-size: .82rem;
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
.btn-hero:hover { background: rgba(255, 255, 255, 0.28); border-color: #fff; transform: translateY(-2px); }

/* Stats Row */
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
.stat-card {
    background: var(--card); border-radius: var(--radius-md);
    border: 1px solid var(--border); padding: 14px 18px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: var(--shadow-sm); transition: var(--t);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; flex-shrink: 0;
}
.stat-icon.blue   { background: var(--brand-light); color: var(--brand); }
.stat-icon.green  { background: var(--success-bg); color: var(--success); }
.stat-icon.amber  { background: var(--warning-bg); color: var(--warning); }
.stat-icon.red    { background: var(--danger-bg);  color: var(--danger); }
.stat-val   { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: .72rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: .4px; margin-top: 3px; }

/* Toolbar */
.toolbar {
    background: var(--card);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    box-shadow: var(--shadow-sm); flex-wrap: wrap;
}
.search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 400px; }
.search-wrap .si { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .82rem; }
.search-input {
    width: 100%; padding: 8px 32px 8px 34px;
    border: 1.5px solid var(--border); border-radius: 50px;
    font-size: .85rem; background: #fafafa; color: var(--text);
    outline: none; transition: var(--t);
}
.search-input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px rgba(2,132,199,.12); }
.export-btns { display: flex; gap: 8px; }
.btn-export {
    padding: 7px 14px; border-radius: 50px; font-size: .78rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--border);
    background: #fff; color: var(--text-mid); cursor: pointer; transition: var(--t); text-decoration: none;
}
.btn-export:hover { border-color: var(--brand); color: var(--brand); transform: translateY(-1px); }

/* Table */
.table-card {
    background: var(--card); border-radius: var(--radius-md);
    border: 1px solid var(--border); box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead th {
    background: linear-gradient(135deg, var(--brand), var(--brand-mid));
    color: #fff; padding: 11px 14px;
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    white-space: nowrap; text-align: left; border: none;
}
.data-table thead th.center { text-align: center; }
.data-table tbody tr { border-bottom: 1px solid #f0eef6; transition: background .15s; }
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #faf9fc; }
.data-table td { padding: 11px 14px; vertical-align: middle; font-size: .85rem; }
.data-table td.center { text-align: center; }

/* Badges */
.badge-stock {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 50px; font-size: .72rem; font-weight: 700;
}
.badge-stock.in { background: var(--success-bg); color: var(--success); }
.badge-stock.low { background: var(--warning-bg); color: var(--warning); }
.badge-stock.out { background: var(--danger-bg); color: var(--danger); }

.code-chip {
    font-family: monospace; font-size: .78rem;
    background: #f1f5f9; border: 1px solid var(--border);
    border-radius: 5px; padding: 2px 7px; color: var(--text-mid);
}

.btn-edit-qty {
    background: linear-gradient(135deg, var(--brand), var(--brand-mid));
    color: #fff; border: none; border-radius: 8px;
    padding: 6px 12px; font-size: .78rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 5px; cursor: pointer;
    transition: var(--t); text-decoration: none;
}
.btn-edit-qty:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(2,132,199,.25); color: #fff; }

/* Modal */
.modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); overflow: hidden; }
.modal-header { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); color: #fff; padding: 14px 20px; }
.modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; font-size: 1rem; color: #fff; }
</style>
</head>
<body>

<?php include('layout.php'); ?>

<div class="page-wrap">

    <!-- PAGE HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-boxes-stacked me-2" style="opacity:.85"></i>Manage Stock</div>
            <div class="hero-sub">Live inventory tracking, quantity audit &amp; stock alert limits</div>
        </div>
        <div class="hero-actions">
            <a href="stock_adjustment.php" class="btn-hero"><i class="fas fa-sliders me-1"></i> Adjust Stock</a>
            <a href="master_list.php" class="btn-hero"><i class="fas fa-plus-circle me-1"></i> Add Product</a>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- STATS CARDS -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_products) ?></div>
                <div class="stat-label">Total Products</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-cubes"></i></div>
            <div>
                <div class="stat-val"><?= number_format($total_units) ?></div>
                <div class="stat-label">Total Units</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <div class="stat-val"><?= number_format($low_stock_cnt) ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-circle-xmark"></i></div>
            <div>
                <div class="stat-val"><?= number_format($out_stock_cnt) ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="search-wrap">
            <i class="fas fa-search si"></i>
            <input type="text" id="stockSearchInput" class="search-input" placeholder="Search product name, code, category…" autocomplete="off" onkeyup="filterStockTable()">
        </div>
        <div class="export-btns">
            <button type="button" class="btn-export" onclick="exportStockExcel()"><i class="fas fa-file-excel text-success"></i> Export Excel</button>
            <button type="button" class="btn-export" onclick="window.print()"><i class="fas fa-print text-primary"></i> Print Report</button>
        </div>
    </div>

    <!-- DATA TABLE -->
    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table" id="stockTable">
                <thead>
                    <tr>
                        <th>Product Details</th>
                        <th>Product Code</th>
                        <th>Category</th>
                        <th class="center">Current Stock</th>
                        <th class="center">Alert Limit</th>
                        <th class="center">Stock Status</th>
                        <th class="center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fs-2 mb-2 d-block opacity-25"></i>
                                No products found in inventory.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): 
                            $qty = (int)$p['quantity'];
                            $alert = (int)$p['quantity_alert'];
                            $status_class = ($qty == 0) ? 'out' : (($qty <= $alert) ? 'low' : 'in');
                            $status_text  = ($qty == 0) ? 'Out of Stock' : (($qty <= $alert) ? 'Low Stock' : 'In Stock');
                            $unit = $p['unit'] ?: 'Piece';
                        ?>
                        <tr data-search="<?= strtolower(htmlspecialchars($p['product_name'].' '.$p['product_code'].' '.($p['category_name']??''))) ?>">
                            <td>
                                <div style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($p['product_name']) ?></div>
                                <div style="font-size: .75rem; color: var(--text-muted);">Sell Price: ₹<?= number_format((float)$p['sell_price'], 2) ?></div>
                            </td>
                            <td><span class="code-chip"><?= htmlspecialchars($p['product_code'] ?: '—') ?></span></td>
                            <td><?= htmlspecialchars($p['category_name'] ?: 'Uncategorized') ?></td>
                            <td class="center">
                                <span style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1rem; color: var(--brand);">
                                    <?= $qty ?>
                                </span>
                                <span style="font-size: .72rem; color: var(--text-muted); margin-left: 2px;"><?= htmlspecialchars($unit) ?></span>
                            </td>
                            <td class="center" style="color: var(--text-mid); font-weight: 600;">
                                ≤ <?= $alert ?> <?= htmlspecialchars($unit) ?>
                            </td>
                            <td class="center">
                                <span class="badge-stock <?= $status_class ?>">
                                    <i class="fas <?= $qty == 0 ? 'fa-circle-xmark' : ($qty <= $alert ? 'fa-triangle-exclamation' : 'fa-circle-check') ?>"></i>
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td class="center">
                                <button type="button" class="btn-edit-qty" onclick='openEditModal(<?= json_encode($p) ?>)'>
                                    <i class="fas fa-edit"></i> Edit Qty
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- EDIT STOCK MODAL -->
<div class="modal fade" id="editStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-boxes-stacked me-2"></i>Update Product Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="manage-stock.php" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="modalProductId">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted text-uppercase fw-bold" style="font-size:.7rem;">Product</label>
                        <input type="text" class="form-control" id="modalProductName" readonly style="background:#f8fafc; font-weight:600;">
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label text-muted text-uppercase fw-bold" style="font-size:.7rem;">Current Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="modalQuantity" class="form-control" min="0" required style="font-weight:700; font-size:1.1rem; color:var(--brand);">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted text-uppercase fw-bold" style="font-size:.7rem;">Low Stock Alert Limit <span class="text-danger">*</span></label>
                            <input type="number" name="quantity_alert" id="modalQuantityAlert" class="form-control" min="0" required style="font-weight:600;">
                        </div>
                    </div>
                    <div class="mt-2 text-muted" style="font-size:.75rem;">
                        <i class="fas fa-info-circle me-1 text-primary"></i>Stock will be instantly updated across sales and billing.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_product" class="btn btn-primary" style="background:var(--brand); border-color:var(--brand); font-weight:700; border-radius:50px; padding:7px 22px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openEditModal(prod) {
    $('#modalProductId').val(prod.id);
    $('#modalProductName').val(prod.product_name + (prod.product_code ? ' (' + prod.product_code + ')' : ''));
    $('#modalQuantity').val(prod.quantity || 0);
    $('#modalQuantityAlert').val(prod.quantity_alert || 5);
    new bootstrap.Modal(document.getElementById('editStockModal')).show();
}

function filterStockTable() {
    const q = $('#stockSearchInput').val().toLowerCase().trim();
    $('#stockTable tbody tr').each(function () {
        const d = ($(this).data('search') || '').toString().toLowerCase();
        $(this).toggle(!q || d.includes(q));
    });
}

function exportStockExcel() {
    let csv = "Product Name,Product Code,Category,Quantity,Alert Limit,Sell Price\n";
    <?php foreach ($products as $p): ?>
        csv += `"<?= addslashes($p['product_name']) ?>","<?= addslashes($p['product_code'] ?? '') ?>","<?= addslashes($p['category_name'] ?? '') ?>","<?= $p['quantity'] ?>","<?= $p['quantity_alert'] ?>","<?= $p['sell_price'] ?>"\n`;
    <?php endforeach; ?>
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "inventory_stock_report.csv";
    link.click();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>