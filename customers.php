<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$indian_states = [
    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Delhi", "Goa", "Gujarat",
    "Haryana", "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh",
    "Maharashtra", "Manipur", "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab",
    "Rajasthan", "Sikkim", "Tamil Nadu", "Telangana", "Tripura", "Uttar Pradesh",
    "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands", "Chandigarh",
    "Dadra and Nagar Haveli and Daman and Diu", "Jammu and Kashmir",
    "Ladakh", "Lakshadweep", "Puducherry"
];

$page_title = "Customers";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];

if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_customer'])) {
            $name    = trim($_POST['name']    ?? '');
            $phone   = trim($_POST['phone']   ?? '');
            $email   = trim($_POST['email']   ?? '');
            $address = trim($_POST['address'] ?? '');
            $state   = trim($_POST['state']   ?? '');
            $city    = trim($_POST['city']    ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $gstin   = trim($_POST['gstin']   ?? '');

            // Only name is mandatory
            if (empty($name)) {
                throw new Exception("Customer name is required.");
            }

            // Phone duplicate check — only if phone is provided
            if (!empty($phone)) {
                $stmt = $conn->prepare("SELECT id FROM
                customers WHERE phone = ?");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $dupResult = $stmt->get_result();
                $stmt->close();
                if ($dupResult->num_rows > 0) {
                    throw new Exception("A customer with this phone number already exists.");
                }
            }

            // GSTIN format check — only if provided
            if (!empty($gstin) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                throw new Exception("Invalid GSTIN format.");
            }

      // Convert empty strings to NULL for optional fields
$phone = !empty($phone) ? $phone : null;
$email = !empty($email) ? $email : null;
$gstin = !empty($gstin) ? $gstin : null;

$stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address, state, city, pincode, gstin, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("ssssssss", $name, $phone, $email, $address, $state, $city, $pincode, $gstin);
            if (!$stmt->execute()) {
                throw new Exception("Failed to add customer: " . $stmt->error);
            }
            $stmt->close();

            $_SESSION['success_message'] = "Customer added successfully.";
            header("Location: customers.php");
            exit();

        } elseif (isset($_POST['edit_customer'])) {
            $customer_id = (int)($_POST['customer_id'] ?? 0);
            $name    = trim($_POST['name']    ?? '');
            $phone   = trim($_POST['phone']   ?? '');
            $email   = trim($_POST['email']   ?? '');
            $address = trim($_POST['address'] ?? '');
            $state   = trim($_POST['state']   ?? '');
            $city    = trim($_POST['city']    ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $gstin   = trim($_POST['gstin']   ?? '');

            if (empty($name) || !$customer_id) {
                throw new Exception("Customer name is required.");
            }

            // Phone duplicate check — only if phone is provided
            if (!empty($phone)) {
                $stmt = $conn->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
                $stmt->bind_param("si", $phone, $customer_id);
                $stmt->execute();
                $dupResult = $stmt->get_result();
                $stmt->close();
                if ($dupResult->num_rows > 0) {
                    throw new Exception("Another customer with this phone number already exists.");
                }
            }

            // GSTIN format check — only if provided
            if (!empty($gstin) && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                throw new Exception("Invalid GSTIN format.");
            }

      // Convert empty strings to NULL for optional fields
$phone = !empty($phone) ? $phone : null;
$email = !empty($email) ? $email : null;
$gstin = !empty($gstin) ? $gstin : null;

$stmt = $conn->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, state=?, city=?, pincode=?, gstin=?, updated_at=NOW() WHERE id=?");
$stmt->bind_param("ssssssssi", $name, $phone, $email, $address, $state, $city, $pincode, $gstin, $customer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update customer: " . $stmt->error);
            }
            $stmt->close();

            $_SESSION['success_message'] = "Customer updated successfully.";
            header("Location: customers.php");
            exit();

        } elseif (isset($_POST['delete_customer']) && $user_type === 'admin') {
            $customer_id = (int)($_POST['customer_id'] ?? 0);
            if (!$customer_id) {
                throw new Exception("Invalid customer ID.");
            }

            // Check for linked invoices
            $stmt = $conn->prepare("SELECT id FROM service_invoices WHERE customer_id = ?");
            $stmt->bind_param("i", $customer_id);
            $stmt->execute();
            $linked = $stmt->get_result();
            $stmt->close();
            if ($linked->num_rows > 0) {
                throw new Exception("Cannot delete customer as they have associated service invoices.");
            }

            $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->bind_param("i", $customer_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete customer: " . $stmt->error);
            }
            $stmt->close();

            $_SESSION['success_message'] = "Customer deleted successfully.";
            header("Location: customers.php");
            exit();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Customer Error: " . $e->getMessage());
    }
}

try {
    $stmt = $conn->prepare("SELECT * FROM customers ORDER BY name ASC");
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching customers: " . $e->getMessage();
    $customers = [];
}

$total_customers = count($customers);
$with_gstin   = count(array_filter($customers, fn($c) => !empty($c['gstin'])));
$with_phone   = count(array_filter($customers, fn($c) => !empty($c['phone'])));
$states_count = count(array_unique(array_filter(array_column($customers, 'state'))));
?>

<style>
    /* ============================================================
       DESIGN TOKENS — matches global CREDIBLE theme
    ============================================================ */
    :root {
        --brand:       #0284c7;
        --brand-dark:  #0b1220;
        --brand-mid:   #0ea5e9;
        --brand-light: #e0f2fe;
        --success:     #16a34a;
        --success-bg:  #dcfce7;
        --danger:      #dc2626;
        --danger-bg:   #fee2e2;
        --warning:     #d97706;
        --warning-bg:  #fef3c7;
        --info:        #0284c7;
        --info-bg:     #e0f2fe;
        --bg:          #f4f8fb;
        --card:        #ffffff;
        --border:      #e2e8f0;
        --text:        #0b1220;
        --text-mid:    #475569;
        --text-muted:  #94a3b8;
        --radius-sm:   7px;
        --radius-md:   12px;
        --radius-lg:   18px;
        --shadow-sm:   0 2px 8px rgba(2,132,199,.07);
        --shadow-md:   0 8px 24px rgba(2,132,199,.11);
        --shadow-lg:   0 16px 48px rgba(2,132,199,.16);
        --transition:  all .25s cubic-bezier(.4,0,.2,1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
        font-size: 14px;
        line-height: 1.5;
    }

    /* ── LAYOUT ── */
    .content {
        margin-top: 70px;
        margin-left: 260px;
        padding: 24px 20px 60px;
        min-height: calc(100vh - 70px);
        transition: var(--transition);
    }
    .content-full { margin-left: 0; }
    @media (max-width: 992px) { .content { margin-left: 0; padding: 16px 12px 50px; } }

    /* ── PAGE HERO ── */
    .page-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 22px 26px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
        flex-wrap: wrap;
    }
    .page-hero::before {
        content: ''; position: absolute;
        right: -50px; top: -50px;
        width: 200px; height: 200px;
        border-radius: 50%; background: rgba(255,255,255,.06);
    }
    .page-title h4 {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.45rem; font-weight: 800; color: #fff;
        display: flex; align-items: center; gap: 10px;
        margin: 0;
    }
    .page-title h6 { color: rgba(255,255,255,.75); font-size: .82rem; margin-top: 3px; font-weight: 500; }
    .page-btn-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

    /* ── STAT CARDS ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 20px;
    }
    @media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 500px) { .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; } }
    .stat-card {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        padding: 16px 18px;
        display: flex; align-items: center; gap: 12px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        position: relative; overflow: hidden;
    }
    .stat-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .stat-card::after {
        content: ''; position: absolute; right: -10px; top: -10px;
        width: 60px; height: 60px; border-radius: 50%;
        background: currentColor; opacity: .04;
    }
    .stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; flex-shrink: 0;
    }
    .stat-icon.purple { background: var(--brand-light); color: var(--brand); }
    .stat-icon.green  { background: var(--success-bg);  color: var(--success); }
    .stat-icon.blue   { background: var(--info-bg);     color: var(--info); }
    .stat-icon.gold   { background: #fdf4dc;            color: #c8932a; }
    .stat-val   { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.2rem; font-weight: 800; line-height: 1; color: var(--brand); }
    .stat-label { font-size: .7rem; color: var(--text-muted); margin-top: 3px; font-weight: 600; text-transform: uppercase; letter-spacing: .3px; }

    /* ── BUTTONS ── */
    .btn-custom {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff !important; border: 2px solid rgba(255,255,255,.7);
        padding: 10px 22px; border-radius: 50px;
        font-size: .85rem; font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        display: inline-flex; align-items: center; gap: 8px;
        cursor: pointer; transition: var(--transition);
        box-shadow: 0 5px 16px rgba(2,132,199,.28);
        text-decoration: none; white-space: nowrap;
    }
    .btn-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(2,132,199,.38);
        border-color: #fff;
        color: #fff;
    }

    /* ── ALERTS ── */
    .alert {
        border-radius: var(--radius-sm);
        padding: 12px 16px;
        margin-bottom: 18px;
        font-weight: 500;
        display: flex; align-items: center; gap: 10px;
        font-size: .88rem;
        animation: slideDown .3s ease;
    }
    @keyframes slideDown { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
    .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
    .alert-danger  { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #fca5a5; }

    /* ── TABLE CARD ── */
    .table-responsive {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        overflow-x: auto;
    }
    .table { width: 100%; border-collapse: collapse; }
    .table th {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff;
        padding: 12px 14px;
        font-size: .68rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px;
        text-align: left; white-space: nowrap; border: none;
    }
    .table td {
        padding: 13px 14px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        font-size: .88rem;
        color: var(--text);
    }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover { background: #f8fafc; }

    /* Customer name cell with avatar */
    .customer-cell { display: flex; align-items: center; gap: 10px; }
    .customer-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        background: linear-gradient(135deg, var(--brand), var(--brand-mid));
        color: #fff; font-weight: 800; font-size: .8rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .customer-name { font-weight: 600; font-size: .9rem; }

    /* GSTIN chip */
    .gstin-chip {
        font-family: monospace; font-size: .78rem;
        background: var(--brand-light); color: var(--brand);
        border-radius: 6px; padding: 4px 9px;
        font-weight: 700; letter-spacing: .3px; white-space: nowrap;
    }

    /* PIN chip */
    .pin-chip {
        font-family: monospace; font-size: .8rem;
        color: var(--text-mid);
    }

    /* Location chip */
    .loc-chip {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: .8rem; color: var(--text-mid);
    }
    .loc-chip i { color: var(--brand); font-size: .7rem; }

    .badge-na {
        background: #f1f5f9;
        color: var(--text-muted);
        padding: 3px 9px;
        border-radius: 50px;
        font-size: .72rem;
        font-weight: 600;
    }

    /* ── ACTION BUTTONS ── */
    .action-container { display: flex; gap: 6px; justify-content: center; align-items: center; }
    .action-btn {
        width: 34px; height: 34px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: .82rem;
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(0,0,0,.12);
        border: none; cursor: pointer; text-decoration: none;
        position: relative; flex-shrink: 0;
    }
    .action-btn.edit   { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); box-shadow: 0 4px 12px rgba(2,132,199,.25); }
    .action-btn.delete { background: linear-gradient(135deg, #ef4444, #b91c1c); box-shadow: 0 4px 12px rgba(239,68,68,.25); }
    .action-btn:hover { transform: translateY(-3px) scale(1.1); color: #fff;}

    .action-btn[data-title]::after {
        content: attr(data-title);
        position: absolute; bottom: calc(100% + 6px); left: 50%;
        transform: translateX(-50%);
        background: #0b1220; color: #fff;
        font-size: .68rem; padding: 4px 8px; border-radius: 5px;
        white-space: nowrap; opacity: 0; visibility: hidden;
        transition: opacity .2s; pointer-events: none; z-index: 30;
    }
    .action-btn:hover::after { opacity: 1; visibility: visible; }

    /* ── EMPTY STATE ── */
    .empty-row td {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        font-size: .9rem;
    }
    .empty-row i { color: #d1d5db; opacity: .6; }

    /* ── MODALS ── */
    .modal-content {
        border-radius: var(--radius-md);
        border: none;
        box-shadow: var(--shadow-lg);
    }
    .modal-header {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff;
        border-radius: var(--radius-md) var(--radius-md) 0 0;
        padding: 18px 22px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .modal-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.02rem; font-weight: 700; margin: 0;
    }
    .modal-body { padding: 22px; }
    .modal-footer { padding: 14px 22px; border-top: 1px solid var(--border); gap: 10px; }

    .form-label {
        font-weight: 700; font-size: .72rem; color: var(--text-mid);
        text-transform: uppercase; letter-spacing: .3px;
        margin-bottom: 6px; display: block;
    }
    .required-star { color: var(--danger); margin-left: 3px; }

    .form-control {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        font-size: .9rem;
        font-family: 'Inter', sans-serif;
        color: var(--text);
        background: #fafafa;
        transition: var(--transition);
        width: 100%;
        outline: none;
    }
    .form-control:focus {
        border-color: var(--brand);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    }
    select.form-control { appearance: auto; cursor: pointer; }
    .form-group { margin-bottom: 16px; }

    .modal-close-btn {
        background: rgba(255,255,255,.18);
        border: none; color: #fff;
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 18px; line-height: 1;
        transition: background .2s;
    }
    .modal-close-btn:hover { background: rgba(255,255,255,.32); }

    @media (max-width: 768px) {
        .table th, .table td { padding: 10px 10px; }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">

    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-users me-2" style="opacity:.85"></i>Customers</h4>
            <h6>Manage your customer and buyer accounts</h6>
        </div>

        <div class="page-btn-group">
            <a href="javascript:void(0);" class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-circle-plus"></i> Create New
            </a>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger" id="errorAlert">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-users"></i></div>
            <div>
                <div class="stat-val"><?php echo number_format($total_customers); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-phone"></i></div>
            <div>
                <div class="stat-val" style="color:var(--info)"><?php echo number_format($with_phone); ?></div>
                <div class="stat-label">With Phone</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="stat-val" style="color:#c8932a"><?php echo number_format($with_gstin); ?></div>
                <div class="stat-label">GST Registered</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-map-location-dot"></i></div>
            <div>
                <div class="stat-val" style="color:var(--success)"><?php echo number_format($states_count); ?></div>
                <div class="stat-label">States Covered</div>
            </div>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-responsive">
        <table class="table datatable" id="customersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>State</th>
                    <th>PIN Code</th>
                    <th>GSTIN</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $i => $customer):
                        $initials = strtoupper(substr($customer['name'], 0, 1) . (strpos($customer['name'], ' ') !== false ? substr($customer['name'], strpos($customer['name'], ' ') + 1, 1) : ''));
                    ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <div class="customer-cell">
                                    <div class="customer-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                </div>
                            </td>
                            <td><?php echo !empty($customer['phone']) ? htmlspecialchars($customer['phone']) : '<span class="badge-na">N/A</span>'; ?></td>
                            <td><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : '<span class="badge-na">N/A</span>'; ?></td>
                            <td><?php echo !empty($customer['city']) ? '<span class="loc-chip"><i class="fas fa-city"></i>'.htmlspecialchars($customer['city']).'</span>' : '<span class="badge-na">N/A</span>'; ?></td>
                            <td><?php echo !empty($customer['state']) ? '<span class="loc-chip"><i class="fas fa-map-pin"></i>'.htmlspecialchars($customer['state']).'</span>' : '<span class="badge-na">N/A</span>'; ?></td>
                            <td><?php echo !empty($customer['pincode']) ? '<span class="pin-chip">'.htmlspecialchars($customer['pincode']).'</span>' : '<span class="badge-na">N/A</span>'; ?></td>
                            <td><?php echo !empty($customer['gstin']) ? '<span class="gstin-chip">'.htmlspecialchars($customer['gstin']).'</span>' : '<span class="badge-na">N/A</span>'; ?></td>
                            <td>
                                <div class="action-container">
                                    <a href="javascript:void(0);"
                                       class="action-btn edit"
                                       data-title="Edit"
                                       data-bs-toggle="modal"
                                       data-bs-target="#editCustomerModal"
                                       onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer), ENT_QUOTES, 'UTF-8'); ?>);">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <?php if ($user_type === 'admin'): ?>
                                        <a href="javascript:void(0);"
                                           class="action-btn delete"
                                           data-title="Delete"
                                           data-bs-toggle="modal"
                                           data-bs-target="#deleteModal"
                                           onclick="document.getElementById('deleteCustomerId').value='<?php echo (int)$customer['id']; ?>';">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="9">
                            <i class="fas fa-users" style="font-size:32px;display:block;margin-bottom:10px;"></i>
                            <div style="font-weight:600;margin-bottom:4px;color:var(--text)">No customers found</div>
                            <div style="font-size:.82rem">Click <strong>Create New</strong> to add your first customer.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ==================== ADD CUSTOMER MODAL ==================== -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="addCustomerModalLabel">
                    <i class="fas fa-user-plus me-2"></i> Create Customer
                </h4>

                <button type="button" class="modal-close-btn" data-bs-dismiss="modal" aria-label="Close">×</button>
            </div>
            <form id="addCustomerForm" action="customers.php" method="post" autocomplete="off">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user me-1" style="color:var(--brand)"></i>Customer Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" name="name" required placeholder="Enter customer name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone me-1" style="color:var(--brand)"></i>Phone Number</label>
                                <input type="text" class="form-control" name="phone" placeholder="e.g. 9876543210" maxlength="15">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-envelope me-1" style="color:var(--brand)"></i>Email</label>
                                <input type="text" class="form-control" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-receipt me-1" style="color:var(--brand)"></i>GSTIN</label>
                                <input type="text" class="form-control" name="gstin" placeholder="e.g. 22AAAAA0000A1Z5" maxlength="15" style="text-transform:uppercase;font-family:monospace;letter-spacing:.5px" oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-location-dot me-1" style="color:var(--brand)"></i>Address</label>
                        <textarea class="form-control" name="address" rows="2" placeholder="Enter complete address"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-city me-1" style="color:var(--brand)"></i>City</label>
                                <input type="text" class="form-control" name="city" placeholder="Enter city">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map me-1" style="color:var(--brand)"></i>State</label>
                                <select class="form-control" name="state">
                                    <option value="">Select State</option>
                                    <?php foreach ($indian_states as $state_name): ?>
                                        <option value="<?php echo htmlspecialchars($state_name); ?>"><?php echo htmlspecialchars($state_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-hashtag me-1" style="color:var(--brand)"></i>PIN Code</label>
                                <input type="text" class="form-control" name="pincode" placeholder="Enter PIN code" maxlength="6">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_customer" class="btn btn-custom">
                        <i class="fas fa-plus"></i> Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT CUSTOMER MODAL ==================== -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="editCustomerModalLabel">
                    <i class="fas fa-user-pen me-2"></i> Edit Customer
                </h4>
                <button type="button" class="modal-close-btn" data-bs-dismiss="modal" aria-label="Close">×</button>
            </div>
            <form id="editCustomerForm" action="customers.php" method="post" autocomplete="off">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="editCustomerId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user me-1" style="color:var(--brand)"></i>Customer Name <span class="required-star">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required placeholder="Enter customer name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-phone me-1" style="color:var(--brand)"></i>Phone Number</label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" placeholder="e.g. 9876543210" maxlength="15">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-envelope me-1" style="color:var(--brand)"></i>Email</label>
                                <input type="text" class="form-control" id="edit_email" name="email" placeholder="Enter email address">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-receipt me-1" style="color:var(--brand)"></i>GSTIN</label>
                                <input type="text" class="form-control" id="edit_gstin" name="gstin" placeholder="e.g. 22AAAAA0000A1Z5" maxlength="15" style="text-transform:uppercase;font-family:monospace;letter-spacing:.5px" oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-location-dot me-1" style="color:var(--brand)"></i>Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2" placeholder="Enter complete address"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-city me-1" style="color:var(--brand)"></i>City</label>
                                <input type="text" class="form-control" id="edit_city" name="city" placeholder="Enter city">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-map me-1" style="color:var(--brand)"></i>State</label>
                                <select class="form-control" id="edit_state" name="state">
                                    <option value="">Select State</option>
                                    <?php foreach ($indian_states as $state_name): ?>
                                        <option value="<?php echo htmlspecialchars($state_name); ?>"><?php echo htmlspecialchars($state_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-hashtag me-1" style="color:var(--brand)"></i>PIN Code</label>
                                <input type="text" class="form-control" id="edit_pincode" name="pincode" placeholder="Enter PIN code" maxlength="6">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_customer" class="btn btn-custom">
                        <i class="fas fa-save"></i> Update Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== DELETE MODAL ==================== -->
<?php if ($user_type === 'admin'): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#b91c1c);">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete</h5>
                <button type="button" class="modal-close-btn" data-bs-dismiss="modal">×</button>
            </div>
            <div class="modal-body" style="padding:28px;text-align:center;">
                <div style="width:60px;height:60px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.7rem;color:var(--danger)">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h5 style="font-weight:700;margin-bottom:7px">Delete this customer?</h5>
                <p style="color:var(--text-muted);font-size:.85rem">This action cannot be undone.</p>
            </div>
            <div class="modal-footer" style="justify-content:center;gap:12px;">
                <form method="post">
                    <input type="hidden" name="customer_id" id="deleteCustomerId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_customer" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;padding:10px 22px;border-radius:8px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif">
                        <i class="fas fa-trash me-1"></i> Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function () {

    // Auto-dismiss alerts after 5 seconds
    function autoDismiss(id) {
        var el = document.getElementById(id);
        if (el) {
            setTimeout(function () {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 500);
            }, 5000);
        }
    }
    autoDismiss('successAlert');
    autoDismiss('errorAlert');

    // Reset add form on modal close
    $('#addCustomerModal').on('hidden.bs.modal', function () {
        $('#addCustomerForm')[0].reset();
    });

    // Reset edit form on modal close
    $('#editCustomerModal').on('hidden.bs.modal', function () {
        $('#editCustomerForm')[0].reset();
    });
    // Auto-fetch GST details on typing 15-char GSTIN
    $('input[name="gstin"]').on('input change', function() {
        var gstin = $(this).val().trim().toUpperCase();
        var form = $(this).closest('form');
        if (gstin.length === 15) {
            $.getJSON('ajax_gst_lookup.php?gstin=' + gstin, function(res) {
                if (res.success && res.data) {
                    if (!form.find('input[name="name"]').val() && res.data.legal_name) {
                        form.find('input[name="name"]').val(res.data.legal_name);
                    }
                    if (res.data.state) {
                        form.find('select[name="state"]').val(res.data.state);
                    }
                    if (res.data.address) {
                        form.find('textarea[name="address"]').val(res.data.address);
                    }
                }
            });
        }
    });
});

function editCustomer(customer) {
    try {
        $('#editCustomerId').val(customer.id   || '');
        $('#edit_name').val(customer.name       || '');
        $('#edit_phone').val(customer.phone     || '');
        $('#edit_email').val(customer.email     || '');
        $('#edit_address').val(customer.address || '');
        $('#edit_city').val(customer.city       || '');
        $('#edit_state').val(customer.state     || '');
        $('#edit_pincode').val(customer.pincode || '');
        $('#edit_gstin').val(customer.gstin     || '');
    } catch (e) {
        console.error('editCustomer error:', e);
        alert('Failed to load customer data: ' + e.message);
    }
}
</script>

<?php ob_end_flush(); ?>