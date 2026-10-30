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

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = '';
$payments = [];

try {
    // Get all payments with customer information
    $query = "
        SELECT 
            cp.id,
            cb.bill_number,
            cb.customer_name,
            cb.mobile_number,
            cp.amount,
            cp.payment_date,
            cp.notes,
            u.full_name as received_by
        FROM cash_bill_payments cp
        JOIN cash_bills cb ON cp.cash_bill_id = cb.id
        LEFT JOIN users u ON cp.created_by = u.id
        ORDER BY cp.payment_date DESC, cp.created_at DESC
    ";
    
    $result = $conn->query($query);
    if ($result) {
        $payments = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = 'Failed to fetch payment history: ' . $e->getMessage();
}
?>

<style>
:root {
    --billing-base: #571F5A;
    --billing-hover: #6d2a70;
    --billing-active: #3e1641;

    --text-primary: #1A202C;
    --text-light: #FFFFFF;
    --text-secondary: #4a5568;

    --card-bg: #FFFFFF;
    --canvas-cream: #f4f4f6;

    --danger: #e11d48;
    --success: #16a34a;
    --accent: #2563eb;

    --grey-light: #e5e7eb;
}

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--canvas-cream);
    color: var(--text-primary);
}

/* CONTENT */
.content {
    margin-top: 70px;
    margin-left: 260px;
    padding: 20px;
    min-height: calc(100vh - 70px);
    background: var(--canvas-cream);
}

/* HEADER */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title h4 {
    font-size: 22px;
    font-weight: 700;
    color: var(--billing-base);
}

.page-title h6 {
    font-size: 14px;
    color: var(--text-secondary);
}

/* CARD */
.card {
    border: none;
    border-radius: 10px;
    background: var(--card-bg);
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.card-body {
    padding: 20px;
}

/* TABLE */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f1f1f1;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 13px;
    padding: 12px;
}

.table td {
    padding: 12px;
    border-bottom: 1px solid var(--grey-light);
    font-size: 14px;
}

.table tbody tr:hover {
    background: #fafafa;
}

/* BUTTON BASE */
.btn {
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 14px;
    border: none;
    transition: 0.2s;
}

/* PRIMARY BUTTON (MAIN ACTION) */
.btn-primary {
    background: var(--billing-base);
    color: #fff;
}

.btn-primary:hover {
    background: var(--billing-hover);
}

/* SECONDARY BUTTON */
.btn-secondary {
    background: #e5e7eb;
    color: #111;
}

.btn-secondary:hover {
    background: #d1d5db;
}

/* SUCCESS BUTTON */
.btn-success {
    background: var(--success);
    color: #fff;
}

/* DANGER BUTTON */
.btn-danger {
    background: var(--danger);
    color: #fff;
}

/* SMALL BUTTON */
.btn-sm {
    font-size: 12px;
    padding: 6px 10px;
}

/* PREMIUM BUTTON (for main CTA like Receive Payment) */
.btn-premium {
    background: #571F5A;
    color: #fff;
    border-radius: 6px;
    padding: 8px 16px;
}

.btn-premium:hover {
    background: #6d2a70;
}

/* OUTSTANDING BUTTON FIX */
.btn-outstanding {
    background: #571F5A;
    color: #fff;
    padding: 8px 16px;
    border-radius: 6px;
}

.btn-outstanding:hover {
    background: #6d2a70;
}

/* ALERT */
.alert-success {
    background: #dcfce7;
    color: #166534;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}

/* BADGE */
.badge {
    padding: 5px 8px;
    border-radius: 5px;
    font-size: 11px;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

/* MODAL */
.modal-content {
    border-radius: 10px;
    border: none;
}

.modal-header {
    background: #f9fafb;
}

.modal-title {
    font-weight: 600;
}

/* INPUT */
.form-control {
    border-radius: 6px;
    border: 1px solid var(--grey-light);
    font-size: 14px;
}

.form-control:focus {
    border-color: var(--billing-base);
    box-shadow: 0 0 0 2px rgba(87,31,90,0.15);
}

/* TABLE FOOTER */
.table tfoot {
    background: #fafafa;
    font-weight: 600;
}

/* ICON BUTTONS */
.action-buttons i {
    margin-right: 4px;
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .content {
        margin-left: 0;
        padding: 10px;
    }
}
</style>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Cash Bill Received-Payments History</h4>
            <h6>View all cash bill payments</h6>
        </div>
        <div class="page-btn">
            
           
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <div class="card">
        
        <div class="card-body p-0">
            <?php if (!empty($payments)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Id</th>
                                <th>Bill No.</th>
                                <th>Date</th>
                                <th>Customer Name</th>
                                <th>Mobile</th>
                                <th class="text-end">Amount (₹)</th>
                                <th>Received By</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalAmount = 0;
                            foreach ($payments as $index => $payment): 
                                $totalAmount += $payment['amount'];
                            ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['bill_number']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['customer_name'] ?: 'Walk-in Customer'); ?></td>
                                    <td><?php echo !empty($payment['mobile_number']) ? htmlspecialchars($payment['mobile_number']) : '-'; ?></td>
                                    <td class="text-end">₹<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['received_by'] ?? 'N/A'); ?></td>
                                    <td class="action-buttons no-print">
                                        
                                       <a href="print_cash_bill_receipt.php?bill_id=<?php echo $payment['id']; ?>&print=1" 
   target="_blank" 
   class="btn btn-sm btn-primary">
   <i class="fas fa-print"></i> Print Money Receipt
</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-active">
                                <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                <td class="text-end"><strong>₹<?php echo number_format($totalAmount, 2); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <p class="mb-0">No payment records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Print Receipt Modal -->
<div class="modal fade" id="printReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="receiptContent">
                <!-- Receipt content will be loaded here via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading receipt...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
               <a href="print_cash_bill_receipt.php?bill_id=<?php echo $payment['id']; ?>&print=1" 
   target="_blank" 
   class="btn btn-sm btn-primary">
    <i class="fas fa-print"></i> Print Money Receipt
</a>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

<!-- jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>

    
// Print receipt functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle print receipt button clicks
    document.querySelectorAll('.print-receipt').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const paymentId = this.getAttribute('data-payment-id');
            const billId = <?php echo $bill_id; ?>;
            
            // Show loading state
            const receiptContent = document.getElementById('receiptContent');
            receiptContent.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading receipt...</p>
                </div>
            `;
            
            // Load receipt content via AJAX
            fetch(`ajax/get_cash_payment_receipt.php?bill_id=${billId}&payment_id=${paymentId}`)
                .then(response => response.text())
                .then(html => {
                    receiptContent.innerHTML = html;
                    // Initialize any tooltips in the loaded content
                    var tooltipTriggerList = [].slice.call(receiptContent.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                })
                .catch(error => {
                    console.error('Error loading receipt:', error);
                    receiptContent.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Failed to load receipt. Please try again.
                        </div>
                    `;
                });
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('printReceiptModal'));
            modal.show();
        });
    });


    
});
</script>

