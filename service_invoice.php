<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create/alter tables
try {
    $conn->query("ALTER TABLE service_invoice_items ADD COLUMN IF NOT EXISTS service_description TEXT AFTER service_name");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(50) AFTER id");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS invoice_date DATE AFTER invoice_no");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS delivery_note TEXT AFTER invoice_date");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(255) AFTER delivery_note");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS reference_no VARCHAR(50) AFTER payment_terms");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS buyer_order_no VARCHAR(50) AFTER reference_no");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dated DATE AFTER buyer_order_no");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dispatch_doc_no VARCHAR(50) AFTER dated");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS delivery_note_date DATE AFTER dispatch_doc_no");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS dispatched_through VARCHAR(100) AFTER delivery_note_date");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS destination TEXT AFTER dispatched_through");
    $conn->query("ALTER TABLE service_invoices ADD COLUMN IF NOT EXISTS terms_of_delivery TEXT AFTER destination");
} catch (Exception $e) {
    error_log("Table alter error: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

include('layout.php');

$page_title      = "Service Invoice";
$success_message = $_SESSION['success_message'] ?? '';
$error_message   = '';
$user_type       = $_SESSION['user_type'] ?? 'admin';
$created_by_id   = (int)$_SESSION['user_id'];

if ($success_message) unset($_SESSION['success_message']);

// Company details
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$company) $company = ['company_name'=>'Your Company','company_address'=>'','company_gst'=>'','company_logo'=>'','contact_name'=>'','email'=>'','phone'=>'','state'=>''];
    $company_state = $company['state'] ?? '';
} catch (Exception $e) {
    $company = ['company_name'=>'Your Company','company_address'=>'','company_gst'=>'','company_logo'=>'','contact_name'=>'','email'=>'','phone'=>'','state'=>''];
    $company_state = '';
}

// Customers
try {
    $stmt = $conn->prepare("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC");
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $customers = [];
}

require_once 'includes/company_functions.php';

// Helper for auto-increment Service Invoice Number (Unified with Sales Invoices)
function generateNextServiceInvoiceNo($conn) {
    if (function_exists('getUnifiedNextInvoiceNumber')) {
        return getUnifiedNextInvoiceNumber($conn, 'SINV');
    }
    $currentYear = date('Y');
    return "SINV-" . $currentYear . "-0001";
}
$next_auto_invoice_no = generateNextServiceInvoiceNo($conn);

// GST rate options
$gst_rates = [0, 5, 12, 18, 28];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['delete_invoice'])) {
            $invoice_id = (int)$_POST['delete_invoice'];
            if ($invoice_id <= 0) throw new Exception("Invalid invoice ID");
            if ($user_type !== 'admin') {
                $stmt = $conn->prepare("SELECT created_by FROM service_invoices WHERE id = ?");
                $stmt->bind_param("i", $invoice_id); $stmt->execute();
                $chk = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$chk || $chk['created_by'] != $created_by_id) throw new Exception("No permission to delete this invoice.");
            }
            $conn->begin_transaction();
            $stmt = $conn->prepare("DELETE FROM service_invoice_items WHERE invoice_id = ?");
            $stmt->bind_param("i", $invoice_id); $stmt->execute(); $stmt->close();
            $stmt = $conn->prepare("DELETE FROM service_invoices WHERE id = ?");
            $stmt->bind_param("i", $invoice_id); $stmt->execute(); $stmt->close();
            $conn->commit();
            $_SESSION['success_message'] = "Invoice deleted successfully.";
            header("Location: service_invoice.php"); exit();

        } elseif (isset($_POST['add_service_invoice'])) {
            $customer_id      = (int)($_POST['customer_id'] ?? 0);
            $customer_name    = trim($_POST['customer_name'] ?? '');
            $customer_phone   = !empty(trim($_POST['customer_phone'] ?? '')) ? trim($_POST['customer_phone']) : null;
            $customer_address = !empty(trim($_POST['customer_address'] ?? '')) ? trim($_POST['customer_address']) : null;
            $shipping_address = !empty(trim($_POST['shipping_address'] ?? '')) ? trim($_POST['shipping_address']) : null;
            $services         = json_decode($_POST['services_json'] ?? '[]', true);
            $payment_method   = trim($_POST['payment_method'] ?? '');
            $paid_amount      = floatval($_POST['paid_amount'] ?? 0);

            if (empty($customer_name) || empty($services)) throw new Exception("Customer name and at least one service are required.");
            if (!empty($customer_phone) && !preg_match('/^\+?[1-9]\d{9,14}$/', $customer_phone)) throw new Exception("Invalid phone number format.");

            $subtotal = $total_discount = $total_gst = $grand_total = 0;
            $customer_state = null;
            if (!empty($services) && isset($services[0]['customer_state'])) $customer_state = $services[0]['customer_state'];

            foreach ($services as $service) {
                $amount         = floatval($service['amount'] ?? 0);
                $discount_type  = $service['discount_type'] ?? 'amount';
                $discount_value = floatval($service['discount'] ?? 0);
                $gst_rate       = floatval($service['gst_rate'] ?? 0);

                $discount_amount = ($discount_type === 'percentage') ? $amount * ($discount_value / 100) : $discount_value;
                $after_discount  = $amount - $discount_amount;
                $gst_amount      = (!empty($customer_state) && !empty($company_state)) ? $after_discount * ($gst_rate / 100) : 0;
                $service_total   = $after_discount + $gst_amount;

                $subtotal      += $amount;
                $total_discount += $discount_amount;
                $total_gst     += $gst_amount;
                $grand_total   += $service_total;
            }

            $conn->begin_transaction();
            $invoice_no         = $_POST['invoice_no'] ?? '';
            $invoice_date       = $_POST['invoice_date'] ?? null;
            $delivery_note      = $_POST['delivery_note'] ?? '';
            $payment_terms      = $_POST['payment_terms'] ?? '';
            $reference_no       = $_POST['reference_no'] ?? '';
            $buyer_order_no     = $_POST['buyer_order_no'] ?? '';
            $dated              = $_POST['dated'] ?? null;
            $dispatch_doc_no    = $_POST['dispatch_doc_no'] ?? '';
            $delivery_note_date = $_POST['delivery_note_date'] ?? null;
            $dispatched_through = $_POST['dispatched_through'] ?? '';
            $destination        = $_POST['destination'] ?? '';
            $terms_of_delivery  = $_POST['terms_of_delivery'] ?? '';

            $stmt = $conn->prepare("INSERT INTO service_invoices (invoice_no, invoice_date, delivery_note, payment_terms, reference_no, buyer_order_no, dated, dispatch_doc_no, delivery_note_date, dispatched_through, destination, terms_of_delivery, customer_id, customer_name, customer_phone, customer_address, shipping_address, subtotal, discount_amount, gst_amount, total_amount, payment_method, paid_amount, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssssssssssissssddddsdi", $invoice_no, $invoice_date, $delivery_note, $payment_terms, $reference_no, $buyer_order_no, $dated, $dispatch_doc_no, $delivery_note_date, $dispatched_through, $destination, $terms_of_delivery, $customer_id, $customer_name, $customer_phone, $customer_address, $shipping_address, $subtotal, $total_discount, $total_gst, $grand_total, $payment_method, $paid_amount, $created_by_id);
            if (!$stmt->execute()) throw new Exception("Invoice insert failed: " . $stmt->error);
            $invoice_id = $stmt->insert_id; $stmt->close();

            foreach ($services as $service) {
                $sname        = $service['service_name'] ?? '';
                $sdesc        = $service['service_description'] ?? '';
                $sac          = $service['sac_code'] ?? '';
                $qty          = floatval($service['quantity'] ?? 0);
                $unit         = $service['unit'] ?? '';
                $rate         = floatval($service['rate'] ?? 0);
                $amount       = floatval($service['amount'] ?? 0);
                $disc_type    = $service['discount_type'] ?? 'amount';
                $disc_val     = floatval($service['discount'] ?? 0);
                $cust_state   = $service['customer_state'] ?? null;
                $gst_rate_val = floatval($service['gst_rate'] ?? 0);

                $stmt = $conn->prepare("INSERT INTO service_invoice_items (invoice_id, service_name, service_description, sac_code, quantity, unit, rate, amount, discount_type, discount_value, customer_state, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssdsdssss", $invoice_id, $sname, $sdesc, $sac, $qty, $unit, $rate, $amount, $disc_type, $disc_val, $cust_state);
                if (!$stmt->execute()) throw new Exception("Service item insert failed: " . $stmt->error);
                $stmt->close();
            }

            if ($paid_amount > 0 && !empty($payment_method)) {
                $stmt = $conn->prepare("INSERT INTO service_invoice_payments (invoice_id, customer_id, payment_method, payment_amount, payment_notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $pnotes = "Payment received during invoice creation";
                $stmt->bind_param("iisdsi", $invoice_id, $customer_id, $payment_method, $paid_amount, $pnotes, $created_by_id);
                if (!$stmt->execute()) throw new Exception("Payment insert failed."); $stmt->close();
            }

            $conn->commit();
            $_SESSION['success_message'] = "Service invoice #{$invoice_no} created successfully.";
            header("Location: service_invoice.php"); exit();
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno === 0) $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Service Invoice Error: " . $e->getMessage());
    }
}

