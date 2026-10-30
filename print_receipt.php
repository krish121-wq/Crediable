<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized access. Please login.");
}

// Auto-migrate optional columns safely
try {
    $conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(50) NULL");
    $conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS customer_address TEXT NULL");
} catch (Exception $e) {}

$payment_id = (int)($_GET['id'] ?? 0);
$type       = $_GET['type'] ?? 'in'; // 'in' for Received Payment, 'out' for Payment Out / Expense

// Number to Words function
if (!function_exists('numberToWordsIndianReceipt')) {
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
}

// Default Receipt Structure
$receipt = [
    'receipt_no' => 'REC-' . str_pad($payment_id, 5, '0', STR_PAD_LEFT),
    'date' => date('Y-m-d H:i:s'),
    'party_name' => 'General Customer',
    'party_phone' => '',
    'party_address' => '',
    'payment_mode' => 'Cash',
    'amount' => 0.00,
    'total_invoice_amt' => 0.00,
    'total_paid_so_far' => 0.00,
    'balance_due' => 0.00,
    'ref_no' => 'N/A',
    'notes' => 'Payment Voucher Settlement',
    'received_by' => $_SESSION['full_name'] ?? 'Admin'
];

if ($type === 'in') {
    // Try comprehensive query with joins first
    $fetched = false;
    try {
        $stmt = $conn->prepare("
            SELECT rp.*, i.customer_name as party_name, i.customer_phone as party_phone, 
                   i.total_amount as invoice_total, i.id as inv_id
            FROM received_payments rp
            LEFT JOIN invoices i ON rp.invoice_id = i.id
            WHERE rp.id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) {
                $fetched = true;
                $receipt['date'] = $res['created_at'] ?? date('Y-m-d H:i:s');
                $receipt['party_name'] = $res['party_name'] ?: 'Customer';
                $receipt['party_phone'] = $res['party_phone'] ?: '';
                $receipt['payment_mode'] = $res['payment_method'] ?: 'Cash';
                $receipt['amount'] = (float)($res['amount'] ?? 0);
                
                $inv_id = (int)($res['invoice_id'] ?? 0);
                $receipt['ref_no'] = $inv_id ? ('INV-' . str_pad($inv_id, 5, '0', STR_PAD_LEFT)) : 'Direct Receipt';
                
                $total_inv = (float)($res['invoice_total'] ?? $res['amount']);
                $receipt['total_invoice_amt'] = $total_inv > 0 ? $total_inv : $receipt['amount'];

                // Check invoice_no if exists
                if ($inv_id > 0) {
                    try {
                        $inv_res = $conn->query("SELECT invoice_no, customer_address FROM invoices WHERE id = $inv_id LIMIT 1");
                        if ($inv_res && $irow = $inv_res->fetch_assoc()) {
                            if (!empty($irow['invoice_no'])) $receipt['ref_no'] = $irow['invoice_no'];
                            if (!empty($irow['customer_address'])) $receipt['party_address'] = $irow['customer_address'];
                        }
                    } catch (Exception $e) {}

                    // Sum payments for this invoice
                    try {
                        $sum_res = $conn->query("SELECT SUM(amount) as total_paid FROM received_payments WHERE invoice_id = $inv_id");
                        if ($sum_res && $srow = $sum_res->fetch_assoc()) {
                            $receipt['total_paid_so_far'] = (float)$srow['total_paid'];
                        } else {
                            $receipt['total_paid_so_far'] = $receipt['amount'];
                        }
                    } catch (Exception $e) {
                        $receipt['total_paid_so_far'] = $receipt['amount'];
                    }
                } else {
                    $receipt['total_paid_so_far'] = $receipt['amount'];
                }
                $receipt['balance_due'] = max(0, $receipt['total_invoice_amt'] - $receipt['total_paid_so_far']);
            }
        }
    } catch (Exception $e) {}

    // Fallback direct query on received_payments
    if (!$fetched) {
        try {
            $stmt = $conn->prepare("SELECT * FROM received_payments WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $payment_id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($res) {
                    $receipt['date'] = $res['created_at'] ?? date('Y-m-d H:i:s');
                    $receipt['payment_mode'] = $res['payment_method'] ?? 'Cash';
                    $receipt['amount'] = (float)($res['amount'] ?? 0);
                    $receipt['total_invoice_amt'] = $receipt['amount'];
                    $receipt['total_paid_so_far'] = $receipt['amount'];
                    $receipt['balance_due'] = 0.00;
                    if (!empty($res['invoice_id'])) {
                        $receipt['ref_no'] = 'Invoice #' . $res['invoice_id'];
                    }
                }
            }
        } catch (Exception $e) {}
    }
} else {
    // Payment Out / Expense Voucher
    try {
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res) {
                $receipt['receipt_no'] = 'PAYOUT-' . str_pad($res['id'], 5, '0', STR_PAD_LEFT);
                $receipt['date'] = $res['expense_date'] ?? date('Y-m-d');
                $receipt['party_name'] = $res['vendor_name'] ?? $res['expense_category'] ?? 'Supplier / Party';
                $receipt['payment_mode'] = $res['payment_method'] ?? 'Cash';
                $receipt['amount'] = (float)($res['amount'] ?? 0);
                $receipt['ref_no'] = !empty($res['receipt_number']) ? $res['receipt_number'] : ('Voucher #' . $res['id']);
                $receipt['notes'] = $res['notes'] ?? $res['description'] ?? 'Expense / Payment Out Voucher';
                $receipt['total_invoice_amt'] = $receipt['amount'];
                $receipt['total_paid_so_far'] = $receipt['amount'];
                $receipt['balance_due'] = 0.00;
            }
        }
    } catch (Exception $e) {}
}

