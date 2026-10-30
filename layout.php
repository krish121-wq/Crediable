<?php
// ── SESSION ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$restricted_pages = [
    'dashboard.php','company-details.php','master_list.php','fetch_master_list.php','save-product.php',
    'check-product-uniqueness.php','accounting.php','inventory.php','categories-list.php',
    'sub-categories-list.php','brand-list.php','unit-list.php','product-list.php','stock_adjustment.php',
    'expire-product.php','low-stock.php','create-user.php','manage-stock.php',
    'sales.php','sale_return.php','save_invoice.php','purchase-stock.php',
    'service_invoice.php','service_invoice_outstanding.php','received_payments_history.php',
    'customers.php','expense_management.php','suppliers.php','trial_balance.php',
    'cash_bill_list.php','create_cash_bill.php','cashbill_outstanding.php','received_payments.php',
    'payment_history.php','cash_payment_history.php','editcash_bill.php','profit_loss_report.php','payment_out.php',
    'Credit&Debit.php','general-ledger.php','receivables-payables.php',
    'customer_ledger.php','view_quotations.php','add_quotation.php','whatsapp_logs.php',
    'bank_reconciliation.php','journal_entries.php','gstr1.php','gstr2.php',
    'gstr3.php','auto_bank_reconciliation.php','cheque_printing.php','profile.php',
    'e_payment_support.php','bank_ledger_management.php','salary_management.php',
    'purchase_entry.php','debit_note.php','credit_note.php','delivery_challan.php',
    'purchase_return.php','reports.php'
];

$current_page = basename($_SERVER['PHP_SELF']);
$is_restricted = in_array($current_page, $restricted_pages);

if ($is_restricted && !isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_type     = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)($_SESSION['user_id'] ?? 0);

if ($user_type === 'staff' && !isset($_SESSION['created_by']) && isset($conn)) {
    $staff_id = $_SESSION['user_id'];
    $s = $conn->prepare("SELECT created_by FROM create_users WHERE id = ?");
    $s->bind_param("i", $staff_id); $s->execute();
    $r = $s->get_result();
    if ($r->num_rows > 0) { $_SESSION['created_by'] = $r->fetch_assoc()['created_by']; }
    $s->close();
}
if ($user_type === 'staff' && isset($_SESSION['created_by'])) {
    $created_by_id = (int)$_SESSION['created_by'];
}
if ($user_type === 'staff' && file_exists('staff_permissions_helper.php')) {
    require_once 'staff_permissions_helper.php';
}

// ── HELPER: active class ──────────────────────────────────────────────────────
function isActive($pages) {
    global $current_page;
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($current_page, $pages) ? 'active' : '';
}
function isOpen($pages) {
    global $current_page;
    $pages = is_array($pages) ? $pages : [$pages];
    return in_array($current_page, $pages) ? 'open' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Credible — <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Business Suite'; ?></title>
<link rel="icon" type="image/png" href="images/favicon.png">
<link rel="apple-touch-icon" href="images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
/* ════════════════════════════════════════════════════════════════════════════
   CREDIBLE — LAYOUT SYSTEM
   Strategy:
   · Navbar  : fixed top, height 62px, full width, z-index 1040
   · Sidebar : fixed left, top 62px, width 260px, z-index 1030
   · Content : margin-left 260px, margin-top 62px  (desktop)
               margin-left 0, margin-top 62px       (mobile – sidebar overlays)
   · Overlay : covers content when sidebar open on mobile, z-index 1025
   NO bootstrap navbar-collapse used — all layout is pure CSS/JS to avoid
   the conflict where page content shifts when hamburger fires.
   ════════════════════════════════════════════════════════════════════════════ */

/* ── TOKENS ── */
:root {
    --brand:        #0284c7;
    --brand-dark:   #0b1220;
    --brand-mid:    #0ea5e9;
    --brand-light:  #e0f2fe;
    --brand-xlight: #f0f9ff;
    --gold:         #0369a1;
    --white:        #ffffff;
    --bg:           #f4f8fb;
    --card:         #ffffff;
    --border:       #e2e8f0;
    --text:         #0b1220;
    --text-mid:     #475569;
    --text-muted:   #94a3b8;
    --success:      #16a34a;
    --danger:       #dc2626;
    --nav-h:        46px;
    --sb-w:         262px;
    --sb-w-collapsed: 64px;
    --radius:       10px;
    --shadow-nav:   0 2px 16px rgba(2,132,199,.18);
    --shadow-sb:    4px 0 24px rgba(2,132,199,.10);
    --transition:   0.28s cubic-bezier(.4,0,.2,1);
}

/* ── RESET ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    overflow-x: hidden;
    /* NO padding-top here — handled by page-wrap margin */
}

/* ════════════════════════════════════════════════════════════════════════════
   NAVBAR
   ════════════════════════════════════════════════════════════════════════════ */
#gb-navbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--nav-h);
    z-index: 1040;
    background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
    box-shadow: var(--shadow-nav);
    display: flex;
    align-items: center;
    padding: 0 20px;
    gap: 14px;
}

/* Hamburger */
#gb-toggle {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: none;
    background: rgba(255,255,255,.12);
    color: var(--white);
    font-size: .92rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background var(--transition);
}
#gb-toggle:hover { background: rgba(255,255,255,.22); }

