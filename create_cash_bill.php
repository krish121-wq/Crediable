<?php
require_once 'config.php';

// Start session and output buffering before including layout
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();


// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('layout.php');

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
$error_message = '';
$created_by_id = (int)$_SESSION['user_id'];

// Ensure cash_bills table exists
try {
    // First, create the table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS cash_bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_number VARCHAR(20) NOT NULL UNIQUE,
        customer_name VARCHAR(255) DEFAULT NULL,
        mobile_number VARCHAR(50) NOT NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at),
        INDEX idx_bill_number (bill_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Add bill_number column if it doesn't exist
    $conn->query("ALTER TABLE cash_bills ADD COLUMN IF NOT EXISTS bill_number VARCHAR(20) UNIQUE AFTER id");
} catch (Exception $e) {
    error_log('Could not create cash_bills table: ' . $e->getMessage());
}

// Ensure cash_bill_items table exists
try {
    // Check if table exists first
    $table_check = $conn->query("SHOW TABLES LIKE 'cash_bill_items'");
    if ($table_check->num_rows == 0) {
        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("CREATE TABLE cash_bill_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cash_bill_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cash_bill_id (cash_bill_id),
            INDEX idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Add foreign key constraint separately if cash_bills table exists
        $cash_bills_check = $conn->query("SHOW TABLES LIKE 'cash_bills'");
        if ($cash_bills_check->num_rows > 0) {
            try {
                $conn->query("ALTER TABLE cash_bill_items ADD CONSTRAINT fk_cash_bill_items_bill FOREIGN KEY (cash_bill_id) REFERENCES cash_bills(id) ON DELETE CASCADE");
            } catch (Exception $fk_e) {
                error_log('Could not add foreign key constraint: ' . $fk_e->getMessage());
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
} catch (Exception $e) {
    error_log('Could not create cash_bill_items table: ' . $e->getMessage());
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_cash_bill'])) {
    try {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $mobile_number = trim($_POST['mobile_number'] ?? '');
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        
        // Get products from JSON
        $products_json = $_POST['products_json'] ?? '[]';
        $products = json_decode($products_json, true);
        
        // Debug logging
        error_log("Cash Bill Form Data - Mobile: $mobile_number, Products JSON: $products_json");
        
        if (empty($mobile_number)) {
            throw new Exception("Mobile number is required.");
        }
        
        if (empty($products) || !is_array($products)) {
            throw new Exception("At least one product must be added. Products: " . print_r($products, true));
        }
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            $quantity = (int)($product['quantity'] ?? 0);
            $unit_price = floatval($product['unit_price'] ?? 0);
            
            if ($product_id <= 0 || $quantity <= 0) {
                throw new Exception("Invalid product or quantity. Product ID: $product_id, Quantity: $quantity");
            }
            
            $total_amount += ($quantity * $unit_price);
        }
        
        // Generate bill number
        $bill_number = '';
        $year_month = date('Ym');
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING_INDEX(bill_number, '-', -1) AS UNSIGNED)) as last_number 
                              FROM cash_bills 
                              WHERE bill_number LIKE 'CB-$year_month-%'");
        $next_number = 1;
        if ($result && $row = $result->fetch_assoc()) {
            $next_number = (int)$row['last_number'] + 1;
        }
        $bill_number = 'CB-' . $year_month . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        // Check if bill number already exists (unlikely but just to be safe)
        $check = $conn->prepare("SELECT id FROM cash_bills WHERE bill_number = ?");
        $check->bind_param("s", $bill_number);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            // If bill number exists, append a random character to make it unique
            $bill_number .= chr(rand(65, 90)); // Append a random uppercase letter
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        // Insert cash bill - handle NULL customer_name properly
        if (!empty($customer_name)) {
            $stmt = $conn->prepare("INSERT INTO cash_bills (bill_number, customer_name, mobile_number, total_amount, paid_amount, created_by) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddi", $bill_number, $customer_name, $mobile_number, $total_amount, $paid_amount, $created_by_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO cash_bills (bill_number, mobile_number, total_amount, paid_amount, created_by) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddi", $bill_number, $mobile_number, $total_amount, $paid_amount, $created_by_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create cash bill: " . $stmt->error);
        }
        
        $cash_bill_id = $stmt->insert_id;
        $stmt->close();
        
        error_log("Cash Bill Created - ID: $cash_bill_id, Total: $total_amount");
        
        // Insert cash bill items
        foreach ($products as $product) {
            $product_id = (int)($product['product_id'] ?? 0);
            $quantity = (int)($product['quantity'] ?? 0);
            $unit_price = floatval($product['unit_price'] ?? 0);
            $total_price = $quantity * $unit_price;
            
            $stmt = $conn->prepare("INSERT INTO cash_bill_items (cash_bill_id, product_id, quantity, unit_price, total_price, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                throw new Exception("Prepare failed for items: " . $conn->error);
            }
            
            $stmt->bind_param("iiidd", $cash_bill_id, $product_id, $quantity, $unit_price, $total_price);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert bill item: " . $stmt->error);
            }
            $stmt->close();
            
            // Deduct stock quantity
            $update_stock = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            if (!$update_stock) {
                throw new Exception("Failed to prepare stock update: " . $conn->error);
            }
            
            $update_stock->bind_param("ii", $quantity, $product_id);
            if (!$update_stock->execute()) {
                throw new Exception("Failed to update stock for product ID $product_id: " . $update_stock->error);
            }
            $update_stock->close();
            
            // Log the stock update
            error_log("Deducted $quantity units from product ID $product_id");
        }


        // After inserting the cash bill and getting $cash_bill_id
if ($paid_amount > 0) {
    try {
        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO cash_bill_payments 
            (cash_bill_id, amount, payment_method, payment_date, reference_no, notes, created_by)
            VALUES (?, ?, 'Cash', CURDATE(), 'Initial Payment', 'Initial payment on bill creation', ?)
        ");
        $stmt->bind_param('idi', 
            $cash_bill_id, 
            $paid_amount,
            $created_by_id
        );
        
        if (!$stmt->execute()) {
            error_log("Failed to record payment: " . $stmt->error);
            // Don't throw exception here, as the bill was created successfully
            // Just log the error and continue
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Error recording payment: " . $e->getMessage());
        // Continue with bill creation even if payment recording fails
    }
}
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to view the bill with success message
        $_SESSION['success_message'] = 'Cash bill ' . htmlspecialchars($bill_number) . ' created successfully!';
        header("Location: view_cash_bill.php?id=" . $cash_bill_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();

        $error_message = $e->getMessage();
        error_log("Cash Bill Creation Error: " . $e->getMessage() . " | Line: " . $e->getLine());
    }
}
?>

<style>
:root {
    --primary: #0284c7;
    --primary-hover: #0ea5e9;
    --primary-active: #0b1220;
    --accent: #0b1220;
    
    --text-primary: #0b1220;
    --text-secondary: #475569;
    --text-light: #FFFFFF;
    
    --card-bg: #FFFFFF;
    --canvas-bg: #f4f8fb;
    --border-color: #e2e8f0;
    --grey-light: #F1F1F1;
    
    --header-height: 70px;
    --sidebar-width: 260px;
}

/* ====================== BASE ====================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--canvas-bg);
    color: var(--text-primary);
    line-height: 1.6;
}

/* ====================== MAIN CONTENT ====================== */
.content {
    margin-top: var(--header-height);
    margin-left: var(--sidebar-width);
    padding: 24px;
    min-height: calc(100vh - var(--header-height));
    background: var(--canvas-bg);
}

/* ====================== PAGE HEADER ====================== */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    background: linear-gradient(135deg, var(--primary-active) 0%, var(--primary) 55%, var(--primary-hover) 100%);
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    color: white;
    box-shadow: 0 6px 20px rgba(2, 132, 199, 0.25);
}

.page-title h4 {
    font-size: 1.55rem;
    font-weight: 700;
}

.page-title h6 {
    font-size: 0.95rem;
    opacity: 0.9;
}

/* ====================== CARDS ====================== */
.form-card,
.total-section {
    background: var(--card-bg);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
    border: 1px solid var(--border-color);
}

/* ====================== FORM ELEMENTS ====================== */
.form-label {
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: block;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.15);
    outline: none;
}

