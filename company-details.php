<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['sidebar_open'])) {
    $_SESSION['sidebar_open'] = true;
}

require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php?redirect=company-details.php");
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "Company Details";
$success_message = '';
$error_message = '';
$edit_mode = false;
$current = [];

try {
    $query = "SELECT * FROM company_details LIMIT 1";
    $result = mysqli_query($conn, $query);
    if (!$result) throw new Exception("Failed to fetch company details: " . mysqli_error($conn));
    if (mysqli_num_rows($result) > 0) {
        $current = mysqli_fetch_assoc($result);
        $edit_mode = true;
    }
} catch (Exception $e) {
    error_log("Fetch error in company-details.php: " . $e->getMessage());
    $error_message = "Error fetching company details: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Invalid CSRF token.";
    } else {
        try {
            $id = $edit_mode ? (int)$current['id'] : 0;
            $action = $edit_mode ? 'edit' : 'add';

            $company_name        = trim($_POST['company_name'] ?? '');
            $business_type       = trim($_POST['business_type'] ?? '');
            $company_address     = trim($_POST['company_address'] ?? '');
            $billing_address     = trim($_POST['billing_address'] ?? '');
            $company_gst         = trim($_POST['company_gst'] ?? '');
            $pan_number          = trim($_POST['pan_number'] ?? '');
            $registration_number = trim($_POST['registration_number'] ?? '');
            $contact_name        = trim($_POST['contact_name'] ?? '');
            $email               = trim($_POST['email'] ?? '');
            $phone               = trim($_POST['phone'] ?? '');
            $alternate_contact   = trim($_POST['alternate_contact'] ?? '');
            $state               = trim($_POST['state'] ?? '');
            $upi_id              = trim($_POST['upi_id'] ?? '');
            $bank_name           = trim($_POST['bank_name'] ?? '');
            $bank_account        = trim($_POST['bank_account'] ?? '');
            $bank_ifsc           = trim($_POST['bank_ifsc'] ?? '');
            $bank_branch         = trim($_POST['bank_branch'] ?? '');
            $website             = trim($_POST['website'] ?? '');

            if (empty($company_name) || empty($business_type) || empty($company_address) || empty($contact_name) || empty($email) || empty($phone)) {
                throw new Exception("All required fields must be filled.");
            }
            if ($company_gst && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $company_gst)) {
                throw new Exception("Invalid GST number format.");
            }
            if ($pan_number && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
                throw new Exception("Invalid PAN number format.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            $check = "SELECT id FROM company_details WHERE company_name = '" . mysqli_real_escape_string($conn, $company_name) . "' AND id != $id";
            $res = mysqli_query($conn, $check);
            if ($res && mysqli_num_rows($res) > 0) throw new Exception("Company name already exists.");

            $check2 = "SELECT id FROM company_details WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' AND id != $id";
            $res2 = mysqli_query($conn, $check2);
            if ($res2 && mysqli_num_rows($res2) > 0) throw new Exception("Email is already in use.");

            $company_logo = $edit_mode ? ($current['company_logo'] ?? null) : null;
            if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
                    throw new Exception("Failed to create uploads directory.");
                }
                if ($_FILES['company_logo']['size'] > 102400) {
                    throw new Exception("Logo file too large. Maximum size is 100KB.");
                }
                $file_ext = strtolower(pathinfo($_FILES['company_logo']['name'], PATHINFO_EXTENSION));
                if (!in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                    throw new Exception("Only JPG, PNG, GIF, WebP, SVG files are allowed.");
                }
                $file_name = time() . '_' . basename($_FILES['company_logo']['name']);
                $target_file = $upload_dir . $file_name;
                if (!move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_file)) {
                    throw new Exception("Failed to upload logo.");
                }
                if ($edit_mode && $company_logo && file_exists($company_logo)) {
                    unlink($company_logo);
                }
                $company_logo = $target_file;
            }

            $esc = function($v) use ($conn) { return mysqli_real_escape_string($conn, $v); };
            $logo_sql = $company_logo ? "'" . $esc($company_logo) . "'" : 'NULL';

            $new_cols = ['upi_id VARCHAR(100)', 'bank_name VARCHAR(100)', 'bank_account VARCHAR(50)', 'bank_ifsc VARCHAR(20)', 'bank_branch VARCHAR(100)', 'website VARCHAR(200)'];
            foreach ($new_cols as $col_def) {
                @mysqli_query($conn, "ALTER TABLE company_details ADD COLUMN IF NOT EXISTS $col_def");
            }

            if ($action === 'add') {
                $query = "INSERT INTO company_details (company_name, business_type, company_logo, company_address, billing_address, state, company_gst, pan_number, registration_number, contact_name, email, phone, alternate_contact, upi_id, bank_name, bank_account, bank_ifsc, bank_branch, website)
                          VALUES ('{$esc($company_name)}', '{$esc($business_type)}', $logo_sql, '{$esc($company_address)}', '{$esc($billing_address)}', '{$esc($state)}', '{$esc($company_gst)}', '{$esc($pan_number)}', '{$esc($registration_number)}', '{$esc($contact_name)}', '{$esc($email)}', '{$esc($phone)}', '{$esc($alternate_contact)}', '{$esc($upi_id)}', '{$esc($bank_name)}', '{$esc($bank_account)}', '{$esc($bank_ifsc)}', '{$esc($bank_branch)}', '{$esc($website)}')";
            } else {
                $query = "UPDATE company_details SET
                          company_name = '{$esc($company_name)}', business_type = '{$esc($business_type)}', company_logo = $logo_sql,
                          company_address = '{$esc($company_address)}', billing_address = '{$esc($billing_address)}', state = '{$esc($state)}',
                          company_gst = '{$esc($company_gst)}', pan_number = '{$esc($pan_number)}', registration_number = '{$esc($registration_number)}',
                          contact_name = '{$esc($contact_name)}', email = '{$esc($email)}', phone = '{$esc($phone)}',
                          alternate_contact = '{$esc($alternate_contact)}', upi_id = '{$esc($upi_id)}', bank_name = '{$esc($bank_name)}',
                          bank_account = '{$esc($bank_account)}', bank_ifsc = '{$esc($bank_ifsc)}', bank_branch = '{$esc($bank_branch)}',
                          website = '{$esc($website)}'
                          WHERE id = $id";
            }

            if (!mysqli_query($conn, $query)) {
                throw new Exception(ucfirst($action) . " failed: " . mysqli_error($conn));
            }

            $_SESSION['company_saved'] = true;
            header("Location: company-details.php");
            exit();
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            error_log("Error in company-details.php: $error_message");
        }
    }
}

