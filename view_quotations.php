<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Handle AJAX delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quotation']) && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        $quotation_id = (int)$_POST['delete_quotation'];
        if ($quotation_id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid ID']); exit; }
        $conn->begin_transaction();
        $stmt = $conn->prepare("DELETE FROM service_quotation_items WHERE quotation_id = ?");
        $stmt->bind_param("i", $quotation_id); $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM service_quotations WHERE id = ?");
        $stmt->bind_param("i", $quotation_id); $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['success_message'] = "Quotation deleted successfully.";
            echo json_encode(['success'=>true,'redirect'=>'view_quotations.php']);
        } else {
            $conn->rollback();
            echo json_encode(['success'=>false,'message'=>'Not found']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// Handle CSV Export
if (isset($_POST['export_excel']) && $_POST['export_excel'] == '1') {
    $dateRange = $_POST['date_range'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $currentDate = new DateTime();
    $dateFilter  = "";
    switch ($dateRange) {
        case '7days':    $d=(clone $currentDate)->modify('-7 days');   $dateFilter="AND DATE(sq.quotation_date)>='".$d->format('Y-m-d')."'"; break;
        case '1month':   $d=(clone $currentDate)->modify('-1 month');  $dateFilter="AND DATE(sq.quotation_date)>='".$d->format('Y-m-d')."'"; break;
        case '6months':  $d=(clone $currentDate)->modify('-6 months'); $dateFilter="AND DATE(sq.quotation_date)>='".$d->format('Y-m-d')."'"; break;
        case 'thisyear': $y=$currentDate->format('Y'); $d=new DateTime($y.'-04-01'); if($currentDate<$d)$d=new DateTime(($y-1).'-04-01'); $dateFilter="AND DATE(sq.quotation_date)>='".$d->format('Y-m-d')."'"; break;
        case 'custom':   if(!empty($startDate)&&!empty($endDate)) $dateFilter="AND DATE(sq.quotation_date) BETWEEN '$startDate' AND '$endDate'"; break;
    }
    try {
        $stmt = $conn->prepare("SELECT sq.quotation_no,sq.quotation_date,sq.customer_name,sq.customer_phone,sq.customer_address,sq.subtotal,sq.discount_amount,sq.gst_amount,sq.total_amount,sq.created_at FROM service_quotations sq WHERE 1=1 $dateFilter ORDER BY sq.quotation_date DESC");
        $stmt->execute();
        $quotations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="quotations_'.date('Y-m-d').'.csv"');
        $output = fopen('php://output','w');
        fputcsv($output,["Quotation No","Date","Customer","Phone","Address","Subtotal","Discount","GST","Total","Created At"]);
        foreach ($quotations as $q) {
            fputcsv($output,[$q['quotation_no'],date('d/m/Y',strtotime($q['quotation_date'])),$q['customer_name'],$q['customer_phone'],str_replace(["\n","\r"]," ",$q['customer_address']),number_format($q['subtotal'],2),number_format($q['discount_amount'],2),number_format($q['gst_amount'],2),number_format($q['total_amount'],2),date('d/m/Y H:i',strtotime($q['created_at']))]);
        }
        fclose($output); exit;
    } catch (Exception $e) { die("Export error: ".$e->getMessage()); }
}

include('layout.php');
$page_title      = "Quotations";
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) unset($_SESSION['success_message']);

$res = $conn->query("SELECT * FROM service_quotations ORDER BY created_at DESC");

// Stats
$stats = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(total_amount),0) as revenue, COALESCE(SUM(subtotal),0) as subtotal_sum, COALESCE(SUM(gst_amount),0) as gst_sum FROM service_quotations")->fetch_assoc();

// Set timezone for India
date_default_timezone_set('Asia/Kolkata');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quotations — CREDIBLE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --brand: #0284c7;
  --brand-dark: #0b1220;
  --brand-mid: #0ea5e9;
  --brand-light: #e0f2fe;
  --success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--danger-bg:#fee2e2;
  --warning:#d97706;--warning-bg:#fef3c7;--info:#0284c7;--info-bg:#e0f2fe;
  --teal:#0f766e;--teal-bg:#ccfbf1;
  --bg:#f4f8fb;--card:#ffffff;--border:#e2e8f0;--text:#0b1220;
  --text-mid:#475569;--text-muted:#94a3b8;
  --radius-sm:7px;--radius-md:12px;--radius-lg:18px;
  --shadow-sm:0 2px 8px rgba(2,132,199,.07);--shadow-md:0 8px 24px rgba(2,132,199,.11);
  --shadow-lg:0 16px 48px rgba(2,132,199,.16);--tr:all .22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}

