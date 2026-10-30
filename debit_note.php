<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include 'layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];
?>
    <style>
        /* Optimized Debit Note CSS with white-grey background, reduced text sizes, and minimal margins/padding */
:root {
    --billing-base: #0F7870; /* Teal for headers and buttons */
    --billing-hover: #1A9A90; /* Lighter teal for hover */
    --billing-active: #0A5A54; /* Darker teal for active */
    --text-primary: #1A202C; /* Dark text for contrast */
    --text-light: #FFFFFF; /* White text for buttons and headers */
    --text-secondary: #D1D5DB; /* Light grey for secondary text */
    --card-bg: #FFFFFF; /* White for cards and tables */
    --main-bg: #F5F6F5; /* White-grey background */
    --danger: #EF4444; /* Red for errors */
    --success: #22C55E; /* Green for success */
    --accent: #3B82F6; /* Blue for input focus */
    --grey-light: #E5E7EB; /* Light grey for borders */
    --shadow-base: 0 4px 12px rgba(0, 0, 0, 0.15);
    --shadow-hover: 0 6px 18px rgba(0, 0, 0, 0.2);
    --sidebar-width: 260px; /* Consistent with other pages */
    --header-height: 60px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--main-bg); /* White-grey background */
    color: var(--text-primary);
    height: 100vh;
    overflow-x: hidden;
}

.main-content {
    margin-left: var(--sidebar-width);
    margin-top: var(--header-height);
    padding: 0.5rem; /* Reduced from 20px */
    min-height: calc(100vh - var(--header-height));
    background: var(--main-bg);
    width: calc(100% - var(--sidebar-width));
    transition: margin-left 0.3s ease-in-out;
}

.sidebar-hidden ~ .main-content {
    margin-left: 0;
    width: 100%;
}

.content {
    background: var(--card-bg);
    border-radius: 8px;
    box-shadow: var(--shadow-base);
    padding: 0.75rem; /* Reduced from 30px */
    margin-bottom: 0.75rem; /* Reduced from 2rem */
}

.page-header {
    background: var(--billing-base);
    color: var(--text-light);
    padding: 0.75rem; /* Reduced from 1.5rem */
    margin: -0.5rem -0.5rem 0.5rem -0.5rem; /* Reduced from -30px/-30px/2rem */
    box-shadow: var(--shadow-base);
    border-radius: 8px 8px 0 0;
    text-align: center;
}

.page-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}

.header-table {
    width: 100%;
    margin-bottom: 0.75rem; /* Reduced from 15px */
}

.header-table td {
    padding: 0.5rem; /* Reduced from 10px */
    vertical-align: middle;
}

.header-table label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.4rem; /* Reduced from 5px */
    display: block;
}

.header-table input,
.header-table select {
    width: 100%;
    padding: 0.5rem 0; /* Reduced from 8px */
    border: none;
    border-bottom: 1px solid var(--grey-light);
    background: transparent;
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 500;
    transition: border-bottom-color 0.3s;
}

.header-table input:focus,
.header-table select:focus {
    border-bottom-color: var(--accent);
    outline: none;
}

.header-table input[readonly],
.header-table select[readonly] {
    background: transparent;
    cursor: not-allowed;
    color: var(--text-secondary);
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--shadow-base);
    margin-top: 0.75rem; /* Reduced from 20px */
}

.items-table th {
    background: var(--billing-base);
    color: var(--text-light);
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    padding: 0.5rem; /* Reduced from 12px */
    text-align: center;
}

.items-table td {
    padding: 0.5rem; /* Reduced from 8px */
    border-bottom: 1px solid var(--grey-light);
    background: rgba(15, 120, 112, 0.1);
}

.items-table th:not(:last-child),
.items-table td:not(:last-child) {
    border-right: 1px solid var(--grey-light);
}

.items-table input,
.items-table select {
    width: 100%;
    padding: 0.4rem 0; /* Reduced from 6px */
    border: none;
    border-bottom: 1px solid var(--grey-light);
    background: transparent;
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 500;
    transition: border-bottom-color 0.3s;
}

.items-table input:focus,
.items-table select:focus {
    border-bottom-color: var(--accent);
    outline: none;
}

.items-table input[readonly],
.items-table select[readonly] {
    background: transparent;
    cursor: not-allowed;
    color: var(--text-secondary);
}

.action-buttons {
    margin-top: 0.75rem; /* Reduced from 15px */
    display: flex;
    gap: 0.5rem; /* Reduced from 10px */
    justify-content: center;
}

.btn {
    padding: 0.5rem 1rem; /* Reduced from 12px/24px */
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-light);
    text-decoration: none;
}

.btn-fetch {
    background: var(--billing-base);
}

