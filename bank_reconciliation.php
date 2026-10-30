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

$page_title = "Bank Reconciliation";
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = (int)$_SESSION['user_id'];

    try {
        if (isset($_POST['add_reconciliation'])) {
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $bank_transaction_id = (int)($_POST['bank_transaction_id'] ?? 0);
            $system_reference_id = (int)($_POST['system_reference_id'] ?? 0);
            $system_reference_type = trim($_POST['system_reference_type'] ?? '');
            $reconciled_amount = floatval($_POST['reconciled_amount'] ?? 0);
            $reconciled_date = trim($_POST['reconciled_date'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;
            $notes = trim($_POST['notes'] ?? '');

            if (empty($bank_account_id) || empty($bank_transaction_id) || empty($system_reference_id) || empty($system_reference_type) || empty($reconciled_date)) {
                throw new Exception("All required fields must be filled.");
            }

            if ($reconciled_amount <= 0) {
                throw new Exception("Amount must be greater than zero.");
            }

            $stmt = $conn->prepare("INSERT INTO bank_reconciliation (bank_account_id, bank_transaction_id, system_reference_id, system_reference_type, reconciled_amount, reconciled_date, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiisdsisi", $bank_account_id, $bank_transaction_id, $system_reference_id, $system_reference_type, $reconciled_amount, $reconciled_date, $status, $notes, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Reconciliation added successfully";
        } elseif (isset($_POST['edit_reconciliation'])) {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for edit.");

            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $bank_transaction_id = (int)($_POST['bank_transaction_id'] ?? 0);
            $system_reference_id = (int)($_POST['system_reference_id'] ?? 0);
            $system_reference_type = trim($_POST['system_reference_type'] ?? '');
            $reconciled_amount = floatval($_POST['reconciled_amount'] ?? 0);
            $reconciled_date = trim($_POST['reconciled_date'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;
            $notes = trim($_POST['notes'] ?? '');

            if (empty($bank_account_id) || empty($bank_transaction_id) || empty($system_reference_id) || empty($system_reference_type) || empty($reconciled_date)) {
                throw new Exception("All required fields must be filled.");
            }

            if ($reconciled_amount <= 0) {
                throw new Exception("Amount must be greater than zero.");
            }

            $stmt = $conn->prepare("UPDATE bank_reconciliation SET bank_account_id = ?, bank_transaction_id = ?, system_reference_id = ?, system_reference_type = ?, reconciled_amount = ?, reconciled_date = ?, status = ?, notes = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $stmt->bind_param("iiisdsisiii", $bank_account_id, $bank_transaction_id, $system_reference_id, $system_reference_type, $reconciled_amount, $reconciled_date, $status, $notes, $user_id, $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Reconciliation updated successfully";
        } elseif (isset($_POST['delete_reconciliation'])) {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            $stmt = $conn->prepare("DELETE FROM bank_reconciliation WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Reconciliation deleted successfully";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Error in bank_reconciliation.php: " . $error_message);
    }
}

// Pagination setup
$user_id = (int)$_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bank_reconciliation WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM bank_reconciliation WHERE created_by = ? ORDER BY reconciled_date DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $user_id, $start_from, $records_per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    $result = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?> — CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            font-size: 14px; 
        }
        
        /* Main Content Area */
        .page-wrap {
            margin-top: 70px;
            margin-left: 260px;
            padding: 24px 28px 40px;
            transition: var(--transition);
            min-height: calc(100vh - 70px);
        }
        @media (max-width: 992px) {
            .page-wrap { margin-left: 0; padding: 16px 14px; }
        }
        @media (max-width: 576px) {
            .page-wrap { padding: 12px; }
        }
        
        /* Hero Banner */
        .page-hero {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 22px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: ''; position: absolute; right: -50px; top: -50px;
            width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,.06);
        }
        @media (max-width: 768px) {
            .page-hero {
                padding: 18px 20px;
                flex-direction: column;
                align-items: stretch;
                text-align: left;
            }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hero-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.85rem;
            margin: 0;
            font-weight: 500;
        }
        .hero-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 50px;
            padding: 6px 16px;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 10;
        }
        
        /* Search Bar */
        .search-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .search-input {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        .search-input i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .search-input input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-size: 0.85rem;
            transition: var(--transition);
            background: #fafafa;
        }
        .search-input input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
            outline: none;
            background: #fff;
        }
        
        /* Buttons */
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 14px rgba(2,132,199,.25);
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(2,132,199,.35);
            color: #fff;
        }
        
        .btn-outline-premium {
            background: transparent;
            border: 1.5px solid var(--border);
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            color: var(--text-mid);
        }
        .btn-outline-premium:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }

        /* Card */
        .card-premium {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-premium:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header-premium {
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header-premium h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body-premium {
            padding: 0;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 1000px;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 14px 16px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tbody tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-reconciled { background: var(--success-bg); color: var(--success); }
        .status-not { background: var(--danger-bg); color: var(--danger); }
        
        /* Badges */
        .badge {
            font-size: 0.72rem;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 700;
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        
        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.8rem;
            border: none;
            cursor: pointer;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            color: #fff;
        }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); box-shadow: 0 2px 8px rgba(2,132,199,.2); }
        .action-btn.delete { background: linear-gradient(135deg, #dc2626, #b91c1c); box-shadow: 0 2px 8px rgba(220,38,38,.2); }
        
        /* Pagination */
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .pagination-custom a {
            padding: 6px 14px;
            border-radius: 6px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.8rem;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        .pagination-custom a.active, .pagination-custom a:hover {
            background: var(--brand-dark);
            color: #fff;
        }
        
        /* Alerts */
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
        .alert-success-custom {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
        }
        .alert-danger-custom {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
        }
        
        /* Modal */
        .modal-content {
            border-radius: var(--radius-md);
            border: none;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 18px 22px;
            border: none;
        }
        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }
        .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
        }
        .modal-body {
            padding: 22px;
        }
        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            gap: 10px;
            justify-content: flex-end;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: var(--text-mid);
            margin-bottom: 6px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: #fafafa;
            width: 100%;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            outline: none;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
            background: #fff;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
        }
        .checkbox-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--brand);
        }
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text);
        }
        
        @media (max-width: 768px) {
            .search-bar { flex-direction: column; align-items: stretch; }
            .search-bar > div { display: flex; flex-direction: column; width: 100%;}
            .search-bar .btn-primary-premium { width: 100%; justify-content: center;}
            .card-header-premium { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <!-- Hero Banner -->
    <div class="page-hero">
        <div>
            <div class="hero-title">
                <i class="fas fa-university me-2"></i>
                Bank Reconciliation
            </div>
            <div class="hero-sub">Match and reconcile bank transactions</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-check-double me-1"></i>
            Bank Matching
        </div>
    </div>

    <!-- Search and Add Bar -->
    <div class="search-bar">
        <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by reference type..." onkeyup="searchTable()">
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#reconciliationModal">
                <i class="fas fa-plus-circle"></i> Add Reconciliation
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #16a34a, #15803d);" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="card-premium">
        <div class="card-header-premium">
            <h5><i class="fas fa-list me-2"></i> Reconciliation History</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="reconciliationTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Bank Account ID</th>
                            <th>Bank Transaction ID</th>
                            <th>System Ref ID</th>
                            <th>Reference Type</th>
                            <th>Amount (₹)</th>
                            <th>Reconciled Date</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Created On</th>
                            <th style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $created_at = new DateTime($row['created_at'], new DateTimeZone('UTC'));
                                $created_at->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                $status_class = $row['status'] ? 'status-reconciled' : 'status-not';
                                $status_text = $row['status'] ? 'Reconciled' : 'Not Reconciled';
                                ?>
                                <tr>
                                    <td style="color:var(--text-muted); font-weight:600;"><?php echo $sno++; ?></td>
                                    <td><span class="badge" style="background: var(--brand-light); color: var(--brand);">ACC-<?php echo $row['bank_account_id']; ?></span></td>
                                    <td><span class="badge" style="background: var(--info-bg); color: var(--info);">TXN-<?php echo $row['bank_transaction_id']; ?></span></td>
                                    <td style="font-weight:600;"><?php echo $row['system_reference_id']; ?></td>
                                    <td><span class="badge" style="background: var(--warning-bg); color: var(--warning);"><?php echo htmlspecialchars($row['system_reference_type']); ?></span></td>
                                    <td><span style="font-family:'Plus Jakarta Sans',sans-serif; font-weight: 800; color: var(--brand);">₹<?php echo number_format($row['reconciled_amount'], 2); ?></span></td>
                                    <td style="color:var(--text-mid); font-weight:500;"><?php echo date('d M Y', strtotime($row['reconciled_date'])); ?></td>
                                    <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td><small><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></small></td>
                                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $created_at->format('d M Y, h:i A'); ?></td>
                                    <td style="text-align:center;">
                                        <div class="action-btns">
                                            <a class="action-btn edit" href="#" data-bs-toggle="modal" data-bs-target="#reconciliationModal" onclick='editReconciliation(<?php echo json_encode($row); ?>)'><i class="fas fa-pen"></i></a>
                                            <a class="action-btn delete" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='11' style='text-align:center; padding:50px 20px; color:var(--text-muted);'>
                                <i class='fas fa-university' style='font-size:3rem; opacity:0.3; margin-bottom:15px; display:block; color:var(--brand);'></i>
                                <div style='font-size:1rem; font-weight:600; color:var(--text); margin-bottom:5px;'>No reconciliations found</div>
                                <a href='#' data-bs-toggle='modal' data-bs-target='#reconciliationModal' style='color:var(--brand); font-weight:600; text-decoration:none;'><i class='fas fa-plus me-1'></i> Add your first reconciliation</a>
                            </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($result && $total_pages > 1): ?>
            <div class="pagination-custom">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>"><i class="fas fa-chevron-left me-1"></i> Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>">Next <i class="fas fa-chevron-right ms-1"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reconciliation Modal -->
<div class="modal fade" id="reconciliationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reconciliationModalLabel"><i class="fas fa-handshake me-2"></i>Add Reconciliation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reconciliationForm" action="bank_reconciliation.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="reconciliationId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Bank Account ID <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="bank_account_id" name="bank_account_id" required placeholder="Enter bank account ID">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Bank Transaction ID <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="bank_transaction_id" name="bank_transaction_id" required placeholder="Enter bank transaction ID">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">System Ref ID <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="system_reference_id" name="system_reference_id" required placeholder="Enter system ref ID">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reference Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="system_reference_type" name="system_reference_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Invoice">Invoice</option>
                                    <option value="Payment">Payment</option>
                                    <option value="Purchase">Purchase</option>
                                    <option value="Expense">Expense</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reconciled Amount (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="reconciled_amount" name="reconciled_amount" required placeholder="Enter amount">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Reconciled Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="reconciled_date" name="reconciled_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="status" name="status">
                        <label for="status">Mark as Reconciled successfully</label>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_reconciliation" class="btn-primary-premium" id="addReconciliationBtn"><i class="fas fa-save me-1"></i> Save Reconciliation</button>
                    <button type="submit" name="edit_reconciliation" class="btn-primary-premium" id="editReconciliationBtn" style="display:none;"><i class="fas fa-check me-1"></i> Update Reconciliation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc2626; margin-bottom: 15px; opacity:0.8;"></i>
                    <p style="font-weight:600; margin-bottom:5px;">Are you sure you want to delete this reconciliation?</p>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">This action cannot be undone.</p>
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_reconciliation" class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 9px 22px;">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    $('#reconciliationModal').on('hidden.bs.modal', function() {
        $('#reconciliationForm')[0].reset();
        $('#reconciliationId').val('');
        $('#addReconciliationBtn').show();
        $('#editReconciliationBtn').hide();
        $('#reconciled_date').val(new Date().toISOString().split('T')[0]);
        $('#reconciliationModalLabel').html('<i class="fas fa-handshake me-2"></i>Add Reconciliation');
    });

    // Auto dismiss alerts
    setTimeout(function() {
        $('.alert-custom').css('opacity', '0');
        setTimeout(function() {
            $('.alert-custom').remove();
        }, 300);
    }, 4000);
});

function editReconciliation(reconciliation) {
    $('#reconciliationId').val(reconciliation.id || '');
    $('#bank_account_id').val(reconciliation.bank_account_id || '');
    $('#bank_transaction_id').val(reconciliation.bank_transaction_id || '');
    $('#system_reference_id').val(reconciliation.system_reference_id || '');
    $('#system_reference_type').val(reconciliation.system_reference_type || '');
    $('#reconciled_amount').val(reconciliation.reconciled_amount || '');
    $('#reconciled_date').val(reconciliation.reconciled_date || '');
    $('#status').prop('checked', reconciliation.status == 1);
    $('#notes').val(reconciliation.notes || '');
    $('#addReconciliationBtn').hide();
    $('#editReconciliationBtn').show();
    $('#reconciliationModalLabel').html('<i class="fas fa-pen me-2"></i>Edit Reconciliation');
}

function searchTable() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let rows = document.querySelectorAll('#reconciliationTable tbody tr');
    
    rows.forEach(row => {
        // Skip empty state row
        if(row.cells.length <= 1) return;
        
        let refType = row.cells[4]?.innerText.toLowerCase() || '';
        if (refType.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Set Header color to Sky Blue (2, 132, 199)
    doc.setFillColor(2, 132, 199);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Bank Reconciliation Report', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [];
    $('#reconciliationTable tbody tr:visible').each(function(i) {
        if($(this).find('td').length > 1) {
            const cells = $(this).find('td');
            rows.push([
                cells.eq(0).text(),
                cells.eq(1).text(),
                cells.eq(2).text(),
                cells.eq(3).text(),
                cells.eq(4).text(),
                cells.eq(5).text(),
                cells.eq(6).text(),
                cells.eq(7).text(),
                cells.eq(8).text(),
                cells.eq(9).text()
            ]);
        }
    });
    
    doc.autoTable({
        head: [['S.No', 'Bank A/c ID', 'Bank TXN ID', 'System Ref ID', 'Ref Type', 'Amount', 'Reconciled Date', 'Status', 'Notes', 'Created']],
        body: rows,
        startY: 32,
        styles: { fontSize: 7, cellPadding: 2 },
        headStyles: { fillColor: [2, 132, 199], textColor: 255, fontStyle: 'bold' }
    });
    doc.save('bank_reconciliation_credible.pdf');
}

function exportToExcel() {
    const ws_data = [['S.No', 'Bank Account ID', 'Bank Transaction ID', 'System Reference ID', 'Reference Type', 'Amount', 'Reconciled Date', 'Status', 'Notes', 'Created On']];
    $('#reconciliationTable tbody tr:visible').each(function(i) {
        if($(this).find('td').length > 1) {
            const cells = $(this).find('td');
            ws_data.push([
                cells.eq(0).text(),
                cells.eq(1).text(),
                cells.eq(2).text(),
                cells.eq(3).text(),
                cells.eq(4).text(),
                cells.eq(5).text(),
                cells.eq(6).text(),
                cells.eq(7).text(),
                cells.eq(8).text(),
                cells.eq(9).text()
            ]);
        }
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'Bank Reconciliation');
    XLSX.writeFile(wb, 'bank_reconciliation_credible.xlsx');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>