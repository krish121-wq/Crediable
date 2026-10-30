<?php
require_once 'config.php';
include 'layout.php';

// Auto-create offer_banners table if not exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS dashboard_offer_banners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        badge_text VARCHAR(100) DEFAULT 'FLASH SALE',
        main_heading VARCHAR(255) DEFAULT 'Flat 15% Off',
        sub_heading VARCHAR(255) DEFAULT 'on All Bookings & Orders',
        slot_1 VARCHAR(100) DEFAULT '2-3 PM',
        slot_2 VARCHAR(100) DEFAULT '8-9 PM',
        slot_3 VARCHAR(100) DEFAULT '10 PM-12 AM',
        target_link VARCHAR(255) DEFAULT 'sales.php',
        banner_bg_image VARCHAR(255) DEFAULT 'Uploads/default_offer_banner.jpg',
        is_active TINYINT(1) DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Check if row exists, if not insert default row
    $chk = $conn->query("SELECT id FROM dashboard_offer_banners LIMIT 1");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("INSERT INTO dashboard_offer_banners (badge_text, main_heading, sub_heading, slot_1, slot_2, slot_3, target_link, banner_bg_image, is_active) 
                      VALUES ('FLASH SALE', 'Flat 15% Off', 'on All Bookings & Orders', '2-3 PM', '8-9 PM', '10 PM-12 AM', 'sales.php', 'Uploads/default_offer_banner.jpg', 1)");
    }
} catch (Exception $e) {}

$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$user_type = $_SESSION['user_type'] ?? 'admin';

// Handle Admin Banner Update
$banner_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_offer_banner']) && $user_type === 'admin') {
    $badge_text   = trim($_POST['badge_text'] ?? 'FLASH SALE');
    $main_heading = trim($_POST['main_heading'] ?? 'Special Offer');
    $sub_heading  = trim($_POST['sub_heading'] ?? '');
    $slot_1       = trim($_POST['slot_1'] ?? '');
    $slot_2       = trim($_POST['slot_2'] ?? '');
    $slot_3       = trim($_POST['slot_3'] ?? '');
    $target_link  = trim($_POST['target_link'] ?? 'sales.php');
    $is_active    = isset($_POST['is_active']) ? 1 : 0;
    
    $banner_img_path = null;
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','webp','gif'];
        $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_name = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = __DIR__ . '/Uploads/' . $new_name;
            if (!is_dir(__DIR__ . '/Uploads')) mkdir(__DIR__ . '/Uploads', 0755, true);
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $dest)) {
                $banner_img_path = 'Uploads/' . $new_name;
            }
        }
    }
    
    if ($banner_img_path) {
        $stmt = $conn->prepare("UPDATE dashboard_offer_banners SET badge_text=?, main_heading=?, sub_heading=?, slot_1=?, slot_2=?, slot_3=?, target_link=?, banner_bg_image=?, is_active=? WHERE id=1");
        $stmt->bind_param("ssssssssi", $badge_text, $main_heading, $sub_heading, $slot_1, $slot_2, $slot_3, $target_link, $banner_img_path, $is_active);
    } else {
        $stmt = $conn->prepare("UPDATE dashboard_offer_banners SET badge_text=?, main_heading=?, sub_heading=?, slot_1=?, slot_2=?, slot_3=?, target_link=?, is_active=? WHERE id=1");
        $stmt->bind_param("sssssssi", $badge_text, $main_heading, $sub_heading, $slot_1, $slot_2, $slot_3, $target_link, $is_active);
    }
    $stmt->execute();
    $stmt->close();
    $banner_msg = "Offer banner updated successfully!";
}

// Fetch current banner
$banner = [
    'badge_text' => 'FLASH SALE',
    'main_heading' => 'Flat 15% Off',
    'sub_heading' => 'on All Bookings & Orders',
    'slot_1' => '2-3 PM',
    'slot_2' => '8-9 PM',
    'slot_3' => '10 PM-12 AM',
    'target_link' => 'sales.php',
    'banner_bg_image' => 'Uploads/default_offer_banner.jpg',
    'is_active' => 1
];
try {
    $res = $conn->query("SELECT * FROM dashboard_offer_banners WHERE id=1 LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        $banner = array_merge($banner, $row);
    }
} catch (Exception $e) {}