.btn-fetch:hover {
    background: var(--billing-hover);
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.btn-fetch:active {
    background: var(--billing-active);
}

.btn-print {
    background: var(--success);
}

.btn-print:hover {
    background: #16A34A;
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.btn-export {
    background: var(--accent);
}

.btn-export:hover {
    background: #2563EB;
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.btn-help {
    background: #8B5CF6;
}

.btn-help:hover {
    background: #7C3AED;
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
}

.alert-success, .alert-danger {
    font-size: 0.85rem;
    padding: 0.5rem; /* Reduced from 15px */
    margin-bottom: 0.75rem; /* Reduced from 20px */
    border-radius: 6px;
    text-align: center;
    font-weight: 500;
    box-shadow: var(--shadow-base);
}

.alert-success {
    background: var(--success);
    color: var(--text-light);
}

.alert-danger {
    background: var(--danger);
    color: var(--text-light);
}

.form-control {
    border: 1px solid var(--grey-light);
    border-radius: 6px;
    font-size: 0.85rem;
    padding: 0.5rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.4rem;
}

.modal-content {
    border-radius: 8px;
    border: none;
    box-shadow: var(--shadow-base);
}

.modal-header {
    background: var(--billing-base);
    color: var(--text-light);
    border-bottom: none;
    padding: 0.75rem; /* Reduced */
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-body {
    font-size: 0.85rem;
    color: var(--text-primary);
    padding: 0.75rem; /* Reduced */
}

.modal-footer {
    padding: 0.75rem; /* Reduced */
    border-top: 1px solid var(--grey-light);
}

.modal-footer .btn-success {
    background: var(--success);
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}

.modal-footer .btn-success:hover {
    background: #16A34A;
}

.modal-footer .btn-secondary {
    background: var(--grey-light);
    color: var(--text-primary);
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}

.modal-footer .btn-secondary:hover {
    background: #D1D5DB;
}

.btn-close {
    background: var(--danger);
    color: var(--text-light);
    border-radius: 50%;
    opacity: 1;
    padding: 6px;
    transition: background-color 0.3s ease;
}

.btn-close:hover {
    background: #DC2626;
}

footer {
    text-align: center;
    padding: 0.5rem 0; /* Reduced from 10px */
    color: var(--text-secondary);
    font-size: 0.75rem;
    margin-top: 0.75rem; /* Reduced from 20px */
}

/* Responsive Styles */
@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 0.4rem;
    }
    .page-header {
        margin: -0.4rem -0.4rem 0.4rem -0.4rem;
        padding: 0.5rem;
    }
}

@media (max-width: 768px) {
    .main-content {
        padding: 0.3rem;
    }
    .content {
        padding: 0.5rem;
    }
    .page-header h3 {
        font-size: 1.35rem;
    }
    .header-table td {
        display: block;
        width: 100%;
        padding: 0.4rem 0;
        margin-bottom: 0.5rem;
    }
    .header-table label {
        font-size: 0.8rem;
    }
    .header-table input,
    .header-table select {
        font-size: 0.8rem;
        padding: 0.4rem 0;
    }
    .items-table th, .items-table td {
        font-size: 0.8rem;
        padding: 0.4rem;
    }
    .items-table input,
    .items-table select {
        font-size: 0.8rem;
        padding: 0.3rem 0;
    }
    .action-buttons {
        flex-direction: column;
        gap: 0.4rem;
    }
    .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
        width: 100%;
    }
    .modal-body {
        font-size: 0.8rem;
        padding: 0.5rem;
    }
    .modal-footer .btn {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.25rem;
    }
    .page-header {
        margin: -0.25rem -0.25rem 0.25rem -0.25rem;
        padding: 0.4rem;
    }
    .page-header h3 {
        font-size: 1.25rem;
    }
    .content {
        padding: 0.4rem;
    }
    .header-table td {
        padding: 0.3rem 0;
        margin-bottom: 0.4rem;
    }
    .items-table th, .items-table td {
        font-size: 0.75rem;
        padding: 0.3rem;
    }
    .items-table input,
    .items-table select {
        font-size: 0.75rem;
        padding: 0.25rem 0;
    }
    .btn {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
    }
    .form-label {
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }
}

/* Print Styles */
@media print {
    .action-buttons, .btn-help, .alert {
        display: none !important;
    }
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 0;
    }
    .content {
        padding: 0.5rem;
        margin: 0;
        box-shadow: none;
    }
    .page-header {
        margin: 0;
        padding: 0.5rem;
    }
    .items-table th, .items-table td {
        border: 1px solid var(--grey-light);
    }
}
    </style>

