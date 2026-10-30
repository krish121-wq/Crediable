<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$page_title      = "E-Payment Support";
$success_message = '';
$error_message   = '';
$user_id         = (int)$_SESSION['user_id'];
$total_pages     = 1;
$bank_accounts   = [];

try {
    $r = $conn->query("SELECT id, bank_name, account_number FROM bank_accounts WHERE created_by = $user_id AND status = 'active'");
    $bank_accounts = $r->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error_message = "Error fetching bank accounts: " . $e->getMessage();
}

/* ── HANDLE POST ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        /* ADD */
        if (isset($_POST['add_payment'])) {
            $bank_account_id     = (int)($_POST['bank_account_id'] ?? 0);
            $transaction_type    = trim($_POST['transaction_type'] ?? '');
            $payee_name          = $conn->real_escape_string(trim($_POST['payee_name'] ?? ''));
            $amount              = floatval($_POST['amount'] ?? 0);
            $payment_date        = trim($_POST['payment_date'] ?? '');
            $payment_type        = trim($_POST['payment_type'] ?? '');
            $e_payment_reference = $conn->real_escape_string(trim($_POST['e_payment_reference'] ?? ''));
            $description         = $conn->real_escape_string(trim($_POST['description'] ?? ''));

            if (!$bank_account_id || empty($transaction_type) || empty($payee_name) || $amount <= 0 || empty($payment_date) || empty($payment_type) || empty($e_payment_reference))
                throw new Exception("All required fields must be filled, and amount must be greater than zero.");
            if (!$conn->query("SELECT id FROM bank_accounts WHERE id=$bank_account_id AND created_by=$user_id AND status='active'")->num_rows)
                throw new Exception("Invalid bank account selected.");
            if (!in_array($transaction_type, ['credit','debit'])) throw new Exception("Invalid transaction type.");
            if (!in_array($payment_type, ['NEFT','RTGS','UPI']))  throw new Exception("Invalid payment type.");
            $fd = (new DateTime($payment_date))->format('Y-m-d H:i:s');
            if ($conn->query("SELECT id FROM bank_transactions WHERE e_payment_reference='$e_payment_reference' AND created_by=$user_id")->num_rows)
                throw new Exception("E-payment reference already exists.");
            if (!$conn->query("INSERT INTO bank_transactions (bank_account_id,transaction_type,payee_name,amount,transaction_date,description,reference_type,e_payment_reference,payment_type,created_by,created_at,status) VALUES ($bank_account_id,'$transaction_type','$payee_name',$amount,'$fd','$description','e_payment','$e_payment_reference','$payment_type',$user_id,NOW(),'completed')"))
                throw new Exception("Insert failed: " . $conn->error);
            $success_message = "E-payment added successfully.";

        /* EDIT */
        } elseif (isset($_POST['edit_payment'])) {
            $id                  = (int)($_POST['id'] ?? 0);
            $bank_account_id     = (int)($_POST['bank_account_id'] ?? 0);
            $transaction_type    = trim($_POST['transaction_type'] ?? '');
            $payee_name          = $conn->real_escape_string(trim($_POST['payee_name'] ?? ''));
            $amount              = floatval($_POST['amount'] ?? 0);
            $payment_date        = trim($_POST['payment_date'] ?? '');
            $payment_type        = trim($_POST['payment_type'] ?? '');
            $e_payment_reference = $conn->real_escape_string(trim($_POST['e_payment_reference'] ?? ''));
            $description         = $conn->real_escape_string(trim($_POST['description'] ?? ''));

            if (!$id || !$bank_account_id || empty($transaction_type) || empty($payee_name) || $amount <= 0 || empty($payment_date) || empty($payment_type) || empty($e_payment_reference))
                throw new Exception("All required fields must be filled.");
            if (!$conn->query("SELECT id FROM bank_accounts WHERE id=$bank_account_id AND created_by=$user_id AND status='active'")->num_rows)
                throw new Exception("Invalid bank account.");
            if (!in_array($transaction_type, ['credit','debit'])) throw new Exception("Invalid transaction type.");
            if (!in_array($payment_type, ['NEFT','RTGS','UPI']))  throw new Exception("Invalid payment type.");
            $fd = (new DateTime($payment_date))->format('Y-m-d H:i:s');
            if ($conn->query("SELECT id FROM bank_transactions WHERE e_payment_reference='$e_payment_reference' AND created_by=$user_id AND id!=$id")->num_rows)
                throw new Exception("E-payment reference already exists.");
            if (!$conn->query("UPDATE bank_transactions SET bank_account_id=$bank_account_id,transaction_type='$transaction_type',payee_name='$payee_name',amount=$amount,transaction_date='$fd',description='$description',e_payment_reference='$e_payment_reference',payment_type='$payment_type',updated_by=$user_id,updated_at=NOW() WHERE id=$id AND created_by=$user_id AND reference_type='e_payment'"))
                throw new Exception("Update failed: " . $conn->error);
            $success_message = "E-payment updated successfully.";

        /* DELETE */
        } elseif (isset($_POST['delete_payment'])) {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Missing ID for delete.");
            if (!$conn->query("DELETE FROM bank_transactions WHERE id=$id AND created_by=$user_id"))
                throw new Exception("Delete failed: " . $conn->error);
            $success_message = "E-payment deleted successfully.";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("e_payment_support.php: " . $e->getMessage());
    }
}

