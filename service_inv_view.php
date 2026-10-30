<?php
// ── NUMBER TO WORDS (Indian) ──────────────────────────────────────────────────
if (!function_exists('number_to_words')) {
function number_to_words($number) {
    $ones = ['','one','two','three','four','five','six','seven','eight','nine',
             'ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen',
             'seventeen','eighteen','nineteen'];
    $tens = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
    $n = (int)round(abs($number));
    if ($n == 0) return 'zero';
    $w = function($n) use ($ones, $tens, &$w) {
        if ($n < 20)  return $ones[$n];
        if ($n < 100) return trim($tens[intdiv($n,10)] . ' ' . $ones[$n%10]);
        return trim($ones[intdiv($n,100)] . ' hundred' . ($n%100 ? ' and ' . $w($n%100) : ''));
    };
    $parts = [];
    if ($n >= 10000000) { $parts[] = $w(intdiv($n,10000000)) . ' crore'; $n %= 10000000; }
    if ($n >= 100000)   { $parts[] = $w(intdiv($n,100000))   . ' lakh';  $n %= 100000;   }
    if ($n >= 1000)     { $parts[] = $w(intdiv($n,1000))     . ' thousand'; $n %= 1000;   }
    if ($n > 0)         { $parts[] = $w($n); }
    return implode(' ', $parts);
}
}

