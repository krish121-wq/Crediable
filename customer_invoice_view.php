<?php
// Helper function to safely escape HTML with null check
function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function number_to_words($number) {
    $dictionary = array(
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven',
        12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
        17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety'
    );
    
    if (!is_numeric($number)) {
        return false;
    }
    
    $number = (int)$number;
    
    if ($number < 0) {
        return 'minus ' . number_to_words(abs($number));
    }
    
    if ($number < 21) {
        return $dictionary[$number];
    }
    
    if ($number < 100) {
        $tens = ((int)($number / 10)) * 10;
        $units = $number % 10;
        return $dictionary[$tens] . ($units ? ' ' . $dictionary[$units] : '');
    }
    
    if ($number < 1000) {
        $hundreds = (int)($number / 100);
        $remainder = $number % 100;
        return $dictionary[$hundreds] . ' hundred' . ($remainder ? ' and ' . number_to_words($remainder) : '');
    }
    
    if ($number < 100000) {
        $thousands = (int)($number / 1000);
        $remainder = $number % 1000;
        return number_to_words($thousands) . ' thousand' . ($remainder ? ' ' . number_to_words($remainder) : '');
    }
    
    if ($number < 10000000) {
        $lakhs = (int)($number / 100000);
        $remainder = $number % 100000;
        return number_to_words($lakhs) . ' lakh' . ($remainder ? ' ' . number_to_words($remainder) : '');
    }
    
    $crores = (int)($number / 10000000);
    $remainder = $number % 10000000;
    return number_to_words($crores) . ' crore' . ($remainder ? ' ' . number_to_words($remainder) : '');
}

ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

$invoice_id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!$invoice_id || !$token) {
    die("Invalid invoice link");
}

// Verify token (simple validation)
$expected_token = base64_encode($invoice_id . '_' . floor(time() / 86400)); // Token valid for 24 hours
if ($token !== $expected_token) {
    // Try with timestamp from token (for backward compatibility)
    $decoded = base64_decode($token);
    if ($decoded !== false) {
        $token_parts = explode('_', $decoded);
        if (count($token_parts) < 2 || (int)$token_parts[0] !== $invoice_id) {
            die("Invalid or expired invoice link");
        }
    } else {
        die("Invalid or expired invoice link");
    }
}

// Fetch invoice details
try {
    $stmt = $conn->prepare("
        SELECT si.*, 
               c.state AS customer_state,
               c.gstin AS customer_gstin,
               CASE
                   WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                   WHEN staff_users.name IS NOT NULL THEN staff_users.name
                   ELSE 'Unknown'
               END AS created_by_name
        FROM service_invoices si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN users admin_users ON si.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON si.created_by = staff_users.id
        WHERE si.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        die("Invoice not found");
    }
    
    // Fetch service items
    $stmt = $conn->prepare("SELECT * FROM service_invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    die("Error fetching invoice: " . $e->getMessage());
}

// Fetch company details
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$company) {
        $company = [
            'company_name' => 'Your Company Name',
            'company_address' => 'Your Company Address',
            'company_gst' => '',
            'company_logo' => '',
            'contact_name' => 'Contact Person',
            'email' => 'info@company.com',
            'phone' => '1234567890',
            'state' => ''
        ];
    }
} catch (Exception $e) {
    $company = [
        'company_name' => 'Your Company Name',
        'company_address' => 'Your Company Address',
        'company_gst' => '',
        'company_logo' => '',
        'contact_name' => 'Contact Person',
        'email' => 'info@company.com',
        'phone' => '1234567890',
        'state' => ''
    ];
}

$company_state = strtolower(trim($company['state'] ?? ''));
$customer_state = strtolower(trim($invoice['customer_state'] ?? ''));

// Calculate Taxable Value (Subtotal before GST)
$taxable_value = ($invoice['subtotal'] ?? 0) - ($invoice['discount_amount'] ?? 0);

// Initialize GST variables
$cgst_amount = 0; 
$sgst_amount = 0; 
$igst_amount = 0;

