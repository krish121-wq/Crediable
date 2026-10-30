<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include 'layout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Handle form submission for processing returns
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_return'])) {
    try {
        $conn->autocommit(false);
        
        $invoice_id = trim($_POST['invoice_id']);
        $return_note = trim($_POST['return_note']);
        $selected_items = $_POST['selected_items'] ?? [];
        
        if (empty($selected_items)) {
            throw new Exception("Please select at least one item to return.");
        }
        
        if (empty($return_note)) {
            throw new Exception("Please provide a reason for the return.");
        }
        
        // Verify invoice exists
        $stmt = $conn->prepare("SELECT id, customer_name, customer_phone, customer_address FROM invoices WHERE id = ?");
        $stmt->bind_param("s", $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$invoice) {
            throw new Exception("Sale invoice not found.");
        }
        
        foreach ($selected_items as $sale_id) {
            $return_quantity = (int)$_POST['return_qty_' . $sale_id];
            
            if ($return_quantity <= 0) {
                continue;
            }
            
            // Get original sale item details
            $stmt = $conn->prepare("
                SELECT s.id, s.product_id, s.quantity, s.price_per_piece, s.discount_amount, 
                       s.total_price, p.product_name, p.gst_percentage
                FROM sales s
                JOIN products p ON s.product_id = p.id
                WHERE s.id = ? AND s.invoice_id = ?
            
            ");
            $stmt->bind_param("is", $sale_id, $invoice_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$item) {
                throw new Exception("Sale item not found.");
            }
            
            if ($return_quantity > $item['quantity']) {
                throw new Exception("Return quantity cannot exceed sold quantity for " . $item['product_name']);
            }
            
            // Calculate return amounts (rate is GST inclusive)
            $unit_price = $item['price_per_piece'];
            $discount_per_unit = $item['discount_amount'] / $item['quantity'];
            
            // Calculate the base price (price without GST)
            $gst_percentage = $item['gst_percentage'];
            $gst_factor = 1 + ($gst_percentage / 100);
            $base_price_per_unit = $unit_price / $gst_factor;
            
            // Calculate amounts for return
            $return_amount = $return_quantity * $unit_price;
            $return_discount = $return_quantity * $discount_per_unit;
            
            // Calculate GST on the base price
            $gst_amount = 0;
            if ($gst_percentage > 0) {
                $taxable_amount = ($base_price_per_unit * $return_quantity) - $return_discount;
                $gst_amount = ($taxable_amount * $gst_percentage) / 100;
            }
            
            // Create return entry
            $stmt = $conn->prepare("
                INSERT INTO sale_return_records (
                    original_invoice_id, product_id, return_quantity, unit_price, 
                    discount_amount, gst_percentage, gst_amount, return_amount, 
                    return_note, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            
            ");
            $stmt->bind_param("siidddddss", 
                $invoice_id, 
                $item['product_id'], 
                $return_quantity, 
                $unit_price,
                $return_discount,
                $item['gst_percentage'],
                $gst_amount,
                $return_amount,
                $return_note, 
                $created_by_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create return entry: " . $stmt->error);
            }
            $stmt->close();
            
            // Update product stock (add returned quantity back to stock)
            $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param("ii", $return_quantity, $item['product_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product stock: " . $stmt->error);
            }
            $stmt->close();
            
            // Update the original sale record to reduce quantity
            $new_quantity = $item['quantity'] - $return_quantity;
            if ($new_quantity > 0) {
                // If some items are still remaining, update the quantity
                $stmt = $conn->prepare("
                    UPDATE sales 
                    SET quantity = ?, 
                        discount_amount = discount_amount * ? / quantity,
                        total_price = total_price * ? / quantity
                    WHERE id = ?
                
                ");
                $stmt->bind_param("iddi", $new_quantity, $new_quantity, $new_quantity, $sale_id);
            } else {
                // If all items are returned, mark as returned or delete
                $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
                $stmt->bind_param("i", $sale_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update sale record: " . $stmt->error);
            }
            $stmt->close();
        }
        
        $conn->commit();
        $success_message = "Sale return processed successfully!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}


$returns = [];
try {
    $query = "
        SELECT sr.*, p.product_name, u.full_name  as created_by_name
        FROM sale_return_records sr
        JOIN products p ON sr.product_id = p.id
        LEFT JOIN users u ON sr.created_by = u.id
        ORDER BY sr.created_at DESC
        LIMIT 100
    ";
    $result = $conn->query($query);
    if ($result) {
        $returns = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $returns = [];
    error_log("Error fetching returns: " . $e->getMessage());
}
?>

<style>
  
:root {
    --billing-base: #0284c7; /* MAIN BRAND BLUE (matches Sales page) */
    --billing-hover: #0ea5e9; /* hover blue */
    --billing-active: #0b1220; /* dark press */
    --text-primary: #0b1220;
    --text-light: #FFFFFF;
    --text-secondary: #cbd5e1;
    --card-bg: #FFFFFF;
    --main-bg: #f4f8fb;
    --danger: #dc2626;
    --success: #16a34a;
    --accent: #0ea5e9;
    --grey-light: #e2e8f0;
    --shadow-base: 0 2px 8px rgba(2,132,199,0.08);
    --shadow-hover: 0 8px 24px rgba(2,132,199,0.15);

    /* IMPORTANT for sidebar */
    --sidebar-width: 260px;
    --header-height: 60px;
}

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--main-bg);
    color: var(--text-primary);
}

/* MAIN CONTENT (SIDEBAR FIX) */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 15px;
    margin-top: var(--header-height);
    min-height: 100vh;
    transition: 0.3s;
}

/* WHEN SIDEBAR COLLAPSE */
.sidebar-hidden .main-content {
    margin-left: 0;
}

/* HEADER */
.page-header {
    background: var(--billing-base);
    color: white;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-base);
}

.page-header h1 {
    font-size: 20px;
    margin: 0;
}

/* CARD */
.card {
    border: none;
    border-radius: 10px;
    background: white;
    box-shadow: var(--shadow-base);
    margin-bottom: 20px;
}

.card-header {
    background: var(--billing-base);
    color: white;
    padding: 10px 15px;
    border-radius: 10px 10px 0 0;
}

.card-body {
    padding: 15px;
}

/* BUTTON */
.btn-primary,
.btn-sea-green {
    background: var(--billing-base);
    border: none;
    color: white;
    font-weight: 600;
    transition: 0.3s;
}

.btn-primary:hover,
.btn-sea-green:hover {
    background: var(--billing-hover);
    transform: translateY(-1px);
}

.btn-primary:active {
    background: var(--billing-active);
}

/* INPUT */
.form-control {
    border-radius: 6px;
    border: 1px solid var(--grey-light);
}

.form-control:focus {
    border-color: var(--billing-base);
    box-shadow: 0 0 0 2px rgba(2,132,199,0.15);
}

/* TABLE */
.table thead {
    background: var(--billing-base);
    color: white;
}

.table th {
    font-size: 13px;
    text-align: center;
}

.table td {
    font-size: 13px;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: rgba(2,132,199,0.05);
}

/* ALERT */
.alert-success {
    background: var(--success);
    color: white;
}

.alert-danger {
    background: var(--danger);
    color: white;
}

/* RETURN BOX */
.bill-details-card {
    background: rgba(2,132,199,0.05);
    border: 1px solid var(--billing-base);
    border-radius: 8px;
    padding: 12px;
}

/* BADGES */
.badge {
    font-size: 12px;
}

/* RESPONSIVE */
@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding: 10px;
    }
}

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 18px;
    }

    .table th, .table td {
        font-size: 12px;
    }
}
</style>