/* Logo pill */
.gb-logo-pill {
    background: var(--white);
    border-radius: 8px;
    padding: 3px 10px 3px 8px;
    display: flex; align-items: center;
    gap: 8px;
    box-shadow: 0 1px 6px rgba(0,0,0,.10);
    text-decoration: none;
    flex-shrink: 0;
}
.gb-logo-pill img {
    height: 26px; width: auto; object-fit: contain; display: block;
}
.gb-logo-text {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 0.95rem;
    color: var(--brand);
    letter-spacing: -0.2px;
    line-height: 1;
}

/* Search */
.gb-search {
    flex: 1;
    max-width: 360px;
    position: relative;
}
.gb-search input {
    width: 100%;
    height: 38px;
    border-radius: 50px;
    border: 1.5px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.14);
    color: var(--white);
    font-size: .87rem;
    padding: 0 16px 0 40px;
    outline: none;
    transition: border-color var(--transition), background var(--transition);
}
.gb-search input::placeholder { color: rgba(255,255,255,.6); }
.gb-search input:focus {
    border-color: rgba(255,255,255,.7);
    background: rgba(255,255,255,.22);
}
.gb-search .si {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,.6); font-size: .8rem; pointer-events: none;
}

/* Search dropdown */
#gb-search-results {
    position: absolute;
    top: calc(100% + 6px); left: 0; right: 0;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: 0 8px 28px rgba(2,132,199,.18);
    border: 1px solid var(--border);
    overflow: hidden;
    display: none;
    z-index: 9999;
    max-height: 320px;
    overflow-y: auto;
}
#gb-search-results.show { display: block; }
.sr-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    font-size: .85rem; color: var(--text);
    text-decoration: none;
    transition: background .15s;
}
.sr-item:hover, .sr-item.focused { background: var(--brand-light); color: var(--brand); }
.sr-item i { color: var(--brand); width: 18px; font-size: .9rem; }
.sr-empty { padding: 16px; text-align: center; font-size: .85rem; color: var(--text-muted); }

/* Spacer */
.gb-nav-spacer { flex: 1; }

/* Nav actions */
.gb-nav-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.gb-nav-btn {
    width: 38px; height: 38px; border-radius: 10px;
    border: none; background: rgba(255,255,255,.12);
    color: var(--white); font-size: .9rem;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    text-decoration: none; transition: background var(--transition);
    position: relative;
}
.gb-nav-btn:hover { background: rgba(255,255,255,.22); color: var(--white); }

/* User avatar pill */
.gb-user-pill {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.12);
    border-radius: 50px; padding: 3px 10px 3px 4px;
    cursor: pointer; border: none;
    transition: background var(--transition); flex-shrink: 0;
}
.gb-user-pill:hover { background: rgba(255,255,255,.22); }
.gb-user-avatar {
    width: 25px; height: 25px; border-radius: 50%;
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: var(--white); font-weight: 700; font-size: .75rem;
    display: flex; align-items: center; justify-content: center;
}
.gb-user-name { font-size: .75rem; color: var(--white); font-weight: 600; }

/* User dropdown */
.gb-user-dropdown {
    position: absolute; top: calc(var(--nav-h) - 4px); right: 20px;
    background: var(--white); border-radius: var(--radius);
    box-shadow: 0 8px 28px rgba(2,132,199,.18);
    border: 1px solid var(--border);
    min-width: 200px; z-index: 9999;
    overflow: hidden;
    display: none;
}
.gb-user-dropdown.show { display: block; }
.gb-ud-header {
    padding: 14px 16px;
    background: linear-gradient(135deg, var(--brand-light), #bae6fd);
    border-bottom: 1px solid var(--border);
}
.gb-ud-name { font-weight: 700; font-size: .9rem; color: var(--brand); }
.gb-ud-role { font-size: .72rem; color: var(--text-muted); margin-top: 2px; text-transform: capitalize; }
.gb-ud-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px; font-size: .85rem; color: var(--text);
    text-decoration: none; transition: background .15s;
}
.gb-ud-item:hover { background: var(--brand-light); color: var(--brand); }
.gb-ud-item i { width: 16px; color: var(--text-muted); }
.gb-ud-item:hover i { color: var(--brand); }
.gb-ud-item.danger { color: var(--danger); }
.gb-ud-item.danger i { color: var(--danger); }
.gb-ud-divider { height: 1px; background: var(--border); margin: 4px 0; }

/* ════════════════════════════════════════════════════════════════════════════
   SIDEBAR
   ════════════════════════════════════════════════════════════════════════════ */
#gb-sidebar {
    position: fixed;
    top: var(--nav-h);
    left: 0;
    width: var(--sb-w);
    height: calc(100vh - var(--nav-h));
    background: var(--white);
    box-shadow: var(--shadow-sb);
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1030;
    transition: transform var(--transition);
    display: flex;
    flex-direction: column;
}
#gb-sidebar::-webkit-scrollbar { width: 4px; }
#gb-sidebar::-webkit-scrollbar-track { background: transparent; }
#gb-sidebar::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
#gb-sidebar::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

/* Desktop: visible by default */
/* Mobile: hidden by default, slides in */

