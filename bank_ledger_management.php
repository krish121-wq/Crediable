<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$page_title = "Bank Ledger Management";
$success_message = '';
$error_message = '';
$user_id = (int)$_SESSION['user_id'];
$bank_accounts = [];

try {
    $stmt = $conn->prepare("SELECT id, bank_name, account_number FROM bank_accounts WHERE created_by = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $bank_accounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching bank accounts: " . $e->getMessage();
}

$filter_type      = $_POST['filter_type']      ?? 'monthly';
$selected_month   = $_POST['month']            ?? date('Y-m');
$selected_year    = $_POST['year']             ?? date('Y');
$start_date       = $_POST['start_date']       ?? '';
$end_date         = $_POST['end_date']         ?? '';
$bank_account_id  = (int)($_POST['bank_account_id'] ?? 0);
$timezone         = new DateTimeZone('Asia/Kolkata');
$transactions     = [];
$total_pages      = 1;
$records_per_page = 10;
$page             = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from       = ($page - 1) * $records_per_page;

try {
    $query = "SELECT bt.id, bt.bank_account_id, bt.transaction_type, bt.amount, bt.transaction_date, bt.description,
               bt.cheque_number, bt.e_payment_reference, bt.reference_type, bt.created_at,
               ba.bank_name, ba.account_number
        FROM bank_transactions bt
        JOIN bank_accounts ba ON bt.bank_account_id = ba.id
        WHERE bt.created_by = ? AND bt.status = 'completed'";
    $params = [$user_id];
    $param_types = "i";

    if ($bank_account_id) {
        $query .= " AND bt.bank_account_id = ?";
        $params[] = $bank_account_id; $param_types .= "i";
    }
    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(bt.transaction_date,'%Y-%m') = ?";
        $params[] = $selected_month; $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(bt.transaction_date) = ?";
        $params[] = (int)$selected_year; $param_types .= "i";
    } elseif ($filter_type === 'custom') {
        if (empty($start_date) || empty($end_date)) throw new Exception("Start and end date required for custom filter.");
        $s = new DateTime($start_date, $timezone);
        $e = new DateTime($end_date, $timezone);
        if ($s > $e) throw new Exception("Start date cannot be after end date.");
        $query .= " AND bt.transaction_date BETWEEN ? AND ?";
        $params[] = $s->format('Y-m-d 00:00:00');
        $params[] = $e->format('Y-m-d 23:59:59');
        $param_types .= "ss";
    }

    $count_q = str_replace(
        "SELECT bt.id, bt.bank_account_id, bt.transaction_type, bt.amount, bt.transaction_date, bt.description, bt.cheque_number, bt.e_payment_reference, bt.reference_type, bt.created_at, ba.bank_name, ba.account_number",
        "SELECT COUNT(*) as total", $query
    );
    $stmt = $conn->prepare($count_q);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    $total_pages = max(1, ceil($total_records / $records_per_page));

    $query .= " ORDER BY bt.transaction_date ASC, bt.id ASC LIMIT ?, ?";
    $params[] = $start_from; $params[] = $records_per_page; $param_types .= "ii";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $balance = 0;
    while ($row = $result->fetch_assoc()) {
        $balance += ($row['transaction_type'] === 'credit' ? $row['amount'] : -$row['amount']);
        $row['running_balance'] = $balance;
        $transactions[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Query Error: " . $e->getMessage();
    error_log("Bank Ledger Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bank Ledger — Grow Biller</title>
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

.page-wrap{margin-top:10px;margin-left:260px;padding:28px 24px 60px;transition:var(--transition)}
@media(max-width:992px){.page-wrap{margin-left:0;padding:18px 14px 50px}}

/* HERO */
.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.5rem;font-weight:800;color:#fff}
.hero-sub{color:rgba(255,255,255,.75);font-size:.85rem;margin-top:3px}
.hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:relative;z-index:1}
.btn-hero{color:#fff;border:2px solid rgba(255,255,255,.45);padding:9px 20px;border-radius:50px;font-size:.82rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:7px;cursor:pointer;transition:var(--transition);background:rgba(255,255,255,.14);text-decoration:none;white-space:nowrap}
.btn-hero:hover,.btn-hero:focus{background:rgba(255,255,255,.26);border-color:#fff;color:#fff;transform:translateY(-1px)}
.btn-hero.pdf{background:rgba(220,38,38,.75);border-color:rgba(255,255,255,.3)}
.btn-hero.pdf:hover{background:rgba(220,38,38,.95)}
.btn-hero.excel{background:rgba(22,163,74,.75);border-color:rgba(255,255,255,.3)}
.btn-hero.excel:hover{background:rgba(22,163,74,.95)}

/* ALERTS */
.alert-top{border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:10px;font-size:.88rem;font-weight:500;animation:slideDown .3s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5}

/* FILTER CARD */
.filter-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);margin-bottom:20px;overflow:hidden}
.filter-card-header{background:linear-gradient(90deg,var(--brand-light),#fff);padding:13px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
.filter-card-header h5{font-family:'Plus Jakarta Sans',sans-serif;font-size:.85rem;font-weight:800;color:var(--brand);margin:0}
.filter-body{padding:16px 20px}
.filter-grid{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end}
.f-group{display:flex;flex-direction:column;min-width:160px}
.f-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-mid);margin-bottom:5px}
.f-control,.f-select{border-radius:var(--radius-sm);border:1.5px solid var(--border);padding:8px 12px;font-size:.85rem;background:#fafafa;transition:var(--transition);color:var(--text);font-family:'Inter',sans-serif;height:38px}
.f-control:focus,.f-select:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.1);outline:none;background:#fff}
.btn-filter{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border:none;padding:9px 22px;border-radius:50px;font-size:.83rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--transition);white-space:nowrap;box-shadow:0 4px 12px rgba(2,132,199,.25)}
.btn-filter:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(2,132,199,.35)}

/* SEARCH BAR */
.table-topbar{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:linear-gradient(90deg,var(--brand-light),#fff)}
.search-wrap{position:relative;min-width:220px}
.search-wrap input{width:100%;padding:8px 12px 8px 36px;border:1.5px solid var(--border);border-radius:50px;font-size:.85rem;background:#fafafa;transition:var(--transition);outline:none}
.search-wrap input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.1);background:#fff}
.search-wrap .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.78rem}

/* TABLE CARD */
.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;margin-bottom:20px}
.table-scroll{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table thead th{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:12px 13px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.45px;white-space:nowrap;border:none;text-align:left}
.data-table tbody tr{border-bottom:1px solid #f0f4f8;transition:background .18s}
.data-table tbody tr:last-child{border-bottom:none}
.data-table tbody tr:hover{background:#f8fafc}
.data-table td{padding:12px 13px;vertical-align:middle;font-size:.85rem;color:var(--text)}
.data-table td.muted{color:var(--text-muted);font-size:.8rem}
.data-table td.debit{color:var(--danger);font-weight:700}
.data-table td.credit{color:var(--success);font-weight:700}
.data-table td.balance{font-weight:800;color:var(--brand);font-family:'Plus Jakarta Sans',sans-serif}

.type-badge{padding:3px 10px;border-radius:50px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
.type-badge.credit{background:var(--success-bg);color:var(--success)}
.type-badge.debit{background:var(--danger-bg);color:var(--danger)}

.no-data{text-align:center;padding:60px 20px;color:var(--text-muted)}
.no-data-icon{font-size:2.5rem;opacity:.3;margin-bottom:12px}

/* PAGINATION */
.pagination-wrap{display:flex;align-items:center;justify-content:center;gap:6px;padding:14px;flex-wrap:wrap;border-top:1px solid var(--border)}
.pg-btn{padding:7px 13px;border-radius:8px;border:1.5px solid var(--border);background:#fafafa;color:var(--text-mid);font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;transition:var(--transition)}
.pg-btn:hover,.pg-btn.active{background:var(--brand);color:#fff;border-color:var(--brand)}

@media(max-width:768px){
  .filter-grid{gap:10px}
  .f-group{min-width:100%}
  .btn-filter{width:100%}
}
</style>
</head>
<body>

<div class="page-wrap">

  <!-- HERO -->
  <div class="page-hero">
    <div>
      <div class="hero-title"><i class="fas fa-university me-2" style="opacity:.85"></i>Bank Ledger</div>
      <div class="hero-sub">View and analyze bank account transaction history</div>
    </div>
    <div class="hero-actions">
      <a href="#" class="btn-hero pdf" onclick="exportToPDF();return false"><i class="fas fa-file-pdf"></i> PDF</a>
      <a href="#" class="btn-hero excel" onclick="exportBackendCSV();return false"><i class="fas fa-file-excel"></i> Excel / CSV</a>
    </div>
  </div>

  <!-- ALERTS -->
  <?php if ($success_message): ?><div class="alert-top success" id="sAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
  <?php if ($error_message): ?><div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

  <!-- FILTER CARD -->
  <div class="filter-card">
    <div class="filter-card-header"><i class="fas fa-filter" style="color:var(--brand)"></i><h5>Filter Transactions</h5></div>
    <div class="filter-body">
      <form method="post" id="filterForm">
        <div class="filter-grid">

          <div class="f-group">
            <label class="f-label">Filter By</label>
            <select class="f-select" id="filter_type" name="filter_type" onchange="toggleFilterFields()">
              <option value="monthly"  <?php echo $filter_type==='monthly'  ? 'selected':'' ?>>Monthly</option>
              <option value="yearly"   <?php echo $filter_type==='yearly'   ? 'selected':'' ?>>Yearly</option>
              <option value="custom"   <?php echo $filter_type==='custom'   ? 'selected':'' ?>>Custom Range</option>
            </select>
          </div>

          <div class="f-group" id="month_field" <?php echo $filter_type!=='monthly' ? 'style="display:none"':'' ?>>
            <label class="f-label">Select Month</label>
            <input type="month" class="f-control" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
          </div>

          <div class="f-group" id="year_field" <?php echo $filter_type!=='yearly' ? 'style="display:none"':'' ?>>
            <label class="f-label">Select Year</label>
            <select class="f-select" name="year">
              <?php for($y=date('Y');$y>=date('Y')-5;$y--): ?>
              <option value="<?php echo $y ?>" <?php echo $selected_year==$y?'selected':'' ?>><?php echo $y ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="f-group" id="start_date_field" <?php echo $filter_type!=='custom' ? 'style="display:none"':'' ?>>
            <label class="f-label">Start Date</label>
            <input type="date" class="f-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
          </div>

          <div class="f-group" id="end_date_field" <?php echo $filter_type!=='custom' ? 'style="display:none"':'' ?>>
            <label class="f-label">End Date</label>
            <input type="date" class="f-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
          </div>

          <div class="f-group" style="min-width:200px">
            <label class="f-label">Bank Account</label>
            <select class="f-select" name="bank_account_id">
              <option value="0">All Accounts</option>
              <?php foreach($bank_accounts as $acc): ?>
              <option value="<?php echo $acc['id'] ?>" <?php echo $bank_account_id==$acc['id']?'selected':'' ?>>
                <?php echo htmlspecialchars($acc['bank_name'].' — '.$acc['account_number']); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="f-group">
            <label class="f-label">&nbsp;</label>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filter</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="table-card">
    <div class="table-topbar">
      <div class="search-wrap">
        <i class="fas fa-search si"></i>
        <input type="text" id="searchInput" placeholder="Search transactions…" oninput="searchTable()">
      </div>
      <span style="font-size:.78rem;color:var(--text-muted)"><?php echo count($transactions); ?> record<?php echo count($transactions)!==1?'s':''; ?></span>
    </div>
    <div class="table-scroll">
      <table class="data-table" id="ledgerTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Bank</th>
            <th>Account No.</th>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th>Reference</th>
            <th>Debit (₹)</th>
            <th>Credit (₹)</th>
            <th>Balance (₹)</th>
            <th>Created On</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($transactions)):
            $sno = $start_from + 1;
            foreach ($transactions as $row):
                $tdate = (new DateTime($row['transaction_date'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('d M Y');
                $cat   = (new DateTime($row['created_at'],      new DateTimeZone('UTC')))->setTimezone($timezone)->format('d M Y, h:i A');
                $ref   = $row['cheque_number'] ?: ($row['e_payment_reference'] ?: '—');
                $dr    = $row['transaction_type']==='debit'  ? '₹'.number_format($row['amount'],2) : '';
                $cr    = $row['transaction_type']==='credit' ? '₹'.number_format($row['amount'],2) : '';
                $bal   = $row['running_balance'];
        ?>
        <tr>
          <td class="muted"><?php echo $sno++; ?></td>
          <td style="font-weight:500"><?php echo htmlspecialchars($row['bank_name']); ?></td>
          <td style="font-family:monospace;font-size:.8rem"><?php echo htmlspecialchars($row['account_number']); ?></td>
          <td class="muted"><?php echo $tdate; ?></td>
          <td><span class="type-badge <?php echo $row['transaction_type']; ?>"><?php echo ucfirst($row['transaction_type']); ?></span></td>
          <td style="max-width:180px;color:var(--text-mid);font-size:.82rem"><?php echo htmlspecialchars($row['description'] ?? '—'); ?></td>
          <td style="font-family:monospace;font-size:.78rem;color:var(--text-muted)"><?php echo htmlspecialchars($ref); ?></td>
          <td class="debit"><?php echo $dr; ?></td>
          <td class="credit"><?php echo $cr; ?></td>
          <td class="balance">
            <span style="color:<?php echo $bal >= 0 ? 'var(--success)' : 'var(--danger)'; ?>">
              <?php echo ($bal<0?'−':'').'₹'.number_format(abs($bal),2); ?>
            </span>
          </td>
          <td class="muted" style="font-size:.78rem"><?php echo $cat; ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="11">
            <div class="no-data">
              <div class="no-data-icon"><i class="fas fa-university"></i></div>
              <div style="font-weight:600;margin-bottom:6px">No transactions found</div>
              <div style="font-size:.82rem">Try adjusting your filter or select a different account.</div>
            </div>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function toggleFilterFields(){
  var v=document.getElementById('filter_type').value;
  document.getElementById('month_field').style.display      = v==='monthly' ? '' : 'none';
  document.getElementById('year_field').style.display       = v==='yearly'  ? '' : 'none';
  document.getElementById('start_date_field').style.display = v==='custom'  ? '' : 'none';
  document.getElementById('end_date_field').style.display   = v==='custom'  ? '' : 'none';
}

function searchTable(){
  var q=document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#ledgerTable tbody tr').forEach(function(tr){
    tr.style.display=tr.innerText.toLowerCase().includes(q)?'':'none';
  });
}

function exportToPDF(){
  var {jsPDF}=window.jspdf;
  var doc=new jsPDF('l','mm','a4');
  var pw=doc.internal.pageSize.width;
  doc.setFillColor(2,132,199); doc.rect(0,0,pw,22,'F');
  doc.setTextColor(255,255,255); doc.setFontSize(14); doc.setFont(undefined,'bold');
  doc.text('Bank Ledger Report',pw/2,14,{align:'center'});
  doc.setTextColor(30,30,30);
  doc.autoTable({
    html:'#ledgerTable',
    startY:28,
    styles:{fontSize:7,cellPadding:2},
    headStyles:{fillColor:[2,132,199],textColor:255,fontStyle:'bold'},
    alternateRowStyles:{fillColor:[240,247,252]},
    margin:{left:6,right:6}
  });
  doc.save('bank_ledger_report.pdf');
}

function exportToExcel(){
  var wb=XLSX.utils.book_new();
  var ws=XLSX.utils.table_to_sheet(document.getElementById('ledgerTable'));
  XLSX.utils.book_append_sheet(wb,'Bank Ledger',ws);
  XLSX.writeFile(wb,'bank_ledger_report.xlsx');
}

function exportBackendCSV() {
    var filter_type = document.getElementById('filter_type').value;
    var bank_account_id = document.querySelector('select[name="bank_account_id"]').value;
    var month = document.querySelector('input[name="month"]').value;
    var year = document.querySelector('select[name="year"]').value;
    var start_date = document.querySelector('input[name="start_date"]').value;
    var end_date = document.querySelector('input[name="end_date"]').value;
    
    var url = 'export_bank_ledger_csv.php?filter_type=' + encodeURIComponent(filter_type) + 
              '&bank_account_id=' + encodeURIComponent(bank_account_id) +
              '&month=' + encodeURIComponent(month) +
              '&year=' + encodeURIComponent(year) +
              '&start_date=' + encodeURIComponent(start_date) +
              '&end_date=' + encodeURIComponent(end_date);
    
    window.location.href = url;
}

// Auto-hide success alert
var sa=document.getElementById('sAlert');
if(sa) setTimeout(function(){sa.style.transition='opacity .5s';sa.style.opacity='0';setTimeout(function(){sa.remove();},500);},4000);
</script>
</body>
</html>
<?php ob_end_flush(); ?>