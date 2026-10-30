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

$page_title = "Cheque Printing";
$success_message = '';
$error_message = '';
$user_id = (int)$_SESSION['user_id'];

// Fetch bank accounts for dropdown
try {
    $stmt = $conn->prepare("SELECT id, bank_name, account_number, ifsc_code, branch_name, account_holder_name FROM bank_accounts WHERE created_by = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $bank_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching bank accounts: " . $e->getMessage();
    $bank_accounts = [];
}

// Fetch employees from create_users table
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'create_users'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT id, name FROM `create_users` ORDER BY name ASC");
        $stmt->execute();
        $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $employees = [];
    }
} catch (Exception $e) {
    $employees = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_cheque'])) {
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $payee_name = trim($_POST['payee_name'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $cheque_date = trim($_POST['cheque_date'] ?? '');
            $cheque_number = trim($_POST['cheque_number'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$bank_account_id || empty($payee_name) || $amount <= 0 || empty($cheque_date) || empty($cheque_number)) {
                throw new Exception("All required fields must be filled.");
            }

            $date_obj = new DateTime($cheque_date);
            $formatted_date = $date_obj->format('Y-m-d');

            $stmt = $conn->prepare("SELECT id FROM bank_transactions WHERE cheque_number = ? AND created_by = ?");
            $stmt->bind_param("si", $cheque_number, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Cheque number already exists.");
            }
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO bank_transactions (bank_account_id, transaction_type, amount, transaction_date, description, cheque_number, payee_name, created_by, created_at, status) VALUES (?, 'debit', ?, ?, ?, ?, ?, ?, NOW(), 'completed')");
            $stmt->bind_param("idssssi", $bank_account_id, $amount, $formatted_date, $description, $cheque_number, $payee_name, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Cheque added successfully!";
            
        } elseif (isset($_POST['edit_cheque'])) {
            $id = (int)($_POST['id'] ?? 0);
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $payee_name = trim($_POST['payee_name'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $cheque_date = trim($_POST['cheque_date'] ?? '');
            $cheque_number = trim($_POST['cheque_number'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$id || !$bank_account_id || empty($payee_name) || $amount <= 0 || empty($cheque_date) || empty($cheque_number)) {
                throw new Exception("All required fields must be filled.");
            }

            $date_obj = new DateTime($cheque_date);
            $formatted_date = $date_obj->format('Y-m-d');

            $stmt = $conn->prepare("SELECT id FROM bank_transactions WHERE cheque_number = ? AND created_by = ? AND id != ?");
            $stmt->bind_param("sii", $cheque_number, $user_id, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                throw new Exception("Cheque number already exists.");
            }
            $stmt->close();

            $stmt = $conn->prepare("UPDATE bank_transactions SET bank_account_id = ?, amount = ?, transaction_date = ?, description = ?, cheque_number = ?, payee_name = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $stmt->bind_param("idssssii", $bank_account_id, $amount, $formatted_date, $description, $cheque_number, $payee_name, $id, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Cheque updated successfully!";
            
        } elseif (isset($_POST['delete_cheque'])) {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            $stmt = $conn->prepare("DELETE FROM bank_transactions WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Cheque deleted successfully!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Cheque Printing Error: " . $e->getMessage());
    }
}

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM bank_transactions WHERE created_by = ? AND transaction_type = 'debit' AND cheque_number IS NOT NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT bt.id, bt.bank_account_id, bt.amount, bt.transaction_date, bt.description, bt.cheque_number, bt.payee_name, bt.created_at,
               ba.bank_name, ba.account_number, ba.ifsc_code, ba.account_holder_name, ba.branch_name
        FROM bank_transactions bt
        LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.created_by = ? AND bt.transaction_type = 'debit' AND bt.cheque_number IS NOT NULL
        ORDER BY bt.transaction_date DESC LIMIT ?, ?
    ");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque Printing - Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220;
            --brand-mid: #0ea5e9;
            --brand-light: #e0f2fe;
            --bg: #f4f8fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --success: #16a34a;
            --success-bg: #dcfce7;
            --danger: #dc2626;
            --danger-bg: #fee2e2;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 2px 8px rgba(2,132,199,.07);
            --shadow-md: 0 8px 24px rgba(2,132,199,.11);
            --transition: all 0.25s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        
        .page-wrap {
            margin-top: 70px;
            margin-left: 260px;
            padding: 24px 28px 40px;
            transition: var(--transition);
            min-height: calc(100vh - 70px);
        }
        @media (max-width: 992px) {
            .page-wrap { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 576px) {
            .page-wrap { padding: 12px; }
        }
        
        .hero-banner {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        @media (max-width: 768px) {
            .hero-banner {
                padding: 14px 18px;
                flex-direction: column;
                text-align: center;
            }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hero-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.7rem;
        }
        .hero-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 5px 14px;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .search-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .search-input {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        .search-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .search-input input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-size: 0.8rem;
            transition: var(--transition);
            background: #fafbfc;
        }
        .search-input input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            outline: none;
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(2,132,199,0.3);
        }
        
        .card-premium {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
        }
        .card-premium:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header-premium {
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
        }
        .card-header-premium h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-body-premium {
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 900px;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 10px 12px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .action-btns {
            display: flex;
            gap: 6px;
        }
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
        .action-btn.print { background: linear-gradient(135deg, #0284c7, #0369a1); }
        .action-btn.delete { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .pagination-custom a {
            padding: 5px 12px;
            border-radius: 6px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.7rem;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        .pagination-custom a.active, .pagination-custom a:hover {
            background: var(--brand);
            color: #fff;
        }
        
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 8px 14px;
            margin-bottom: 16px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
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
        
        .modal-content {
            border-radius: var(--radius-md);
            border: none;
            box-shadow: var(--shadow-md);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            border: none;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .modal-title {
            font-size: 0.9rem;
            font-weight: 700;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-mid);
            margin-bottom: 5px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 8px 12px;
            font-size: 0.8rem;
            transition: var(--transition);
            background: #fafbfc;
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            outline: none;
            background: #fff;
        }
        
        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
        }
        
        .btn-outline-premium {
            background: transparent;
            border: 1.5px solid var(--border);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
        }
        .btn-outline-premium:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }
        
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
            }
            .data-table {
                min-width: 700px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-money-check"></i>
                Cheque Printing
            </div>
            <div class="hero-sub">Create and manage cheque payments</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-credit-card me-1"></i>
            Bank Cheques
        </div>
    </div>

    <div class="search-bar">
        <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by bank, account, payee, cheque number..." onkeyup="searchTable()">
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#chequeModal">
                <i class="fas fa-plus"></i> Add Cheque
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #16a34a, #15803d);" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom" id="successAlert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card-premium">
        <div class="card-header-premium">
            <h5><i class="fas fa-list"></i> Cheque Transactions</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="chequeTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Bank Name</th>
                            <th>Account Number</th>
                            <th>Payee Name</th>
                            <th>Amount (₹)</th>
                            <th>Cheque Date</th>
                            <th>Cheque No.</th>
                            <th>Description</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $created_at = (new DateTime($row['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y h:i A');
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['bank_name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($row['account_number']); ?></code></td>
                                    <td><?php echo htmlspecialchars($row['payee_name'] ?? '-'); ?></td>
                                    <td><span style="font-weight: 700; color: var(--brand);">₹<?php echo number_format($row['amount'], 2); ?></span></td>
                                    <td><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                                    <td><span class="badge" style="background: var(--brand-light); color: var(--brand);"><?php echo htmlspecialchars($row['cheque_number']); ?></span></td>
                                    <td><small><?php echo htmlspecialchars($row['description'] ?? '-'); ?></small></td>
                                    <td><small><?php echo $created_at; ?></small></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn edit" data-bs-toggle="modal" data-bs-target="#editChequeModal" 
                                                onclick='editCheque(<?php echo json_encode($row); ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="action-btn print" onclick="printCheque(<?php echo $row['id']; ?>)"><i class="fas fa-print"></i></button>
                                            <button class="action-btn delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='10' style='text-align:center; padding:40px;'>No cheques found. <a href='#' data-bs-toggle='modal' data-bs-target='#chequeModal' style='color:var(--brand);'>Add your first cheque</a></tr></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($result && $total_pages > 1): ?>
            <div class="pagination-custom">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Cheque Modal -->
<div class="modal fade" id="chequeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Add Cheque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cheque_printing.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                        <select class="form-select" name="bank_account_id" required>
                            <option value="">Select Bank Account</option>
                            <?php foreach ($bank_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payee Name <span class="text-danger">*</span></label>
                        <select class="form-select" id="payee_select" name="payee_name">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo htmlspecialchars($employee['name']); ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (Specify)</option>
                        </select>
                        <input type="text" class="form-control mt-2" id="other_payee" name="other_payee" placeholder="Enter payee name" style="display:none;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="amount" required placeholder="Enter amount">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cheque Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="cheque_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cheque Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="cheque_number" required placeholder="Enter cheque number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_cheque" class="btn-primary-premium"><i class="fas fa-save"></i> Add Cheque</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cheque Modal -->
<div class="modal fade" id="editChequeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Cheque</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cheque_printing.php">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Bank Account <span class="text-danger">*</span></label>
                        <select class="form-select" name="bank_account_id" id="edit_bank_account_id" required>
                            <option value="">Select Bank Account</option>
                            <?php foreach ($bank_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_number']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payee Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="payee_name" id="edit_payee_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="amount" id="edit_amount" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cheque Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="cheque_date" id="edit_cheque_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cheque Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="cheque_number" id="edit_cheque_number" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2" placeholder="Optional description..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_cheque" class="btn-primary-premium"><i class="fas fa-save"></i> Update Cheque</button>
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
            <form method="POST">
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626; margin-bottom: 10px;"></i>
                    <p>Are you sure you want to delete this cheque?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_cheque" class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 8px 20px;">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    $('#payee_select').on('change', function() {
        if ($(this).val() === 'other') {
            $('#other_payee').show();
            $('#other_payee').attr('name', 'payee_name');
            $(this).attr('name', '');
        } else {
            $('#other_payee').hide();
            $('#other_payee').attr('name', '');
            $(this).attr('name', 'payee_name');
        }
    });
    
    setTimeout(function() {
        $('#successAlert').fadeOut(500, function() { $(this).remove(); });
    }, 3000);
});

function editCheque(cheque) {
    $('#edit_id').val(cheque.id);
    $('#edit_bank_account_id').val(cheque.bank_account_id);
    $('#edit_payee_name').val(cheque.payee_name);
    $('#edit_amount').val(cheque.amount);
    $('#edit_cheque_date').val(cheque.transaction_date);
    $('#edit_cheque_number').val(cheque.cheque_number);
    $('#edit_description').val(cheque.description || '');
}

function printCheque(id) {
    window.open('print_cheque.php?id=' + id, '_blank', 'width=800,height=600');
}

function searchTable() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let rows = document.querySelectorAll('#chequeTable tbody tr');
    
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFillColor(2, 132, 199);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Cheque Transactions Report', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [];
    $('#chequeTable tbody tr:visible').each(function(i) {
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
            cells.eq(8).text()
        ]);
    });
    
    doc.autoTable({
        head: [['S.No', 'Bank', 'Account No', 'Payee', 'Amount', 'Date', 'Cheque No', 'Description', 'Created']],
        body: rows,
        startY: 32,
        styles: { fontSize: 7, cellPadding: 2 },
        headStyles: { fillColor: [2, 132, 199], textColor: 255, fontStyle: 'bold' }
    });
    doc.save('cheque_transactions.pdf');
}

function exportToExcel() {
    const ws_data = [['S.No', 'Bank Name', 'Account Number', 'Payee Name', 'Amount', 'Cheque Date', 'Cheque Number', 'Description', 'Created On']];
    $('#chequeTable tbody tr:visible').each(function(i) {
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
            cells.eq(8).text()
        ]);
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'Cheque Transactions');
    XLSX.writeFile(wb, 'cheque_transactions.xlsx');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>