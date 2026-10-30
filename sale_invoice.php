<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0);
if (!$invoice_id) { die('<h2 style="font-family:sans-serif;text-align:center;margin-top:80px;color:#571F5A">Invalid invoice link.</h2>'); }

$user_type     = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)($_SESSION['user_id'] ?? 0);

/* ── COMPANY ── */
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$company) $company = [];
} catch (Exception $e) { $company = []; }


$company = array_merge([
    'company_name'=>'Your Company','company_address'=>'','company_gst'=>'','company_logo'=>'',
    'contact_name'=>'','email'=>'','phone'=>'','upi_id'=>'','bank_name'=>'','bank_account'=>'',
    'bank_ifsc'=>'','bank_branch'=>'','website'=>'','state'=>'','pan_number'=>'','alternate_contact'=>''
], $company);

/* ── INVOICE HEADER ── */
try {
    $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id); $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc(); $stmt->close();
} catch (Exception $e) { $invoice = null; }

/* ── LINE ITEMS ── */
try {
    $stmt = $conn->prepare("
        SELECT s.*, p.product_name, p.id AS pid, p.hsn_code,
               ROUND(COALESCE(p.sgst,0), 2) AS sgst, 
               ROUND(COALESCE(p.cgst,0), 2) AS cgst, 
               ROUND(COALESCE(p.igst,0), 2) AS igst,
               ROUND(COALESCE(p.sgst,0) + COALESCE(p.cgst,0) + COALESCE(p.igst,0), 2) AS tax_rate
        FROM sales s 
        JOIN products p ON s.product_id = p.id
        WHERE s.invoice_id = ?
    ");
    $stmt->bind_param("i", $invoice_id); $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Exception $e) { $items = []; }

if (empty($items) && !$invoice)
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:80px;color:#571F5A">Invoice not found.</h2>');

$customer_name    = $invoice['customer_name']    ?? ($items[0]['customer_name']    ?? 'Customer');
$customer_phone   = $invoice['customer_phone']   ?? ($items[0]['customer_phone']   ?? '');
$customer_address = $invoice['customer_address'] ?? ($items[0]['customer_address'] ?? '');
$created_at       = $invoice['created_at']       ?? ($items[0]['created_at']       ?? date('Y-m-d H:i:s'));

/* ── COMPUTE LINE TOTALS ── */
$subtotal_excl  = 0; $total_discount = 0;
$total_sgst     = 0; $total_cgst     = 0; $total_igst = 0;
$total_tax      = 0; $grand_total    = 0;

foreach ($items as &$it) {
    $price_incl  = (float)$it['price_per_piece'];
    $qty         = (int)$it['quantity'];
    $tax_rate    = (float)$it['tax_rate'];
    $sgst_rate   = (float)$it['sgst'];
    $cgst_rate   = (float)$it['cgst'];
    $igst_rate   = (float)$it['igst'];
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
    $it['_gross_excl'] = $gross_excl;
    $it['_disc_amt']   = $disc_amt;
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

/* ── PAYMENTS ── */
$paid_amount = 0; $payment_methods = ''; $payment_history = [];
try {
    $stmt = $conn->prepare("SELECT amount, payment_method, created_at FROM received_payments WHERE invoice_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $invoice_id); $stmt->execute();
    $payment_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    foreach ($payment_history as $ph) $paid_amount += (float)$ph['amount'];
    $methods = array_unique(array_column($payment_history, 'payment_method'));
    $payment_methods = implode(', ', $methods);
} catch (Exception $e) {}

$outstanding    = round($grand_total - $paid_amount, 2);
$payment_status = $paid_amount <= 0.004 ? 'Unpaid'
    : ($outstanding > 0.009 ? 'Partially Paid' : 'Paid');

/* ── DATE ── */
$dateObj = new DateTime($created_at, new DateTimeZone('UTC'));
$dateObj->setTimezone(new DateTimeZone('Asia/Kolkata'));
$invoice_date = $dateObj->format('d M Y');
$invoice_no   = !empty($invoice['invoice_no']) ? $invoice['invoice_no'] : ('INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT));
$due_date_obj = clone $dateObj; $due_date_obj->modify('+30 days');
$due_date     = $due_date_obj->format('d M Y');

/* ── UPI QR ── */
$upi_id = !empty($company['upi_id']) ? $company['upi_id'] : 'credible@upi';
$upi_string = "upi://pay?pa=" . urlencode($upi_id)
            . "&pn=" . urlencode($company['company_name'])
            . "&am=" . urlencode(number_format($grand_total, 2, '.', ''))
            . "&tn=" . urlencode($invoice_no) . "&cu=INR";
$upi_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&data=" . urlencode($upi_string);

/* ── AMOUNT IN WORDS ── */
function numberToWordsIndian($num) {
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
    return trim(implode(' ',$parts));
}
$amount_words = numberToWordsIndian($grand_total) . ' Rupees Only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
<title><?php echo htmlspecialchars($invoice_no); ?> – <?php echo htmlspecialchars($company['company_name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
/* ────────────────────────────────────────────────────────────
   PROFESSIONAL GST INVOICE - GROW BILLER
   A4 Size (210mm × 297mm)
   All icons are Font Awesome (no emojis)
──────────────────────────────────────────────────────────── */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --brand: #0284c7;
    --brand-dark: #0b1220;
    --brand-mid: #0ea5e9;
    --brand-light: #e0f2fe;
    --gold: #0284c7;
    --gold-light: #f0f9ff;
    --success: #16a34a;
    --success-bg: #dcfce7;
    --warning: #d97706;
    --warning-bg: #fef3c7;
    --danger: #dc2626;
    --danger-bg: #fee2e2;
    --sgst: #1d4ed8;
    --cgst: #9d174d;
    --igst: #92400e;
    --ink: #0b1220;
    --ink-mid: #334155;
    --ink-muted: #64748b;
    --paper: #ffffff;
    --paper-soft: #f8fafc;
    --border: #e2e8f0;
    --border-strong: #cbd5e1;
    --radius: 10px;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f1f5f9;
    color: var(--ink);
    font-size: 13px;
    line-height: 1.45;
}


/* ── TOOLBAR (print hide) ── */
.toolbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255,255,255,0.96);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    padding: 10px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.toolbar-brand { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tb-back {
    color: var(--ink-mid);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 14px;
    border-radius: 50px;
    border: 1.5px solid var(--border-strong);
    transition: all 0.2s;
}
.tb-back:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }
.tb-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 0.95rem; color: var(--brand); }
.tb-status {
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.tb-status.paid { background: var(--success-bg); color: var(--success); }
.tb-status.partial { background: var(--warning-bg); color: var(--warning); }
.tb-status.unpaid { background: var(--danger-bg); color: var(--danger); }
.toolbar-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.tbtn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.tbtn-print { background: linear-gradient(135deg, var(--brand), var(--brand-dark)); color: #fff; box-shadow: 0 3px 12px rgba(87,31,90,0.25); }
.tbtn-thermal { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 3px 12px rgba(245,158,11,0.25); }
.tbtn-wa { background: linear-gradient(135deg, #25D366, #128C7E); color: #fff; }
.tbtn-pdf { background: #fff; color: var(--brand); border: 1.5px solid var(--border-strong); }
.tbtn:hover { transform: translateY(-2px); }
.tbtn-print:hover { box-shadow: 0 6px 16px rgba(87,31,90,0.35); }
.tbtn-thermal:hover { box-shadow: 0 6px 16px rgba(245,158,11,0.35); }
.tbtn-pdf:hover { border-color: var(--brand); background: var(--brand-light); }


/* ── SHEET ── */
.sheet-wrap {
    padding: 20px 16px 50px;
    display: flex;
    justify-content: center;
}
.sheet {
    width: 210mm;
    max-width: 100%;
    background: var(--paper);
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(26,18,40,0.08), 0 20px 40px rgba(26,18,40,0.12);
    overflow: hidden;
    position: relative;
}
.band { height: 5px; background: linear-gradient(90deg, var(--brand) 0%, var(--brand-mid) 60%, var(--gold) 100%); }

/* ── HEADER ── */
.inv-header { padding: 28px 35px 20px; display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; border-bottom: 2px solid var(--border); }
.co-block { display: flex; gap: 14px; align-items: flex-start; max-width: 60%; }
.co-logo { width: 60px; height: 60px; border-radius: 12px; object-fit: contain; background: var(--paper-soft); border: 1px solid var(--border); padding: 5px; }
.co-logo-fb {
    width: 60px; height: 60px; border-radius: 12px;
    background: linear-gradient(135deg, var(--brand), var(--brand-mid));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.4rem;
}
.co-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--brand); margin-bottom: 5px; }
.co-meta { font-size: 0.72rem; color: var(--ink-mid); line-height: 1.7; }
.co-meta .row { display: flex; gap: 6px; margin-top: 2px; }
.co-meta .row i { color: var(--brand); width: 14px; font-size: 0.6rem; margin-top: 3px; }

.inv-title-block { text-align: right; }
.inv-word { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.8rem; font-weight: 800; letter-spacing: 4px; color: var(--ink); opacity: 0.85; }
.inv-type-label { font-size: 0.6rem; color: var(--ink-muted); letter-spacing: 1.5px; text-transform: uppercase; margin-top: 3px; }
.inv-meta { margin-top: 12px; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.inv-num-pill { font-family: monospace; font-size: 0.85rem; font-weight: 800; background: var(--brand-light); color: var(--brand); padding: 5px 12px; border-radius: 7px; }
.inv-date-line { font-size: 0.7rem; color: var(--ink-mid); }
.status-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.status-chip.paid { background: var(--success-bg); color: var(--success); }
.status-chip.partial { background: var(--warning-bg); color: var(--warning); }
.status-chip.unpaid { background: var(--danger-bg); color: var(--danger); }
.status-chip .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

/* ── PARTIES ── */
.parties-row {
    padding: 18px 35px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    background: var(--paper-soft);
    border-bottom: 1px solid var(--border);
}
.party-label {
    font-size: 0.6rem;
    font-weight: 800;
    color: var(--brand);
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 8px;
}
.party-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.9rem; font-weight: 700; color: var(--ink); margin-bottom: 5px; }
.party-detail { font-size: 0.72rem; color: var(--ink-mid); line-height: 1.7; }
.party-detail .row { display: flex; gap: 6px; margin-top: 2px; }
.party-detail .row i { width: 14px; font-size: 0.6rem; margin-top: 3px; color: var(--ink-muted); }

/* ── ITEMS TABLE ── */
.items-sec { padding: 20px 35px 10px; }
.sec-title {
    font-size: 0.65rem;
    font-weight: 800;
    color: var(--brand);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sec-title::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, rgba(87,31,90,0.2), transparent); }
.items-table { width: 100%; border-collapse: collapse; font-size: 0.75rem; }
.items-table th {
    background: var(--brand);
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 8px 10px;
    text-align: left;
    white-space: nowrap;
}
.items-table th.nr, .items-table td.nr { text-align: right; }
.items-table td { padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
.items-table tr:last-child td { border-bottom: none; }
.items-table tr:nth-child(even) { background: var(--paper-soft); }
.item-name { font-weight: 700; color: var(--ink); }
.item-sub { font-size: 0.6rem; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px; }
.tax-chip { padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.55rem; }
.tax-chip.sgst { background: #dbeafe; color: var(--sgst); }
.tax-chip.cgst { background: #fce7f3; color: var(--cgst); }
.tax-chip.igst { background: #fef3c7; color: var(--igst); }
.tax-chip.nil { background: var(--success-bg); color: var(--success); }
.sno-col { width: 28px; color: var(--ink-muted); font-weight: 700; }
.tax-breakdown { font-size: 0.6rem; color: var(--ink-muted); margin-top: 2px; line-height: 1.5; }

/* ── TOTALS ── */
.totals-sec { padding: 10px 35px 20px; display: grid; grid-template-columns: 1fr 0.9fr; gap: 24px; align-items: start; }
.amount-words { background: linear-gradient(135deg, var(--brand-light), #ede3f5); border-radius: var(--radius); padding: 14px 16px; border: 1px solid rgba(87,31,90,0.1); }
.aw-label { font-size: 0.6rem; font-weight: 800; color: var(--brand); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; display: flex; align-items: center; gap: 5px; }
.aw-value { font-size: 0.8rem; font-weight: 700; color: var(--ink); line-height: 1.5; }
.pay-history { margin-top: 12px; background: #f8fff9; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 12px; }
.ph-title { font-size: 0.6rem; font-weight: 800; color: var(--success); text-transform: uppercase; margin-bottom: 6px; }
.ph-entry { display: flex; justify-content: space-between; font-size: 0.7rem; padding: 3px 0; border-bottom: 1px dashed #bbf7d0; }
.ph-entry:last-child { border-bottom: none; }
.ph-method { font-weight: 700; }
.ph-date { color: var(--ink-muted); font-size: 0.6rem; }
.ph-amt { font-weight: 800; color: var(--success); }

.totals-box { border: 1.5px solid var(--border); border-radius: var(--radius); overflow: hidden; }
.trow { display: flex; justify-content: space-between; padding: 8px 14px; font-size: 0.75rem; border-bottom: 1px solid var(--border); }
.trow:last-child { border-bottom: none; }
.trow .tv { font-weight: 600; }
.trow.sub-row { background: #fafafa; }
.trow.sgst-row { background: #eff6ff; color: var(--sgst); }
.trow.cgst-row { background: #fdf2f8; color: var(--cgst); }
.trow.igst-row { background: #fffbeb; color: var(--igst); }
.trow.total-gst { background: #f5f3ff; color: #6d28d9; font-weight: 700; }
.trow.grand { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); color: #fff; font-weight: 800; font-size: 0.85rem; }
.trow.paid-row { background: var(--success-bg); color: var(--success); font-weight: 700; }
.trow.due-row { background: var(--danger-bg); color: var(--danger); font-weight: 800; }

/* ── GST SUMMARY ── */
.gst-summary-sec { padding: 0 35px 20px; }
.gst-table-wrap { border: 1px solid var(--border); border-radius: var(--radius); overflow: auto; }
.gst-table-label { font-size: 0.6rem; font-weight: 800; color: var(--brand); padding: 8px 12px; background: var(--paper-soft); border-bottom: 1px solid var(--border); }
.gst-table { width: 100%; border-collapse: collapse; font-size: 0.7rem; }
.gst-table th { padding: 6px 10px; font-size: 0.6rem; font-weight: 700; background: var(--paper-soft); border-bottom: 1px solid var(--border); text-align: left; }
.gst-table th.nr, .gst-table td.nr { text-align: right; }
.gst-table td { padding: 6px 10px; border-bottom: 1px solid var(--border); }
.gst-table tr:last-child td { border-bottom: none; background: #f8f4fc; font-weight: 700; }
.gst-rate-badge { padding: 2px 6px; border-radius: 20px; font-size: 0.55rem; font-weight: 800; }
.gst-rate-badge.sgst { background: #dbeafe; color: var(--sgst); }
.gst-rate-badge.cgst { background: #fce7f3; color: var(--cgst); }
.gst-rate-badge.igst { background: #fef3c7; color: var(--igst); }

/* ── PAYMENT STRIP ── */
.pay-strip { margin: 0 35px 20px; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; display: flex; flex-wrap: wrap; }
.pay-qr { background: var(--gold-light); border-right: 1px solid var(--gold-border, #e8c97c); padding: 14px 18px; display: flex; align-items: center; gap: 12px; flex: 1; min-width: 200px; }
.pay-qr img { width: 70px; height: 70px; border-radius: 8px; background: #fff; padding: 3px; border: 1px solid #e8c97c; }
.pq-title { font-size: 0.6rem; font-weight: 800; color: var(--gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
.pq-upi { font-size: 0.8rem; font-weight: 700; font-family: monospace; }
.pq-note { font-size: 0.65rem; color: var(--ink-mid); margin-top: 3px; }
.pay-bank { padding: 14px 18px; flex: 1.5; }
.pb-title { font-size: 0.6rem; font-weight: 800; color: var(--brand); text-transform: uppercase; margin-bottom: 8px; }
.bank-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
.bf .bfl { font-size: 0.55rem; color: var(--ink-muted); text-transform: uppercase; letter-spacing: 0.3px; }
.bf .bfv { font-size: 0.75rem; font-weight: 700; font-family: monospace; }

/* ── FOOTER ── */
.inv-footer { padding: 16px 35px 20px; display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; border-top: 1px solid var(--border); }
.terms-block { max-width: 55%; }
.terms-title { font-size: 0.6rem; font-weight: 800; color: var(--brand); text-transform: uppercase; margin-bottom: 6px; }
.terms-list { list-style: none; font-size: 0.68rem; color: var(--ink-mid); line-height: 1.7; }
.terms-list li { display: flex; gap: 5px; }
.terms-list li::before { content: '-'; color: var(--gold); font-weight: 800; }
.sig-block { text-align: center; }
.sig-line { width: 140px; height: 45px; border-bottom: 2px solid var(--border-strong); margin-bottom: 5px; }
.sig-label { font-size: 0.65rem; color: var(--ink-muted); font-weight: 600; }
.sig-company { font-size: 0.75rem; font-weight: 800; color: var(--brand); margin-top: 2px; }
.thanks { text-align: center; padding: 10px; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.75rem; font-weight: 700; color: var(--brand); background: var(--brand-light); border-top: 1px dashed var(--border-strong); }
.thanks i { color: var(--gold); margin: 0 4px; }

/* ── PRINT STYLES ── */
@media print {
    html, body { background: #fff; margin: 0; padding: 0; }
    .toolbar { display: none !important; }
    .sheet-wrap { padding: 0; }
    .sheet { box-shadow: none; border-radius: 0; width: 100%; max-width: 100%; margin: 0; }
    @page { size: A4; margin: 10mm 8mm; }
    .items-table, .gst-table { page-break-inside: auto; }
    .items-table tr, .gst-table tr { page-break-inside: avoid; }
    
    body.thermal-mode {
        width: 80mm !important;
        margin: 0 auto !important;
        font-size: 11px !important;
    }
    body.thermal-mode @page {
        size: 80mm auto;
        margin: 2mm;
    }
    body.thermal-mode .sheet {
        width: 80mm !important;
        padding: 4mm !important;
    }
    body.thermal-mode .inv-header,
    body.thermal-mode .parties-row,
    body.thermal-mode .totals-sec,
    body.thermal-mode .gst-summary-sec {
        padding: 6px 0 !important;
        flex-direction: column !important;
        grid-template-columns: 1fr !important;
    }
    body.thermal-mode .co-block { max-width: 100% !important; text-align: center; justify-content: center; }
    body.thermal-mode .inv-title-block { text-align: center !important; }
    body.thermal-mode .inv-meta { align-items: center !important; }
    body.thermal-mode .items-table th, body.thermal-mode .items-table td { padding: 4px 2px !important; font-size: 10px !important; }
}


/* ── RESPONSIVE ── */
@media (max-width: 700px) {
    .inv-header { padding: 18px 18px; flex-direction: column; }
    .co-block { max-width: 100%; }
    .inv-title-block { text-align: left; }
    .inv-meta { align-items: flex-start; }
    .parties-row { grid-template-columns: 1fr; padding: 14px 18px; }
    .items-sec, .totals-sec, .gst-summary-sec, .inv-footer { padding-left: 16px; padding-right: 16px; }
    .totals-sec { grid-template-columns: 1fr; }
    .pay-strip { flex-direction: column; margin-left: 16px; margin-right: 16px; }
    .pay-qr { border-right: none; border-bottom: 1px solid #e8c97c; }
    .terms-block { max-width: 100%; }
    .items-table { display: block; overflow-x: auto; }
}
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="toolbar">
    <div class="toolbar-brand">
        <a href="sales.php" class="tb-back"><i class="fas fa-arrow-left"></i> Sales</a>
        <span class="tb-title"><?php echo htmlspecialchars($invoice_no); ?></span>
        <span class="tb-status <?php echo $payment_status === 'Paid' ? 'paid' : ($payment_status === 'Partially Paid' ? 'partial' : 'unpaid'); ?>">
            <?php echo htmlspecialchars($payment_status); ?>
        </span>
    </div>
    <div class="toolbar-actions">
        <button class="tbtn tbtn-wa" onclick="shareWhatsApp()"><i class="fab fa-whatsapp"></i> Share</button>
        <button class="tbtn tbtn-pdf" onclick="downloadPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
        <button class="tbtn tbtn-print" onclick="printNormal()"><i class="fas fa-print"></i> Print Normal (A4)</button>
        <button class="tbtn tbtn-thermal" onclick="printThermal()"><i class="fas fa-receipt"></i> Print Thermal (POS)</button>
    </div>

</div>

<!-- INVOICE SHEET -->
<div class="sheet-wrap">
<div class="sheet" id="invoiceSheet">
    <div class="band"></div>

    <!-- HEADER -->
    <div class="inv-header">
        <div class="co-block">
            <?php if (!empty($company['company_logo']) && file_exists($company['company_logo'])): ?>
                <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" alt="Logo" class="co-logo">
            <?php else: ?>
                <div class="co-logo-fb"><?php echo htmlspecialchars(strtoupper(substr($company['company_name'] ?? 'C', 0, 1))); ?></div>
            <?php endif; ?>
            <div>

                <div class="co-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
                <div class="co-meta">
                    <?php if (!empty($company['company_address'])): ?>
                    <div class="row"><i class="fas fa-location-dot"></i><span><?php echo nl2br(htmlspecialchars($company['company_address'])); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($company['phone'])): ?>
                    <div class="row"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($company['phone']); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($company['email'])): ?>
                    <div class="row"><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($company['email']); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($company['company_gst'])): ?>
                    <div class="row"><i class="fas fa-receipt"></i><span><strong>GSTIN:</strong> <?php echo htmlspecialchars($company['company_gst']); ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="inv-title-block">
            <div class="inv-word">INVOICE</div>
            <div class="inv-type-label">Tax Invoice / GST Invoice</div>
            <div class="inv-meta">
                <div class="inv-num-pill"><?php echo htmlspecialchars($invoice_no); ?></div>
                <div class="inv-date-line"><i class="fas fa-calendar-alt"></i> Date: <strong><?php echo $invoice_date; ?></strong></div>
                <div class="inv-date-line"><i class="fas fa-calendar-check"></i> Due: <strong><?php echo $due_date; ?></strong></div>
                <div class="status-chip <?php echo $payment_status === 'Paid' ? 'paid' : ($payment_status === 'Partially Paid' ? 'partial' : 'unpaid'); ?>">
                    <span class="dot"></span><?php echo htmlspecialchars($payment_status); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PARTIES -->
    <div class="parties-row">
        <div>
            <div class="party-label"><i class="fas fa-user"></i> Bill To</div>
            <div class="party-name"><?php echo htmlspecialchars($customer_name); ?></div>
            <div class="party-detail">
                <?php if (!empty($customer_address)): ?>
                <div class="row"><i class="fas fa-location-dot"></i><span><?php echo nl2br(htmlspecialchars($customer_address)); ?></span></div>
                <?php endif; ?>
                <?php if (!empty($customer_phone)): ?>
                <div class="row"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($customer_phone); ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="party-label"><i class="fas fa-file-invoice"></i> Invoice Details</div>
            <div class="party-detail">
                <div class="row"><i class="fas fa-hashtag"></i><span>Invoice No: <strong><?php echo htmlspecialchars($invoice_no); ?></strong></span></div>
                <div class="row"><i class="fas fa-calendar-day"></i><span>Date: <strong><?php echo $invoice_date; ?></strong></span></div>
                <div class="row"><i class="fas fa-calendar-check"></i><span>Due Date: <strong><?php echo $due_date; ?></strong></span></div>
                <?php if (!empty($payment_methods)): ?>
                <div class="row"><i class="fas fa-credit-card"></i><span>Mode: <strong><?php echo htmlspecialchars($payment_methods); ?></strong></span></div>
                <?php endif; ?>
                <?php if ($paid_amount > 0): ?>
                <div class="row"><i class="fas fa-check-circle" style="color:var(--success)"></i><span>Paid: <strong>₹<?php echo number_format($paid_amount,2); ?></strong></span></div>
                <?php endif; ?>
                <?php if ($outstanding > 0.009): ?>
                <div class="row"><i class="fas fa-hourglass-half" style="color:var(--danger)"></i><span>Balance Due: <strong>₹<?php echo number_format($outstanding,2); ?></strong></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <div class="items-sec">
        <div class="sec-title"><i class="fas fa-box-open"></i> Items / Products</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Description</th>
                    <th class="nr">HSN/SAC</th>
                    <th class="nr">Qty</th>
                    <th class="nr">Rate (Excl.)</th>
                    <th class="nr">Discount</th>
                    <th class="nr">Taxable</th>
                    <th class="nr">GST Amt</th>
                    <th class="nr">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php $sno = 1; foreach ($items as $it): ?>
                <tr>
                    <td class="sno-col"><?php echo $sno++; ?></td>
                    <td>
                        <div class="item-name"><?php echo htmlspecialchars($it['product_name']); ?></div>
                        <div class="item-sub">
                            <?php if ((float)$it['sgst'] > 0): ?><span class="tax-chip sgst">SGST <?php echo number_format((float)$it['sgst'],1); ?>%</span><?php endif; ?>
                            <?php if ((float)$it['cgst'] > 0): ?><span class="tax-chip cgst">CGST <?php echo number_format((float)$it['cgst'],1); ?>%</span><?php endif; ?>
                            <?php if ((float)$it['igst'] > 0): ?><span class="tax-chip igst">IGST <?php echo number_format((float)$it['igst'],1); ?>%</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="nr"><?php echo !empty($it['hsn_code']) ? htmlspecialchars($it['hsn_code']) : '—'; ?></td>
                    <td class="nr"><?php echo (int)$it['quantity']; ?></td>
                    <td class="nr">₹<?php echo number_format($it['_price_excl'],2); ?></td>
                    <td class="nr"><?php if ($it['_disc_amt'] > 0): ?>₹<?php echo number_format($it['_disc_amt'],2); ?><?php else: ?>—<?php endif; ?></td>
                    <td class="nr">₹<?php echo number_format($it['_taxable'],2); ?></td>
                    <td class="nr">
                        ₹<?php echo number_format($it['_tax_amt'],2); ?>
                        <div class="tax-breakdown">
                            <?php if ($it['_sgst_amt'] > 0): ?>SGST ₹<?php echo number_format($it['_sgst_amt'],2); ?><br><?php endif; ?>
                            <?php if ($it['_cgst_amt'] > 0): ?>CGST ₹<?php echo number_format($it['_cgst_amt'],2); ?><?php endif; ?>
                            <?php if ($it['_igst_amt'] > 0): ?>IGST ₹<?php echo number_format($it['_igst_amt'],2); ?><?php endif; ?>
                        </div>
                    </td>
                    <td class="nr"><strong style="color:var(--brand)">₹<?php echo number_format($it['_total'],2); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TOTALS -->
    <div class="totals-sec">
        <div>
            <div class="amount-words">
                <div class="aw-label"><i class="fas fa-coins"></i> Amount in Words</div>
                <div class="aw-value"><?php echo htmlspecialchars($amount_words); ?></div>
            </div>
            <?php if (!empty($payment_history)): ?>
            <div class="pay-history">
                <div class="ph-title"><i class="fas fa-receipt"></i> Payment History</div>
                <?php foreach ($payment_history as $ph):
                    $phDate = new DateTime($ph['created_at'], new DateTimeZone('UTC'));
                    $phDate->setTimezone(new DateTimeZone('Asia/Kolkata'));
                ?>
                <div class="ph-entry">
                    <span class="ph-method"><?php echo htmlspecialchars($ph['payment_method']); ?></span>
                    <span class="ph-date"><?php echo $phDate->format('d M Y h:i A'); ?></span>
                    <span class="ph-amt">₹<?php echo number_format((float)$ph['amount'],2); ?></span>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;justify-content:space-between;margin-top:5px;padding-top:5px;border-top:1.5px solid #86efac;font-weight:800;">
                    <span>Total Received</span>
                    <span>₹<?php echo number_format($paid_amount,2); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="totals-box">
            <div class="trow sub-row"><span>Subtotal (excl. GST)</span><span class="tv">₹<?php echo number_format($subtotal_excl,2); ?></span></div>
            <?php if ($total_discount > 0.004): ?>
            <div class="trow"><span>Total Discount</span><span class="tv" style="color:var(--success)">-₹<?php echo number_format($total_discount,2); ?></span></div>
            <?php endif; ?>
            <div class="trow sub-row"><span>Taxable Amount</span><span class="tv">₹<?php echo number_format($taxable_total,2); ?></span></div>
            <?php if ($total_sgst > 0.004): ?>
            <div class="trow sgst-row"><span>SGST</span><span class="tv">₹<?php echo number_format($total_sgst,2); ?></span></div>
            <?php endif; ?>
            <?php if ($total_cgst > 0.004): ?>
            <div class="trow cgst-row"><span>CGST</span><span class="tv">₹<?php echo number_format($total_cgst,2); ?></span></div>
            <?php endif; ?>
            <?php if ($total_igst > 0.004): ?>
            <div class="trow igst-row"><span>IGST</span><span class="tv">₹<?php echo number_format($total_igst,2); ?></span></div>
            <?php endif; ?>
            <div class="trow total-gst"><span>Total GST</span><span class="tv">₹<?php echo number_format($total_tax,2); ?></span></div>
            <div class="trow grand"><span>Gross Amount (incl. GST)</span><span class="tv">₹<?php echo number_format($grand_total,2); ?></span></div>
            <?php 
            $inv_tds_pct = floatval($invoice['tds_percent'] ?? 0);
            $inv_tds_amt = floatval($invoice['tds_amount'] ?? 0);
            if ($inv_tds_pct > 0 || $inv_tds_amt > 0): 
            ?>
            <div class="trow" style="color:var(--danger);"><span><i class="fas fa-minus-circle me-1"></i> Less: TDS Deduction (<?php echo $inv_tds_pct; ?>%)</span><span class="tv">-₹<?php echo number_format($inv_tds_amt,2); ?></span></div>
            <div class="trow grand" style="background:#f0f9ff;color:#0284c7;"><span>Net Payable / Receivable</span><span class="tv">₹<?php echo number_format($grand_total - $inv_tds_amt,2); ?></span></div>
            <?php endif; ?>
            <?php if ($paid_amount > 0.004): ?>
            <div class="trow paid-row"><span><i class="fas fa-check-circle"></i> Amount Received</span><span class="tv">₹<?php echo number_format($paid_amount,2); ?></span></div>
            <?php endif; ?>
            <?php if ($outstanding > 0.009): ?>
            <div class="trow due-row"><span><i class="fas fa-hourglass-half"></i> Balance Due</span><span class="tv">₹<?php echo number_format($outstanding,2); ?></span></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GST SUMMARY -->
    <?php if ($total_tax > 0.004): ?>
    <div class="gst-summary-sec">
        <div class="gst-table-wrap">
            <div class="gst-table-label"><i class="fas fa-percent"></i> GST Summary</div>
            <table class="gst-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>HSN</th>
                        <th class="nr">Taxable Value</th>
                        <?php if ($total_sgst > 0.004): ?><th class="nr">SGST</th><th class="nr">SGST Amt</th><?php endif; ?>
                        <?php if ($total_cgst > 0.004): ?><th class="nr">CGST</th><th class="nr">CGST Amt</th><?php endif; ?>
                        <?php if ($total_igst > 0.004): ?><th class="nr">IGST</th><th class="nr">IGST Amt</th><?php endif; ?>
                        <th class="nr">Total Tax</th>
                        <th class="nr">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                        <td><?php echo !empty($it['hsn_code']) ? htmlspecialchars($it['hsn_code']) : '—'; ?></td>
                        <td class="nr">₹<?php echo number_format($it['_taxable'],2); ?></td>
                        <?php if ($total_sgst > 0.004): ?>
                        <td class="nr"><span class="gst-rate-badge sgst"><?php echo number_format((float)$it['sgst'],1); ?>%</span></td>
                        <td class="nr" style="color:var(--sgst)">₹<?php echo number_format($it['_sgst_amt'],2); ?></td>
                        <?php endif; ?>
                        <?php if ($total_cgst > 0.004): ?>
                        <td class="nr"><span class="gst-rate-badge cgst"><?php echo number_format((float)$it['cgst'],1); ?>%</span></td>
                        <td class="nr" style="color:var(--cgst)">₹<?php echo number_format($it['_cgst_amt'],2); ?></td>
                        <?php endif; ?>
                        <?php if ($total_igst > 0.004): ?>
                        <td class="nr"><span class="gst-rate-badge igst"><?php echo number_format((float)$it['igst'],1); ?>%</span></td>
                        <td class="nr" style="color:var(--igst)">₹<?php echo number_format($it['_igst_amt'],2); ?></td>
                        <?php endif; ?>
                        <td class="nr" style="font-weight:700;color:#6d28d9">₹<?php echo number_format($it['_tax_amt'],2); ?></td>
                        <td class="nr" style="font-weight:800;color:var(--brand)">₹<?php echo number_format($it['_total'],2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="2" style="font-weight:800">TOTAL</td>
                    <td class="nr" style="font-weight:800">₹<?php echo number_format($taxable_total,2); ?></td>
                    <?php if ($total_sgst > 0.004): ?>
                    <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--sgst)">₹<?php echo number_format($total_sgst,2); ?></td>
                    <?php endif; ?>
                    <?php if ($total_cgst > 0.004): ?>
                    <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--cgst)">₹<?php echo number_format($total_cgst,2); ?></td>
                    <?php endif; ?>
                    <?php if ($total_igst > 0.004): ?>
                    <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--igst)">₹<?php echo number_format($total_igst,2); ?></td>
                    <?php endif; ?>
                    <td class="nr" style="font-weight:800;color:#6d28d9">₹<?php echo number_format($total_tax,2); ?></td>
                    <td class="nr" style="font-weight:800;color:var(--brand)">₹<?php echo number_format($grand_total,2); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- PAYMENT STRIP -->
    <div class="pay-strip">
        <?php if (!empty($upi_qr_url)): ?>
        <div class="pay-qr">
            <img src="<?php echo htmlspecialchars($upi_qr_url); ?>" alt="UPI QR">
            <div>
                <div class="pq-title"><i class="fas fa-qrcode"></i> Scan & Pay</div>
                <div class="pq-upi"><?php echo htmlspecialchars($upi_id); ?></div>
                <div class="pq-note">GPay | PhonePe | Paytm | BHIM</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($company['bank_name'])): ?>
        <div class="pay-bank">
            <div class="pb-title"><i class="fas fa-university"></i> Bank Transfer</div>
            <div class="bank-grid">
                <div class="bf"><div class="bfl">Bank Name</div><div class="bfv"><?php echo htmlspecialchars($company['bank_name']); ?></div></div>
                <?php if (!empty($company['bank_branch'])): ?>
                <div class="bf"><div class="bfl">Branch</div><div class="bfv"><?php echo htmlspecialchars($company['bank_branch']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($company['bank_account'])): ?>
                <div class="bf"><div class="bfl">Account No.</div><div class="bfv"><?php echo htmlspecialchars($company['bank_account']); ?></div></div>
                <?php endif; ?>
                <?php if (!empty($company['bank_ifsc'])): ?>
                <div class="bf"><div class="bfl">IFSC Code</div><div class="bfv"><?php echo htmlspecialchars($company['bank_ifsc']); ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- FOOTER -->
    <div class="inv-footer">
        <?php if (!empty($invoice['terms_text'])): ?>
        <div class="terms-block">
            <div class="terms-title"><i class="fas fa-file-contract"></i> Terms & Conditions</div>
            <div class="terms-list" style="font-size: 0.82rem; color: #555; white-space: pre-wrap; margin-top: 6px;"><?php echo htmlspecialchars($invoice['terms_text']); ?></div>
        </div>
        <?php elseif (!isset($invoice['terms_text'])): // For older invoices before this feature ?>
        <div class="terms-block">
            <div class="terms-title"><i class="fas fa-file-contract"></i> Terms & Conditions</div>
            <ul class="terms-list">
                <li>Goods once sold will not be taken back or exchanged without prior approval.</li>
                <li>Payment is due within 30 days from invoice date.</li>
                <li>All disputes are subject to <?php echo htmlspecialchars($company['state'] ?: 'local'); ?> jurisdiction.</li>
                <li>This is a computer-generated invoice, no signature required.</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="terms-block"></div>
        <?php endif; ?>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized Signatory</div>
            <div class="sig-company"><?php echo htmlspecialchars($company['company_name']); ?></div>
        </div>
    </div>

    <div class="thanks"><i class="fas fa-heart"></i> Thank you for your business! <i class="fas fa-heart"></i></div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
const invoiceNo = <?php echo json_encode($invoice_no); ?>;
const custPhone = <?php echo json_encode($customer_phone); ?>;
const custName = <?php echo json_encode($customer_name); ?>;
const grandTotal = <?php echo json_encode(number_format($grand_total,2)); ?>;
const pageUrl = window.location.href;

function shareWhatsApp() {
    let phone = (custPhone || '').replace(/\D/g, '');
    if (phone.length === 10) phone = '91' + phone;
    if (!phone) { alert('Customer phone number not available.'); return; }
    const msg = `Hello ${custName},\n\nYour invoice from <?php echo addslashes(htmlspecialchars_decode($company['company_name'])); ?> is ready.\n\nInvoice: ${invoiceNo}\nTotal: ₹${grandTotal}\nStatus: <?php echo $payment_status; ?>\n\nView Invoice: ${pageUrl}\n\nThank you for your business!`;
    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function downloadPDF() {
    const sheet = document.getElementById('invoiceSheet');
    const btn = document.querySelector('.tbtn-pdf');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
    html2canvas(sheet, { scale: 2.5, useCORS: true, backgroundColor: '#ffffff', logging: false }).then(canvas => {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const imgW = pageW;
        const imgH = (canvas.height * imgW) / canvas.width;
        let heightLeft = imgH;
        let position = 0;
        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        pdf.addImage(imgData, 'JPEG', 0, position, imgW, imgH);
        heightLeft -= pageH;
        while (heightLeft > 0) {
            position = heightLeft - imgH;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, position, imgW, imgH);
            heightLeft -= pageH;
        }
        pdf.save(`${invoiceNo}.pdf`);
        btn.innerHTML = '<i class="fas fa-file-pdf"></i> PDF';
        btn.disabled = false;
    }).catch(() => {
        btn.innerHTML = '<i class="fas fa-file-pdf"></i> PDF';
        btn.disabled = false;
        alert('PDF generation failed. Please try printing instead.');
    });
}

function printNormal() {
    document.body.classList.remove('thermal-mode');
    window.print();
}

function printThermal() {
    window.open('print_thermal_invoice.php?id=<?php echo $invoice_id; ?>', '_blank');
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>