// Fetch Company Details
$company = [
    'company_name' => 'Credible Technologies',
    'company_address' => '',
    'company_gst' => '',
    'company_phone' => '',
    'company_email' => ''
];
try {
    $comp_res = $conn->query("SELECT * FROM company_details LIMIT 1");
    if ($comp_res && $comp_res->num_rows > 0) {
        $company = array_merge($company, $comp_res->fetch_assoc());
    }
} catch (Exception $e) {}

$banner_title = ($type === 'in') ? 'MONEY RECEIVED / PAYMENT RECEIPT' : 'PAYMENT OUT / EXPENSE VOUCHER';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= ($type === 'in') ? 'Money Received' : 'Payment Voucher' ?> #<?= htmlspecialchars($receipt['receipt_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Inter', Arial, sans-serif;
        font-size: 12px;
        color: #0b1220;
        background: #f1f5f9;
        padding: 24px 10px;
    }

    /* Print Top Actions */
    .print-actions {
        max-width: 750px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .btn-action {
        background: #0284c7;
        color: #ffffff;
        border: none;
        padding: 8px 18px;
        font-weight: 700;
        font-size: 13px;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        box-shadow: 0 2px 6px rgba(2,132,199,0.25);
    }
    .btn-action:hover {
        background: #0369a1;
    }

    /* VYAPAR-STYLE MONEY RECEIVED BOX */
    .receipt-container {
        background: #ffffff;
        max-width: 750px;
        margin: 0 auto;
        border: 2px solid #0b1220;
        box-shadow: 0 6px 24px rgba(11,18,32,0.08);
    }

    /* Header Banner */
    .receipt-banner {
        background: linear-gradient(135deg, #0b1220 0%, #0284c7 100%);
        color: #ffffff;
        padding: 10px 16px;
        text-align: center;
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Grid Table Structure */
    .grid-table {
        width: 100%;
        border-collapse: collapse;
    }
    .grid-table td, .grid-table th {
        border: 1px solid #0b1220;
        padding: 8px 12px;
        vertical-align: top;
    }

    /* Section Subheaders */
    .sec-hdr {
        background: #0284c7;
        color: #ffffff;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        padding: 5px 12px;
        letter-spacing: 0.5px;
    }

    .company-title {
        font-size: 16px;
        font-weight: 800;
        color: #0b1220;
        margin-bottom: 3px;
        text-transform: uppercase;
    }

    .party-val {
        font-size: 14px;
        font-weight: 800;
        color: #0284c7;
        margin-bottom: 2px;
    }

    /* Amount Received Highlighting */
    .amt-highlight-row {
        background: #f0f9ff;
        border-top: 2px solid #0b1220;
        border-bottom: 2px solid #0b1220;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .amt-label {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        color: #0b1220;
    }
    .amt-num {
        font-size: 22px;
        font-weight: 900;
        color: #0284c7;
        font-family: 'Inter', sans-serif;
    }

    .in-words-row {
        padding: 7px 14px;
        background: #ffffff;
        border-bottom: 1.5px solid #0b1220;
        font-size: 11.5px;
    }

    /* Reason Row */
    .reason-row {
        padding: 8px 14px;
        border-bottom: 1.5px solid #0b1220;
        background: #f8fafc;
    }

    /* Split Details Tables */
    .sub-table {
        width: 100%;
        border-collapse: collapse;
    }
    .sub-table td {
        padding: 5px 0;
        border-bottom: 1px dashed #cbd5e1;
        font-size: 11.5px;
    }
    .sub-table tr:last-child td {
        border-bottom: none;
    }

    /* Signatures Section */
    .sign-row {
        border-top: 1.5px solid #0b1220;
        padding: 12px 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        background: #ffffff;
    }
    .sign-box {
        text-align: center;
    }
    .sign-line {
        border-top: 1.5px solid #0b1220;
        width: 180px;
        margin: 50px auto 0;
        padding-top: 4px;
        font-weight: 700;
        font-size: 11px;
    }

    .footer-note {
        text-align: center;
        font-size: 10px;
        color: #64748b;
        padding: 6px;
        background: #f8fafc;
        border-top: 1px solid #cbd5e1;
    }

    @media print {
        body {
            background: #ffffff;
            padding: 0;
        }
        .print-actions {
            display: none !important;
        }
        .receipt-container {
            border: 2px solid #000;
            box-shadow: none;
            width: 100%;
            max-width: 100%;
        }
        .receipt-banner {
            background: #000 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
        }
        .sec-hdr {
            background: #000 !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
        }
        .grid-table td, .grid-table th {
            border-color: #000 !important;
        }
    }
</style>
</head>
<body>

<div class="print-actions">
    <button class="btn-action" onclick="window.print()"><i class="fas fa-print"></i> Print Payment Receipt</button>
</div>

<div class="receipt-container">

    <!-- TOP HEADER BANNER -->
    <div class="receipt-banner">
        <?= $banner_title ?>
    </div>

    <!-- COMPANY HEADER ROW -->
    <div class="sec-hdr">Company Details</div>
    <table class="grid-table" style="border-top:none;">
        <tr>
            <td style="border-top:none;">
                <div class="company-title"><?= htmlspecialchars($company['company_name']) ?></div>
                <?php if (!empty($company['company_address'])): ?>
                    <div><strong>Address:</strong> <?= nl2br(htmlspecialchars($company['company_address'])) ?></div>
                <?php endif; ?>
                <div><strong>Phone No.:</strong> <?= htmlspecialchars($company['phone'] ?? $company['company_phone'] ?? 'N/A') ?></div>
                <div><strong>Email ID:</strong> <?= htmlspecialchars($company['email'] ?? $company['company_email'] ?? 'N/A') ?></div>
                <div><strong>GSTIN:</strong> <?= htmlspecialchars($company['company_gst'] ?: 'N/A') ?></div>
            </td>
        </tr>
    </table>

    <!-- RECEIVED FROM & RECEIPT META (2 COLUMNS) -->
    <table class="grid-table" style="border-top:none;">
        <tr>
            <!-- LEFT COLUMN: PARTY INFO -->
            <td style="width: 55%; padding: 0; border-top:none;">
                <div class="sec-hdr"><?= ($type === 'in') ? 'Received From:' : 'Paid To:' ?></div>
                <div style="padding: 8px 12px;">
                    <div class="party-val"><?= htmlspecialchars($receipt['party_name']) ?></div>
                    <?php if (!empty($receipt['party_address'])): ?>
                        <div><strong>Address:</strong> <?= htmlspecialchars($receipt['party_address']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($receipt['party_phone'])): ?>
                        <div><strong>Phone No.:</strong> <?= htmlspecialchars($receipt['party_phone']) ?></div>
                    <?php endif; ?>
                </div>
            </td>

            <!-- RIGHT COLUMN: RECEIPT META -->
            <td style="width: 45%; padding: 0; border-top:none;">
                <div class="sec-hdr">Receipt Info</div>
                <div style="padding: 8px 12px;">
                    <div><strong>Date:</strong> <?= date('d-M-Y h:i A', strtotime($receipt['date'])) ?></div>
                    <div style="margin-top: 3px;"><strong>Receipt No.:</strong> #<?= htmlspecialchars($receipt['receipt_no']) ?></div>
                    <div style="margin-top: 3px;"><strong>Reference / Doc:</strong> <?= htmlspecialchars($receipt['ref_no']) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- AMOUNT RECEIVED HIGHLIGHT BAR -->
    <div class="amt-highlight-row">
        <div class="amt-label">Amount <?= ($type === 'in') ? 'Received' : 'Paid' ?>:</div>
        <div class="amt-num">₹<?= number_format($receipt['amount'], 2) ?></div>
    </div>

    <!-- AMOUNT IN WORDS -->
    <div class="in-words-row">
        <strong>Amount in Words:</strong> <?= numberToWordsIndianReceipt($receipt['amount']) ?> Rupees Only
    </div>

    <!-- REASON FOR PAYMENT -->
    <div class="reason-row">
        <strong>Reason / Notes for Payment:</strong> <?= htmlspecialchars($receipt['notes']) ?>
    </div>

    <!-- ACCOUNT DETAILS & PAYMENT MODE (SPLIT SECTION) -->
    <table class="grid-table" style="border-top:none; border-bottom:none;">
        <tr>
            <!-- LEFT: ACCOUNT DETAILS -->
            <td style="width: 50%; padding: 0;">
                <div class="sec-hdr">Account Details</div>
                <div style="padding: 8px 12px;">
                    <table class="sub-table">
                        <tr>
                            <td>Total Invoice Value:</td>
                            <td style="text-align: right; font-weight: 700;">₹<?= number_format($receipt['total_invoice_amt'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Total Amount Paid:</td>
                            <td style="text-align: right; font-weight: 700; color: #16a34a;">₹<?= number_format($receipt['total_paid_so_far'], 2) ?></td>
                        </tr>
                        <tr>
                            <td>Current Balance Due:</td>
                            <td style="text-align: right; font-weight: 700; color: <?= ($receipt['balance_due'] > 0) ? '#dc2626' : '#16a34a' ?>;">₹<?= number_format($receipt['balance_due'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- RIGHT: PAYMENT MODE -->
            <td style="width: 50%; padding: 0;">
                <div class="sec-hdr">Payment Mode / Details</div>
                <div style="padding: 8px 12px;">
                    <table class="sub-table">
                        <tr>
                            <td>Payment Mode:</td>
                            <td style="text-align: right;"><strong style="text-transform: uppercase; color: #0284c7;"><?= htmlspecialchars($receipt['payment_mode']) ?></strong></td>
                        </tr>
                        <tr>
                            <td>Transaction Status:</td>
                            <td style="text-align: right;"><span style="color: #16a34a; font-weight: 700;">CONFIRMED / SETTLED</span></td>
                        </tr>
                        <tr>
                            <td>Reference Number:</td>
                            <td style="text-align: right; font-family: monospace; font-size: 11px;"><?= htmlspecialchars($receipt['ref_no']) ?></td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- SIGNATURES -->
    <div class="sign-row">
        <div class="sign-box">
            <div style="font-weight: 700; font-size: 11px; color: #475569; margin-bottom: 2px;">
                Received By: <strong><?= htmlspecialchars($receipt['received_by']) ?></strong>
            </div>
            <div class="sign-line">Payer / Customer Signature</div>
        </div>
        <div class="sign-box">
            <div style="font-weight: 700; font-size: 11px; color: #0b1220;">
                For, <?= htmlspecialchars($company['company_name']) ?>
            </div>
            <div class="sign-line">Authorised Signatory</div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-note">
        This is a computer generated payment voucher receipt. | Credible Suite
    </div>

</div>

</body>
</html>
<?php ob_end_flush(); ?>
