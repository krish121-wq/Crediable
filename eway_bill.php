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

// Business GSTIN
$recipient_gstin = $_SESSION['gstin'] ?? '29ABCDE1234F1Z5'; // Replace with actual
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>e-Way Bill - GROW BILLER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f4f3 0%, #fff3e0 100%);
        }

        .eway-bill {
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
        }

        .content h3 {
            color: #4b2e8a;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info-section {
            background: #f8faf8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .info-section label {
            font-size: 13px;
            color: #37474f;
            font-weight: 500;
            margin-right: 10px;
        }

        .info-section span {
            font-size: 13px;
            color: #263238;
            font-weight: 400;
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
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            padding: 10px;
            text-align: center;
        }

        .items-table td {
            padding: 6px;
            border-bottom: 1px solid #e8f0e8;
            text-align: center;
        }

        .items-table th:not(:last-child),
        .items-table td:not(:last-child) {
            border-right: 1px solid #d1e7dd;
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

        .btn-fetch {
            background: #2e7d32;
            color: #fff;
        }

        .btn-fetch:hover {
            background: #255d27;
            transform: translateY(-2px);
        }

        .btn-generate {
            background: #2e7d32;
            color: #fff;
        }

        .btn-generate:hover {
            background: #255d27;
            transform: translateY(-2px);
        }

        .btn-print {
            background: #f5a623;
            color: #fff;
        }

        .btn-print:hover {
            background: #e6951c;
            transform: translateY(-2px);
        }

        .btn-export {
            background: #0288d1;
            color: #fff;
        }

        .btn-export:hover {
            background: #026aa7;
            transform: translateY(-2px);
        }

        .btn-help {
            background: #6c757d;
            color: #fff;
        }

        .btn-help:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .alert-success {
            background-color: #00b328;
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
            background: #d1e7dd;
            border-bottom: none;
        }

        .modal-title {
            color: #2e7d32;
            font-weight: 500;
        }

        .modal-body {
            font-size: 14px;
            color: #37474f;
        }

        .modal-footer .btn-success {
            background: #2e7d32;
            border: none;
            border-radius: 6px;
        }

        .modal-footer .btn-success:hover {
            background: #255d27;
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

            .info-section div {
                margin-bottom: 10px;
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

<div class="eway-bill">
    <div class="content">
        <h3>e-Way Bill List</h3>
        <div id="errorAlert" class="alert alert-danger d-none"></div>
        <div id="successAlert" class="alert alert-success d-none"></div>

        <!-- Invoice Search -->
        <div class="mb-3">
            <label for="entry_no" class="form-label">Invoice Number</label>
            <input type="text" id="entry_no" class="form-control" placeholder="Enter Invoice No">
            <button type="button" class="btn btn-fetch mt-2" id="fetchBill"><i class="fas fa-search"></i> Fetch e-Way Bill</button>
        </div>

        <!-- Bill Details Section -->
        <div id="billDetails" style="display: none;">
            <div class="info-section">
                <div><label>Supplier Name:</label><span id="supplier_name"></span></div>
                <div><label>Invoice No:</label><span id="entry_no_display"></span></div>
                <div><label>Date:</label><span id="date"></span></div>
                <div><label>Party No:</label><span id="party_no"></span></div>
                <div><label>Supplier GSTIN:</label><span id="supplier_gstin"></span></div>
                <div><label>Recipient GSTIN:</label><span id="recipient_gstin"><?= $recipient_gstin ?></span></div>
                <div><label>Total Amount (₹):</label><span id="total_amount"></span></div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>PRODUCT NAME</th>
                        <th>HSN CODE</th>
                        <th>BATCH NO</th>
                        <th>EXPIRY DATE</th>
                        <th>QUANTITY</th>
                        <th>UNIT PRICE</th>
                        <th>GST (%)</th>
                        <th>AMOUNT</th>
                    </tr>
                </thead>
                <tbody id="billItems"></tbody>
            </table>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-generate" data-bs-toggle="modal" data-bs-target="#transportModal"><i class="fas fa-file-alt"></i> Generate e-Way Bill</button>
                <button type="button" class="btn btn-print" id="printBill"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="btn btn-export" id="exportCsv"><i class="fas fa-file-export"></i> Export to CSV</button>
                <button type="button" class="btn btn-help" data-bs-toggle="modal" data-bs-target="#helpModal"><i class="fas fa-question-circle"></i> Help</button>
            </div>
        </div>
    </div>
</div>

<!-- Transport Details Modal -->
<div class="modal fade" id="transportModal" tabindex="-1" aria-labelledby="transportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transportModalLabel">Transport Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="place_of_dispatch" class="form-label">Place of Dispatch</label>
                    <input type="text" id="place_of_dispatch" class="form-control" placeholder="Enter Place of Dispatch" required>
                </div>
                <div class="mb-3">
                    <label for="place_of_delivery" class="form-label">Place of Delivery</label>
                    <input type="text" id="place_of_delivery" class="form-control" placeholder="Enter Place of Delivery" required>
                </div>
                <div class="mb-3">
                    <label for="transport_mode" class="form-label">Transport Mode</label>
                    <select id="transport_mode" class="form-control" required>
                        <option value="">Select Mode</option>
                        <option value="Road">Road</option>
                        <option value="Rail">Rail</option>
                        <option value="Air">Air</option>
                        <option value="Ship">Ship</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="distance" class="form-label">Distance (km)</label>
                    <input type="number" id="distance" class="form-control" placeholder="Enter Distance" required>
                </div>
                <div class="mb-3">
                    <label for="vehicle_no" class="form-label">Vehicle No</label>
                    <input type="text" id="vehicle_no" class="form-control" placeholder="Enter Vehicle No">
                </div>
                <div class="mb-3">
                    <label for="transporter_id" class="form-label">Transporter ID</label>
                    <input type="text" id="transporter_id" class="form-control" placeholder="Enter Transporter ID">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="submitTransport">Generate</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">e-Way Bill Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>How to Generate an e-Way Bill:</strong></p>
                <ul>
                    <li>Enter the Invoice Number and click "Fetch e-Way Bill" to load details.</li>
                    <li>Verify the supplier, invoice, and item details.</li>
                    <li>Click "Generate e-Way Bill" and fill in transport details in the modal.</li>
                    <li>Download the JSON file for e-Way Bill portal upload or print the PDF.</li>
                    <li>Use "Export to CSV" for documentation.</li>
                </ul>
                <p>For further assistance, contact our support team.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
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

    // Fetch bill details
    $('#fetchBill').click(function() {
        let entry_no = $('#entry_no').val().trim();
        if (!entry_no) {
            $('#errorAlert').text('Please enter an Invoice Number').removeClass('d-none');
            return;
        }

        $.ajax({
            url: 'fetch_purchase_details.php',
            method: 'POST',
            data: { entry_no: entry_no },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let data = response.data;
                    $('#supplier_name').text(data.supplier_name);
                    $('#entry_no_display').text(data.entry_no);
                    $('#date').text(data.date);
                    $('#party_no').text(data.party_no);
                    $('#supplier_gstin').text(data.supplier_gstin || 'N/A');
                    $('#total_amount').text(data.total_amount.toFixed(2));

                    // Populate items table
                    let tbody = $('#billItems');
                    tbody.empty();
                    data.items.forEach(function(item) {
                        tbody.append(`
                            <tr>
                                <td>${item.product_name}</td>
                                <td>${item.hsn_code || 'N/A'}</td>
                                <td>${item.batch_no || ''}</td>
                                <td>${item.expiry_date || ''}</td>
                                <td>${item.qty}</td>
                                <td>${item.per_piece_price.toFixed(2)}</td>
                                <td>${item.gst_rate.toFixed(2)}</td>
                                <td>${(item.qty * item.per_piece_price * (1 + item.gst_rate / 100)).toFixed(2)}</td>
                            </tr>
                        `);
                    });

                    $('#billDetails').show();
                    $('#errorAlert').addClass('d-none');
                } else {
                    $('#errorAlert').text(response.message).removeClass('d-none');
                    $('#billDetails').hide();
                }
            },
            error: function() {
                $('#errorAlert').text('Error fetching purchase details').removeClass('d-none');
            }
        });
    });

    // Generate e-Way Bill
    $('#submitTransport').click(function() {
        let supplier_gstin = $('#supplier_gstin').text().trim() || '';
        let place_of_dispatch = $('#place_of_dispatch').val().trim();
        let place_of_delivery = $('#place_of_delivery').val().trim();
        let transport_mode = $('#transport_mode').val();
        let distance = parseFloat($('#distance').val()) || 0;
        let vehicle_no = $('#vehicle_no').val().trim();
        let transporter_id = $('#transporter_id').val().trim();
        let entry_no = $('#entry_no_display').text();

        // Validate inputs
        if (!supplier_gstin || !place_of_dispatch || !place_of_delivery || !transport_mode || distance <= 0) {
            alert('Please fill all required transport details.');
            return;
        }

        // Validate GSTIN (simple regex)
        if (supplier_gstin && !/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[A-Z0-9]{1}$/.test(supplier_gstin)) {
            alert('Invalid Supplier GSTIN format.');
            return;
        }

        // Prepare form data
        let formData = new FormData();
        formData.append('entry_no', entry_no);
        formData.append('supplier_gstin', supplier_gstin);
        formData.append('recipient_gstin', $('#recipient_gstin').text());
        formData.append('place_of_dispatch', place_of_dispatch);
        formData.append('place_of_delivery', place_of_delivery);
        formData.append('transport_mode', transport_mode);
        formData.append('distance', distance);
        formData.append('vehicle_no', vehicle_no);
        formData.append('transporter_id', transporter_id);

        $.ajax({
            url: 'generate_eway_bill.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#transportModal').modal('hide');
                    $('#successAlert').text(`e-Way Bill generated: ${response.eway_bill_no}. JSON file generated.`).removeClass('d-none');
                    $('#errorAlert').addClass('d-none');

                    // Download JSON
                    let link = document.createElement('a');
                    link.href = response.json_file;
                    link.download = `eway_bill_${entry_no}.json`;
                    link.click();

                    // Generate PDF
                    let pdfContent = generatePdfContent(response.data);
                    html2pdf().from(pdfContent).save(`eway_bill_${entry_no}.pdf`);
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Error generating e-Way Bill');
            }
        });
    });

    // Print e-Way Bill
    $('#printBill').click(function() {
        let content = `
            <div style="font-family: 'Inter', sans-serif; padding: 20px;">
                <h3 style="color: #4b2e8a; text-align: center;">e-Way Bill List</h3>
                <div style="background: #f8faf8; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div><strong>Supplier Name:</strong> ${$('#supplier_name').text()}</div>
                    <div><strong>Invoice No:</strong> ${$('#entry_no_display').text()}</div>
                    <div><strong>Date:</strong> ${$('#date').text()}</div>
                    <div><strong>Party No:</strong> ${$('#party_no').text()}</div>
                    <div><strong>Supplier GSTIN:</strong> ${$('#supplier_gstin').text()}</div>
                    <div><strong>Recipient GSTIN:</strong> ${$('#recipient_gstin').text()}</div>
                    <div><strong>Total Amount:</strong> ₹${$('#total_amount').text()}</div>
                </div>
                ${$('#billItems').parent().html()}
            </div>
        `;
        let printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>e-Way Bill - GROW BILLER</title>
                    <style>
                        body { font-family: 'Inter', sans-serif; }
                        h3 { color: #4b2e8a; text-align: center; }
                        .info-section { background: #f8faf8; padding: 15px; border-radius: 8px; }
                        .items-table { width: 100%; border-collapse: collapse; }
                        .items-table th, .items-table td { padding: 6px; border: 1px solid #d1e7dd; }
                        .items-table th { background: #c7b1f0; color: #4b2e8a; }
                    </style>
                </head>
                <body>${content}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    });

    // Export to CSV
    $('#exportCsv').click(function() {
        let supplierName = $('#supplier_name').text();
        let entryNo = $('#entry_no_display').text();
        if (!supplierName || !entryNo) {
            alert('Please fetch bill details before exporting.');
            return;
        }

        function escapeCsvField(field) {
            if (!field) return '';
            return `"${field.toString().replace(/"/g, '""').replace(/\n/g, ' ')}"`;
        }

        let csv = '\uFEFF';
        csv += 'Supplier Name,Invoice No,Date,Party No,Supplier GSTIN,Recipient GSTIN,Total Amount,Product Name,HSN Code,Batch No,Expiry Date,Quantity,Unit Price,GST (%),Amount\n';

        let date = $('#date').text();
        let partyNo = $('#party_no').text();
        let supplierGstin = $('#supplier_gstin').text();
        let recipientGstin = $('#recipient_gstin').text();
        let totalAmount = $('#total_amount').text();

        $('#billItems tr').each(function() {
            let row = $(this);
            let productName = row.find('td:eq(0)').text();
            let hsnCode = row.find('td:eq(1)').text();
            let batchNo = row.find('td:eq(2)').text();
            let expiryDate = row.find('td:eq(3)').text();
            let quantity = row.find('td:eq(4)').text();
            let unitPrice = row.find('td:eq(5)').text();
            let gstRate = row.find('td:eq(6)').text();
            let amount = row.find('td:eq(7)').text();

            csv += [
                escapeCsvField(supplierName),
                escapeCsvField(entryNo),
                escapeCsvField(date),
                escapeCsvField(partyNo),
                escapeCsvField(supplierGstin),
                escapeCsvField(recipientGstin),
                totalAmount,
                escapeCsvField(productName),
                escapeCsvField(hsnCode),
                escapeCsvField(batchNo),
                escapeCsvField(expiryDate),
                quantity,
                unitPrice,
                gstRate,
                amount
            ].join(',') + '\n';
        });

        let blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        let link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `eway_bill_${entryNo}.csv`;
        link.click();
    });

    // Generate PDF content
    function generatePdfContent(data) {
        return `
            <div style="font-family: 'Inter', sans-serif; padding: 20px;">
                <h3 style="color: #4b2e8a; text-align: center;">e-Way Bill: ${data.eway_bill_no}</h3>
                <div style="background: #f8faf8; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div><strong>Supplier Name:</strong> ${data.supplier_name}</div>
                    <div><strong>Invoice No:</strong> ${data.entry_no}</div>
                    <div><strong>Date:</strong> ${data.date}</div>
                    <div><strong>Party No:</strong> ${data.party_no}</div>
                    <div><strong>Supplier GSTIN:</strong> ${$('#supplier_gstin').text()}</div>
                    <div><strong>Recipient GSTIN:</strong> ${$('#recipient_gstin').text()}</div>
                    <div><strong>Place of Dispatch:</strong> ${$('#place_of_dispatch').val()}</div>
                    <div><strong>Place of Delivery:</strong> ${$('#place_of_delivery').val()}</div>
                    <div><strong>Transport Mode:</strong> ${$('#transport_mode').val()}</div>
                    <div><strong>Distance:</strong> ${$('#distance').val()} km</div>
                    <div><strong>Vehicle No:</strong> ${$('#vehicle_no').val()}</div>
                    <div><strong>Transporter ID:</strong> ${$('#transporter_id').val()}</div>
                    <div><strong>Total Amount:</strong> ₹${data.total_amount.toFixed(2)}</div>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #c7b1f0; color: #4b2e8a;">
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Product Name</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">HSN Code</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Batch No</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Expiry Date</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Quantity</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Unit Price</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">GST (%)</th>
                            <th style="padding: 8px; border: 1px solid #d1e7dd;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.items.map(item => `
                            <tr>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.product_name}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.hsn_code || 'N/A'}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.batch_no}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.expiry_date || ''}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.qty}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.per_piece_price.toFixed(2)}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${item.gst_rate.toFixed(2)}</td>
                                <td style="padding: 8px; border: 1px solid #d1e7dd;">${(item.qty * item.per_piece_price * (1 + item.gst_rate / 100)).toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }
});
</script>
</body>
</html>