/* Select2 Customization */
.select2-container--default .select2-selection--single {
    border: 2px solid var(--border-color) !important;
    border-radius: 10px !important;
    height: 46px !important;
    padding: 4px 12px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: var(--text-primary);
    line-height: 38px;
    font-size: 1rem;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px;
}

.select2-dropdown {
    border: 2px solid var(--primary) !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.select2-results__option {
    padding: 10px 14px;
    font-size: 0.95rem;
}

.select2-results__option:hover {
    background: #e0f2fe;
}

/* ====================== TABLE ====================== */
.items-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
    margin-bottom: 20px;
}

.items-table th {
    background: #e0f2fe;
    color: var(--primary);
    font-weight: 600;
    padding: 16px 12px;
    text-align: center;
    font-size: 0.92rem;
}

.items-table td {
    padding: 14px 10px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
    text-align: center;
}

.items-table tr:hover {
    background: #f4f9fd;
}

/* ====================== BUTTONS ====================== */
.btn {
    border: none;
    border-radius: 10px;
    font-weight: 600;
    padding: 10px 20px;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-2px);
}

.btn-add {
    background: var(--accent);
    color: white;
    padding: 12px 24px;
}

.btn-add:hover {
    background: #1e293b;
    transform: translateY(-2px);
}

.btn-danger {
    background: #ef4444;
    color: white;
    padding: 8px 14px;
    font-size: 0.85rem;
}

