<?php
require_once 'config.php';

// Start session and output buffering before including layout
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();
include('layout.php');

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = '';

// Handle form submission for receiving payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_payment'])) {
    try {
        $cash_bill_id = (int)($_POST['cash_bill_id'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $reference_no = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!$cash_bill_id || !$payment_method || $amount <= 0) {
            throw new Exception('Please fill in all required fields.');
        }

        // Start transaction
        $conn->begin_transaction();

        // Get bill details and calculate outstanding
        $stmt = $conn->prepare("
            SELECT cb.*, 
                   (SELECT COALESCE(SUM(amount), 0) 
                    FROM cash_bill_payments 
                    WHERE cash_bill_id = ?) as total_paid
            FROM cash_bills cb 
            WHERE cb.id = ?
        ");
        $stmt->bind_param('ii', $cash_bill_id, $cash_bill_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$bill) {
            throw new Exception('Cash bill not found.');
        }

        $outstanding = $bill['total_amount'] - $bill['total_paid'];
        
        if ($amount > $outstanding) {
            throw new Exception('Amount exceeds outstanding balance of ₹' . number_format($outstanding, 2));
        }

        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO cash_bill_payments 
            (cash_bill_id, amount, payment_method, payment_date, reference_no, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('idssssi', 
            $cash_bill_id, 
            $amount, 
            $payment_method,
            $payment_date,
            $reference_no,
            $notes,
            $user_id
        );
        $stmt->execute();
        $payment_id = $stmt->insert_id;
        $stmt->close();

        // Update paid amount in cash_bills table
        $new_paid = $bill['total_paid'] + $amount;
        $stmt = $conn->prepare("UPDATE cash_bills SET paid_amount = ? WHERE id = ?");
        $stmt->bind_param('di', $new_paid, $cash_bill_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        
        $_SESSION['success_message'] = 'Payment of ₹' . number_format($amount, 2) . ' received successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Payment Error: " . $e->getMessage());
    }
}

// Fetch outstanding cash bills with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

// Add search conditions
if (!empty($search)) {
    $search_term = "%$search%";
    $where_conditions[] = "(cb.bill_number LIKE ? OR cb.customer_name LIKE ? OR cb.mobile_number LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= 'sss';
}

// Add user restriction if not admin
if (!$is_admin) {
    $where_conditions[] = "cb.created_by = ?";
    $params[] = $user_id;
    $types .= 'i';
}

// Only show bills with outstanding amount > 0
$where_conditions[] = "(cb.total_amount - COALESCE((SELECT SUM(amount) FROM cash_bill_payments WHERE cash_bill_id = cb.id), 0)) > 0";

// Build final query
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM cash_bills cb
    $where_clause
";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);
$stmt->close();

// Get bills with outstanding amounts
$query = "
    SELECT 
        cb.*,
        COALESCE((SELECT SUM(amount) FROM cash_bill_payments WHERE cash_bill_id = cb.id), 0) as total_paid,
        (cb.total_amount - COALESCE((SELECT SUM(amount) FROM cash_bill_payments WHERE cash_bill_id = cb.id), 0)) as outstanding,
        u.full_name as created_by_name
    FROM cash_bills cb
    LEFT JOIN users u ON cb.created_by = u.id
    $where_clause
    ORDER BY cb.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types .= 'ii';
    $params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}

$stmt->execute();
$bills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
:root {
    --primary: #0284c7;
    --primary-dark: #0b1220;
    --primary-light: #0ea5e9;
    --text-dark: #1f2937;
    --text-light: #ffffff;
    --bg-light: #f9fafb;
    --border: #e5e7eb;
    --success: #16a34a;
    --danger: #dc2626;
}

/* Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f4f4f5;
    color: var(--text-dark);
}

/* Layout */
.content {
    margin-top: 70px;
    margin-left: 260px;
    padding: 20px;
    background: #f4f4f5;
}

@media (max-width: 992px) {
    .content {
        margin-left: 0;
    }
}

/* Header */
.page-header {
    background: var(--primary);
    padding: 16px 22px;
    border-radius: 10px;
    color: #fff;
    margin-bottom: 20px;
}

.page-title h4 {
    font-size: 22px;
    font-weight: 700;
}

.page-title h6 {
    font-size: 14px;
    opacity: 0.85;
}

/* Card */
.card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid var(--border);
    box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.card-body {
    padding: 20px;
}

/* Table */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f3f4f6;
    font-size: 13px;
    text-transform: uppercase;
    font-weight: 600;
    padding: 12px;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}

.table tbody tr:hover {
    background: #fafafa;
}

/* Buttons */
.btn {
    border-radius: 6px;
    font-weight: 600;
    font-size: 13px;
    padding: 8px 14px;
    border: none;
    transition: 0.2s;
}

/* Primary Button */
.btn-primary {
    background: var(--primary);
    color: #fff;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

/* Secondary */
.btn-secondary {
    background: #6b7280;
    color: #fff;
}

.btn-secondary:hover {
    background: #4b5563;
}

/* Success */
.btn-success {
    background: var(--success);
    color: #fff;
}

.btn-success:hover {
    background: #15803d;
}

/* Danger */
.btn-danger {
    background: var(--danger);
    color: #fff;
}

.btn-danger:hover {
    background: #b91c1c;
}

/* Action Button (Receive Payment) */
.receive-payment {
    background: var(--primary);
    color: #fff;
}

.receive-payment:hover {
    background: var(--primary-dark);
}

/* Alerts */
.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    text-align: center;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Modal */
.modal-content {
    border-radius: 10px;
    border: none;
}

.modal-header {
    background: var(--primary);
    color: #fff;
    padding: 15px;
}

.modal-title {
    font-weight: 600;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px;
}

/* Form */
.form-control,
.form-select {
    border-radius: 6px;
    border: 1px solid var(--border);
    padding: 10px;
    font-size: 14px;
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(2,132,199,0.15);
}

/* DataTables Fix */
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid var(--border);
    padding: 6px 10px;
    border-radius: 6px;
}

.dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--border);
    padding: 5px;
    border-radius: 6px;
}