/* Sidebar header */
.sb-header {
    padding: 12px 14px 12px 16px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    position: relative;
}
.sb-brand-info {
    flex: 1;
    overflow: hidden;
    transition: opacity var(--transition);
}
.sb-company {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .82rem; font-weight: 700; color: var(--brand);
    display: flex; align-items: center; gap: 7px;
    white-space: nowrap;
}
.sb-company i { font-size: .75rem; }
.sb-tagline { font-size: .70rem; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }

/* Sidebar collapse toggle arrow button */
.sb-collapse-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-mid);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: .75rem;
    transition: background var(--transition), color var(--transition), transform var(--transition);
    flex-shrink: 0;
}
.sb-collapse-btn:hover {
    background: var(--brand-light);
    color: var(--brand);
    border-color: #bae6fd;
}
.sb-collapse-btn i {
    transition: transform var(--transition);
}

/* Nav list */
.sb-nav { padding: 10px 0; flex: 1; }

/* Section label */
.sb-section-label {
    font-size: .62rem; font-weight: 800; letter-spacing: 1.2px;
    text-transform: uppercase; color: var(--text-muted);
    padding: 12px 20px 5px;
    transition: opacity var(--transition);
}

/* Nav link */
.sb-link {
    display: flex; align-items: center; gap: 11px;
    padding: 9px 16px 9px 18px;
    margin: 1px 8px;
    border-radius: var(--radius);
    font-size: .875rem; font-weight: 500; color: var(--text-mid);
    text-decoration: none;
    cursor: pointer;
    transition: background var(--transition), color var(--transition), width var(--transition), padding var(--transition);
    white-space: nowrap;
    position: relative;
    user-select: none;
    border: none; background: none; width: calc(100% - 16px); text-align: left;
}
.sb-link i.sb-icon {
    width: 20px; text-align: center; font-size: .9rem;
    color: var(--text-muted); flex-shrink: 0;
    transition: color var(--transition);
}
.sb-link .sb-label { flex: 1; }
.sb-link .sb-arrow {
    font-size: .65rem; color: var(--text-muted);
    transition: transform var(--transition), color var(--transition);
    flex-shrink: 0;
}
.sb-link:hover {
    background: var(--brand-light);
    color: var(--brand);
}
.sb-link:hover i.sb-icon, .sb-link:hover .sb-arrow { color: var(--brand); }

/* Active state */
.sb-link.active {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: var(--white) !important;
    box-shadow: 0 3px 12px rgba(2,132,199,.25);
}
.sb-link.active i.sb-icon,
.sb-link.active .sb-arrow { color: rgba(255,255,255,.8) !important; }

/* Parent active (has open child) */
.sb-link.parent-active {
    background: var(--brand-light);
    color: var(--brand);
}
.sb-link.parent-active i.sb-icon { color: var(--brand); }
.sb-link.parent-active .sb-arrow { color: var(--brand); }

/* Arrow rotation when open */
.sb-link[aria-expanded="true"] .sb-arrow { transform: rotate(90deg); }

/* Sub-menu */
.sb-sub {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.32s cubic-bezier(.4,0,.2,1);
}
.sb-sub.open { max-height: 600px; }
.sb-sub-inner { padding: 2px 0 4px 14px; }

.sb-sub-link {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 14px 7px 20px;
    margin: 1px 8px;
    border-radius: 8px;
    font-size: .83rem; font-weight: 500; color: var(--text-muted);
    text-decoration: none;
    transition: background var(--transition), color var(--transition);
    position: relative;
}
.sb-sub-link::before {
    content: '';
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--border);
    flex-shrink: 0;
    transition: background var(--transition);
}
.sb-sub-link:hover {
    background: var(--brand-light);
    color: var(--brand);
}
.sb-sub-link:hover::before { background: var(--brand); }
.sb-sub-link.active {
    background: var(--brand-light);
    color: var(--brand);
    font-weight: 600;
}
.sb-sub-link.active::before { background: var(--brand); }

/* Sidebar footer */
.sb-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
    transition: opacity var(--transition);
}
.sb-version {
    font-size: .7rem; color: var(--text-muted);
    display: flex; align-items: center; justify-content: space-between;
}

/* ════════════════════════════════════════════════════════════════════════════
   COLLAPSED SIDEBAR (DESKTOP)
   ════════════════════════════════════════════════════════════════════════════ */
