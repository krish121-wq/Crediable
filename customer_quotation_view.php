<?php
function number_to_words($number) {
    $dictionary = array(
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven',
        12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen',
        17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy', 80 => 'eighty', 90 => 'ninety'
    );
    if (!is_numeric($number)) return false;
    $number = (int)$number;
    if ($number < 0) return 'minus ' . number_to_words(abs($number));
    if ($number < 21) return $dictionary[$number];
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

$quotation_id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

if (!$quotation_id || !$token) {
    die("Invalid quotation link");
}

$expected_token = base64_encode($quotation_id . '_' . floor(time() / 86400)); 
if ($token !== $expected_token) {
    $token_parts = explode('_', base64_decode($token));
    if (count($token_parts) < 2 || (int)$token_parts[0] !== $quotation_id) {
        die("Invalid or expired quotation link");
    }
}

try {
    $stmt = $conn->prepare("
        SELECT si.*, si.customer_state, si.customer_phone, c.gstin as customer_gst,
               CASE
                   WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                   WHEN staff_users.name IS NOT NULL THEN staff_users.name
                   ELSE 'Unknown'
               END AS created_by_name
        FROM service_quotations si
        LEFT JOIN customers c ON si.customer_id = c.id
        LEFT JOIN users admin_users ON si.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON si.created_by = staff_users.id
        WHERE si.id = ?
    ");
    $stmt->bind_param("i", $quotation_id);
    $stmt->execute();
    $quotation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$quotation) die("Quotation not found");

    $stmt = $conn->prepare("SELECT * FROM service_quotation_items WHERE quotation_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $quotation_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    die("Error fetching quotation: " . $e->getMessage());
}

try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$company) {
        $company = ['company_name' => 'Your Company Name', 'company_address' => 'Your Company Address', 'company_gst' => '', 'phone' => '', 'state' => '', 'email' => ''];
    }
} catch (Exception $e) {
    $company = ['company_name' => 'Your Company Name', 'company_address' => 'Your Company Address', 'company_gst' => '', 'phone' => '', 'state' => '', 'email' => ''];
}

$company_state = strtolower(trim($company['state'] ?? ''));
$customer_state = strtolower(trim($quotation['customer_state'] ?? ''));
$taxable_value = $quotation['subtotal'] - $quotation['discount_amount'];
$cgst_amount = 0; $sgst_amount = 0; $igst_amount = 0;

