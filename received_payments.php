<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$created_by = (int)$_SESSION['user_id'];

// Ensure tables exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS payment_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        receipt_no VARCHAR(50) NOT NULL,
        customer_name VARCHAR(255) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL,
        unused_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        reference_no VARCHAR(100) DEFAULT NULL,
        payment_date DATE NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conn->query("ALTER TABLE received_payments ADD COLUMN IF NOT EXISTS receipt_id INT DEFAULT NULL");
} catch (Exception $e) { error_log($e->getMessage()); }

// AJAX Endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] == 'get_invoices' && isset($_GET['customer'])) {
        $customer = $conn->real_escape_string($_GET['customer']);
        $exclude_id = isset($_GET['exclude_receipt_id']) ? (int)$_GET['exclude_receipt_id'] : 0;
        
        $sql = "SELECT i.id, i.invoice_no, i.created_at as date, 'Sale' as type, i.total_amount, 
                ROUND(i.total_amount - COALESCE(SUM(CASE WHEN r.receipt_id != $exclude_id OR r.receipt_id IS NULL THEN r.amount ELSE 0 END), 0), 2) as balance
                FROM invoices i
                LEFT JOIN received_payments r ON r.invoice_id = i.id
                WHERE i.customer_name = '$customer'
                GROUP BY i.id
                HAVING balance > 0.009
                ORDER BY i.created_at ASC";
        $res = $conn->query($sql);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if(empty($row['invoice_no'])) {
                    $row['invoice_no'] = 'INV-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
                }
                $data[] = $row;
            }
        }
        echo json_encode(['status'=>'success', 'data'=>$data]);
        exit;
    }
    
    if ($_GET['action'] == 'get_receipt' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $sql = "SELECT * FROM payment_receipts WHERE id = $id";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $receipt = $res->fetch_assoc();
            
            // Get links
            $links = [];
            $lsql = "SELECT invoice_id, amount FROM received_payments WHERE receipt_id = $id";
            $lres = $conn->query($lsql);
            if ($lres) {
                while($lrow = $lres->fetch_assoc()){
                    $links[$lrow['invoice_id']] = floatval($lrow['amount']);
                }
            }
            $receipt['links'] = $links;
            echo json_encode(['status'=>'success', 'data'=>$receipt]);
            exit;
        }
        echo json_encode(['status'=>'error']);
        exit;
    }
}