.btn-danger:hover {
    background: #dc2626;
    transform: scale(1.08);
}

/* ====================== TOTAL SECTION ====================== */
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary);
    padding: 16px 0;
    border-top: 2px solid var(--grey-light);
}

.total-value {
    font-size: 1.6rem;
}

/* ====================== ALERTS ====================== */
.alert {
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #86efac;
}

.alert-danger {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
}

/* ====================== RESPONSIVE ====================== */
@media (max-width: 992px) {
    .content { margin-left: 0; padding: 18px; }
}

@media (max-width: 768px) {
    .page-header { padding: 18px; flex-direction: column; align-items: flex-start; gap: 10px; }
    .form-card, .total-section { padding: 18px; }
    .items-table th, .items-table td { padding: 12px 8px; font-size: 0.85rem; }
    .btn { width: 100%; padding: 12px; }
    .btn-add { width: auto; }
}

@media (max-width: 576px) {
    .content { padding: 14px; }
    .form-card, .total-section { padding: 16px; }
    .items-table th, .items-table td { padding: 10px 6px; font-size: 0.82rem; }
    .page-title h4 { font-size: 1.35rem; }
}
</style>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Create Cash Bill</h4>
            <h6>Create a new cash bill with multiple items</h6>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            <?php if (isset($_POST['products_json'])): ?>
                <br><small>Products JSON received: <?php echo htmlspecialchars($_POST['products_json']); ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    

    <form method="POST" id="cashBillForm">
        <div class="form-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                        <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Enter customer name">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" required placeholder="Enter mobile number">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <h5 style="margin-bottom: 20px; color: var(--text-primary);">Items</h5>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemsTableBody">
                    <tr class="item-row">
                        <td>
                            <select class="form-control product-select" name="product_id[]" required>
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
                            <button type="button" class="btn btn-danger remove-item" style="display: none;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-add" id="addItemBtn">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>

        <div class="total-section">
            <div class="total-row">
                <span class="total-label">Total Amount:</span>
                <span class="total-value" id="totalAmount">₹0.00</span>
            </div>
            <div class="form-group" style="margin-top: 20px;">
                <label for="paid_amount" class="form-label">Paid Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="paid_amount" name="paid_amount" step="0.01" min="0" required placeholder="Enter paid amount">
            </div>
        </div>

        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn btn-primary" name="create_cash_bill">
                <i class="fas fa-save"></i> Create Cash Bill
            </button>
        </div>

        <input type="hidden" name="products_json" id="products_json">
    </form>