.page-wrap{margin-top:70px;margin-left:260px;padding:24px 20px 60px;transition:var(--tr);}
@media(max-width:992px){.page-wrap{margin-left:0;padding:16px 12px 50px;}}

/* HERO */
.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap;}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06);}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;}
.hero-sub{color:rgba(255,255,255,.75);font-size:.82rem;margin-top:2px;}
.hero-actions{display:flex;gap:8px;flex-wrap:wrap;}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
.stat-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);transition:var(--tr);}
.stat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.stat-icon.purple{background:var(--brand-light);color:var(--brand);}
.stat-icon.blue{background:var(--info-bg);color:var(--info);}
.stat-icon.teal{background:var(--teal-bg);color:var(--teal);}
.stat-icon.green{background:var(--success-bg);color:var(--success);}
.stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.15rem;font-weight:800;line-height:1;}
.stat-label{font-size:.68rem;color:var(--text-muted);margin-top:3px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}

/* ALERTS */
.alert-top{border-radius:var(--radius-sm);padding:11px 15px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:500;animation:slideDown .3s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac;}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

/* TABLE */
.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;}
.search-wrap{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.search-input-wrap{position:relative;flex:1;min-width:200px;}
.search-input-wrap input{width:100%;padding:8px 14px 8px 35px;border:1.5px solid var(--border);border-radius:50px;font-size:.83rem;outline:none;transition:var(--tr);background:#fafafa;}
.search-input-wrap input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.12);background:#fff;}
.search-input-wrap .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.75rem;}
.table-scroll{overflow-x:auto;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:11px 13px;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;text-align:left;border:none;}
.data-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s;}
.data-table tbody tr:hover{background:#f8fafc;}
.data-table td{padding:12px 13px;vertical-align:middle;font-size:.84rem;}

.qtn-num{font-family:monospace;font-size:.8rem;background:var(--brand-light);color:var(--brand);border-radius:6px;padding:4px 9px;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.customer-cell{display:flex;align-items:center;gap:9px;}
.cust-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;font-weight:700;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.cust-name{font-weight:600;font-size:.87rem;}
.cust-phone{font-size:.72rem;color:var(--text-muted);margin-top:1px;}
.amt-cell{font-weight:800;font-size:.92rem;color:var(--brand);font-family:'Plus Jakarta Sans',sans-serif;}
.amt-sub{font-size:.69rem;color:var(--text-muted);margin-top:1px;}

/* ACTION BTNS */
.action-group{display:flex;gap:5px;align-items:center;justify-content:center;}
.act-btn{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.77rem;color:#fff;border:none;cursor:pointer;transition:var(--tr);text-decoration:none;flex-shrink:0;position:relative;}
.act-btn:hover{transform:translateY(-3px) scale(1.1);}
.act-btn.print{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 3px 10px rgba(16,185,129,.3);}
.act-btn.view{background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 3px 10px rgba(124,58,237,.3);}
.act-btn.wa{background:linear-gradient(135deg,#25D366,#128C7E);box-shadow:0 3px 10px rgba(37,211,102,.3);}
.act-btn.edit{background:linear-gradient(135deg,var(--brand),var(--brand-mid));box-shadow:0 3px 10px rgba(2,132,199,.3);}
.act-btn.del{background:linear-gradient(135deg,#ef4444,#b91c1c);box-shadow:0 3px 10px rgba(239,68,68,.3);}
.act-btn::after{content:attr(data-tip);position:absolute;bottom:calc(100%+6px);left:50%;transform:translateX(-50%);background:#0b1220;color:#fff;font-size:.64rem;padding:3px 7px;border-radius:5px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s;z-index:100;}
.act-btn:hover::after{opacity:1;}

/* BUTTONS */
.btn-hero{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff!important;border:2px solid rgba(255,255,255,.75);padding:9px 18px;border-radius:50px;font-size:.83rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--tr);box-shadow:0 5px 16px rgba(2,132,199,.28);text-decoration:none;white-space:nowrap;}
.btn-hero:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(2,132,199,.38);border-color:#fff;}
.btn-export{padding:9px 16px;border-radius:50px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:var(--tr);color:#fff;white-space:nowrap;background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 4px 12px rgba(22,163,74,.25);}
.btn-export:hover{transform:translateY(-2px);}

/* MODAL */
.modal-content{border-radius:var(--radius-md);border:none;box-shadow:var(--shadow-lg);}
.modal-header-grad{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;border-radius:var(--radius-md) var(--radius-md) 0 0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;}
.modal-body{padding:22px;}
.modal-footer{border-top:1px solid var(--border);padding:12px 20px;}
.mfield label{display:block;font-size:.72rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;}
.mfield input,.mfield select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.88rem;font-family:'Inter',sans-serif;color:var(--text);background:#fafafa;outline:none;transition:var(--tr);}
.mfield input:focus,.mfield select:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.12);}
.dur-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.dur-card{border:2px solid var(--border);border-radius:var(--radius-sm);padding:13px;cursor:pointer;transition:var(--tr);text-align:center;}
.dur-card:hover,.dur-card.active{border-color:var(--brand);background:var(--brand-light);}
.dur-card i{font-size:1.3rem;color:var(--brand);margin-bottom:5px;}
.dur-card h5{font-size:.83rem;font-weight:700;margin:0 0 2px;}
.dur-card p{font-size:.68rem;color:var(--text-muted);margin:0;}
.dur-card.all{grid-column:span 2;}

/* EMPTY */
.no-data td{text-align:center;padding:60px 20px;color:var(--text-muted);}
</style>
</head>
<body>
<?php  ?>
<div class="page-wrap">

  <!-- HERO -->
  <div class="page-hero">
    <div>
      <div class="hero-title"><i class="fas fa-file-invoice"></i> Quotations</div>
      <div class="hero-sub">Manage and track all customer estimates</div>
    </div>
    <div class="hero-actions">
      <a href="add_quotation.php" class="btn-hero"><i class="fas fa-plus-circle"></i> Create Quotation</a>
      <button class="btn-export" onclick="showExportModal()"><i class="fas fa-file-excel"></i> Export CSV</button>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if ($success_message): ?>
  <div class="alert-top success" id="successAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon purple"><i class="fas fa-file-invoice"></i></div>
      <div><div class="stat-val" style="color:var(--brand)"><?php echo number_format((int)$stats['total']); ?></div><div class="stat-label">Total Quotations</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-indian-rupee-sign"></i></div>
      <div><div class="stat-val" style="color:var(--info)">₹<?php echo number_format((float)$stats['revenue'],0); ?></div><div class="stat-label">Total Value (incl. GST)</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class="fas fa-percent"></i></div>
      <div><div class="stat-val" style="color:var(--teal)">₹<?php echo number_format((float)$stats['gst_sum'],0); ?></div><div class="stat-label">Total GST</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-calculator"></i></div>
      <div><div class="stat-val" style="color:var(--success)">₹<?php echo number_format((float)$stats['subtotal_sum'],0); ?></div><div class="stat-label">Subtotal (excl. GST)</div></div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="table-card">
    <div class="search-wrap">
      <div class="search-input-wrap">
        <i class="fas fa-search si"></i>
        <input type="text" id="tableSearch" placeholder="Search by customer, quotation number…" oninput="filterTable()">
      </div>
      <span style="font-size:.75rem;color:var(--text-muted)" id="tableCount"></span>
    </div>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Qtn. No.</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Created</th>
            <th style="text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody id="tableBody">
        <?php if ($res->num_rows === 0): ?>
          <tr class="no-data"><td colspan="6">
            <div style="font-size:2.5rem;opacity:.3;margin-bottom:10px"><i class="fas fa-file-invoice"></i></div>
            <div style="font-weight:600;margin-bottom:5px">No quotations yet</div>
            <div style="font-size:.8rem">Click <strong>Create Quotation</strong> to get started.</div>
           </div></td></tr>
        <?php else: ?>
          <?php while($row = $res->fetch_assoc()):
            $initials = strtoupper(substr($row['customer_name'],0,1).(strpos($row['customer_name'],' ')!==false?substr($row['customer_name'],strpos($row['customer_name'],' ')+1,1):''));
            // Convert UTC to IST for created_at
            $created_utc = new DateTime($row['created_at'], new DateTimeZone('UTC'));
            $created_ist = clone $created_utc;
            $created_ist->setTimezone(new DateTimeZone('Asia/Kolkata'));
            $quotation_date = new DateTime($row['quotation_date']);
          ?>
          <tr data-search="<?php echo strtolower($row['customer_name'].' '.$row['quotation_no']); ?>">
            <td><span class="qtn-num"><?php echo htmlspecialchars($row['quotation_no']); ?></span></td>
            <td style="font-size:.82rem;color:var(--text-mid)"><?php echo $quotation_date->format('d M Y'); ?></td>
            <td>
              <div class="customer-cell">
                <div class="cust-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                  <div class="cust-name"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                  <?php if (!empty($row['customer_phone'])): ?>
                  <div class="cust-phone"><i class="fas fa-phone" style="font-size:.6rem;margin-right:2px"></i><?php echo htmlspecialchars($row['customer_phone']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <div class="amt-cell">₹<?php echo number_format((float)$row['total_amount'],2); ?></div>
              <div class="amt-sub">excl. GST ₹<?php echo number_format((float)$row['subtotal'],2); ?> · GST ₹<?php echo number_format((float)$row['gst_amount'],2); ?></div>
            </td>
            <td style="font-size:.79rem;color:var(--text-muted)">
              <?php echo $created_ist->format('d M Y'); ?><br>
              <span style="font-size:.68rem"><?php echo $created_ist->format('h:i A'); ?></span>
             </td>
            <td>
              <div class="action-group">
                <a href="service_qtn_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="act-btn print" data-tip="Print"><i class="fas fa-print"></i></a>
                <a href="service_qtn_view.php?id=<?php echo $row['id']; ?>" class="act-btn view" data-tip="View"><i class="fas fa-eye"></i></a>
                <button type="button" class="act-btn wa" data-tip="WhatsApp" onclick="shareWhatsApp(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['quotation_no']); ?>','<?php echo htmlspecialchars($row['customer_name']); ?>','<?php echo htmlspecialchars($row['customer_phone']); ?>')"><i class="fab fa-whatsapp"></i></button>
                <a href="edit_quotation.php?id=<?php echo $row['id']; ?>" class="act-btn edit" data-tip="Edit"><i class="fas fa-pen"></i></a>
                <button type="button" class="act-btn del" data-tip="Delete" onclick="deleteQuotation(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['quotation_no']); ?>')"><i class="fas fa-trash"></i></button>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header-grad" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-trash me-2"></i>Delete Quotation</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="text-align:center;padding:28px">
        <div style="width:60px;height:60px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.7rem;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div>
        <h5 style="font-weight:700;margin-bottom:7px">Delete <span id="delQtnNo" style="color:var(--brand)"></span>?</h5>
        <p style="color:var(--text-muted);font-size:.85rem">This action cannot be undone.</p>
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button id="confirmDelBtn" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer"><i class="fas fa-trash me-1"></i>Delete</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- EXPORT MODAL -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header-grad">
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-file-excel me-2"></i>Export Quotations</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:14px">Select a date range to export:</p>
        <div class="dur-grid">
          <div class="dur-card" onclick="selDur(this,'7days')"><i class="fas fa-calendar-week"></i><h5>Last 7 Days</h5><p>Past week</p></div>
          <div class="dur-card" onclick="selDur(this,'1month')"><i class="fas fa-calendar-alt"></i><h5>Last Month</h5><p>Past 30 days</p></div>
          <div class="dur-card" onclick="selDur(this,'6months')"><i class="fas fa-calendar"></i><h5>Last 6 Months</h5><p></p></div>
          <div class="dur-card" onclick="selDur(this,'thisyear')"><i class="fas fa-calendar-check"></i><h5>This Fin. Year</h5><p>From 1st April</p></div>
          <div class="dur-card all" onclick="selDur(this,'custom')"><i class="fas fa-sliders-h"></i><h5>Custom Range</h5><p>Pick your dates</p></div>
        </div>
        <div id="customFields" style="display:none;border-top:1px solid var(--border);padding-top:14px;margin-top:4px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="mfield"><label>From</label><input type="date" id="expFrom"></div>
            <div class="mfield"><label>To</label><input type="date" id="expTo"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button id="doExportBtn" onclick="doExport()" disabled style="background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;border:none;padding:9px 18px;border-radius:8px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px"><i class="fas fa-download"></i> Export</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let selDurVal = '', delId = null;

function filterTable() {
  const q = (document.getElementById('tableSearch').value||'').toLowerCase();
  let visible = 0;
  document.querySelectorAll('#tableBody tr[data-search]').forEach(tr => {
    const show = !q || tr.dataset.search.includes(q);
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  const el = document.getElementById('tableCount');
  if (el) el.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
}

// Init count
window.addEventListener('DOMContentLoaded', function(){
  const rows = document.querySelectorAll('#tableBody tr[data-search]');
  const el = document.getElementById('tableCount');
  if (el) el.textContent = rows.length + ' record' + (rows.length !== 1 ? 's' : '');

  const sAlert = document.getElementById('successAlert');
  if (sAlert) setTimeout(() => { sAlert.style.transition='opacity .5s'; sAlert.style.opacity='0'; setTimeout(()=>sAlert.remove(),500); }, 4000);
});

function shareWhatsApp(id, quotationNo, customerName, phone) {
  const baseUrl = window.location.origin + window.location.pathname.replace('view_quotations.php','');
  const link = baseUrl + 'service_qtn_view.php?id=' + id;
  let msg = `Dear ${customerName},\n\nPlease find your quotation *${quotationNo}* at the link below:\n\n📄 View Quotation: ${link}\n\nThank you for your business!\nContact us for any queries.`;
  
  // If phone number is available, open WhatsApp directly
  if (phone && phone.trim() !== '') {
    let cleanPhone = phone.replace(/\D/g, '');
    if (cleanPhone.length === 10) cleanPhone = '91' + cleanPhone;
    if (cleanPhone.length >= 10) {
      window.open('https://wa.me/' + cleanPhone + '?text=' + encodeURIComponent(msg), '_blank');
      return;
    }
  }
  // Fallback - just open WhatsApp with message
  window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
}

function deleteQuotation(id, qtnNo) {
  delId = id;
  document.getElementById('delQtnNo').textContent = qtnNo;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.getElementById('confirmDelBtn').addEventListener('click', function() {
  if (!delId) return;
  this.disabled = true;
  this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting…';
  const xhr = new XMLHttpRequest();
  xhr.open('POST','view_quotations.php',true);
  xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
      try {
        const r = JSON.parse(xhr.responseText);
        if (r.success) window.location.href = r.redirect;
        else { alert('Error: ' + r.message); document.getElementById('confirmDelBtn').disabled=false; document.getElementById('confirmDelBtn').innerHTML='<i class="fas fa-trash me-1"></i>Delete'; }
      } catch(e) { alert('Error. Please try again.'); }
    }
  };
  xhr.send('delete_quotation=' + delId + '&ajax=1');
});

function showExportModal() {
  selDurVal = '';
  document.querySelectorAll('.dur-card').forEach(c=>c.classList.remove('active'));
  document.getElementById('doExportBtn').disabled = true;
  document.getElementById('customFields').style.display = 'none';
  new bootstrap.Modal(document.getElementById('exportModal')).show();
}

function selDur(el, val) {
  document.querySelectorAll('.dur-card').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  selDurVal = val;
  document.getElementById('doExportBtn').disabled = false;
  document.getElementById('customFields').style.display = (val === 'custom') ? 'block' : 'none';
}

function doExport() {
  if (!selDurVal) return;
  if (selDurVal === 'custom') {
    const f = document.getElementById('expFrom').value;
    const t = document.getElementById('expTo').value;
    if (!f || !t) { alert('Please select both dates.'); return; }
    if (new Date(f) > new Date(t)) { alert('Start date cannot be after end date.'); return; }
  }
  const form = document.createElement('form');
  form.method = 'POST'; form.action = 'view_quotations.php';
  const fields = [
    ['export_excel','1'],
    ['date_range',selDurVal],
    ['start_date',document.getElementById('expFrom').value||''],
    ['end_date',document.getElementById('expTo').value||'']
  ];
  fields.forEach(([name,value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });
  bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
  document.body.appendChild(form);
  form.submit();
}
</script>
</body>
</html>