// Handle Form Submission (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_in'])) {
    try {
        $edit_id = (int)($_POST['edit_receipt_id'] ?? 0);
        $customer_name = trim($_POST['customer_name'] ?? '');
        $receipt_no = trim($_POST['receipt_no'] ?? '');
        $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $payment_method = trim($_POST['payment_method'] ?? '');
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $reference_no = trim($_POST['reference_no'] ?? '');
        $links_json = $_POST['links_json'] ?? '[]';
        
        $links = json_decode($links_json, true);
        if (!is_array($links)) $links = [];
        
        if (empty($customer_name) || empty($payment_method) || $total_amount <= 0) {
            throw new Exception("Please provide valid customer, payment method, and amount.");
        }
        
        $conn->begin_transaction();
        
        // If editing, reverse the previous transaction first
        if ($edit_id > 0) {
            $sel = $conn->prepare("SELECT total_amount, customer_name FROM payment_receipts WHERE id = ?");
            $sel->bind_param("i", $edit_id);
            $sel->execute();
            $res = $sel->get_result();
            if ($res->num_rows > 0) {
                $old_row = $res->fetch_assoc();
                $old_tot = floatval($old_row['total_amount']);
                $old_cname = $old_row['customer_name'];
                
                // Revert balance
                $conn->query("UPDATE customers SET current_balance = current_balance + $old_tot WHERE name = '$old_cname'");
                // Delete old links
                $conn->query("DELETE FROM received_payments WHERE receipt_id = $edit_id");
            }
            $sel->close();
        }
        
        $used_amount = 0;
        foreach($links as $link) {
            $used_amount += floatval($link['amount']);
        }
        $unused_amount = $total_amount - $used_amount;
        
        if ($edit_id > 0) {
            // Update existing receipt
            $stmt = $conn->prepare("UPDATE payment_receipts SET receipt_no=?, customer_name=?, payment_method=?, total_amount=?, unused_amount=?, reference_no=?, payment_date=? WHERE id=?");
            $stmt->bind_param("sssddssi", $receipt_no, $customer_name, $payment_method, $total_amount, $unused_amount, $reference_no, $payment_date, $edit_id);
            if(!$stmt->execute()) throw new Exception("Failed to update receipt.");
            $receipt_id = $edit_id;
            $stmt->close();
        } else {
            // Insert new receipt
            $stmt = $conn->prepare("INSERT INTO payment_receipts (receipt_no, customer_name, payment_method, total_amount, unused_amount, reference_no, payment_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssddssi", $receipt_no, $customer_name, $payment_method, $total_amount, $unused_amount, $reference_no, $payment_date, $created_by);
            if(!$stmt->execute()) throw new Exception("Failed to save receipt.");
            $receipt_id = $stmt->insert_id;
            $stmt->close();
        }
        
        // Insert new links
        foreach($links as $link) {
            $inv_id = (int)$link['invoice_id'];
            $amt = floatval($link['amount']);
            if($amt > 0) {
                $istmt = $conn->prepare("INSERT INTO received_payments (invoice_id, receipt_id, payment_method, amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $istmt->bind_param("iisdi", $inv_id, $receipt_id, $payment_method, $amt, $created_by);
                $istmt->execute();
                $istmt->close();
            }
        }
        
        // Deduct new balance
        $cstmt = $conn->prepare("UPDATE customers SET current_balance = GREATEST(0, current_balance - ?) WHERE name = ?");
        $cstmt->bind_param("ds", $total_amount, $customer_name);
        $cstmt->execute();
        $cstmt->close();
        
        $conn->commit();
        $_SESSION['success_message'] = $edit_id > 0 ? "Payment-In updated successfully!" : "Payment-In recorded successfully!";
        header('Location: received_payments.php');
        exit;
    } catch(Exception $e) {
        if(isset($conn)) $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: received_payments.php');
        exit;
    }
}

// Handle Delete Request
if (isset($_GET['delete_receipt_id'])) {
    $del_id = (int)$_GET['delete_receipt_id'];
    try {
        $conn->begin_transaction();
        
        // Get receipt info to restore balance
        $sel = $conn->prepare("SELECT total_amount, customer_name FROM payment_receipts WHERE id = ?");
        $sel->bind_param("i", $del_id);
        $sel->execute();
        $res = $sel->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $tot = floatval($row['total_amount']);
            $cname = $row['customer_name'];
            
            // Restore customer balance
            $conn->query("UPDATE customers SET current_balance = current_balance + $tot WHERE name = '$cname'");
            
            // Delete from received_payments
            $conn->query("DELETE FROM received_payments WHERE receipt_id = $del_id");
            
            // Delete from payment_receipts
            $conn->query("DELETE FROM payment_receipts WHERE id = $del_id");
        }
        $sel->close();
        
        $conn->commit();
        $_SESSION['success_message'] = "Payment Receipt deleted successfully!";
        header('Location: received_payments.php');
        exit;
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Failed to delete: " . $e->getMessage();
        header('Location: received_payments.php');
        exit;
    }
}

include('layout.php');

$customers = [];
$res = $conn->query("SELECT name FROM customers ORDER BY name ASC");
if($res) { while($row = $res->fetch_assoc()) $customers[] = $row; }