@media (min-width: 993px) {
    #gb-sidebar {
        transition: width var(--transition), transform var(--transition);
    }
    #gb-sidebar.collapsed {
        width: var(--sb-w-collapsed);
    }
    #gb-sidebar.collapsed .sb-brand-info,
    #gb-sidebar.collapsed .sb-tagline,
    #gb-sidebar.collapsed .sb-label,
    #gb-sidebar.collapsed .sb-arrow,
    #gb-sidebar.collapsed .sb-section-label,
    #gb-sidebar.collapsed .sb-sub,
    #gb-sidebar.collapsed .sb-footer {
        display: none !important;
    }
    #gb-sidebar.collapsed .sb-header {
        padding: 10px;
        justify-content: center;
    }
    #gb-sidebar.collapsed .sb-collapse-btn {
        margin: 0 auto;
    }
    #gb-sidebar.collapsed .sb-collapse-btn i {
        transform: rotate(180deg);
    }
    #gb-sidebar.collapsed .sb-link {
        width: 44px;
        height: 40px;
        margin: 4px auto;
        padding: 0;
        justify-content: center;
    }
    #gb-sidebar.collapsed .sb-link i.sb-icon {
        width: 100%;
        font-size: 1.05rem;
    }
    body.sb-is-collapsed .page-wrap,
    body.sb-is-collapsed .gb-page-wrap {
        margin-left: var(--sb-w-collapsed) !important;
    }

    /* Floating Tooltip for collapsed icons */
    #gb-sidebar.collapsed .sb-link[data-tooltip]::after {
        content: attr(data-tooltip);
        position: absolute;
        left: calc(100% + 12px);
        top: 50%;
        transform: translateY(-50%);
        background: var(--brand-dark);
        color: var(--white);
        font-size: .78rem;
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 6px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity .18s ease;
        box-shadow: 0 4px 14px rgba(0,0,0,.2);
        z-index: 9999;
    }
    #gb-sidebar.collapsed .sb-link[data-tooltip]:hover::after {
        opacity: 1;
        visibility: visible;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   OVERLAY (mobile)
   ════════════════════════════════════════════════════════════════════════════ */
#gb-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(11,18,32,.45);
    z-index: 1025;
    backdrop-filter: blur(2px);
}
#gb-overlay.show { display: block; }

/* ════════════════════════════════════════════════════════════════════════════
   PAGE CONTENT WRAPPER
   Pages that include layout.php should wrap their content in .gb-page-wrap
   or use the auto-applied body padding below.
   ════════════════════════════════════════════════════════════════════════════ */
.page-wrap,
.gb-page-wrap {
    margin-top: var(--nav-h);
    margin-left: var(--sb-w);
    padding: 28px 24px 60px;
    min-height: calc(100vh - var(--nav-h));
    transition: margin-left var(--transition);
}

/* ════════════════════════════════════════════════════════════════════════════
   RESPONSIVE
   ════════════════════════════════════════════════════════════════════════════ */

/* ── TABLET (≤ 992px) — sidebar overlays, hidden by default ── */
@media (max-width: 992px) {
    #gb-sidebar {
        transform: translateX(-100%);
        box-shadow: none;
    }
    #gb-sidebar.sidebar-open {
        transform: translateX(0);
        box-shadow: var(--shadow-sb);
    }
    .page-wrap,
    .gb-page-wrap {
        margin-left: 0;
        padding: 20px 16px 50px;
    }
    .gb-search { max-width: 240px; }
    .gb-user-name { display: none; }
    #gb-toggle { display: block !important; }
}

/* ── MOBILE (≤ 640px) ── */
@media (max-width: 640px) {
    .gb-search { max-width: 160px; }
    .gb-logo-pill img { height: 30px; }
    .page-wrap,
    .gb-page-wrap { padding: 16px 12px 40px; }
}

/* ── VERY SMALL (≤ 400px) ── */
@media (max-width: 400px) {
    .gb-search { display: none; }
    #gb-navbar { padding: 0 12px; gap: 10px; }
}

/* ════════════════════════════════════════════════════════════════════════════
   LOGOUT MODAL
   ════════════════════════════════════════════════════════════════════════════ */
#logoutModal .modal-header {
    background: linear-gradient(135deg, var(--brand-dark), var(--brand));
    color: var(--white); border: none;
}
#logoutModal .modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; }
#logoutModal .btn-close { filter: invert(1); }
#logoutModal .btn-logout-confirm {
    background: linear-gradient(135deg, var(--brand), var(--brand-dark));
    color: var(--white); border: none;
    padding: 8px 22px; border-radius: 8px; font-weight: 600;
    transition: opacity .2s;
}
#logoutModal .btn-logout-confirm:hover { opacity: .88; }
</style>
</head>
<body>

<!-- ════════════ NAVBAR ════════════ -->
<nav id="gb-navbar">



    <!-- Mobile Toggle -->
    <button id="gb-toggle" aria-label="Toggle Sidebar" style="display:none; background:transparent; border:none; color:var(--white); font-size:1.4rem; cursor:pointer; padding:5px; margin-right:10px;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Logo -->
    <a class="gb-logo-pill" href="dashboard.php" aria-label="Credible Home">
        <img src="images/logo1.png" alt="Credible">
        <span class="gb-logo-text">Credible</span>
    </a>



    <div class="gb-nav-spacer"></div>

    <!-- Actions -->
    <div class="gb-nav-actions">
        <!-- User pill -->
        <button class="gb-user-pill" id="gb-user-toggle" aria-expanded="false" aria-haspopup="true">
            <div class="gb-user-avatar">
                <?php
                $uname = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'U';
                echo htmlspecialchars(strtoupper(substr($uname, 0, 1)));
                ?>
            </div>
            <span class="gb-user-name">
                <?php echo htmlspecialchars(explode(' ', $uname)[0]); ?>
            </span>
            <i class="fas fa-chevron-down" style="font-size:.6rem;color:rgba(255,255,255,.7);margin-left:2px"></i>
        </button>
    </div>
</nav>