<div class="main-content">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0"><i class="fas fa-undo-alt me-3"></i>Sale Return</h1>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Invoice Search Section -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Process Sale Return</h5>
        </div>
        <div class="card-body">
            <form id="invoiceSearchForm" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="invoice_id" class="form-label">Enter Invoice No.</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="invoice_id" name="invoice_id" placeholder="Enter invoice number" required>
                            <button class="btn btn-primary" type="submit">Search</button>
                        </div>
                    </div>
                </div>
            </form>
            
            <div id="invoiceDetails" class="mt-4 d-none">
                <form id="returnForm" method="POST" action="">
                    <input type="hidden" name="invoice_id" id="return_invoice_id">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Sl.No</th>
                                    <th>Product</th>
                                    <th>HSN</th>
                                    <th>Qty Sold</th>
                                    <th>Rate</th>
                                    <th>Disc.</th>
                                    <th>GST %</th>
                                    <th>Return Qty</th>
                                    <th>Return Amt</th>
                                </tr>
                            </thead>
                            <tbody id="invoiceItems">
                                <!-- Items will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="return_note" class="form-label">Return Reason/Note</label>
                                <textarea class="form-control" id="return_note" name="return_note" rows="2" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-grid gap-2">
                                <button type="submit" name="process_return" class="btn btn-danger btn-lg">
                                    <i class="fas fa-undo me-2"></i> Process Return
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Recent Returns Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Sale Returns</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="returnsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Sl.No</th>
                            <th>Date</th>
                            <th>Invoice ID</th>
                            <th>Product</th>
                            <th>Qty Returned</th>
                            <th>Unit Price</th>
                            <th>Disc.</th>
                            <th>GST</th>
                            <th>Amount</th>
                            <th>Processed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returns)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No recent returns found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($returns as $index => $return): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo date('d-m-Y', strtotime($return['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($return['original_invoice_id']); ?></td>
                                    <td><?php echo htmlspecialchars($return['product_name']); ?></td>
                                    <td class="text-end"><?php echo $return['return_quantity']; ?></td>
                                    <td class="text-end"><?php echo number_format($return['unit_price'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($return['discount_amount'], 2); ?></td>
                                    <td class="text-end">
                                        <?php echo $return['gst_percentage']; ?>%
                                        (<?php echo number_format($return['gst_amount'], 2); ?>)
                                    </td>
                                    <td class="text-end"><?php echo number_format($return['return_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($return['created_by_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Handle invoice search
    $('#invoiceSearchForm').on('submit', function(e) {
        e.preventDefault();
        
        const invoiceId = $('#invoice_id').val().trim();
        if (!invoiceId) return;
        
        $.ajax({
            url: 'ajax/get_invoice_details.php',
            type: 'GET',
            data: { invoice_id: invoiceId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    displayInvoiceDetails(response.data);
                } else {
                    alert(response.message || 'Error fetching invoice details');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('An error occurred while fetching invoice details');
            }
        });
    });
    
    // Display invoice details
    function displayInvoiceDetails(data) {
        const invoice = data.invoice;
        const items = data.items;
        
        // Set the invoice ID for the return form
        $('#return_invoice_id').val(invoice.invoice_id);
        
        // Build items table
        let itemsHtml = '';
        items.forEach((item, index) => {
            itemsHtml += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <input type="hidden" name="selected_items[]" value="${item.id}">
                        ${item.product_name}
                    </td>
                    <td>${item.hsn_code || 'N/A'}</td>
                    <td class="text-end">${item.quantity}</td>
                    <td class="text-end">${parseFloat(item.price_per_piece).toFixed(2)}</td>
                    <td class="text-end">${parseFloat(item.discount_amount || 0).toFixed(2)}</td>
                    <td class="text-end">${parseFloat(item.gst_percentage || 0).toFixed(2)}%</td>
                    <td class="text-end">
                        <input type="number" 
                               name="return_qty_${item.id}" 
                               class="form-control return-qty" 
                               min="0" 
                               max="${item.quantity}" 
                               value="0"
                               data-price="${item.price_per_piece}"
                               data-discount="${item.discount_amount || 0}"
                               data-gst="${item.gst_percentage || 0}"
                               data-quantity="${item.quantity}">
                    </td>
                    <td class="text-end return-amount">0.00</td>
                </tr>
            `;
        });
        
        $('#invoiceItems').html(itemsHtml);
        
        // Show the invoice details section
        $('#invoiceDetails').removeClass('d-none');
        
        // Scroll to the form
        $('html, body').animate({
            scrollTop: $('#invoiceDetails').offset().top - 20
        }, 500);
    }
    
    // Calculate return amount when quantity changes
    $(document).on('input', '.return-qty', function() {
        const $row = $(this).closest('tr');
        const quantity = parseInt($(this).val()) || 0;
        const price = parseFloat($(this).data('price'));
        const totalDiscount = parseFloat($(this).data('discount') || 0);
        const totalQuantity = parseInt($(this).data('quantity'));
        const gstPercentage = parseFloat($(this).data('gst') || 0);
        
        // Calculate discount per unit
        const discountPerUnit = totalQuantity > 0 ? (totalDiscount / totalQuantity) : 0;
        const totalReturnAmount = quantity * price;
        const totalReturnDiscount = quantity * discountPerUnit;
        
        // Calculate GST on the base amount (since rate is GST inclusive)
        let gstAmount = 0;
        if (gstPercentage > 0) {
            const gstFactor = 1 + (gstPercentage / 100);
            const basePrice = price / gstFactor;
            const baseAmount = quantity * basePrice;
            const taxableAmount = baseAmount - totalReturnDiscount;
            gstAmount = (taxableAmount * gstPercentage) / 100;
        }
        
        const totalAmount = totalReturnAmount - totalReturnDiscount;
        
        $row.find('.return-amount').text(totalAmount.toFixed(2));
    });
    
    // Initialize DataTable for returns
    if ($.fn.DataTable && $('#returnsTable').length) {
        $('#returnsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            responsive: true,
            dom: '<"top"f>rt<"bottom"lip><"clear">',
            language: {
                search: "Search returns:",
                lengthMenu: "Show _MENU_ entries per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "No entries found",
                infoFiltered: "(filtered from _MAX_ total entries)"
            }
        });
    }
});
</script>