// Excel export
if (isset($_POST['export_excel']) && $_POST['export_excel'] == '1') {
    $dateRange = $_POST['date_range'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $now       = new DateTime();
    $dateFilter = "";
    switch ($dateRange) {
        case '7days':   $dateFilter = "AND DATE(si.invoice_date) >= '" . (clone $now)->modify('-7 days')->format('Y-m-d') . "'"; break;
        case '1month':  $dateFilter = "AND DATE(si.invoice_date) >= '" . (clone $now)->modify('-1 month')->format('Y-m-d') . "'"; break;
        case '6months': $dateFilter = "AND DATE(si.invoice_date) >= '" . (clone $now)->modify('-6 months')->format('Y-m-d') . "'"; break;
        case 'thisyear':
            $yr = $now->format('Y'); $apr = new DateTime($yr.'-04-01');
            if ($now < $apr) $apr = new DateTime(($yr-1).'-04-01');
            $dateFilter = "AND DATE(si.invoice_date) >= '" . $apr->format('Y-m-d') . "'"; break;
        case 'custom':
            if (!empty($startDate) && !empty($endDate)) $dateFilter = "AND DATE(si.invoice_date) BETWEEN '$startDate' AND '$endDate'"; break;
    }
    try {
        $q = "SELECT si.invoice_no, si.invoice_date, si.customer_name, si.customer_phone, si.customer_address, si.subtotal, si.discount_amount, si.gst_amount, si.total_amount, si.paid_amount, si.payment_method, si.created_at, CASE WHEN au.id IS NOT NULL THEN COALESCE(au.full_name,'Admin') WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name FROM service_invoices si LEFT JOIN users au ON si.created_by = au.id LEFT JOIN create_users cu ON si.created_by = cu.id WHERE 1=1 $dateFilter ORDER BY si.invoice_date DESC";
        if ($user_type !== 'admin') {
            $q = str_replace("WHERE 1=1", "WHERE si.created_by = $created_by_id", $q);
        }
        $result = $conn->query($q);
        $invoices = $result->fetch_all(MYSQLI_ASSOC);
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="service_invoices_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ["Invoice No","Invoice Date","Customer","Phone","Address","Subtotal","Discount","GST","Total","Paid","Outstanding","Payment Method","Created By","Created At"]);
        foreach ($invoices as $inv) {
            fputcsv($out, [$inv['invoice_no'], (!empty($inv['invoice_date']) ? date('d/m/Y', strtotime($inv['invoice_date'])) : 'N/A'), $inv['customer_name'], $inv['customer_phone']??'N/A', str_replace(["\n","\r","\t"]," ",$inv['customer_address']??''), number_format($inv['subtotal'],2), number_format($inv['discount_amount'],2), number_format($inv['gst_amount'],2), number_format($inv['total_amount'],2), number_format($inv['paid_amount'],2), number_format($inv['total_amount']-$inv['paid_amount'],2), $inv['payment_method']??'N/A', $inv['created_by_name'], date('d/m/Y H:i', strtotime($inv['created_at']))]);
        }
        fclose($out); exit;
    } catch (Exception $e) { die("Export error: " . $e->getMessage()); }
}

// Fetch invoices for display
try {
    if ($user_type === 'admin') {
        $stmt = $conn->prepare("SELECT si.*, CASE WHEN au.id IS NOT NULL THEN COALESCE(au.full_name,'Admin') WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name FROM service_invoices si LEFT JOIN users au ON si.created_by = au.id LEFT JOIN create_users cu ON si.created_by = cu.id ORDER BY si.created_at DESC");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT si.*, CASE WHEN au.id IS NOT NULL THEN COALESCE(au.full_name,'Admin') WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name FROM service_invoices si LEFT JOIN users au ON si.created_by = au.id LEFT JOIN create_users cu ON si.created_by = cu.id WHERE si.created_by = ? ORDER BY si.created_at DESC");
        $stmt->bind_param("i", $created_by_id); $stmt->execute();
    }
    $service_invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) { $error_message = "Error fetching invoices: " . $e->getMessage(); $service_invoices = []; }

// Stats
$total_invoices    = count($service_invoices);
$total_revenue     = array_sum(array_column($service_invoices, 'total_amount'));
$total_paid        = array_sum(array_column($service_invoices, 'paid_amount'));
$total_outstanding = $total_revenue - $total_paid;

function safeHtml($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Service Invoice – CREDIBLE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
/* ── TOKENS ── */
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
    --t:           all .22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:13.5px;}

/* ── LAYOUT ── */
.page-wrap{margin-top:64px;margin-left:260px;padding:22px 22px 60px;min-height:calc(100vh - 64px);transition:var(--t);}
@media(max-width:992px){.page-wrap{margin-left:0;padding:14px 12px 48px;}}

/* ── HERO ── */
.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:20px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap;}
.page-hero::before{content:'';position:absolute;right:-45px;top:-45px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.06);}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.35rem;font-weight:800;color:#fff;}
.hero-sub{color:rgba(255,255,255,.72);font-size:.8rem;margin-top:2px;}
.hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}