<!-- User Dropdown (rendered outside navbar to avoid stacking context issues) -->
<div class="gb-user-dropdown" id="gb-user-dropdown" role="menu">
    <div class="gb-ud-header">
        <div class="gb-ud-name"><?php echo htmlspecialchars($uname); ?></div>
        <div class="gb-ud-role"><?php echo htmlspecialchars($user_type); ?></div>
    </div>
    <a href="profile.php" class="gb-ud-item" role="menuitem">
        <i class="fas fa-user-circle"></i> Profile Settings
    </a>
    <a href="company-details.php" class="gb-ud-item" role="menuitem">
        <i class="fas fa-building"></i> Company Details
    </a>
    <div class="gb-ud-divider"></div>
    <a href="#" class="gb-ud-item danger" role="menuitem"
       onclick="event.preventDefault(); document.getElementById('logoutModal').style.display='flex'">
        <i class="fas fa-sign-out-alt"></i> Sign Out
    </a>
</div>

<!-- ════════════ OVERLAY ════════════ -->
<div id="gb-overlay" aria-hidden="true"></div>

<!-- ════════════ SIDEBAR ════════════ -->
<aside id="gb-sidebar" role="navigation" aria-label="Main navigation">

    <div class="sb-header">
        <div class="sb-brand-info">
            <div class="sb-company"><i class="fas fa-store"></i> Credible</div>
            <div class="sb-tagline">Business Management Suite</div>
        </div>
        <button type="button" class="sb-collapse-btn" id="gb-collapse-toggle" title="Collapse / Expand Sidebar" aria-label="Toggle sidebar width">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sb-nav" id="sb-nav">

        <!-- MAIN -->
        <?php $mainOpen = isOpen(['dashboard.php']); ?>
        <button class="sb-link <?= $mainOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-main" aria-expanded="<?= $mainOpen ? 'true' : 'false' ?>">
            <i class="fas fa-home sb-icon"></i>
            <span class="sb-label">Main</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $mainOpen ? 'open' : '' ?>" id="sb-sb-main">
            <div class="sb-sub-inner">
                <a href="dashboard.php" class="sb-sub-link <?= isActive('dashboard.php') ?>">Dashboard</a>
            </div>
        </div>

        <!-- PARTIES -->
        <?php $partiesOpen = isOpen(['customers.php','suppliers.php','customer_ledger.php']); ?>
        <button class="sb-link <?= $partiesOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-parties" aria-expanded="<?= $partiesOpen ? 'true' : 'false' ?>">
            <i class="fas fa-users sb-icon"></i>
            <span class="sb-label">Parties</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $partiesOpen ? 'open' : '' ?>" id="sb-sb-parties">
            <div class="sb-sub-inner">
                <a href="customers.php"       class="sb-sub-link <?= isActive('customers.php') ?>">Customers</a>
                <a href="suppliers.php"       class="sb-sub-link <?= isActive('suppliers.php') ?>">Suppliers</a>
                <a href="customer_ledger.php" class="sb-sub-link <?= isActive('customer_ledger.php') ?>">Customer Ledger</a>
            </div>
        </div>

        <?php $itemOpen = isOpen(['master_list.php','fetch_master_list.php','manage-stock.php','stock_adjustment.php']); ?>
        <button class="sb-link <?= $itemOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="item-masters" aria-expanded="<?= $itemOpen ? 'true' : 'false' ?>">
            <i class="fas fa-warehouse sb-icon"></i>
            <span class="sb-label">Item Masters</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $itemOpen ? 'open' : '' ?>" id="sb-item-masters">
            <div class="sb-sub-inner">
                <a href="master_list.php"      class="sb-sub-link <?= isActive('master_list.php') ?>">Add Product</a>
                <a href="fetch_master_list.php" class="sb-sub-link <?= isActive('fetch_master_list.php') ?>">Product List</a>
                <a href="manage-stock.php"      class="sb-sub-link <?= isActive('manage-stock.php') ?>">Manage Stock</a>
                <a href="stock_adjustment.php"  class="sb-sub-link <?= isActive('stock_adjustment.php') ?>">Adjust Stock</a>
            </div>
        </div>

        <!-- SALES -->
        <?php $salesOpen = isOpen(['sales.php','sale_return.php','cash_bill_list.php','service_invoice.php','view_quotations.php','add_quotation.php','received_payments.php','view_proforma.php','add_proforma.php']); ?>
        <button class="sb-link <?= $salesOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-sales" aria-expanded="<?= $salesOpen ? 'true' : 'false' ?>">
            <i class="fas fa-shopping-bag sb-icon"></i>
            <span class="sb-label">Sales</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $salesOpen ? 'open' : '' ?>" id="sb-sb-sales">
            <div class="sb-sub-inner">
                <a href="sales.php"            class="sb-sub-link <?= isActive('sales.php') ?>">Add / View Sales</a>
                <a href="sale_return.php"      class="sb-sub-link <?= isActive('sale_return.php') ?>">Sale Return</a>
                <a href="cash_bill_list.php"   class="sb-sub-link <?= isActive('cash_bill_list.php') ?>">Cash Bills</a>
                <a href="service_invoice.php"  class="sb-sub-link <?= isActive('service_invoice.php') ?>">Service Invoice</a>
                <a href="view_quotations.php"  class="sb-sub-link <?= isActive(['view_quotations.php','add_quotation.php']) ?>">Quotations</a>
                <a href="view_proforma.php"    class="sb-sub-link <?= isActive(['view_proforma.php','add_proforma.php']) ?>">Proforma Invoice</a>
                <a href="received_payments.php" class="sb-sub-link <?= isActive('received_payments.php') ?>">Payment In</a>
            </div>
        </div>


        <!-- PAYMENTS -->
        <?php $paymentsOpen = isOpen(['service_invoice_outstanding.php','payment_history.php','received_payments_history.php']); ?>
        <button class="sb-link <?= $paymentsOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-payments" aria-expanded="<?= $paymentsOpen ? 'true' : 'false' ?>">
            <i class="fas fa-money-bill-wave sb-icon"></i>
            <span class="sb-label">Payments</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $paymentsOpen ? 'open' : '' ?>" id="sb-sb-payments">
            <div class="sb-sub-inner">
                <a href="service_invoice_outstanding.php" class="sb-sub-link <?= isActive('service_invoice_outstanding.php') ?>">Outstanding</a>
                <a href="payment_history.php"             class="sb-sub-link <?= isActive('payment_history.php') ?>">Received History</a>
                <a href="received_payments_history.php"   class="sb-sub-link <?= isActive('received_payments_history.php') ?>">History – Service</a>
            </div>
        </div>


        <!-- PURCHASE & EXPENSE -->
        <?php $purchOpen = isOpen(['purchase_entry.php','purchase-stock.php','delivery_challan.php','purchase_return.php','debit_note.php','credit_note.php','expense_management.php']); ?>
        <button class="sb-link <?= $purchOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-purchase" aria-expanded="<?= $purchOpen ? 'true' : 'false' ?>">
            <i class="fas fa-cart-arrow-down sb-icon"></i>
            <span class="sb-label">Purchase &amp; Expense</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $purchOpen ? 'open' : '' ?>" id="sb-sb-purchase">
            <div class="sb-sub-inner">
                <a href="payment_out.php"        class="sb-sub-link <?= isActive('payment_out.php') ?>">Payment Out</a>
                <a href="expense_management.php" class="sb-sub-link <?= isActive('expense_management.php') ?>">Expense</a>
                <a href="purchase_entry.php"     class="sb-sub-link <?= isActive('purchase_entry.php') ?>">Create Purchase</a>
                <a href="purchase-stock.php"     class="sb-sub-link <?= isActive('purchase-stock.php') ?>">View Purchase</a>
                <a href="delivery_challan.php"   class="sb-sub-link <?= isActive('delivery_challan.php') ?>">Delivery Challan</a>
                <a href="purchase_return.php"    class="sb-sub-link <?= isActive('purchase_return.php') ?>">Purchase Return</a>
            </div>
        </div>


        <!-- CASH & BANK (Vyapar Standard) -->
        <?php $cashBankOpen = isOpen(['bank_account_management.php','general-ledger.php','cheque_printing.php','bank_ledger_management.php','bank_reconciliation.php','e_payment_support.php','receivables-payables.php']); ?>
        <button class="sb-link <?= $cashBankOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-cash-bank" aria-expanded="<?= $cashBankOpen ? 'true' : 'false' ?>">
            <i class="fas fa-university sb-icon"></i>
            <span class="sb-label">Cash &amp; Bank</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $cashBankOpen ? 'open' : '' ?>" id="sb-sb-cash-bank">
            <div class="sb-sub-inner">
                <a href="bank_account_management.php" class="sb-sub-link <?= isActive('bank_account_management.php') ?>">Bank Accounts</a>
                <a href="general-ledger.php"          class="sb-sub-link <?= isActive('general-ledger.php') ?>">Cash in Hand</a>
                <a href="cheque_printing.php"         class="sb-sub-link <?= isActive('cheque_printing.php') ?>">Cheques</a>
                <a href="bank_ledger_management.php"  class="sb-sub-link <?= isActive('bank_ledger_management.php') ?>">Bank Ledger</a>
                <a href="bank_reconciliation.php"     class="sb-sub-link <?= isActive('bank_reconciliation.php') ?>">Bank Reconciliation</a>
                <a href="e_payment_support.php"       class="sb-sub-link <?= isActive('e_payment_support.php') ?>">E-Payments</a>
                <a href="receivables-payables.php"     class="sb-sub-link <?= isActive('receivables-payables.php') ?>">Receivables / Payables</a>
            </div>
        </div>

        <?php $rptOpen = isOpen(['reports.php','profit_loss_report.php','trial_balance.php']); ?>
        <a href="reports.php" class="sb-link <?= $rptOpen ? 'parent-active' : '' ?>">
            <i class="fas fa-chart-pie sb-icon"></i>
            <span class="sb-label">Reports</span>
        </a>

        <!-- ADMIN ONLY -->
        <?php if ($user_type === 'admin'): ?>

        <?php $staffOpen = isOpen(['create-user.php','salary_management.php']); ?>
        <button class="sb-link <?= $staffOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-staff" aria-expanded="<?= $staffOpen ? 'true' : 'false' ?>">
            <i class="fas fa-users-cog sb-icon"></i>
            <span class="sb-label">Staff Management</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $staffOpen ? 'open' : '' ?>" id="sb-sb-staff">
            <div class="sb-sub-inner">
                <a href="create-user.php"        class="sb-sub-link <?= isActive('create-user.php') ?>">Staff Users</a>
                <a href="salary_management.php"  class="sb-sub-link <?= isActive('salary_management.php') ?>">Salary Management</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ACCOUNT -->
        <?php $accountOpen = isOpen(['profile.php','company-details.php']); ?>
        <button class="sb-link <?= $accountOpen ? 'parent-active' : '' ?>"
                data-sb-toggle="sb-account" aria-expanded="<?= $accountOpen ? 'true' : 'false' ?>">
            <i class="fas fa-user-circle sb-icon"></i>
            <span class="sb-label">Account</span>
            <i class="fas fa-chevron-right sb-arrow"></i>
        </button>
        <div class="sb-sub <?= $accountOpen ? 'open' : '' ?>" id="sb-sb-account">
            <div class="sb-sub-inner">
                <a href="profile.php"         class="sb-sub-link <?= isActive('profile.php') ?>">Profile Settings</a>
                <a href="company-details.php" class="sb-sub-link <?= isActive('company-details.php') ?>">Company Details</a>
                <a href="javascript:void(0)" onclick="document.getElementById('logoutModal').style.display='flex'" class="sb-sub-link" style="color:var(--danger, #ef4444)">
                    <i class="fas fa-sign-out-alt me-1"></i> Sign Out
                </a>
            </div>
        </div>

    </nav>

    <div class="sb-footer">
        <div class="sb-version">
            <span>Credible v2.0</span>
            <span><?php echo date('Y'); ?></span>
        </div>
    </div>