// Get current time greeting
$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard — Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    :root {
        --brand: #0284c7;
        --brand-dark: #0b1220;
        --brand-mid: #0ea5e9;
        --brand-light: #e0f2fe;
        --success: #10b981;
        --success-bg: #d1fae5;
        --danger: #ef4444;
        --danger-bg: #fee2e2;
        --warning: #f59e0b;
        --warning-bg: #fed7aa;
        --info: #3b82f6;
        --info-bg: #dbeafe;
        --bg: #f4f8fb;
        --card: #ffffff;
        --border: #e2e8f0;
        --text: #0b1220;
        --text-mid: #475569;
        --text-muted: #94a3b8;
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
        --shadow-md: 0 1px 3px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.05);
        --shadow-lg: 0 4px 12px rgba(0,0,0,0.08);
        --transition: all 0.3s ease;
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; overflow-x: hidden; }
    
    /* Page Wrap - Responsive (Works with layout.php sidebar) */
    .page-wrap { 
        margin-top: 70px; 
        margin-left: 260px; 
        padding: 20px 24px 40px; 
        transition: var(--transition);
        min-height: calc(100vh - 70px);
    }
    @media (max-width: 992px) { 
        .page-wrap { margin-left: 0; padding: 16px; } 
    }
    @media (max-width: 576px) { 
        .page-wrap { padding: 12px; } 
    }
    
    /* Hero Banner - Responsive */
    .hero-banner {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 20px 28px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    @media (max-width: 576px) { 
        .hero-banner { padding: 16px 20px; flex-direction: column; text-align: center; }
        .hero-right { justify-content: center; }
    }
    .hero-greeting { font-size: 0.85rem; color: rgba(255,255,255,0.75); margin-bottom: 4px; }
    .hero-greeting span { font-weight: 600; color: #fff; }
    .hero-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.6rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
    @media (max-width: 576px) { .hero-title { font-size: 1.3rem; } }
    .hero-sub { color: rgba(255,255,255,0.7); font-size: 0.8rem; }
    .datetime-card {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(8px);
        border-radius: var(--radius-md);
        padding: 8px 18px;
        text-align: center;
        border: 1px solid rgba(255,255,255,0.15);
    }
    @media (max-width: 576px) { .datetime-card { padding: 6px 14px; } }
    .datetime-date { font-size: 1.4rem; font-weight: 700; color: #fff; line-height: 1; }
    .datetime-month { font-size: 0.65rem; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.5px; }
    .datetime-time { font-size: 1.1rem; font-weight: 600; color: #fff; font-family: monospace; }
    
    /* Filter Bar - Responsive */
    .filter-bar {
        background: var(--card);
        border-radius: var(--radius-md);
        padding: 10px 18px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
    }
    @media (max-width: 768px) { 
        .filter-bar { flex-direction: column; align-items: stretch; }
        .date-range { justify-content: flex-start; flex-wrap: wrap; }
    }
    .filter-group { display: flex; gap: 8px; flex-wrap: wrap; }
    @media (max-width: 480px) { .filter-group { gap: 6px; } }
    .filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        border: 1px solid var(--border);
        background: white;
        color: var(--text-mid);
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }
    @media (max-width: 480px) { .filter-btn { padding: 5px 12px; font-size: 0.7rem; } }
    .filter-btn:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }
    .filter-btn.active { background: var(--brand-dark); color: white; border-color: var(--brand-dark); }
    .date-range { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .date-input {
        padding: 6px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.75rem;
        background: white;
    }
    @media (max-width: 480px) { .date-input { padding: 5px 8px; font-size: 0.7rem; } }
    .apply-btn {
        padding: 6px 16px;
        background: var(--brand);
        color: white;
        border: none;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.75rem;
        cursor: pointer;
        transition: var(--transition);
    }
    .apply-btn:hover { background: var(--brand-dark); transform: translateY(-1px); }
    
    /* Stats Grid - Responsive */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }
    @media (max-width: 1100px) { .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; } }
    @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; gap: 12px; } }
    
    .stat-card {
        background: var(--card);
        border-radius: var(--radius-md);
        padding: 18px 20px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    @media (max-width: 576px) { .stat-card { padding: 14px 16px; } }
    .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--brand-light); }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 3px;
        height: 100%;
        border-radius: 3px 0 0 3px;
    }
    .stat-card.purple::before { background: var(--brand-dark); }
    .stat-card.green::before { background: var(--success); }
    .stat-card.blue::before { background: var(--brand); }
    .stat-card.orange::before { background: var(--brand-mid); }
    
    .stat-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    @media (max-width: 576px) { .stat-icon { width: 38px; height: 38px; font-size: 1rem; } }
    .stat-icon.purple { background: #0b1220; color: #ffffff; }
    .stat-icon.green { background: var(--success-bg); color: var(--success); }
    .stat-icon.blue { background: var(--brand-light); color: var(--brand); }
    .stat-icon.orange { background: #e0f2fe; color: var(--brand-mid); }
    .stat-value { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.6rem; font-weight: 700; color: var(--text); line-height: 1.2; }
    @media (max-width: 576px) { .stat-value { font-size: 1.3rem; } }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 6px; }
    .stat-sub { font-size: 0.65rem; color: var(--text-muted); margin-top: 6px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
    .trend-up { color: var(--success); }
    
    /* Charts Row - Responsive */
    .charts-row {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 18px;
        margin-bottom: 24px;
    }
    @media (max-width: 900px) { .charts-row { grid-template-columns: 1fr; gap: 16px; } }
    
    .chart-card {
        background: var(--card);
        border-radius: var(--radius-md);
        padding: 18px 20px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        transition: var(--transition);
    }
    @media (max-width: 576px) { .chart-card { padding: 14px 16px; } }
    .chart-card:hover { box-shadow: var(--shadow-lg); }
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 8px;
    }
    .chart-title { font-weight: 600; color: var(--text); font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    .chart-title i { color: var(--brand); font-size: 1rem; }
    .chart-container { position: relative; height: 260px; width: 100%; }
    @media (max-width: 768px) { .chart-container { height: 220px; } }
    @media (max-width: 576px) { .chart-container { height: 200px; } }
    
    /* Overview Cards - Responsive */
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }
    @media (max-width: 768px) { .overview-grid { gap: 14px; } }
    @media (max-width: 640px) { .overview-grid { grid-template-columns: 1fr; } }
    
    .overview-card {
        background: var(--card);
        border-radius: var(--radius-md);
        padding: 18px 20px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: var(--transition);
        cursor: pointer;
    }
    @media (max-width: 576px) { .overview-card { padding: 14px 16px; gap: 12px; } }
    .overview-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--brand-light); }
    .overview-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    @media (max-width: 576px) { .overview-icon { width: 48px; height: 48px; font-size: 1.2rem; } }
    .overview-icon.purple { background: #0b1220; color: #ffffff; }
    .overview-icon.blue { background: var(--brand-light); color: var(--brand); }
    .overview-icon.orange { background: #e0f2fe; color: var(--brand-mid); }
    .overview-number { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.7rem; font-weight: 700; color: var(--text); line-height: 1; }
    @media (max-width: 576px) { .overview-number { font-size: 1.4rem; } }
    .overview-title { font-size: 0.8rem; font-weight: 500; color: var(--text-mid); margin-top: 4px; }
    .overview-desc { font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; }
    
    /* Section Title */
    .section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text);
    }
    .section-title i { color: var(--brand); font-size: 1rem; }
    .section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, var(--border), transparent);
    }
    
    /* Recent Invoices Table - Responsive */
    .invoices-card {
        background: var(--card);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        overflow: hidden;
    }
    .invoices-header {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: #0b1220;
    }
    @media (max-width: 576px) { .invoices-header { padding: 12px 16px; } }
    .invoices-header h5 { font-weight: 600; margin: 0; font-size: 0.85rem; color: #ffffff; }
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .invoice-table { width: 100%; border-collapse: collapse; min-width: 500px; }
    .invoice-table th {
        padding: 10px 14px;
        background: #f0f7fc;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-mid);
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 1px solid var(--border);
    }
    .invoice-table td {
        padding: 12px 14px;
        font-size: 0.8rem;
        border-bottom: 1px solid #f1f5f9;
    }
    @media (max-width: 576px) { .invoice-table td, .invoice-table th { padding: 8px 10px; font-size: 0.75rem; } }
    .badge-status {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }
    .badge-paid { background: var(--success-bg); color: var(--success); }
    .badge-pending { background: var(--danger-bg); color: var(--danger); }
    .badge-partial { background: var(--warning-bg); color: var(--warning); }
    .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
    .action-btn {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        transition: var(--transition);
    }
    .action-btn.print { background: var(--brand); }
    .action-btn.whatsapp { background: #25D366; }
    .action-btn:hover { transform: scale(1.05); opacity: 0.9; }
    
    /* Quick Actions - Responsive */
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-top: 10px;
    }
    @media (max-width: 900px) { .quick-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; } }
    @media (max-width: 480px) { .quick-grid { grid-template-columns: 1fr; gap: 12px; } }
    
    .quick-card {
        background: var(--card);
        border-radius: var(--radius-md);
        padding: 16px;
        text-align: center;
        text-decoration: none;
        border: 1px solid var(--border);
        transition: var(--transition);
    }
    @media (max-width: 576px) { .quick-card { padding: 12px; } }
    .quick-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--brand); }
    .quick-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 1.3rem;
    }
    @media (max-width: 576px) { .quick-icon { width: 44px; height: 44px; font-size: 1.1rem; } }
    .quick-label { font-weight: 600; font-size: 0.8rem; margin-bottom: 3px; color: var(--text); }
    .quick-desc { font-size: 0.65rem; color: var(--text-muted); }
    
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-top-color: var(--brand);
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Bootstrap tooltip re-skin to match the dashboard's dark theme */
    .tooltip .tooltip-inner {
        background: #0b1220;
        color: #fff;
        font-size: 0.68rem;
        padding: 5px 10px;
        border-radius: 6px;
        max-width: 220px;
    }
    .tooltip.bs-tooltip-top .tooltip-arrow::before,
    .tooltip.bs-tooltip-auto[data-popper-placement^="top"] .tooltip-arrow::before {
        border-top-color: #0b1220;
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .stat-card, .chart-card, .overview-card, .quick-card { animation: fadeInUp 0.3s ease forwards; }

    /* ── DASHBOARD OFFER / FLASH SALE BANNER ── */
    .dashboard-offer-card {
        position: relative;
        background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240,249,255,0.92) 100%);
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(2,132,199,0.12), 0 1px 3px rgba(0,0,0,0.05);
        border: 1.5px solid rgba(186,230,253,0.8);
        display: flex;
        align-items: center;
        min-height: 220px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .dashboard-offer-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 36px rgba(2,132,199,0.18);
    }
    .offer-bg-layer {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background-size: cover;
        background-position: center right;
        background-repeat: no-repeat;
        opacity: 0.95;
        z-index: 1;
    }
    .offer-gradient-overlay {
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: linear-gradient(90deg, #ffffff 0%, rgba(255,255,255,0.92) 32%, rgba(255,255,255,0.4) 55%, rgba(255,255,255,0) 75%);
        z-index: 2;
    }
    .offer-content-wrap {
        position: relative;
        z-index: 3;
        padding: 24px 34px;
        max-width: 650px;
    }
    .offer-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #0b1220;
        color: #facc15;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-weight: 800;
        font-size: 0.85rem;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 50px;
        margin-bottom: 12px;
        box-shadow: 0 4px 12px rgba(11,18,32,0.15);
    }
    .offer-badge-pill .bolt-icon {
        color: #facc15;
        font-size: 1rem;
        animation: pulseBolt 1.2s infinite alternate;
    }
    @keyframes pulseBolt {
        0% { transform: scale(1); }
        100% { transform: scale(1.25); filter: drop-shadow(0 0 6px #facc15); }
    }
    .offer-main-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 2.2rem;
        font-weight: 800;
        color: #0369a1;
        line-height: 1.15;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }
    .offer-sub-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 16px;
    }
    .offer-time-slots {
        display: inline-flex;
        align-items: center;
        background: #0284c7;
        border-radius: 50px;
        padding: 4px 6px;
        box-shadow: 0 4px 14px rgba(2,132,199,0.3);
        flex-wrap: wrap;
        gap: 4px;
    }
    .offer-slot-item {
        color: #ffffff;
        font-size: 0.82rem;
        font-weight: 700;
        padding: 4px 14px;
        border-right: 1px solid rgba(255,255,255,0.3);
        letter-spacing: 0.3px;
    }
    .offer-slot-item:last-child {
        border-right: none;
    }
    .offer-admin-btn {
        position: absolute;
        top: 16px;
        right: 18px;
        z-index: 5;
        background: rgba(255,255,255,0.9);
        border: 1px solid #bae6fd;
        border-radius: 30px;
        padding: 6px 14px;
        font-size: 0.76rem;
        font-weight: 700;
        color: #0284c7;
        cursor: pointer;
        backdrop-filter: blur(8px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .offer-admin-btn:hover {
        background: #0284c7;
        color: #fff;
        border-color: #0284c7;
        transform: scale(1.04);
    }
    @media (max-width: 768px) {
        .dashboard-offer-card { min-height: auto; padding: 20px 0; }
        .offer-content-wrap { padding: 16px 20px; max-width: 100%; }
        .offer-main-title { font-size: 1.6rem; }
        .offer-sub-title { font-size: 0.95rem; }
        .offer-slot-item { font-size: 0.72rem; padding: 3px 10px; }
        .offer-gradient-overlay {
            background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(255,255,255,0.85) 100%);
        }
    }
    </style>
</head>
<body>
<?php ?>

<div class="page-wrap">
    
    <?php if (!empty($banner_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:12px; font-weight:600;">
        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($banner_msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ══ DYNAMIC OFFER / FLASH SALE BANNER (Vyapar / Flipkart Style) ══ -->
    <?php 
    if (!empty($banner['is_active'])): 
        $bannerImg = !empty($banner['banner_bg_image']) ? $banner['banner_bg_image'] : 'Uploads/default_offer_banner.jpg';
        // Handle server path case sensitivity if file is in uploads or Uploads
        if (!file_exists($bannerImg)) {
            if (file_exists('Uploads/default_offer_banner.jpg')) $bannerImg = 'Uploads/default_offer_banner.jpg';
            elseif (file_exists('uploads/default_offer_banner.jpg')) $bannerImg = 'uploads/default_offer_banner.jpg';
        }
    ?>
    <div class="dashboard-offer-card">
        <!-- Background Image Layer -->
        <div class="offer-bg-layer" style="background-image: url('<?= htmlspecialchars($bannerImg) ?>');"></div>
        <!-- Gradient Overlay to ensure crisp contrast and readability on left -->
        <div class="offer-gradient-overlay"></div>

        <!-- Content Area -->
        <div class="offer-content-wrap">
            <?php if (!empty($banner['badge_text'])): ?>
            <div class="offer-badge-pill">
                <i class="fas fa-bolt bolt-icon"></i>
                <span><?= htmlspecialchars($banner['badge_text']) ?></span>
            </div>
            <?php endif; ?>

            <div class="offer-main-title"><?= htmlspecialchars($banner['main_heading']) ?></div>
            
            <?php if (!empty($banner['sub_heading'])): ?>
            <div class="offer-sub-title"><?= htmlspecialchars($banner['sub_heading']) ?></div>
            <?php endif; ?>

            <?php if (!empty($banner['slot_1']) || !empty($banner['slot_2']) || !empty($banner['slot_3'])): ?>
            <div class="offer-time-slots">
                <?php if (!empty($banner['slot_1'])): ?><div class="offer-slot-item"><?= htmlspecialchars($banner['slot_1']) ?></div><?php endif; ?>
                <?php if (!empty($banner['slot_2'])): ?><div class="offer-slot-item"><?= htmlspecialchars($banner['slot_2']) ?></div><?php endif; ?>
                <?php if (!empty($banner['slot_3'])): ?><div class="offer-slot-item"><?= htmlspecialchars($banner['slot_3']) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Admin Edit Button (Only visible to Admin) -->
        <?php if ($user_type === 'admin'): ?>
        <button type="button" class="offer-admin-btn" data-bs-toggle="modal" data-bs-target="#editOfferModal">
            <i class="fas fa-edit"></i> Edit Banner
        </button>
        <?php endif; ?>
    </div>
    <?php elseif ($user_type === 'admin'): ?>
    <!-- Banner is Hidden - Admin Restore Bar -->
    <div style="background:#f0fdf4; border:1.5px dashed #86efac; border-radius:14px; padding:12px 20px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <div style="font-size:0.85rem; font-weight:600; color:#166534;">
            <i class="fas fa-eye-slash me-2 text-success"></i>Dashboard Offer Banner is currently <strong>Hidden</strong>.
        </div>
        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#editOfferModal" style="border-radius:20px; font-weight:700; font-size:0.78rem; padding:6px 16px;">
            <i class="fas fa-bullhorn me-1"></i> Enable / Edit Banner
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <button class="filter-btn active" data-period="Today">Today</button>
            <button class="filter-btn" data-period="Yesterday">Yesterday</button>
            <button class="filter-btn" data-period="Last 7 Days">7 Days</button>
            <button class="filter-btn" data-period="Last 30 Days">30 Days</button>
            <button class="filter-btn" data-period="Last 6 Months">6 Months</button>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="date-range">
                <input type="date" id="startDate" class="date-input">
                <span style="color:var(--text-muted);">—</span>
                <input type="date" id="endDate" class="date-input">
                <button class="apply-btn" id="applyCustomRange"><i class="fas fa-calendar-check me-1"></i> Apply</button>
            </div>
            <?php if ($user_type === 'admin'): ?>
            <button type="button" class="btn btn-sm" data-bs-toggle="modal" data-bs-target="#editOfferModal" title="Offer Banner Settings" style="background:#f0f9ff; border:1px solid #bae6fd; color:#0284c7; border-radius:8px; font-weight:700; font-size:0.75rem; padding:6px 12px;">
                <i class="fas fa-bullhorn me-1"></i> Offer Banner
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stats Row 1 -->
    <div class="stats-grid">
        <div class="stat-card purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Total revenue from all sales">
            <div class="stat-header">
                <div><div class="stat-value" id="totalSales">₹0</div><div class="stat-label">Total Sales</div></div>
                <div class="stat-icon purple"><i class="fas fa-chart-line"></i></div>
            </div>
            <div class="stat-sub"><i class="fas fa-file-invoice"></i> <span id="salesCount">0</span> invoices</div>
        </div>
        <div class="stat-card green" data-bs-toggle="tooltip" data-bs-placement="top" title="Total purchase amount from suppliers">
            <div class="stat-header">
                <div><div class="stat-value" id="totalPurchases">₹0</div><div class="stat-label">Total Purchases</div></div>
                <div class="stat-icon green"><i class="fas fa-shopping-cart"></i></div>
            </div>
            <div class="stat-sub"><i class="fas fa-truck"></i> <span id="purchasesCount">0</span> orders</div>
        </div>
        <div class="stat-card blue" data-bs-toggle="tooltip" data-bs-placement="top" title="Sales minus Purchases">
            <div class="stat-header">
                <div><div class="stat-value" id="totalProfit">₹0</div><div class="stat-label">Net Profit</div></div>
                <div class="stat-icon blue"><i class="fas fa-chart-simple"></i></div>
            </div>
            <div class="stat-sub"><span id="profitMargin" class="trend-up">0%</span> margin</div>
        </div>
        <div class="stat-card orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Pending payments from customers">
            <div class="stat-header">
                <div><div class="stat-value" id="totalOutstanding">₹0</div><div class="stat-label">Outstanding</div></div>
                <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="stat-sub"><i class="fas fa-clock"></i> Pending payments</div>
        </div>
    </div>
    
    <!-- Stats Row 2 -->
    <div class="stats-grid">
        <div class="stat-card purple" data-bs-toggle="tooltip" data-bs-placement="top" title="Cash payments received">
            <div class="stat-header">
                <div><div class="stat-value" id="cashReceived">₹0</div><div class="stat-label">Cash Received</div></div>
                <div class="stat-icon purple"><i class="fas fa-money-bill-wave"></i></div>
            </div>
        </div>
        <div class="stat-card green" data-bs-toggle="tooltip" data-bs-placement="top" title="UPI, Card, Bank Transfer payments">
            <div class="stat-header">
                <div><div class="stat-value" id="onlineReceived">₹0</div><div class="stat-label">Online Received</div></div>
                <div class="stat-icon green"><i class="fas fa-mobile-alt"></i></div>
            </div>
        </div>
        <div class="stat-card blue" data-bs-toggle="tooltip" data-bs-placement="top" title="Payroll + Bank debits">
            <div class="stat-header">
                <div><div class="stat-value" id="totalExpenses">₹0</div><div class="stat-label">Total Expenses</div></div>
                <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
            </div>
        </div>
        <div class="stat-card orange" data-bs-toggle="tooltip" data-bs-placement="top" title="Purchase returns value">
            <div class="stat-header">
                <div><div class="stat-value" id="purchaseReturns">₹0</div><div class="stat-label">Purchase Returns</div></div>
                <div class="stat-icon orange"><i class="fas fa-rotate-left"></i></div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title"><i class="fas fa-chart-line"></i> Sales Trend</div>
                <span class="badge bg-light text-muted" id="revenuePeriodLabel" style="font-size:0.65rem; padding:3px 10px; border-radius:12px;">Last 30 Days</span>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> Payment Methods</div>
                <span class="badge bg-light text-muted" style="font-size:0.65rem; padding:3px 10px; border-radius:12px;">Distribution</span>
            </div>
            <div class="chart-container">
                <canvas id="paymentChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Overview Cards -->
    <div class="overview-grid">
        <div class="overview-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Number of active suppliers" onclick="window.location.href='purchase_entry.php'">
            <div class="overview-icon purple"><i class="fas fa-building"></i></div>
            <div><div class="overview-number" id="suppliersCount">0</div><div class="overview-title">Suppliers</div><div class="overview-desc">Active vendors</div></div>
        </div>
        <div class="overview-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Number of unique customers" onclick="window.location.href='customer_ledger.php'">
            <div class="overview-icon blue"><i class="fas fa-users"></i></div>
            <div><div class="overview-number" id="customersCount">0</div><div class="overview-title">Customers</div><div class="overview-desc">Unique buyers</div></div>
        </div>
        <div class="overview-card" data-bs-toggle="tooltip" data-bs-placement="top" title="Total transactions" onclick="window.location.href='sales.php'">
            <div class="overview-icon orange"><i class="fas fa-box"></i></div>
            <div><div class="overview-number" id="totalOrders">0</div><div class="overview-title">Total Orders</div><div class="overview-desc">Sales + Purchases</div></div>
        </div>
    </div>
    
    <!-- Recent Invoices -->
    <div class="section-title"><i class="fas fa-clock"></i> Recent Invoices</div>
    <div class="invoices-card">
        <div class="invoices-header">
            <h5><i class="fas fa-file-invoice me-2"></i>Last 10 Transactions</h5>
            <a href="sales.php" class="btn btn-sm" style="background: var(--brand-light); color: var(--brand); border-radius: 20px; font-size:0.7rem; padding:5px 14px;">
                <i class="fas fa-arrow-right me-1"></i> View All
            </a>
        </div>
        <div class="table-responsive">
            <table class="invoice-table">
                <thead><tr><th>Invoice #</th><th>Customer</th><th>Products</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody id="recentInvoicesBody"><tr><td colspan="7" class="text-center py-5"><div class="loading-spinner"></div><div class="mt-2 text-muted" style="font-size:0.7rem;">Loading invoices...</div></td></tr></tbody>
            </table>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="section-title" style="margin-top: 24px;"><i class="fas fa-bolt"></i> Quick Actions</div>
    <div class="quick-grid">
        <a href="sales.php" class="quick-card"><div class="quick-icon" style="background: linear-gradient(135deg, #0b1220, #1e293b); color: #7dd3fc;"><i class="fas fa-chart-line"></i></div><div class="quick-label">Sales</div><div class="quick-desc">Manage invoices</div></a>
        <a href="purchase_entry.php" class="quick-card"><div class="quick-icon" style="background: linear-gradient(135deg, var(--brand-light), #bae6fd); color: var(--brand);"><i class="fas fa-shopping-cart"></i></div><div class="quick-label">Purchases</div><div class="quick-desc">Track stock</div></a>
        <a href="customer_ledger.php" class="quick-card"><div class="quick-icon" style="background: linear-gradient(135deg, var(--success-bg), #bbf7d0); color: var(--success);"><i class="fas fa-book"></i></div><div class="quick-label">Ledger</div><div class="quick-desc">Customer statements</div></a>
    </div>

    <!-- ══ ADMIN EDIT OFFER BANNER MODAL ══ -->
    <?php if ($user_type === 'admin'): ?>
    <div class="modal fade" id="editOfferModal" tabindex="-1" aria-labelledby="editOfferModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:18px; border:none; overflow:hidden; box-shadow:0 20px 60px rgba(2,132,199,0.25);">
                <div class="modal-header" style="background:linear-gradient(135deg, #0b1220 0%, #0284c7 100%); color:#fff; padding:18px 24px;">
                    <h5 class="modal-title" id="editOfferModalLabel" style="font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; font-size:1.15rem;">
                        <i class="fas fa-bullhorn me-2 text-warning"></i>Customize Dashboard Offer &amp; Sale Banner
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_offer_banner" value="1">
                    <div class="modal-body" style="padding:24px; background:#fcfdfe;">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Sale Badge Tag (e.g. FLASH SALE / MEGA DEAL)</label>
                                <input type="text" class="form-control" name="badge_text" value="<?= htmlspecialchars($banner['badge_text'] ?? 'FLASH SALE') ?>" placeholder="FLASH SALE" style="border-radius:10px; font-weight:600;">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Main Heading / Discount (e.g. Flat 15% Off / 20% Discount)</label>
                                <input type="text" class="form-control" name="main_heading" value="<?= htmlspecialchars($banner['main_heading'] ?? 'Flat 15% Off') ?>" placeholder="Flat 15% Off" required style="border-radius:10px; font-weight:700; color:#0284c7;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Sub-Heading / Offer Detail (e.g. on All Services &amp; Products)</label>
                                <input type="text" class="form-control" name="sub_heading" value="<?= htmlspecialchars($banner['sub_heading'] ?? 'on All Bookings & Orders') ?>" placeholder="on Flight bookings / All Services" style="border-radius:10px;">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Time / Date Slot 1</label>
                                <input type="text" class="form-control" name="slot_1" value="<?= htmlspecialchars($banner['slot_1'] ?? '2-3 PM') ?>" placeholder="2-3 PM" style="border-radius:10px;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Time / Date Slot 2</label>
                                <input type="text" class="form-control" name="slot_2" value="<?= htmlspecialchars($banner['slot_2'] ?? '8-9 PM') ?>" placeholder="8-9 PM" style="border-radius:10px;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Time / Date Slot 3</label>
                                <input type="text" class="form-control" name="slot_3" value="<?= htmlspecialchars($banner['slot_3'] ?? '10 PM-12 AM') ?>" placeholder="10 PM-12 AM" style="border-radius:10px;">
                            </div>

                            <div class="col-12">
                                <label class="form-label" style="font-weight:600; font-size:0.8rem; color:#475569;">Banner Background Image (Aeroplane / Product / Custom Ad)</label>
                                <input type="file" class="form-control" name="banner_image" accept="image/*" style="border-radius:10px;">
                                <div class="form-text" style="font-size:0.75rem; color:#64748b;">Current image: <code><?= htmlspecialchars($banner['banner_bg_image'] ?? 'Default Aeroplane') ?></code>. Upload new image to replace.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active_switch" name="is_active" value="1" <?= (!empty($banner['is_active'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="is_active_switch" style="font-size:0.85rem; color:#0f172a;">Show Banner on Dashboard</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background:#f8fafc; border-top:1px solid #e2e8f0; padding:14px 24px;">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px; font-weight:600;">Cancel</button>
                        <button type="submit" class="btn btn-primary" style="background:var(--brand); border-color:var(--brand); border-radius:10px; font-weight:700; padding:8px 24px;">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Live Clock
function updateDateTime() {
    const now = new Date();
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('currentDay').textContent = now.getDate();
    document.getElementById('currentMonth').textContent = months[now.getMonth()] + ' ' + now.getFullYear();
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-IN');
}
updateDateTime();
setInterval(updateDateTime, 1000);

// Initialize Bootstrap tooltips (replaces the old buggy CSS-only [data-tooltip] tooltip
// that was causing the black bar / broken layout on hover)
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (el) {
        return new bootstrap.Tooltip(el);
    });
});

let revenueChart = null;
let paymentChart = null;

class Dashboard {
    constructor() {
        this.currentPeriod = 'Today';
        this.startDate = null;
        this.endDate = null;
        this.init();
    }
    
    init() {
        this.loadData();
        this.setupFilters();
        this.loadRecentInvoices();
        this.loadChartData();
        setInterval(() => { this.loadData(); this.loadChartData(); }, 300000);
    }
    
    setupFilters() {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentPeriod = btn.dataset.period;
                this.startDate = null;
                this.endDate = null;
                this.loadData();
                this.loadRecentInvoices();
                this.loadChartData();
                document.getElementById('revenuePeriodLabel').innerText = this.currentPeriod;
            });
        });
        document.getElementById('applyCustomRange').addEventListener('click', () => {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            if (!start || !end) { alert('Please select both start and end dates'); return; }
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.currentPeriod = 'Custom';
            this.startDate = start;
            this.endDate = end;
            this.loadData();
            this.loadRecentInvoices();
            this.loadChartData();
            document.getElementById('revenuePeriodLabel').innerHTML = `${start} to ${end}`;
        });
    }
    
    formatCurrency(value) {
        if (!value || isNaN(value)) return '₹0';
        const num = parseFloat(value);
        if (num >= 10000000) return '₹' + (num / 10000000).toFixed(2) + 'Cr';
        if (num >= 100000) return '₹' + (num / 100000).toFixed(2) + 'L';
        if (num >= 1000) return '₹' + (num / 1000).toFixed(1) + 'K';
        return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    
    async loadData() {
        try {
            let url = `ajax/dashboard-ajax.php?period=${encodeURIComponent(this.currentPeriod)}`;
            if (this.startDate && this.endDate) url += `&start_date=${this.startDate}&end_date=${this.endDate}`;
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) this.renderStats(data.data);
        } catch (error) { console.error('Fetch error:', error); }
    }
    
    renderStats(d) {
        this.animateNumber('totalSales', d.total_sales || 0);
        this.animateNumber('totalPurchases', d.total_purchase || 0);
        this.animateNumber('totalOutstanding', d.invoice_due || 0);
        this.animateNumber('cashReceived', d.total_cash_received || 0);
        this.animateNumber('onlineReceived', d.total_online_received || 0);
        this.animateNumber('totalExpenses', d.total_expenses || 0);
        this.animateNumber('purchaseReturns', d.total_purchase_return || 0);
        this.animateNumber('suppliersCount', d.suppliers_count || 0);
        this.animateNumber('customersCount', d.customers_count || 0);
        const profit = (d.total_sales || 0) - (d.total_purchase || 0);
        this.animateNumber('totalProfit', profit);
        const profitMargin = d.total_sales > 0 ? ((profit / d.total_sales) * 100).toFixed(1) : 0;
        document.getElementById('profitMargin').innerHTML = profitMargin + '%';
        document.getElementById('salesCount').innerHTML = d.sales_count || 0;
        document.getElementById('purchasesCount').innerHTML = d.purchase_count || 0;
        document.getElementById('totalOrders').innerHTML = (d.sales_count || 0) + (d.purchase_count || 0);
    }
    
    animateNumber(elementId, targetValue) {
        const element = document.getElementById(elementId);
        if (!element) return;
        const startValue = 0;
        const duration = 800;
        const startTime = performance.now();
        const isCurrency = !['salesCount', 'purchasesCount', 'suppliersCount', 'customersCount', 'totalOrders'].includes(elementId);
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const currentValue = startValue + (targetValue * progress);
            if (isCurrency) element.innerHTML = this.formatCurrency(currentValue);
            else element.innerHTML = Math.floor(currentValue);
            if (progress < 1) requestAnimationFrame(animate);
            else if (isCurrency) element.innerHTML = this.formatCurrency(targetValue);
            else element.innerHTML = targetValue;
        };
        requestAnimationFrame(animate);
    }
    
    async loadRecentInvoices() {
        try {
            let url = `ajax/get_recent_invoices.php`;
            if (this.currentPeriod === 'Custom' && this.startDate && this.endDate) url += `?start_date=${this.startDate}&end_date=${this.endDate}`;
            else url += `?period=${encodeURIComponent(this.currentPeriod)}`;
            const response = await fetch(url);
            const data = await response.json();
            if (data.success && data.data.length > 0) this.renderInvoices(data.data);
            else document.getElementById('recentInvoicesBody').innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>No invoices found</td></tr>`;
        } catch (error) { document.getElementById('recentInvoicesBody').innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">Failed to load</td></tr>`; }
    }
    
    renderInvoices(invoices) {
        let html = '';
        invoices.forEach(inv => {
            const statusClass = inv.payment_status === 'paid' ? 'badge-paid' : (inv.payment_status === 'partial' ? 'badge-partial' : 'badge-pending');
            const statusIcon = inv.payment_status === 'paid' ? 'fa-check-circle' : (inv.payment_status === 'partial' ? 'fa-clock' : 'fa-hourglass');
            const statusText = inv.payment_status === 'paid' ? 'Paid' : (inv.payment_status === 'partial' ? 'Partial' : 'Pending');
            html += `<tr>
                <td><span class="fw-bold text-primary">#${inv.invoice_id}</span></td>
                <td><div class="fw-semibold">${this.escapeHtml(inv.customer_name)}</div><small class="text-muted">${this.escapeHtml(inv.customer_phone || '-')}</small></td>
                <td><small>${inv.product_count} item(s)</small></td>
                <td><span class="fw-bold">${this.formatCurrency(inv.total_amount)}</span></td>
                <td><span class="badge-status ${statusClass}"><i class="fas ${statusIcon}"></i> ${statusText}</span></td>
                <td><small>${inv.date}</small></td>
                <td><div class="action-btns"><a href="sale_invoice.php?id=${inv.invoice_id}" target="_blank" class="action-btn print"><i class="fas fa-print"></i></a><a href="https://wa.me/91${inv.customer_phone_raw}" target="_blank" class="action-btn whatsapp"><i class="fab fa-whatsapp"></i></a></div></td>
            </tr>`;
        });
        document.getElementById('recentInvoicesBody').innerHTML = html;
    }
    
    async loadChartData() {
        try {
            let url = `ajax/get_chart_data.php`;
            if (this.startDate && this.endDate) url += `?start_date=${this.startDate}&end_date=${this.endDate}`;
            else url += `?period=${encodeURIComponent(this.currentPeriod)}`;
            const response = await fetch(url);
            const data = await response.json();
            if (data.success) this.updateCharts(data);
        } catch (error) { console.error('Chart data error:', error); }
    }
    
    updateCharts(data) {
        if (revenueChart) revenueChart.destroy();
        const ctx1 = document.getElementById('revenueChart').getContext('2d');
        revenueChart = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.revenue_labels || [],
                datasets: [{
                    label: 'Sales Revenue',
                    data: data.revenue_data || [],
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2,132,199,0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#0284c7',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1.5,
                    pointRadius: 3.5,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 11, weight: '500' }, boxWidth: 12 } },
                    tooltip: { backgroundColor: '#0b1220', callbacks: { label: (ctx) => `Revenue: ₹${ctx.raw.toLocaleString()}` } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e2e8f0' }, ticks: { callback: v => '₹' + v.toLocaleString(), font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });
        
        if (paymentChart) paymentChart.destroy();
        const ctx2 = document.getElementById('paymentChart').getContext('2d');
        paymentChart = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Cash', 'Online (UPI/Card/Bank)'],
                datasets: [{ data: [data.cash_total || 0, data.online_total || 0], backgroundColor: ['#0b1220', '#0ea5e9'], borderWidth: 0, hoverOffset: 8 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 10 }, usePointStyle: true, boxWidth: 8 } },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ₹${ctx.raw.toLocaleString()}` } }
                }
            }
        });
    }
    
    escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
}

const dashboard = new Dashboard();
const today = new Date().toISOString().split('T')[0];
document.getElementById('startDate').value = today;
document.getElementById('endDate').value = today;
</script>
</body>
</html>