/* ── STAT CARDS ── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr 1fr;gap:10px;}}
.stat-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);padding:15px 17px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);transition:var(--t);}
.stat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);}
.stat-icon{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.stat-icon.purple{background:var(--brand-light);color:var(--brand);}
.stat-icon.blue{background:var(--info-bg);color:var(--info);}
.stat-icon.green{background:var(--success-bg);color:var(--success);}
.stat-icon.red{background:var(--danger-bg);color:var(--danger);}
.stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;line-height:1.1;}
.stat-val.purple{color:var(--brand);}
.stat-val.blue{color:var(--info);}
.stat-val.green{color:var(--success);}
.stat-val.red{color:var(--danger);}
.stat-label{font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.35px;font-weight:600;margin-top:3px;}

/* ── SEARCH BAR ── */
.search-bar{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);flex-wrap:wrap;}
.search-field{position:relative;flex:1;min-width:200px;}
.search-field .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.78rem;}
.search-input{width:100%;padding:9px 32px 9px 34px;border:1.5px solid var(--border);border-radius:50px;font-size:.85rem;background:#fafafa;outline:none;transition:var(--t);}
.search-input:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.12);}
.search-count{font-size:.75rem;color:var(--text-muted);white-space:nowrap;}

/* ── TABLE CARD ── */
.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;}
.table-scroll{overflow-x:auto;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;padding:11px 13px;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;text-align:left;border:none;}
.data-table tbody tr{border-bottom:1px solid var(--border);transition:background .14s;}
.data-table tbody tr:last-child{border-bottom:none;}
.data-table tbody tr:hover{background:#f8fafc;}
.data-table td{padding:11px 13px;vertical-align:middle;font-size:.85rem;}

/* ── TABLE CELLS ── */
.inv-num{font-family:monospace;background:var(--brand-light);color:var(--brand);border-radius:6px;padding:3px 8px;font-size:.78rem;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.cust-cell{display:flex;align-items:center;gap:9px;}
.cust-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;font-weight:800;font-size:.78rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.cust-name{font-weight:600;font-size:.87rem;}
.cust-phone{font-size:.7rem;color:var(--text-muted);margin-top:1px;}
.amount-cell{font-weight:700;color:var(--brand);font-family:'Plus Jakarta Sans',sans-serif;font-size:.9rem;}
.outstanding-cell{font-weight:700;color:var(--danger);font-size:.88rem;}
.paid-cell{font-weight:700;color:var(--success);font-size:.88rem;}
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;}
.status-badge.paid{background:var(--success-bg);color:var(--success);}
.status-badge.partial{background:var(--warning-bg);color:var(--warning);}
.status-badge.pending{background:var(--danger-bg);color:var(--danger);}
.creator-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:50px;font-size:.68rem;font-weight:700;}
.creator-badge.admin{background:var(--brand-light);color:var(--brand);}
.creator-badge.staff{background:var(--success-bg);color:var(--success);}