</aside>

<!-- ════════════ LOGOUT MODAL ════════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;background:rgba(11,18,32,.55);">
    <div style="background:#fff;border-radius:16px;width:min(420px,90vw);box-shadow:0 20px 60px rgba(2,132,199,.25);overflow:hidden;animation:fadeInUp .25s ease">
        <div style="background:linear-gradient(135deg,#0b1220,#0284c7);padding:20px 24px;display:flex;align-items:center;justify-content:space-between">
            <span style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:#fff;font-size:1rem">
                <i class="fas fa-sign-out-alt me-2"></i>Confirm Sign Out
            </span>
            <button onclick="document.getElementById('logoutModal').style.display='none'"
                    style="background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:.9rem">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:28px 24px;text-align:center">
            <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.8rem;color:#dc2626">
                <i class="fas fa-door-open"></i>
            </div>
            <p style="font-size:.95rem;color:#475569;margin:0">Are you sure you want to sign out of <strong>Credible</strong>?</p>
        </div>
        <div style="padding:0 24px 24px;display:flex;gap:10px;justify-content:center">
            <button onclick="document.getElementById('logoutModal').style.display='none'"
                    style="padding:10px 24px;border:1.5px solid #e2e8f0;background:#fff;border-radius:8px;font-weight:600;cursor:pointer;font-size:.88rem;color:#475569">
                Cancel
            </button>
            <a href="logout.php"
               style="padding:10px 24px;background:linear-gradient(135deg,#0284c7,#0b1220);color:#fff;border-radius:8px;font-weight:700;text-decoration:none;font-size:.88rem;display:inline-flex;align-items:center;gap:7px">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </div>
    </div>
