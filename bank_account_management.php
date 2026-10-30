<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include('layout.php');

$success_message = '';
$error_message = '';
$bank_accounts = [];

// Handle Add Bank Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bank_account'])) {
    try {
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $branch_name = trim($_POST['branch_name'] ?? '');
        $account_holder_name = trim($_POST['account_holder_name'] ?? '');
        
        if (empty($bank_name) || empty($account_number) || empty($ifsc_code) || empty($branch_name) || empty($account_holder_name)) {
            throw new Exception("All fields are required");
        }
        
        $check_stmt = $conn->prepare("SELECT id FROM bank_accounts WHERE account_number = ? AND created_by = ?");
        $check_stmt->bind_param("ss", $account_number, $_SESSION['user_id']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Account number already exists");
        }
        $check_stmt->close();
        
        $stmt = $conn->prepare("
            INSERT INTO bank_accounts (bank_name, account_number, ifsc_code, branch_name, account_holder_name, created_by, created_at, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'active')
        ");
        $stmt->bind_param("sssssi", $bank_name, $account_number, $ifsc_code, $branch_name, $account_holder_name, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = "Bank account added successfully!";
        } else {
            throw new Exception("Failed to add bank account");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Edit Bank Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_bank_account'])) {
    try {
        $account_id = (int)($_POST['account_id'] ?? 0);
        $bank_name = trim($_POST['edit_bank_name'] ?? '');
        $account_number = trim($_POST['edit_account_number'] ?? '');
        $ifsc_code = trim($_POST['edit_ifsc_code'] ?? '');
        $branch_name = trim($_POST['edit_branch_name'] ?? '');
        $account_holder_name = trim($_POST['edit_account_holder_name'] ?? '');
        $status = trim($_POST['edit_status'] ?? '');
        
        if ($account_id <= 0 || empty($bank_name) || empty($account_number) || empty($ifsc_code) || empty($branch_name) || empty($account_holder_name)) {
            throw new Exception("All fields are required");
        }
        
        $check_stmt = $conn->prepare("SELECT id FROM bank_accounts WHERE account_number = ? AND created_by = ? AND id != ?");
        $check_stmt->bind_param("ssi", $account_number, $_SESSION['user_id'], $account_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Account number already exists");
        }
        $check_stmt->close();
        
        $stmt = $conn->prepare("
            UPDATE bank_accounts 
            SET bank_name = ?, account_number = ?, ifsc_code = ?, branch_name = ?, account_holder_name = ?, status = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ? AND created_by = ?
        ");
        $stmt->bind_param("ssssssiii", $bank_name, $account_number, $ifsc_code, $branch_name, $account_holder_name, $status, $_SESSION['user_id'], $account_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = "Bank account updated successfully!";
        } else {
            throw new Exception("Failed to update bank account");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Delete Bank Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bank_account'])) {
    try {
        $account_id = (int)($_POST['account_id'] ?? 0);
        
        if ($account_id <= 0) {
            throw new Exception("Invalid account ID");
        }
        
        $stmt = $conn->prepare("DELETE FROM bank_accounts WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $account_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success_message = "Bank account deleted successfully!";
        } else {
            throw new Exception("Failed to delete bank account");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// AJAX Get Account Data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_account_data'])) {
    header('Content-Type: application/json');
    
    try {
        $account_id = (int)($_GET['account_id'] ?? 0);
        
        if ($account_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
            exit();
        }
        
        $stmt = $conn->prepare("
            SELECT id, bank_name, account_number, ifsc_code, branch_name, account_holder_name, status 
            FROM bank_accounts 
            WHERE id = ? AND created_by = ?
        ");
        $stmt->bind_param("ii", $account_id, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($account = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'data' => $account]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bank account not found']);
        }
        $stmt->close();
        exit();
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Fetch Bank Accounts
try {
    $stmt = $conn->prepare("
        SELECT id, bank_name, account_number, ifsc_code, branch_name, account_holder_name, created_at, status 
        FROM bank_accounts 
        WHERE created_by = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $bank_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching bank accounts: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bank Account Management - Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --success: #10b981;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --warning: #f59e0b;
            --warning-bg: #fed7aa;
            --info: #3b82f6;
            --info-bg: #dbeafe;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 1px 3px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.05);
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
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
        }
        .card-header-premium h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body-premium {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-mid);
            margin-bottom: 5px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 10px 14px;
            font-size: 0.85rem;
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
        
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2,132,199,0.3);
        }
        
        .btn-outline-premium {
            background: #fff;
            color: var(--brand-dark);
            border: 1.5px solid var(--border);
            padding: 9px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
        }
        .btn-outline-premium:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 12px 15px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f4f9fd;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-active {
            background: var(--success-bg);
            color: var(--success);
        }
        .status-inactive {
            background: var(--danger-bg);
            color: var(--danger);
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
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
            cursor: pointer;
            border: none;
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
        .action-btn.delete { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .data-table {
                min-width: 700px;
            }
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-university"></i>
                Bank Account Management
            </div>
            <div class="hero-sub">Manage your company bank accounts</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-credit-card me-1"></i>
            Bank Accounts: <?php echo count($bank_accounts); ?>
        </div>
    </div>

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

    <!-- Add Bank Account Card -->
    <div class="card-premium">
        <div class="card-header-premium">
            <h4><i class="fas fa-plus-circle"></i> Add New Bank Account</h4>
        </div>
        <div class="card-body-premium">
            <form method="POST" id="addBankAccountForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                        <input type="text" name="bank_name" class="form-control" required placeholder="e.g., State Bank of India">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Number <span class="text-danger">*</span></label>
                        <input type="text" name="account_number" class="form-control" required placeholder="Enter account number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">IFSC Code <span class="text-danger">*</span></label>
                        <input type="text" name="ifsc_code" class="form-control" required placeholder="e.g., SBIN0001234">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="branch_name" class="form-control" required placeholder="e.g., Main Branch">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                        <input type="text" name="account_holder_name" class="form-control" required placeholder="Enter account holder name">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" name="add_bank_account" class="btn-primary-premium">
                            <i class="fas fa-save"></i> Add Bank Account
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bank Accounts List Card -->
    <div class="card-premium">
        <div class="card-header-premium">
            <h4><i class="fas fa-list"></i> Existing Bank Accounts</h4>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="bankAccountsTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Bank Name</th>
                            <th>Account Number</th>
                            <th>IFSC Code</th>
                            <th>Branch Name</th>
                            <th>Account Holder</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bank_accounts)): ?>
                            <?php 
                            $serial = 1;
                            foreach ($bank_accounts as $account): 
                            ?>
                            <tr>
                                <td><?php echo $serial++; ?></td>
                                <td><strong><?php echo htmlspecialchars($account['bank_name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($account['account_number']); ?></code></td>
                                <td><?php echo strtoupper(htmlspecialchars($account['ifsc_code'])); ?></td>
                                <td><?php echo htmlspecialchars($account['branch_name']); ?></td>
                                <td><?php echo htmlspecialchars($account['account_holder_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $account['status']; ?>">
                                        <i class="fas fa-<?php echo $account['status'] == 'active' ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo ucfirst($account['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="editAccount(<?php echo $account['id']; ?>)" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button class="action-btn delete" onclick="deleteAccount(<?php echo $account['id']; ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <h5>No bank accounts found</h5>
                                    <p class="text-muted">Add your first bank account using the form above.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Bank Account Modal -->
<div class="modal fade" id="editBankAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Bank Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editBankAccountForm">
                <input type="hidden" name="account_id" id="edit_account_id">
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="edit_bank_name" id="edit_bank_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Number <span class="text-danger">*</span></label>
                            <input type="text" name="edit_account_number" id="edit_account_number" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">IFSC Code <span class="text-danger">*</span></label>
                            <input type="text" name="edit_ifsc_code" id="edit_ifsc_code" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" name="edit_branch_name" id="edit_branch_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                            <input type="text" name="edit_account_holder_name" id="edit_account_holder_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="edit_status" id="edit_status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_bank_account" class="btn-primary-premium">
                        <i class="fas fa-save"></i> Update Bank Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626; margin-bottom: 10px;"></i>
                    <p>Are you sure you want to delete this bank account?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="account_id" id="delete_account_id">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_bank_account" class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Auto-dismiss alerts after 3 seconds
    setTimeout(function() {
        $('.alert-custom').fadeOut(500, function() { $(this).remove(); });
    }, 3000);
});

function editAccount(accountId) {
    $.ajax({
        url: 'bank_account_management.php',
        method: 'GET',
        data: { 
            get_account_data: true,
            account_id: accountId 
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#edit_account_id').val(response.data.id);
                $('#edit_bank_name').val(response.data.bank_name);
                $('#edit_account_number').val(response.data.account_number);
                $('#edit_ifsc_code').val(response.data.ifsc_code);
                $('#edit_branch_name').val(response.data.branch_name);
                $('#edit_account_holder_name').val(response.data.account_holder_name);
                $('#edit_status').val(response.data.status);
                $('#editBankAccountModal').modal('show');
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Error fetching bank account data');
        }
    });
}

function deleteAccount(accountId) {
    $('#delete_account_id').val(accountId);
    $('#deleteConfirmModal').modal('show');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>