<?php
require_once 'config.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Salary Management";
$success_message = '';
$error_message = '';
$admin_id = (int)$_SESSION['user_id'];

// Fetch employees for dropdown
try {
    $result = $conn->query("SELECT id, name FROM create_users WHERE status = 1");
    $employees = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Fetch active PF/ESI rates
try {
    $result = $conn->query("SELECT pf_employee_rate, pf_employer_rate, pf_salary_cap, esi_employee_rate, esi_employer_rate, esi_salary_cap FROM pf_esi_rates WHERE status = 'active' LIMIT 1");
    $rates = $result->fetch_assoc();
    if (!$rates) {
        $rates = [
            'pf_employee_rate' => 12.00, 'pf_employer_rate' => 12.00, 'pf_salary_cap' => 15000.00,
            'esi_employee_rate' => 0.75, 'esi_employer_rate' => 3.25, 'esi_salary_cap' => 21000.00
        ];
    }
} catch (Exception $e) {
    $rates = [
        'pf_employee_rate' => 12.00, 'pf_employer_rate' => 12.00, 'pf_salary_cap' => 15000.00,
        'esi_employee_rate' => 0.75, 'esi_employer_rate' => 3.25, 'esi_salary_cap' => 21000.00
    ];
}

// Fetch company details
try {
    $result = $conn->query("SELECT company_name, company_address, company_gst, company_logo FROM company_details LIMIT 1");
    $company = $result->fetch_assoc();
    if (!$company) {
        $company = ['company_name' => 'Grow Biller Pvt Ltd', 'company_address' => 'Mumbai, India', 'company_gst' => '22AAAAA0000A1Z5', 'company_logo' => ''];
    }
} catch (Exception $e) {
    $company = ['company_name' => 'Grow Biller Pvt Ltd', 'company_address' => 'Mumbai, India', 'company_gst' => '22AAAAA0000A1Z5', 'company_logo' => ''];
}

// Handle form submissions (SAME LOGIC - NO CHANGE)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_salary_structure'])) {
            $user_id = (int)$_POST['user_id'];
            $basic_salary = floatval($_POST['basic_salary']);
            $hra = floatval($_POST['hra'] ?? 0);
            $travel_allowance = floatval($_POST['travel_allowance'] ?? 0);
            $other_allowances = floatval($_POST['other_allowances'] ?? 0);
            $fixed_deductions = floatval($_POST['fixed_deductions'] ?? 0);
            $status = $_POST['status'];

            $check = $conn->query("SELECT id FROM salary_structures WHERE user_id = $user_id AND status = 'active'");
            if ($check->num_rows > 0) throw new Exception("Active salary structure already exists for this employee.");

            $conn->query("INSERT INTO salary_structures (user_id, basic_salary, hra, travel_allowance, other_allowances, fixed_deductions, status, created_by, created_at) VALUES ($user_id, $basic_salary, $hra, $travel_allowance, $other_allowances, $fixed_deductions, '$status', $admin_id, NOW())");
            $success_message = "Salary structure added successfully!";
        }
        elseif (isset($_POST['edit_salary_structure'])) {
            $id = (int)$_POST['id'];
            $user_id = (int)$_POST['user_id'];
            $basic_salary = floatval($_POST['basic_salary']);
            $hra = floatval($_POST['hra'] ?? 0);
            $travel_allowance = floatval($_POST['travel_allowance'] ?? 0);
            $other_allowances = floatval($_POST['other_allowances'] ?? 0);
            $fixed_deductions = floatval($_POST['fixed_deductions'] ?? 0);
            $status = $_POST['status'];

            $conn->query("UPDATE salary_structures SET user_id = $user_id, basic_salary = $basic_salary, hra = $hra, travel_allowance = $travel_allowance, other_allowances = $other_allowances, fixed_deductions = $fixed_deductions, status = '$status', updated_by = $admin_id, updated_at = NOW() WHERE id = $id");
            $success_message = "Salary structure updated successfully!";
        }
        elseif (isset($_POST['process_payroll'])) {
            $user_id = (int)$_POST['user_id'];
            $payroll_month = $_POST['payroll_month'] . '-01';

            $salary = $conn->query("SELECT id, basic_salary, hra, travel_allowance, other_allowances, fixed_deductions FROM salary_structures WHERE user_id = $user_id AND status = 'active'")->fetch_assoc();
            if (!$salary) throw new Exception("No active salary structure found.");

            $check = $conn->query("SELECT id FROM payroll_records WHERE user_id = $user_id AND payroll_month = '$payroll_month'");
            if ($check->num_rows > 0) throw new Exception("Payroll already processed for this month.");

            $gross = $salary['basic_salary'] + $salary['hra'] + $salary['travel_allowance'] + $salary['other_allowances'];
            $pf_base = min($salary['basic_salary'], $rates['pf_salary_cap']);
            $pf_emp = $pf_base * ($rates['pf_employee_rate'] / 100);
            $pf_er = $pf_base * ($rates['pf_employer_rate'] / 100);
            $esi_emp = $gross <= $rates['esi_salary_cap'] ? $gross * ($rates['esi_employee_rate'] / 100) : 0;
            $esi_er = $gross <= $rates['esi_salary_cap'] ? $gross * ($rates['esi_employer_rate'] / 100) : 0;
            $net = $gross - $salary['fixed_deductions'] - $pf_emp - $esi_emp;

            $conn->query("INSERT INTO payroll_records (user_id, salary_structure_id, payroll_month, basic_salary, hra, travel_allowance, other_allowances, fixed_deductions, gross_salary, net_salary, pf_employee_contribution, pf_employer_contribution, esi_employee_contribution, esi_employer_contribution, created_by, status) VALUES ($user_id, {$salary['id']}, '$payroll_month', {$salary['basic_salary']}, {$salary['hra']}, {$salary['travel_allowance']}, {$salary['other_allowances']}, {$salary['fixed_deductions']}, $gross, $net, $pf_emp, $pf_er, $esi_emp, $esi_er, $admin_id, 'processed')");
            $success_message = "Payroll processed successfully!";
        }
        elseif (isset($_POST['update_pf_esi_rates'])) {
            $conn->query("UPDATE pf_esi_rates SET status = 'inactive' WHERE status = 'active'");
            $conn->query("INSERT INTO pf_esi_rates (pf_employee_rate, pf_employer_rate, pf_salary_cap, esi_employee_rate, esi_employer_rate, esi_salary_cap, created_by, status) VALUES ({$_POST['pf_employee_rate']}, {$_POST['pf_employer_rate']}, {$_POST['pf_salary_cap']}, {$_POST['esi_employee_rate']}, {$_POST['esi_employer_rate']}, {$_POST['esi_salary_cap']}, $admin_id, 'active')");
            $success_message = "PF/ESI rates updated successfully!";
        }
        elseif (isset($_POST['delete_salary_structure'])) {
            $id = (int)$_POST['id'];
            $conn->query("DELETE FROM salary_structures WHERE id = $id");
            $success_message = "Salary structure deleted successfully!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Pagination (SAME LOGIC)
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

$result = $conn->query("SELECT ss.*, cu.name FROM salary_structures ss JOIN create_users cu ON ss.user_id = cu.id ORDER BY ss.created_at DESC LIMIT $start_from, $records_per_page");
$total_records = $conn->query("SELECT COUNT(*) as total FROM salary_structures")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$payroll_result = $conn->query("SELECT pr.*, cu.name, cu.employee_id, cu.designation FROM payroll_records pr JOIN create_users cu ON pr.user_id = cu.id ORDER BY pr.payroll_month DESC LIMIT $start_from, $records_per_page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management - Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
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
            padding: 20px 28px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .hero-banner {
                padding: 16px 20px;
                flex-direction: column;
                text-align: center;
            }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .hero-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.8rem;
        }
        .hero-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 6px 16px;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .card-premium {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-premium:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header-premium {
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-header-premium h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body-premium {
            padding: 0;
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(2,132,199,0.3);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            min-width: 1000px;
        }
        .data-table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff;
            padding: 12px 15px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f4f9fd;
        }
        
        .badge-active { background: var(--success-bg); color: var(--success); padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-inactive { background: var(--danger-bg); color: var(--danger); padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        
        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .action-btn:hover {
            transform: translateY(-2px);
        }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
        .action-btn.delete { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .action-btn.payslip { background: linear-gradient(135deg, #0b1220, #1e293b); }
        
        .pagination-custom {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination-custom a {
            padding: 6px 14px;
            border-radius: 8px;
            background: var(--brand-light);
            color: var(--brand);
            font-size: 0.75rem;
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
            padding: 10px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
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
            font-size: 1rem;
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
            margin-bottom: 18px;
        }
        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-mid);
            margin-bottom: 5px;
            display: block;
        }
        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 8px 12px;
            font-size: 0.85rem;
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
        
        .btn-outline-premium {
            background: transparent;
            border: 1.5px solid var(--border);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
        }
        .btn-outline-premium:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }
        
        .action-header {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
            }
            .data-table {
                min-width: 800px;
            }
            .action-header {
                justify-content: flex-start;
                margin-top: 0;
            }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-rupee-sign"></i>
                Salary Management
            </div>
            <div class="hero-sub">Configure and process employee salaries, PF, ESI, and payslips</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-users me-1"></i>
            Payroll System
        </div>
    </div>

    <div class="action-header">
        <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#salaryStructureModal" id="addSalaryBtn">
            <i class="fas fa-plus"></i> Add Salary Structure
        </button>
        <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#processPayrollModal">
            <i class="fas fa-money-check"></i> Process Payroll
        </button>
        <button class="btn-primary-premium" data-bs-toggle="modal" data-bs-target="#pfEsiRatesModal">
            <i class="fas fa-sliders-h"></i> PF/ESI Settings
        </button>
        <button class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);" onclick="exportPayrollPDF()">
            <i class="fas fa-file-pdf"></i> PDF
        </button>
        <button class="btn-primary-premium" style="background: linear-gradient(135deg, #16a34a, #15803d);" onclick="exportPayrollExcel()">
            <i class="fas fa-file-excel"></i> Excel
        </button>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom" id="successAlert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Salary Structures Table -->
    <div class="card-premium">
        <div class="card-header-premium">
            <h5><i class="fas fa-list"></i> Salary Structures</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="salaryTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Employee</th>
                            <th>Basic Salary</th>
                            <th>HRA</th>
                            <th>Travel Allow.</th>
                            <th>Other Allow.</th>
                            <th>Fixed Deduct.</th>
                            <th>Status</th>
                            <th>Actions</th>
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
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td>₹<?php echo number_format($row['basic_salary'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['hra'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['travel_allowance'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['other_allowances'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['fixed_deductions'], 2); ?></td>
                                    <td><span class="<?php echo $row['status'] == 'active' ? 'badge-active' : 'badge-inactive'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn edit" data-bs-toggle="modal" data-bs-target="#salaryStructureModal" 
                                                onclick='editSalary(<?php echo json_encode($row); ?>)'><i class="fas fa-pen"></i></button>
                                            <button class="action-btn delete" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<td><td colspan='9' style='text-align:center; padding:40px;'>No salary structures found. <a href='#' data-bs-toggle='modal' data-bs-target='#salaryStructureModal' style='color:var(--brand);'>Add your first salary structure</a></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
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

    <!-- Payroll Records Table -->
    <div class="card-premium">
        <div class="card-header-premium">
            <h5><i class="fas fa-history"></i> Processed Payroll Records</h5>
        </div>
        <div class="card-body-premium">
            <div class="table-responsive">
                <table class="data-table" id="payrollTable">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Employee</th>
                            <th>Month</th>
                            <th>Gross Salary</th>
                            <th>PF (Emp)</th>
                            <th>ESI (Emp)</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($payroll_result && $payroll_result->num_rows > 0) {
                            while ($row = $payroll_result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><?php echo $sno++; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                    <td><?php echo date('M Y', strtotime($row['payroll_month'])); ?></td>
                                    <td><span style="color: var(--brand); font-weight: 700;">₹<?php echo number_format($row['gross_salary'], 2); ?></span></td>
                                    <td>₹<?php echo number_format($row['pf_employee_contribution'], 2); ?></td>
                                    <td>₹<?php echo number_format($row['esi_employee_contribution'], 2); ?></td>
                                    <td><span style="color: var(--success); font-weight: 700;">₹<?php echo number_format($row['net_salary'], 2); ?></span></td>
                                    <td><span class="badge-active">Processed</span></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="action-btn payslip" onclick="generatePayslip(<?php echo htmlspecialchars(json_encode($row)); ?>)"><i class="fas fa-file-pdf"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<td><td colspan='9' style='text-align:center; padding:40px;'>No payroll records found. <a href='#' data-bs-toggle='modal' data-bs-target='#processPayrollModal' style='color:var(--brand);'>Process your first payroll</a></td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Salary Structure Modal -->
<div class="modal fade" id="salaryStructureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Salary Structure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="salary_management.php">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="user_id" id="user_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Basic Salary <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="basic_salary" id="basic_salary" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">HRA</label>
                        <input type="number" step="0.01" class="form-control" name="hra" id="hra">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Travel Allowance</label>
                        <input type="number" step="0.01" class="form-control" name="travel_allowance" id="travel_allowance">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Other Allowances</label>
                        <input type="number" step="0.01" class="form-control" name="other_allowances" id="other_allowances">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fixed Deductions</label>
                        <input type="number" step="0.01" class="form-control" name="fixed_deductions" id="fixed_deductions">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" id="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_salary_structure" class="btn-primary-premium" id="addBtn"><i class="fas fa-save"></i> Save Structure</button>
                    <button type="submit" name="edit_salary_structure" class="btn-primary-premium" id="editBtn" style="display:none;"><i class="fas fa-save"></i> Update Structure</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processPayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-check me-2"></i>Process Payroll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="salary_management.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Payroll Month <span class="text-danger">*</span></label>
                        <input type="month" class="form-control" name="payroll_month" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="process_payroll" class="btn-primary-premium"><i class="fas fa-calculator"></i> Process Payroll</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PF/ESI Settings Modal -->
<div class="modal fade" id="pfEsiRatesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h me-2"></i>PF/ESI Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="salary_management.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">PF Employee Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="pf_employee_rate" value="<?php echo $rates['pf_employee_rate']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PF Employer Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="pf_employer_rate" value="<?php echo $rates['pf_employer_rate']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PF Salary Cap (₹)</label>
                        <input type="number" step="0.01" class="form-control" name="pf_salary_cap" value="<?php echo $rates['pf_salary_cap']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ESI Employee Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="esi_employee_rate" value="<?php echo $rates['esi_employee_rate']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ESI Employer Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="esi_employer_rate" value="<?php echo $rates['esi_employer_rate']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ESI Salary Cap (₹)</label>
                        <input type="number" step="0.01" class="form-control" name="esi_salary_cap" value="<?php echo $rates['esi_salary_cap']; ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_pf_esi_rates" class="btn-primary-premium"><i class="fas fa-save"></i> Update Rates</button>
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
            <form method="POST">
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626; margin-bottom: 10px;"></i>
                    <p>Are you sure you want to delete this salary structure?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn-outline-premium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_salary_structure" class="btn-primary-premium" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">Delete</button>
                </div>
            </form>
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
    setTimeout(function() {
        $('#successAlert').fadeOut(500, function() { $(this).remove(); });
    }, 3000);
    
    $('#addSalaryBtn').on('click', function() {
        $('#addBtn').show();
        $('#editBtn').hide();
        $('#edit_id').val('');
        $('#user_id').val('');
        $('#basic_salary').val('');
        $('#hra').val('');
        $('#travel_allowance').val('');
        $('#other_allowances').val('');
        $('#fixed_deductions').val('');
        $('#status').val('active');
        $('#salaryStructureModal .modal-title').html('<i class="fas fa-plus-circle me-2"></i>Add Salary Structure');
    });
});

function editSalary(data) {
    $('#edit_id').val(data.id);
    $('#user_id').val(data.user_id);
    $('#basic_salary').val(data.basic_salary);
    $('#hra').val(data.hra);
    $('#travel_allowance').val(data.travel_allowance);
    $('#other_allowances').val(data.other_allowances);
    $('#fixed_deductions').val(data.fixed_deductions);
    $('#status').val(data.status);
    $('#addBtn').hide();
    $('#editBtn').show();
    $('#salaryStructureModal .modal-title').html('<i class="fas fa-edit me-2"></i>Edit Salary Structure');
}

function generatePayslip(data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFillColor(11, 18, 32);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Salary Payslip', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [
        ['Employee Name', data.name],
        ['Employee ID', data.employee_id || 'N/A'],
        ['Designation', data.designation || 'N/A'],
        ['Payroll Month', new Date(data.payroll_month).toLocaleString('default', { month: 'long', year: 'numeric' })],
        ['Basic Salary', '₹' + parseFloat(data.basic_salary).toFixed(2)],
        ['HRA', '₹' + parseFloat(data.hra).toFixed(2)],
        ['Travel Allowance', '₹' + parseFloat(data.travel_allowance).toFixed(2)],
        ['Other Allowances', '₹' + parseFloat(data.other_allowances).toFixed(2)],
        ['Gross Salary', '₹' + parseFloat(data.gross_salary).toFixed(2)],
        ['Fixed Deductions', '₹' + parseFloat(data.fixed_deductions).toFixed(2)],
        ['PF Contribution', '₹' + parseFloat(data.pf_employee_contribution).toFixed(2)],
        ['ESI Contribution', '₹' + parseFloat(data.esi_employee_contribution).toFixed(2)],
        ['Net Salary', '₹' + parseFloat(data.net_salary).toFixed(2)]
    ];
    
    doc.autoTable({
        body: rows,
        startY: 32,
        styles: { fontSize: 10, cellPadding: 4 },
        columnStyles: { 0: { fontStyle: 'bold', cellWidth: 60 }, 1: { cellWidth: 120 } }
    });
    
    doc.text('* This is a computer generated document *', doc.internal.pageSize.width / 2, doc.lastAutoTable.finalY + 15, { align: 'center', fontSize: 8 });
    doc.save('payslip_' + data.name + '_' + data.payroll_month + '.pdf');
}

function exportPayrollPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFillColor(11, 18, 32);
    doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Payroll Report', doc.internal.pageSize.width / 2, 12, { align: 'center' });
    doc.setFontSize(9);
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN'), doc.internal.pageSize.width / 2, 22, { align: 'center' });
    doc.setTextColor(30, 30, 30);
    
    const rows = [];
    $('#payrollTable tbody tr:visible').each(function(i) {
        const cells = $(this).find('td');
        rows.push([
            cells.eq(0).text(),
            cells.eq(1).text(),
            cells.eq(2).text(),
            cells.eq(3).text(),
            cells.eq(4).text(),
            cells.eq(5).text(),
            cells.eq(6).text()
        ]);
    });
    
    doc.autoTable({
        head: [['S.No', 'Employee', 'Month', 'Gross Salary', 'PF', 'ESI', 'Net Salary']],
        body: rows,
        startY: 32,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [11, 18, 32], textColor: 255, fontStyle: 'bold' }
    });
    doc.save('payroll_report.pdf');
}

function exportPayrollExcel() {
    const ws_data = [['S.No', 'Employee', 'Month', 'Gross Salary', 'PF', 'ESI', 'Net Salary']];
    $('#payrollTable tbody tr:visible').each(function(i) {
        const cells = $(this).find('td');
        ws_data.push([
            cells.eq(0).text(),
            cells.eq(1).text(),
            cells.eq(2).text(),
            cells.eq(3).text(),
            cells.eq(4).text(),
            cells.eq(5).text(),
            cells.eq(6).text()
        ]);
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'Payroll Records');
    XLSX.writeFile(wb, 'payroll_records.xlsx');
}
</script>
</body>
</html>