</div>

<style>
@keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
</style>

<!-- ════════════ SCRIPTS ════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
'use strict';

// ── ELEMENTS ──────────────────────────────────────────────────────────────────
const toggle   = document.getElementById('gb-toggle');
const sidebar  = document.getElementById('gb-sidebar');
const overlay  = document.getElementById('gb-overlay');
const navbar   = document.getElementById('gb-navbar');
const srInput  = document.getElementById('gb-search-input');
const srBox    = document.getElementById('gb-search-results');
const userBtn  = document.getElementById('gb-user-toggle');
const userDrop = document.getElementById('gb-user-dropdown');
const collapseBtn = document.getElementById('gb-collapse-toggle');

// ── AUTO-ASSIGN TOOLTIPS FOR COLLAPSED SIDEBAR ──
document.querySelectorAll('#gb-sidebar .sb-link').forEach(function(link) {
    const lbl = link.querySelector('.sb-label');
    if (lbl && !link.getAttribute('data-tooltip')) {
        link.setAttribute('data-tooltip', lbl.textContent.trim());
    }
});

// ── RESTORE COLLAPSED STATE FROM STORAGE ──
try {
    if (localStorage.getItem('credible_sb_collapsed') === '1' && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sb-is-collapsed');
    }
} catch (err) {}

// ── SIDEBAR COLLAPSE TOGGLE ──
if (collapseBtn) {
    collapseBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isMobile()) {
            closeSidebar();
        } else {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            document.body.classList.toggle('sb-is-collapsed', isCollapsed);
            try {
                localStorage.setItem('credible_sb_collapsed', isCollapsed ? '1' : '0');
            } catch (err) {}
        }
    });
}

// ── BREAKPOINT ─────────────────────────────────────────────────────────────────
function isMobile() { return window.innerWidth <= 992; }