<!-- Main Content Area -->
<div class="main-content">
    <div class="content">
        <div class="page-header">
            <h3><i class="fas fa-file-minus me-3"></i>Debit Note</h3>
        </div>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <div id="errorAlert" class="alert alert-danger d-none"></div>
        <div id="successAlert" class="alert alert-success d-none"></div>
        <form id="debitNoteForm">
            <!-- Debit Note Header -->
            <table class="header-table">
                <tr>
                    <td><label>Supplier Name</label><input type="text" name="supplier_name" id="supplier_name" readonly></td>
                    <td><label>Return No</label><input type="text" name="return_no" id="return_no" readonly></td>
                    <td><label>Debit Note No</label><input type="text" name="debit_note_no" id="debit_note_no" placeholder="Enter Debit Note Number" required></td>
                    <td><label>Date</label><input type="text" name="return_date" id="return_date" readonly></td>
                </tr>
                <tr>
                    <td><label>Invoice No</label><input type="text" name="invoice_no" id="invoice_no" readonly></td>
                    <td><label>Refund Type</label><input type="text" name="refund_type" id="refund_type" readonly></td>
                    <td><label>Return Status</label><input type="text" name="return_status" id="return_status" readonly></td>
                    <td></td>
                </tr>
            </table>

            <!-- Debit Note Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>PRODUCT NAME</th>
                        <th>BATCH NO</th>
                        <th>EXPIRY DATE</th>
                        <th>QUANTITY PURCHASED</th>
                        <th>UNIT PRICE</th>
                        <th>GST (%)</th>
                        <th>RETURN QUANTITY</th>
                        <th>REASON</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="debitNoteItems">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                    <tr>
                        <td><input type="text" name="product_name[]" class="product-name" readonly></td>
                        <td><input type="text" name="batch_no[]" class="batch-no" readonly></td>
                        <td><input type="text" name="expiry_date[]" class="expiry-date" readonly></td>
                        <td><input type="number" name="qty_purchased[]" class="qty-purchased" readonly></td>
                        <td><input type="number" step="0.01" name="unit_price[]" class="unit-price" readonly></td>
                        <td><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" readonly></td>
                        <td><input type="number" name="return_qty[]" class="return-qty" readonly></td>
                        <td><input type="text" name="reason[]" class="reason" readonly></td>
                        <td><input type="number" step="0.01" name="amount[]" class="amount" readonly></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- Notes -->
            <div class="mt-3">
                <label for="returnNotes" class="form-label">Notes</label>
                <textarea class="form-control" id="returnNotes" name="return_notes" rows="4" readonly></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-fetch" id="fetchDebitNote"><i class="fas fa-search"></i> Fetch Debit Note</button>
                <button type="button" class="btn btn-print" id="printDebitNote"><i class="fas fa-print"></i> Print Debit Note</button>
                <button type="button" class="btn btn-export" id="exportDebitNote"><i class="fas fa-file-export"></i> Export to CSV</button>
                <button type="button" class="btn btn-help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="fas fa-question-circle"></i> Help</button>
            </div>
        </form>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">Debit Note Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>How to View a Debit Note:</strong></p>
                <ul>
                    <li>Enter the Debit Note Number and click "Fetch Debit Note" to load details.</li>
                    <li>Verify supplier name, invoice number, return number, and item details.</li>
                    <li>Use "Print Debit Note" to print the document or "Export to CSV" for a file download.</li>
                    <li>Debit notes are view-only; edit returns in the Purchase Return page.</li>
                </ul>
                <p>For further assistance, contact our support team.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Leave Modal -->
