<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
include('layout.php');

$customers = $conn->query("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Auto-generate next Proforma number PI/001
$last = $conn->query("SELECT proforma_no FROM proforma_invoices ORDER BY id DESC LIMIT 1")->fetch_assoc();
$next_number = 1;
if ($last && preg_match('/PI\/(\d+)$/', $last['proforma_no'], $m)) $next_number = intval($m[1]) + 1;
$proforma_no = "PI/" . str_pad($next_number, 3, '0', STR_PAD_LEFT);

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_proforma'])) {
    try {
        $conn->begin_transaction();
        $p_no    = trim($_POST['proforma_no']);
        $p_date  = $_POST['proforma_date'];
        $c_id    = (int)$_POST['customer_id'];
        $c_name  = trim($_POST['customer_name']);
        $c_phone = trim($_POST['customer_phone']);
        $c_addr  = trim($_POST['customer_address']);
        $c_state = trim($_POST['customer_state']);
        $services = json_decode($_POST['services_json'], true);

        $subtotal = 0; $total_gst = 0;
        foreach ($services as $s) {
            $amt = (float)$s['amount'];
            $gst_rate = (float)($s['gst_rate'] ?? 0);
            $subtotal += $amt;
            $total_gst += $amt * ($gst_rate / 100);
        }
        $discount_amount = (float)($_POST['discount_amount'] ?? 0);
        $taxable_amt = $subtotal - $discount_amount;
        $grand_total = $taxable_amt + $total_gst;

        $stmt = $conn->prepare("INSERT INTO proforma_invoices (proforma_no, proforma_date, customer_id, customer_name, customer_phone, customer_address, customer_state, subtotal, discount_amount, gst_amount, total_amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssddddi", $p_no, $p_date, $c_id, $c_name, $c_phone, $c_addr, $c_state, $subtotal, $discount_amount, $total_gst, $grand_total, $_SESSION['user_id']);
        $stmt->execute();
        $p_id = $stmt->insert_id;
        $stmt->close();

        $item_stmt = $conn->prepare("INSERT INTO proforma_invoice_items (proforma_id, service_name, service_description, sac_code, quantity, unit, rate, gst_rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($services as $s) {
            $gst_r = (float)($s['gst_rate'] ?? 0);
            $qty   = (float)($s['quantity'] ?? 1);
            $rate  = (float)($s['rate'] ?? 0);
            $amt   = (float)$s['amount'];
            $item_stmt->bind_param("issssdddd", $p_id, $s['service_name'], $s['service_description'], $s['sac_code'], $qty, $s['unit'], $rate, $gst_r, $amt);
            $item_stmt->execute();
        }
        $item_stmt->close();

        $conn->commit();
        $_SESSION['success_message'] = "Proforma Invoice {$p_no} saved successfully!";
        header("Location: view_proforma.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Proforma Invoice — CREDIBLE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
:root{
  --brand:#0284c7;--brand-dark:#0b1220;--brand-mid:#0ea5e9;--brand-light:#e0f2fe;
  --success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--danger-bg:#fee2e2;
  --bg:#f4f8fb;--card:#fff;--border:#e2e8f0;--text:#0b1220;
  --text-mid:#475569;--text-muted:#94a3b8;
  --radius-sm:8px;--radius-md:14px;--radius-lg:20px;
  --shadow-sm:0 2px 8px rgba(2,132,199,.07);--shadow-md:0 8px 28px rgba(2,132,199,.11);
  --shadow-lg:0 16px 48px rgba(2,132,199,.16);--tr:all .22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}
.page-wrap{margin-top:var(--nav-h,46px);margin-left:var(--sb-w,262px);padding:24px 20px 60px;transition:var(--tr);}
@media(max-width:992px){.page-wrap{margin-left:0;padding:16px 12px 50px;}}
body.sb-is-collapsed .page-wrap{margin-left:var(--sb-w-collapsed,64px);}

.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:20px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap;}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06);}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.35rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;}
.hero-sub{color:rgba(255,255,255,.75);font-size:.8rem;margin-top:2px;}

.form-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);padding:22px;margin-bottom:18px;}
.card-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1rem;font-weight:800;color:var(--brand);display:flex;align-items:center;gap:8px;margin-bottom:18px;padding-bottom:12px;border-bottom:2px solid var(--brand-light);}
.card-title i{opacity:.8;}