// ── SIDEBAR OPEN / CLOSE ───────────────────────────────────────────────────────
function openSidebar() {
    sidebar.classList.add('sidebar-open');
    overlay.classList.add('show');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    overlay.setAttribute('aria-hidden', 'false');
}
function closeSidebar() {
    sidebar.classList.remove('sidebar-open');
    overlay.classList.remove('show');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
    overlay.setAttribute('aria-hidden', 'true');
}
function toggleSidebar() {
    if (isMobile()) {
        sidebar.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    }
    // Desktop: sidebar is always visible; toggle does nothing extra
}

if (toggle) toggle.addEventListener('click', toggleSidebar);
overlay.addEventListener('click', closeSidebar);

// Close sidebar on mobile when a non-toggle link is clicked
sidebar.addEventListener('click', function(e) {
    const link = e.target.closest('a.sb-link, a.sb-sub-link');
    if (link && isMobile()) { closeSidebar(); }
});

// Handle resize: restore sidebar on desktop
window.addEventListener('resize', function() {
    if (!isMobile()) {
        closeSidebar(); // remove open class (not needed on desktop)
        overlay.classList.remove('show');
    }
});

// ── SUBMENU ACCORDION ──────────────────────────────────────────────────────────
document.querySelectorAll('[data-sb-toggle]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const key    = this.getAttribute('data-sb-toggle');
        const sub    = document.getElementById('sb-' + key);
        const isOpen = sub.classList.contains('open');

        // Close all subs
        document.querySelectorAll('.sb-sub.open').forEach(function(el) {
            el.classList.remove('open');
        });
        document.querySelectorAll('[data-sb-toggle]').forEach(function(b) {
            b.setAttribute('aria-expanded', 'false');
        });

        // Open clicked if it was closed
        if (!isOpen) {
            sub.classList.add('open');
            this.setAttribute('aria-expanded', 'true');
        }
    });
});

// ── SEARCH ────────────────────────────────────────────────────────────────────
// Collect all links from sidebar
const allLinks = [];
document.querySelectorAll('#gb-sidebar a.sb-link, #gb-sidebar a.sb-sub-link').forEach(function(el) {
    allLinks.push({ text: el.textContent.trim(), href: el.href, icon: getIcon(el) });
});

function getIcon(el) {
    const ic = el.querySelector('i.sb-icon') || el.querySelector('i');
    return ic ? ic.className : 'fas fa-circle';
}

function renderSearch(q) {
    if (!q) { srBox.classList.remove('show'); return; }
    const hits = allLinks.filter(function(l) {
        return l.text.toLowerCase().includes(q.toLowerCase());
    });
    if (!hits.length) {
        srBox.innerHTML = '<div class="sr-empty"><i class="fas fa-search" style="margin-right:6px"></i>No results for "' + escHtml(q) + '"</div>';
    } else {
        srBox.innerHTML = hits.slice(0, 8).map(function(h, i) {
            return '<a class="sr-item" href="' + h.href + '" role="option" tabindex="0"><i class="' + h.icon + '"></i>' + escHtml(h.text) + '</a>';
        }).join('');
    }
    srBox.classList.add('show');
}

if (srInput && srBox) {
    srInput.addEventListener('input', function() { renderSearch(this.value.trim()); });
    srInput.addEventListener('focus', function() { if (this.value.trim()) renderSearch(this.value.trim()); });

    // Keyboard navigation in search
    srInput.addEventListener('keydown', function(e) {
        const items = srBox.querySelectorAll('.sr-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault(); items[0].focus();
        } else if (e.key === 'Escape') {
            srBox.classList.remove('show'); srInput.blur();
        }
    });
    srBox.addEventListener('keydown', function(e) {
        const items = Array.from(srBox.querySelectorAll('.sr-item'));
        const cur   = document.activeElement;
        const idx   = items.indexOf(cur);
        if (e.key === 'ArrowDown' && idx < items.length - 1) { e.preventDefault(); items[idx+1].focus(); }
        if (e.key === 'ArrowUp')  {
            e.preventDefault();
            if (idx === 0) { srInput.focus(); }
            else if (idx > 0) { items[idx-1].focus(); }
        }
        if (e.key === 'Escape') { srBox.classList.remove('show'); srInput.focus(); }
    });

    // Close search on outside click
    document.addEventListener('click', function(e) {
        if (!srInput.contains(e.target) && !srBox.contains(e.target)) {
            srBox.classList.remove('show');
        }
    });
}

// ── USER DROPDOWN ──────────────────────────────────────────────────────────────
userBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    const isOpen = userDrop.classList.contains('show');
    userDrop.classList.toggle('show', !isOpen);
    this.setAttribute('aria-expanded', String(!isOpen));
});
document.addEventListener('click', function(e) {
    if (!userBtn.contains(e.target) && !userDrop.contains(e.target)) {
        userDrop.classList.remove('show');
        userBtn.setAttribute('aria-expanded', 'false');
    }
});

// ── CLOSE DROPDOWNS ON ESCAPE ──────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (srBox) srBox.classList.remove('show');
        if (userDrop) userDrop.classList.remove('show');
        if (isMobile()) closeSidebar();
    }
});

// ── CLOSE LOGOUT MODAL ON OVERLAY CLICK ───────────────────────────────────────
document.getElementById('logoutModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});

// ── UTIL ──────────────────────────────────────────────────────────────────────
function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

})();
</script>