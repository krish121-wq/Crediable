<?php
ob_start();
session_start();
require_once 'config.php';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0);
if (!$invoice_id) { 
    die('<div style="font-family:sans-serif;text-align:center;padding:50px 20px;"><h3>Invalid Invoice ID</h3></div>'); 
}

/* ── 1. COMPANY DETAILS ── */
$company = [
    'company_name' => 'Credible Technologies',
    'company_address' => '',
    'company_gst' => '',
    'phone' => '',
    'email' => '',
    'upi_id' => ''
];
try {
    $c_res = $conn->query("SELECT * FROM company_details LIMIT 1");
    if ($c_res && $c_res->num_rows > 0) {
        $company = array_merge($company, $c_res->fetch_assoc());
    }
} catch (Exception $e) {}

/* ── 2. FETCH INVOICE HEADER ── */
$invoice = null;
try {
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {}

/* ── 3. FETCH LINE ITEMS WITH TAX & HSN ── */
$items = [];
try {
    $stmt = $conn->prepare("
        SELECT s.*, p.product_name, p.hsn_code, p.unit,
               ROUND(COALESCE(p.sgst,0), 2) AS sgst_pct, 
               ROUND(COALESCE(p.cgst,0), 2) AS cgst_pct, 
               ROUND(COALESCE(p.igst,0), 2) AS igst_pct,
               ROUND(COALESCE(p.sgst,0) + COALESCE(p.cgst,0) + COALESCE(p.igst,0), 2) AS total_tax_rate
        FROM sales s 
        LEFT JOIN products p ON s.product_id = p.id
        WHERE s.invoice_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {}

if (empty($items) && !$invoice) {
    die('<div style="font-family:sans-serif;text-align:center;padding:50px 20px;"><h3>Invoice Not Found</h3></div>');
}

$customer_name    = $invoice['customer_name']    ?? ($items[0]['customer_name']    ?? 'Walk-in Customer');
$customer_phone   = $invoice['customer_phone']   ?? ($items[0]['customer_phone']   ?? '');
$customer_address = $invoice['customer_address'] ?? ($items[0]['customer_address'] ?? '');
$created_at       = $invoice['created_at']       ?? ($items[0]['created_at']       ?? date('Y-m-d H:i:s'));
$tds_percent      = floatval($invoice['tds_percent'] ?? 0);
$tds_amount       = floatval($invoice['tds_amount'] ?? 0);

/* ── 4. COMPUTE ITEM VALUES & TAX BREAKUPS ── */
$subtotal_excl  = 0;
$total_discount = 0;
$total_sgst     = 0;
$total_cgst     = 0;
$total_igst     = 0;
$total_tax      = 0;
$grand_total    = 0;

foreach ($items as &$it) {
    $price_incl  = (float)$it['price_per_piece'];
    $qty         = (int)$it['quantity'];
    $tax_rate    = (float)$it['total_tax_rate'];
    $sgst_rate   = (float)$it['sgst_pct'];
    $cgst_rate   = (float)$it['cgst_pct'];
    $igst_rate   = (float)$it['igst_pct'];
    $disc_pct    = (float)($it['discount_percent'] ?? 0);

    $price_excl  = $tax_rate > 0 ? $price_incl / (1 + $tax_rate / 100) : $price_incl;
    $gross_excl  = $price_excl * $qty;
    $disc_amt    = $gross_excl * ($disc_pct / 100);
    $taxable     = $gross_excl - $disc_amt;

    $sgst_amt    = $taxable * ($sgst_rate / 100);
    $cgst_amt    = $taxable * ($cgst_rate / 100);
    $igst_amt    = $taxable * ($igst_rate / 100);
    $tax_amt     = $sgst_amt + $cgst_amt + $igst_amt;
    $line_total  = $taxable + $tax_amt;

    $it['_price_excl'] = $price_excl;
    $it['_taxable']    = $taxable;
    $it['_sgst_amt']   = $sgst_amt;
    $it['_cgst_amt']   = $cgst_amt;
    $it['_igst_amt']   = $igst_amt;
    $it['_tax_amt']    = $tax_amt;
    $it['_total']      = $line_total;

    $subtotal_excl  += $gross_excl;
    $total_discount += $disc_amt;
    $total_sgst     += $sgst_amt;
    $total_cgst     += $cgst_amt;
    $total_igst     += $igst_amt;
    $total_tax      += $tax_amt;
    $grand_total    += $line_total;
}
unset($it);

$taxable_total = $subtotal_excl - $total_discount;
$net_payable   = $grand_total - $tds_amount;

/* ── 5. PAYMENTS & OUTSTANDING ── */
$paid_amount = 0;
$payment_methods = [];
try {
    $stmt = $conn->prepare("SELECT amount, payment_method FROM received_payments WHERE invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($ph = $res->fetch_assoc()) {
        $paid_amount += (float)$ph['amount'];
        if (!empty($ph['payment_method'])) {
            $payment_methods[] = $ph['payment_method'];
        }
    }
    $stmt->close();
} catch (Exception $e) {}

$payment_mode_str = !empty($payment_methods) ? implode(', ', array_unique($payment_methods)) : 'Cash';
if ($paid_amount == 0 && !empty($invoice['total_amount'])) {
    // If no explicit received_payments row, treat full amount as settled via invoice total
    $paid_amount = (float)$invoice['total_amount'];
}
$outstanding = max(0, $net_payable - $paid_amount);
$pay_status = ($outstanding <= 0.05) ? 'PAID' : (($paid_amount > 0) ? 'PARTIAL' : 'UNPAID');

/* ── 6. DYNAMIC UPI QR CODE ── */
$invoice_no = !empty($invoice['invoice_no']) ? $invoice['invoice_no'] : ('INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT));
$upi_id = !empty($company['upi_id']) ? $company['upi_id'] : 'credible@upi';
$upi_qr_url = '';
if ($grand_total > 0) {
    $upi_string = "upi://pay?pa=" . urlencode($upi_id)
                . "&pn=" . urlencode($company['company_name'])
                . "&am=" . urlencode(number_format($net_payable, 2, '.', ''))
                . "&tn=" . urlencode($invoice_no) . "&cu=INR";
    $upi_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=3&data=" . urlencode($upi_string);
}

/* ── 7. NUMBER TO WORDS (INDIAN RUPEES) ── */
function numberToWordsIndianReceipt($num) {
    $num = (int)round($num);
    if ($num == 0) return 'Zero';
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $w = function($n) use ($ones,$tens,&$w) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return trim($tens[(int)($n/10)].' '.$ones[$n%10]);
        return trim($ones[(int)($n/100)].' Hundred '.$w($n%100));
    };
    $c = (int)($num/10000000); $num %= 10000000;
    $l = (int)($num/100000);   $num %= 100000;
    $t = (int)($num/1000);     $num %= 1000;
    $parts = [];
    if ($c) $parts[] = $w($c).' Crore';
    if ($l) $parts[] = $w($l).' Lakh';
    if ($t) $parts[] = $w($t).' Thousand';
    if ($num) $parts[] = $w($num);
    return implode(' ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Thermal Receipt – <?= htmlspecialchars($invoice_no) ?></title>
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Courier New', Courier, monospace, 'Lucida Console';
        width: 80mm;
        margin: 0 auto;
        padding: 12px 10px 24px;
        font-size: 11px;
        line-height: 1.35;
        color: #000;
        background: #fff;
    }
    .center { text-align: center; }
    .bold { font-weight: 700; }
    .uppercase { text-transform: uppercase; }
    .right { text-align: right; }
    .left { text-align: left; }
    
    .divider {
        border-top: 1px dashed #000;
        margin: 6px 0;
    }
    .divider-double {
        border-top: 2px dashed #000;
        margin: 7px 0;
    }

    .shop-title {
        font-size: 15px;
        font-weight: 900;
        text-align: center;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }

    .meta-table, .items-table, .totals-table {
        width: 100%;
        border-collapse: collapse;
    }

    .meta-table td {
        padding: 1.5px 0;
        vertical-align: top;
    }

    .items-table th {
        font-weight: 700;
        border-bottom: 1px dashed #000;
        padding: 4px 0;
        font-size: 10.5px;
    }
    .items-table td {
        padding: 3px 0;
        vertical-align: top;
        font-size: 10.5px;
    }

    .totals-table td {
        padding: 2px 0;
    }

    .status-badge {
        display: inline-block;
        border: 1px solid #000;
        padding: 1px 6px;
        font-weight: 700;
        font-size: 10px;
    }

    .qr-container {
        text-align: center;
        margin: 8px 0 4px;
    }
    .qr-container img {
        width: 100px;
        height: 100px;
        display: block;
        margin: 0 auto;
    }

    .btn-print-box {
        position: sticky;
        top: 0;
        background: #fff;
        padding-bottom: 10px;
        z-index: 100;
        display: flex;
        gap: 8px;
    }
    .btn-print {
        background: #000;
        color: #fff;
        border: none;
        padding: 9px 16px;
        width: 100%;
        cursor: pointer;
        font-weight: 700;
        font-size: 12px;
        border-radius: 4px;
    }
    .btn-back {
        background: #e2e8f0;
        color: #000;
        border: none;
        padding: 9px 14px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        border-radius: 4px;
        white-space: nowrap;
    }

    @media print {
        .btn-print-box { display: none !important; }
        body { width: 100%; padding: 0; }
    }
</style>
</head>
<body>

<div class="btn-print-box">
    <button class="btn-print" onclick="window.print()">🖨️ Print Receipt (80mm)</button>
    <a href="sale_invoice.php?id=<?= $invoice_id ?>" class="btn-back">Standard View</a>
</div>

<!-- COMPANY HEADER -->
<div class="shop-title"><?= htmlspecialchars(strtoupper($company['company_name'])) ?></div>
<?php if (!empty($company['company_address'])): ?>
    <div class="center"><?= htmlspecialchars($company['company_address']) ?></div>
<?php endif; ?>
<div class="center">
    <?php if (!empty($company['phone'])): ?>Ph: <?= htmlspecialchars($company['phone']) ?><?php endif; ?>
    <?php if (!empty($company['company_gst'])): ?> | GSTIN: <?= htmlspecialchars($company['company_gst']) ?><?php endif; ?>
</div>

<div class="divider"></div>

<!-- INVOICE & CUSTOMER META -->
<table class="meta-table">
    <tr>
        <td><strong>Receipt #:</strong> <?= htmlspecialchars($invoice_no) ?></td>
        <td class="right"><span class="status-badge"><?= $pay_status ?></span></td>
    </tr>
    <tr>
        <td><strong>Date:</strong> <?= date('d-M-Y h:i A', strtotime($created_at)) ?></td>
        <td class="right"><strong>Mode:</strong> <?= htmlspecialchars($payment_mode_str) ?></td>
    </tr>
    <tr>
        <td colspan="2"><strong>Customer:</strong> <?= htmlspecialchars($customer_name) ?></td>
    </tr>
    <?php if (!empty($customer_phone)): ?>
    <tr>
        <td colspan="2"><strong>Phone:</strong> <?= htmlspecialchars($customer_phone) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($customer_address)): ?>
    <tr>
        <td colspan="2"><strong>Address:</strong> <?= htmlspecialchars($customer_address) ?></td>
    </tr>
    <?php endif; ?>
</table>

<div class="divider-double"></div>

<!-- ITEMS TABLE -->
<table class="items-table">
    <thead>
        <tr>
            <th class="left" style="width:46%;">Item [HSN]</th>
            <th class="center" style="width:14%;">Qty</th>
            <th class="right" style="width:20%;">Rate</th>
            <th class="right" style="width:20%;">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $it): 
            $hsnStr = !empty($it['hsn_code']) ? ' ['.$it['hsn_code'].']' : '';
        ?>
        <tr>
            <td class="left">
                <strong><?= htmlspecialchars($it['product_name']) ?></strong><?= htmlspecialchars($hsnStr) ?><br>
                <span style="font-size:9.5px; opacity:.85;">GST <?= number_format($it['total_tax_rate'], 1) ?>%</span>
            </td>
            <td class="center"><?= $it['quantity'] ?> <?= htmlspecialchars($it['unit'] ?: 'Pcs') ?></td>
            <td class="right">₹<?= number_format($it['_price_excl'], 2) ?></td>
            <td class="right"><strong>₹<?= number_format($it['_total'], 2) ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="divider"></div>

<!-- FINANCIAL TOTALS & TAX BREAKDOWN -->
<table class="totals-table">
    <tr>
        <td>Subtotal (Taxable):</td>
        <td class="right">₹<?= number_format($taxable_total, 2) ?></td>
    </tr>
    <?php if ($total_sgst > 0): ?>
    <tr>
        <td>SGST (9.0%):</td>
        <td class="right">+₹<?= number_format($total_sgst, 2) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($total_cgst > 0): ?>
    <tr>
        <td>CGST (9.0%):</td>
        <td class="right">+₹<?= number_format($total_cgst, 2) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($total_igst > 0): ?>
    <tr>
        <td>IGST:</td>
        <td class="right">+₹<?= number_format($total_igst, 2) ?></td>
    </tr>
    <?php endif; ?>
    <tr class="bold">
        <td>Gross Amount (Incl. GST):</td>
        <td class="right">₹<?= number_format($grand_total, 2) ?></td>
    </tr>
    <?php if ($tds_percent > 0 || $tds_amount > 0): ?>
    <tr style="color:#000;">
        <td>Less: TDS (<?= $tds_percent ?>%):</td>
        <td class="right">-₹<?= number_format($tds_amount, 2) ?></td>
    </tr>
    <?php endif; ?>
</table>

<div class="divider-double"></div>

<!-- NET PAYABLE & PAID BREAKDOWN -->
<table class="totals-table bold" style="font-size:12.5px;">
    <tr>
        <td>NET PAYABLE:</td>
        <td class="right">₹<?= number_format($net_payable, 2) ?></td>
    </tr>
    <tr style="font-size:11px;">
        <td>Amount Paid:</td>
        <td class="right">₹<?= number_format($paid_amount, 2) ?></td>
    </tr>
    <?php if ($outstanding > 0): ?>
    <tr style="font-size:11px;">
        <td>Balance Due:</td>
        <td class="right">₹<?= number_format($outstanding, 2) ?></td>
    </tr>
    <?php endif; ?>
</table>

<div class="divider"></div>

<!-- AMOUNT IN WORDS -->
<div style="font-size:10px; margin: 4px 0;">
    <strong>In Words:</strong> <?= numberToWordsIndianReceipt($net_payable) ?> Rupees Only
</div>

<!-- UPI QR CODE -->
<?php if (!empty($upi_qr_url)): ?>
<div class="divider"></div>
<div class="qr-container">
    <img src="<?= htmlspecialchars($upi_qr_url) ?>" alt="UPI QR Code">
    <div class="bold" style="font-size:9.5px; margin-top:2px;">Scan &amp; Pay: ₹<?= number_format($net_payable, 2) ?></div>
    <div style="font-size:8.5px;"><?= htmlspecialchars($upi_id) ?></div>
</div>
<?php endif; ?>

<div class="divider"></div>

<!-- FOOTER -->
<div class="center" style="font-size:10px; line-height:1.4;">
    <strong>Thank you for your business!</strong><br>
    Goods once sold cannot be returned.<br>
    <em>Computer Generated Thermal Receipt</em>
</div>

</body>
</html>