/* ── FETCH RECORDS ───────────────────────────────── */
$records_per_page = 10;
$page       = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;
$result     = null;

try {
    $total_records = $conn->query("SELECT COUNT(*) as total FROM bank_transactions bt LEFT JOIN bank_accounts ba ON bt.bank_account_id=ba.id WHERE bt.created_by=$user_id")->fetch_assoc()['total'];
    $total_pages   = max(1, ceil($total_records / $records_per_page));
    $result = $conn->query("SELECT bt.id,bt.bank_account_id,bt.transaction_type,bt.payee_name,bt.amount,bt.transaction_date,bt.description,bt.e_payment_reference,bt.payment_type,bt.created_at,ba.bank_name,ba.account_number,bt.reference_type FROM bank_transactions bt LEFT JOIN bank_accounts ba ON bt.bank_account_id=ba.id WHERE bt.created_by=$user_id ORDER BY bt.transaction_date DESC LIMIT $start_from,$records_per_page");
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    error_log("e_payment fetch: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Payment Support — Grow Biller</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --brand:#0284c7;--brand-dark:#0b1220;--brand-mid:#0ea5e9;--brand-light:#e0f2fe;
  --success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--danger-bg:#fee2e2;
  --warning:#d97706;--warning-bg:#fef3c7;--info:#0284c7;--info-bg:#e0f2fe;
  --bg:#f4f8fb;--card:#ffffff;--border:#e2e8f0;--text:#0b1220;--text-mid:#475569;--text-muted:#94a3b8;
  --radius-sm:8px;--radius-md:14px;--radius-lg:20px;
  --shadow-sm:0 2px 8px rgba(2,132,199,.07);--shadow-md:0 8px 28px rgba(2,132,199,.11);--shadow-lg:0 16px 48px rgba(2,132,199,.16);
  --transition:all .25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px}
.page-wrap{margin-top:70px;margin-left:260px;padding:28px 24px 60px;transition:var(--transition)}
@media(max-width:992px){.page-wrap{margin-left:0;padding:18px 14px 50px}}

/* HERO */
.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:#fff}
.hero-sub{color:rgba(255,255,255,.75);font-size:.85rem;margin-top:3px}
.hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:relative;z-index:1}
.btn-hero{color:#fff;border:2px solid rgba(255,255,255,.45);padding:9px 20px;border-radius:50px;font-size:.82rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);background:rgba(255,255,255,.14);text-decoration:none;white-space:nowrap}
.btn-hero:hover,.btn-hero:focus{background:rgba(255,255,255,.26);border-color:#fff;color:#fff;transform:translateY(-1px)}
.btn-hero.solid{background:rgba(255,255,255,.9);color:var(--brand);border-color:#fff}
.btn-hero.solid:hover{background:#fff;color:var(--brand)}
.btn-hero.pdf{background:rgba(220,38,38,.75);border-color:rgba(255,255,255,.3)}
.btn-hero.pdf:hover{background:rgba(220,38,38,.95)}
.btn-hero.excel{background:rgba(22,163,74,.75);border-color:rgba(255,255,255,.3)}
.btn-hero.excel:hover{background:rgba(22,163,74,.95)}

/* ALERTS */
.alert-top{border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:500;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5}

/* TABLE CARD */
.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden}
.table-topbar{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:linear-gradient(90deg,var(--brand-light),#fff)}
.search-wrap{position:relative;min-width:220px}
.search-wrap input{width:100%;padding:8px 12px 8px 36px;border:1.5px solid var(--border);border-radius:50px;font-size:.85rem;background:#fafafa;transition:var(--transition);outline:none}
.search-wrap input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.1);background:#fff}
.search-wrap .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.78rem}
.table-scroll{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;min-width:900px}
.data-table thead th{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:12px 12px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.45px;white-space:nowrap;border:none;text-align:left}
.data-table tbody tr{border-bottom:1px solid #f0f4f8;transition:background .18s}
.data-table tbody tr:last-child{border-bottom:none}
.data-table tbody tr:hover{background:#f8fafc}
.data-table td{padding:12px 12px;vertical-align:middle;font-size:.85rem;color:var(--text)}
.muted{color:var(--text-muted);font-size:.8rem}
.amount-cell{font-weight:700;color:var(--brand);font-family:'Plus Jakarta Sans',sans-serif}
.type-badge{padding:3px 10px;border-radius:50px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.type-badge.credit{background:var(--success-bg);color:var(--success)}
.type-badge.debit{background:var(--danger-bg);color:var(--danger)}
.pay-badge{background:var(--info-bg);color:var(--info);padding:3px 9px;border-radius:50px;font-size:.7rem;font-weight:700}
.ref-tag{font-family:monospace;font-size:.78rem;background:var(--brand-light);color:var(--brand);border-radius:5px;padding:2px 7px;font-weight:700}

/* ACTION BUTTONS */
.act-group{display:flex;gap:6px;align-items:center}
.act-btn{width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;color:#fff;border:none;cursor:pointer;transition:var(--transition);text-decoration:none}
.act-btn:hover{transform:translateY(-2px) scale(1.1)}
.act-btn.edit{background:linear-gradient(135deg,var(--brand),var(--brand-mid));box-shadow:0 3px 8px rgba(2,132,199,.25)}
.act-btn.del{background:linear-gradient(135deg,var(--danger),#b91c1c);box-shadow:0 3px 8px rgba(220,38,38,.25)}

/* NO DATA */
.no-data{text-align:center;padding:60px 20px;color:var(--text-muted)}
.no-data-icon{font-size:2.5rem;opacity:.3;margin-bottom:12px}

/* PAGINATION */
.pagination-wrap{display:flex;align-items:center;justify-content:center;gap:6px;padding:14px;flex-wrap:wrap;border-top:1px solid var(--border)}
.pg-btn{padding:7px 13px;border-radius:8px;border:1.5px solid var(--border);background:#fafafa;color:var(--text-mid);font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:var(--transition)}
.pg-btn:hover,.pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand)}

/* MODAL */
.modal-content{border-radius:var(--radius-md);border:none;box-shadow:var(--shadow-lg);overflow:hidden}
.modal-hdr{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:18px 22px;display:flex;align-items:center;justify-content:space-between}
.modal-hdr h5{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;margin:0;font-size:1rem}
.modal-body{padding:22px;max-height:76vh;overflow-y:auto}
.modal-footer{border-top:1px solid var(--border);padding:14px 22px;gap:10px}

/* FORM FIELDS IN MODAL */
.f-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-mid);margin-bottom:5px;display:block}
.f-control,.f-select{border-radius:var(--radius-sm);border:1.5px solid var(--border);padding:9px 12px;font-size:.88rem;background:#fafafa;transition:var(--transition);width:100%;color:var(--text);font-family:'Inter',sans-serif}
.f-control:focus,.f-select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.1);outline:none;background:#fff}
textarea.f-control{height:70px;resize:vertical}
.f-group{margin-bottom:14px}

/* MODAL GRID */
.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:576px){.modal-grid{grid-template-columns:1fr}}
.modal-grid .span2{grid-column:span 2}
@media(max-width:576px){.modal-grid .span2{grid-column:span 1}}

/* BUTTONS */
.btn-brand{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border:none;padding:10px 22px;border-radius:50px;font-size:.85rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);box-shadow:0 4px 14px rgba(2,132,199,.28)}
.btn-brand:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(2,132,199,.38)}
.btn-brand.del-btn{background:linear-gradient(135deg,var(--danger),#b91c1c);box-shadow:0 4px 14px rgba(220,38,38,.28)}
.btn-brand.del-btn:hover{box-shadow:0 8px 22px rgba(220,38,38,.4)}
.btn-cancel{background:#f1f5f9;color:var(--text-mid);border:1.5px solid var(--border);padding:10px 20px;border-radius:50px;font-size:.85rem;font-weight:600;cursor:pointer;transition:var(--transition)}
.btn-cancel:hover{border-color:var(--brand);color:var(--brand)}

@media(max-width:768px){.page-wrap{padding:14px 10px 50px}}
</style>
</head>
<body>

<div class="page-wrap">

  <!-- HERO -->
  <div class="page-hero">
    <div>
      <div class="hero-title"><i class="fas fa-mobile-screen-button me-2" style="opacity:.85"></i>E-Payment Support</div>
      <div class="hero-sub">Manage NEFT, RTGS and UPI electronic payment transactions</div>
    </div>
    <div class="hero-actions">
      <button class="btn-hero solid" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus-circle"></i> Add Payment</button>
      <a href="#" class="btn-hero pdf"   onclick="exportToPDF();return false"><i class="fas fa-file-pdf"></i> PDF</a>
      <a href="#" class="btn-hero excel" onclick="exportToExcel();return false"><i class="fas fa-file-excel"></i> Excel</a>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if ($success_message): ?><div class="alert-top success" id="sAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
  <?php if ($error_message):   ?><div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <!-- TABLE CARD -->
  <div class="table-card">
    <div class="table-topbar">
      <div class="search-wrap">
        <i class="fas fa-search si"></i>
        <input type="text" id="searchInput" placeholder="Search payments…" oninput="searchTable()">
      </div>
      <span style="font-size:.78rem;color:var(--text-muted)">
        <?php echo ($result ? $result->num_rows : 0); ?> record<?php echo (!$result||$result->num_rows!==1)?'s':''; ?>
      </span>
    </div>

    <div class="table-scroll">
      <table class="data-table" id="paymentTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Bank</th>
            <th>Account No.</th>
            <th>Type</th>
            <th>Payee / Receiver</th>
            <th>Amount (₹)</th>
            <th>Payment Date</th>
            <th>Pay Type</th>
            <th>Reference (UTR)</th>
            <th>Description</th>
            <th>Created On</th>
            <th style="text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $tz  = new DateTimeZone('Asia/Kolkata');
        $sno = $start_from + 1;
        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
                $cat = (new DateTime($row['created_at'], new DateTimeZone('UTC')))->setTimezone($tz)->format('d M Y, h:i A');
                $pdt = date('d M Y', strtotime($row['transaction_date']));
                $pt  = !empty($row['payment_type']) ? htmlspecialchars($row['payment_type']) : 'N/A';
                $esc = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES);
        ?>
        <tr>
          <td class="muted"><?php echo $sno++; ?></td>
          <td style="font-weight:500"><?php echo $esc($row['bank_name'] ?? 'N/A'); ?></td>
          <td style="font-family:monospace;font-size:.8rem"><?php echo $esc($row['account_number'] ?? 'N/A'); ?></td>
          <td><span class="type-badge <?php echo $row['transaction_type']; ?>"><?php echo ucfirst($row['transaction_type']); ?></span></td>
          <td style="font-weight:500"><?php echo $esc($row['payee_name'] ?? 'N/A'); ?></td>
          <td class="amount-cell">₹<?php echo number_format($row['amount'],2); ?></td>
          <td class="muted"><?php echo $pdt; ?></td>
          <td><span class="pay-badge"><?php echo $pt; ?></span></td>
          <td><span class="ref-tag"><?php echo $esc($row['e_payment_reference'] ?? '—'); ?></span></td>
          <td style="max-width:160px;color:var(--text-mid);font-size:.8rem"><?php echo $esc($row['description'] ?? '—'); ?></td>
          <td class="muted" style="font-size:.78rem;white-space:nowrap"><?php echo $cat; ?></td>
          <td>
            <div class="act-group" style="justify-content:center">
              <button class="act-btn edit" title="Edit"
                data-bs-toggle="modal" data-bs-target="#editModal"
                onclick="populateEdit(
                  <?php echo $row['id']; ?>,
                  <?php echo $row['bank_account_id']; ?>,
                  '<?php echo addslashes($row['transaction_type']); ?>',
                  '<?php echo addslashes($row['payee_name'] ?? ''); ?>',
                  '<?php echo $row['amount']; ?>',
                  '<?php echo date('Y-m-d', strtotime($row['transaction_date'])); ?>',
                  '<?php echo addslashes($pt); ?>',
                  '<?php echo addslashes($row['e_payment_reference'] ?? ''); ?>',
                  '<?php echo addslashes($row['description'] ?? ''); ?>'
                )"><i class="fas fa-pen"></i></button>
              <button class="act-btn del" title="Delete"
                data-bs-toggle="modal" data-bs-target="#deleteModal"
                onclick="document.getElementById('deleteId').value=<?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr>
          <td colspan="12">
            <div class="no-data"><div class="no-data-icon"><i class="fas fa-mobile-screen-button"></i></div><div style="font-weight:600;margin-bottom:6px">No e-payments found</div><div style="font-size:.82rem">Click <strong>Add Payment</strong> to record your first e-payment.</div></div>
          </td>
        </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
      <?php if($page>1): ?><a href="?page=<?php echo $page-1?>" class="pg-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
      <?php for($i=1;$i<=$total_pages;$i++): ?>
        <a href="?page=<?php echo $i ?>" class="pg-btn <?php echo $page==$i?'active':'' ?>"><?php echo $i ?></a>
      <?php endfor; ?>
      <?php if($page<$total_pages): ?><a href="?page=<?php echo $page+1?>" class="pg-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div><!-- end page-wrap -->

<!-- ══ ADD MODAL ════════════════════════════════ -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-hdr">
        <h5><i class="fas fa-plus-circle me-2"></i>Add E-Payment</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="addForm">
        <div class="modal-body">
          <div class="modal-grid">
            <div class="f-group">
              <label class="f-label">Bank Account <span style="color:var(--danger)">*</span></label>
              <select class="f-select" name="bank_account_id" required>
                <option value="">Select Account</option>
                <?php foreach($bank_accounts as $acc): ?>
                <option value="<?php echo $acc['id'] ?>"><?php echo htmlspecialchars($acc['bank_name'].' — '.$acc['account_number']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="f-group">
              <label class="f-label">Transaction Type <span style="color:var(--danger)">*</span></label>
              <select class="f-select" name="transaction_type" required>
                <option value="credit">Credit</option>
                <option value="debit">Debit</option>
              </select>
            </div>
            <div class="f-group">
              <label class="f-label">Payee / Receiver Name <span style="color:var(--danger)">*</span></label>
              <input type="text" class="f-control" name="payee_name" required placeholder="Enter payee name">
            </div>
            <div class="f-group">
              <label class="f-label">Amount (₹) <span style="color:var(--danger)">*</span></label>
              <input type="number" step="0.01" min="0.01" class="f-control" name="amount" required placeholder="0.00">
            </div>
            <div class="f-group">
              <label class="f-label">Payment Date <span style="color:var(--danger)">*</span></label>
              <input type="date" class="f-control" name="payment_date" required>
            </div>
            <div class="f-group">
              <label class="f-label">Payment Type <span style="color:var(--danger)">*</span></label>
              <select class="f-select" name="payment_type" required>
                <option value="NEFT">NEFT</option>
                <option value="RTGS">RTGS</option>
                <option value="UPI">UPI</option>
              </select>
            </div>
            <div class="f-group span2">
              <label class="f-label">Reference Number (UTR) <span style="color:var(--danger)">*</span></label>
              <input type="text" class="f-control" name="e_payment_reference" required placeholder="Enter UTR / reference number">
            </div>
            <div class="f-group span2">
              <label class="f-label">Description</label>
              <textarea class="f-control" name="description" placeholder="Optional description…"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_payment" class="btn-brand"><i class="fas fa-save"></i> Add E-Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ EDIT MODAL ════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-hdr">
        <h5><i class="fas fa-pen me-2"></i>Edit E-Payment</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" id="editForm">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
          <div class="modal-grid">
            <div class="f-group">
              <label class="f-label">Bank Account <span style="color:var(--danger)">*</span></label>
              <select class="f-select" id="edit_bank_account_id" name="bank_account_id" required>
                <option value="">Select Account</option>
                <?php foreach($bank_accounts as $acc): ?>
                <option value="<?php echo $acc['id'] ?>"><?php echo htmlspecialchars($acc['bank_name'].' — '.$acc['account_number']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="f-group">
              <label class="f-label">Transaction Type <span style="color:var(--danger)">*</span></label>
              <select class="f-select" id="edit_transaction_type" name="transaction_type" required>
                <option value="credit">Credit</option>
                <option value="debit">Debit</option>
              </select>
            </div>
            <div class="f-group">
              <label class="f-label">Payee / Receiver Name <span style="color:var(--danger)">*</span></label>
              <input type="text" class="f-control" id="edit_payee_name" name="payee_name" required>
            </div>
            <div class="f-group">
              <label class="f-label">Amount (₹) <span style="color:var(--danger)">*</span></label>
              <input type="number" step="0.01" min="0.01" class="f-control" id="edit_amount" name="amount" required>
            </div>
            <div class="f-group">
              <label class="f-label">Payment Date <span style="color:var(--danger)">*</span></label>
              <input type="date" class="f-control" id="edit_payment_date" name="payment_date" required>
            </div>
            <div class="f-group">
              <label class="f-label">Payment Type <span style="color:var(--danger)">*</span></label>
              <select class="f-select" id="edit_payment_type" name="payment_type" required>
                <option value="NEFT">NEFT</option>
                <option value="RTGS">RTGS</option>
                <option value="UPI">UPI</option>
              </select>
            </div>
            <div class="f-group span2">
              <label class="f-label">Reference Number (UTR) <span style="color:var(--danger)">*</span></label>
              <input type="text" class="f-control" id="edit_e_payment_reference" name="e_payment_reference" required>
            </div>
            <div class="f-group span2">
              <label class="f-label">Description</label>
              <textarea class="f-control" id="edit_description" name="description"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_payment" class="btn-brand"><i class="fas fa-check-circle"></i> Update Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ DELETE MODAL ══════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-hdr" style="background:linear-gradient(135deg,var(--danger),#b91c1c)">
        <h5><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="text-align:center;padding:28px">
        <div style="width:60px;height:60px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.6rem;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div>
        <p style="font-weight:600;color:var(--text)">Delete this e-payment?</p>
        <p style="font-size:.85rem;color:var(--text-muted);margin-top:6px">This action cannot be undone.</p>
      </div>
      <form method="post">
        <input type="hidden" name="id" id="deleteId">
        <div class="modal-footer" style="justify-content:center">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_payment" class="btn-brand del-btn"><i class="fas fa-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// Auto-hide success
var sa=document.getElementById('sAlert');
if(sa) setTimeout(function(){sa.style.transition='opacity .5s';sa.style.opacity='0';setTimeout(function(){sa.remove();},500);},4000);

// Reset add form on modal close
document.getElementById('addModal').addEventListener('hidden.bs.modal',function(){
  document.getElementById('addForm').reset();
});

function populateEdit(id,bankId,txType,payee,amount,date,payType,ref,desc){
  document.getElementById('edit_id').value                = id;
  document.getElementById('edit_bank_account_id').value   = bankId;
  document.getElementById('edit_transaction_type').value  = txType;
  document.getElementById('edit_payee_name').value        = payee;
  document.getElementById('edit_amount').value            = amount;
  document.getElementById('edit_payment_date').value      = date;
  document.getElementById('edit_payment_type').value      = payType;
  document.getElementById('edit_e_payment_reference').value = ref;
  document.getElementById('edit_description').value       = desc;
}

function searchTable(){
  var q=document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#paymentTable tbody tr').forEach(function(tr){
    tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none';
  });
}

function exportToPDF(){
  var {jsPDF}=window.jspdf;
  var doc=new jsPDF('l','mm','a4');
  var pw=doc.internal.pageSize.width;
  doc.setFillColor(2,132,199); doc.rect(0,0,pw,22,'F');
  doc.setTextColor(255,255,255); doc.setFontSize(14); doc.setFont(undefined,'bold');
  doc.text('E-Payment Report',pw/2,14,{align:'center'});
  doc.setTextColor(30,30,30);
  doc.autoTable({
    html:'#paymentTable',
    startY:28,
    columnStyles:{11:{cellWidth:0,minCellWidth:0}}, // hide actions col
    styles:{fontSize:7,cellPadding:2},
    headStyles:{fillColor:[2,132,199],textColor:255,fontStyle:'bold'},
    alternateRowStyles:{fillColor:[240,247,252]},
    margin:{left:6,right:6}
  });
  doc.save('e_payment_report.pdf');
}

function exportToExcel(){
  // Clone table, remove action column
  var tbl=document.getElementById('paymentTable').cloneNode(true);
  tbl.querySelectorAll('th:last-child,td:last-child').forEach(el=>el.remove());
  var wb=XLSX.utils.book_new();
  var ws=XLSX.utils.table_to_sheet(tbl);
  XLSX.utils.book_append_sheet(wb,'E-Payments',ws);
  XLSX.writeFile(wb,'e_payment_report.xlsx');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>