$receipts = [];
$sql = "SELECT pr.*, 
        CASE WHEN u.id IS NOT NULL THEN COALESCE(u.full_name,'Admin')
             WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name
        FROM payment_receipts pr
        LEFT JOIN users u ON pr.created_by = u.id
        LEFT JOIN create_users cu ON pr.created_by = cu.id
        ORDER BY pr.payment_date DESC, pr.created_at DESC";
$res = $conn->query($sql);
if($res) { while($row = $res->fetch_assoc()) $receipts[] = $row; }

$next_receipt = 'RCPT/26-27/0' . rand(100,999);
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
:root {
    --brand: #0284c7; --brand-dark: #0b1220; --brand-light: #e0f2fe;
    --success: #16a34a; --success-bg: #dcfce7;
    --danger: #dc2626; --danger-bg: #fee2e2;
    --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0;
    --text: #0f172a; --text-mid: #475569; --text-muted: #94a3b8;
    --radius-sm: 6px; --radius-md: 10px;
}
body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); }
.content { margin-top: 70px; margin-left: 260px; padding: 24px; min-height: 100vh; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.top-bar h4 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; color: var(--brand-dark); margin:0;}
.btn-primary { background: var(--brand); border-color: var(--brand); font-weight: 600; padding: 8px 16px; border-radius: 20px; box-shadow: 0 4px 10px rgba(2,132,199,0.2); }
.btn-primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
.card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: 0 2px 10px rgba(0,0,0,0.03); overflow: visible; }
.table-responsive { overflow: visible; }
.table { margin: 0; }
.table th { background: #f1f5f9; color: var(--text-mid); font-weight: 600; font-size: 0.75rem; text-transform: uppercase; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.table td { padding: 14px 16px; vertical-align: middle; font-size: 0.85rem; border-bottom: 1px solid var(--border); position: relative; }
.table tbody tr:hover { background: #f8fafc; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; background: var(--success-bg); color: var(--success); }
.form-control, .form-select { border-color: var(--border); font-size: 0.85rem; padding: 8px 12px; }
.form-control:focus, .form-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(2,132,199,0.1); }
label { font-size: 0.75rem; font-weight: 600; color: var(--text-mid); margin-bottom: 4px; }

/* Custom Checkbox */
.custom-checkbox { width: 18px; height: 18px; cursor: pointer; }

/* Nested Modals */
.modal-header { background: #fff; border-bottom: 1px solid var(--border); }
.modal-title { font-weight: 700; color: var(--brand-dark); }
.modal-footer { border-top: 1px solid var(--border); background: #f8fafc; }
</style>

<div class="content">
    
    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="top-bar">
        <h4>Payment-In <i class="fas fa-chevron-down ms-1" style="font-size: 0.8rem; color: #94a3b8;"></i></h4>
        <button class="btn btn-primary" onclick="resetPaymentForm()" data-bs-toggle="modal" data-bs-target="#paymentInModal"><i class="fas fa-plus me-2"></i> Add Payment-In</button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Ref. No.</th>
                        <th>Party Name</th>
                        <th>Total Amount</th>
                        <th>Received</th>
                        <th>Payment Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($receipts)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No transactions found.</td></tr>
                    <?php else: ?>
                        <?php foreach($receipts as $rcpt): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($rcpt['payment_date'])); ?></td>
                                <td><span style="font-weight:600"><?php echo htmlspecialchars($rcpt['receipt_no']); ?></span></td>
                                <td><div style="font-weight:600; color:var(--brand);"><?php echo htmlspecialchars($rcpt['customer_name']); ?></div></td>
                                <td style="font-weight:700">₹<?php echo number_format($rcpt['total_amount'],2); ?></td>
                                <td style="font-weight:700; color:var(--success)">₹<?php echo number_format($rcpt['total_amount'],2); ?></td>
                                <td><?php echo htmlspecialchars($rcpt['payment_method']); ?></td>
                                <td><span class="status-badge">Settled</span></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-light border text-primary" title="Print Receipt" onclick="window.print()"><i class="fas fa-print"></i></button>
                                        <button class="btn btn-sm btn-light border text-success" title="Edit" onclick="editReceipt(<?php echo $rcpt['id']; ?>)"><i class="fas fa-edit"></i></button>
                                        <a href="received_payments.php?delete_receipt_id=<?php echo $rcpt['id']; ?>" class="btn btn-sm btn-light border text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this payment receipt? All linked invoices will be unlinked and customer balance will be reversed.')"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Payment-In Details -->
<div class="modal fade" id="paymentInModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment-In</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentInForm" method="post" onsubmit="return submitPaymentIn(event)">
                <input type="hidden" name="links_json" id="links_json" value="[]">
                <input type="hidden" name="edit_receipt_id" id="edit_receipt_id" value="0">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label>Party <span class="text-danger">*</span></label>
                            <select id="party_select" name="customer_name" class="form-control" required style="width:100%">
                                <option value="">Search by Name/Phone *</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Receipt No</label>
                            <input type="text" name="receipt_no" class="form-control" value="<?php echo $next_receipt; ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label>Payment Type <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="Kotak Mahindra">Kotak Mahindra</option>
                                <option value="HDFC">HDFC</option>
                                <option value="UPI">UPI</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>
                        <div class="col-md-4 offset-md-4">
                            <label>Received Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" id="received_amount" name="total_amount" class="form-control" required style="font-size: 1.1rem; font-weight: 700; color: var(--success);" oninput="updateLinkButtonStatus()">
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label>Reference No.</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Cheque/Txn ID">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded border">
                        <div>
                            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Unlinked Amount</div>
                            <div id="unlinked_amount_display" style="font-size:1.1rem; font-weight:800; color:var(--brand);">₹0.00</div>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="btnOpenLinkModal" onclick="openLinkModal()" disabled>
                            <i class="fas fa-link me-1"></i> Link Payment to Txns
                        </button>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-light border"><i class="fas fa-share-alt"></i> Share</button>
                    </div>
                    <div>
                        <button type="submit" name="save_payment_in" class="btn btn-primary" style="padding:8px 24px;">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Link Payment -->
<div class="modal fade" id="linkPaymentModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center">
                <h6 class="modal-title m-0">Link Payment to Txns</h6>
                <button type="button" class="btn-close" onclick="closeLinkModal()"></button>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom" style="background:#f8fafc;">
                    <div>
                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight:600; text-transform:uppercase">Party</div>
                        <div id="link_party_name" style="font-weight:700; color:var(--text);"></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem; color:var(--brand); font-weight:600; text-transform:uppercase">Received</div>
                        <div id="link_received_amount" style="font-weight:800; color:var(--brand); font-size:1.1rem;"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="autoLinkInvoices()"><i class="fas fa-bolt me-1"></i> AUTO LINK</button>
                        <button type="button" class="btn btn-light btn-sm border" onclick="resetLinks()"><i class="fas fa-redo me-1"></i> RESET</button>
                    </div>
                </div>
                
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table table-hover mb-0" id="linkTxnsTable">
                        <thead style="position: sticky; top: 0; z-index: 10; background:#f1f5f9;">
                            <tr>
                                <th style="width: 40px; background:#f1f5f9;"></th>
                                <th style="background:#f1f5f9;">Date</th>
                                <th style="background:#f1f5f9;">Type</th>
                                <th style="background:#f1f5f9;">Ref/Inv No.</th>
                                <th class="text-end" style="background:#f1f5f9;">Total</th>
                                <th class="text-end" style="background:#f1f5f9;">Balance</th>
                                <th class="text-end" style="width: 150px; background:#f1f5f9;">Linked Amount</th>
                            </tr>
                        </thead>
                        <tbody id="txns_tbody">
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center" style="background:#fff;">
                <div class="me-4" style="font-weight:600; font-size:0.85rem;">
                    Unused Amount: <span id="modal_unused_display" style="color:var(--text-muted)">0.00</span>
                </div>
                <button type="button" class="btn btn-light border" onclick="closeLinkModal()">CANCEL</button>
                <button type="button" class="btn btn-primary" onclick="doneLinking()" style="background:#4338ca; border-color:#4338ca;">DONE</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let invoicesData = [];
let totalReceived = 0;
let unusedAmount = 0;

$(document).ready(function() {
    $('#party_select').select2({
        dropdownParent: $('#paymentInModal')
    }).on('change', function() {
        // Reset links when party changes
        invoicesData = [];
        updateLinkButtonStatus();
        renderLinksSummary();
    });
});

function updateLinkButtonStatus() {
    const party = $('#party_select').val();
    totalReceived = parseFloat($('#received_amount').val()) || 0;
    
    if (party && totalReceived > 0) {
        $('#btnOpenLinkModal').prop('disabled', false);
    } else {
        $('#btnOpenLinkModal').prop('disabled', true);
    }
    renderLinksSummary();
}

function resetPaymentForm() {
    document.getElementById('paymentInForm').reset();
    $('#party_select').val('').trigger('change');
    $('#edit_receipt_id').val('0');
    preloadedLinks = {};
    $('#paymentInModal .modal-title').text('Payment-In');
    updateLinkButtonStatus();
}

// Preloaded links for edit mode
let preloadedLinks = {};

function editReceipt(id) {
    // Reset form
    document.getElementById('paymentInForm').reset();
    $('#party_select').val('').trigger('change');
    
    // Fetch data
    $.get('received_payments.php', {action: 'get_receipt', id: id}, function(res) {
        if (res.status === 'success') {
            const data = res.data;
            $('#edit_receipt_id').val(data.id);
            $('#party_select').val(data.customer_name).trigger('change');
            $('input[name="receipt_no"]').val(data.receipt_no);
            $('input[name="payment_date"]').val(data.payment_date);
            $('select[name="payment_method"]').val(data.payment_method);
            $('input[name="reference_no"]').val(data.reference_no);
            $('#received_amount').val(parseFloat(data.total_amount).toFixed(2));
            
            preloadedLinks = data.links || {};
            
            $('#paymentInModal .modal-title').text('Edit Payment-In');
            $('#paymentInModal').modal('show');
            updateLinkButtonStatus();
        } else {
            alert('Failed to fetch receipt data.');
        }
    }, 'json');
}

function openLinkModal() {
    const party = $('#party_select').val();
    totalReceived = parseFloat($('#received_amount').val()) || 0;
    const edit_id = $('#edit_receipt_id').val();
    
    $('#link_party_name').text(party);
    $('#link_received_amount').text('₹' + totalReceived.toFixed(2));
    
    if(invoicesData.length === 0) {
        $('#txns_tbody').html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading transactions...</td></tr>');
        $.get('received_payments.php', {action: 'get_invoices', customer: party, exclude_receipt_id: edit_id}, function(res) {
            if(res.status === 'success') {
                invoicesData = res.data.map(inv => {
                    // Include preloaded linked amount for this invoice if any
                    const prevLinked = preloadedLinks[inv.id] || 0;
                    inv.linked = prevLinked;
                    // Because SQL already excluded the previous payment from balance, balance is exactly what it would be without this receipt.
                    // So we don't need to add it back to balance here anymore!
                    // BUT wait! The user wants to see the balance AS IF it is unpaid by this receipt. Yes, SQL does that.
                    return inv;
                });
                renderTxnsTable();
            }
        }, 'json');
    } else {
        renderTxnsTable();
    }
    
    $('#linkPaymentModal').modal('show');
}

function closeLinkModal() {
    $('#linkPaymentModal').modal('hide');
}

function formatDate(dateStr) {
    if(!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB');
}

function renderTxnsTable() {
    let html = '';
    let totalLinked = 0;
    
    if(invoicesData.length === 0) {
        html = '<tr><td colspan="7" class="text-center text-muted py-4">No pending transactions found.</td></tr>';
    }
    
    invoicesData.forEach((inv, idx) => {
        const isChecked = inv.linked > 0 ? 'checked' : '';
        const rowBg = inv.linked > 0 ? 'background:#f0f9ff;' : '';
        totalLinked += parseFloat(inv.linked);
        
        html += `
        <tr style="${rowBg}">
            <td><input type="checkbox" class="custom-checkbox" onchange="toggleRow(${idx})" ${isChecked} id="chk_${idx}"></td>
            <td>${formatDate(inv.date)}</td>
            <td>${inv.type}</td>
            <td>${inv.invoice_no}</td>
            <td class="text-end">₹${parseFloat(inv.total_amount).toFixed(2)}</td>
            <td class="text-end">₹${parseFloat(inv.balance).toFixed(2)}</td>
            <td class="text-end">
                <input type="number" step="0.01" class="form-control text-end form-control-sm" 
                       value="${inv.linked > 0 ? inv.linked.toFixed(2) : ''}" 
                       onchange="manualLink(${idx}, this.value)"
                       style="display: ${inv.linked > 0 ? 'block' : 'none'}; border-color:var(--brand); color:var(--brand); font-weight:600;">
            </td>
        </tr>`;
    });
    
    $('#txns_tbody').html(html);
    
    unusedAmount = totalReceived - totalLinked;
    $('#modal_unused_display').text('₹' + unusedAmount.toFixed(2));
}

function toggleRow(idx) {
    const isChecked = $(`#chk_${idx}`).is(':checked');
    const inv = invoicesData[idx];
    
    if (isChecked) {
        let currentTotalLinked = invoicesData.reduce((sum, item) => sum + (item.linked || 0), 0);
        let rem = totalReceived - currentTotalLinked;
        
        if (rem <= 0) {
            alert('No unused amount left to link.');
            $(`#chk_${idx}`).prop('checked', false);
            return;
        }
        
        let linkAmt = Math.min(rem, parseFloat(inv.balance));
        inv.linked = linkAmt;
    } else {
        inv.linked = 0;
    }
    renderTxnsTable();
}

function manualLink(idx, val) {
    const inv = invoicesData[idx];
    let amt = parseFloat(val) || 0;
    
    if (amt > parseFloat(inv.balance)) {
        alert('Cannot link more than balance amount.');
        amt = parseFloat(inv.balance);
    }
    
    inv.linked = amt;
    if (amt === 0) {
        $(`#chk_${idx}`).prop('checked', false);
    }
    renderTxnsTable();
}

function autoLinkInvoices() {
    let rem = totalReceived;
    
    invoicesData.sort((a, b) => new Date(a.date) - new Date(b.date));
    
    invoicesData.forEach(inv => {
        if (rem > 0) {
            let linkAmt = Math.min(rem, parseFloat(inv.balance));
            inv.linked = linkAmt;
            rem -= linkAmt;
        } else {
            inv.linked = 0;
        }
    });
    renderTxnsTable();
}

function resetLinks() {
    invoicesData.forEach(inv => inv.linked = 0);
    renderTxnsTable();
}

function doneLinking() {
    renderLinksSummary();
    closeLinkModal();
}

function renderLinksSummary() {
    let totalLinked = invoicesData.reduce((sum, item) => sum + (item.linked || 0), 0);
    let unused = totalReceived - totalLinked;
    $('#unlinked_amount_display').text('₹' + unused.toFixed(2));
    
    let links = invoicesData.filter(inv => inv.linked > 0).map(inv => {
        return { invoice_id: inv.id, amount: inv.linked };
    });
    $('#links_json').val(JSON.stringify(links));
}

function submitPaymentIn(e) {
    if(totalReceived <= 0) {
        alert('Received amount must be greater than zero.');
        return false;
    }
    return true;
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>