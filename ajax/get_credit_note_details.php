<?php
require_once '../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Unauthorized']));
}

$credit_note_id = (int)($_GET['id'] ?? 0);

if (!$credit_note_id) {
    die(json_encode(['error' => 'Invalid credit note ID']));
}

try {
    // Get credit note details
    $stmt = $conn->prepare("
        SELECT cn.*, u.username as created_by_name 
        FROM credit_notes cn
        LEFT JOIN create_users u ON cn.created_by = u.id
        WHERE cn.id = ?
    ");
    $stmt->bind_param("i", $credit_note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(['error' => 'Credit note not found']));
    }
    
    $credit_note = $result->fetch_assoc();
    
    // Get credit note items
    $stmt = $conn->prepare("
        SELECT * FROM credit_note_items 
        WHERE credit_note_id = ?
    ");
    $stmt->bind_param("i", $credit_note_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = [];
    
    while ($row = $items_result->fetch_assoc()) {
        $items[] = $row;
    }
    
    // Get company details
    $company = [];
    $company_stmt = $conn->query("SELECT * FROM company_details LIMIT 1");
    if ($company_stmt && $company_stmt->num_rows > 0) {
        $company = $company_stmt->fetch_assoc();
    }
    
    // Generate HTML for the modal
    ob_start();
    ?>
    <div class="credit-note-details">
        <div class="row mb-4">
            <div class="col-md-6">
                <?php if (!empty($company['company_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 15px;">
                <?php endif; ?>
                <h4><?php echo htmlspecialchars($company['company_name'] ?? 'Your Company Name'); ?></h4>
                <p class="mb-1"><?php echo nl2br(htmlspecialchars($company['company_address'] ?? '')); ?></p>
                <?php if (!empty($company['company_gst'])): ?>
                    <p class="mb-1">GST: <?php echo htmlspecialchars($company['company_gst']); ?></p>
                <?php endif; ?>
                <?php if (!empty($company['phone'])): ?>
                    <p class="mb-0">Phone: <?php echo htmlspecialchars($company['phone']); ?></p>
                <?php endif; ?>
            </div>
            <div class="col-md-6 text-end">
                <h2 class="text-primary">CREDIT NOTE</h2>
                <p class="mb-1"><strong>Credit Note #:</strong> <?php echo htmlspecialchars($credit_note['credit_note_number']); ?></p>
                <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($credit_note['created_at'])); ?></p>
                <?php if (!empty($credit_note['invoice_id'])): ?>
                    <p class="mb-0"><strong>Invoice #:</strong> <?php echo htmlspecialchars($credit_note['invoice_id']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Bill To</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-1"><?php echo htmlspecialchars($credit_note['customer_name']); ?></h6>
                        <?php if (!empty($credit_note['customer_phone'])): ?>
                            <p class="mb-1">Phone: <?php echo htmlspecialchars($credit_note['customer_phone']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($credit_note['customer_address'])): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($credit_note['customer_address'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Credit Note Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Status:</strong> 
                            <span class="badge bg-<?php 
                                echo $credit_note['status'] === 'issued' ? 'success' : 
                                    ($credit_note['status'] === 'draft' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($credit_note['status']); ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Created By:</strong> <?php echo htmlspecialchars($credit_note['created_by_name'] ?? 'N/A'); ?></p>
                        <p class="mb-0"><strong>Created On:</strong> <?php echo date('d M Y, h:i A', strtotime($credit_note['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive mb-4">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal = 0;
                    foreach ($items as $index => $item): 
                        $subtotal += $item['total_price'];
                    ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <?php echo htmlspecialchars($item['product_name']); ?>
                                <?php if (!empty($item['reason'])): ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($item['reason']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                            <td class="text-end">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-end">₹<?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end"><strong>Subtotal