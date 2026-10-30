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

$page_title = "Auto Bank Reconciliation";
$debug_messages = [];
$success_message = '';
$error_message = '';
$user_id = (int)$_SESSION['user_id'];

// Fetch bank accounts for dropdown
try {
    $stmt = $conn->prepare("SELECT id, bank_name, account_number FROM bank_accounts WHERE created_by = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $bank_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching bank accounts: " . $e->getMessage();
    error_log("Bank Accounts Fetch Error: " . $e->getMessage());
    $debug_messages[] = "Bank Accounts Fetch Error: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_messages[] = "POST request received at " . date('Y-m-d H:i:s');

    try {
        if (isset($_POST['add_reconciliation'])) {
            $debug_messages[] = "Add reconciliation action triggered";
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $bank_transaction_id = (int)($_POST['bank_transaction_id'] ?? 0);
            $system_reference_id = (int)($_POST['system_reference_id'] ?? 0);
            $system_reference_type = trim($_POST['system_reference_type'] ?? '');
            $reconciled_amount = floatval($_POST['reconciled_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            // Validate required fields
            if (!$bank_account_id || !$bank_transaction_id || !$system_reference_id || empty($system_reference_type) || $reconciled_amount <= 0) {
                throw new Exception("All required fields must be filled and amount must be greater than zero.");
            }

            // Validate system_reference_type
            if (!in_array($system_reference_type, ['sale', 'purchase', 'manual'])) {
                throw new Exception("Invalid reference type.");
            }

            // Validate bank transaction
            $stmt = $conn->prepare("SELECT amount FROM bank_transactions WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $bank_transaction_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid bank transaction ID.");
            }
            $transaction = $result->fetch_assoc();
            $stmt->close();

            // Validate amount
            if (abs($transaction['amount'] - $reconciled_amount) > 0.01) {
                throw new Exception("Reconciled amount does not match transaction amount.");
            }

            // Insert reconciliation
            $stmt = $conn->prepare("
                INSERT INTO bank_reconciliation (bank_account_id, bank_transaction_id, system_reference_id, system_reference_type, reconciled_amount, reconciled_date, status, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW(), 'matched', ?, ?, NOW())
            ");
            $stmt->bind_param("iiisdsi", $bank_account_id, $bank_transaction_id, $system_reference_id, $system_reference_type, $reconciled_amount, $notes, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();

            // Update bank transaction
            $stmt = $conn->prepare("UPDATE bank_transactions SET reconciled = TRUE, reconciled_date = NOW() WHERE id = ?");
            $stmt->bind_param("i", $bank_transaction_id);
            $stmt->execute();
            $stmt->close();

            $success_message = "Reconciliation added successfully";
        } elseif (isset($_POST['edit_reconciliation'])) {
            $debug_messages[] = "Edit reconciliation action triggered";
            $reconciliation_id = (int)($_POST['id'] ?? 0);
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
            $bank_transaction_id = (int)($_POST['bank_transaction_id'] ?? 0);
            $system_reference_id = (int)($_POST['system_reference_id'] ?? 0);
            $system_reference_type = trim($_POST['system_reference_type'] ?? '');
            $reconciled_amount = floatval($_POST['reconciled_amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            // Validate required fields
            if (!$reconciliation_id || !$bank_account_id || !$bank_transaction_id || !$system_reference_id || empty($system_reference_type) || $reconciled_amount <= 0) {
                throw new Exception("All required fields must be filled and amount must be greater than zero.");
            }

            // Validate system_reference_type
            if (!in_array($system_reference_type, ['sale', 'purchase', 'manual'])) {
                throw new Exception("Invalid reference type.");
            }

            // Validate bank transaction
            $stmt = $conn->prepare("SELECT amount FROM bank_transactions WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $bank_transaction_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Invalid bank transaction ID.");
            }
            $transaction = $result->fetch_assoc();
            $stmt->close();

            // Validate amount
            if (abs($transaction['amount'] - $reconciled_amount) > 0.01) {
                throw new Exception("Reconciled amount does not match transaction amount.");
            }

            // Update reconciliation
            $stmt = $conn->prepare("
                UPDATE bank_reconciliation
                SET bank_account_id = ?, bank_transaction_id = ?, system_reference_id = ?, system_reference_type = ?, reconciled_amount = ?, notes = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->bind_param("iiisdsi", $bank_account_id, $bank_transaction_id, $system_reference_id, $system_reference_type, $reconciled_amount, $notes, $user_id, $reconciliation_id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();

            // Update bank transaction
            $stmt = $conn->prepare("UPDATE bank_transactions SET reconciled = TRUE, reconciled_date = NOW() WHERE id = ?");
            $stmt->bind_param("i", $bank_transaction_id);
            $stmt->execute();
            $stmt->close();

            $success_message = "Reconciliation updated successfully";
        } elseif (isset($_POST['delete_reconciliation'])) {
            $debug_messages[] = "Delete reconciliation action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            // Fetch reconciliation to get bank_transaction_id
            $stmt = $conn->prepare("SELECT bank_transaction_id FROM bank_reconciliation WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Reconciliation not found for ID: $id");
            }
            
            $reconciliation = $result->fetch_assoc();
            $bank_transaction_id = $reconciliation['bank_transaction_id'];
            $stmt->close();

            // Delete reconciliation
            $stmt = $conn->prepare("DELETE FROM bank_reconciliation WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $stmt->error);
            }
            $stmt->close();

            // Update bank transaction
            $stmt = $conn->prepare("UPDATE bank_transactions SET reconciled = FALSE, reconciled_date = NULL WHERE id = ?");
            $stmt->bind_param("i", $bank_transaction_id);
            $stmt->execute();
            $stmt->close();

            $success_message = "Reconciliation deleted successfully";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $debug_messages[] = "Error: $error_message | Line: " . $e->getLine();
        error_log("Error in auto_bank_reconciliation.php: $error_message | Line: " . $e->getLine());
    }

    file_put_contents('debug.log', implode("\n", $debug_messages) . "\n", FILE_APPEND);
}

// Pagination and filters
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;
$filter_type = $_POST['filter_type'] ?? 'monthly';
$selected_month = $_POST['month'] ?? date('Y-m');
$selected_year = $_POST['year'] ?? date('Y');
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
$timezone = new DateTimeZone('Asia/Kolkata');

// Fetch transactions and reconciliations
try {
   $query = "
    SELECT bt.id AS transaction_id, bt.bank_account_id, bt.transaction_type, bt.amount, bt.transaction_date, bt.description,
           bt.cheque_number, bt.e_payment_reference, bt.reconciled, bt.reconciled_date,
           ba.bank_name, ba.account_number,
           br.id AS reconciliation_id, br.system_reference_id, br.system_reference_type, br.reconciled_amount, br.status, br.notes
    FROM bank_transactions bt
    JOIN bank_accounts ba ON bt.bank_account_id = ba.id
    LEFT JOIN bank_reconciliation br ON bt.id = br.bank_transaction_id AND br.created_by = ?
    WHERE bt.created_by = ? AND bt.status = 'completed'
";
    $params = [$user_id, $user_id];
    $param_types = "ii";

    if ($bank_account_id) {
        $query .= " AND bt.bank_account_id = ?";
        $params[] = $bank_account_id;
        $param_types .= "i";
    }
    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(bt.transaction_date, '%Y-%m') = ?";
        $params[] = $selected_month;
        $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(bt.transaction_date) = ?";
        $params[] = $selected_year;
        $param_types .= "i";
    } elseif ($filter_type === 'custom') {
        if (empty($start_date) || empty($end_date)) {
            throw new Exception("Start date and end date are required for custom filter.");
        }
        $start = new DateTime($start_date, $timezone);
        $end = new DateTime($end_date, $timezone);
        if ($start > $end) {
            throw new Exception("Start date cannot be after end date.");
        }
        $query .= " AND bt.transaction_date BETWEEN ? AND ?";
        $params[] = $start->format('Y-m-d 00:00:00');
        $params[] = $end->format('Y-m-d 23:59:59');
        $param_types .= "ss";
    }

    // Count total records
   $count_query = str_replace("SELECT bt.id AS transaction_id, bt.bank_account_id, bt.transaction_type, bt.amount, bt.transaction_date, bt.description, bt.cheque_number, bt.e_payment_reference, bt.reconciled, bt.reconciled_date, ba.bank_name, ba.account_number, br.id AS reconciliation_id, br.system_reference_id, br.system_reference_type, br.reconciled_amount, br.status, br.notes", "SELECT COUNT(*) as total", $query);
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $total_pages = ceil($total_records / $records_per_page);

    // Fetch paginated data
    $query .= " ORDER BY bt.transaction_date DESC LIMIT ?, ?";
    $params[] = $start_from;
    $params[] = $records_per_page;
    $param_types .= "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    error_log("Query Error: $error_message | Line: " . $e->getLine());
    $debug_messages[] = "Query Error: $error_message | Line: " . $e->getLine();
    file_put_contents('debug.log', "Query Error: $error_message | Line: " . $e->getLine() . "\n", FILE_APPEND);
    $result = null;
}

// Auto-match transactions
$sanitized_transactions = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['match_status'] = $row['reconciled'] ? ($row['status'] ?? 'Matched') : 'Unmatched';
        if (!$row['reconciled']) {
            $match_query = "";
            $match_params = [];
            $match_param_types = "";
            if ($row['transaction_type'] === 'credit') {
                $match_query = "
                    SELECT id, total_price AS amount, created_at
                    FROM sales
                    WHERE created_by = ? AND total_price = ? AND created_at BETWEEN ? AND ?
                ";
                $start_match = (new DateTime($row['transaction_date']))->modify('-3 days')->format('Y-m-d 00:00:00');
                $end_match = (new DateTime($row['transaction_date']))->modify('+3 days')->format('Y-m-d 23:59:59');
                $match_params = [$user_id, $row['amount'], $start_match, $end_match];
                $match_param_types = "idss";
            } elseif ($row['transaction_type'] === 'debit') {
                $match_query = "
                    SELECT id, total_price_with_gst AS amount, created_at
                    FROM products
                    WHERE created_by = ? AND total_price_with_gst = ? AND created_at BETWEEN ? AND ?
                ";
                $start_match = (new DateTime($row['transaction_date']))->modify('-3 days')->format('Y-m-d 00:00:00');
                $end_match = (new DateTime($row['transaction_date']))->modify('+3 days')->format('Y-m-d 23:59:59');
                $match_params = [$user_id, $row['amount'], $start_match, $end_match];
                $match_param_types = "idss";
            }

            if ($match_query) {
                $stmt = $conn->prepare($match_query);
                $stmt->bind_param($match_param_types, ...$match_params);
                $stmt->execute();
                $match_result = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($match_result) {
                    $row['match_status'] = 'Matched (Auto)';
                    $row['system_reference_id'] = $match_result['id'];
                    $row['system_reference_type'] = $row['transaction_type'] === 'credit' ? 'sale' : 'purchase';
                    $row['reconciled_amount'] = $match_result['amount'];

                    // Save auto-match
                    $stmt = $conn->prepare("
                        INSERT INTO bank_reconciliation (bank_account_id, bank_transaction_id, system_reference_id, system_reference_type, reconciled_amount, reconciled_date, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW(), 'matched', ?, NOW())
                    ");
                    $stmt->bind_param("iiisdi", $row['bank_account_id'], $row['transaction_id'], $match_result['id'], $row['system_reference_type'], $row['amount'], $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE bank_transactions SET reconciled = TRUE, reconciled_date = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $row['transaction_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        $sanitized_transactions[] = $row;
    }
}
?>

<style>
    :root {
        --primary: #007bff;
        --secondary: #6c757d;
        --background: #f5f7fa;
        --card-bg: #ffffff;
        --grey-light: #e9ecef;
        --text-primary: #333;
        --text-secondary: #6c757d;
        --orange: #fe9f43;
        --red: #ff3300;
        --green: #00b328;
    }

    .content {
        margin-top: 80px;
        margin-left: 280px;
        padding: 20px 30px;
        min-height: calc(100vh - 112px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    .page-header {
        margin-right: auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .page-title h4 {
        font-weight: 700;
        margin: 0;
    }

    .page-title h6 {
        font-weight: 400;
        color: var(--text-secondary);
        margin: 0;
    }

    .table-top-head {
        display: flex;
        gap: 10px;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .table-top-head a {
        color: var(--text-primary);
        text-decoration: none;
        display: flex;
        align-items: center;
    }

    .table-top-head a:hover {
        color: var(--primary);
    }

    .table-top-head .pdf-icon {
        color: var(--red);
    }

    .table-top-head .excel-icon {
        color: var(--green);
    }

    .page-btn {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-btn .btn {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .btn-custom {
        background-color: var(--orange) !important;
        border-color: var(--orange) !important;
    }

    .btn-custom:hover {
        background-color: #e68f3b !important;
        border-color: #e68f3b !important;
    }

    .search-set {
        display: flex;
        align-items: center;
        position: relative;
    }

    .btn-searchset {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
        z-index: 10;
    }

    .search-input input {
        padding-left: 35px;
        border-radius: 5px;
        height: 38px;
        width: 100%;
    }

    .table-responsive table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        vertical-align: middle;
        padding: 10px;
        text-align: left;
    }

    .table th {
        background: var(--grey-light);
        border-bottom: 2px solid var(--secondary);
    }

    .action-table-data a {
        color: var(--text-primary);
        margin-right: 5px;
    }

    .action-table-data a:hover {
        color: var(--primary);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .pagination a {
        padding: 5px 10px;
        text-decoration: none;
        color: #fff;
        background: var(--orange);
        border-radius: 5px;
    }

    .pagination a.active {
        background: #e68f3b;
        color: #fff;
    }

    .pagination a:hover:not(.active) {
        background: #d68a39;
        color: #fff;
    }

    .alert-success {
        background-color: var(--green);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .alert-danger {
        background-color: var(--red);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .modal-content {
        border-radius: 5px;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 15px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .page-btn {
            flex-direction: column;
            align-items: flex-start;
        }
        .table-top-head {
            margin-top: 10px;
        }
        .card-header {
            flex-direction: column;
            align-items: stretch;
        }
        .card-header .search-set {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>Auto Bank Reconciliation</h4>
            <h6>Match bank transactions with system records</h6>
        </div>
        <div class="page-btn">
            <a href="#" class="btn btn-custom btn-sm" data-bs-toggle="modal" data-bs-target="#reconciliationModal"><i class="fas fa-circle-plus me-1"></i>Add Reconciliation</a>
            <ul class="table-top-head">
                <li>
                    <a href="#" class="pdf-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pdf" data-bs-original-title="Pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
                </li>
                <li>
                    <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
                </li>
            </ul>
        </div>
    </div>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <span class="btn-searchset"><i class="fas fa-search fs-14"></i></span>
                    <input type="search" class="form-control form-control-sm" placeholder="Search" aria-controls="reconciliationTable" onkeyup="searchTable()">
                </div>
            </div>
            <form class="filter-form" method="post">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-group">
                        <label for="filter_type">Filter By</label>
                        <select class="form-control" id="filter_type" name="filter_type" onchange="toggleFilterFields()">
                            <option value="monthly" <?php echo $filter_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="yearly" <?php echo $filter_type === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                            <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    <div class="form-group" id="month_field" style="<?php echo $filter_type !== 'monthly' ? 'display:none;' : ''; ?>">
                        <label for="month">Select Month</label>
                        <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
                    </div>
                    <div class="form-group" id="year_field" style="<?php echo $filter_type !== 'yearly' ? 'display:none;' : ''; ?>">
                        <label for="year">Select Year</label>
                        <select class="form-control" id="year" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" id="start_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="form-group" id="end_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bank_account_id">Bank Account</label>
                        <select class="form-control" id="bank_account_id" name="bank_account_id">
                            <option value="0">All Accounts</option>
                            <?php foreach ($bank_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>" <?php echo $bank_account_id == $account['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-custom">Apply Filter</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="reconciliationTable" aria-describedby="reconciliationTable_info">
                   <!-- Update table header -->
<thead class="thead-light">
    <tr>
        <th>S.No.</th>
        <th>Bank</th>
        <th>Account Number</th>
        <th>Transaction Type</th>
        <th>Amount</th>
        <th>Transaction Date</th>
        <th>Description</th>
        <th>Match Status</th>
        <th>System Reference</th>
        <th>Notes</th>
        <th>Reconciled On</th>
        <th class="no-sort">Action</th>
    </tr>
</thead>
                    <!-- Update table body -->
<tbody>
    <?php
    $sno = $start_from + 1;
    if ($result && $result->num_rows > 0) {
        foreach ($sanitized_transactions as $row) {
            $transaction_date = (new DateTime($row['transaction_date'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
            $reconciled_date = $row['reconciled_date'] ? (new DateTime($row['reconciled_date'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d H:i:s') : 'Not Reconciled';
            $system_ref = $row['system_reference_id'] ? ($row['system_reference_type'] . ' #' . $row['system_reference_id']) : 'N/A';
            ?>
            <tr>
                <td><?php echo $sno++; ?></td>
                <td><?php echo htmlspecialchars($row['bank_name']); ?></td>
                <td><?php echo htmlspecialchars($row['account_number']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($row['transaction_type'])); ?></td>
                <td><?php echo number_format($row['amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($transaction_date); ?></td>
                <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($row['match_status']); ?></td>
                <td><?php echo htmlspecialchars($system_ref); ?></td>
                <td><?php echo htmlspecialchars($row['notes'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($reconciled_date); ?></td>
                <td class="action-table-data">
                    <div class="edit-delete-action">
                        <?php if ($row['reconciliation_id']): ?>
                            <a class="me-2 p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editReconciliationModal" 
                               onclick="populateEditModal(<?php echo $row['reconciliation_id']; ?>, <?php echo $row['bank_account_id']; ?>, <?php echo $row['transaction_id']; ?>, '<?php echo addslashes($row['bank_name'] . ' - ' . $row['account_number']); ?>', '<?php echo addslashes($row['transaction_type']); ?>', '<?php echo $row['amount']; ?>', '<?php echo $row['system_reference_type']; ?>', <?php echo $row['system_reference_id']; ?>, '<?php echo $row['reconciled_amount']; ?>', '<?php echo addslashes($row['notes'] ?? ''); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </a>
                            <a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['reconciliation_id']; ?>;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </a>
                        <?php else: ?>
                            <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#reconciliationModal" onclick='addReconciliation(<?php echo json_encode($row); ?>)'>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo "<tr><td colspan='12'>No transactions found.</td></tr>";
    }
    ?>
</tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="pagination">
        <?php
        if ($result && $total_pages > 1) {
            if ($page > 1) {
                echo "<a href='?page=" . ($page - 1) . "' class='prev'>Previous</a>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page=" . $i . "' class='" . ($page == $i ? 'active' : '') . "'>" . $i . "</a>";
            }
            if ($page < $total_pages) {
                echo "<a href='?page=" . ($page + 1) . "' class='next'>Next</a>";
            }
        }
        ?>
    </div>
</div>

<!-- Add Reconciliation Modal -->
<div class="modal fade" id="reconciliationModal" tabindex="-1" aria-labelledby="reconciliationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="reconciliationModalLabel">Add Reconciliation</h4>
                <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="reconciliationForm" action="auto_bank_reconciliation.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="bank_account_id" id="bankAccountId">
                    <input type="hidden" name="bank_transaction_id" id="bankTransactionId">
                    <div class="mb-3">
                        <label for="bank_account_display" class="form-label">Bank Account<span class="text-danger ms-1">*</span></label>
                        <input type="text" class="form-control" id="bank_account_display" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_type" class="form-label">Transaction Type<span class="text-danger ms-1">*</span></label>
                        <input type="text" class="form-control" id="transaction_type" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount<span class="text-danger ms-1">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="system_reference_type" class="form-label">Reference Type<span class="text-danger ms-1">*</span></label>
                        <select class="form-control" id="system_reference_type" name="system_reference_type" required>
                            <option value="sale">Sale</option>
                            <option value="purchase">Purchase</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="system_reference_id" class="form-label">Reference ID<span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" id="system_reference_id" name="system_reference_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="reconciled_amount" class="form-label">Reconciled Amount<span class="text-danger ms-1">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="reconciled_amount" name="reconciled_amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_reconciliation" class="btn btn-custom">Add Reconciliation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Reconciliation Modal -->
<div class="modal fade" id="editReconciliationModal" tabindex="-1" aria-labelledby="editReconciliationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="editReconciliationModalLabel">Edit Reconciliation</h4>
                <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="editReconciliationForm" action="auto_bank_reconciliation.php" method="post">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="bank_account_id" id="edit_bankAccountId">
                <input type="hidden" name="bank_transaction_id" id="edit_bankTransactionId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_bank_account_display" class="form-label">Bank Account<span class="text-danger ms-1">*</span></label>
                        <input type="text" class="form-control" id="edit_bank_account_display" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_transaction_type" class="form-label">Transaction Type<span class="text-danger ms-1">*</span></label>
                        <input type="text" class="form-control" id="edit_transaction_type" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Amount<span class="text-danger ms-1">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_system_reference_type" class="form-label">Reference Type<span class="text-danger ms-1">*</span></label>
                        <select class="form-control" id="edit_system_reference_type" name="system_reference_type" required>
                            <option value="sale">Sale</option>
                            <option value="purchase">Purchase</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_system_reference_id" class="form-label">Reference ID<span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" id="edit_system_reference_id" name="system_reference_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_reconciled_amount" class="form-label">Reconciled Amount<span class="text-danger ms-1">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="edit_reconciled_amount" name="reconciled_amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="edit_notes" name="notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_reconciliation" class="btn btn-custom">Update Reconciliation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this reconciliation?
                <form method="post">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_reconciliation" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();

    $('#reconciliationModal').on('hidden.bs.modal', function() {
        $('#reconciliationForm')[0].reset();
        $('#bankAccountId').val('');
        $('#bankTransactionId').val('');
    });

    $('#editReconciliationModal').on('hidden.bs.modal', function() {
        $('#editReconciliationForm')[0].reset();
        $('#edit_bankAccountId').val('');
        $('#edit_bankTransactionId').val('');
    });

    $('#reconciliationForm, #editReconciliationForm').on('submit', function(e) {
        console.log('Form submitted with data:', $(this).serialize());
    });

    window.toggleFilterFields = function() {
        const filterType = $('#filter_type').val();
        $('#month_field').toggle(filterType === 'monthly');
        $('#year_field').toggle(filterType === 'yearly');
        $('#start_date_field').toggle(filterType === 'custom');
        $('#end_date_field').toggle(filterType === 'custom');
    };
});

function addReconciliation(transaction) {
    console.log('Adding reconciliation for transaction:', transaction);
    $('#bankAccountId').val(transaction.bank_account_id || '');
    $('#bankTransactionId').val(transaction.transaction_id || '');
    $('#bank_account_display').val(transaction.bank_name + ' - ' + transaction.account_number);
    $('#transaction_type').val(transaction.transaction_type || '');
    $('#amount').val(transaction.amount || '');
    $('#reconciled_amount').val(transaction.amount || '');
}

function populateEditModal(id, bank_account_id, bank_transaction_id, bank_account_display, transaction_type, amount, system_reference_type, system_reference_id, reconciled_amount, notes) {
    $('#edit_id').val(id);
    $('#edit_bankAccountId').val(bank_account_id);
    $('#edit_bankTransactionId').val(bank_transaction_id);
    $('#edit_bank_account_display').val(bank_account_display);
    $('#edit_transaction_type').val(transaction_type);
    $('#edit_amount').val(amount);
    $('#edit_system_reference_type').val(system_reference_type);
    $('#edit_system_reference_id').val(system_reference_id);
    $('#edit_reconciled_amount').val(reconciled_amount);
    $('#edit_notes').val(notes);
}

function searchTable() {
    let input = document.querySelector('.search-input input').value.toLowerCase();
    let table = document.getElementById('reconciliationTable');
    let tr = table.getElementsByTagName('tr');

    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td')[6]; // Description column
        if (td) {
            let text = td.textContent || td.innerText;
            tr[i].style.display = text.toLowerCase().indexOf(input) > -1 ? '' : 'none';
        }
    }
}
// Update exportToPDF
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({
        html: '#reconciliationTable',
        columns: [
            { header: 'S.No.', dataKey: 'sno' },
            { header: 'Bank', dataKey: 'bank_name' },
            { header: 'Account Number', dataKey: 'account_number' },
            { header: 'Transaction Type', dataKey: 'transaction_type' },
            { header: 'Amount', dataKey: 'amount' },
            { header: 'Transaction Date', dataKey: 'transaction_date' },
            { header: 'Description', dataKey: 'description' },
            { header: 'Match Status', dataKey: 'match_status' },
            { header: 'System Reference', dataKey: 'system_reference' },
            { header: 'Notes', dataKey: 'notes' },
            { header: 'Reconciled On', dataKey: 'reconciled_date' }
        ],
        didParseCell: function(data) {
            if (data.column.dataKey === 'description' && data.cell.raw) {
                const text = data.cell.raw.textContent || data.cell.raw.innerText;
                data.cell.text = [text];
            }
        }
    });
    doc.save('bank_reconciliation.pdf');
}
/ Update exportToExcel
function exportToExcel() {
    const table = document.getElementById('reconciliationTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "S.No.,Bank,Account Number,Transaction Type,Amount,Transaction Date,Description,Match Status,System Reference,Notes,Reconciled On\n";

    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td');
        if (cols.length < 12) continue;
        const rowData = [
            cols[0].innerText.replace(/,/g, ''),
            cols[1].innerText.replace(/,/g, ''),
            cols[2].innerText.replace(/,/g, ''),
            cols[3].innerText.replace(/,/g, ''),
            cols[4].innerText.replace(/,/g, ''),
            cols[5].innerText.replace(/,/g, ''),
            cols[6].innerText.replace(/,/g, ''),
            cols[7].innerText.replace(/,/g, ''),
            cols[8].innerText.replace(/,/g, ''),
            cols[9].innerText.replace(/,/g, ''),
            cols[10].innerText.replace(/,/g, '')
        ];
        csvContent += rowData.join(',') + '\n';
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'bank_reconciliation.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
ob_end_flush();
?>