/* Pagination */
.pagination .page-link {
    color: var(--primary);
    border-radius: 6px;
    margin: 2px;
}

.pagination .active .page-link {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Footer Total Row */
tfoot {
    background: #f9fafb;
    font-weight: 600;
}

/* Icons inside button */
.btn i {
    margin-right: 5px;
}
</style>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Outstanding Cash Bills</h4>
            <h6>View and receive payments for outstanding cash bills</h6>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

   
    

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="outstandingBillsTable">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th class="text-end">Total (₹)</th>
                            <th class="text-end">Paid (₹)</th>
                            <th class="text-end">Outstanding (₹)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="8" class="text-center">No outstanding cash bills found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): 
                                $outstanding = $bill['total_amount'] - $bill['total_paid'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($bill['created_at'])); ?></td>
                                    <td><?php echo !empty($bill['customer_name']) ? htmlspecialchars($bill['customer_name']) : 'Walk-in Customer'; ?></td>
                                    <td><?php echo htmlspecialchars($bill['mobile_number']); ?></td>
                                    <td class="text-end"><?php echo number_format($bill['total_amount'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($bill['total_paid'], 2); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($outstanding, 2); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary receive-payment" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#receivePaymentModal"
                                                    data-bill-id="<?php echo $bill['id']; ?>"
                                                    data-bill-number="<?php echo htmlspecialchars($bill['bill_number']); ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($bill['customer_name'] ?? 'Walk-in Customer'); ?>"
                                                    data-outstanding="<?php echo $outstanding; ?>">
                                                Receive Payment
                                            </button>
                                            
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($bills)): ?>
                    <tfoot>
                        <tr class="table-active">
                            <th colspan="4" class="text-end">Total Outstanding:</th>
                            <th class="text-end">₹<?php echo number_format(array_sum(array_column($bills, 'total_amount')), 2); ?></th>
                            <th class="text-end">₹<?php echo number_format(array_sum(array_column($bills, 'total_paid')), 2); ?></th>
                            <th class="text-end">₹<?php echo number_format(array_sum(array_column($bills, 'outstanding')), 2); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1) . (!empty($search) ? '&search=' . urlencode($search) : ''); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i . (!empty($search) ? '&search=' . urlencode($search) : ''); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1) . (!empty($search) ? '&search=' . urlencode($search) : ''); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Receive Payment Modal -->
<div class="modal fade" id="receivePaymentModal" tabindex="-1" aria-labelledby="receivePaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="receivePaymentForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="receivePaymentModalLabel">Receive Payment</h5>
                    
                </div>
                <div class="modal-body">
                    <input type="hidden" name="cash_bill_id" id="cash_bill_id">
                    
                    
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount to Receive (₹) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                        <div class="form-text">Enter the amount being received</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            
                            <option value="Cash">Cash</option>
                            
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                   
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Remarks.</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    
                    <button type="submit" name="receive_payment" class="btn btn-primary">
                        <i class="fas fa-rupee-sign"></i> Receive Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<!-- Include DataTables and other JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#outstandingBillsTable').DataTable({
        "order": [[1, "desc"]], // Sort by date descending by default
        "pageLength": 25,
        "responsive": true,
        "dom": '<"top"f>rt<"bottom"lip><"clear">',
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        },
        "columnDefs": [
            { "orderable": false, "targets": [7] } // Disable sorting on actions column
        ]
    });

    // Handle receive payment button click
    $('.receive-payment').on('click', function() {
        const billId = $(this).data('bill-id');
        const billNumber = $(this).data('bill-number');
        const customerName = $(this).data('customer-name');
        const outstanding = parseFloat($(this).data('outstanding'));
        
        // Set modal values
        $('#cash_bill_id').val(billId);
        $('#bill_number').val(billNumber);
        $('#customer_name').val(customerName);
        $('#outstanding_amount').val(outstanding.toFixed(2));
        $('#amount').val(outstanding.toFixed(2));
        $('#amount').attr('max', outstanding);
        
        // Reset form
        $('#receivePaymentForm')[0].reset();
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('receivePaymentModal'));
        modal.show();
    });
    
    // Validate amount doesn't exceed outstanding
    $('#amount').on('change', function() {
        const max = parseFloat($(this).attr('max'));
        const value = parseFloat($(this).val());
        
        if (value > max) {
            alert('Amount cannot exceed outstanding amount of ' + max.toFixed(2));
            $(this).val(max.toFixed(2));
        }
    });
});
</script>

<?php
// Get the buffered content and output it
$content = ob_get_clean();
echo $content;
?>