<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'config.php';
require_once 'staff_permissions_helper.php';
include('layout.php');

// Set admin session for testing if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'admin';
}

// For staff users, ensure they have created_by set to their admin's ID
if ($_SESSION['user_type'] === 'staff' && !isset($_SESSION['created_by'])) {
    $staff_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT created_by FROM create_users WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $staff_data = $result->fetch_assoc();
        $_SESSION['created_by'] = $staff_data['created_by'];
    }
}

$page_title = "Staff Management";
$success_message = '';
$error_message = '';
$permissions_helper = new StaffPermissions($conn);

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    try {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            $email = $conn->real_escape_string($email);
            $query = "SELECT id, password, status FROM create_users WHERE email = '$email' AND status = 1";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $success_message = "Login successful!";
                } else {
                    throw new Exception("Invalid email or password");
                }
            } else {
                throw new Exception("Invalid email or password");
            }
        } else {
            throw new Exception("Email and password are required");
        }
    } catch (Exception $e) {
        error_log("Login Error in create-user.php: " . $e->getMessage());
        $error_message = "Login Error: " . $e->getMessage();
    }
}

// Handle user operations (add, edit, delete) only if logged in
$user_type = $_SESSION['user_type'] ?? 'admin';
if ($user_type === 'staff') {
    header("Location: dashboard.php");
    exit();
}
$admin_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    try {
        $count_result = $conn->query("SELECT COUNT(*) as count FROM create_users WHERE created_by = $admin_id");
        $current_count = $count_result->fetch_assoc()['count'];
        if ($current_count >= 5) {
            throw new Exception("Maximum limit of 5 staff users reached.");
        }

        $name = $_POST['name'] ?? '';
        $number = $_POST['number'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? '0';
        $role = $_POST['role'] ?? 'staff';
        $employee_id = $_POST['employee_id'] ?? '';
        $designation = $_POST['designation'] ?? '';
        $department = $_POST['department'] ?? '';
        $joining_date = $_POST['joining_date'] ?? '';

        if ($name && $number && $email && $password) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }

            $email_check = $conn->prepare("SELECT id FROM create_users WHERE email = ?");
            $email_check->bind_param("s", $email);
            $email_check->execute();
            if ($email_check->get_result()->num_rows > 0) {
                throw new Exception("Email already exists.");
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $admin_id = (int)$admin_id;
            $status = (int)$status;

            if (empty($employee_id)) {
                $employee_id = 'EMP' . date('Y') . str_pad($current_count + 1, 3, '0', STR_PAD_LEFT);
            }

            $stmt = $conn->prepare("INSERT INTO create_users (name, number, email, password, status, role, employee_id, designation, department, joining_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssisssssi", $name, $number, $email, $hashed_password, $status, $role, $employee_id, $designation, $department, $joining_date, $admin_id);

            if (!$stmt->execute()) {
                throw new Exception("Insert failed: " . $conn->error);
            }

            $new_user_id = $conn->insert_id;
            $success_message = "Staff user '$name' added successfully with Employee ID: $employee_id";
        } else {
            throw new Exception("Missing required fields");
        }
    } catch (Exception $e) {
        error_log("Error in create-user.php: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user']) && $admin_id) {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }

        $id = $_POST['id'] ?? '';
        if (!$id) throw new Exception("Missing id for edit");

        $id = (int)$id;
        $admin_id = (int)$admin_id;

        $stmt = $conn->prepare("SELECT name, number, email, password, status, role, employee_id, designation, department, joining_date FROM create_users WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $id, $admin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Staff user not found or unauthorized access");
        }
        $current = $result->fetch_assoc();

        $name = $_POST['name'] ?? $current['name'];
        $number = $_POST['number'] ?? $current['number'];
        $email = $_POST['email'] ?? $current['email'];
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? $current['status'];
        $role = $_POST['role'] ?? $current['role'];
        $employee_id = $_POST['employee_id'] ?? $current['employee_id'];
        $designation = $_POST['designation'] ?? $current['designation'];
        $department = $_POST['department'] ?? $current['department'];
        $joining_date = $_POST['joining_date'] ?? $current['joining_date'];

        if ($email !== $current['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }

            $email_check = $conn->prepare("SELECT id FROM create_users WHERE email = ? AND id != ?");
            $email_check->bind_param("si", $email, $id);
            $email_check->execute();
            if ($email_check->get_result()->num_rows > 0) {
                throw new Exception("Email already exists.");
            }
        }

        $update_fields = [];
        $params = [];
        $types = '';

        if ($name !== $current['name']) {
            $update_fields[] = "name = ?";
            $params[] = $name;
            $types .= 's';
        }
        if ($number !== $current['number']) {
            $update_fields[] = "number = ?";
            $params[] = $number;
            $types .= 's';
        }
        if ($email !== $current['email']) {
            $update_fields[] = "email = ?";
            $params[] = $email;
            $types .= 's';
        }
        if (!empty($password)) {
            $update_fields[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
            $types .= 's';
        }
        if ($status !== $current['status']) {
            $update_fields[] = "status = ?";
            $params[] = (int)$status;
            $types .= 'i';
        }
        if ($role !== $current['role']) {
            $update_fields[] = "role = ?";
            $params[] = $role;
            $types .= 's';
        }
        if ($employee_id !== $current['employee_id']) {
            $update_fields[] = "employee_id = ?";
            $params[] = $employee_id;
            $types .= 's';
        }
        if ($designation !== $current['designation']) {
            $update_fields[] = "designation = ?";
            $params[] = $designation;
            $types .= 's';
        }
        if ($department !== $current['department']) {
            $update_fields[] = "department = ?";
            $params[] = $department;
            $types .= 's';
        }
        if ($joining_date !== $current['joining_date']) {
            $update_fields[] = "joining_date = ?";
            $params[] = $joining_date;
            $types .= 's';
        }

        if (!empty($update_fields)) {
            $update_fields[] = "updated_by = ?";
            $update_fields[] = "updated_at = NOW()";
            $params[] = $admin_id;
            $types .= 'i';

            $params[] = $id;
            $params[] = $admin_id;
            $types .= 'ii';

            $query = "UPDATE create_users SET " . implode(", ", $update_fields) . " WHERE id = ? AND created_by = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param($types, ...$params);

            if (!$stmt->execute()) {
                throw new Exception("Update failed: " . $conn->error);
            }
        }

        $success_message = "Staff user updated successfully.";
    } catch (Exception $e) {
        error_log("Error in create-user.php: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user']) && $admin_id) {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }

        $id = $_POST['id'] ?? '';
        if ($id) {
            $id = (int)$id;
            $admin_id = (int)$admin_id;

            $stmt = $conn->prepare("SELECT name FROM create_users WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Staff user not found or unauthorized access");
            }

            $user_name = $result->fetch_assoc()['name'];

            $stmt = $conn->prepare("DELETE FROM create_users WHERE id = ? AND created_by = ?");
            $stmt->bind_param("ii", $id, $admin_id);

            if (!$stmt->execute()) {
                throw new Exception("Delete failed: " . $conn->error);
            }

            $success_message = "Staff user '$user_name' deleted successfully.";
        } else {
            throw new Exception("Missing id for delete");
        }
    } catch (Exception $e) {
        error_log("Error in create-user.php: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}

// Pagination setup
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    if ($admin_id) {
        $admin_id = (int)$admin_id;

        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM create_users WHERE created_by = ?");
        $count_stmt->bind_param("i", $admin_id);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['count'];

        $stmt = $conn->prepare("
            SELECT id, name, number, email, status, role, employee_id, designation, department, joining_date, created_at, updated_at
            FROM create_users
            WHERE created_by = ?
            ORDER BY created_at DESC
            LIMIT ?, ?
        ");
        $stmt->bind_param("iii", $admin_id, $start_from, $records_per_page);
        $stmt->execute();
        $result = $stmt->get_result();

        $total_pages = ceil($total_records / $records_per_page);
        $all_permissions = $permissions_helper->getAllPermissions();
    }
} catch (Exception $e) {
    error_log("Pagination Error in create-user.php: " . $e->getMessage());
    $error_message = "Pagination Error: " . $e->getMessage();
    $result = null;
    $all_permissions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?> - GROW BILLER</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220;
            --brand-mid: #0ea5e9;
            --brand-light: #e0f2fe;
            --bg: #f4f8fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --success: #10b981;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --warning: #f59e0b;
            --warning-bg: #fed7aa;
            --info: #3b82f6;
            --info-bg: #dbeafe;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 1px 3px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.05);
            --transition: all 0.25s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        
        .page-wrap {
            margin-top: 70px;
            margin-left: 260px;
            padding: 24px 28px 40px;
            transition: var(--transition);
            min-height: calc(100vh - 70px);
        }
        @media (max-width: 992px) {
            .page-wrap { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 576px) {
            .page-wrap { padding: 12px; }
        }
        
        .hero-banner {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        @media (max-width: 768px) {
            .hero-banner {
                padding: 14px 18px;
                flex-direction: column;
                text-align: center;
            }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hero-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.7rem;
        }
        .hero-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 5px 14px;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .search-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .search-input {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        .search-input i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        .search-input input {
            width: 100%;
            padding: 8px 12px 8px 35px;
            border: 1.5px solid var(--border);
            border-radius: 50px;
            font-size: 0.8rem;
            transition: var(--transition);
            background: #fafbfc;
        }
        .search-input input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            outline: none;
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(2,132,199,0.3);
            color: #fff;
        }
        
        .card-premium {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
        }
        .card-premium:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header-premium {
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
            padding: 10px 16px;
            border-bottom: 1px solid var(--border);
        }
        .card-header-premium h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .card-body-premium {
            padding: 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 900px;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 10px 12px;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f4f9fd;
        }
        
        .action-btns {
            display: flex;
            gap: 6px;
        }
        .action-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            transition: var(--transition);
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
        .action-btn.delete { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .pagination-custom a {
            padding: 5px 12px;
            border-radius: 6px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.7rem;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        .pagination-custom a.active, .pagination-custom a:hover {
            background: var(--brand);
            color: #fff;
        }
        
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 8px 14px;
            margin-bottom: 16px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success-custom {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
        }
        .alert-danger-custom {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
        }
        
        .modal-content {
            border-radius: var(--radius-md);
            border: none;
            box-shadow: var(--shadow-md);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            border: none;
        }
        .modal-header .btn-close {
            filter: invert(1);
        }
        .modal-title {
            font-size: 0.9rem;
            font-weight: 700;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-mid);
            margin-bottom: 5px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 8px 12px;
            font-size: 0.8rem;
            transition: var(--transition);
            background: #fafbfc;
            width: 100%;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            outline: none;
            background: #fff;
        }
        
        .badge-active { background: var(--success-bg); color: var(--success); padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .badge-inactive { background: var(--danger-bg); color: var(--danger); padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        .badge-role { background: var(--info-bg); color: var(--info); padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
        
        .staff-count {
            background: var(--brand-light);
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--brand);
        }
        
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
            }
            .data-table {
                min-width: 700px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-users-cog"></i>
                Staff Management
            </div>
            <div class="hero-sub">Manage your staff users (Maximum 5 users allowed)</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-user-friends me-1"></i>
            Staff Count: <?php echo $total_records ?? 0; ?>/5
        </div>
    </div>

    <div class="search-bar">
        <div class="search-input">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, email, employee ID..." onkeyup="searchTable()">
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#userModal" id="addUserTrigger" <?php echo ($total_records ?? 0) >= 5 ? 'disabled' : ''; ?>>
                <i class="fas fa-user-plus"></i> Add Staff
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #16a34a, #15803d);" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button class="btn-primary-premium" style="background: linear-gradient(135deg, #6b7280, #4b5563);" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-question-circle"></i> Help
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card-premium">
        <div class="card-header-premium">
            <h5><i class="fas fa-list"></i> Staff List</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="userTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joining Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><span class="badge" style="background: var(--brand-light); color: var(--brand);"><?php echo htmlspecialchars($row['employee_id'] ?? 'N/A'); ?></span></td>
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['designation'] ?? 'Staff'); ?></td>
                                    <td><small><?php echo htmlspecialchars($row['email']); ?></small></td>
                                    <td><?php echo htmlspecialchars($row['number']); ?></td>
                                    <td><span class="badge-role"><?php echo ucfirst($row['role'] ?? 'staff'); ?></span></td>
                                    <td><span class="<?php echo $row['status'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $row['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td><?php echo $row['joining_date'] ? date('d M Y', strtotime($row['joining_date'])) : '-'; ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a class="action-btn edit" href="#"
                                               onclick='editUser(<?php echo json_encode($row); ?>); return false;'><i class="fas fa-pen"></i></a>
                                            <a class="action-btn delete" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                               onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>; document.getElementById('deleteUserName').innerHTML = '<?php echo addslashes($row['name']); ?>';"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='10' style='text-align:center; padding:40px;'>No staff users found. <a href='#' data-bs-toggle='modal' data-bs-target='#userModal' style='color:var(--brand);'>Add your first staff user</a></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($result && $total_pages > 1): ?>
            <div class="pagination-custom">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo ($page + 1); ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Staff User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm" action="create-user.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" id="userId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Employee ID</label>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" placeholder="Auto-generated if empty">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="number" name="number" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Designation</label>
                                <input type="text" class="form-control" id="designation" name="designation" placeholder="e.g., Sales Executive">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <select class="form-select" id="department" name="department">
                                    <option value="">Select Department</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Purchase">Purchase</option>
                                    <option value="Inventory">Inventory</option>
                                    <option value="Accounts">Accounts</option>
                                    <option value="Operations">Operations</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="staff">Staff</option>
                                    <option value="manager">Manager</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Joining Date</label>
                                <input type="date" class="form-control" id="joining_date" name="joining_date">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password <span class="text-danger" id="passwordReq">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> <strong>Staff Access:</strong> Staff users automatically get access to all features except Salary Management and Staff Management (Admin only).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn-primary-premium" id="addUserBtn"><i class="fas fa-save"></i> Add Staff User</button>
                    <button type="submit" name="edit_user" class="btn-primary-premium" id="editUserBtn" style="display:none;"><i class="fas fa-update"></i> Update Staff User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626; margin-bottom: 10px;"></i>
                    <p>Are you sure you want to delete the staff user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_user" class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 8px 20px;">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-question-circle me-2"></i>Staff Management Help</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>How to Use the Staff Management System:</strong></p>
                <ul>
                    <li><strong>Add Staff:</strong> Click "Add Staff" to create new staff accounts (maximum 5 allowed).</li>
                    <li><strong>Edit Staff:</strong> Click the edit icon to modify staff details.</li>
                    <li><strong>Delete Staff:</strong> Click the trash icon to permanently remove a staff user.</li>
                    <li><strong>Employee ID:</strong> Auto-generated if not provided.</li>
                </ul>
                <p><strong>Staff Access:</strong> Staff users automatically get access to ALL features except:</p>
                <ul>
                    <li>Salary Management (Admin only)</li>
                    <li>Staff Management (Admin only)</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary-premium" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(document).ready(function() {
    // Reset the form + buttons every time ANY close of the modal happens
    $('#userModal').on('hidden.bs.modal', function() {
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#addUserBtn').show();
        $('#editUserBtn').hide();
        $('#password').attr('required', true);
        $('#passwordReq').show();
        $('#userModal .modal-title').html('<i class="fas fa-user-plus me-2"></i>Add Staff User');

        // Safety cleanup: if no modal is open anymore but a stray backdrop
        // is still in the DOM (can happen if a modal was opened/closed
        // programmatically more than once), remove it so the page doesn't
        // become unresponsive.
        if ($('.modal.show').length === 0) {
            $('body').removeClass('modal-open').css({ 'padding-right': '', 'overflow': '' });
            $('.modal-backdrop').remove();
        }
    });
    
    $('#addUserTrigger').on('click', function() {
        $('#userForm')[0].reset();
        $('#userId').val('');
        $('#addUserBtn').show();
        $('#editUserBtn').hide();
        $('#password').attr('required', true);
        $('#passwordReq').show();
        $('#userModal .modal-title').html('<i class="fas fa-user-plus me-2"></i>Add Staff User');
    });
});

function editUser(user) {
    $('#userForm')[0].reset();
    $('#userId').val(user.id);
    $('#name').val(user.name);
    $('#number').val(user.number);
    $('#email').val(user.email);
    $('#status').val(user.status);
    $('#role').val(user.role || 'staff');
    $('#employee_id').val(user.employee_id || '');
    $('#designation').val(user.designation || '');
    $('#department').val(user.department || '');
    $('#joining_date').val(user.joining_date || '');
    $('#password').val('').removeAttr('required');
    $('#passwordReq').hide();
    $('#addUserBtn').hide();
    $('#editUserBtn').show();
    $('#userModal .modal-title').html('<i class="fas fa-edit me-2"></i>Edit Staff User');

    // Only one place opens the modal now (here), so we never end up with
    // two stacked modal instances / two backdrops.
    let existing = bootstrap.Modal.getInstance(document.getElementById('userModal'));
    if (!existing) {
        existing = new bootstrap.Modal(document.getElementById('userModal'));
    }
    existing.show();
}

function togglePassword() {
    const pwd = $('#password');
    const icon = $('#toggleIcon');
    if (pwd.attr('type') === 'password') {
        pwd.attr('type', 'text');
        icon.removeClass('fa-eye').addClass('fa-eye-slash');
    } else {
        pwd.attr('type', 'password');
        icon.removeClass('fa-eye-slash').addClass('fa-eye');
    }
}

function searchTable() {
    let input = $('#searchInput').val().toLowerCase();
    $('#userTable tbody tr').each(function() {
        let text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(input) > -1);
    });
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFillColor(11, 18, 32);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Staff List Report', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [];
    $('#userTable tbody tr:visible').each(function(i) {
        const cells = $(this).find('td');
        rows.push([
            cells.eq(0).text(),
            cells.eq(1).text(),
            cells.eq(2).text(),
            cells.eq(3).text(),
            cells.eq(4).text(),
            cells.eq(5).text(),
            cells.eq(6).text(),
            cells.eq(7).text(),
            cells.eq(8).text()
        ]);
    });
    
    doc.autoTable({
        head: [['S.No', 'Employee ID', 'Name', 'Designation', 'Email', 'Phone', 'Role', 'Status', 'Joining Date']],
        body: rows,
        startY: 32,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [11, 18, 32], textColor: 255, fontStyle: 'bold' }
    });
    doc.save('staff_list.pdf');
}

function exportToExcel() {
    const ws_data = [['S.No', 'Employee ID', 'Name', 'Designation', 'Email', 'Phone', 'Role', 'Status', 'Joining Date']];
    $('#userTable tbody tr:visible').each(function(i) {
        const cells = $(this).find('td');
        ws_data.push([
            cells.eq(0).text(),
            cells.eq(1).text(),
            cells.eq(2).text(),
            cells.eq(3).text(),
            cells.eq(4).text(),
            cells.eq(5).text(),
            cells.eq(6).text(),
            cells.eq(7).text(),
            cells.eq(8).text()
        ]);
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'Staff List');
    XLSX.writeFile(wb, 'staff_list.xlsx');
}

setTimeout(function() {
    $('.alert-custom').fadeOut(500, function() { $(this).remove(); });
}, 3000);
</script>
</body>
</html>
<?php ob_end_flush(); ?>