$upi_qr_url = '';
if (!empty($current['upi_id'])) {
    $upi_string = "upi://pay?pa=" . urlencode($current['upi_id']) . "&pn=" . urlencode($current['company_name'] ?? '') . "&cu=INR";
    $upi_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($upi_string);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($page_title); ?> – CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --brand: #0284c7;
        --brand-dark: #0b1220;
        --brand-mid: #0ea5e9;
        --brand-light: #e0f2fe;
        --gold: #0b1220;
        --gold-light: #f0f9ff;
        --success: #16a34a;
        --danger: #dc2626;
        --warning: #f59e0b;
        --info: #0ea5e9;
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
        --transition: all 0.2s ease;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }

    /* Layout */
    .page-wrap {
        margin-top: 70px;
        margin-left: 260px;
        padding: 24px 28px 48px;
        transition: var(--transition);
    }
    @media (max-width: 992px) { 
        .page-wrap { margin-left: 0; padding: 20px; } 
    }
    @media (max-width: 576px) { 
        .page-wrap { padding: 16px; } 
    }

    /* Hero Banner */
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
        .hero-banner { flex-direction: column; align-items: flex-start; }
    }
    .hero-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .hero-sub { color: rgba(255,255,255,0.7); font-size: 0.8rem; }
    .hero-badge {
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        border-radius: 30px;
        padding: 6px 16px;
        color: #fff;
        font-size: 0.75rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .hero-badge .dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #4ade80;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.5; } }

    /* Section Cards */
    .section-card {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        background: #fafbfc;
    }
    .section-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .section-head h2 { font-size: 0.9rem; font-weight: 700; color: var(--text); margin: 0; }
    .section-head p { font-size: 0.7rem; color: var(--text-muted); margin: 0; }
    .step-tag {
        margin-left: auto;
        background: var(--brand-light);
        color: var(--brand);
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 0.65rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .section-body { padding: 20px; }
    @media (max-width: 576px) { .section-body { padding: 16px; } }

    /* Form Grid */
    .form-row { display: grid; gap: 16px; }
    .form-row.cols-3 { grid-template-columns: repeat(3, 1fr); }
    .form-row.cols-2 { grid-template-columns: repeat(2, 1fr); }
    @media (max-width: 900px) { .form-row.cols-3 { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 640px) { .form-row.cols-3, .form-row.cols-2 { grid-template-columns: 1fr; } }

    .field-group { display: flex; flex-direction: column; gap: 5px; }
    .field-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-mid);
        text-transform: uppercase;
        letter-spacing: 0.3px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .field-label .req { color: var(--danger); }
    .field-label .hint { font-size: 0.65rem; text-transform: none; font-weight: 400; }
    .field-input, .field-select, .field-textarea {
        width: 100%;
        padding: 9px 12px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
        font-family: 'Inter', sans-serif;
        color: var(--text);
        background: #fafafa;
        transition: var(--transition);
        outline: none;
    }
    .field-input:focus, .field-select:focus, .field-textarea:focus {
        border-color: var(--brand);
        background: #fff;
        box-shadow: 0 0 0 2px rgba(2,132,199,0.12);
    }
    .field-textarea { resize: vertical; min-height: 70px; }
    .field-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%239590a8'%3E%3Cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 16px;
        padding-right: 32px;
    }

    .input-icon-wrap { position: relative; }
    .input-icon-wrap .icon-prefix {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 0.8rem;
        pointer-events: none;
    }
    .input-icon-wrap .field-input { padding-left: 34px; }

    /* Logo Upload */
    .logo-upload-area {
        border: 2px dashed var(--border);
        border-radius: var(--radius-md);
        padding: 16px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: #fafafa;
        position: relative;
    }
    .logo-upload-area:hover { border-color: var(--brand); background: var(--brand-light); }
    .logo-upload-area input[type=file] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer;
    }
    .logo-preview-img {
        width: 80px; height: 80px;
        border-radius: 10px;
        object-fit: contain;
        border: 1px solid var(--border);
        background: #fff;
        padding: 6px;
        margin: 0 auto 10px;
    }
    .upload-label { font-size: 0.8rem; font-weight: 500; color: var(--text-mid); }
    .upload-hint { font-size: 0.7rem; color: var(--text-muted); margin-top: 3px; }
    .size-warning { font-size: 0.65rem; color: var(--warning); margin-top: 2px; }

    /* UPI QR Section */
    .upi-qr-card {
        background: linear-gradient(135deg, #f0f9ff 0%, #e6f4fb 100%);
        border: 1px solid #bae6fd;
        border-radius: var(--radius-md);
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .qr-frame {
        background: #fff;
        border-radius: 10px;
        padding: 8px;
        border: 1px solid #bae6fd;
    }
    .qr-frame img { display: block; border-radius: 4px; width: 110px; height: 110px; }
    .qr-placeholder {
        width: 110px; height: 110px;
        background: #e0f2fe;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        color: #0369a1;
        font-size: 0.7rem;
        text-align: center;
    }
    .upi-info h4 { font-size: 0.9rem; font-weight: 700; color: var(--brand); margin-bottom: 5px; }
    .upi-id-display {
        background: #fff;
        border: 1px solid #bae6fd;
        border-radius: 30px;
        padding: 5px 14px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--brand);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
    }
    .upi-note { font-size: 0.7rem; color: var(--text-muted); }

    /* Bank Grid */
    .bank-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    @media (max-width: 640px) { .bank-grid { grid-template-columns: 1fr; } }
    .bank-chip {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .bank-chip-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 0.8rem;
    }
    .bank-chip-label { font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
    .bank-chip-val { font-size: 0.8rem; color: var(--text); font-weight: 600; margin-top: 2px; }

    /* Progress Bar */
    .setup-progress {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        padding: 12px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .progress-label { font-size: 0.75rem; font-weight: 600; color: var(--text-mid); }
    .progress-track {
        flex: 1;
        min-width: 100px;
        height: 5px;
        background: var(--border);
        border-radius: 99px;
        overflow: hidden;
    }
    .progress-fill {
        height: 100%;
        border-radius: 99px;
        background: linear-gradient(90deg, var(--brand-dark), var(--brand));
        transition: width 0.5s ease;
    }
    .progress-pct { font-size: 0.75rem; font-weight: 700; color: var(--brand); }

    /* Alert */
    .alert-custom {
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .alert-success-custom { background: #ecfdf5; border: 1px solid #86efac; color: #15803d; }
    .alert-danger-custom { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

    /* Action Bar */
    .action-bar {
        position: sticky;
        bottom: 20px;
        z-index: 90;
        display: flex;
        justify-content: center;
        gap: 12px;
        padding: 12px 20px;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        border: 1px solid var(--border);
        border-radius: 50px;
        box-shadow: var(--shadow-lg);
        margin-top: 20px;
        flex-wrap: wrap;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff;
        border: none;
        padding: 9px 24px;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-primary-custom:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(2,132,199,0.35); }
    .btn-ghost {
        background: transparent;
        color: var(--text-mid);
        border: 1px solid var(--border);
        padding: 8px 20px;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-ghost:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }

    /* Modal */
    .modal-header-custom {
        background: linear-gradient(135deg, var(--brand-dark), var(--brand));
        color: #fff;
        border-radius: 12px 12px 0 0;
        padding: 14px 20px;
    }
    .modal-content { border-radius: 12px; border: none; }

    /* Help Tiles */
    .help-tile {
        background: var(--bg);
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .help-tile-icon { color: var(--brand); font-size: 0.85rem; margin-top: 2px; }
    .help-tile-text { font-size: 0.75rem; color: var(--text-mid); line-height: 1.5; }

    .invalid-msg { font-size: 0.7rem; color: var(--danger); margin-top: 3px; }
    .is-invalid { border-color: var(--danger) !important; }
    </style>
</head>
<body>
<?php include('layout.php'); ?>

<div class="page-wrap">

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div>
            <div class="hero-title"><i class="fas fa-building me-2"></i>Company Details</div>
            <div class="hero-sub"><?php echo $edit_mode ? 'Update your business profile and payment settings' : 'Set up your business to get started'; ?></div>
        </div>
        <div class="hero-badge">
            <div class="dot"></div>
            <?php echo $edit_mode ? 'Profile Active' : 'Setup Required'; ?>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['company_saved']) && $_SESSION['company_saved']): unset($_SESSION['company_saved']); ?>
        <div class="alert-custom alert-success-custom" id="inlineSuccess">
            <i class="fas fa-check-circle"></i>
            Company details saved successfully!
            <button onclick="document.getElementById('inlineSuccess')?.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Progress -->
    <?php
    $filled = 0; $total = 8;
    if (!empty($current['company_name']))    $filled++;
    if (!empty($current['business_type']))   $filled++;
    if (!empty($current['company_address'])) $filled++;
    if (!empty($current['email']))           $filled++;
    if (!empty($current['phone']))           $filled++;
    if (!empty($current['company_gst']))     $filled++;
    if (!empty($current['upi_id']))          $filled++;
    if (!empty($current['company_logo']))    $filled++;
    $pct = $edit_mode ? round(($filled / $total) * 100) : 0;
    ?>
    <div class="setup-progress">
        <span class="progress-label"><i class="fas fa-tasks me-1"></i> Profile Completion</span>
        <div class="progress-track"><div class="progress-fill" style="width:<?php echo $pct; ?>%"></div></div>
        <span class="progress-pct"><?php echo $pct; ?>%</span>
    </div>

    <form method="POST" action="company-details.php" id="companyForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <!-- Section 1: Business Identity -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon"><i class="fas fa-id-card"></i></div>
                <div><h2>Business Identity</h2><p>Core information about your business</p></div>
                <span class="step-tag"><i class="fas fa-star-of-life me-1"></i>Required</span>
            </div>
            <div class="section-body">
                <!-- Logo Upload -->
                <div style="margin-bottom:20px">
                    <div class="field-label" style="margin-bottom:8px"><i class="fas fa-image"></i> Company Logo <span class="hint">Max 100KB</span></div>
                    <div class="logo-upload-area" id="logoDropzone">
                        <input type="file" name="company_logo" id="companyLogo" accept="image/*">
                        <?php if ($edit_mode && !empty($current['company_logo']) && file_exists($current['company_logo'])): ?>
                            <img id="logoPreview" class="logo-preview-img" src="<?php echo htmlspecialchars($current['company_logo']); ?>" alt="Logo">
                        <?php else: ?>
                            <div id="logoIconHolder" style="margin-bottom:8px">
                                <div style="width:60px;height:60px;border-radius:12px;background:var(--brand-light);display:flex;align-items:center;justify-content:center;margin:0 auto;border:1px dashed var(--brand);">
                                    <i class="fas fa-cloud-upload-alt" style="font-size:1.4rem;color:var(--brand);opacity:.6"></i>
                                </div>
                            </div>
                            <img id="logoPreview" class="logo-preview-img" alt="Logo" style="display:none">
                        <?php endif; ?>
                        <div class="upload-label">Click or drag & drop your logo</div>
                        <div class="upload-hint">PNG, JPG, SVG up to 100KB</div>
                        <div id="sizeError" style="color:var(--danger);font-size:0.7rem;margin-top:4px;display:none"><i class="fas fa-times-circle me-1"></i>File exceeds 100KB limit</div>
                    </div>
                </div>

                <div class="form-row cols-3">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-building"></i> Company Name <span class="req">*</span></label>
                        <input type="text" name="company_name" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? $current['company_name'] : ''); ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-briefcase"></i> Business Type <span class="req">*</span></label>
                        <select name="business_type" class="field-select" required>
                            <option value="">Select Type</option>
                            <?php $types = ['retail'=>'Retail','ecommerce'=>'E-commerce','service'=>'Services','restaurant'=>'Restaurant','healthcare'=>'Healthcare','manufacturing'=>'Manufacturing','wholesale'=>'Wholesale','technology'=>'Technology','other'=>'Other'];
                            foreach ($types as $k => $v): ?>
                                <option value="<?php echo $k; ?>" <?php echo $edit_mode && ($current['business_type']??'') === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-globe"></i> Website</label>
                        <div class="input-icon-wrap">
                            <i class="icon-prefix fas fa-link"></i>
                            <input type="url" name="website" class="field-input" placeholder="https://" value="<?php echo htmlspecialchars($edit_mode ? ($current['website'] ?? '') : ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Address -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div><h2>Address Details</h2><p>Company location information</p></div>
            </div>
            <div class="section-body">
                <div class="form-row cols-1" style="margin-bottom:16px">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-location-dot"></i> Company Address <span class="req">*</span></label>
                        <textarea name="company_address" class="field-textarea" placeholder="Full address with PIN code" required><?php echo htmlspecialchars($edit_mode ? $current['company_address'] : ''); ?></textarea>
                    </div>
                </div>
                <div class="form-row cols-2">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-file-invoice"></i> Billing Address</label>
                        <textarea name="billing_address" class="field-textarea" placeholder="Same as company address if left blank"><?php echo htmlspecialchars($edit_mode ? $current['billing_address'] : ''); ?></textarea>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-map"></i> State</label>
                        <select name="state" class="field-select">
                            <option value="">Select State</option>
                            <?php $states=['Maharashtra','Delhi','Karnataka','Tamil Nadu','Gujarat','Uttar Pradesh','West Bengal','Rajasthan','Madhya Pradesh','Punjab','Haryana','Kerala','Telangana','Andhra Pradesh','Bihar','Chhattisgarh','Odisha','Jharkhand','Assam','Others'];
                            foreach ($states as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $edit_mode && ($current['state']??'') === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Tax & Legal -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon"><i class="fas fa-file-shield"></i></div>
                <div><h2>Tax & Legal Details</h2><p>GST, PAN for invoicing</p></div>
            </div>
            <div class="section-body">
                <div class="form-row cols-3">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-percent"></i> GST Number</label>
                        <input type="text" name="company_gst" id="companyGst" class="field-input" maxlength="15" placeholder="22AAAAA0000A1Z5" value="<?php echo htmlspecialchars($edit_mode ? $current['company_gst'] : ''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-id-badge"></i> PAN Number</label>
                        <input type="text" name="pan_number" id="panNumber" class="field-input" maxlength="10" placeholder="AAAAA1234A" value="<?php echo htmlspecialchars($edit_mode ? $current['pan_number'] : ''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-registered"></i> Registration No.</label>
                        <input type="text" name="registration_number" class="field-input" placeholder="Company registration" value="<?php echo htmlspecialchars($edit_mode ? $current['registration_number'] : ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Contact -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon"><i class="fas fa-headset"></i></div>
                <div><h2>Contact Information</h2><p>Primary contact details</p></div>
                <span class="step-tag"><i class="fas fa-star-of-life me-1"></i>Required</span>
            </div>
            <div class="section-body">
                <div class="form-row cols-3">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-user"></i> Contact Person <span class="req">*</span></label>
                        <input type="text" name="contact_name" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? $current['contact_name'] : ''); ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-envelope"></i> Email <span class="req">*</span></label>
                        <input type="email" name="email" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? $current['email'] : ''); ?>" required>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-phone"></i> Phone <span class="req">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="icon-prefix fas fa-phone"></i>
                            <input type="text" name="phone" class="field-input" placeholder="+91 98765 43210" value="<?php echo htmlspecialchars($edit_mode ? $current['phone'] : ''); ?>" required>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-phone-volume"></i> Alternate Contact</label>
                        <input type="text" name="alternate_contact" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? $current['alternate_contact'] : ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: UPI & QR -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon" style="background:linear-gradient(135deg,#0b1220,#0284c7)"><i class="fas fa-qrcode"></i></div>
                <div><h2>UPI & Payment QR</h2><p>Accept digital payments</p></div>
            </div>
            <div class="section-body">
                <?php if ($edit_mode && !empty($current['upi_id'])): ?>
                <div class="upi-qr-card">
                    <div class="qr-frame">
                        <img src="<?php echo htmlspecialchars($upi_qr_url); ?>" width="110" height="110" alt="UPI QR Code" id="liveQrImg">
                    </div>
                    <div class="upi-info">
                        <h4><i class="fas fa-qrcode me-1"></i>Payment QR Code</h4>
                        <div class="upi-id-display">
                            <i class="fas fa-at"></i>
                            <span id="liveUpiText"><?php echo htmlspecialchars($current['upi_id']); ?></span>
                        </div>
                        <div class="upi-note">Scan with any UPI app to pay</div>
                        <div class="d-flex gap-2 mt-2">
                            <a href="<?php echo htmlspecialchars($upi_qr_url); ?>" download="upi-qr.png" class="btn-ghost" style="padding:4px 12px;font-size:0.7rem;">Download QR</a>
                            <button type="button" onclick="printQR()" class="btn-ghost" style="padding:4px 12px;font-size:0.7rem;">Print QR</button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="upi-qr-card" style="opacity:0.7">
                    <div class="qr-frame">
                        <div class="qr-placeholder" id="qrPlaceholder">
                            <i class="fas fa-qrcode" style="font-size:2rem;opacity:0.4"></i>
                            <span>QR will appear<br>after UPI ID</span>
                        </div>
                        <img src="" width="110" height="110" alt="QR" id="liveQrImg" style="display:none">
                    </div>
                    <div class="upi-info">
                        <h4><i class="fas fa-qrcode me-1"></i>Payment QR Code</h4>
                        <div class="upi-id-display" style="opacity:0.6">
                            <i class="fas fa-at"></i>
                            <span id="liveUpiText">Enter UPI ID below</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row cols-2">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-at" style="color:#0284c7"></i> UPI ID</label>
                        <div class="input-icon-wrap">
                            <i class="icon-prefix fas fa-at"></i>
                            <input type="text" name="upi_id" id="upiIdInput" class="field-input" placeholder="yourname@upi" value="<?php echo htmlspecialchars($edit_mode ? ($current['upi_id'] ?? '') : ''); ?>">
                        </div>
                        <span style="font-size:0.65rem;color:var(--text-muted)">PhonePe, GPay, Paytm, BHIM accepted</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 6: Bank Details -->
        <div class="section-card">
            <div class="section-head">
                <div class="section-icon" style="background:linear-gradient(135deg,#0ea5e9,#0284c7)"><i class="fas fa-university"></i></div>
                <div><h2>Bank Account Details</h2><p>Printed on invoices</p></div>
            </div>
            <div class="section-body">
                <div class="form-row cols-2">
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-university"></i> Bank Name</label>
                        <input type="text" name="bank_name" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? ($current['bank_name'] ?? '') : ''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-credit-card"></i> Account Number</label>
                        <input type="text" name="bank_account" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? ($current['bank_account'] ?? '') : ''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-code-branch"></i> IFSC Code</label>
                        <input type="text" name="bank_ifsc" class="field-input" placeholder="SBIN0001234" value="<?php echo htmlspecialchars($edit_mode ? ($current['bank_ifsc'] ?? '') : ''); ?>">
                    </div>
                    <div class="field-group">
                        <label class="field-label"><i class="fas fa-map-pin"></i> Branch</label>
                        <input type="text" name="bank_branch" class="field-input" value="<?php echo htmlspecialchars($edit_mode ? ($current['bank_branch'] ?? '') : ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn-ghost" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-circle-question"></i> Help
            </button>
            <button type="button" class="btn-ghost" onclick="resetForm()">
                <i class="fas fa-undo-alt"></i> Reset
            </button>
            <button type="submit" class="btn-primary-custom">
                <i class="fas fa-save"></i>
                <?php echo $edit_mode ? 'Update Company' : 'Save Company'; ?>
            </button>
        </div>
    </form>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-circle-question me-2"></i>Company Details Help</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px">
                <div class="help-tile"><i class="fas fa-star-of-life help-tile-icon"></i><div class="help-tile-text"><strong>Required fields</strong> — Company Name, Business Type, Address, Contact Name, Email, Phone are mandatory</div></div>
                <div class="help-tile"><i class="fas fa-image help-tile-icon"></i><div class="help-tile-text"><strong>Logo</strong> — Max 100KB, PNG/JPG/SVG recommended</div></div>
                <div class="help-tile"><i class="fas fa-percent help-tile-icon"></i><div class="help-tile-text"><strong>GST format</strong> — 15 characters: 22AAAAA0000A1Z5</div></div>
                <div class="help-tile"><i class="fas fa-qrcode help-tile-icon"></i><div class="help-tile-text"><strong>UPI ID</strong> — Enter to auto-generate payment QR code</div></div>
                <div class="help-tile"><i class="fas fa-university help-tile-icon"></i><div class="help-tile-text"><strong>Bank details</strong> — Appear on invoices for NEFT/RTGS</div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary-custom" data-bs-dismiss="modal" style="padding:8px 20px;">Got it</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Leave Modal -->
<div class="modal fade" id="confirmLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Unsaved Changes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:20px">
                You have unsaved changes. Are you sure you want to leave?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Stay</button>
                <button type="button" id="confirmLeave" class="btn-primary-custom" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">Leave</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(function() {
    // Logo upload
    const MAX_SIZE = 102400;
    const logoInput = document.getElementById('companyLogo');
    const logoPreview = document.getElementById('logoPreview');
    const logoIconHolder = document.getElementById('logoIconHolder');
    const sizeError = document.getElementById('sizeError');

    logoInput?.addEventListener('change', function() {
        if (this.files.length) {
            const file = this.files[0];
            if (file.size > MAX_SIZE) {
                sizeError.style.display = 'block';
                this.value = '';
                return;
            }
            sizeError.style.display = 'none';
            const reader = new FileReader();
            reader.onload = e => {
                logoPreview.src = e.target.result;
                logoPreview.style.display = 'block';
                if (logoIconHolder) logoIconHolder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });

    // UPI QR Preview
    const upiInput = document.getElementById('upiIdInput');
    const qrImg = document.getElementById('liveQrImg');
    const qrPlaceholder = document.getElementById('qrPlaceholder');
    const liveUpiText = document.getElementById('liveUpiText');
    let qrTimer;

    if (upiInput) {
        upiInput.addEventListener('input', function() {
            clearTimeout(qrTimer);
            const val = this.value.trim();
            if (val.length < 3) {
                if (qrPlaceholder) qrPlaceholder.style.display = 'flex';
                if (qrImg) qrImg.style.display = 'none';
                return;
            }
            qrTimer = setTimeout(() => {
                const compName = document.querySelector('input[name="company_name"]')?.value || '';
                const upiStr = 'upi://pay?pa=' + encodeURIComponent(val) + '&pn=' + encodeURIComponent(compName) + '&cu=INR';
                const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(upiStr);
                if (qrImg) {
                    qrImg.src = qrUrl;
                    qrImg.style.display = 'block';
                    if (qrPlaceholder) qrPlaceholder.style.display = 'none';
                }
                if (liveUpiText) liveUpiText.textContent = val;
            }, 500);
        });
    }

    // GST & PAN uppercase
    $('#companyGst, #panNumber').on('input', function() { this.value = this.value.toUpperCase(); });

    // Unsaved changes
    let changed = false;
    $('#companyForm').on('change keyup', 'input,textarea,select', () => { changed = true; });
    $('#companyForm').on('submit', () => { changed = false; });
    window.onbeforeunload = () => changed ? true : undefined;
    window.addEventListener('popstate', function() {
        if (changed) {
            history.pushState(null, null, window.location.href);
            new bootstrap.Modal(document.getElementById('confirmLeaveModal')).show();
        }
    });
    history.pushState(null, null, window.location.href);
    $('#confirmLeave').on('click', () => { changed = false; window.history.back(); });
});

function resetForm() { if (confirm('Reset all unsaved changes?')) document.getElementById('companyForm').reset(); }
function printQR() {
    const qrSrc = document.getElementById('liveQrImg')?.src;
    if (!qrSrc) return;
    const w = window.open('', '_blank', 'width=300,height=350');
    w.document.write(`<html><body style="text-align:center;padding:20px;font-family:sans-serif"><h3>Scan to Pay</h3><img src="${qrSrc}" width="120"><p>${document.getElementById('liveUpiText')?.textContent || ''}</p><script>window.print();<\/script></body></html>`);
    w.document.close();
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>