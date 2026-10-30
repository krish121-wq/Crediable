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

$page_title = "General Ledger";
$debug_messages = [];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_messages[] = "POST request received at " . date('Y-m-d H:i:s');
    $user_id = (int)$_SESSION['user_id'];
    $debug_messages[] = "User ID: $user_id";

    try {
        if (isset($_POST['add_transaction'])) {
            $debug_messages[] = "Add transaction action triggered";
            $account_name = trim($_POST['account_name'] ?? '');
            $account_type = trim($_POST['account_type'] ?? '');
            $debit = floatval($_POST['debit'] ?? 0);
            $credit = floatval($_POST['credit'] ?? 0);
            $transaction_date = trim($_POST['transaction_date'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($account_name) || empty($account_type) || empty($transaction_date)) {
                throw new Exception("All required fields must be filled.");
            }

            $valid_account_types = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
            if (!in_array($account_type, $valid_account_types)) {
                throw new Exception("Invalid account type.");
            }

            if ($debit < 0 || $credit < 0) {
                throw new Exception("Debit and Credit amounts must be non-negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new Exception("Cannot have both debit and credit amounts in a single transaction.");
            }

            try {
                $date_obj = new DateTime($transaction_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            $stmt = $conn->prepare("INSERT INTO general_ledger (account_name, account_type, debit, credit, transaction_date, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssddssi", $account_name, $account_type, $debit, $credit, $formatted_date, $description, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Transaction added successfully";
        } elseif (isset($_POST['edit_transaction'])) {
            $debug_messages[] = "Edit transaction action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for edit.");

            $stmt = $conn->prepare("SELECT * FROM general_ledger WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Record not found for ID: $id");
            }
            $current = $result->fetch_assoc();
            $stmt->close();

            $account_name = trim($_POST['account_name'] ?? $current['account_name']);
            $account_type = trim($_POST['account_type'] ?? $current['account_type']);
            $debit = floatval($_POST['debit'] ?? $current['debit']);
            $credit = floatval($_POST['credit'] ?? $current['credit']);
            $transaction_date = trim($_POST['transaction_date'] ?? $current['transaction_date']);
            $description = trim($_POST['description'] ?? $current['description']);

            if (empty($account_name) || empty($account_type) || empty($transaction_date)) {
                throw new Exception("All required fields must be filled.");
            }

            $valid_account_types = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
            if (!in_array($account_type, $valid_account_types)) {
                throw new Exception("Invalid account type.");
            }

            if ($debit < 0 || $credit < 0) {
                throw new Exception("Debit and Credit amounts must be non-negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new Exception("Cannot have both debit and credit amounts in a single transaction.");
            }

            try {
                $date_obj = new DateTime($transaction_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            $stmt = $conn->prepare("UPDATE general_ledger SET account_name = ?, account_type = ?, debit = ?, credit = ?, transaction_date = ?, description = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ssddssiii", $account_name, $account_type, $debit, $credit, $formatted_date, $description, $user_id, $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Transaction updated successfully";
        } elseif (isset($_POST['delete_transaction'])) {
            $debug_messages[] = "Delete transaction action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            $stmt = $conn->prepare("DELETE FROM general_ledger WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Transaction deleted successfully";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $debug_messages[] = "Error: $error_message | Line: " . $e->getLine();
        error_log("Error in general-ledger.php: $error_message | Line: " . $e->getLine());
    }

    file_put_contents('debug.log', implode("\n", $debug_messages) . "\n", FILE_APPEND);
}

// Pagination setup
$user_id = (int)$_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM general_ledger WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM general_ledger WHERE created_by = ? ORDER BY transaction_date DESC LIMIT ?, ?");
    $stmt->bind_param("iii", $user_id, $start_from, $records_per_page);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    error_log("Query Error: $error_message | Line: " . $e->getLine());
    file_put_contents('debug.log', "Query Error: $error_message | Line: " . $e->getLine() . "\n", FILE_APPEND);
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

        .btn-danger-premium {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 14px rgba(220,38,38,.25);
        }
        .btn-danger-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(220,38,38,.35);
            color: #fff;
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
            min-width: 800px;
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
        
        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 6px;
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
            padding: 16px 22px;
            border-top: 1px solid var(--border);
            gap: 10px;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
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
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
            outline: none;
            background: #fff;
        }
        
        @media (max-width: 768px) {
            .action-bar-top { flex-direction: column; align-items: stretch !important; }
            .action-bar-top .btn-primary-premium { width: 100%; justify-content: center; margin-bottom: 8px;}
            .action-bar-top > div { display: flex; flex-direction: column; width: 100%;}
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <!-- Hero Banner -->
    <div class="page-hero">
        <div>
            <div class="hero-title">
                <i class="fas fa-book"></i>
                General Ledger
            </div>
            <div class="hero-sub">Manage your financial transactions</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-chart-line me-1"></i>
            Ledger Report
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar-top" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#transactionModal">
            <i class="fas fa-plus-circle"></i> Add Transaction
        </button>
        <div style="display: flex; gap: 10px;">
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
            <h5><i class="fas fa-list"></i> Transaction History</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="transactionTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Transaction Date</th>
                            <th>Description</th>
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
                                ?>
                                <tr>
                                    <td style="color:var(--text-muted); font-weight:600;"><?php echo $sno++; ?></td>
                                    <td style="font-weight:600;"><?php echo htmlspecialchars($row['account_name']); ?></td>
                                    <td><span class="badge" style="background: var(--brand-light); color: var(--brand); padding: 4px 10px; border-radius: 50px; font-size: 0.72rem; font-weight:700; letter-spacing:0.5px;"><?php echo htmlspecialchars($row['account_type']); ?></span></td>
                                    <td><?php echo $row['debit'] > 0 ? '<span style="color:var(--danger); font-weight:700; font-family:\'Plus Jakarta Sans\',sans-serif;">₹' . number_format($row['debit'], 2) . '</span>' : '—'; ?></td>
                                    <td><?php echo $row['credit'] > 0 ? '<span style="color:var(--success); font-weight:700; font-family:\'Plus Jakarta Sans\',sans-serif;">₹' . number_format($row['credit'], 2) . '</span>' : '—'; ?></td>
                                    <td style="color:var(--text-mid);"><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['description'] ?? '-'); ?></td>
                                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $created_at->format('d M Y, h:i A'); ?></td>
                                    <td style="text-align:center;">
                                        <div class="action-btns" style="justify-content:center;">
                                            <a class="action-btn edit" href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick='editTransaction(<?php echo json_encode($row); ?>)'><i class="fas fa-pen"></i></a>
                                            <a class="action-btn delete" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9' style='text-align:center; padding:50px 20px; color:var(--text-muted);'>
                                <i class='fas fa-book-open' style='font-size:3rem; opacity:0.3; margin-bottom:15px; display:block; color:var(--brand);'></i>
                                <div style='font-size:1rem; font-weight:600; color:var(--text); margin-bottom:5px;'>No transactions found</div>
                                <a href='#' data-bs-toggle='modal' data-bs-target='#transactionModal' style='color:var(--brand); font-weight:600; text-decoration:none;'><i class='fas fa-plus me-1'></i> Add your first transaction</a>
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

<!-- Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel"><i class="fas fa-plus-circle me-2"></i>Add Transaction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="transactionForm" action="general-ledger.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="transactionId">
                    <div class="form-group">
                        <label class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="account_type" name="account_type" required>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Debit Amount</label>
                                <input type="number" step="0.01" class="form-control" id="debit" name="debit" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Credit Amount</label>
                                <input type="number" step="0.01" class="form-control" id="credit" name="credit" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Optional description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_transaction" class="btn-primary-premium" id="addTransactionBtn"><i class="fas fa-save me-1"></i> Save Transaction</button>
                    <button type="submit" name="edit_transaction" class="btn-primary-premium" id="editTransactionBtn" style="display:none;"><i class="fas fa-check me-1"></i> Update Transaction</button>
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
                    <p style="font-weight:600; margin-bottom:5px;">Are you sure you want to delete this transaction?</p>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">This action cannot be undone.</p>
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_transaction" class="btn-danger-premium" style="padding: 9px 22px;">Yes, Delete</button>
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
    $('#transactionModal').on('hidden.bs.modal', function() {
        $('#transactionForm')[0].reset();
        $('#transactionId').val('');
        $('#addTransactionBtn').show();
        $('#editTransactionBtn').hide();
        $('#debit').val('0');
        $('#credit').val('0');
        $('#transaction_date').val(new Date().toISOString().split('T')[0]);
        $('#transactionModalLabel').html('<i class="fas fa-plus-circle me-2"></i>Add Transaction');
    });

    // Auto dismiss alerts
    setTimeout(function() {
        $('.alert-custom').css('opacity', '0');
        setTimeout(function() {
            $('.alert-custom').remove();
        }, 300);
    }, 4000);
});

function editTransaction(transaction) {
    $('#transactionId').val(transaction.id || '');
    $('#account_name').val(transaction.account_name || '');
    $('#account_type').val(transaction.account_type || '');
    $('#debit').val(transaction.debit || '0');
    $('#credit').val(transaction.credit || '0');
    $('#transaction_date').val(transaction.transaction_date || '');
    $('#description').val(transaction.description || '');
    $('#addTransactionBtn').hide();
    $('#editTransactionBtn').show();
    $('#transactionModalLabel').html('<i class="fas fa-pen me-2"></i>Edit Transaction');
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Set Header color to Sky Blue (2, 132, 199)
    doc.setFillColor(2, 132, 199);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('General Ledger Report', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [];
    $('#transactionTable tbody tr').each(function(i) {
        // Skip empty state row if present
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
                cells.eq(7).text()
            ]);
        }
    });
    
    doc.autoTable({
        head: [['S.No', 'Account Name', 'Type', 'Debit', 'Credit', 'Date', 'Description', 'Created']],
        body: rows,
        startY: 32,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [2, 132, 199], textColor: 255, fontStyle: 'bold' }
    });
    doc.save('general_ledger_credible.pdf');
}

function exportToExcel() {
    const ws_data = [['S.No', 'Account Name', 'Account Type', 'Debit', 'Credit', 'Transaction Date', 'Description', 'Created On']];
    $('#transactionTable tbody tr').each(function(i) {
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
                cells.eq(7).text()
            ]);
        }
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'General Ledger');
    XLSX.writeFile(wb, 'general_ledger_credible.xlsx');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>