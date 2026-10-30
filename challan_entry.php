<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

session_start();
include('layout.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Generate auto challan number
$auto_challan_no = "CHL-" . date("Ymd") . "-" . rand(100, 999);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Challan Entry - GROW BILLER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e1d9f4 0%, #f3e8ff 100%);
        }

        .challan-entry {
            padding: 8px;
            min-height: 100vh;
        }

        .content {
            margin-top: 20px;
            margin-left: 280px;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            min-height: calc(100vh - 52px);
            transition: margin-left 0.3s ease;
        }

        .content h3 {
            color: #4b2e8a;
            font-weight: 500;
            font-size: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .header-table {
            width: 100%;
            margin-bottom: 15px;
        }

        .header-table td {
            padding: 10px;
            vertical-align: middle;
        }

        .header-table label {
            font-size: 13px;
            color: #37474f;
            font-weight: 500;
            margin-bottom: 3px;
            display: block;
        }

        .header-table input,
        .header-table select {
            width: 100%;
            padding: 6px 0;
            border: none;
            border-bottom: 1px solid #c7b1f0;
            background: transparent;
            font-size: 13px;
            color: #263238;
            transition: border-bottom-color 0.3s;
        }

        .header-table input:focus,
        .header-table select:focus {
            border-bottom-color: #8e44ad;
            outline: none;
        }

        .header-table input[readonly] {
            background: transparent;
            cursor: not-allowed;
            color: #6c757d;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8faf8;
            border-radius: 8px;
            overflow: hidden;
        }

        .items-table th {
            background: #c7b1f0;
            color: #4b2e8a;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            padding: 10px;
            text-align: center;
        }

        .items-table td {
            padding: 6px;
            border-bottom: 1px solid #e8f0e8;
        }

        .items-table th:not(:last-child),
        .items-table td:not(:last-child) {
            border-right: 1px solid #c7b1f0;
        }

        .items-table input,
        .items-table select {
            width: 100%;
            padding: 6px 0;
            border: none;
            border-bottom: 1px solid #c7b1f0;
            background: transparent;
            font-size: 13px;
            color: #263238;
            transition: border-bottom-color 0.3s;
        }

        .items-table input:focus,
        .items-table select:focus {
            border-bottom-color: #8e44ad;
            outline: none;
        }

        .items-table input[readonly],
        .items-table select[readonly] {
            background: transparent;
            cursor: not-allowed;
            color: #6c757d;
        }

        .action-buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
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
        }

        .btn-save {
            background: #5e4b8c;
            color: #fff;
        }

        .btn-save:hover {
            background: #4b3a73;
            transform: translateY(-2px);
        }

        .btn-print {
            background: #7b5ea8;
            color: #fff;
        }

        .btn-print:hover {
            background: #6a4f94;
            transform: translateY(-2px);
        }

        .btn-export {
            background: #8e44ad;
            color: #fff;
        }

        .btn-export:hover {
            background: #7a3c99;
            transform: translateY(-2px);
        }

        .btn-help {
            background: #b0a1d6;
            color: #fff;
        }

        .btn-help:hover {
            background: #9f90c5;
            transform: translateY(-2px);
        }

        .alert-success {
            background-color: #8e44ad;
            color: #fff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }

        .alert-danger {
            background-color: #ff3300;
            color: #fff;
            padding: 10px;
            margin-bottom: 15px;
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

        .modal-footer .btn-success {
            background: #5e4b8c;
            border: none;
            border-radius: 6px;
        }

        .modal-footer .btn-success:hover {
            background: #4b3a73;
        }

        .modal-footer .btn-secondary {
            background: #b0a1d6;
            color: #fff;
            border: none;
            border-radius: 6px;
        }

        .modal-footer .btn-secondary:hover {
            background: #9f90c5;
        }

        footer {
            text-align: center;
            padding: 10px 0;
            color: #6c757d;
            font-size: 12px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 240px;
                padding: 15px;
                margin-top: 15px;
            }

            .header-table td {
                display: block;
                width: 100%;
                margin-bottom: 8px;
            }

            .items-table th,
            .items-table td {
                font-size: 11px;
                padding: 5px;
            }

            .items-table th:not(:last-child),
            .items-table td:not(:last-child) {
                border-right: none;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 180px;
            }
        }
    </style>
</head>
<body>
<?php
// Ensure sidebar is visible
echo '<script>document.querySelector(".sidebar").classList.remove("sidebar-hidden");</script>';
?>

<div class="challan-entry">
    <div class="content">
        <h3>Challan Entry</h3>
        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="alert alert-success">Challan processed successfully.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <div id="errorAlert" class="alert alert-danger d-none"></div>
        <div id="successAlert" class="alert alert-success d-none"></div>
        <form method="POST" action="process_challan.php" id="challanForm">
            <!-- Challan Header -->
            <table class="header-table">
                <tr>
                    <td><label>Supplier Name</label>
                        <select name="supplier_name" id="supplier_name" required>
                            <option value="">Select Supplier</option>
                        </select>
                    </td>
                    <td><label>Challan No</label><input type="text" name="challan_no" value="<?= $auto_challan_no ?>" readonly></td>
                    <td><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>" required></td>
                    <td><label>Invoice No</label><input type="text" name="invoice_no" placeholder="Enter Invoice No"></td>
                </tr>
                <tr>
                    <td><label>Party No</label><input type="text" name="party_no" placeholder="Enter Party No"></td>
                    <td colspan="3"></td>
                </tr>
            </table>

            <!-- Challan Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>PRODUCT NAME</th>
                        <th>BATCH NO</th>
                        <th>EXPIRY DATE</th>
                        <th>QUANTITY</th>
                        <th>UNIT PRICE</th>
                        <th>GST (%)</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="challanItems">
                    <?php for ($i = 0; $i < 7; $i++): ?>
                    <tr>
                        <td>
                            <select name="product_id[]" class="product-id" onchange="updateProductName(this)">
                                <option value="">Select Product</option>
                            </select>
                            <input type="hidden" name="product_name[]" class="product-name">
                        </td>
                        <td><input type="text" name="batch_no[]" class="batch-no"></td>
                        <td><input type="date" name="expiry_date[]" class="expiry-date"></td>
                        <td><input type="number" name="quantity[]" class="quantity" min="0" oninput="calculateAmount(this)"></td>
                        <td><input type="number" step="0.01" name="unit_price[]" class="unit-price" oninput="calculateAmount(this)"></td>
                        <td><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" oninput="calculateAmount(this)"></td>
                        <td><input type="number" step="0.01" name="amount[]" class="amount" readonly></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <!-- Notes -->
            <div class="mt-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Enter additional notes"></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="submit" class="btn btn-save"><i class="fas fa-save"></i> Save Challan</button>
                <button type="button" class="btn btn-print" id="printChallan"><i class="fas fa-print"></i> Print Challan</button>
                <button type="button" class="btn btn-export" id="exportChallan"><i class="fas fa-file-export"></i> Export to CSV</button>
                <button type="button" class="btn btn-help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="fas fa-question-circle"></i> Help</button>
            </div>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Success</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Challan processed successfully!
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="window.location.href='purchase-stock.php'">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">Challan Entry Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>How to Process a Challan:</strong></p>
                <ul>
                    <li>Select a supplier from the dropdown.</li>
                    <li>Enter the challan number (auto-generated), date, invoice number, and party number.</li>
                    <li>Add items by selecting products, entering batch number, expiry date, quantity, unit price, and GST rate.</li>
                    <li>Click "Save Challan" to process. Use "Print Challan" or "Export to CSV" for documentation.</li>
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
    // Ensure sidebar is visible
    $('.sidebar').removeClass('sidebar-hidden').css({'display': 'block', 'transition': 'none'});

    // Track form changes
    let formChanged = false;
    $('#challanForm').on('change keyup', 'input:not([readonly]), select:not([readonly]), textarea', function() {
        formChanged = true;
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

    // Show success modal if redirected with success
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1') {
        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();
    }

    // Fetch suppliers
    $.ajax({
        url: 'fetch_suppliers.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let supplierSelect = $('#supplier_name');
                response.suppliers.forEach(function(supplier) {
                    supplierSelect.append(`<option value="${supplier}">${supplier}</option>`);
                });
                $('#successAlert').text('Suppliers fetched successfully.').removeClass('d-none');
            } else {
                $('#errorAlert').text(response.message).removeClass('d-none');
            }
        },
        error: function() {
            $('#errorAlert').text('Error fetching suppliers.').removeClass('d-none');
        }
    });

    // Fetch products
    $.ajax({
        url: 'fetch_products.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let productSelects = $('.product-id');
                productSelects.each(function() {
                    let select = $(this);
                    response.products.forEach(function(product) {
                        select.append(`<option value="${product.id}">${product.name}</option>`);
                    });
                });
                $('#successAlert').text('Products fetched successfully.').removeClass('d-none');
            } else {
                $('#errorAlert').text(response.message).removeClass('d-none');
            }
        },
        error: function() {
            $('#errorAlert').text('Error fetching products.').removeClass('d-none');
        }
    });

    // Update product name
    window.updateProductName = function(el) {
        let select = $(el);
        let row = select.closest('tr');
        let productName = select.find('option:selected').text();
        row.find('.product-name').val(productName);
    };

    // Calculate amount
    window.calculateAmount = function(el) {
        const row = $(el).closest('tr');
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.unit-price').val()) || 0;
        const gstRate = parseFloat(row.find('.gst-rate').val()) || 0;
        const baseAmount = quantity * unitPrice;
        const gstAmount = baseAmount * (gstRate / 100);
        const totalAmount = baseAmount + gstAmount;
        row.find('.amount').val(totalAmount.toFixed(2));
    };

    // Add Row
    function addRow() {
        const table = document.getElementById("challanItems");
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>
                <select name="product_id[]" class="product-id" onchange="updateProductName(this)">
                    <option value="">Select Product</option>
                </select>
                <input type="hidden" name="product_name[]" class="product-name">
            </td>
            <td><input type="text" name="batch_no[]" class="batch-no"></td>
            <td><input type="date" name="expiry_date[]" class="expiry-date"></td>
            <td><input type="number" name="quantity[]" class="quantity" min="0" oninput="calculateAmount(this)"></td>
            <td><input type="number" step="0.01" name="unit_price[]" class="unit-price" oninput="calculateAmount(this)"></td>
            <td><input type="number" step="0.01" name="gst_rate[]" class="gst-rate" oninput="calculateAmount(this)"></td>
            <td><input type="number" step="0.01" name="amount[]" class="amount" readonly></td>
        `;
        table.appendChild(row);
        // Populate product dropdown for new row
        $.ajax({
            url: 'fetch_products.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let select = row.querySelector('.product-id');
                    response.products.forEach(function(product) {
                        select.innerHTML += `<option value="${product.id}">${product.name}</option>`;
                    });
                }
            }
        });
        attachEnterListener(row);
    }

    // Remove Row
    function removeRow(row) {
        const table = document.getElementById("challanItems");
        if (table.rows.length > 7) {
            table.deleteRow(table.rows.length - 1);
        }
    }

    // Enter Key Navigation
    function attachEnterListener(row) {
        const inputs = row.querySelectorAll('input:not([readonly]), select');
        inputs.forEach((input, index) => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const table = document.getElementById("challanItems");
                    const rows = table.rows;
                    const currentRowIndex = Array.from(rows).indexOf(row);
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    } else if (currentRowIndex < rows.length - 1) {
                        const nextRow = rows[currentRowIndex + 1];
                        nextRow.querySelector('input:not([readonly]), select').focus();
                    } else {
                        addRow();
                        const newRow = table.rows[rows.length - 1];
                        newRow.querySelector('input:not([readonly]), select').focus();
                    }
                } else if (e.key === 'Backspace' && input.value === '') {
                    const table = document.getElementById("challanItems");
                    const rows = table.rows;
                    const currentRowIndex = Array.from(rows).indexOf(row);
                    if (currentRowIndex === rows.length - 1 && table.rows.length > 7) {
                        e.preventDefault();
                        removeRow(row);
                        const lastRow = table.rows[rows.length - 1];
                        lastRow.querySelector('input:not([readonly]), select').focus();
                    }
                }
            });
        });
    }

    document.querySelectorAll('#challanItems tr').forEach(row => attachEnterListener(row));

    // Print Challan
    $('#printChallan').click(function() {
        let printContents = document.querySelector('.content').outerHTML;
        let printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Challan Entry - GROW BILLER</title>
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
    $('#exportChallan').click(function() {
        let supplierName = $('#supplier_name').val();
        let challanNo = $('input[name="challan_no"]').val();
        
        // Validate required fields
        if (!supplierName || !challanNo) {
            $('#errorAlert').text('Please fill Supplier Name and Challan No before exporting.').removeClass('d-none');
            return;
        }

        // Escape CSV field function
        function escapeCsvField(field) {
            if (!field) return '';
            return `"${field.toString().replace(/"/g, '""').replace(/\n/g, ' ')}"`;
        }

        // CSV header
        let csv = '\uFEFF';
        csv += 'Supplier Name,Challan No,Date,Invoice No,Party No,Product Name,Batch No,Expiry Date,Quantity,Unit Price,GST (%),Amount,Notes\n';

        let date = $('input[name="date"]').val() || '';
        let invoiceNo = $('input[name="invoice_no"]').val() || '';
        let partyNo = $('input[name="party_no"]').val() || '';
        let notes = $('#notes').val() || '';

        let hasValidRows = false;
        $('#challanItems tr').each(function() {
            let row = $(this);
            let productId = row.find('.product-id').val() || '';
            let quantity = row.find('.quantity').val() || '';
            
            // Skip empty rows
            if (!productId || !quantity) {
                return;
            }

            hasValidRows = true;
            let productName = row.find('.product-name').val() || '';
            let batchNo = row.find('.batch-no').val() || '';
            let expiryDate = row.find('.expiry-date').val() || '';
            let unitPrice = row.find('.unit-price').val() || '';
            let gstRate = row.find('.gst-rate').val() || '';
            let amount = row.find('.amount').val() || '';

            csv += [
                escapeCsvField(supplierName),
                escapeCsvField(challanNo),
                escapeCsvField(date),
                escapeCsvField(invoiceNo),
                escapeCsvField(partyNo),
                escapeCsvField(productName),
                escapeCsvField(batchNo),
                escapeCsvField(expiryDate),
                quantity,
                unitPrice,
                gstRate,
                amount,
                escapeCsvField(notes)
            ].join(',') + '\n';
        });

        // Check if there are valid rows
        if (!hasValidRows) {
            $('#errorAlert').text('Please add at least one valid item (Product and Quantity) before exporting.').removeClass('d-none');
            return;
        }

        // Create and download CSV
        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `challan_${challanNo}.csv`;
        link.click();
    });
});
</script>
</body>
</html>
<?php
ob_end_flush();
?>