if (!function_exists('safeHtml')) {
function safeHtml($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
}

ob_start();
require_once 'config.php';

$invoice_id = (int)($_GET['id'] ?? 0);
if (!$invoice_id) die('<p style="font-family:sans-serif;text-align:center;margin-top:80px">Invalid invoice ID.</p>');

// ── FETCH INVOICE ─────────────────────────────────────────────────────────────
try {
    $stmt = $conn->prepare("
        SELECT si.*,
               COALESCE(c.state,'')   AS customer_state_db,
               COALESCE(c.gstin,'')   AS customer_gstin
        FROM service_invoices si
        LEFT JOIN customers c ON si.customer_id = c.id
        WHERE si.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$invoice) die('<p style="font-family:sans-serif;text-align:center;margin-top:80px">Invoice not found.</p>');

    // ── FETCH ITEMS ───────────────────────────────────────────────────────────
    $stmt = $conn->prepare("SELECT * FROM service_invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) { die("DB Error: " . $e->getMessage()); }

// ── FETCH COMPANY ─────────────────────────────────────────────────────────────
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc() ?? [];
    $stmt->close();
} catch (Exception $e) { $company = []; }
$company = array_merge([
    'company_name'=>'Your Company','company_address'=>'','company_gst'=>'',
    'company_logo'=>'','email'=>'','phone'=>'','state'=>'',
    'pan_number'=>'','upi_id'=>'','bank_name'=>'','bank_account'=>'',
    'bank_ifsc'=>'','bank_branch'=>''
], $company);

// ── GST LOGIC ─────────────────────────────────────────────────────────────────
// sell_price stored in DB is INCLUSIVE; items table stores original amount (pre-GST or as-entered)
// The gst_rate is NOT stored per item in the current schema — it was stored in service_invoice_items
// but the column may not exist yet. We'll try to read it, fall back to deriving from invoice totals.

$company_state  = strtolower(trim($company['state'] ?? ''));
$customer_state = strtolower(trim(
    !empty($invoice['customer_state_db']) ? $invoice['customer_state_db'] : ($invoice['customer_state'] ?? '')
));

$intra_state = ($company_state !== '' && $customer_state !== '' && $company_state === $customer_state);

// Build per-item GST breakdown from items
// Each item: amount = base amount (pre-GST as stored)
// We need the gst_rate per item. Try to read discount_value as gst proxy or use a dedicated column.
// Since gst_rate was NOT stored in service_invoice_items in the original schema,
// we compute it from invoice-level totals proportionally (most accurate fallback).

$items_subtotal   = array_sum(array_column($items, 'amount'));
$invoice_subtotal = (float)($invoice['subtotal'] ?? $items_subtotal);
$invoice_discount = (float)($invoice['discount_amount'] ?? 0);
$invoice_gst      = (float)($invoice['gst_amount'] ?? 0);
$invoice_total    = (float)($invoice['total_amount'] ?? 0);
$taxable_total    = $invoice_subtotal - $invoice_discount;

// GST split
$cgst_total = 0; $sgst_total = 0; $igst_total = 0;
if ($intra_state) {
    $cgst_total = $invoice_gst / 2;
    $sgst_total = $invoice_gst / 2;
} else {
    $igst_total = $invoice_gst;
}

// Per-item GST breakdown (proportional if gst_rate column missing)
foreach ($items as &$it) {
    $it_amount = (float)($it['amount'] ?? 0);
    // Try to read gst_rate column directly (added in newer schema)
    // Column may be named 'gst_rate' or stored via discount_value where discount_type='gst'
    $gst_rate = 0;
    if (isset($it['gst_rate']) && $it['gst_rate'] > 0) {
        $gst_rate = (float)$it['gst_rate'];
    } elseif ($items_subtotal > 0 && $invoice_gst > 0) {
        // Proportional fallback: item's share of total GST
        $it_share = $items_subtotal > 0 ? $it_amount / $items_subtotal : 0;
        $it['_gst_amount'] = $it_share * $invoice_gst;
        $it['_gst_rate_display'] = $items_subtotal > 0 && $it_amount > 0
            ? round(($it['_gst_amount'] / $it_amount) * 100, 2) : 0;
        $it['_cgst'] = $intra_state ? $it['_gst_amount'] / 2 : 0;
        $it['_sgst'] = $intra_state ? $it['_gst_amount'] / 2 : 0;
        $it['_igst'] = !$intra_state ? $it['_gst_amount'] : 0;
        continue;
    }
    // If we have gst_rate
    $disc_pct = 0;
    if (isset($it['discount_type']) && $it['discount_type'] === 'percentage') {
        $disc_pct = (float)($it['discount_value'] ?? 0);
    }
    $after_disc     = $it_amount * (1 - $disc_pct/100);
    $gst_amount     = $after_disc * ($gst_rate / 100);
    $it['_gst_amount']       = $gst_amount;
    $it['_gst_rate_display'] = $gst_rate;
    $it['_cgst'] = $intra_state ? $gst_amount / 2 : 0;
    $it['_sgst'] = $intra_state ? $gst_amount / 2 : 0;
    $it['_igst'] = !$intra_state ? $gst_amount : 0;
}
unset($it);

// Payment info
$paid_amount    = (float)($invoice['paid_amount'] ?? 0);
$outstanding    = $invoice_total - $paid_amount;
$payment_status = $paid_amount <= 0.004 ? 'Unpaid' : ($outstanding > 0.009 ? 'Partial' : 'Paid');

// Invoice meta
$inv_no   = $invoice['invoice_no'] ?: 'INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT);
$inv_date = !empty($invoice['invoice_date']) ? date('d M Y', strtotime($invoice['invoice_date'])) : date('d M Y');
$amount_words = ucwords(number_to_words($invoice_total)) . ' Rupees Only';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo safeHtml($inv_no); ?> — <?php echo safeHtml($company['company_name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
/* ── SCREEN WRAPPER ── */
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:#f4f8fb;font-size:11px;color:#111;}

.screen-toolbar{
    position:sticky;top:0;z-index:100;
    background:#fff;border-bottom:1px solid #ddd;
    padding:10px 24px;
    display:flex;align-items:center;justify-content:space-between;
    gap:12px;flex-wrap:wrap;
    box-shadow:0 2px 8px rgba(0,0,0,.08);
}
.tb-left{display:flex;align-items:center;gap:12px;}
.tb-back{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px;border-radius:50px;
    border:1.5px solid #e2e8f0;color:#0284c7;
    text-decoration:none;font-size:.82rem;font-weight:600;
    transition:all .2s;
}
.tb-back:hover{background:#e0f2fe;border-color:#0284c7;}
.tb-title{font-size:.95rem;font-weight:700;color:#0284c7;}
.tb-status{
    padding:4px 12px;border-radius:50px;
    font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;
}
.tb-status.paid{background:#dcfce7;color:#16a34a;}
.tb-status.partial{background:#fef3c7;color:#d97706;}
.tb-status.unpaid{background:#fee2e2;color:#dc2626;}
.tb-actions{display:flex;gap:8px;flex-wrap:wrap;}
.tbtn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 16px;border-radius:50px;
    font-size:.8rem;font-weight:700;border:none;cursor:pointer;
    text-decoration:none;transition:all .2s;
}
.tbtn-print{background:linear-gradient(135deg,#0284c7,#0b1220);color:#fff;box-shadow:0 4px 12px rgba(2,132,199,.25);}
.tbtn-print:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(2,132,199,.35);color:#fff;}
.tbtn-wa{background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;box-shadow:0 4px 12px rgba(37,211,102,.22);}
.tbtn-wa:hover{transform:translateY(-1px);color:#fff;}

/* ── A4 SHEET ── */
.page-wrap{padding:24px 16px 60px;display:flex;justify-content:center;}
.invoice-sheet{
    width:210mm;
    min-height:297mm;
    background:#fff;
    box-shadow:0 4px 32px rgba(0,0,0,.15);
    display:flex;flex-direction:column;
    font-size:10.5px;
    color:#111;
}

/* ── HEADER BAND ── */
.inv-band{height:5px;background:linear-gradient(90deg,#0284c7 0%,#0ea5e9 50%,#0ea5e9 100%);}

/* ── HEADER ROW ── */
.inv-header{
    display:flex;justify-content:space-between;align-items:flex-start;
    padding:16px 18px 12px;
    border-bottom:2px solid #111;
    gap:16px;
}
.co-block{display:flex;gap:12px;align-items:flex-start;max-width:56%;}
.co-logo{width:64px;height:64px;object-fit:contain;border-radius:8px;border:1px solid #e0e0e0;padding:4px;flex-shrink:0;}
.co-logo-fb{width:64px;height:64px;border-radius:8px;background:linear-gradient(135deg,#0284c7,#0ea5e9);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800;flex-shrink:0;}
.co-name{font-size:14px;font-weight:800;color:#0284c7;margin-bottom:4px;}
.co-meta{font-size:9.5px;color:#444;line-height:1.7;}
.co-meta strong{color:#111;}

.inv-title-block{text-align:right;flex-shrink:0;}
.inv-word{font-size:22px;font-weight:800;letter-spacing:4px;color:#111;text-transform:uppercase;line-height:1;}
.inv-type{font-size:8px;color:#888;letter-spacing:2px;text-transform:uppercase;margin-top:3px;}
.inv-num-pill{
    display:inline-block;
    background:#e0f2fe;color:#0284c7;
    border:1px solid rgba(2,132,199,.15);
    border-radius:6px;padding:5px 12px;
    font-family:monospace;font-size:.85rem;font-weight:700;
    margin-top:10px;letter-spacing:.4px;
}
.inv-date-line{font-size:9px;color:#555;margin-top:4px;}
.inv-status-chip{
    display:inline-flex;align-items:center;gap:5px;
    margin-top:7px;padding:4px 11px;border-radius:50px;
    font-size:8px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;
}
.inv-status-chip.paid{background:#dcfce7;color:#16a34a;}
.inv-status-chip.partial{background:#fef3c7;color:#d97706;}
.inv-status-chip.unpaid{background:#fee2e2;color:#dc2626;}
.inv-status-chip .dot{width:5px;height:5px;border-radius:50%;background:currentColor;}

/* ── PARTIES ROW ── */
.parties-row{
    display:grid;grid-template-columns:1fr 1fr;
    border-bottom:1px solid #ddd;
}
.party-col{padding:12px 18px;}
.party-col:first-child{border-right:1px solid #ddd;}
.party-label{
    font-size:7.5px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;
    color:#0284c7;margin-bottom:7px;
    display:flex;align-items:center;gap:6px;
}
.party-label::before{content:'';width:16px;height:2px;background:#0ea5e9;border-radius:2px;}
.party-name{font-size:11.5px;font-weight:700;color:#111;margin-bottom:3px;}
.party-det{font-size:9.5px;color:#444;line-height:1.65;}
.party-det strong{color:#111;}

/* ── INVOICE META GRID ── */
.meta-grid{
    display:grid;grid-template-columns:1fr 1fr 1fr;
    border-bottom:1px solid #ddd;
}
.meta-cell{
    padding:7px 14px;border-right:1px solid #ddd;
    font-size:9px;
}
.meta-cell:last-child{border-right:none;}
.meta-cell .ml{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#888;margin-bottom:2px;}
.meta-cell .mv{font-weight:600;color:#111;}

/* ── ITEMS TABLE ── */
.items-wrap{flex:1;padding:0 0 0 0;border-bottom:1px solid #ddd;}
.items-table{width:100%;border-collapse:collapse;font-size:9.5px;}
.items-table thead th{
    background:#0284c7;color:#fff;
    padding:8px 10px;font-size:8px;font-weight:700;
    text-transform:uppercase;letter-spacing:.6px;
    text-align:left;white-space:nowrap;
    border-right:1px solid rgba(255,255,255,.15);
}
.items-table thead th:last-child{border-right:none;text-align:right;}
.items-table thead th.nr{text-align:right;}
.items-table tbody td{
    padding:8px 10px;
    border-bottom:1px solid #eee;
    border-right:1px solid #eee;
    vertical-align:top;
}
.items-table tbody td:last-child{border-right:none;text-align:right;font-weight:700;}
.items-table tbody td.nr{text-align:right;}
.items-table tbody tr:last-child td{border-bottom:none;}
.items-table tbody tr:nth-child(even){background:#f8fafc;}
.item-name{font-weight:700;color:#111;font-size:10px;}
.item-desc{font-size:8.5px;color:#666;margin-top:2px;font-style:italic;}
.item-chips{display:flex;gap:4px;flex-wrap:wrap;margin-top:3px;}
.item-chip{
    background:#e0f2fe;color:#0284c7;
    border-radius:4px;padding:1px 5px;
    font-size:7px;font-weight:800;
}

/* ── TOTALS + WORDS SECTION ── */
.totals-section{
    display:grid;grid-template-columns:1.1fr 1fr;
    border-bottom:1px solid #ddd;
}
.words-col{padding:14px 16px;border-right:1px solid #ddd;}
.aw-label{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#0284c7;margin-bottom:5px;}
.aw-text{font-size:10px;font-weight:700;color:#111;line-height:1.5;font-style:italic;}
.terms-title{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#555;margin-top:12px;margin-bottom:5px;}
.terms-list{font-size:8px;color:#555;line-height:1.8;}
.terms-list li{margin-left:12px;}

.totals-col{padding:10px 14px;}
.trow{display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:9.5px;color:#444;border-bottom:1px solid #f0eef6;}
.trow:last-child{border-bottom:none;}
.trow .tv{font-weight:600;color:#111;}
.trow.gst-row{background:#eff8ff;border-radius:4px;padding:4px 6px;margin:2px 0;}
.trow.gst-row .tv{color:#0284c7;}
.trow.grand{
    background:linear-gradient(135deg,#0284c7,#0ea5e9);
    color:#fff;border-radius:6px;padding:8px 10px;margin-top:6px;
    font-weight:800;font-size:11px;
}
.trow.grand .tv{color:#fff;}
.trow.paid-row{color:#16a34a;font-weight:700;}
.trow.paid-row .tv{color:#16a34a;}
.trow.due-row{background:#fee2e2;color:#dc2626;font-weight:800;border-radius:4px;padding:5px 8px;margin-top:4px;}
.trow.due-row .tv{color:#dc2626;}

/* ── GST SUMMARY TABLE ── */
.gst-summary{padding:10px 16px;border-bottom:1px solid #ddd;}
.gst-sum-title{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#0284c7;margin-bottom:7px;display:flex;align-items:center;gap:5px;}
.gst-table{width:100%;border-collapse:collapse;font-size:9px;}
.gst-table th{
    background:#f5f4f8;color:#555;
    padding:5px 9px;font-size:7.5px;font-weight:700;
    text-transform:uppercase;letter-spacing:.5px;text-align:left;
    border-bottom:1px solid #f4f8fb;
}
.gst-table th.nr{text-align:right;}
.gst-table td{padding:5px 9px;border-bottom:1px solid #f0eef6;color:#333;}
.gst-table td.nr{text-align:right;}
.gst-table tbody tr:last-child td{border-bottom:none;font-weight:700;background:#eff8ff;}

/* ── PAYMENT / BANK STRIP ── */
.pay-strip{
    display:grid;grid-template-columns:1fr 1fr;
    border-bottom:1px solid #ddd;
}
.pay-qr-col{padding:12px 16px;border-right:1px solid #ddd;display:flex;align-items:center;gap:12px;background:#eff6fc;}
.pay-qr-col img{width:72px;height:72px;border-radius:6px;border:1px solid #7dd3fc;background:#fff;padding:3px;}
.pq-title{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#0ea5e9;margin-bottom:3px;}
.pq-upi{font-family:monospace;font-size:10px;font-weight:700;color:#111;}
.pq-note{font-size:8px;color:#666;margin-top:3px;}
.pay-bank-col{padding:12px 16px;}
.pb-title{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#0284c7;margin-bottom:8px;}
.bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;}
.bf .bfl{font-size:7px;color:#888;text-transform:uppercase;letter-spacing:.4px;font-weight:600;}
.bf .bfv{font-size:9.5px;font-weight:700;color:#111;font-family:monospace;margin-top:1px;}

/* ── FOOTER ── */
.inv-footer{
    display:flex;justify-content:space-between;align-items:flex-end;
    padding:14px 18px 16px;gap:20px;
}
.declaration-block{max-width:58%;}
.dec-title{font-size:7.5px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#555;margin-bottom:4px;}
.dec-text{font-size:8.5px;color:#555;line-height:1.55;}
.sig-block{text-align:center;flex-shrink:0;}
.sig-for{font-size:8.5px;color:#555;margin-bottom:4px;}
.sig-company{font-size:10px;font-weight:800;color:#0284c7;}
.sig-line{width:140px;height:44px;border-bottom:1.5px solid #bbb;margin:0 auto 5px;}
.sig-label{font-size:8px;color:#777;}

/* ── THANKS ── */
.inv-thanks{
    text-align:center;padding:10px 16px;
    font-size:9px;font-weight:700;letter-spacing:.5px;color:#0284c7;
    background:#e0f2fe;border-top:1px dashed #e2e8f0;
}

/* ── PRINT ── */
@media print {
    body{background:#fff;}
    .screen-toolbar{display:none!important;}
    .page-wrap{padding:0;}
    .invoice-sheet{box-shadow:none;width:210mm;min-height:297mm;}
    @page{size:A4;margin:6mm;}
}
@media(max-width:640px){
    .inv-header{flex-direction:column;}
    .inv-title-block{text-align:left;}
    .parties-row{grid-template-columns:1fr;}
    .meta-grid{grid-template-columns:1fr 1fr;}
    .totals-section{grid-template-columns:1fr;}
    .pay-strip{grid-template-columns:1fr;}
    .items-table{display:block;overflow-x:auto;}
    .bank-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- TOOLBAR (hidden on print) -->
<div class="screen-toolbar">
    <div class="tb-left">
        <a href="service_invoice.php" class="tb-back"><i class="fas fa-arrow-left" style="font-size:.75rem"></i> Back</a>
        <span class="tb-title"><?php echo safeHtml($inv_no); ?></span>
        <span class="tb-status <?php echo strtolower($payment_status); ?>"><?php echo safeHtml($payment_status); ?></span>
    </div>
    <div class="tb-actions">
        <button class="tbtn tbtn-wa" onclick="shareWhatsApp()">
            <i class="fab fa-whatsapp"></i> Share
        </button>
        <button class="tbtn tbtn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
    </div>
</div>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- INVOICE SHEET -->
<div class="page-wrap">
<div class="invoice-sheet" id="invoiceSheet">

    <div class="inv-band"></div>

    <!-- ── HEADER ── -->
    <div class="inv-header">
        <div class="co-block">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?php echo safeHtml($company['company_logo']); ?>" alt="Logo" class="co-logo"
                     onerror="this.outerHTML='<div class=\'co-logo-fb\'><?php echo safeHtml(strtoupper(substr($company['company_name'],0,1))); ?></div>'">
            <?php else: ?>
                <div class="co-logo-fb"><?php echo safeHtml(strtoupper(substr($company['company_name'],0,1))); ?></div>
            <?php endif; ?>
            <div>
                <div class="co-name"><?php echo safeHtml($company['company_name']); ?></div>
                <div class="co-meta">
                    <?php if (!empty($company['company_address'])): ?>
                        <?php echo nl2br(safeHtml($company['company_address'])); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($company['phone'])): ?>
                        <strong>Phone:</strong> <?php echo safeHtml($company['phone']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($company['email'])): ?>
                        <strong>Email:</strong> <?php echo safeHtml($company['email']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($company['company_gst'])): ?>
                        <strong>GSTIN:</strong> <?php echo safeHtml($company['company_gst']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($company['pan_number'])): ?>
                        <strong>PAN:</strong> <?php echo safeHtml($company['pan_number']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($company['state'])): ?>
                        <strong>State:</strong> <?php echo safeHtml($company['state']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="inv-title-block">
            <div class="inv-word">INVOICE</div>
            <div class="inv-type">Tax Invoice / Service Invoice</div>
            <div class="inv-num-pill"><?php echo safeHtml($inv_no); ?></div>
            <div class="inv-date-line" style="margin-top:5px">
                <strong>Date:</strong> <?php echo $inv_date; ?>
                <?php if (!empty($invoice['invoice_date'])): ?>
                &nbsp;&nbsp;<strong>Due:</strong> <?php echo date('d M Y', strtotime($invoice['invoice_date'] . ' +30 days')); ?>
                <?php endif; ?>
            </div>
            <div style="margin-top:6px">
                <span class="inv-status-chip <?php echo strtolower($payment_status); ?>">
                    <span class="dot"></span><?php echo safeHtml($payment_status); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- ── PARTIES ── -->
    <div class="parties-row">
        <div class="party-col">
            <div class="party-label">Bill To</div>
            <div class="party-name"><?php echo safeHtml($invoice['customer_name'] ?? ''); ?></div>
            <div class="party-det">
                <?php if (!empty($invoice['customer_address'])): ?>
                    <?php echo nl2br(safeHtml($invoice['customer_address'])); ?><br>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_phone'])): ?>
                    <strong>Phone:</strong> <?php echo safeHtml($invoice['customer_phone']); ?><br>
                <?php endif; ?>
                <?php $cgstin = $invoice['customer_gstin'] ?? ''; ?>
                <?php if (!empty($cgstin)): ?>
                    <strong>GSTIN:</strong> <?php echo safeHtml($cgstin); ?><br>
                <?php endif; ?>
                <?php $cstate = $invoice['customer_state_db'] ?: ($invoice['customer_state'] ?? ''); ?>
                <?php if (!empty($cstate)): ?>
                    <strong>State:</strong> <?php echo safeHtml($cstate); ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="party-col">
            <div class="party-label">Ship To</div>
            <div class="party-name"><?php echo safeHtml($invoice['customer_name'] ?? ''); ?></div>
            <div class="party-det">
                <?php $shpAddr = !empty($invoice['shipping_address']) ? $invoice['shipping_address'] : ($invoice['customer_address'] ?? ''); ?>
                <?php if (!empty($shpAddr)): ?>
                    <?php echo nl2br(safeHtml($shpAddr)); ?><br>
                <?php endif; ?>
                <?php if (!empty($cgstin)): ?>
                    <strong>GSTIN:</strong> <?php echo safeHtml($cgstin); ?><br>
                <?php endif; ?>
                <?php if (!empty($cstate)): ?>
                    <strong>State:</strong> <?php echo safeHtml($cstate); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── INVOICE META FIELDS ── -->
    <?php
    $meta_fields = [
        ['Delivery Note',       $invoice['delivery_note']      ?? ''],
        ['Mode / Terms',        $invoice['payment_terms']      ?? ''],
        ['Reference No.',       $invoice['reference_no']       ?? ''],
        ['Buyer Order No.',     $invoice['buyer_order_no']     ?? ''],
        ['Dispatch Doc No.',    $invoice['dispatch_doc_no']    ?? ''],
        ['Delivery Note Date',  !empty($invoice['delivery_note_date']) ? date('d M Y',strtotime($invoice['delivery_note_date'])) : ''],
        ['Dispatched Through',  $invoice['dispatched_through'] ?? ''],
        ['Destination',         $invoice['destination']        ?? ''],
    ];
    $non_empty = array_filter($meta_fields, fn($m) => !empty(trim($m[1])));
    if (!empty($non_empty)):
    ?>
    <div class="meta-grid" style="grid-template-columns:repeat(<?php echo min(count($non_empty),4); ?>,1fr)">
        <?php foreach ($non_empty as $m): ?>
        <div class="meta-cell">
            <div class="ml"><?php echo safeHtml($m[0]); ?></div>
            <div class="mv"><?php echo safeHtml($m[1]); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty(trim($invoice['terms_of_delivery'] ?? ''))): ?>
    <div style="padding:6px 16px;border-bottom:1px solid #ddd;font-size:9px;color:#444;background:#f8fafc">
        <strong style="color:#555">Terms of Delivery:</strong> <?php echo safeHtml($invoice['terms_of_delivery']); ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── ITEMS TABLE ── -->
    <div class="items-wrap">
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:36%">Service Description</th>
                    <th style="width:9%" class="nr">SAC</th>
                    <th style="width:7%" class="nr">Qty</th>
                    <th style="width:7%" class="nr">Unit</th>
                    <th style="width:10%" class="nr">Rate</th>
                    <th style="width:10%" class="nr">Amount</th>
                    <?php if ($invoice_gst > 0): ?>
                    <th style="width:8%" class="nr">GST %</th>
                    <th style="width:9%" class="nr">GST Amt</th>
                    <?php endif; ?>
                    <th style="width:10%">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $idx => $it):
                $it_gst     = $it['_gst_amount']       ?? 0;
                $it_gst_pct = $it['_gst_rate_display'] ?? 0;
                $it_total   = (float)($it['amount'] ?? 0) + $it_gst;
            ?>
            <tr>
                <td class="nr"><?php echo $idx+1; ?></td>
                <td>
                    <div class="item-name"><?php echo safeHtml($it['service_name'] ?? ''); ?></div>
                    <?php if (!empty($it['service_description'])): ?>
                    <div class="item-desc"><?php echo nl2br(safeHtml($it['service_description'])); ?></div>
                    <?php endif; ?>
                    <?php if ($it_gst > 0): ?>
                    <div class="item-chips">
                        <?php if ($intra_state): ?>
                        <span class="item-chip">CGST <?php echo number_format($it_gst_pct/2,1); ?>%</span>
                        <span class="item-chip">SGST <?php echo number_format($it_gst_pct/2,1); ?>%</span>
                        <?php else: ?>
                        <span class="item-chip">IGST <?php echo number_format($it_gst_pct,1); ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="nr"><?php echo safeHtml($it['sac_code'] ?? '—'); ?></td>
                <td class="nr"><?php echo number_format((float)($it['quantity']??1),2); ?></td>
                <td class="nr"><?php echo safeHtml($it['unit'] ?? '—'); ?></td>
                <td class="nr">₹<?php echo number_format((float)($it['rate']??0),2); ?></td>
                <td class="nr">₹<?php echo number_format((float)($it['amount']??0),2); ?></td>
                <?php if ($invoice_gst > 0): ?>
                <td class="nr"><?php echo $it_gst_pct > 0 ? number_format($it_gst_pct,1).'%' : '—'; ?></td>
                <td class="nr">₹<?php echo number_format($it_gst,2); ?></td>
                <?php endif; ?>
                <td>₹<?php echo number_format($it_total,2); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ── TOTALS + AMOUNT IN WORDS ── -->
    <div class="totals-section">
        <div class="words-col">
            <div class="aw-label">Amount in Words</div>
            <div class="aw-text"><?php echo safeHtml(ucwords($amount_words)); ?></div>

            <div class="terms-title">Terms &amp; Conditions</div>
            <ul class="terms-list">
                <li>Payment should be made by Cheque/NEFT/UPI in favour of the company.</li>
                <li>Interest @18% p.a. will be charged if payment is not made within 30 days.</li>
                <li>All disputes subject to <?php echo safeHtml($company['state'] ?: 'local'); ?> jurisdiction only.</li>
            </ul>
        </div>
        <div class="totals-col">
            <div class="trow"><span>Subtotal</span><span class="tv">₹<?php echo number_format($invoice_subtotal,2); ?></span></div>
            <?php if ($invoice_discount > 0.004): ?>
            <div class="trow"><span>Discount</span><span class="tv" style="color:#16a34a">−₹<?php echo number_format($invoice_discount,2); ?></span></div>
            <?php endif; ?>
            <div class="trow"><span>Taxable Amount</span><span class="tv">₹<?php echo number_format($taxable_total,2); ?></span></div>
            <?php if ($intra_state && $cgst_total > 0.004): ?>
            <div class="trow gst-row"><span>CGST</span><span class="tv">₹<?php echo number_format($cgst_total,2); ?></span></div>
            <div class="trow gst-row"><span>SGST</span><span class="tv">₹<?php echo number_format($sgst_total,2); ?></span></div>
            <?php elseif (!$intra_state && $igst_total > 0.004): ?>
            <div class="trow gst-row"><span>IGST</span><span class="tv">₹<?php echo number_format($igst_total,2); ?></span></div>
            <?php endif; ?>
            <?php if ($invoice_gst > 0.004): ?>
            <div class="trow" style="font-weight:700"><span>Total GST</span><span class="tv" style="color:#0284c7">₹<?php echo number_format($invoice_gst,2); ?></span></div>
            <?php endif; ?>
            <div class="trow grand"><span>Grand Total</span><span class="tv">₹<?php echo number_format($invoice_total,2); ?></span></div>
            <?php if ($paid_amount > 0.004): ?>
            <div class="trow paid-row"><span>✓ Amount Paid</span><span class="tv">₹<?php echo number_format($paid_amount,2); ?></span></div>
            <?php endif; ?>
            <?php if ($outstanding > 0.009): ?>
            <div class="trow due-row"><span>⏳ Balance Due</span><span class="tv">₹<?php echo number_format($outstanding,2); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($invoice['payment_method'])): ?>
            <div class="trow" style="margin-top:4px;font-size:9px;color:#555">
                <span>Payment Via</span><span class="tv" style="color:#555"><?php echo safeHtml($invoice['payment_method']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── GST SUMMARY TABLE ── -->
    <?php if ($invoice_gst > 0.004): ?>
    <div class="gst-summary">
        <div class="gst-sum-title"><span style="color:#0ea5e9">%</span> GST Tax Summary</div>
        <table class="gst-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>SAC</th>
                    <th class="nr">Taxable Value</th>
                    <?php if ($intra_state): ?>
                    <th class="nr">CGST Rate</th>
                    <th class="nr">CGST Amt</th>
                    <th class="nr">SGST Rate</th>
                    <th class="nr">SGST Amt</th>
                    <?php else: ?>
                    <th class="nr">IGST Rate</th>
                    <th class="nr">IGST Amt</th>
                    <?php endif; ?>
                    <th class="nr">Total Tax</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it):
                $it_gst     = $it['_gst_amount'] ?? 0;
                $it_gst_pct = $it['_gst_rate_display'] ?? 0;
                $it_cgst    = $it['_cgst'] ?? 0;
                $it_sgst    = $it['_sgst'] ?? 0;
                $it_igst    = $it['_igst'] ?? 0;
            ?>
            <tr>
                <td><?php echo safeHtml($it['service_name'] ?? ''); ?></td>
                <td><?php echo safeHtml($it['sac_code'] ?? '—'); ?></td>
                <td class="nr">₹<?php echo number_format((float)($it['amount']??0),2); ?></td>
                <?php if ($intra_state): ?>
                <td class="nr"><?php echo number_format($it_gst_pct/2,1); ?>%</td>
                <td class="nr">₹<?php echo number_format($it_cgst,2); ?></td>
                <td class="nr"><?php echo number_format($it_gst_pct/2,1); ?>%</td>
                <td class="nr">₹<?php echo number_format($it_sgst,2); ?></td>
                <?php else: ?>
                <td class="nr"><?php echo number_format($it_gst_pct,1); ?>%</td>
                <td class="nr">₹<?php echo number_format($it_igst,2); ?></td>
                <?php endif; ?>
                <td class="nr">₹<?php echo number_format($it_gst,2); ?></td>
            </tr>
            <?php endforeach; ?>
            <!-- Total row -->
            <tr>
                <td colspan="2" style="font-weight:700">Total</td>
                <td class="nr" style="font-weight:700">₹<?php echo number_format($taxable_total,2); ?></td>
                <?php if ($intra_state): ?>
                <td class="nr"></td>
                <td class="nr" style="font-weight:700">₹<?php echo number_format($cgst_total,2); ?></td>
                <td class="nr"></td>
                <td class="nr" style="font-weight:700">₹<?php echo number_format($sgst_total,2); ?></td>
                <?php else: ?>
                <td class="nr"></td>
                <td class="nr" style="font-weight:700">₹<?php echo number_format($igst_total,2); ?></td>
                <?php endif; ?>
                <td class="nr" style="font-weight:700">₹<?php echo number_format($invoice_gst,2); ?></td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ── PAYMENT / BANK STRIP ── -->
    <?php if (!empty($company['upi_id']) || !empty($company['bank_name'])): ?>
    <div class="pay-strip">
        <?php if (!empty($company['upi_id'])): ?>
        <?php
        $upi_str = "upi://pay?pa=" . urlencode($company['upi_id'])
                 . "&pn=" . urlencode($company['company_name'])
                 . "&am=" . urlencode(number_format($invoice_total,2,'.',''))
                 . "&tn=" . urlencode($inv_no) . "&cu=INR";
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=4&data=" . urlencode($upi_str);
        ?>
        <div class="pay-qr-col">
            <img src="<?php echo $qr_url; ?>" alt="UPI QR">
            <div>
                <div class="pq-title">Scan &amp; Pay</div>
                <div class="pq-upi"><?php echo safeHtml($company['upi_id']); ?></div>
                <div class="pq-note">GPay · PhonePe · Paytm · BHIM</div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($company['bank_name'])): ?>
        <div class="pay-bank-col">
            <div class="pb-title">Bank Transfer Details</div>
            <div class="bank-grid">
                <div class="bf"><div class="bfl">Bank Name</div><div class="bfv" style="font-family:'Inter',sans-serif"><?php echo safeHtml($company['bank_name']); ?></div></div>
                <?php if (!empty($company['bank_branch'])): ?><div class="bf"><div class="bfl">Branch</div><div class="bfv" style="font-family:'Inter',sans-serif"><?php echo safeHtml($company['bank_branch']); ?></div></div><?php endif; ?>
                <?php if (!empty($company['bank_account'])): ?><div class="bf"><div class="bfl">Account No.</div><div class="bfv"><?php echo safeHtml($company['bank_account']); ?></div></div><?php endif; ?>
                <?php if (!empty($company['bank_ifsc'])): ?><div class="bf"><div class="bfl">IFSC Code</div><div class="bfv"><?php echo safeHtml($company['bank_ifsc']); ?></div></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── FOOTER ── -->
    <div class="inv-footer">
        <div class="declaration-block">
            <div class="dec-title">Declaration</div>
            <div class="dec-text">We declare that this invoice shows the actual price of the services described and that all particulars are true and correct. This is a computer generated invoice.</div>
        </div>
        <div class="sig-block">
            <div class="sig-for">For <strong><?php echo safeHtml($company['company_name']); ?></strong></div>
            <div class="sig-line"></div>
            <div class="sig-label">Authorised Signatory</div>
        </div>
    </div>

    <div class="inv-thanks">
        ❤ &nbsp;Thank you for your business&nbsp; ❤
    </div>

</div><!-- /invoice-sheet -->
</div><!-- /page-wrap -->

<script>
const custPhone  = <?php echo json_encode($invoice['customer_phone'] ?? ''); ?>;
const custName   = <?php echo json_encode($invoice['customer_name'] ?? ''); ?>;
const invNo      = <?php echo json_encode($inv_no); ?>;
const invTotal   = <?php echo json_encode('₹'.number_format($invoice_total,2)); ?>;
const pageUrl    = window.location.href;

function shareWhatsApp() {
    let phone = (custPhone || '').replace(/\D/g,'');
    if (phone.length === 10) phone = '91' + phone;
    if (!phone) { alert('Customer phone number not available.'); return; }
    const status = <?php echo json_encode($payment_status); ?>;
    const statusEmoji = status === 'Paid' ? '✅' : (status === 'Partial' ? '⚠️' : '🔴');
    const msg = `Hello ${custName} 👋\n\nYour invoice from *<?php echo addslashes($company['company_name']); ?>* is ready.\n\n🧾 *Invoice ${invNo}*\n💰 Total: ${invTotal}\n${statusEmoji} Status: ${status}\n\n📄 *View Invoice:*\n${pageUrl}\n\nThank you for your business! 🙏`;
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>