.mfield{margin-bottom:14px;}
.mfield label{display:block;font-size:.72rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;}
.mfield input,.mfield select,.mfield textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.88rem;font-family:'Inter',sans-serif;color:var(--text);background:#fafafa;outline:none;transition:var(--tr);}
.mfield input:focus,.mfield select:focus,.mfield textarea:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.1);}
.mfield textarea{resize:vertical;min-height:60px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;}
@media(max-width:640px){.grid-2,.grid-3{grid-template-columns:1fr;}}

.alert-top{border-radius:var(--radius-sm);padding:11px 15px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:500;}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

.service-item{background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;position:relative;transition:var(--tr);}
.service-item:hover{border-color:rgba(2,132,199,.25);box-shadow:var(--shadow-sm);}
.service-item-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.service-num{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;font-size:.72rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;}
.remove-btn{width:30px;height:30px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.75rem;transition:var(--tr);}
.remove-btn:hover{transform:scale(1.1);}

.gst-rate-label{font-size:.7rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.3px;margin-bottom:6px;display:block;}
.gst-pills{display:flex;gap:5px;flex-wrap:wrap;}
.gst-pill{padding:5px 10px;border-radius:50px;border:1.5px solid var(--border);background:#fafafa;color:var(--text-mid);font-size:.72rem;font-weight:700;cursor:pointer;transition:var(--tr);white-space:nowrap;}
.gst-pill:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-light);}
.gst-pill.active{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;border-color:var(--brand);box-shadow:0 3px 10px rgba(2,132,199,.3);}
.gst-pill.nil-pill.active{background:linear-gradient(135deg,#16a34a,#15803d);border-color:#16a34a;}

.price-preview{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:9px 12px;margin-top:10px;font-size:.75rem;display:none;}
.price-preview.show{display:block;}
.pp-row{display:flex;justify-content:space-between;color:var(--text-mid);padding:2px 0;}
.pp-row.total{font-weight:800;color:var(--success);border-top:1px solid #bbf7d0;margin-top:4px;padding-top:6px;}

.add-row-btn{width:100%;background:var(--brand-light);border:1.5px dashed rgba(2,132,199,.3);color:var(--brand);padding:10px 16px;border-radius:10px;font-size:.83rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:var(--tr);margin-bottom:4px;}
.add-row-btn:hover{background:rgba(2,132,199,.1);border-color:var(--brand);}

.summary-box{background:linear-gradient(135deg,var(--brand-light),#eff8ff);border:1.5px solid rgba(2,132,199,.15);border-radius:12px;padding:16px 18px;}
.summary-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.78rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;display:flex;align-items:center;gap:7px;}
.s-row{display:flex;justify-content:space-between;font-size:.84rem;color:var(--text-mid);padding:5px 0;border-bottom:1px dashed rgba(2,132,199,.1);}
.s-row:last-child{border-bottom:none;}
.s-row.sgst-r{color:#1d4ed8;font-weight:600;}
.s-row.cgst-r{color:#9d174d;font-weight:600;}
.s-row.discount-r{color:var(--success);font-weight:600;}
.s-row.grand{font-weight:800;font-size:1.05rem;color:var(--brand);border-top:2px solid rgba(2,132,199,.2);margin-top:6px;padding-top:10px;}

.submit-bar{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.btn-save{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border:none;padding:12px 28px;border-radius:50px;font-size:.9rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:var(--tr);box-shadow:0 5px 18px rgba(2,132,199,.3);}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(2,132,199,.4);}
.btn-cancel{background:#fff;color:var(--text-mid);border:1.5px solid var(--border);padding:11px 22px;border-radius:50px;font-size:.88rem;font-weight:600;cursor:pointer;transition:var(--tr);text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-cancel:hover{border-color:var(--brand);color:var(--brand);}

.select2-container--default .select2-selection--single{height:40px!important;border:1.5px solid var(--border)!important;border-radius:8px!important;background:#fafafa!important;}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px!important;padding-left:12px;font-size:.88rem;}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:38px!important;}
.select2-container--default .select2-results__option--highlighted[aria-selected]{background:var(--brand)!important;}
.select2-dropdown{border:1.5px solid var(--border)!important;border-radius:8px!important;box-shadow:var(--shadow-md)!important;}
</style>
</head>
<body>
<?php ?>
<div class="page-wrap">

  <div class="page-hero">
    <div>
      <div class="hero-title"><i class="fas fa-file-contract"></i> Create Proforma Invoice</div>
      <div class="hero-sub">Build a professional GST-ready proforma invoice for your customer</div>
    </div>
    <a href="view_proforma.php" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.5);padding:8px 16px;border-radius:50px;font-size:.82rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:var(--tr)"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  <?php if ($error_message): ?>
  <div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <form method="POST" id="proformaForm">
    <input type="hidden" name="services_json" id="services_json">

    <!-- PROFORMA DETAILS -->
    <div class="form-card">
      <div class="card-title"><i class="fas fa-hashtag"></i> Proforma Invoice Details</div>
      <div class="grid-2">
        <div class="mfield">
          <label>Proforma Number <span style="color:var(--danger)">*</span></label>
          <input type="text" name="proforma_no" value="<?php echo htmlspecialchars($proforma_no); ?>" required>
        </div>
        <div class="mfield">
          <label>Date <span style="color:var(--danger)">*</span></label>
          <input type="date" name="proforma_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
      </div>
    </div>

    <!-- CUSTOMER -->
    <div class="form-card">
      <div class="card-title"><i class="fas fa-user-circle"></i> Customer Details</div>
      <div class="mfield">
        <label>Select Customer</label>
        <select id="customer_select" name="customer_id" style="width:100%">
          <option value="">Search or select customer…</option>
          <?php foreach($customers as $c): ?>
          <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['name']); ?>" data-phone="<?php echo htmlspecialchars($c['phone']); ?>" data-addr="<?php echo htmlspecialchars($c['address']); ?>" data-state="<?php echo htmlspecialchars($c['state']); ?>">
            <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['phone']); ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid-3">
        <div class="mfield"><label>Name <span style="color:var(--danger)">*</span></label><input type="text" name="customer_name" id="c_name" placeholder="Customer name" required></div>
        <div class="mfield"><label>Phone <span style="color:var(--danger)">*</span></label><input type="text" name="customer_phone" id="c_phone" placeholder="+91 98765 43210" required></div>
        <div class="mfield"><label>State <span style="color:var(--danger)">*</span></label><input type="text" name="customer_state" id="c_state" placeholder="e.g. Uttar Pradesh" required></div>
      </div>
      <div class="mfield"><label>Address</label><textarea name="customer_address" id="c_addr" placeholder="Full billing address…"></textarea></div>
    </div>

    <!-- SERVICE ITEMS -->
    <div class="form-card">
      <div class="card-title"><i class="fas fa-list-check"></i> Items / Services</div>
      <div id="service-list"></div>
      <button type="button" class="add-row-btn" id="addRowBtn"><i class="fas fa-plus-circle"></i> Add Item / Service</button>

      <!-- DISCOUNT -->
      <div style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px">
        <div style="max-width:260px;margin-left:auto">
          <div class="mfield">
            <label><i class="fas fa-tag me-1"></i>Discount Amount (₹)</label>
            <input type="number" step="0.01" min="0" name="discount_amount" id="discount_amount" placeholder="0.00" oninput="updateSummary()">
          </div>
        </div>
      </div>

      <!-- SUMMARY -->
      <div class="summary-box" id="summaryBox">
        <div class="summary-title"><i class="fas fa-calculator"></i> Proforma Summary</div>
        <div class="s-row"><span>Subtotal (excl. GST)</span><span id="s_subtotal">₹ 0.00</span></div>
        <div class="s-row discount-r" id="s_discount_row" style="display:none"><span><i class="fas fa-tag me-1"></i>Discount</span><span id="s_discount">− ₹ 0.00</span></div>
        <div class="s-row"><span>Taxable Amount</span><span id="s_taxable">₹ 0.00</span></div>
        <div class="s-row sgst-r" id="s_sgst_row" style="display:none"><span>SGST</span><span id="s_sgst">₹ 0.00</span></div>
        <div class="s-row cgst-r" id="s_cgst_row" style="display:none"><span>CGST</span><span id="s_cgst">₹ 0.00</span></div>
        <div class="s-row" id="s_total_gst_row" style="display:none;font-weight:700;color:#d97706"><span>Total GST</span><span id="s_total_gst">₹ 0.00</span></div>
        <div class="s-row grand"><span>Grand Total (incl. GST)</span><span id="s_grand">₹ 0.00</span></div>
      </div>
    </div>

    <!-- SUBMIT -->
    <div class="submit-bar">
      <a href="view_proforma.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
      <button type="submit" name="save_proforma" class="btn-save"><i class="fas fa-check-circle"></i> Save Proforma Invoice</button>
    </div>
  </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const GST_RATES = [0, 5, 8, 12, 18, 28];
let itemCount = 0;

function buildGSTPills(idx, selectedRate) {
  let html = '<div class="gst-pills">';
  GST_RATES.forEach(rate => {
    const isActive = (rate == selectedRate) ? 'active' : '';
    const isNil = rate === 0 ? 'nil-pill' : '';
    html += `<span class="gst-pill ${isNil} ${isActive}" data-idx="${idx}" data-rate="${rate}" onclick="selectGSTRate(this, ${idx}, ${rate})">${rate === 0 ? 'NIL' : rate + '%'}</span>`;
  });
  html += '</div>';
  return html;
}

function buildServiceRow(idx) {
  return `
  <div class="service-item" id="sitem_${idx}">
    <div class="service-item-header">
      <span class="service-num">${idx + 1}</span>
      <button type="button" class="remove-btn" onclick="removeItem(${idx})" title="Remove item"><i class="fas fa-trash"></i></button>
    </div>
    <div class="grid-2" style="margin-bottom:10px">
      <div class="mfield" style="margin-bottom:0">
        <label>Item / Service Name <span style="color:var(--danger)">*</span></label>
        <input type="text" class="s_name" data-idx="${idx}" placeholder="e.g. Website Design" required>
      </div>
      <div class="mfield" style="margin-bottom:0">
        <label>SAC / HSN Code</label>
        <input type="text" class="s_sac" data-idx="${idx}" placeholder="e.g. 998314">
      </div>
    </div>
    <div class="mfield" style="margin-bottom:10px">
      <label>Description</label>
      <textarea class="s_desc" data-idx="${idx}" placeholder="Detailed description…" rows="2"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px">
      <div class="mfield" style="margin-bottom:0">
        <label>Quantity</label>
        <input type="number" class="s_qty" data-idx="${idx}" min="0.01" step="0.01" placeholder="1" oninput="calcRow(${idx})">
      </div>
      <div class="mfield" style="margin-bottom:0">
        <label>Unit</label>
        <select class="s_unit" data-idx="${idx}">
          <option value="">— Unit —</option>
          <option value="Nos">Nos</option>
          <option value="Hours">Hours</option>
          <option value="Days">Days</option>
          <option value="Months">Months</option>
          <option value="Lumpsum">Lumpsum</option>
          <option value="Sqft">Sqft</option>
          <option value="Kg">Kg</option>
          <option value="Ltr">Ltr</option>
          <option value="Pcs">Pcs</option>
        </select>
      </div>
      <div class="mfield" style="margin-bottom:0">
        <label>Rate (₹)</label>
        <input type="number" class="s_rate" data-idx="${idx}" min="0" step="0.01" placeholder="0.00" oninput="calcRow(${idx})">
      </div>
      <div class="mfield" style="margin-bottom:0">
        <label>Amount (₹)</label>
        <input type="number" class="s_amt" data-idx="${idx}" min="0" step="0.01" placeholder="0.00" oninput="updateSummary()" readonly style="background:#f0fdf4;font-weight:700;color:var(--success)">
      </div>
    </div>
    <div>
      <span class="gst-rate-label"><i class="fas fa-percent" style="margin-right:4px;color:var(--brand)"></i>GST Rate on this item</span>
      ${buildGSTPills(idx, 18)}
      <input type="hidden" class="s_gst_rate" data-idx="${idx}" value="18">
    </div>
    <div class="price-preview" id="preview_${idx}">
      <div class="pp-row"><span>Taxable Amount</span><span id="pp_taxable_${idx}">—</span></div>
      <div class="pp-row" id="pp_gst_row_${idx}"><span>GST Amount</span><span id="pp_gst_${idx}">—</span></div>
      <div class="pp-row total"><span>Line Total</span><span id="pp_total_${idx}">—</span></div>
    </div>
  </div>`;
}

function addRow() {
  const idx = itemCount++;
  $('#service-list').append(buildServiceRow(idx));
  renumberItems();
}

function removeItem(idx) {
  if ($('.service-item').length <= 1) { alert('At least one item is required.'); return; }
  $(`#sitem_${idx}`).remove();
  renumberItems();
  updateSummary();
}

function renumberItems() {
  $('.service-item').each(function(i) { $(this).find('.service-num').text(i + 1); });
}

function selectGSTRate(el, idx, rate) {
  $(el).closest('.gst-pills').find('.gst-pill').removeClass('active');
  $(el).addClass('active');
  $(`.s_gst_rate[data-idx="${idx}"]`).val(rate);
  calcRow(idx);
}

function calcRow(idx) {
  const qty  = parseFloat($(`.s_qty[data-idx="${idx}"]`).val() || 0);
  const rate = parseFloat($(`.s_rate[data-idx="${idx}"]`).val() || 0);
  const gst  = parseFloat($(`.s_gst_rate[data-idx="${idx}"]`).val() || 0);
  if (qty > 0 && rate > 0) {
    const amt = qty * rate;
    const taxAmt = amt * (gst / 100);
    const total  = amt + taxAmt;
    $(`.s_amt[data-idx="${idx}"]`).val(amt.toFixed(2));
    $(`#pp_taxable_${idx}`).text('₹ ' + amt.toFixed(2));
    if (gst > 0) { $(`#pp_gst_${idx}`).text('₹ ' + taxAmt.toFixed(2) + ' (' + gst + '%)'); $(`#pp_gst_row_${idx}`).show(); }
    else { $(`#pp_gst_${idx}`).text('NIL'); }
    $(`#pp_total_${idx}`).text('₹ ' + total.toFixed(2));
    $(`#preview_${idx}`).addClass('show');
  } else {
    $(`.s_amt[data-idx="${idx}"]`).val('');
    $(`#preview_${idx}`).removeClass('show');
  }
  updateSummary();
}

function updateSummary() {
  let subtotal = 0, sgst_total = 0, cgst_total = 0, total_gst = 0;
  $('.s_amt').each(function() {
    const idx = $(this).data('idx');
    const amt = parseFloat($(this).val() || 0);
    const gst = parseFloat($(`.s_gst_rate[data-idx="${idx}"]`).val() || 0);
    subtotal += amt;
    const tax = amt * (gst / 100);
    sgst_total += tax / 2; cgst_total += tax / 2; total_gst += tax;
  });
  const discount = parseFloat($('#discount_amount').val() || 0);
  const taxable  = subtotal - discount;
  const grand    = taxable + total_gst;
  $('#s_subtotal').text('₹ ' + subtotal.toFixed(2));
  if (discount > 0) { $('#s_discount_row').show(); $('#s_discount').text('− ₹ ' + discount.toFixed(2)); } else { $('#s_discount_row').hide(); }
  $('#s_taxable').text('₹ ' + taxable.toFixed(2));
  if (total_gst > 0) {
    $('#s_total_gst_row').show(); $('#s_total_gst').text('₹ ' + total_gst.toFixed(2));
    $('#s_sgst_row').show(); $('#s_sgst').text('₹ ' + sgst_total.toFixed(2));
    $('#s_cgst_row').show(); $('#s_cgst').text('₹ ' + cgst_total.toFixed(2));
  } else {
    $('#s_total_gst_row, #s_sgst_row, #s_cgst_row').hide();
  }
  $('#s_grand').text('₹ ' + grand.toFixed(2));
}

function collectServices() {
  const services = [];
  $('.service-item').each(function() {
    services.push({
      service_name: $(this).find('.s_name').val(),
      service_description: $(this).find('.s_desc').val(),
      sac_code: $(this).find('.s_sac').val(),
      quantity: $(this).find('.s_qty').val() || 1,
      unit: $(this).find('.s_unit').val(),
      rate: $(this).find('.s_rate').val() || 0,
      gst_rate: $(this).find('.s_gst_rate').val() || 0,
      amount: parseFloat($(this).find('.s_amt').val() || 0),
    });
  });
  return services;
}

$(document).ready(function() {
  $('#customer_select').select2({ placeholder:'Search or select customer…', allowClear:true, width:'100%' });
  $('#customer_select').on('change', function() {
    const opt = $(this).find('option:selected');
    $('#c_name').val(opt.data('name') || '');
    $('#c_phone').val(opt.data('phone') || '');
    $('#c_addr').val(opt.data('addr') || '');
    $('#c_state').val(opt.data('state') || '');
  });

  addRow();
  $('#addRowBtn').on('click', addRow);

  $('#proformaForm').on('submit', function() {
    const services = collectServices();
    if (!services.length || !services[0].service_name) { alert('Please add at least one item.'); return false; }
    $('#services_json').val(JSON.stringify(services));
    return true;
  });
});
</script>
</body>
</html>
