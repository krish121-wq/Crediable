<?php
ob_start(); // Enable output buffering
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

$page_title = "Credit & Debit Transactions";
$debug_messages = []; // Array to store debug messages
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
            $transaction_type = trim($_POST['transaction_type'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $transaction_date = trim($_POST['transaction_date'] ?? '');
            $party_name = trim($_POST['party_name'] ?? '');

            // Log input values
            $debug_messages[] = "Input values: transaction_type=$transaction_type, amount=$amount, description=$description, transaction_date=$transaction_date, party_name=$party_name";

            // Validate required fields
            if (empty($transaction_type) || empty($amount) || empty($description) || empty($transaction_date) || empty($party_name)) {
                throw new Exception("All required fields must be filled.");
            }

            // Validate transaction type
            if (!in_array($transaction_type, ['credit', 'debit'])) {
                throw new Exception("Invalid transaction type.");
            }

            // Validate amount
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than zero.");
            }

            // Validate date format and ensure it's a valid date
            try {
                $date_obj = new DateTime($transaction_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            // Insert query with prepared statement
            $stmt = $conn->prepare("INSERT INTO transactions (transaction_type, amount, description, transaction_date, party_name, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sdsssi", $transaction_type, $amount, $description, $formatted_date, $party_name, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $stmt->error);
            }
            $stmt->close();
            $debug_messages[] = "Insert query executed successfully";
            $success_message = "Transaction added successfully";
        } elseif (isset($_POST['edit_transaction'])) {
            $debug_messages[] = "Edit transaction action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for edit.");

            // Fetch current record
            $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Record not found for ID: $id");
            }
            $current = $result->fetch_assoc();
            $stmt->close();

            $transaction_type = trim($_POST['transaction_type'] ?? $current['transaction_type']);
            $amount = floatval($_POST['amount'] ?? $current['amount']);
            $description = trim($_POST['description'] ?? $current['description']);
            $transaction_date = trim($_POST['transaction_date'] ?? $current['transaction_date']);
            $party_name = trim($_POST['party_name'] ?? $current['party_name']);

            // Validate required fields
            if (empty($transaction_type) || empty($amount) || empty($description) || empty($transaction_date) || empty($party_name)) {
                throw new Exception("All required fields must be filled.");
            }

            // Validate transaction type
            if (!in_array($transaction_type, ['credit', 'debit'])) {
                throw new Exception("Invalid transaction type.");
            }

            // Validate amount
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than zero.");
            }

            // Validate date format and ensure it's a valid date
            try {
                $date_obj = new DateTime($transaction_date);
                $formatted_date = $date_obj->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("Invalid date format. Use YYYY-MM-DD.");
            }

            // Update query
            $stmt = $conn->prepare("UPDATE transactions SET transaction_type = ?, amount = ?, description = ?, transaction_date = ?, party_name = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
            $stmt->bind_param("sdsssiii", $transaction_type, $amount, $description, $formatted_date, $party_name, $user_id, $id, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $stmt->error);
            }
            $stmt->close();
            $success_message = "Transaction updated successfully";
        } elseif (isset($_POST['delete_transaction'])) {
            $debug_messages[] = "Delete transaction action triggered";
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");

            $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND created_by = ?");
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
        error_log("Error in Credit&Debit.php: $error_message | Line: " . $e->getLine());
    }

    // Write debug messages to a log file
    file_put_contents('debug.log', implode("\n", $debug_messages) . "\n", FILE_APPEND);
}

