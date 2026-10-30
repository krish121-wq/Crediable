<?php
require_once 'config.php';

// Start session and output buffering before including layout
if (session_status() == PHP_SESSION_NONE) session_start();

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('layout.php');

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = '';
$updated_by_id = (int)$_SESSION['user_id'];

// Check if bill ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid bill ID';
    header('Location: cash_bill_list.php');
    exit();
}

$bill_id = (int)$_GET['id'];

// Fetch existing bill data
$bill = null;
$bill_items = [];

try {
    // Get bill details
    $stmt = $conn->prepare("SELECT * FROM cash_bills WHERE id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill = $result->fetch_assoc();
    $stmt->close();
    
    if (!$bill) {
        throw new Exception("Bill not found");
    }
    
    // Get bill items
    $stmt = $conn->prepare("
        SELECT cbi.*, p.product_name, p.product_code, p.sell_price 
        FROM cash_bill_items cbi
        LEFT JOIN products p ON cbi.product_id = p.id
        WHERE cbi.cash_bill_id = ?
    ");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bill_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    $error_message = "Error fetching bill data: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cash_bill'])) {
    try {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        
        // Get products from JSON
        $products_json = $_POST['products_json'] ?? '[]';
        $products = json_decode($products_json, true);
        
        if (empty($mobile_number)) {
            throw new Exception("Mobile number is required.");
        }
        
        if (empty($products) || !is_array($products)) {
            throw new Exception("At least one product must be added.");
        }
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            $quantity = (int)($product['quantity'] ?? 0);
            $unit_price = floatval($product['unit_price'] ?? 0);
            
            if ($product_id <= 0 || $quantity <= 0) {
                throw new Exception("Invalid product or quantity.");
            }
            
            $total_amount += ($quantity * $unit_price);
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Update cash bill
        if (!empty($customer_name)) {
            $stmt = $conn->prepare("UPDATE cash_bills 
                                  SET customer_name = ?, mobile_number = ?, total_amount = ?, paid_amount = ?
                                  WHERE id = ?");
            $stmt->bind_param("ssddi", $customer_name, $mobile_number, $total_amount, $paid_amount, $bill_id);
        } else {
            $stmt = $conn->prepare("UPDATE cash_bills 
                                  SET customer_name = NULL, mobile_number = ?, total_amount = ?, paid_amount = ?
                                  WHERE id = ?");
            $stmt->bind_param("sddi", $mobile_number, $total_amount, $paid_amount, $bill_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update cash bill: " . $stmt->error);
        }
        $stmt->close();
        
        // Delete existing items
        $stmt = $conn->prepare("DELETE FROM cash_bill_items WHERE cash_bill_id = ?");
        $stmt->bind_param("i", $bill_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete existing bill items: " . $stmt->error);
        }
        $stmt->close();
        
        // Insert updated items
        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            $quantity = (int)($product['quantity'] ?? 0);
            $unit_price = floatval($product['unit_price'] ?? 0);
            $total_price = $quantity * $unit_price;
            
            $stmt = $conn->prepare("INSERT INTO cash_bill_items 
                                  (cash_bill_id, product_id, quantity, unit_price, total_price, created_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iiidd", $bill_id, $product_id, $quantity, $unit_price, $total_price);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert bill item: " . $stmt->error);
            }
            $stmt->close();
        }
        
        // Update payment record if paid amount changed
        if ($paid_amount > 0) {
            try {
                $stmt = $conn->prepare("SELECT id FROM cash_bill_payments WHERE cash_bill_id = ?");
                $stmt->bind_param('i', $bill_id);
                $stmt->execute();
                $payment_exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                
                if ($payment_exists) {
                    $stmt = $conn->prepare("UPDATE cash_bill_payments SET amount = ?, updated_at = NOW() WHERE cash_bill_id = ?");
                    $stmt->bind_param('di', $paid_amount, $bill_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO cash_bill_payments (cash_bill_id, amount, payment_method, payment_date, reference_no, notes, created_by, created_at) VALUES (?, ?, 'Cash', CURDATE(), 'Updated payment', 'Payment updated on bill update', ?, NOW())");
                    $stmt->bind_param('idi', $bill_id, $paid_amount, $updated_by_id);
                }
                
                if (!$stmt->execute()) {
                    error_log("Failed to update payment record: " . $stmt->error);
                }
                $stmt->close();
                
            } catch (Exception $e) {
                error_log("Error updating payment record: " . $e->getMessage());
            }
        }
        
        $conn->commit();
        
        $_SESSION['success_message'] = 'Cash bill ' . htmlspecialchars($bill['bill_number']) . ' updated successfully!';
        header("Location: view_cash_bill.php?id=" . $bill_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Cash Bill Update Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Cash Bill - Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220;
            --brand-mid: #0ea5e9;
            --brand-light: #e0f2fe;
            --success: #16a34a;
            --success-bg: #dcfce7;
            --danger: #dc2626;
            --danger-bg: #fee2e2;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --info: #0284c7;
            --info-bg: #e0f2fe;
            --bg: #f4f8fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 2px 8px rgba(2,132,199,.07);
            --shadow-md: 0 8px 24px rgba(2,132,199,.11);
            --transition: all 0.25s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }

        /* Main Content Area */
        .page-wrap {
            margin-top: 70px;
            margin-left: 260px;
            padding: 24px 28px 40px;
            transition: var(--transition);
            min-height: calc(100vh - 70px);
        }

        @media (max-width: 992px) {
            .page-wrap {
                margin-left: 0;
                padding: 16px;
            }
        }

        @media (max-width: 576px) {
            .page-wrap {
                padding: 12px;
            }
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
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.25);
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
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Cards */
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
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
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
            padding: 20px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-mid);
            margin-bottom: 6px;
            display: block;
        }

        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 10px 14px;
            font-size: 0.85rem;
            transition: var(--transition);
            background: #fafbfc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            background: #fff;
            outline: none;
        }

        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff !important;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(2,132,199,0.3);
            color: #fff;
        }

        .btn-secondary-custom {
            background: transparent;
            border: 1.5px solid var(--border);
            padding: 9px 22px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            color: var(--text-mid);
        }

        .btn-secondary-custom:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-light);
        }

        .btn-add {
            background: linear-gradient(135deg, var(--brand-dark), #1e293b);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(11,18,32,0.3);
        }

        .btn-danger-sm {
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-danger-sm:hover {
            transform: scale(1.05);
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .items-table th {
            background: var(--brand-light);
            padding: 10px 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--brand);
            border-bottom: 1px solid var(--border);
        }

        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        /* Total Section */
        .total-section {
            background: var(--brand-light);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .total-label {
            font-weight: 600;
            color: var(--text-mid);
        }

        .total-value {
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--brand);
        }

        /* Alerts */
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Select2 Override */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 14px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .select2-dropdown {
            border: 1px solid var(--brand);
            border-radius: var(--radius-sm);
        }

        .select2-results__option:hover {
            background: var(--brand-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-body-premium {
                padding: 16px;
                overflow-x: auto;
            }
            .items-table {
                min-width: 500px;
            }
        }
    </style>
</head>
<body>
<?php ?>

<div class="page-wrap">
    
    <!-- Hero Banner -->
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-edit"></i>
                Edit Cash Bill
            </div>
            <div class="hero-sub">Update bill details - <?php echo htmlspecialchars($bill['bill_number'] ?? ''); ?></div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-money-bill-wave me-1"></i>
            Cash Transaction
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($bill)): ?>
    <form method="POST" id="cashBillForm">
        <!-- Customer Details Card -->
        <div class="card-premium">
            <div class="card-header-premium">
                <h5><i class="fas fa-user"></i> Customer Information</h5>
            </div>
            <div class="card-body-premium">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                   value="<?php echo htmlspecialchars($bill['customer_name'] ?? ''); ?>" 
                                   placeholder="Enter customer name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="mobile_number" name="mobile_number" 
                                   value="<?php echo htmlspecialchars($bill['mobile_number'] ?? ''); ?>" 
                                   required placeholder="Enter mobile number">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Card -->
        <div class="card-premium">
            <div class="card-header-premium">
                <h5><i class="fas fa-boxes"></i> Products & Services</h5>
            </div>
            <div class="card-body-premium">
                <div class="table-responsive">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width:40%">Product</th>
                                <th style="width:15%">Quantity</th>
                                <th style="width:20%">Unit Price (₹)</th>
                                <th style="width:15%">Total (₹)</th>
                                <th style="width:10%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <?php if (empty($bill_items)): ?>
                            <tr class="item-row">
                                <td>
                                    <select class="form-select product-select" name="product_id[]" required>
                                        <option value="">Select Product</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="form-control quantity-input" name="quantity[]" min="1" value="1" required>
                                </td>
                                <td>
                                    <input type="number" class="form-control unit-price-input" name="unit_price[]" step="0.01" min="0" readonly>
                                </td>
                                <td>
                                    <input type="number" class="form-control total-price-input" name="total_price[]" step="0.01" readonly>
                                </td>
                                <td>
                                    <button type="button" class="btn-danger-sm remove-item" style="display: none;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($bill_items as $index => $item): ?>
                                <tr class="item-row">
                                    <td>
                                        <select class="form-select product-select" name="product_id[]" required 
                                                data-product-id="<?php echo $item['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                data-product-code="<?php echo htmlspecialchars($item['product_code'] ?? ''); ?>"
                                                data-sell-price="<?php echo $item['unit_price']; ?>">
                                            <option value="">Select Product</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control quantity-input" name="quantity[]" 
                                               min="1" value="<?php echo (int)$item['quantity']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control unit-price-input" name="unit_price[]" 
                                               step="0.01" min="0" value="<?php echo number_format($item['unit_price'], 2, '.', ''); ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control total-price-input" name="total_price[]" 
                                               step="0.01" value="<?php echo number_format($item['total_price'], 2, '.', ''); ?>" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-danger-sm remove-item" <?php echo count($bill_items) <= 1 ? 'style="display: none;"' : ''; ?>>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn-add" id="addItemBtn">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
        </div>

        <!-- Payment Card -->
        <div class="card-premium">
            <div class="card-header-premium">
                <h5><i class="fas fa-credit-card"></i> Payment Details</h5>
            </div>
            <div class="card-body-premium">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="paid_amount" class="form-label">Paid Amount <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                                   step="0.01" min="0" value="<?php echo number_format($bill['paid_amount'] ?? 0, 2, '.', ''); ?>" 
                                   required placeholder="Enter paid amount">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="total-section" style="margin-top: 0;">
                            <div class="total-row">
                                <span class="total-label"><i class="fas fa-rupee-sign me-1"></i> Total Amount:</span>
                                <span class="total-value" id="totalAmount">₹<?php echo number_format($bill['total_amount'] ?? 0, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
            <a href="view_cash_bill.php?id=<?php echo $bill_id; ?>" class="btn-secondary-custom">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn-primary-custom" name="update_cash_bill">
                <i class="fas fa-save"></i> Update Cash Bill
            </button>
        </div>

        <input type="hidden" name="products_json" id="products_json">
    </form>
    <?php else: ?>
        <div class="card-premium">
            <div class="card-body-premium text-center py-5">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger); margin-bottom: 15px;"></i>
                <h5>Bill Not Found</h5>
                <p class="text-muted">Could not load bill data. Please try again.</p>
                <a href="cash_bill_list.php" class="btn-primary-custom" style="margin-top: 15px;">
                    <i class="fas fa-arrow-left"></i> Back to Cash Bills
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    let itemCount = <?php echo !empty($bill_items) ? count($bill_items) : 1; ?>;
    
    // Initialize Select2 for product dropdowns
    function initializeSelect2(element) {
        const selectedProductId = element.data('product-id');
        const selectedProductName = element.data('product-name') || '';
        const selectedProductCode = element.data('product-code') || '';
        const selectedSellPrice = element.data('sell-price') || 0;
        
        element.select2({
            placeholder: "Search product...",
            allowClear: true,
            width: '100%',
            dropdownParent: element.closest('.card-body-premium'),
            ajax: {
                url: 'fetch-products-cash-bill.php',
                dataType: 'json',
                type: 'GET',
                delay: 250,
                data: function(params) {
                    return {
                        term: params.term || ''
                    };
                },
                processResults: function(data) {
                    return {
                        results: data.map(item => ({
                            id: item.id,
                            text: item.product_name + (item.product_code ? ' (' + item.product_code + ')' : ''),
                            product_name: item.product_name,
                            product_code: item.product_code,
                            sell_price: parseFloat(item.sell_price || 0)
                        }))
                    };
                },
                cache: true
            }
        });
        
        // If we have a selected product, set it
        if (selectedProductId && selectedProductName) {
            const option = new Option(
                selectedProductName + (selectedProductCode ? ' (' + selectedProductCode + ')' : ''), 
                selectedProductId, 
                true, 
                true
            );
            element.append(option).trigger('change');
            
            // Set the sell price
            if (selectedSellPrice) {
                const row = element.closest('tr');
                row.find('.unit-price-input').val(parseFloat(selectedSellPrice).toFixed(2));
                calculateRowTotal(row);
            }
        }
        
        element.on('select2:select', function(e) {
            const row = $(this).closest('tr');
            const data = e.params.data;
            row.find('.unit-price-input').val(data.sell_price.toFixed(2));
            calculateRowTotal(row);
        }).on('select2:unselect', function() {
            const row = $(this).closest('tr');
            row.find('.unit-price-input').val('');
            row.find('.total-price-input').val('');
            calculateGrandTotal();
        });
    }
    
    // Calculate row total
    function calculateRowTotal(row) {
        const quantity = parseFloat(row.find('.quantity-input').val() || 0);
        const unitPrice = parseFloat(row.find('.unit-price-input').val() || 0);
        const total = quantity * unitPrice;
        row.find('.total-price-input').val(total.toFixed(2));
        calculateGrandTotal();
    }
    
    // Calculate grand total
    function calculateGrandTotal() {
        let total = 0;
        $('.total-price-input').each(function() {
            const value = parseFloat($(this).val() || 0);
            if (!isNaN(value)) total += value;
        });
        $('#totalAmount').text('₹' + total.toFixed(2));
    }
    
    // Initialize Select2 for existing product dropdowns
    $('.product-select').each(function() {
        initializeSelect2($(this));
    });
    
    // Handle quantity change
    $(document).on('input', '.quantity-input', function() {
        const row = $(this).closest('tr');
        calculateRowTotal(row);
    });
    
    // Add new item row
    $('#addItemBtn').on('click', function() {
        itemCount++;
        const newRow = `
            <tr class="item-row">
                <td>
                    <select class="form-select product-select" name="product_id[]" required>
                        <option value="">Select Product</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control quantity-input" name="quantity[]" min="1" value="1" required>
                </td>
                <td>
                    <input type="number" class="form-control unit-price-input" name="unit_price[]" step="0.01" min="0" readonly>
                </td>
                <td>
                    <input type="number" class="form-control total-price-input" name="total_price[]" step="0.01" readonly>
                </td>
                <td>
                    <button type="button" class="btn-danger-sm remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#itemsTableBody').append(newRow);
        
        const newSelect = $('#itemsTableBody tr:last .product-select');
        initializeSelect2(newSelect);
        updateRemoveButtons();
    });
    
    // Remove item row
    $(document).on('click', '.remove-item', function() {
        if ($('.item-row').length > 1) {
            $(this).closest('tr').remove();
            calculateGrandTotal();
            updateRemoveButtons();
        }
    });
    
    // Update remove buttons visibility
    function updateRemoveButtons() {
        const rowCount = $('.item-row').length;
        if (rowCount > 1) {
            $('.remove-item').show();
        } else {
            $('.remove-item').hide();
        }
    }
    
    // Form submission
    $('#cashBillForm').on('submit', function(e) {
        let isValid = true;
        
        $('.product-select').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('Please select a product for all items.');
            return false;
        }
        
        const mobileNumber = $('#mobile_number').val();
        if (!mobileNumber || mobileNumber.trim() === '') {
            alert('Mobile number is required.');
            return false;
        }
        
        const paidAmount = $('#paid_amount').val();
        if (!paidAmount || parseFloat(paidAmount) < 0) {
            alert('Please enter a valid paid amount.');
            return false;
        }
        
        const products = [];
        $('.item-row').each(function() {
            const productId = $(this).find('.product-select').val();
            const quantity = $(this).find('.quantity-input').val();
            const unitPrice = $(this).find('.unit-price-input').val();
            
            if (productId && quantity && unitPrice) {
                products.push({
                    product_id: productId,
                    quantity: parseInt(quantity),
                    unit_price: parseFloat(unitPrice)
                });
            }
        });
        
        if (products.length === 0) {
            alert('Please add at least one product.');
            return false;
        }
        
        $('#products_json').val(JSON.stringify(products));
        return true;
    });
    
    updateRemoveButtons();
    calculateGrandTotal();
});
</script>
</body>
</html>