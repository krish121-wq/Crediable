<?php
function numberToWordsIndian($num) {
    $num = (int)round($num);
    if ($num == 0) return 'Zero';
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $w = function($n) use ($ones,$tens,&$w) {
        if ($n < 20) return $ones[$n];
        if ($n < 100) return trim($tens[intdiv($n,10)].' '.$ones[$n%10]);
        return trim($ones[intdiv($n,100)].' Hundred '.$w($n%100));
    };
    $c = intdiv($num,10000000); $num %= 10000000;
    $l = intdiv($num,100000);   $num %= 100000;
    $t = intdiv($num,1000);     $num %= 1000;
    $parts = [];
    if ($c) $parts[] = $w($c).' Crore';
    if ($l) $parts[] = $w($l).' Lakh';
    if ($t) $parts[] = $w($t).' Thousand';
    if ($num) $parts[] = $w($num);
    return trim(implode(' ',$parts));
}

ob_start();
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$invoice_id = (int)($_GET['id'] ?? 0);
if (!$invoice_id) die('<p style="font-family:sans-serif;text-align:center;margin-top:80px;color:#571F5A">Invalid quotation ID.</p>');

try {
    $stmt = $conn->prepare("
        SELECT sq.*, c.gstin AS customer_gst
        FROM service_quotations sq
        LEFT JOIN customers c ON sq.customer_id = c.id
        WHERE sq.id = ?
    ");
    $stmt->bind_param("i", $invoice_id); $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$invoice) die('<p style="font-family:sans-serif;text-align:center;margin-top:80px;color:#571F5A">Quotation not found.</p>');

    $stmt = $conn->prepare("SELECT * FROM service_quotation_items WHERE quotation_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $invoice_id); $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Exception $e) { die("Error: ".$e->getMessage()); }

// Company
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc(); $stmt->close();
} catch (Exception $e) { $company = []; }
$company = array_merge(['company_name'=>'Company Name','company_address'=>'Address','phone'=>'','company_gst'=>'','state'=>'','email'=>'','website'=>'','pan_number'=>'','upi_id'=>'','company_logo'=>''], $company ?? []);

// Determine IGST vs SGST+CGST
$company_state  = strtolower(trim($company['state'] ?? ''));
$customer_state = strtolower(trim($invoice['customer_state'] ?? ''));
$is_igst        = ($company_state !== $customer_state);

// ── COMPUTE PER-ITEM AMOUNTS ──────────────────────────
$subtotal       = 0;
$total_discount = (float)($invoice['discount_amount'] ?? 0);
$gst_by_rate    = []; // [ rate => ['taxable'=>x,'tax'=>x,'sgst'=>x,'cgst'=>x,'igst'=>x] ]
$grand_total    = 0;
$total_gst_amt  = 0;

foreach ($items as &$it) {
    $amt      = (float)$it['amount'];
    $gst_rate = isset($it['gst_rate']) ? (float)$it['gst_rate'] : 18.0; // fallback 18%
    $qty      = (float)$it['quantity'];
    $rate     = (float)$it['rate'];

    // taxable for this item (proportional discount not applied per item — total discount taken below)
    $it['_amt']      = $amt;
    $it['_gst_rate'] = $gst_rate;
    $subtotal       += $amt;
}
unset($it);

// Apply discount proportionally to each item's taxable amount
$taxable_total = $subtotal - $total_discount;

foreach ($items as &$it) {
    $amt      = (float)$it['_amt'];
    $gst_rate = (float)$it['_gst_rate'];
    // Proportional taxable
    $proportion = $subtotal > 0 ? $amt / $subtotal : 0;
    $item_discount  = $total_discount * $proportion;
    $item_taxable   = $amt - $item_discount;
    $item_tax       = $item_taxable * ($gst_rate / 100);
    $item_sgst      = $is_igst ? 0 : $item_tax / 2;
    $item_cgst      = $is_igst ? 0 : $item_tax / 2;
    $item_igst      = $is_igst ? $item_tax : 0;
    $item_total     = $item_taxable + $item_tax;

    $it['_taxable']  = $item_taxable;
    $it['_tax_amt']  = $item_tax;
    $it['_sgst']     = $item_sgst;
    $it['_cgst']     = $item_cgst;
    $it['_igst']     = $item_igst;
    $it['_total']    = $item_total;

    $total_gst_amt += $item_tax;
    $grand_total   += $item_total;

    // Group by GST rate for summary table
    $rk = number_format($gst_rate, 2);
    if (!isset($gst_by_rate[$rk])) $gst_by_rate[$rk] = ['rate'=>$gst_rate,'taxable'=>0,'sgst'=>0,'cgst'=>0,'igst'=>0,'tax'=>0];
    $gst_by_rate[$rk]['taxable'] += $item_taxable;
    $gst_by_rate[$rk]['sgst']   += $item_sgst;
    $gst_by_rate[$rk]['cgst']   += $item_cgst;
    $gst_by_rate[$rk]['igst']   += $item_igst;
    $gst_by_rate[$rk]['tax']    += $item_tax;
}
unset($it);

$total_sgst = $is_igst ? 0 : $total_gst_amt / 2;
$total_cgst = $is_igst ? 0 : $total_gst_amt / 2;
$total_igst = $is_igst ? $total_gst_amt : 0;

$amount_words = numberToWordsIndian($grand_total) . ' Rupees Only';

// Dates
$q_date = !empty($invoice['quotation_date']) ? date('d M Y', strtotime($invoice['quotation_date'])) : date('d M Y');
$valid_until = date('d M Y', strtotime(($invoice['quotation_date'] ?? date('Y-m-d')).' +30 days'));

// UPI QR
$upi_qr_url = '';
if (!empty($company['upi_id'])) {
    $upi_str = "upi://pay?pa=".urlencode($company['upi_id'])."&pn=".urlencode($company['company_name'])."&am=".urlencode(number_format($grand_total,2,'.',''))."&tn=".urlencode($invoice['quotation_no'])."&cu=INR";
    $upi_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=5&data=".urlencode($upi_str);
}

// Logo
if (!empty($company['company_logo']) && !str_starts_with($company['company_logo'],'http')) {
    // Keep local path
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Quotation <?php echo htmlspecialchars($invoice['quotation_no']); ?> — <?php echo htmlspecialchars($company['company_name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   GROW BILLER — PREMIUM QUOTATION · A4
═══════════════════════════════════════════ */
:root{
  --brand:#0284c7;--brand-dark:#0b1220;--brand-mid:#0ea5e9;--brand-light:#e0f2fe;
  --gold:#0284c7;--gold-light:#f0f9ff;--gold-border:#bae6fd;
  --success:#16a34a;--success-bg:#dcfce7;
  --sgst:#1d4ed8;--sgst-bg:#dbeafe;
  --cgst:#9d174d;--cgst-bg:#fce7f3;
  --igst:#92400e;--igst-bg:#fef3c7;
  --ink:#0b1220;--ink-mid:#334155;--ink-muted:#64748b;
  --paper:#fff;--paper-soft:#f8fafc;--line:#e2e8f0;--line-strong:#cbd5e1;
  --r:10px;
}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{font-family:'Inter',sans-serif;color:var(--ink);background:#f1f5f9;-webkit-font-smoothing:antialiased;}


/* ── TOOLBAR ── */
.toolbar{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.tb-brand{display:flex;align-items:center;gap:10px;}
.tb-back{color:var(--ink-mid);text-decoration:none;font-size:.79rem;font-weight:600;display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:50px;border:1.5px solid var(--line-strong);transition:all .2s;}
.tb-back:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-light);}
.tb-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.92rem;color:var(--brand);}
.tb-qtn{font-family:monospace;font-size:.78rem;background:var(--brand-light);color:var(--brand);padding:3px 9px;border-radius:5px;font-weight:700;}
.tb-actions{display:flex;gap:7px;flex-wrap:wrap;}
.tbtn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:50px;font-size:.79rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;border:none;cursor:pointer;text-decoration:none;transition:all .2s;}
.tbtn-print{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;box-shadow:0 4px 14px rgba(87,31,90,.28);}
.tbtn-print:hover{transform:translateY(-2px);color:#fff;box-shadow:0 8px 20px rgba(87,31,90,.36);}
.tbtn-wa{background:linear-gradient(135deg,#25D366,#128C7E);color:#fff;box-shadow:0 4px 14px rgba(37,211,102,.22);}
.tbtn-wa:hover{transform:translateY(-2px);color:#fff;}
.tbtn-outline{background:#fff;color:var(--brand);border:1.5px solid var(--line-strong);}
.tbtn-outline:hover{border-color:var(--brand);background:var(--brand-light);}

/* ── A4 SHEET ── */
.sheet-wrap{padding:22px 10px 60px;display:flex;justify-content:center;}
.sheet{
  width:210mm;max-width:100%;
  background:var(--paper);
  border-radius:14px;
  box-shadow:0 2px 8px rgba(26,18,40,.05),0 20px 60px rgba(26,18,40,.14);
  overflow:hidden;
}

/* TOP BAND */
.top-band{height:5px;background:linear-gradient(90deg,var(--brand) 0%,var(--brand-mid) 50%,#c8832a 100%);}

/* WATERMARK */
.watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-family:'Plus Jakarta Sans',sans-serif;font-size:72px;font-weight:800;color:rgba(87,31,90,.04);pointer-events:none;letter-spacing:8px;text-transform:uppercase;white-space:nowrap;}

/* HEADER */
.inv-header{padding:30px 38px 22px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;border-bottom:2px solid var(--line);position:relative;}
.inv-header::after{content:'';position:absolute;bottom:-1px;left:38px;right:38px;height:2px;background:linear-gradient(to right,var(--brand) 0%,transparent 100%);}

.co-block{display:flex;gap:12px;align-items:flex-start;max-width:56%;}
.co-logo{width:60px;height:60px;border-radius:11px;object-fit:contain;background:var(--paper-soft);border:1px solid var(--line);padding:5px;flex-shrink:0;}
.co-logo-fb{width:60px;height:60px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:1.45rem;flex-shrink:0;}

.co-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.25rem;font-weight:800;color:var(--brand);letter-spacing:-.2px;line-height:1.2;margin-bottom:5px;}
.co-meta{font-size:.73rem;color:var(--ink-mid);line-height:1.75;}
.co-meta .mr{display:flex;gap:6px;align-items:flex-start;margin-top:1px;}
.co-meta .mr i{color:var(--brand);font-size:.63rem;margin-top:3px;width:10px;flex-shrink:0;}

.qtn-block{text-align:right;flex-shrink:0;}
.qtn-word{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.9rem;font-weight:800;letter-spacing:5px;color:var(--brand);line-height:1;}
.qtn-type{font-size:.63rem;color:var(--ink-muted);margin-top:3px;letter-spacing:2px;text-transform:uppercase;font-weight:700;}

.qtn-meta{margin-top:12px;display:inline-flex;flex-direction:column;align-items:flex-end;gap:6px;}
.qtn-num-pill{font-family:monospace;font-size:.88rem;font-weight:800;background:var(--brand-light);color:var(--brand);padding:6px 13px;border-radius:7px;letter-spacing:.5px;border:1px solid rgba(87,31,90,.12);}
.qtn-date{font-size:.72rem;color:var(--ink-mid);}
.valid-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:50px;font-size:.66rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;background:var(--gold-light);color:var(--gold);border:1px solid var(--gold-border);}

/* PARTIES */
.parties-row{padding:20px 38px;display:grid;grid-template-columns:1fr 1fr;gap:22px;background:var(--paper-soft);border-bottom:1px solid var(--line);}
.party-label{font-size:.63rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.party-label::before{content:'';width:15px;height:2px;background:var(--gold);border-radius:2px;flex-shrink:0;}
.party-name{font-family:'Plus Jakarta Sans',sans-serif;font-size:.97rem;font-weight:700;color:var(--ink);margin-bottom:4px;}
.party-detail{font-size:.75rem;color:var(--ink-mid);line-height:1.75;}
.party-detail .dr{display:flex;gap:6px;align-items:flex-start;margin-top:1px;}
.party-detail .dr i{color:var(--ink-muted);font-size:.63rem;margin-top:4px;width:10px;flex-shrink:0;}

/* TABLE */
.items-sec{padding:22px 38px 10px;}
.sec-title{font-size:.65rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.sec-title::after{content:'';flex:1;height:1px;background:linear-gradient(to right,rgba(87,31,90,.2),transparent);}

.items-table{width:100%;border-collapse:collapse;}
.items-table thead th{background:var(--brand);color:#fff;font-size:.61rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:9px 10px;text-align:left;white-space:nowrap;}
.items-table thead th:first-child{border-radius:7px 0 0 7px;}
.items-table thead th:last-child{border-radius:0 7px 7px 0;text-align:right;}
.items-table thead th.nr{text-align:right;}
.items-table tbody td{padding:11px 10px;font-size:.78rem;border-bottom:1px solid var(--line);vertical-align:top;}
.items-table tbody td.nr{text-align:right;white-space:nowrap;}
.items-table tbody tr:last-child td{border-bottom:none;}
.items-table tbody tr:nth-child(even){background:var(--paper-soft);}
.sno{width:28px;color:var(--ink-muted);font-weight:700;font-size:.76rem;}
.item-name{font-weight:700;color:var(--ink);font-size:.8rem;}
.item-desc{font-size:.68rem;color:var(--ink-mid);margin-top:3px;line-height:1.55;}
.item-sac{display:inline-flex;align-items:center;gap:3px;margin-top:4px;padding:2px 7px;border-radius:4px;font-size:.62rem;font-weight:700;background:var(--brand-light);color:var(--brand);}

/* GST RATE TAG IN TABLE */
.gst-tag{display:inline-flex;align-items:center;padding:2px 6px;border-radius:50px;font-size:.6rem;font-weight:800;}
.gst-tag.nil{background:var(--success-bg);color:var(--success);}
.gst-tag.r5{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.gst-tag.r8{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;}
.gst-tag.r12{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.gst-tag.r18{background:var(--brand-light);color:var(--brand);border:1px solid rgba(87,31,90,.15);}
.gst-tag.r28{background:#fdf4ff;color:#7e22ce;border:1px solid #e9d5ff;}

.tax-split{font-size:.65rem;margin-top:3px;line-height:1.6;}
.tax-split .sgst-l{color:var(--sgst);}
.tax-split .cgst-l{color:var(--cgst);}
.tax-split .igst-l{color:var(--igst);}

/* TOTALS + WORDS */
.bottom-sec{padding:8px 38px 22px;display:grid;grid-template-columns:1.15fr 1fr;gap:22px;align-items:start;}
.amount-words{background:linear-gradient(135deg,var(--brand-light),#ede3f5);border-radius:var(--r);padding:14px 16px;border:1px solid rgba(87,31,90,.1);}
.aw-label{font-size:.63rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:7px;display:flex;align-items:center;gap:5px;}
.aw-value{font-size:.82rem;font-weight:700;color:var(--ink);line-height:1.6;text-transform:capitalize;}

.totals-box{border:1.5px solid var(--line);border-radius:var(--r);overflow:hidden;}
.trow{display:flex;justify-content:space-between;align-items:center;padding:8px 14px;font-size:.8rem;color:var(--ink-mid);border-bottom:1px solid var(--line);}
.trow:last-child{border-bottom:none;}
.trow .tv{font-weight:600;color:var(--ink);}
.trow.sub{background:var(--paper-soft);}
.trow.disc{color:var(--success);font-weight:600;}
.trow.disc .tv{color:var(--success);}
.trow.sgst-r{background:var(--sgst-bg);color:var(--sgst);}
.trow.sgst-r .tv{color:var(--sgst);font-weight:700;}
.trow.cgst-r{background:var(--cgst-bg);color:var(--cgst);}
.trow.cgst-r .tv{color:var(--cgst);font-weight:700;}
.trow.igst-r{background:var(--igst-bg);color:var(--igst);}
.trow.igst-r .tv{color:var(--igst);font-weight:700;}
.trow.total-gst{background:#f5f3ff;font-weight:700;color:#6d28d9;}
.trow.total-gst .tv{color:#6d28d9;font-weight:800;}
.trow.grand{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;padding:12px 14px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.92rem;}
.trow.grand .tv{color:#fff;}

/* GST SUMMARY TABLE */
.gst-summary-sec{padding:0 38px 20px;}
.gst-tbl-wrap{border:1px solid var(--line);border-radius:var(--r);overflow:hidden;}
.gst-tbl-label{font-size:.62rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1.1px;padding:8px 12px 6px;background:var(--paper-soft);border-bottom:1px solid var(--line);display:flex;align-items:center;gap:5px;}
.gst-tbl{width:100%;border-collapse:collapse;}
.gst-tbl th{padding:7px 10px;font-size:.61rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--ink-mid);background:var(--paper-soft);border-bottom:1px solid var(--line);text-align:left;}
.gst-tbl th.nr,.gst-tbl td.nr{text-align:right;}
.gst-tbl td{padding:7px 10px;font-size:.76rem;border-bottom:1px solid var(--line);}
.gst-tbl tr:last-child td{border-bottom:none;font-weight:700;background:#f8f4fc;}
.gst-rate-badge{display:inline-flex;padding:2px 7px;border-radius:50px;font-size:.6rem;font-weight:800;}

/* PAYMENT INFO */
.pay-sec{margin:0 38px 20px;border:1px solid var(--line);border-radius:var(--r);overflow:hidden;display:grid;grid-template-columns:auto 1fr;}
.pay-qr{background:var(--gold-light);border-right:1px solid var(--gold-border);padding:16px 18px;display:flex;align-items:center;gap:13px;flex-shrink:0;}
.pay-qr img{width:76px;height:76px;border-radius:7px;border:1px solid var(--gold-border);}
.pq-title{font-size:.61rem;font-weight:800;color:var(--gold);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;}
.pq-upi{font-size:.83rem;font-weight:700;color:var(--ink);font-family:monospace;}
.pq-note{font-size:.67rem;color:var(--ink-mid);margin-top:3px;}
.pay-bank{padding:16px 18px;}
.pb-title{font-size:.62rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;gap:5px;}
.bank-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px 18px;}
.bf .bfl{font-size:.61rem;color:var(--ink-muted);text-transform:uppercase;letter-spacing:.3px;font-weight:600;}
.bf .bfv{font-size:.8rem;font-weight:700;color:var(--ink);margin-top:1px;font-family:monospace;}

/* FOOTER */
.inv-footer{padding:18px 38px 26px;display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;border-top:1px solid var(--line);}
.terms-block{max-width:56%;}
.terms-title{font-size:.66rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:1px;margin-bottom:7px;}
.terms-list{list-style:none;font-size:.72rem;color:var(--ink-mid);line-height:1.85;}
.terms-list li{display:flex;gap:5px;}
.terms-list li::before{content:'·';color:var(--gold);font-weight:800;flex-shrink:0;}
.sig-block{text-align:center;flex-shrink:0;}
.sig-box{width:140px;height:48px;border-bottom:2px solid var(--line-strong);margin-bottom:6px;}
.sig-label{font-size:.68rem;color:var(--ink-muted);font-weight:600;}
.sig-co{font-size:.78rem;font-weight:800;color:var(--brand);margin-top:2px;}
.thanks{text-align:center;padding:11px 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:.79rem;font-weight:700;letter-spacing:.4px;color:var(--brand);background:var(--brand-light);border-top:1px dashed var(--line-strong);}
.thanks i{color:var(--gold);margin:0 5px;}

/* PRINT RULES */
@media print {
  html,body{background:#fff;}
  .toolbar{display:none!important;}
  .sheet-wrap{padding:0;}
  .sheet{box-shadow:none;border-radius:0;width:100%;max-width:100%;}
  @page{size:A4;margin:7mm 6mm;}
  .items-table,.gst-tbl{page-break-inside:auto;}
  .items-table tr,.gst-tbl tr{page-break-inside:avoid;}
}

/* MOBILE */
@media(max-width:680px){
  .inv-header{flex-direction:column;padding:22px 18px 18px;}
  .qtn-block{text-align:left;width:100%;}
  .qtn-meta{align-items:flex-start;}
  .co-block{max-width:100%;}
  .parties-row{grid-template-columns:1fr;padding:16px 18px;}
  .items-sec,.bottom-sec,.gst-summary-sec,.inv-footer{padding-left:16px;padding-right:16px;}
  .bottom-sec{grid-template-columns:1fr;}
  .inv-footer{flex-direction:column;align-items:stretch;}
  .terms-block{max-width:100%;}
  .items-table{display:block;overflow-x:auto;}
  .pay-sec{grid-template-columns:1fr;margin:0 16px 18px;}
  .pay-qr{border-right:none;border-bottom:1px solid var(--gold-border);}
}
</style>
</head>
<body>

<!-- TOOLBAR -->
<div class="toolbar">
  <div class="tb-brand">
    <a href="view_quotations.php" class="tb-back"><i class="fas fa-arrow-left"></i> Quotations</a>
    <span class="tb-title">Quotation</span>
    <span class="tb-qtn"><?php echo htmlspecialchars($invoice['quotation_no']); ?></span>
  </div>
  <div class="tb-actions">
    <button class="tbtn tbtn-wa" onclick="shareWhatsApp()"><i class="fab fa-whatsapp"></i> Share</button>
    <a href="edit_quotation.php?id=<?php echo $invoice_id; ?>" class="tbtn tbtn-outline"><i class="fas fa-pen"></i> Edit</a>
    <button class="tbtn tbtn-print" onclick="window.print()"><i class="fas fa-print"></i> Print / PDF</button>
  </div>
</div>

<!-- SHEET -->
<div class="sheet-wrap">
<div class="sheet" id="quotationSheet">
  <div class="top-band"></div>

  <!-- HEADER -->
  <div class="inv-header" style="position:relative">
    <div class="watermark">QUOTATION</div>
    <div class="co-block">
      <?php if (!empty($company['company_logo'])): ?>
        <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" alt="Logo" class="co-logo"
             onerror="this.outerHTML='<div class=\'co-logo-fb\'><?php echo htmlspecialchars(strtoupper(substr($company['company_name'],0,1))); ?></div>'">
      <?php else: ?>
        <div class="co-logo-fb"><?php echo htmlspecialchars(strtoupper(substr($company['company_name']??'C',0,1))); ?></div>
      <?php endif; ?>
      <div>
        <div class="co-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
        <div class="co-meta">
          <?php if (!empty($company['company_address'])): ?>
          <div class="mr"><i class="fas fa-location-dot"></i><span><?php echo nl2br(htmlspecialchars($company['company_address'])); ?><?php if(!empty($company['state'])): ?>, <?php echo htmlspecialchars($company['state']); ?><?php endif; ?></span></div>
          <?php endif; ?>
          <?php if (!empty($company['phone'])): ?>
          <div class="mr"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($company['phone']); ?></span></div>
          <?php endif; ?>
          <?php if (!empty($company['email'])): ?>
          <div class="mr"><i class="fas fa-envelope"></i><span><?php echo htmlspecialchars($company['email']); ?></span></div>
          <?php endif; ?>
          <?php if (!empty($company['website'])): ?>
          <div class="mr"><i class="fas fa-globe"></i><span><?php echo htmlspecialchars($company['website']); ?></span></div>
          <?php endif; ?>
          <?php if (!empty($company['company_gst'])): ?>
          <div class="mr"><i class="fas fa-receipt"></i><span><strong>GSTIN:</strong> <?php echo htmlspecialchars($company['company_gst']); ?></span></div>
          <?php endif; ?>
          <?php if (!empty($company['pan_number'])): ?>
          <div class="mr"><i class="fas fa-id-card"></i><span><strong>PAN:</strong> <?php echo htmlspecialchars($company['pan_number']); ?></span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="qtn-block">
      <div class="qtn-word">QUOTATION</div>
      <div class="qtn-type">Service Estimate / Proforma</div>
      <div class="qtn-meta">
        <span class="qtn-num-pill"><?php echo htmlspecialchars($invoice['quotation_no']); ?></span>
        <span class="qtn-date"><i class="fas fa-calendar-alt" style="color:var(--gold);margin-right:4px"></i>Date: <strong><?php echo $q_date; ?></strong></span>
        <span class="qtn-date"><i class="fas fa-calendar-check" style="color:var(--ink-muted);margin-right:4px"></i>Valid Till: <strong><?php echo $valid_until; ?></strong></span>
        <span class="valid-pill"><i class="fas fa-clock"></i> Valid 30 Days</span>
      </div>
    </div>
  </div>

  <!-- PARTIES -->
  <div class="parties-row">
    <div>
      <div class="party-label">Estimate For</div>
      <div class="party-name"><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
      <div class="party-detail">
        <?php if (!empty($invoice['customer_address'])): ?>
        <div class="dr"><i class="fas fa-location-dot"></i><span><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($invoice['customer_phone'])): ?>
        <div class="dr"><i class="fas fa-phone"></i><span><?php echo htmlspecialchars($invoice['customer_phone']); ?></span></div>
        <?php endif; ?>
        <?php if (!empty($invoice['customer_state'])): ?>
        <div class="dr"><i class="fas fa-map"></i><span>State: <strong style="color:var(--ink)"><?php echo htmlspecialchars($invoice['customer_state']); ?></strong></span></div>
        <?php endif; ?>
        <?php if (!empty($invoice['customer_gst'])): ?>
        <div class="dr"><i class="fas fa-receipt"></i><span>GSTIN: <strong style="color:var(--ink)"><?php echo htmlspecialchars($invoice['customer_gst']); ?></strong></span></div>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <div class="party-label">Quotation Summary</div>
      <div class="party-detail">
        <div class="dr"><i class="fas fa-hashtag"></i><span>Ref No: <strong style="color:var(--ink)"><?php echo htmlspecialchars($invoice['quotation_no']); ?></strong></span></div>
        <div class="dr"><i class="fas fa-calendar-day"></i><span>Date: <strong style="color:var(--ink)"><?php echo $q_date; ?></strong></span></div>
        <div class="dr"><i class="fas fa-calendar-check"></i><span>Valid Until: <strong style="color:var(--ink)"><?php echo $valid_until; ?></strong></span></div>
        <div class="dr"><i class="fas fa-swap-horizontal" style="color:<?php echo $is_igst ? 'var(--igst)' : 'var(--sgst)'; ?>"></i>
          <span>Tax Type: <strong style="color:var(--ink)"><?php echo $is_igst ? 'Inter-State (IGST)' : 'Intra-State (SGST + CGST)'; ?></strong></span>
        </div>
        <div class="dr"><i class="fas fa-indian-rupee-sign" style="color:var(--brand)"></i><span>Grand Total: <strong style="color:var(--brand);font-size:.9rem">₹<?php echo number_format($grand_total,2); ?></strong></span></div>
      </div>
    </div>
  </div>

  <!-- ITEMS TABLE -->
  <div class="items-sec">
    <div class="sec-title"><i class="fas fa-list-check"></i> Service Items</div>
    <table class="items-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Service Description</th>
          <th class="nr">SAC</th>
          <th class="nr">Qty</th>
          <th class="nr">Rate (₹)</th>
          <th class="nr">Amount (₹)</th>
          <th class="nr">GST</th>
          <th class="nr">Tax Amt (₹)</th>
          <th class="nr">Total (₹)</th>
        </tr>
      </thead>
      <tbody>
      <?php $sno=1; foreach ($items as $it):
        $gst_rate = (float)($it['gst_rate'] ?? (isset($it['_gst_rate']) ? $it['_gst_rate'] : 18));
        $rate_class = $gst_rate == 0 ? 'nil' : 'r'.(int)$gst_rate;
      ?>
        <tr>
          <td class="sno"><?php echo sprintf('%02d',$sno++); ?></td>
          <td>
            <div class="item-name"><?php echo htmlspecialchars($it['service_name']); ?></div>
            <?php if (!empty($it['service_description'])): ?>
            <div class="item-desc"><?php echo nl2br(htmlspecialchars($it['service_description'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($it['sac_code'])): ?>
            <span class="item-sac"><i class="fas fa-barcode" style="font-size:.55rem"></i><?php echo htmlspecialchars($it['sac_code']); ?></span>
            <?php endif; ?>
          </td>
          <td class="nr" style="color:var(--ink-muted);font-size:.72rem;font-family:monospace"><?php echo !empty($it['sac_code']) ? htmlspecialchars($it['sac_code']) : '—'; ?></td>
          <td class="nr"><?php echo number_format((float)$it['quantity'],2); ?></td>
          <td class="nr">₹<?php echo number_format((float)$it['rate'],2); ?></td>
          <td class="nr">₹<?php echo number_format($it['_amt'],2); ?></td>
          <td class="nr">
            <span class="gst-tag <?php echo $rate_class; ?>"><?php echo $gst_rate > 0 ? $gst_rate.'%' : 'NIL'; ?></span>
            <?php if ($gst_rate > 0): ?>
            <div class="tax-split">
              <?php if ($is_igst): ?>
                <span class="igst-l">IGST <?php echo $gst_rate; ?>%</span>
              <?php else: ?>
                <span class="sgst-l">SGST <?php echo ($gst_rate/2); ?>%</span><br>
                <span class="cgst-l">CGST <?php echo ($gst_rate/2); ?>%</span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="nr">
            <strong>₹<?php echo number_format($it['_tax_amt'],2); ?></strong>
            <?php if ($it['_tax_amt'] > 0.004): ?>
            <div class="tax-split">
              <?php if ($is_igst): ?>
                <span class="igst-l">IGST ₹<?php echo number_format($it['_igst'],2); ?></span>
              <?php else: ?>
                <span class="sgst-l">SGST ₹<?php echo number_format($it['_sgst'],2); ?></span><br>
                <span class="cgst-l">CGST ₹<?php echo number_format($it['_cgst'],2); ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
          <td class="nr"><strong style="color:var(--brand);font-size:.85rem">₹<?php echo number_format($it['_total'],2); ?></strong></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- TOTALS + WORDS -->
  <div class="bottom-sec">
    <div class="amount-words">
      <div class="aw-label"><i class="fas fa-coins" style="color:var(--gold)"></i> Amount in Words</div>
      <div class="aw-value"><?php echo htmlspecialchars($amount_words); ?></div>
    </div>
    <div class="totals-box">
      <div class="trow sub"><span>Subtotal (excl. GST)</span><span class="tv">₹<?php echo number_format($subtotal,2); ?></span></div>
      <?php if ($total_discount > 0.004): ?>
      <div class="trow disc"><span>Discount</span><span class="tv">−₹<?php echo number_format($total_discount,2); ?></span></div>
      <?php endif; ?>
      <div class="trow sub"><span>Taxable Amount</span><span class="tv">₹<?php echo number_format($taxable_total,2); ?></span></div>
      <?php if (!$is_igst && $total_sgst > 0.004): ?>
      <div class="trow sgst-r"><span><i class="fas fa-circle" style="font-size:.38rem;margin-right:4px"></i>SGST</span><span class="tv">₹<?php echo number_format($total_sgst,2); ?></span></div>
      <div class="trow cgst-r"><span><i class="fas fa-circle" style="font-size:.38rem;margin-right:4px"></i>CGST</span><span class="tv">₹<?php echo number_format($total_cgst,2); ?></span></div>
      <?php elseif ($is_igst && $total_igst > 0.004): ?>
      <div class="trow igst-r"><span><i class="fas fa-circle" style="font-size:.38rem;margin-right:4px"></i>IGST</span><span class="tv">₹<?php echo number_format($total_igst,2); ?></span></div>
      <?php endif; ?>
      <?php if ($total_gst_amt > 0.004): ?>
      <div class="trow total-gst"><span><i class="fas fa-percent" style="font-size:.62rem;margin-right:4px"></i>Total GST</span><span class="tv">₹<?php echo number_format($total_gst_amt,2); ?></span></div>
      <?php endif; ?>
      <div class="trow grand"><span>Grand Total</span><span class="tv">₹<?php echo number_format($grand_total,2); ?></span></div>
    </div>
  </div>

  <!-- GST SUMMARY BY RATE -->
  <?php if ($total_gst_amt > 0.004 && count($gst_by_rate) > 0): ?>
  <div class="gst-summary-sec">
    <div class="gst-tbl-wrap">
      <div class="gst-tbl-label"><i class="fas fa-percent"></i> GST Summary by Rate</div>
      <table class="gst-tbl">
        <thead>
          <tr>
            <th>GST Rate</th>
            <th class="nr">Taxable Value</th>
            <?php if (!$is_igst): ?>
            <th class="nr">SGST Rate</th><th class="nr">SGST Amt</th>
            <th class="nr">CGST Rate</th><th class="nr">CGST Amt</th>
            <?php else: ?>
            <th class="nr">IGST Rate</th><th class="nr">IGST Amt</th>
            <?php endif; ?>
            <th class="nr">Total Tax</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($gst_by_rate as $rk => $rdata): if ($rdata['rate'] <= 0 && $rdata['tax'] <= 0.004) continue; ?>
        <tr>
          <td>
            <?php $rc = $rdata['rate']==0?'nil':'r'.(int)$rdata['rate']; ?>
            <span class="gst-rate-badge <?php echo $rc; ?> gst-tag"><?php echo $rdata['rate']>0 ? number_format($rdata['rate'],0).'%' : 'NIL'; ?></span>
          </td>
          <td class="nr">₹<?php echo number_format($rdata['taxable'],2); ?></td>
          <?php if (!$is_igst): ?>
          <td class="nr"><?php echo number_format($rdata['rate']/2,1); ?>%</td>
          <td class="nr" style="color:var(--sgst);font-weight:600">₹<?php echo number_format($rdata['sgst'],2); ?></td>
          <td class="nr"><?php echo number_format($rdata['rate']/2,1); ?>%</td>
          <td class="nr" style="color:var(--cgst);font-weight:600">₹<?php echo number_format($rdata['cgst'],2); ?></td>
          <?php else: ?>
          <td class="nr"><?php echo number_format($rdata['rate'],1); ?>%</td>
          <td class="nr" style="color:var(--igst);font-weight:600">₹<?php echo number_format($rdata['igst'],2); ?></td>
          <?php endif; ?>
          <td class="nr" style="font-weight:800;color:#6d28d9">₹<?php echo number_format($rdata['tax'],2); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr>
          <td style="font-weight:800">TOTAL</td>
          <td class="nr" style="font-weight:800">₹<?php echo number_format($taxable_total,2); ?></td>
          <?php if (!$is_igst): ?>
          <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--sgst)">₹<?php echo number_format($total_sgst,2); ?></td>
          <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--cgst)">₹<?php echo number_format($total_cgst,2); ?></td>
          <?php else: ?>
          <td class="nr">—</td><td class="nr" style="font-weight:800;color:var(--igst)">₹<?php echo number_format($total_igst,2); ?></td>
          <?php endif; ?>
          <td class="nr" style="font-weight:800;color:#6d28d9">₹<?php echo number_format($total_gst_amt,2); ?></td>
        </tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- PAYMENT INFO -->
  <?php if (!empty($company['upi_id']) || !empty($company['bank_name'])): ?>
  <div class="pay-sec">
    <?php if (!empty($company['upi_id']) && !empty($upi_qr_url)): ?>
    <div class="pay-qr">
      <img src="<?php echo htmlspecialchars($upi_qr_url); ?>" alt="UPI QR">
      <div>
        <div class="pq-title"><i class="fas fa-qrcode"></i> Scan & Pay</div>
        <div class="pq-upi"><?php echo htmlspecialchars($company['upi_id']); ?></div>
        <div class="pq-note">GPay · PhonePe · Paytm · BHIM</div>
        <div style="margin-top:4px;font-size:.7rem;font-weight:700;color:var(--brand)">Advance: ₹<?php echo number_format($grand_total/2,2); ?> (50%)</div>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($company['bank_name'])): ?>
    <div class="pay-bank">
      <div class="pb-title"><i class="fas fa-university"></i> Bank Transfer</div>
      <div class="bank-grid">
        <div class="bf"><div class="bfl">Bank Name</div><div class="bfv" style="font-family:'Inter',sans-serif"><?php echo htmlspecialchars($company['bank_name']); ?></div></div>
        <?php if (!empty($company['bank_branch'])): ?>
        <div class="bf"><div class="bfl">Branch</div><div class="bfv" style="font-family:'Inter',sans-serif"><?php echo htmlspecialchars($company['bank_branch']); ?></div></div>
        <?php endif; ?>
        <?php if (!empty($company['bank_account'])): ?>
        <div class="bf"><div class="bfl">Account No.</div><div class="bfv"><?php echo htmlspecialchars($company['bank_account']); ?></div></div>
        <?php endif; ?>
        <?php if (!empty($company['bank_ifsc'])): ?>
        <div class="bf"><div class="bfl">IFSC</div><div class="bfv"><?php echo htmlspecialchars($company['bank_ifsc']); ?></div></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="inv-footer">
    <div class="terms-block">
      <div class="terms-title"><i class="fas fa-file-contract" style="margin-right:4px"></i>Terms &amp; Conditions</div>
      <ul class="terms-list">
        <li>This quotation is valid for 30 days from the date of issue.</li>
        <li>50% advance payment required to commence the service.</li>
        <li>Balance to be paid upon completion before delivery.</li>
        <li>Taxes are applicable as per prevailing government norms.</li>
        <li>All disputes are subject to <?php echo htmlspecialchars($company['state'] ?: 'local'); ?> jurisdiction only.</li>
        <li>This is a computer-generated document. No physical signature required.</li>
      </ul>
    </div>
    <div class="sig-block">
      <div class="sig-box"></div>
      <div class="sig-label">Authorized Signatory</div>
      <div class="sig-co"><?php echo htmlspecialchars($company['company_name']); ?></div>
    </div>
  </div>

  <div class="thanks"><i class="fas fa-heart"></i>Thank you for considering our services!<i class="fas fa-heart"></i></div>
</div>
</div>

<script>
const custName  = <?php echo json_encode($invoice['customer_name']); ?>;
const custPhone = <?php echo json_encode($invoice['customer_phone'] ?? ''); ?>;
const qtnNo     = <?php echo json_encode($invoice['quotation_no']); ?>;
const grandTotal = <?php echo json_encode(number_format($grand_total,2)); ?>;
const pageUrl   = window.location.href;

function shareWhatsApp() {
    let phone = (custPhone || '').replace(/\D/g,'');
    if (phone.length === 10) phone = '91' + phone;
    if (!phone) { alert('Customer phone number not available.'); return; }
    const msg = `Dear ${custName} 👋\n\nPlease find your quotation from *<?php echo addslashes(htmlspecialchars_decode($company['company_name'])); ?>*:\n\n📄 *${qtnNo}*\n💰 Total: ₹${grandTotal}\n⏳ Valid for 30 days\n\n🔗 View Quotation:\n${pageUrl}\n\nThank you for your business! 🙏`;
    window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
}

window.onload = function() {
    // Auto print only when ?print=1 is in URL
    if (new URLSearchParams(window.location.search).get('print') === '1') window.print();
};
</script>
</body>
</html>
<?php ob_end_flush(); ?>