if ($company_state === $customer_state && $customer_state != '') {
    // Intra-state: Split GST into CGST and SGST
    $cgst_amount = ($invoice['gst_amount'] ?? 0) / 2;
    $sgst_amount = ($invoice['gst_amount'] ?? 0) / 2;
} else {
    // Inter-state: Full amount to IGST
    $igst_amount = $invoice['gst_amount'] ?? 0;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TAX INVOICE - A4</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
            font-size: 11px;
        }
        /* A4 Page Setup */
        .invoice-container {
            width: 210mm;
            min-height: 297mm;
            margin: 10mm auto;
            padding: 10mm;
            background: white;
            border: 1px solid #000;
            display: flex;
            flex-direction: column;
        }
        .main-table, .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .main-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 11px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .header-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
            border: 1px solid #000;
            border-bottom: none;
            padding: 5px;
        }
        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Items Table */
        .items-table thead th { background: #f2f2f2; }
        .items-table tbody td { border-top: none; border-bottom: none; }
        .items-table tbody tr:last-child td { border-bottom: 1px solid #000; }
        .fill-space { height: 410px; }

        /* Footer Calculations Section */
        .summary-wrapper {
            display: flex;
            border: 1px solid #000;
            border-top: none;
        }
        .words-and-terms { width: 60%; border-right: 1px solid #000; padding: 8px; }
        .tax-calculations { width: 40%; }
        .tax-calculations table { width: 100%; border-collapse: collapse; }
        .tax-calculations td { padding: 5px; border-bottom: 1px solid #eee; }
        
        .signature-section {
            display: flex;
            border: 1px solid #000;
            border-top: none;
            flex-grow: 1;
        }
        .declaration { width: 65%; padding: 8px; border-right: 1px solid #000; font-size: 10px; }
        .auth-sign { width: 35%; padding: 8px; text-align: center; }

        @media print {
            body { background: none; }
            .invoice-container { margin: 0; border: 1px solid #000; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<div class="invoice-container">
    <div class="header-title">TAX INVOICE</div>

    <table class="main-table">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <!-- Company Details Section -->
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; align-items: flex-start; margin-bottom: 5px; gap: 15px;">
                        <?php if (!empty($company['company_logo']) && file_exists($company['company_logo'])): ?>
                            <div style="flex-shrink: 0; margin-top: 2px;">
                                <img src="<?php echo safeHtml($company['company_logo']); ?>" alt="Logo" style="height: 75px; width: 75px; object-fit: contain;">
                            </div>
                        <?php endif; ?>
                        
                        <div style="line-height: 1.4;">
                            <div class="bold" style="font-size: 16px;"><?php echo safeHtml($company['company_name']); ?></div>
                            <div style="font-size: 11px;"><?php echo nl2br(safeHtml($company['company_address'])); ?></div>
                            <div style="font-size: 11px;"><strong>Phone:</strong> <?php echo safeHtml($company['phone'] ?? 'N/A'); ?></div>
                            <div style="font-size: 11px;"><strong>GSTIN/UIN:</strong> <?php echo safeHtml($company['company_gst'] ?? 'N/A'); ?></div>
                            <div style="font-size: 11px;"><strong>State Name:</strong> <?php echo safeHtml($company['state'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Consignee Section -->
                <div style="border-top: 1px solid #000; margin-bottom: 8px;">
                    <div class="bold" style="margin-top: 8px;">Consignee (Ship to)</div>
                    <div class="bold"><?php echo safeHtml($invoice['customer_name'] ?? ''); ?></div>
                    <?php echo nl2br(safeHtml($invoice['shipping_address'] ?? '')); ?><br>
                    <strong>GSTIN :</strong> <?php echo safeHtml($invoice['customer_gstin'] ?? 'N/A'); ?><br>
                    <strong>State :</strong> <?php echo safeHtml($invoice['customer_state'] ?? 'N/A'); ?>
                </div>
                
                <!-- Buyer Section -->
                <div style="border-top: 1px solid #000;">
                    <div class="bold" style="margin-top: 8px;">Buyer (Bill to)</div>
                    <div class="bold"><?php echo safeHtml($invoice['customer_name'] ?? ''); ?></div>
                    <?php echo nl2br(safeHtml($invoice['customer_address'] ?? '')); ?><br>
                    <strong>GSTIN :</strong> <?php echo safeHtml($invoice['customer_gstin'] ?? 'N/A'); ?><br>
                    <strong>State :</strong> <?php echo safeHtml($invoice['customer_state'] ?? 'N/A'); ?>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <!-- Invoice Details Section -->
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Invoice No.</span><br>
                            <?php echo safeHtml($invoice['invoice_no'] ?? 'N/A'); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Dated</span><br>
                            <?php echo safeHtml($invoice['invoice_date'] ?? 'N/A'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Delivery Note</span><br>
                            <?php echo safeHtml($invoice['delivery_note'] ?? ''); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Mode/Terms of Payment</span><br>
                            <?php echo safeHtml($invoice['payment_terms'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Reference No. & Date.</span><br>
                            <?php echo safeHtml($invoice['reference_no'] ?? ''); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Other References</span><br>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Buyer's Order No.</span><br>
                            <?php echo safeHtml($invoice['buyer_order_no'] ?? ''); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Dated</span><br>
                            <?php echo safeHtml($invoice['dated'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Dispatch Doc No.</span><br>
                            <?php echo safeHtml($invoice['dispatch_doc_no'] ?? ''); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Delivery Note Date</span><br>
                            <?php echo safeHtml($invoice['delivery_note_date'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Dispatched through</span><br>
                            <?php echo safeHtml($invoice['dispatched_through'] ?? ''); ?>
                        </td>
                        <td style="width: 50%; border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Destination</span><br>
                            <?php echo safeHtml($invoice['destination'] ?? ''); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="border: 1px solid #000; padding: 5px; font-size: 11px;">
                            <span class="bold">Terms of Delivery</span><br>
                            <?php echo safeHtml($invoice['terms_of_delivery'] ?? ''); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">Sl</th>
                <th width="45%">Description of Services</th>
                <th width="10%">HSN/SAC</th>
                <th width="10%">Qty</th>
                <th width="10%">Rate</th>
                <th width="5%">per</th>
                <th width="15%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
            <tr>
                <td class="text-center"><?php echo $index + 1; ?></td>
                <td>
                    <div class="bold" style="font-size: 14px;"><?php echo safeHtml($item['service_name']); ?></div>
                    <small style="font-size: 12px;"><i>
                        <?php echo nl2br(safeHtml($item['service_description'] ?? '')); ?>
                    </i></small>
                </td>
                <td class="text-center"><?php echo safeHtml($item['sac_code'] ?? ''); ?></td>
                <td class="text-center"><?php echo safeHtml($item['quantity'] ?? ''); ?></td>
                <td class="text-right"><?php echo number_format($item['rate'] ?? 0, 2); ?></td>
                <td class="text-center"><?php echo safeHtml($item['unit'] ?? 'Kgs'); ?></td>
                <td class="text-right bold"><?php echo number_format($item['amount'] ?? 0, 2); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <tr class="fill-space">
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            </tr>
        </tbody>
    </table>

    <div class="summary-wrapper">
        <div class="words-and-terms">
            <div class="bold" style="font-size: 14px;">Amount Chargeable (in words)</div>
            <div style="margin-bottom: 15px; font-size: 12px;"><?php echo number_to_words($invoice['total_amount'] ?? 0); ?> Indian Rupees Only</div>
            
            <div class="bold" style="text-decoration: underline;">Terms and Conditions</div>
            <div style="font-size: 11px; line-height: 1.6;">
                1. Payment should be made by Cheque/Draft/NEFT in favor of the company.<br>
                2. Interest @18% p.a. will be charged if payment is not made within 15 days.
            </div>
        </div>
        <div class="tax-calculations">
            <table>
                <tr>
                    <td style="font-size: 12px;">Taxable Amount</td>
                    <td class="text-right"><?php echo number_format($invoice['subtotal'] ?? 0, 2); ?></td>
                </tr>
                <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                <tr>
                    <td style="font-size: 12px;">Discount</td>
                    <td class="text-right">-<?php echo number_format($invoice['discount_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 12px;">Taxable Value after Discount</td>
                    <td class="text-right"><?php echo number_format($taxable_value, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($igst_amount > 0): ?>
                <tr>
                    <td style="font-size: 12px;">IGST (18%)</td>
                    <td class="text-right"><?php echo number_format($igst_amount, 2); ?></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td style="font-size: 12px;">CGST (9%)</td>
                    <td class="text-right"><?php echo number_format($cgst_amount, 2); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 12px;">SGST (9%)</td>
                    <td class="text-right"><?php echo number_format($sgst_amount, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="bold" style="background: #f9f9f9;">
                    <td style="border-bottom: none;">Grand Total :</td>
                    <td class="text-right" style="border-bottom: none;">₹ <?php echo number_format($invoice['total_amount'] ?? 0, 2); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="signature-section">
        <div class="declaration">
            <div class="bold" style="font-size: 14px;">Declaration:</div>
            We declare that this invoice shows the actual price of the goods/services described and that all particulars are true and correct.
        </div>
        <div class="auth-sign">
            <div>for <strong><?php echo safeHtml($company['company_name']); ?></strong></div>
            <div style="margin-top: 60px;">
                <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto;"></div>
                Authorised Signatory
            </div>
        </div>
    </div>
</div>

</body>
</html>

<?php ob_end_flush(); ?>