// Pagination setup
$user_id = (int)$_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE created_by = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE created_by = ? ORDER BY transaction_date DESC LIMIT ?, ?");
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
        border-radius: 5 bâtiments
    }

    .alert-danger {
        background-color: var(--red);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
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
            <h4>Credit & Debit Transactions</h4>
            <h6>Manage your financial transactions</h6>
        </div>
        <div class="page-btn">
            <a href="#" class="btn btn-custom btn-sm" data-bs-toggle="modal" data-bs-target="#transactionModal"><i class="fas fa-circle-plus me-1"></i>Add Transaction</a>
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
                    <input type="search" class="form-control form-control-sm" placeholder="Search" aria-controls="transactionTable" onkeyup="searchTable()">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="transactionTable" aria-describedby="transactionTable_info">
                    <thead class="thead-light">
                        <tr>
                            <th>S.No.</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Transaction Date</th>
                            <th>Party Name</th>
                            <th>Created On</th>
                            <th>Edited On</th>
                            <th class="no-sort">Action</th>
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
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['transaction_type'])); ?></td>
                                    <td><?php echo number_format($row['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['party_name']); ?></td>
                                    <td><?php echo $created_at->format('Y-m-d H:i:s'); ?></td>
                                    <td><?php echo $updated_at ? $updated_at->format('Y-m-d H:i:s') : 'Not Edited'; ?></td>
                                    <td class="action-table-data">
                                        <div class="edit-delete-action">
                                            <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#transactionModal" onclick='editTransaction(<?php echo json_encode($row); ?>)'>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9'>No transactions found.</td></tr>";
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

<!-- Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="transactionModalLabel">Add Transaction</h4>
                <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form id="transactionForm" action="Credit&Debit.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="transactionId">
                    <div class="mb-3">
                        <label for="transaction_type" class="form-label">Transaction Type<span class="text-danger ms-1">*</span></label>
                        <select class="form-control" id="transaction_type" name="transaction_type" required>
                            <option value="credit">Credit</option>
                            <option value="debit">Debit</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount<span class="text-danger ms-1">*</span></label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description<span class="text-danger ms-1">*</span></label>
                        <textarea class="form-control" id="description" name="description" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_date" class="form-label">Transaction Date<span class="text-danger ms-1">*</span></label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="party_name" class="form-label">Party Name<span class="text-danger ms-1">*</span></label>
                        <input type="text" class="form-control" id="party_name" name="party_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_transaction" class="btn btn-custom">Add Transaction</button>
                    <button type="submit" name="edit_transaction" class="btn btn-custom" style="display:none;" id="editTransactionButton">Update Transaction</button>
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
                Are you sure you want to delete this transaction?
                <form method="post">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_transaction" class="btn btn-danger">Delete</button>
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
    
    $('#transactionModal').on('hidden.bs.modal', function() {
        $('#transactionForm')[0].reset();
        $('#transactionId').val('');
        $('button[name="add_transaction"]').show();
        $('#editTransactionButton').hide();
    });

    // Log form submission
    $('#transactionForm').on('submit', function(e) {
        console.log('Form submitted with data:', $(this).serialize());
    });
});

function editTransaction(transaction) {
    console.log('Editing transaction:', transaction);
    $('#transactionId').val(transaction.id || '');
    $('#transaction_type').val(transaction.transaction_type || '');
    $('#amount').val(transaction.amount || '');
    $('#description').val(transaction.description || '');
    $('#transaction_date').val(transaction.transaction_date || '');
    $('#party_name').val(transaction.party_name || '');
    $('button[name="add_transaction"]').hide();
    $('#editTransactionButton').show();
}

function searchTable() {
    let input = document.querySelector('.search-input input').value.toLowerCase();
    let table = document.getElementById('transactionTable');
    let tr = table.getElementsByTagName('tr');

    for (let i = 0; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td')[3]; // Description column
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
        html: '#transactionTable',
        columns: [
            { header: 'S.No.', dataKey: 'sno' },
            { header: 'Transaction Type', dataKey: 'transaction_type' },
            { header: 'Amount', dataKey: 'amount' },
            { header: 'Description', dataKey: 'description' },
            { header: 'Transaction Date', dataKey: 'transaction_date' },
            { header: 'Party Name', dataKey: 'party_name' },
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
    doc.save('transactions.pdf');
}

function exportToExcel() {
    const table = document.getElementById('transactionTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "S.No.,Transaction Type,Amount,Description,Transaction Date,Party Name,Created On,Edited On\n";

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
    link.setAttribute('download', 'transactions.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
ob_end_flush(); // Flush the output buffer
?>