</div>

<!-- Include Select2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    let itemCount = 0;
    
    // Initialize Select2 for product dropdowns
    function initializeSelect2(element) {
        element.select2({
            placeholder: "Select Product",
            allowClear: true,
            width: '100%',
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
            },
            minimumInputLength: 0
        }).on('select2:select', function(e) {
            const row = $(this).closest('tr');
            const data = e.params.data;
            
            // Set unit price
            row.find('.unit-price-input').val(data.sell_price.toFixed(2));
            
            // Calculate total
            calculateRowTotal(row);
        }).on('select2:unselect', function() {
            const row = $(this).closest('tr');
            row.find('.unit-price-input').val('');
            row.find('.total-price-input').val('');
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
            total += value;
        });
        $('#totalAmount').text('₹' + total.toFixed(2));
    }
    
    // Initialize Select2 for existing product dropdown
    initializeSelect2($('.product-select'));
    
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
                    <select class="form-control product-select" name="product_id[]" required>
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
                    <button type="button" class="btn btn-danger remove-item">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#itemsTableBody').append(newRow);
        
        // Initialize Select2 for new row
        const newSelect = $('#itemsTableBody tr:last .product-select');
        initializeSelect2(newSelect);
        
        // Show remove buttons if more than one row
        updateRemoveButtons();
    });
    
    // Remove item row
    $(document).on('click', '.remove-item', function() {
        $(this).closest('tr').remove();
        calculateGrandTotal();
        updateRemoveButtons();
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
      
        
        console.log('Form submission started');
        
        // Validate form
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
        
        // Validate mobile number
        const mobileNumber = $('#mobile_number').val();
        if (!mobileNumber || mobileNumber.trim() === '') {
            alert('Mobile number is required.');
            return false;
        }
        
        // Validate paid amount
        const paidAmount = $('#paid_amount').val();
        if (!paidAmount || parseFloat(paidAmount) < 0) {
            alert('Please enter a valid paid amount.');
            return false;
        }
        
        // Build products JSON
        const products = [];
        $('.item-row').each(function() {
            const productId = $(this).find('.product-select').val();
            const quantity = $(this).find('.quantity-input').val();
            const unitPrice = $(this).find('.unit-price-input').val();
            
            console.log('Row data:', { productId, quantity, unitPrice });
            
            if (productId && quantity && unitPrice) {
                products.push({
                    product_id: productId,
                    quantity: parseInt(quantity),
                    unit_price: parseFloat(unitPrice)
                });
            }
        });
        
        console.log('Products array:', products);
        
        if (products.length === 0) {
            alert('Please add at least one product.');
            return false;
        }
        
        // Set products JSON
        const productsJson = JSON.stringify(products);
        $('#products_json').val(productsJson);
        
        console.log('Products JSON:', productsJson);
        console.log('Form data:', {
            customer_name: $('#customer_name').val(),
            mobile_number: mobileNumber,
            paid_amount: paidAmount,
            products_json: productsJson
        });
        
        // Submit form
        this.submit();
    });
    
    // Initial update of remove buttons
    updateRemoveButtons();
});

function initializeSelect2(element) {
    element.select2({
        placeholder: "Search Product by Name or Code...",
        allowClear: true,
        width: '100%',
        ajax: {
            url: 'fetch-products-cash-bill.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    term: params.term || ''   // Search term yahan bhej rahe hain
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
        },
        minimumInputLength: 1,           // Ab 1 letter type karte hi search shuru ho jayega
        language: {
            noResults: function() {
                return "No product found";
            }
        }
    }).on('select2:select', function(e) {
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
</script>

<?php
// Get the buffered content and output it
$content = ob_get_clean();
echo $content;
?>