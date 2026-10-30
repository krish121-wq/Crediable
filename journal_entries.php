<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// session_start();
include('layout.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Journal Entries";
$debug_messages = [];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_messages[] = "POST request received at " . date('Y-m-d H:i:s');
    $user_id = (int)$_SESSION['user_id'];
    $debug_messages[] = "User ID: $user_id";

    try {
        if (isset($_POST['add_entry'])) {
            $debug_messages[] = "Add journal entry action triggered";
            $entry_date = trim($_POST['entry_date'] ?? '');
            $account_name = trim($_POST['account_name'] ?? '');
            $debit = floatval($_POST['debit'] ?? 0);
            $credit = floatval($_POST['credit'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if (empty($entry_date) || empty($account_name)) {
                throw new Exception("All required fields must be filled.");
            }

            if ($debit < 0 || $credit < 0) {
                throw new Exception("Debit and Credit amounts must be non-negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new Exception("Cannot have both debit and credit amounts in a single entry.");
            }

            try {
                $date_obj = new DateTime($entry_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, account_name, debit, credit, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssddsi", $formatted_date, $account_name, $debit, $credit, $description, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Journal entry added successfully";
        } elseif (isset($_POST['edit_entry'])) {
            $debug_messages[] = "Edit journal entry action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for edit.");

            $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Record not found for ID: $id");
            }
            $current = $result->fetch_assoc();
            $stmt->close();

            $entry_date = trim($_POST['entry_date'] ?? $current['entry_date']);
            $account_name = trim($_POST['account_name'] ?? $current['account_name']);
            $debit = floatval($_POST['debit'] ?? $current['debit']);
            $credit = floatval($_POST['credit'] ?? $current['credit']);
            $description = trim($_POST['description'] ?? $current['description']);

            if (empty($entry_date) || empty($account_name)) {
                throw new Exception("All required fields must be filled.");
            }

            if ($debit < 0 || $credit < 0) {
                throw new Exception("Debit and Credit amounts must be non-negative.");
            }

            if ($debit > 0 && $credit > 0) {
                throw new Exception("Cannot have both debit and credit amounts in a single entry.");
            }

            try {
                $date_obj = new DateTime($entry_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            $stmt = $conn->prepare("UPDATE journal_entries SET entry_date = ?, account_name = ?, debit = ?, credit = ?, description = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ssddsiii", $formatted_date, $account_name, $debit, $credit, $description, $user_id, $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Journal entry updated successfully";
        } elseif (isset($_POST['delete_entry'])) {
            $debug_messages[] = "Delete journal entry action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            $stmt = $conn->prepare("DELETE FROM journal_entries WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Journal entry deleted successfully";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $debug_messages[] = "Error: $error_message | Line: " . $e->getLine();
        error_log("Error in journal_entries.php: $error_message | Line: " . $e->getLine());
    }

    file_put_contents('debug.log', implode("\n", $debug_messages) . "\n", FILE_APPEND);
}

// Pagination setup
$user_id = (int)$_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM journal_entries WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM journal_entries WHERE created_by = ? ORDER BY entry_date DESC LIMIT ?, ?");
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
    <title><?php echo htmlspecialchars($page_title); ?> - GROW BILLER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e1d9f4 0%, #f3e8ff 100%);
            margin: 0;
            overflow-x: hidden;
        }

        .content {
            margin-top: 10px;
            margin-left: <?php echo isset($is_restricted) && $is_restricted ? '270px' : '0'; ?>;
            padding: 20px 30px;
            min-height: calc(100vh - 112px);
            transition: margin-left 0.3s ease;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .page-title h4 {
            font-weight: 700;
            color: #4b2e8a;
            margin: 0;
        }

        .page-title h6 {
            font-weight: 400;
            color: #6c757d;
            margin: 0;
        }

        .btn {
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #fff;
            touch-action: manipulation;
        }

        .btn-custom {
            background: #5e4b8c;
            color: #fff;
        }

        .btn-custom:hover {
            background: #4b3a73;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #ff3300;
            color: #fff;
        }

        .btn-danger:hover {
            background: #e62e00;
            transform: translateY(-2px);
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
            color: #6c757d;
        }

        .search-input input {
            padding-left: 35px;
            border: none;
            border-bottom: 1px solid #c7b1f0;
            background: transparent;
            font-size: 13px;
            color: #263238;
            height: 30px;
            width: 100%;
        }

        .search-input input:focus {
            border-bottom-color: #8e44ad;
            outline: none;
        }

        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
            background: #f8faf8;
            border-radius: 8px;
            overflow: hidden;
        }

        .table th {
            background: #c7b1f0;
            color: #4b2e8a;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #d1e7dd;
        }

        .table td {
            vertical-align: middle;
            padding: 10px;
            border-bottom: 1px solid #e8f0e8;
            color: #263238;
        }

        .table tbody tr {
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #e1d9f4;
            cursor: pointer;
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
            background: #5e4b8c;
            border-radius: 5px;
        }

        .pagination a.active {
            background: #4b3a73;
            color: #fff;
        }

        .pagination a:hover:not(.active) {
            background: #4b3a73;
            color: #fff;
        }

        .alert-success {
            background-color: #8e44ad;
            color: #fff;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .alert-danger {
            background-color: #ff3300;
            color: #fff;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .modal-content {
            border-radius: 8px;
            border: none;
        }

        .modal-header {
            background: #e1d9f4;
            border-bottom: none;
        }

        .modal-title {
            color: #4b2e8a;
            font-weight: 500;
        }

        .modal-body {
            font-size: 14px;
            color: #37474f;
        }

        .modal-body input,
        .modal-body textarea {
            width: 100%;
            padding: 6px 0;
            border: none;
            border-bottom: 1px solid #c7b1f0;
            background: transparent;
            font-size: 13px;
            color: #263238;
        }

        .modal-body input:focus,
        .modal-body textarea:focus {
            border-bottom-color: #8e44ad;
            outline: none;
        }

        .modal-body .form-label {
            color: #4b2e8a;
            font-weight: 500;
        }

        .modal-footer .btn-custom {
            background: #5e4b8c;
            color: #fff;
        }

        .modal-footer .btn-custom:hover {
            background: #4b3a73;
        }

        .modal-footer .btn-danger {
            background: #ff3300;
            color: #fff;
        }

        .modal-footer .btn-danger:hover {
            background: #e62e00;
        }

        .table-top-head {
            display: flex;
            gap: 10px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .table-top-head a {
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .table-top-head .pdf-icon,
        .table-top-head .excel-icon {
            background: #5e4b8c;
            color: #fff;
            padding: 8px;
            border-radius: 6px;
        }

        .table-top-head .pdf-icon:hover,
        .table-top-head .excel-icon:hover {
            background: #4b3a73;
        }

        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1200;
            background: #5e4b8c;
            color: #fff;
            border: none;
            padding: 8px;
            border-radius: 5px;
            cursor: pointer;
        }

        .sidebar-toggle:hover {
            background: #4b3a73;
        }

        @media (max-width: 576px) {
            .sidebar {
                display: none;
            }

            .sidebar.active {
                display: block;
            }

            .sidebar-toggle {
                display: block;
            }

            .content {
                margin-left: 0;
                max-width: 100%;
                width: 100%;
                padding: clamp(0.5rem, 1.5vw, 0.75rem);
                margin-top: 50px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .page-title h4 {
                font-size: clamp(1rem, 2.5vw, 1.25rem);
            }

            .page-title h6 {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .search-set {
                width: 100%;
            }

            .search-input input {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                padding-left: clamp(1.5rem, 3vw, 1.75rem);
                height: clamp(1.5rem, 3vw, 1.75rem);
            }

            .btn-searchset {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                left: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .table-responsive table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .table th,
            .table td {
                font-size: clamp(0.625rem, 1.8vw, 0.75rem);
                padding: clamp(0.3rem, 1vw, 0.4rem);
                min-width: 80px;
            }

            .btn,
            .btn-custom,
            .btn-danger,
            .table-top-head .pdf-icon,
            .table-top-head .excel-icon {
                width: 100%;
                padding: clamp(0.5rem, 1.5vw, 0.625rem) clamp(1rem, 2vw, 1.25rem);
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                color: #fff;
            }

            .table-top-head {
                flex-direction: column;
                align-items: flex-start;
                gap: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .pagination {
                flex-wrap: wrap;
                gap: clamp(0.3rem, 1vw, 0.5rem);
            }

            .pagination a {
                font-size: clamp(0.625rem, 1.8vw, 0.75rem);
                padding: clamp(0.3rem, 1vw, 0.4rem) clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .alert-success,
            .alert-danger {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                padding: clamp(0.5rem, 1.5vw, 0.75rem);
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-title {
                font-size: clamp(0.875rem, 1.8vw, 1rem);
            }

            .modal-body {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
            }

            .modal-body input,
            .modal-body textarea {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                padding: clamp(0.3rem, 1vw, 0.4rem) 0;
            }

            .modal-body .form-label {
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
            }

            .modal-footer .btn {
                padding: clamp(0.4rem, 1.2vw, 0.5rem) clamp(0.8rem, 2vw, 1rem);
                font-size: clamp(0.75rem, 1.8vw, 0.875rem);
                color: #fff;
            }
        }
    </style>
</head>
<body>
<?php
echo '<script>document.querySelector(".sidebar").classList.remove("sidebar-hidden");</script>';
?>
<button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Journal Entries</h4>
            <h6>Manage your journal entries</h6>
        </div>
        <div class="page-btn">
            <a href="#" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#entryModal"><i class="fas fa-plus"></i> Add Entry</a>
            <ul class="table-top-head list-unstyled d-flex gap-2 mt-2">
                <li>
                    <a href="#" class="pdf-icon btn btn-custom" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="PDF" data-bs-original-title="PDF" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
                </li>
                <li>
                    <a href="#" class="excel-icon btn btn-custom" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
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
        <div class="card-body p-0">
            <div class="card-header d-flex align-items-center justify-content-end flex-wrap row-gap-3">
                <div class="search-set">
                    <div class="search-input">
                        <span class="btn-searchset"><i class="fas fa-search fs-14"></i></span>
                        <input type="search" class="form-control form-control-sm" placeholder="Search by Account Name" aria-controls="entryTable" onkeyup="searchTable()">
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table" id="entryTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Entry Date</th>
                            <th>Account Name</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Description</th>
                            <th>Created On</th>
                            <th>Edited On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $created_at = new DateTime($row['created_at'], new DateTimeZone('UTC'));
                                $created_at->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                $updated_at = $row['updated_at'] ? (new DateTime($row['updated_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata')) : null;
                                ?>
                                <tr data-id="<?php echo $row['id']; ?>">
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo htmlspecialchars($row['entry_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                    <td><?php echo number_format($row['debit'], 2); ?></td>
                                    <td><?php echo number_format($row['credit'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                    <td><?php echo $created_at->format('Y-m-d H:i:s'); ?></td>
                                    <td><?php echo $updated_at ? $updated_at->format('Y-m-d H:i:s') : 'Not Edited'; ?></td>
                                    <td>
                                        <div class="edit-delete-action d-flex gap-2">
                                            <a class="btn btn-custom btn-sm" href="#" data-bs-toggle="modal" data-bs-target="#entryModal" onclick='editEntry(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></a>
                                            <a class="btn btn-danger btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9'>No journal entries found.</td></tr>";
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

<!-- Journal Entry Modal -->
<div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="entryModalLabel">Add Journal Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="entryForm" action="journal_entries.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="entryId">
                    <div class="mb-3">
                        <label for="entry_date" class="form-label">Entry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="entry_date" name="entry_date" required aria-label="Entry Date">
                    </div>
                    <div class="mb-3">
                        <label for="account_name" class="form-label">Account Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="account_name" name="account_name" required aria-label="Account Name">
                    </div>
                    <div class="mb-3">
                        <label for="debit" class="form-label">Debit</label>
                        <input type="number" step="0.01" class="form-control" id="debit" name="debit" aria-label="Debit">
                    </div>
                    <div class="mb-3">
                        <label for="credit" class="form-label">Credit</label>
                        <input type="number" step="0.01" class="form-control" id="credit" name="credit" aria-label="Credit">
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" aria-label="Description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-custom" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_entry" class="btn btn-custom">Add Entry</button>
                    <button type="submit" name="edit_entry" class="btn btn-custom" style="display:none;" id="editEntryButton">Update Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this journal entry?
                <form method="post">
                    <input type="hidden" name="id" id="deleteId">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_entry" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
$(document).ready(function() {
    // Sidebar toggle for mobile
    function toggleSidebar() {
        if ($(window).width() <= 576) {
            $('.sidebar').addClass('sidebar-hidden').removeClass('active').css('display', 'none');
            $('#sidebarToggle').show();
            $('.content').css('margin-left', '0');
        } else {
            $('.sidebar').removeClass('sidebar-hidden').addClass('active').css({'display': 'block', 'transition': 'none'});
            $('.content').css('margin-left', '<?php echo isset($is_restricted) && $is_restricted ? "270px" : "0"; ?>');
            $('#sidebarToggle').hide();
        }
    }

    toggleSidebar();
    $(window).on('resize', toggleSidebar);

    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('sidebar-hidden active');
        if ($('.sidebar').hasClass('active')) {
            $('.sidebar').css('display', 'block');
            $('.content').css('margin-left', '0');
        } else {
            $('.sidebar').css('display', 'none');
            $('.content').css('margin-left', '0');
        }
    });

    $('[data-bs-toggle="tooltip"]').tooltip();

    $('#entryModal').on('hidden.bs.modal', function() {
        $('#entryForm')[0].reset();
        $('#entryId').val('');
        $('button[name="add_entry"]').show();
        $('#editEntryButton').hide();
        console.log('Journal entry modal reset');
    });

    $('#entryForm').on('submit', function(e) {
        console.log('Form submitted with data:', $(this).serialize());
    });

    // Handle row click for viewing entry details
    $(document).on('click', '#entryTable tbody tr', function(e) {
        if ($(e.target).closest('.edit-delete-action').length) return; // Prevent click if action buttons are clicked
        const entry = $(this).data('entry');
        if (entry) {
            editEntry(entry);
            const modal = new bootstrap.Modal(document.getElementById('entryModal'), { backdrop: 'static', keyboard: false });
            modal.show();
            console.log('Journal entry modal opened for viewing');
        }
    });

    // Store entry data in rows
    $('#entryTable tbody tr').each(function() {
        const id = $(this).data('id');
        const rowData = <?php echo json_encode($result ? $result->fetch_all(MYSQLI_ASSOC) : []); ?>.find(row => row.id == id);
        $(this).data('entry', rowData);
    });
});

function editEntry(entry) {
    console.log('Editing journal entry:', entry);
    $('#entryId').val(entry.id || '');
    $('#entry_date').val(entry.entry_date || '');
    $('#account_name').val(entry.account_name || '');
    $('#debit').val(entry.debit || '');
    $('#credit').val(entry.credit || '');
    $('#description').val(entry.description || '');
    $('button[name="add_entry"]').hide();
    $('#editEntryButton').show();
}

function searchTable() {
    let input = document.querySelector('.search-input input')?.value.toLowerCase() || '';
    let table = document.getElementById('entryTable');
    let tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td')[2]; // Account Name column
        if (td) {
            let text = td.textContent || td.innerText;
            tr[i].style.display = text.toLowerCase().indexOf(input) > -1 ? '' : 'none';
        }
    }
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({
        html: '#entryTable',
        columns: [
            { header: 'S.No.', dataKey: 'sno' },
            { header: 'Entry Date', dataKey: 'entry_date' },
            { header: 'Account Name', dataKey: 'account_name' },
            { header: 'Debit', dataKey: 'debit' },
            { header: 'Credit', dataKey: 'credit' },
            { header: 'Description', dataKey: 'description' },
            { header: 'Created On', dataKey: 'created_at' },
            { header: 'Edited On', dataKey: 'updated_at' }
        ],
        didParseCell: function(data) {
            if (data.column.dataKey === 'description' && data.cell.raw) {
                const text = data.cell.raw.textContent || data.cell.raw.innerText;
                data.cell.text = [text];
            }
        }
    });
    doc.save('journal_entries.pdf');
}

function exportToExcel() {
    const table = document.getElementById('entryTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "S.No.,Entry Date,Account Name,Debit,Credit,Description,Created On,Edited On\n";

    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td');
        const rowData = [
            cols[0].innerText.replace(/,/g, ''),
            cols[1].innerText.replace(/,/g, ''),
            cols[2].innerText.replace(/,/g, ''),
            cols[3].innerText.replace(/,/g, ''),
            cols[4].innerText.replace(/,/g, ''),
            cols[5].innerText.replace(/,/g, ''),
            cols[6].innerText.replace(/,/g, ''),
            cols[7].innerText.replace(/,/g, '')
        ];
        csvContent += rowData.join(',') + '\n';
    }

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'journal_entries.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
</body>
</html>
<?php
ob_end_flush();
?>