if ($company_state === $customer_state) {
    $cgst_amount = $quotation['gst_amount'] / 2;
    $sgst_amount = $quotation['gst_amount'] / 2;
} else {
    $igst_amount = $quotation['gst_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation - <?php echo $quotation['quotation_no']; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        
        :root {
            --primary-color: #0284c7;
            --secondary-color: #f0f9ff;
            --text-dark: #0b1220;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }


        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: var(--text-dark);
            font-size: 11.5px;
            line-height: 1.6;
            padding: 20px;
        }

        .a4-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 50px;
            position: relative;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .header-accent {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 10px;
            background: var(--primary-color);
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            align-items: flex-start;
        }

        .company-branding h1 {
            color: var(--primary-color);
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .company-branding p { color: var(--text-muted); font-size: 11px; line-height: 1.4; max-width: 300px; }

        .document-type { text-align: right; }
        .document-type h2 {
            font-size: 32px;
            color: #eee;
            font-weight: 800;
            margin-bottom: 15px;
            margin-top: -5px;
        }

        .meta-card {
            background: var(--secondary-color);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #efe3ff;
            min-width: 180px;
        }

        .meta-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .meta-row span:first-child { font-weight: 600; color: var(--primary-color); }

        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 35px;
        }

        .address-box h3 {
            font-size: 12px;
            text-transform: uppercase;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
            margin-bottom: 12px;
            font-weight: 700;
        }

        .customer-name { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 5px; display: block; }
        .address-details { color: var(--text-muted); font-size: 11px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table thead th {
            background: var(--primary-color);
            color: white;
            padding: 14px 10px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
        }

        .items-table tbody td {
            padding: 15px 10px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }

        .service-name { font-weight: 700; font-size: 12.5px; color: var(--text-dark); display: block; }
        .service-desc { color: var(--text-muted); font-size: 10.5px; margin-top: 5px; display: block; text-align: justify; }

        .summary-section {
            display: flex;
            justify-content: space-between;
        }

        .terms-column { width: 55%; }
        .totals-column { width: 40%; }

        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-color);
        }

        .total-item.grand {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 16px;
            font-weight: 800;
        }

        .words-box {
            margin-top: 25px;
            padding: 12px;
            background: var(--secondary-color);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        .words-box b { color: var(--primary-color); text-transform: uppercase; font-size: 10px; display: block; margin-bottom: 5px; }

        .document-footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .signature-line {
            border-top: 1.5px solid var(--text-dark);
            margin-top: 50px;
            padding-top: 8px;
            font-weight: 700;
            text-align: center;
            width: 180px;
        }

        @media print {
            body { background: white; padding: 0; }
            .a4-container { box-shadow: none; margin: 0; width: 100%; }
            @page { size: A4; margin: 0; }
        }

        @media (max-width: 210mm) {
            body { padding: 10px; }
            .a4-container { width: 100%; padding: 20px; min-height: auto; }
            .address-section { gap: 20px; }
        }
        #quotation-content {
            margin-top: 0 !important;
            top: 0 !important;
        }
        .action-bar {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            gap: 15px;
        }

        .btn-download {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            border: none;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 20px rgba(109, 40, 114, 0.3);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(109, 40, 114, 0.4);
            background: #5a205e;
        }

        @media print {
            .no-print, .action-bar { display: none !important; }
            body { padding: 0; background: white; }
            .a4-container { box-shadow: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body>
<div class="action-bar no-print">
    <button onclick="downloadPDF()" class="btn-download">
        <i class="fas fa-file-pdf"></i> Download Quotation (PDF)
    </button>
</div>

<!-- Container to be converted to PDF -->
<div class="a4-container" id="quotation-content">
    <div class="header-accent"></div>
    
    <div class="header-section">
        <div class="company-branding">
            <h1><?php echo htmlspecialchars($company['company_name']); ?></h1>
            <p><?php echo nl2br(htmlspecialchars($company['company_address'])); ?></p>
            <div style="margin-top: 10px;">
                <p><b>Phone:</b> <?php echo htmlspecialchars($company['phone']); ?></p>
                <p><b>Email:</b> <?php echo htmlspecialchars($company['email']); ?></p>
                <p><b>GSTIN:</b> <?php echo htmlspecialchars($company['company_gst']); ?></p>
            </div>
        </div>
        
        <div class="document-type">
            <h2>QUOTATION</h2>
            <div class="meta-card">
                <div class="meta-row"><span>No.</span> <span><?php echo $quotation['quotation_no']; ?></span></div>
                <div class="meta-row"><span>Date</span> <span><?php echo date('d M Y', strtotime($quotation['quotation_date'])); ?></span></div>
            </div>
        </div>
    </div>

    <div class="address-section">
        <div class="address-box">
            <h3>Bill To</h3>
            <span class="customer-name"><?php echo htmlspecialchars($quotation['customer_name']); ?></span>
            <div class="address-details">
                <p><?php echo nl2br(htmlspecialchars($quotation['customer_address'])); ?></p>
                <p style="margin-top: 8px;"><b>GSTIN:</b> <?php echo htmlspecialchars($quotation['customer_gst'] ?: 'Unregistered'); ?></p>
                <p><b>State:</b> <?php echo htmlspecialchars($quotation['customer_state']); ?></p>
                <p><b>Phone:</b> <?php echo htmlspecialchars($quotation['customer_phone']); ?></p>
            </div>
        </div>
        <div></div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="55%">Service Description</th>
                <th width="10%" style="text-align:center;">Qty</th>
                <th width="15%" style="text-align:right;">Rate</th>
                <th width="15%" style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $index => $item): ?>
            <tr>
                <td><?php echo sprintf('%02d', $index + 1); ?></td>
                <td>
                    <span class="service-name"><?php echo htmlspecialchars($item['service_name']); ?></span>
                    <span class="service-desc"><?php echo nl2br(htmlspecialchars($item['service_description'])); ?></span>
                </td>
                <td style="text-align:center;"><?php echo $item['quantity']; ?></td>
                <td style="text-align:right;"><?php echo number_format($item['rate'], 2); ?></td>
                <td style="text-align:right; font-weight: 700;">₹ <?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-section">
        <div class="terms-column">
            <h3 style="color:var(--primary-color); margin-bottom:10px; font-size:11px; font-weight: 800;">TERMS & CONDITIONS</h3>
            <ul style="padding-left:15px; color:var(--text-muted); font-size:10.5px;">
                <li>Validity: Valid for 30 days from the date of issue.</li>
                <li>Payment: 50% advance, balance upon completion.</li>
                <li>Taxes: Included as per the calculation details.</li>
            </ul>
            
            <div class="words-box">
                <b>Total Amount in Words</b>
                <span style="text-transform: capitalize; font-weight: 600; color: var(--text-dark);"><?php echo number_to_words($quotation['total_amount']); ?> Indian Rupees Only</span>
            </div>
        </div>

        <div class="totals-column">
            <div class="total-item">
                <span>Subtotal</span>
                <span>₹ <?php echo number_format($quotation['subtotal'], 2); ?></span>
            </div>
            <?php if ($quotation['discount_amount'] > 0): ?>
            <div class="total-item" style="color: #be123c;">
                <span>Discount</span>
                <span>- ₹ <?php echo number_format($quotation['discount_amount'], 2); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($igst_amount > 0): ?>
            <div class="total-item">
                <span>IGST (18%)</span>
                <span>₹ <?php echo number_format($igst_amount, 2); ?></span>
            </div>
            <?php else: ?>
            <div class="total-item">
                <span>CGST (9%)</span>
                <span>₹ <?php echo number_format($cgst_amount, 2); ?></span>
            </div>
            <div class="total-item">
                <span>SGST (9%)</span>
                <span>₹ <?php echo number_format($sgst_amount, 2); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-item grand">
                <span>Grand Total</span>
                <span>₹ <?php echo number_format($quotation['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <div class="document-footer">
        <div style="font-size: 10px; color: var(--text-muted); width: 60%;">
            <b>Declaration:</b> We declare that this quotation shows the actual price of the services described and that all particulars are true and correct.
        </div>
        <div>
            <p style="text-align: center; font-size: 10px; color: var(--text-muted);">For <b><?php echo htmlspecialchars($company['company_name']); ?></b></p>
            <div class="signature-line">Authorized Signatory</div>
        </div>
    </div>
</div>
<script>
    function downloadPDF() {
        const element = document.getElementById('quotation-content');
        
      
        const opt = {
            margin:       0,
            filename:     'Quotation_<?php echo $quotation['quotation_no']; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true, 
                letterRendering: true,
                scrollY: 0, 
                scrollX: 0,
                windowWidth: document.documentElement.offsetWidth,
                windowHeight: document.documentElement.offsetHeight
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

   
        const btn = document.querySelector('.btn-download');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.style.opacity = '0.7';
        btn.disabled = true;

       
        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalText;
            btn.style.opacity = '1';
            btn.disabled = false;
        }).catch(err => {
            console.error("PDF Generation Error:", err);
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    }
</script>
</body>
</html>
<?php ob_end_flush(); ?>