<div class="modal fade" id="confirmLeaveModal" tabindex="-1" aria-labelledby="confirmLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmLeaveModalLabel">Confirm Navigation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to leave? Unsaved changes will be lost.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmLeave">Leave</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Track form changes
    let formChanged = false;
    $('#debitNoteForm').on('change keyup', 'input, textarea', function() {
        if ($(this).attr('id') === 'debit_note_no') {
            formChanged = true;
        }
    });

    // Back Navigation Confirmation
    let navigationAttempted = false;
    window.onbeforeunload = function() {
        if (formChanged && !navigationAttempted) {
            return 'Are you sure you want to leave? Unsaved changes will be lost.';
        }
    };

    window.addEventListener('popstate', function(event) {
        if (formChanged && !navigationAttempted) {
            navigationAttempted = true;
            const modal = new bootstrap.Modal(document.getElementById('confirmLeaveModal'));
            modal.show();
            history.pushState(null, null, window.location.href);
        }
    });

    $('#confirmLeave').on('click', function() {
        formChanged = false;
        navigationAttempted = false;
        window.history.back();
    });

    $('#confirmLeaveModal').on('hidden.bs.modal', function() {
        navigationAttempted = false;
        history.pushState(null, null, window.location.href);
    });

    // Fetch Debit Note Details
    $('#fetchDebitNote').click(function() {
        let debitNoteNo = $('#debit_note_no').val();
        if (!debitNoteNo) {
            $('#errorAlert').text('Please enter a Debit Note Number.').removeClass('d-none');
            return;
        }

        $.ajax({
            url: 'fetch_debit_note.php',
            method: 'POST',
            data: { debit_note_no: debitNoteNo },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#errorAlert').addClass('d-none');
                    $('#successAlert').text('Debit note fetched successfully.').removeClass('d-none');
                    $('#supplier_name').val(response.data.supplier_name);
                    $('#return_no').val(response.data.return_no);
                    $('#debit_note_no').val(response.data.debit_note_no);
                    $('#return_date').val(response.data.return_date);
                    $('#invoice_no').val(response.data.invoice_no);
                    $('#refund_type').val(response.data.refund_type);
                    $('#return_status').val(response.data.return_status);
                    $('#returnNotes').val(response.data.notes);
                    let items = response.data.items;
                    let rows = $('#debitNoteItems tr');
                    rows.each(function(index) {
                        if (index < items.length) {
                            let item = items[index];
                            $(this).find('.product-name').val(item.product_name);
                            $(this).find('.batch-no').val(item.batch_no);
                            $(this).find('.expiry-date').val(item.expiry_date);
                            $(this).find('.qty-purchased').val(item.quantity_purchased);
                            $(this).find('.unit-price').val(item.unit_price);
                            $(this).find('.gst-rate').val(item.gst_rate);
                            $(this).find('.return-qty').val(item.return_quantity);
                            $(this).find('.reason').val(item.reason);
                            $(this).find('.amount').val(item.amount);
                        } else {
                            $(this).find('input').val('');
                        }
                    });
                    formChanged = false; // Reset after successful fetch
                } else {
                    $('#errorAlert').text(response.message).removeClass('d-none');
                    $('#successAlert').addClass('d-none');
                }
            },
            error: function() {
                $('#errorAlert').text('Error fetching debit note details.').removeClass('d-none');
                $('#successAlert').addClass('d-none');
            }
        });
    });

    // Print Debit Note
    $('#printDebitNote').click(function() {
        let printContents = document.querySelector('.content').outerHTML;
        let printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Debit Note - GROW BILLER</title>
                    <style>
                        body { font-family: 'Inter', sans-serif; }
                        .content { padding: 20px; }
                        h3 { color: #4b2e8a; text-align: center; }
                        .header-table, .items-table { width: 100%; border-collapse: collapse; }
                        .header-table td { padding: 10px; }
                        .items-table th, .items-table td { padding: 6px; border: 1px solid #c7b1f0; }
                        .items-table th { background: #c7b1f0; color: #4b2e8a; }
                    </style>
                </head>
                <body>${printContents}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    });

    // Export to CSV
    $('#exportDebitNote').click(function() {
        let supplierName = $('#supplier_name').val();
        let debitNoteNo = $('#debit_note_no').val();
        if (!supplierName || !debitNoteNo) {
            $('#errorAlert').text('Please fetch debit note details before exporting.').removeClass('d-none');
            return;
        }

        function escapeCsvField(field) {
            if (!field) return '';
            return `"${field.toString().replace(/"/g, '""').replace(/\n/g, ' ')}"`;
        }

        let csv = '\uFEFF';
        csv += 'Supplier Name,Return No,Debit Note No,Date,Invoice No,Refund Type,Return Status,Product Name,Batch No,Expiry Date,Quantity Purchased,Unit Price,GST (%),Return Quantity,Reason,Amount,Notes\n';
        let returnNo = $('#return_no').val();
        let returnDate = $('#return_date').val();
        let invoiceNo = $('#invoice_no').val();
        let refundType = $('#refund_type').val();
        let returnStatus = $('#return_status').val();
        let notes = $('#returnNotes').val();

        $('#debitNoteItems tr').each(function() {
            let row = $(this);
            let productName = row.find('.product-name').val() || '';
            let batchNo = row.find('.batch-no').val() || '';
            let expiryDate = row.find('.expiry-date').val() || '';
            let qtyPurchased = row.find('.qty-purchased').val() || '';
            let unitPrice = row.find('.unit-price').val() || '';
            let gstRate = row.find('.gst-rate').val() || '';
            let returnQty = row.find('.return-qty').val() || '';
            let reason = row.find('.reason').val() || '';
            let amount = row.find('.amount').val() || '';
            csv += [
                escapeCsvField(supplierName),
                escapeCsvField(returnNo),
                escapeCsvField(debitNoteNo),
                escapeCsvField(returnDate),
                escapeCsvField(invoiceNo),
                escapeCsvField(refundType),
                escapeCsvField(returnStatus),
                escapeCsvField(productName),
                escapeCsvField(batchNo),
                escapeCsvField(expiryDate),
                qtyPurchased,
                unitPrice,
                gstRate,
                returnQty,
                escapeCsvField(reason),
                amount,
                escapeCsvField(notes)
            ].join(',') + '\n';
        });

        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `debit_note_${debitNoteNo}.csv`;
        link.click();
    });
});
</script>