/* ── ACTION BUTTONS ── */
.action-group{display:flex;gap:5px;align-items:center;justify-content:center;}
.act-btn{width:31px;height:31px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.77rem;color:#fff;border:none;cursor:pointer;transition:var(--t);text-decoration:none;flex-shrink:0;position:relative;}
.act-btn:hover{transform:translateY(-3px) scale(1.1);}
.act-btn.print{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 3px 10px rgba(16,185,129,.28);}
.act-btn.view{background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 3px 10px rgba(124,58,237,.28);}
.act-btn.whatsapp{background:linear-gradient(135deg,#25d366,#128c7e);box-shadow:0 3px 10px rgba(37,211,102,.28);}
.act-btn.edit{background:linear-gradient(135deg,var(--brand),var(--brand-mid));box-shadow:0 3px 10px rgba(2,132,199,.28);}
.act-btn.del{background:linear-gradient(135deg,#ef4444,#b91c1c);box-shadow:0 3px 10px rgba(239,68,68,.28);}
.act-btn::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 5px);left:50%;transform:translateX(-50%);background:#0b1220;color:#fff;font-size:.65rem;padding:3px 7px;border-radius:5px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s;z-index:50;}
.act-btn:hover::after{opacity:1;}

/* ── BUTTONS ── */
.btn-primary-c{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff!important;border:2px solid rgba(255,255,255,.7);padding:9px 18px;border-radius:50px;font-size:.82rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--t);box-shadow:0 5px 14px rgba(2,132,199,.26);text-decoration:none;white-space:nowrap;}
.btn-primary-c:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(2,132,199,.36);border-color:#fff;color:#fff!important;}
.btn-export{padding:9px 16px;border-radius:50px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:var(--t);white-space:nowrap;}
.btn-export.excel{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 4px 12px rgba(22,163,74,.22);}
.btn-export:hover{transform:translateY(-2px);}

/* ── ALERTS ── */
.alert-top{border-radius:var(--radius-sm);padding:11px 14px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:500;animation:slideIn .3s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac;}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

/* ── MODAL ── */
.modal-content{border-radius:var(--radius-md);border:none;box-shadow:var(--shadow-lg);}
.modal-hdr{background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:#fff;border-radius:var(--radius-md) var(--radius-md) 0 0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.modal-hdr .modal-title{font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;font-size:.95rem;}
.modal-body{padding:20px;}
.modal-footer{border-top:1px solid var(--border);padding:12px 20px;flex-shrink:0;}

/* Ensure Bootstrap's scrollable modal has room for header+footer and only the body scrolls */
.modal-dialog-scrollable .modal-content{max-height:calc(100vh - 3.5rem);}
.modal-dialog-scrollable .modal-body{overflow-y:auto;}

/* ── FORM SECTIONS IN MODAL ── */
.form-section{background:var(--bg);border-radius:var(--radius-sm);border:1px solid var(--border);padding:14px 16px;margin-bottom:16px;}
.form-section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:.8rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;display:flex;align-items:center;gap:7px;}
.form-section-title i{font-size:.8rem;}

.mfield{margin-bottom:12px;}
.mfield label{display:block;font-size:.7rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.35px;margin-bottom:5px;}
.mfield .req{color:var(--danger);}
.mfield input,
.mfield select,
.mfield textarea{width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.87rem;font-family:'Inter',sans-serif;color:var(--text);background:#fafafa;outline:none;transition:var(--t);}
.mfield input:focus,.mfield select:focus,.mfield textarea:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.12);}
.mfield textarea{resize:vertical;min-height:56px;}
.mfield select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239590a8'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;background-size:16px;padding-right:32px;}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
@media(max-width:640px){.grid-2,.grid-3,.grid-4{grid-template-columns:1fr;}}

/* ── SERVICE ROW ── */
.service-row{background:var(--card);border:1.5px solid var(--border);border-radius:10px;padding:13px;margin-bottom:10px;transition:var(--t);}
.service-row:hover{border-color:rgba(2,132,199,.25);box-shadow:var(--shadow-sm);}
.service-row-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr 100px 36px;gap:9px;align-items:end;}
@media(max-width:1100px){.service-row-grid{grid-template-columns:1fr 1fr 1fr;}}
@media(max-width:640px){.service-row-grid{grid-template-columns:1fr;}}
.remove-svc-btn{width:34px;height:34px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;transition:var(--t);flex-shrink:0;}
.remove-svc-btn:hover{transform:scale(1.1);}
.add-svc-btn{width:100%;background:var(--brand-light);border:1.5px dashed rgba(2,132,199,.3);color:var(--brand);padding:9px;border-radius:9px;font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:var(--t);margin-bottom:4px;}
.add-svc-btn:hover{background:rgba(2,132,199,.08);border-color:var(--brand);}

/* ── GST BADGE ── */
.gst-select-wrap{position:relative;}
.gst-badge-display{display:inline-flex;align-items:center;gap:4px;background:var(--brand-light);color:var(--brand);border-radius:50px;padding:2px 8px;font-size:.68rem;font-weight:800;margin-top:3px;}

/* ── TOTAL DISPLAY ── */
.total-box{background:linear-gradient(135deg,var(--brand-light),#fff);border:1.5px solid rgba(2,132,199,.15);border-radius:10px;padding:13px 15px;}
.total-box .tline{display:flex;justify-content:space-between;font-size:.82rem;color:var(--text-mid);padding:3px 0;}
.total-box .tline.grand{font-weight:800;font-size:.98rem;color:var(--brand);border-top:1.5px solid rgba(2,132,199,.18);margin-top:6px;padding-top:8px;}

/* ── PAYMENT SECTION ── */
.payment-box{background:#f8fafc;border:1.5px solid rgba(2,132,199,.12);border-radius:10px;padding:12px 14px;}
.pay-hint{margin-top:8px;font-size:.74rem;padding:7px 10px;border-radius:7px;display:none;}
.pay-hint.full{background:var(--success-bg);color:var(--success);border:1px solid #86efac;}
.pay-hint.partial{background:var(--warning-bg);color:var(--warning);border:1px solid #fcd34d;}
.pay-hint.none{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

/* ── DELETE CONFIRM ── */
.del-icon{width:54px;height:54px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.6rem;color:var(--danger);}

/* ── EMPTY ROW ── */
.empty-row td{text-align:center;padding:56px 20px;color:var(--text-muted);}
.empty-icon{font-size:2.2rem;opacity:.3;margin-bottom:10px;}

/* ── EXPORT MODAL ── */
.duration-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px;}
.duration-card{border:2px solid var(--border);border-radius:var(--radius-sm);padding:12px;cursor:pointer;transition:var(--t);text-align:center;}
.duration-card:hover,.duration-card.active{border-color:var(--brand);background:var(--brand-light);}
.duration-card i{font-size:1.3rem;color:var(--brand);margin-bottom:5px;}
.duration-card h5{font-size:.83rem;font-weight:700;margin:0 0 2px;}
.duration-card p{font-size:.68rem;color:var(--text-muted);margin:0;}
</style>
</head>
<body>
<?php?>

<div class="page-wrap">

    <!-- HERO -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-file-invoice me-2" style="opacity:.8"></i>Service Invoices</div>
            <div class="hero-sub">Create and manage GST service invoices</div>
        </div>
        <div class="hero-actions">
            <button class="btn-export excel" onclick="showExportModal()"><i class="fas fa-file-excel"></i> Export Excel</button>
            <a href="javascript:void(0)" class="btn-primary-c" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
                <i class="fas fa-plus-circle"></i> New Invoice
            </a>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if ($success_message): ?>
    <div class="alert-top success" id="successAlert"><i class="fas fa-check-circle"></i><?php echo safeHtml($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo safeHtml($error_message); ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-receipt"></i></div>
            <div><div class="stat-val purple"><?php echo number_format($total_invoices); ?></div><div class="stat-label">Invoices</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-indian-rupee-sign"></i></div>
            <div><div class="stat-val blue">₹<?php echo number_format($total_revenue, 0); ?></div><div class="stat-label">Total Revenue</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
            <div><div class="stat-val green">₹<?php echo number_format($total_paid, 0); ?></div><div class="stat-label">Collected</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="stat-val red">₹<?php echo number_format($total_outstanding, 0); ?></div><div class="stat-label">Outstanding</div></div>
        </div>
    </div>

    <!-- SEARCH -->
    <div class="search-bar">
        <div class="search-field">
            <i class="fas fa-search si"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search by invoice no., customer name, phone…" oninput="filterTable()">
        </div>
        <div class="search-count" id="tableCount"><?php echo $total_invoices; ?> records</div>
    </div>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-scroll">
            <table class="data-table" id="invoiceTable">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>By</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoiceBody">
                <?php if (empty($service_invoices)): ?>
                    <tr class="empty-row">
                        <td colspan="10">
                            <div class="empty-icon"><i class="fas fa-file-invoice"></i></div>
                            <div style="font-weight:600;margin-bottom:5px">No service invoices yet</div>
                            <div style="font-size:.8rem">Click <strong>New Invoice</strong> to create one.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($service_invoices as $inv):
                        $outstanding = $inv['total_amount'] - $inv['paid_amount'];
                        $status = ($inv['paid_amount'] <= 0) ? 'pending' : (($outstanding > 0.009) ? 'partial' : 'paid');
                        $initials = strtoupper(substr($inv['customer_name'],0,1) . (strpos($inv['customer_name'],' ')!==false ? substr($inv['customer_name'],strpos($inv['customer_name'],' ')+1,1) : ''));
                        $dateIST = (new DateTime($inv['created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'));
                        $invNum = $inv['invoice_no'] ?: 'INV-'.$inv['id'];
                        $searchVal = strtolower($invNum.' '.$inv['customer_name'].' '.($inv['customer_phone']??''));
                    ?>
                    <tr data-search="<?php echo safeHtml($searchVal); ?>">
                        <td><span class="inv-num"><?php echo safeHtml($invNum); ?></span></td>
                        <td style="font-size:.8rem;color:var(--text-muted)"><?php echo !empty($inv['invoice_date']) ? date('d M Y', strtotime($inv['invoice_date'])) : '—'; ?></td>
                        <td>
                            <div class="cust-cell">
                                <div class="cust-avatar"><?php echo $initials; ?></div>
                                <div>
                                    <div class="cust-name"><?php echo safeHtml($inv['customer_name']); ?></div>
                                    <?php if (!empty($inv['customer_phone'])): ?><div class="cust-phone"><i class="fas fa-phone" style="font-size:.6rem;margin-right:2px"></i><?php echo safeHtml($inv['customer_phone']); ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><div class="amount-cell">₹<?php echo number_format($inv['total_amount'],2); ?></div></td>
                        <td><div class="paid-cell">₹<?php echo number_format($inv['paid_amount'],2); ?></div></td>
                        <td><div class="outstanding-cell">₹<?php echo number_format($outstanding,2); ?></div></td>
                        <td>
                            <span class="status-badge <?php echo $status; ?>">
                                <i class="fas fa-circle" style="font-size:.45rem"></i>
                                <?php echo ucfirst($status); ?>
                            </span>
                        </td>
                        <td style="font-size:.78rem;color:var(--text-muted)"><?php echo $dateIST->format('d M Y'); ?></td>
                        <td>
                            <span class="creator-badge <?php echo $inv['created_by_name'] === 'Admin' ? 'admin' : 'staff'; ?>">
                                <i class="fas fa-<?php echo $inv['created_by_name'] === 'Admin' ? 'shield' : 'user'; ?>" style="font-size:.6rem"></i>
                                <?php echo safeHtml($inv['created_by_name']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-group">
                                <a href="service_invoice_print.php?id=<?php echo $inv['id']; ?>" target="_blank" class="act-btn print" data-tip="Print"><i class="fas fa-print"></i></a>
                                <a href="service_inv_view.php?id=<?php echo $inv['id']; ?>" class="act-btn view" data-tip="View"><i class="fas fa-eye"></i></a>
                                <button type="button" class="act-btn whatsapp" data-tip="WhatsApp" onclick="shareWhatsApp(<?php echo $inv['id']; ?>,'<?php echo safeHtml($invNum); ?>','<?php echo safeHtml($inv['customer_name']); ?>')"><i class="fab fa-whatsapp"></i></button>
                                <a href="edit_service_invoice.php?id=<?php echo $inv['id']; ?>" class="act-btn edit" data-tip="Edit"><i class="fas fa-pen"></i></a>
                                <button type="button" class="act-btn del" data-tip="Delete" onclick="confirmDelete(<?php echo $inv['id']; ?>,'<?php echo safeHtml($invNum); ?>')"><i class="fas fa-trash"></i></button>
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

<!-- ══ ADD INVOICE MODAL ══════════════════════════════════════════════════ -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-hdr">
        <div class="modal-title"><i class="fas fa-plus-circle me-2"></i>New Service Invoice</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addInvoiceForm" action="service_invoice.php" method="post" autocomplete="off" style="display:flex;flex-direction:column;min-height:0;">
        <div class="modal-body">
          <input type="hidden" name="services_json" id="services_json">

          <!-- Invoice Details -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-file-alt"></i> Invoice Details</div>
            <div class="grid-3">
              <div class="mfield">
                <label>Invoice No. <span class="req">*</span></label>
                <input type="text" name="invoice_no" id="invoice_no" value="<?php echo htmlspecialchars($next_auto_invoice_no); ?>" required readonly style="background:#f0f9ff; font-weight:700; color:var(--brand)">
                <span style="font-size:0.68rem; color:var(--brand); font-weight:600"><i class="fas fa-bolt me-1"></i>Auto-incremented sequence</span>
              </div>
              <div class="mfield"><label>Invoice Date</label><input type="date" name="invoice_date" id="invoice_date"></div>
              <div class="mfield"><label>Delivery Note</label><input type="text" name="delivery_note" placeholder="Enter delivery note"></div>
            </div>

            <div class="grid-3">
              <div class="mfield"><label>Mode / Terms of Payment</label><input type="text" name="payment_terms" placeholder="e.g. Net 30"></div>
              <div class="mfield"><label>Reference No.</label><input type="text" name="reference_no" placeholder="Reference number"></div>
              <div class="mfield"><label>Buyer's Order No.</label><input type="text" name="buyer_order_no" placeholder="Buyer order number"></div>
            </div>
            <div class="grid-4">
              <div class="mfield"><label>Dated</label><input type="date" name="dated"></div>
              <div class="mfield"><label>Dispatch Doc No.</label><input type="text" name="dispatch_doc_no" placeholder="Dispatch document no."></div>
              <div class="mfield"><label>Delivery Note Date</label><input type="date" name="delivery_note_date"></div>
              <div class="mfield"><label>Dispatched Through</label><input type="text" name="dispatched_through" placeholder="e.g. Air, Road"></div>
            </div>
            <div class="grid-2">
              <div class="mfield"><label>Destination</label><input type="text" name="destination" placeholder="Enter destination"></div>
              <div class="mfield"><label>Terms of Delivery</label><textarea name="terms_of_delivery" rows="2" placeholder="Terms of delivery…"></textarea></div>
            </div>
          </div>

          <!-- Customer Details -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-user-circle"></i> Customer Details</div>
            <div class="grid-2">
              <div class="mfield">
                <label>Select Customer</label>
                <select id="customer_id" name="customer_id">
                  <option value="">— Search & select —</option>
                  <?php foreach ($customers as $c): ?>
                  <option value="<?php echo $c['id']; ?>" data-name="<?php echo safeHtml($c['name']); ?>" data-phone="<?php echo safeHtml($c['phone']); ?>" data-address="<?php echo safeHtml($c['address']); ?>" data-state="<?php echo safeHtml($c['state']??''); ?>">
                    <?php echo safeHtml($c['name']); ?><?php if (!empty($c['phone'])): ?> — <?php echo safeHtml($c['phone']); ?><?php endif; ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mfield"><label>Customer Name <span class="req">*</span></label><input type="text" id="customer_name" name="customer_name" placeholder="Customer full name" required></div>
            </div>
            <div class="grid-2">
              <div class="mfield"><label>Phone</label><input type="text" id="customer_phone" name="customer_phone" placeholder="+919876543210"></div>
              <div class="mfield"><label>State (for GST)</label><input type="text" id="customer_state" name="customer_state" placeholder="e.g. Maharashtra"></div>
            </div>
            <div class="grid-2">
              <div class="mfield"><label>Billing Address</label><textarea id="customer_address" name="customer_address" rows="2" placeholder="Billing address…"></textarea></div>
              <div class="mfield"><label>Shipping Address</label><textarea id="shipping_address" name="shipping_address" rows="2" placeholder="Shipping address (if different)…"></textarea></div>
            </div>
          </div>

          <!-- Service Items -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-concierge-bell"></i> Service Items</div>
            <div id="service-list"></div>
            <button type="button" class="add-svc-btn" id="addServiceBtn"><i class="fas fa-plus-circle"></i> Add Another Service</button>

            <!-- Totals -->
            <div class="total-box" id="totalBox" style="margin-top:14px">
              <div class="tline"><span>Subtotal</span><span id="td_subtotal">₹0.00</span></div>
              <div class="tline"><span>Discount</span><span id="td_discount" style="color:var(--success)">– ₹0.00</span></div>
              <div class="tline"><span>Taxable Amount</span><span id="td_taxable">₹0.00</span></div>
              <div class="tline" id="td_gst_row" style="color:var(--info);font-weight:600"><span>GST</span><span id="td_gst">₹0.00</span></div>
              <div class="tline grand"><span>Grand Total</span><span id="td_total">₹0.00</span></div>
            </div>
          </div>

          <!-- Payment -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-credit-card"></i> Payment Received</div>
            <div class="payment-box">
              <div class="grid-2">
                <div class="mfield" style="margin-bottom:0"><label>Payment Method</label>
                  <select name="payment_method" id="payment_method">
                    <option value="">— Select —</option>
                    <option value="Cash">💵 Cash</option>
                    <option value="Card">💳 Card</option>
                    <option value="UPI">📱 UPI</option>
                    <option value="Bank Transfer">🏦 Bank Transfer</option>
                    <option value="Cheque">🏷️ Cheque</option>
                  </select>
                </div>
                <div class="mfield" style="margin-bottom:0"><label>Amount Received</label>
                  <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" placeholder="0.00">
                </div>
              </div>
              <div id="payHint" class="pay-hint"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_service_invoice" class="btn-primary-c" style="border:none;padding:10px 24px">
            <i class="fas fa-check-circle"></i> Create Invoice
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ══ DELETE CONFIRM MODAL ══════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-hdr" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
        <div class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="text-align:center;padding:28px">
        <div class="del-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h6 style="font-weight:700;margin-bottom:6px">Delete this invoice?</h6>
        <div style="font-size:.82rem;color:var(--danger);font-weight:700;margin-bottom:6px" id="delInvName"></div>
        <p style="color:var(--text-muted);font-size:.82rem">This action cannot be undone.</p>
        <form method="post" style="margin-top:18px;display:flex;gap:10px;justify-content:center">
          <input type="hidden" name="delete_invoice" id="deleteInvoiceId">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;padding:9px 20px;border-radius:8px;font-weight:700;cursor:pointer"><i class="fas fa-trash me-1"></i>Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- ══ EXPORT MODAL ══════════════════════════════════════════════════════ -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-hdr">
        <div class="modal-title"><i class="fas fa-file-excel me-2"></i>Export to Excel</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:14px">Select time period for export:</p>
        <div class="duration-grid">
          <div class="duration-card" onclick="selectDur(this,'7days')"><i class="fas fa-calendar-week"></i><h5>Last 7 Days</h5><p>Past week</p></div>
          <div class="duration-card" onclick="selectDur(this,'1month')"><i class="fas fa-calendar-alt"></i><h5>Last Month</h5><p>Past 30 days</p></div>
          <div class="duration-card" onclick="selectDur(this,'6months')"><i class="fas fa-calendar"></i><h5>Last 6 Months</h5><p>Past 6 months</p></div>
          <div class="duration-card" onclick="selectDur(this,'thisyear')"><i class="fas fa-calendar-check"></i><h5>This Year</h5><p>From 1 April</p></div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:13px">
          <div style="font-size:.73rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:9px">Custom Range:</div>
          <div class="grid-2">
            <div class="mfield" style="margin:0"><label>From</label><input type="date" id="exportFrom"></div>
            <div class="mfield" style="margin:0"><label>To</label><input type="date" id="exportTo"></div>
          </div>
          <button type="button" onclick="applyCustomExport()" style="margin-top:8px;background:var(--brand-light);color:var(--brand);border:1px solid rgba(2,132,199,.2);padding:6px 12px;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer"><i class="fas fa-check me-1"></i>Apply Custom</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="exportBtn" class="btn-primary-c" style="border:none;padding:9px 20px" disabled onclick="doExport()"><i class="fas fa-download"></i> Export</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const companyState = '<?php echo strtolower($company_state ?? ""); ?>';
let svcCount = 0;
let selectedExportDuration = '';

/* ── SERVICE ROW HTML ─────────────────────────────── */
function makeServiceRow(idx, data) {
    const d = data || {};
    const gstOpts = [0,5,12,18,28].map(r => `<option value="${r}" ${(d.gst_rate==r)?'selected':''}>${r}% GST</option>`).join('');
    return `
    <div class="service-row" id="srow_${idx}">
        <div class="service-row-grid">
            <div class="mfield" style="margin:0">
                <label>Service Name <span class="req">*</span></label>
                <input type="text" class="svc-name" value="${d.service_name||''}" placeholder="Enter service name" required>
            </div>
            <div class="mfield" style="margin:0">
                <label>SAC Code</label>
                <input type="text" class="svc-sac" value="${d.sac_code||''}" placeholder="e.g. 998314">
            </div>
            <div class="mfield" style="margin:0">
                <label>Quantity</label>
                <input type="number" class="svc-qty" min="0" step="0.01" value="${d.quantity||1}" placeholder="1">
            </div>
            <div class="mfield" style="margin:0">
                <label>Unit</label>
                <input type="text" class="svc-unit" value="${d.unit||''}" placeholder="e.g. Hrs">
            </div>
            <div class="mfield" style="margin:0">
                <label>Rate</label>
                <input type="number" class="svc-rate" min="0" step="0.01" value="${d.rate||''}" placeholder="0.00">
            </div>
            <div class="mfield" style="margin:0">
                <label>Amount <span class="req">*</span></label>
                <input type="number" class="svc-amount" min="0" step="0.01" value="${d.amount||''}" placeholder="0.00" required>
            </div>
            <div class="mfield" style="margin:0">
                <label>GST Rate <span style="color:var(--brand);font-size:.65rem;font-weight:800">(Select)</span></label>
                <select class="svc-gst">${gstOpts}</select>
                <div class="gst-badge-display" id="gstbadge_${idx}">
                    <i class="fas fa-percent" style="font-size:.6rem"></i> ${d.gst_rate||0}% GST
                </div>
            </div>
            <div style="display:flex;align-items:flex-end;padding-bottom:18px">
                <button type="button" class="remove-svc-btn" onclick="removeSvcRow(${idx})"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <div class="mfield" style="margin-top:10px;margin-bottom:0">
            <label>Service Description</label>
            <textarea class="svc-desc" rows="2" placeholder="Optional description…">${d.service_description||''}</textarea>
        </div>
    </div>`;
}

function addSvcRow(data) {
    const idx = svcCount++;
    $('#service-list').append(makeServiceRow(idx, data));
    $(`#srow_${idx} .svc-gst`).on('change', function(){
        $(`#gstbadge_${idx}`).html(`<i class="fas fa-percent" style="font-size:.6rem"></i> ${$(this).val()}% GST`);
        calcTotal();
    });
    $(`#srow_${idx} .svc-qty, #srow_${idx} .svc-rate`).on('input', function(){
        const row = $(this).closest('.service-row');
        const qty = parseFloat(row.find('.svc-qty').val())||0;
        const rate = parseFloat(row.find('.svc-rate').val())||0;
        if (qty > 0 && rate > 0) row.find('.svc-amount').val((qty*rate).toFixed(2));
        calcTotal();
    });
    $(`#srow_${idx} .svc-amount`).on('input', calcTotal);
    calcTotal();
}

function removeSvcRow(idx) {
    if ($('.service-row').length <= 1) { alert('At least one service is required.'); return; }
    $(`#srow_${idx}`).remove();
    calcTotal();
}

/* ── TOTAL CALC ───────────────────────────────────── */
function calcTotal() {
    let subtotal = 0, gstTotal = 0;
    const custState = $('#customer_state').val().trim().toLowerCase();
    const discType  = 'amount'; // per-service, applied globally later if needed
    const discVal   = 0;

    $('.service-row').each(function(){
        const amount  = parseFloat($(this).find('.svc-amount').val())||0;
        const gstRate = parseFloat($(this).find('.svc-gst').val())||0;
        subtotal += amount;
        if (custState && companyState) {
            gstTotal += amount * (gstRate / 100);
        }
    });

    const discount  = 0;
    const taxable   = subtotal - discount;
    const grandTotal = taxable + gstTotal;

    $('#td_subtotal').text('₹' + subtotal.toFixed(2));
    $('#td_discount').text('– ₹' + discount.toFixed(2));
    $('#td_taxable').text('₹' + taxable.toFixed(2));
    $('#td_gst').text('₹' + gstTotal.toFixed(2));
    $('#td_total').text('₹' + grandTotal.toFixed(2));
    gstTotal > 0 ? $('#td_gst_row').show() : $('#td_gst_row').hide();

    // Payment hint
    const paid = parseFloat($('#paid_amount').val()||0);
    const hint = $('#payHint');
    hint.show().removeClass('full partial none');
    if (grandTotal <= 0) { hint.hide(); return grandTotal; }
    const due = grandTotal - paid;
    if (paid <= 0) hint.addClass('none').html('<i class="fas fa-clock me-1"></i>No payment received — invoice will be <strong>Pending</strong>');
    else if (due > 0.005) hint.addClass('partial').html(`<i class="fas fa-circle-half-stroke me-1"></i>Due ₹${due.toFixed(2)} — invoice will be <strong>Partial</strong>`);
    else hint.addClass('full').html('<i class="fas fa-check-circle me-1"></i>Fully paid — invoice will be <strong>Paid</strong> ✓');

    return grandTotal;
}

/* ── INIT ─────────────────────────────────────────── */
$(function(){
    addSvcRow(null); // Default first row

    $('#addServiceBtn').on('click', function(){ addSvcRow(null); });

    $('#customer_id').on('change', function(){
        const o = $(this).find('option:selected');
        if (o.val()) {
            $('#customer_name').val(o.data('name')||'');
            $('#customer_phone').val(o.data('phone')||'');
            $('#customer_address').val(o.data('address')||'');
            $('#shipping_address').val(o.data('address')||'');
            $('#customer_state').val(o.data('state')||'');
            calcTotal();
        }
    });

    $('#customer_state, #paid_amount').on('input', calcTotal);

    // Set today's date by default
    const today = new Date().toISOString().split('T')[0];
    $('#invoice_date').val(today);

    // Reset modal
    $('#addInvoiceModal').on('hidden.bs.modal', function(){
        $('#addInvoiceForm')[0].reset();
        $('#service-list').empty();
        svcCount = 0;
        addSvcRow(null);
        $('#invoice_date').val(today);
        $('#payHint').hide();
        calcTotal();
    });

    // Form submit
    $('#addInvoiceForm').on('submit', function(e){
        const services = [];
        let valid = true;
        $('.service-row').each(function(){
            const name   = $(this).find('.svc-name').val();
            const amount = $(this).find('.svc-amount').val();
            if (!name || !amount) { valid = false; return; }
            services.push({
                service_name:        name,
                service_description: $(this).find('.svc-desc').val(),
                sac_code:            $(this).find('.svc-sac').val(),
                quantity:            $(this).find('.svc-qty').val(),
                unit:                $(this).find('.svc-unit').val(),
                rate:                $(this).find('.svc-rate').val(),
                amount:              amount,
                gst_rate:            $(this).find('.svc-gst').val(),
                discount_type:       'amount',
                discount:            0,
                customer_state:      $('#customer_state').val()
            });
        });
        if (!valid || !services.length) { e.preventDefault(); alert('Please fill all required service fields.'); return; }
        if (!$('#customer_name').val()) { e.preventDefault(); alert('Customer name is required.'); return; }
        const total = calcTotal();
        const paid  = parseFloat($('#paid_amount').val()||0);
        if (paid > total + 0.01) { e.preventDefault(); alert(`Paid amount (₹${paid.toFixed(2)}) cannot exceed total (₹${total.toFixed(2)}).`); return; }
        $('#services_json').val(JSON.stringify(services));
    });

    // Auto-dismiss success
    const sa = document.getElementById('successAlert');
    if (sa) setTimeout(()=>{ sa.style.transition='opacity .5s'; sa.style.opacity='0'; setTimeout(()=>sa.remove(),500); }, 4000);
});

/* ── SEARCH ───────────────────────────────────────── */
function filterTable() {
    const q = (document.getElementById('searchInput').value||'').toLowerCase();
    let vis = 0;
    document.querySelectorAll('#invoiceBody tr[data-search]').forEach(tr => {
        const show = !q || tr.dataset.search.includes(q);
        tr.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    document.getElementById('tableCount').textContent = vis + ' records';
}

/* ── DELETE ───────────────────────────────────────── */
function confirmDelete(id, name) {
    document.getElementById('deleteInvoiceId').value = id;
    document.getElementById('delInvName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

/* ── WHATSAPP ─────────────────────────────────────── */
function shareWhatsApp(id, invoiceNo, customerName) {
    const link = window.location.origin + window.location.pathname.replace('service_invoice.php','') + 'service_invoice_print.php?id=' + id;
    const msg  = `Dear ${customerName},\n\nYour invoice ${invoiceNo} is ready.\nView: ${link}\n\nThank you!`;
    window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
}

/* ── EXPORT ───────────────────────────────────────── */
function showExportModal() { new bootstrap.Modal(document.getElementById('exportModal')).show(); }
function selectDur(el, dur) {
    document.querySelectorAll('.duration-card').forEach(c=>c.classList.remove('active'));
    el.classList.add('active');
    selectedExportDuration = dur;
    document.getElementById('exportBtn').disabled = false;
}
function applyCustomExport() {
    const f = document.getElementById('exportFrom').value;
    const t = document.getElementById('exportTo').value;
    if (!f||!t) { alert('Select both dates.'); return; }
    if (new Date(f)>new Date(t)) { alert('Start date cannot be after end date.'); return; }
    document.querySelectorAll('.duration-card').forEach(c=>c.classList.remove('active'));
    selectedExportDuration = 'custom';
    document.getElementById('exportBtn').disabled = false;
}
function doExport() {
    const form = document.createElement('form');
    form.method = 'POST'; form.action = 'service_invoice.php';
    const addField = (n,v) => { const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    addField('export_excel','1');
    addField('date_range', selectedExportDuration);
    if (selectedExportDuration === 'custom') {
        addField('start_date', document.getElementById('exportFrom').value);
        addField('end_date',   document.getElementById('exportTo').value);
    }
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    document.body.appendChild(form); form.submit();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>