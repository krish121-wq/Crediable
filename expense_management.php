<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include('layout.php');
$page_title = "Expenses";
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];
if ($success_message) {
    unset($_SESSION['success_message']);
}

$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? date('Y-m-d');
$category_filter = $_POST['category_filter'] ?? $_GET['category_filter'] ?? '';
$expenses = [];
$total_expenses = 0;
try {
    $stmt = $conn->prepare("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name ASC");
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
try {
   
    $query = "
        SELECT 
            e.*,
            CASE
                WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                WHEN staff_users.name IS NOT NULL THEN staff_users.name
                ELSE 'Unknown'
            END AS created_by_name
        FROM expenses e
        LEFT JOIN users admin_users ON e.created_by = admin_users.id
        LEFT JOIN create_users staff_users ON e.created_by = staff_users.id
        WHERE e.expense_date BETWEEN ? AND ?
    ";
    
    $params = [$from_date, $to_date];
    $types = "ss";
    
  
    if (!empty($category_filter)) {
        $query .= " AND e.expense_category = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    
   
    if ($user_type !== 'admin') {
        $query .= " AND e.created_by = ?";
        $params[] = $created_by_id;
        $types .= "i";
    }
    
    $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $total_expenses = array_sum(array_column($expenses, 'amount'));
    
} catch (Exception $e) {
    $error_message = "Error fetching expenses: " . $e->getMessage();
    $expenses = [];
}

// Handle expense deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_expense'])) {
    try {
        $expense_id = (int)($_POST['expense_id'] ?? 0);
        
        if ($expense_id <= 0) {
            throw new Exception("Invalid expense ID");
        }
        
        // Delete expense
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $expense_id, $created_by_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Expense deleted successfully!";
            header('Location: expense_management.php');
            exit();
        } else {
            throw new Exception("Failed to delete expense");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle category creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    try {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_description = trim($_POST['category_description'] ?? '');
        
        if (empty($category_name)) {
            throw new Exception("Category name is required");
        }
        
        // Check if category already exists
        $check_stmt = $conn->prepare("SELECT id FROM expense_categories WHERE category_name = ? AND is_active = 1");
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Category already exists");
        }
        $check_stmt->close();
        
        // Insert new category
        $stmt = $conn->prepare("
            INSERT INTO expense_categories (category_name, description, created_at, is_active) 
            VALUES (?, ?, NOW(), 1)
        ");
        $stmt->bind_param("ss", $category_name, $category_description);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Category created successfully!";
            header('Location: expense_management.php');
            exit();
        } else {
            throw new Exception("Failed to create category");
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    try {
        $expense_category = $_POST['expense_category'] ?? '';
        $expense_date = $_POST['expense_date'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = $_POST['description'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $receipt_number = $_POST['receipt_number'] ?? '';
        $vendor_name = $_POST['vendor_name'] ?? '';
        $notes = $_POST['notes'] ?? '';
        if (empty($expense_category) || empty($expense_date) || $amount <= 0) {
            throw new Exception("Please fill in all required fields with valid data");
        }
        
        $stmt = $conn->prepare("
            INSERT INTO expenses (
                expense_category, expense_date, amount, description, 
                payment_method, receipt_number, vendor_name, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssdsssssi", 
            $expense_category, $expense_date, $amount, $description,
            $payment_method, $receipt_number, $vendor_name, $notes, $created_by_id
        );
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success_message'] = "Expense added successfully!";
        header('Location: expense_management.php');
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['filter_expenses'])) {
    try {
       
        $query = "
            SELECT 
                e.*,
                CASE
                    WHEN admin_users.id IS NOT NULL THEN COALESCE(admin_users.full_name, 'Admin')
                    WHEN staff_users.name IS NOT NULL THEN staff_users.name
                    ELSE 'Unknown'
                END AS created_by_name
            FROM expenses e
            LEFT JOIN users admin_users ON e.created_by = admin_users.id
            LEFT JOIN create_users staff_users ON e.created_by = staff_users.id
            WHERE e.expense_date BETWEEN ? AND ?
        ";
        
        $params = [$from_date, $to_date];
        $types = "ss";
        if (!empty($category_filter)) {
            $query .= " AND e.expense_category = ?";
            $params[] = $category_filter;
            $types .= "s";
        }
        if ($user_type !== 'admin') {
            $query .= " AND e.created_by = ?";
            $params[] = $created_by_id;
            $types .= "i";
        }
        
        $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $total_expenses = array_sum(array_column($expenses, 'amount'));
        
    } catch (Exception $e) {
        $error_message = "Error fetching expenses: " . $e->getMessage();
        $expenses = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Management — CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            font-size: 14.2px;
            line-height: 1.5;
        }

        .content {
            margin-top: 65px;
            margin-left: 260px;
            padding: 24px 20px 60px;
            min-height: calc(100vh - 65px);
            transition: var(--transition);
        }

        .content-full { margin-left: 0; }

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

        .form-section, .filter-section, .form-section-full {
            background: var(--card);
            padding: 22px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--brand);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group { margin-bottom: 15px; }

        .form-label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
            color: var(--text-mid);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: var(--text);
            transition: var(--transition);
            background: #fafafa;
        }

        .form-control:focus {
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
            outline: none;
        }

        textarea.form-control { resize: vertical; min-height: 80px; }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
            display: inline-flex; align-items: center; gap: 8px; justify-content: center;
        }

        .btn-primary, .btn-filter {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff !important;
            box-shadow: 0 4px 14px rgba(2,132,199,0.25);
        }

        .btn-primary:hover, .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(2,132,199,0.35);
        }

        .btn-filter { width: 100%; }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            color: white;
            display: flex; flex-direction: column; justify-content: center;
            border: none;
        }

        .summary-card.total { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .summary-card.count { background: linear-gradient(135deg, var(--brand-mid), var(--brand)); }

        .summary-label {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 5px;
            text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;
        }

        .summary-value {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
        }

        .table-responsive {
            background: var(--card);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow-x: auto;
            margin-top: 20px;
        }

        .table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .table th {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: white;
            padding: 12px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
            white-space: nowrap;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 14px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.88rem;
            color: var(--text);
        }

        .table tbody tr:last-child td { border-bottom: none; }
        .table tr:hover td { background: #f8fafc; }

        .table .amount { color: var(--danger); font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;}

        .category-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--brand-light);
            color: var(--brand);
            white-space: nowrap;
        }

        .payment-method-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--warning-bg);
            color: var(--warning);
            white-space: nowrap;
        }

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

        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3; }

        .btn-sm.btn-danger {
            width: 32px; height: 32px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            border: none; padding: 0; transition: var(--transition);
            box-shadow: 0 3px 10px rgba(239,68,68,0.25);
        }
        .btn-sm.btn-danger:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239,68,68,0.35); }

        /* Modals */
        .modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); }
        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff; border-radius: var(--radius-md) var(--radius-md) 0 0; padding: 18px 22px;
        }
        .modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem; font-weight: 700; margin: 0; }
        .modal-body { padding: 22px; }
        .modal-footer { padding: 14px 22px; border-top: 1px solid var(--border); gap: 10px; }
        .btn-close-white { filter: invert(1); }

        @media (max-width: 1024px) {
            .content { margin-left: 0; padding: 18px; }
            .form-row, .form-row-3 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        }

        @media (max-width: 768px) {
            .content { padding: 16px 12px; }
            .page-hero { flex-direction: column; align-items: stretch; }
            .form-section, .form-section-full, .filter-section { padding: 18px; }
            .form-row, .form-row-3, .summary-cards { grid-template-columns: 1fr; }
            .table th, .table td { padding: 10px 8px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    
    <!-- PAGE HERO -->
    <div class="page-hero">
        <div class="page-title">
            <h4><i class="fas fa-money-bill-wave me-2" style="opacity:.85"></i>Expenses</h4>
            <h6>Record and manage business expenses</h6>
        </div>
        <div class="page-btn-group">
            <button type="button" class="btn btn-success shadow-sm" onclick="exportExpensesToExcel()" style="background:#16a34a; border:none; border-radius:50px; padding:9px 20px; font-size:0.83rem; font-weight:700; color:#fff;">
                <i class="fas fa-file-excel me-1"></i> Export to Excel
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success" id="successAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

   
    <div class="form-section-full">
        <h5 class="section-title">
            <i class="fas fa-plus-circle"></i> Add New Expense
        </h5>
        <form method="post" action="expense_management.php">
            <input type="hidden" name="add_expense" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="expense_category" class="form-label">Category *</label>
                    <div class="d-flex gap-2">
                        <select class="form-control" id="expense_category" name="expense_category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="openCategoryModal()" style="height: 44px; padding: 0 15px; border-radius: 8px;">
                            <i class="fas fa-plus"></i> 
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="expense_date" class="form-label">Date *</label>
                    <input type="date" class="form-control" id="expense_date" name="expense_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="amount" class="form-label">Amount (₹) *</label>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           step="0.01" min="0" placeholder="0.00" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" 
                           placeholder="Brief description of expense">
                </div>

                <div class="form-group">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method">
                        <option value="">Select Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit Card">Debit Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="vendor_name" class="form-label">Vendor Name</label>
                    <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                           placeholder="Vendor or supplier name">
                </div>
            </div>

            <div class="form-row-3">
                <div class="form-group">
                    <label for="receipt_number" class="form-label">Receipt Number</label>
                    <input type="text" class="form-control" id="receipt_number" name="receipt_number" 
                           placeholder="Receipt or invoice number">
                </div>

                <div class="form-group">
                    <label for="notes" class="form-label">Additional Notes</label>
                    <textarea class="form-control" id="notes" name="notes" 
                              placeholder="Any additional notes about this expense"></textarea>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save me-1"></i> Add Expense
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <h5 class="section-title">
            <i class="fas fa-filter"></i> Filter Expenses
        </h5>
        <form method="post" action="expense_management.php">
            <input type="hidden" name="filter_expenses" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="from_date" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" 
                           value="<?php echo htmlspecialchars($from_date); ?>" required>
                </div>

                <div class="form-group">
                    <label for="to_date" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" 
                           value="<?php echo htmlspecialchars($to_date); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category_filter" class="form-label">Category</label>
                    <select class="form-control" id="category_filter" name="category_filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category_name']); ?>" 
                                    <?php echo ($category_filter == $category['category_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-filter">
                        <i class="fas fa-search me-1"></i> Filter Expenses
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($expenses)): ?>
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card total">
                <div class="summary-label">Total Expenses</div>
                <div class="summary-value">₹ <?php echo number_format($total_expenses, 2); ?></div>
            </div>
            <div class="summary-card count">
                <div class="summary-label">Total Entries</div>
                <div class="summary-value"><?php echo count($expenses); ?></div>
            </div>
        </div>

       
        <div class="table-responsive">
            <table class="table" id="expensesDataTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Vendor</th>
                        <th>Receipt #</th>
                        <th>Added By</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                            <td>
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($expense['expense_category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['description'] ?? '-'); ?></td>
                            <td class="amount">₹ <?php echo number_format($expense['amount'], 2); ?></td>
                            <td>
                                <?php if ($expense['payment_method']): ?>
                                    <span class="payment-method-badge">
                                        <?php echo htmlspecialchars($expense['payment_method']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($expense['vendor_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($expense['receipt_number'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($expense['created_by_name']); ?></td>
                            <td style="text-align:center; display:flex; justify-content:center; gap:6px;">
                                <a href="print_receipt.php?id=<?php echo $expense['id']; ?>&type=out" target="_blank" class="btn btn-sm btn-info" style="width:32px;height:32px;padding:0;background:#0284c7;color:#fff;border-radius:8px;" title="Print Payment Out Voucher">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteExpense(<?php echo $expense['id']; ?>)" title="Delete Expense">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p style="font-weight:700;font-size:1.1rem;color:var(--text);margin-bottom:4px;">No expenses found for the current month</p>
                <small>Add your first expense using the form above</small>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
   
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.display = 'none';
        }, 3000);
    }
    

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('to_date').max = today;
    document.getElementById('from_date').max = today;
    document.getElementById('expense_date').max = today;
    
    document.getElementById('to_date').addEventListener('change', function() {
        const fromDate = document.getElementById('from_date');
        if (fromDate.value > this.value) {
            fromDate.value = this.value;
        }
    });
});
</script>


<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createCategoryModalLabel">
                    <i class="fas fa-plus-circle me-2"></i> Create New Category
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="expense_management.php" autocomplete="off">
                <input type="hidden" name="add_category" value="1">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="category_name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" 
                               placeholder="Enter category name" required>
                    </div>
                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" style="border-radius:8px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px;">
                        <i class="fas fa-save me-1"></i> Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCategoryModal() {
    $('#createCategoryModal').modal('show');
}

function deleteExpense(expenseId) {
    $('#deleteExpenseId').val(expenseId);
    $('#deleteExpenseModal').modal('show');
}

function exportExpensesToExcel() {
    const table = document.getElementById('expensesDataTable');
    if (!table) {
        alert('No expense data available to export.');
        return;
    }

    try {
        if (typeof XLSX !== 'undefined') {
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.table_to_sheet(table);
            XLSX.utils.book_append_sheet(wb, ws, "Payment Out");
            const filename = "payment_out_expenses_" + new Date().toISOString().slice(0,10) + ".xlsx";
            XLSX.writeFile(wb, filename);
            return;
        }
    } catch(e) {
        console.error("XLSX export fallback:", e);
    }
    exportExpensesToCSV();
}

function exportExpensesToCSV() {
    const table = document.getElementById('expensesDataTable');
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll('tr');
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll('td, th');
        // skip last column (Actions)
        for (let j = 0; j < cols.length - 1; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""');
            row.push('"' + data.trim() + '"');
        }
        csv.push(row.join(','));
    }
    const csvString = csv.join('\n');
    const blob = new Blob(["\uFEFF" + csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = "payment_out_expenses_" + new Date().toISOString().slice(0,10) + ".csv";
    link.click();
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<!-- Delete Expense Confirmation Modal -->
<div class="modal fade" id="deleteExpenseModal" tabindex="-1" aria-labelledby="deleteExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444, #b91c1c);">
                <h5 class="modal-title" id="deleteExpenseModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="expense_management.php">
                <input type="hidden" name="delete_expense" value="1">
                <input type="hidden" name="expense_id" id="deleteExpenseId">
                <div class="modal-body" style="text-align:center; padding: 25px;">
                    <div style="width:60px;height:60px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.7rem;color:var(--danger)">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <h5 style="font-weight:700;margin-bottom:7px;">Delete this expense?</h5>
                    <p style="color:var(--text-muted);font-size:.85rem;margin:0;">Are you sure you want to delete this expense? This action cannot be undone.</p>
                </div>
                <div class="modal-footer" style="justify-content:center; gap: 10px;">
                    <button type="button" class="btn btn-secondary" style="border-radius:8px;" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background: linear-gradient(135deg, #ef4444, #b91c1c); border: none; border-radius:8px;">
                        <i class="fas fa-trash me-1"></i> Delete Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php ob_end_flush(); ?>