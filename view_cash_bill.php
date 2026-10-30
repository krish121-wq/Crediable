<?php
require_once 'config.php';
require_once 'includes/company_functions.php';


$company = getCompanyDetails($conn);

if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid bill ID';
    header('Location: create_cash_bill.php');
    exit();
}

$bill_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

// Fetch bill details
$bill = [];
$items = [];

try {
    // Get bill information
    $stmt = $conn->prepare("SELECT cb.*, u.full_name as created_by_name 
                           FROM cash_bills cb 
                           LEFT JOIN users u ON cb.created_by = u.id 
                           WHERE cb.id = ? AND (cb.created_by = ? OR ? = 1)"); // Allow admin to view any bill
    $is_admin = $_SESSION['is_admin'] ?? 0;
    $stmt->bind_param("iii", $bill_id, $user_id, $is_admin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Bill not found or access denied');
    }
    
    $bill = $result->fetch_assoc();
    $stmt->close();
    
    // Get bill items with GST percentage
    $stmt = $conn->prepare("SELECT cbi.*, p.product_name as product_name, p.gst_percentage as gst_percentage 
                           FROM cash_bill_items cbi 
                           LEFT JOIN products p ON cbi.product_id = p.id 
                           WHERE cbi.cash_bill_id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error fetching bill: ' . $e->getMessage();
    header('Location: create_cash_bill.php');
    exit();
}

// Get company state for GST calculation
$company_state = $company['state'] ?? '';
$customer_state = $bill['customer_state'] ?? ''; // Assuming customer_state is stored in cash_bills table

// Calculate totals with GST
$subtotal_without_gst = 0;
$total_cgst = 0;
$total_sgst = 0;
$total_igst = 0;

// Track GST percentages for average calculation
$cgst_percentages = [];
$sgst_percentages = [];
$igst_percentages = [];
$total_taxable_amount = 0;

foreach ($items as $item) {
    $item_amount = (float)$item['total_price'];
    $gst_percentage = (float)($item['gst_percentage'] ?? 0);
    
    // Calculate amount without GST (assuming current total_price includes GST)
    $item_without_gst = $item_amount / (1 + ($gst_percentage / 100));
    $subtotal_without_gst += $item_without_gst;
    
    // Calculate GST amounts
    $gst_amount = $item_amount - $item_without_gst;
    $total_taxable_amount += $item_without_gst;
    
    // Check if customer is from same state (home state)
    if (strtoupper(trim($customer_state)) === strtoupper(trim($company_state)) || empty($customer_state)) {
        // Home state - apply CGST and SGST
        $cgst_amount = $gst_amount / 2;
        $sgst_amount = $gst_amount / 2;
        $total_cgst += $cgst_amount;
        $total_sgst += $sgst_amount;
        
        // Track CGST and SGST percentages (half of GST rate for each)
        if ($gst_percentage > 0) {
            $cgst_percentages[] = ['rate' => $gst_percentage / 2, 'amount' => $item_without_gst];
            $sgst_percentages[] = ['rate' => $gst_percentage / 2, 'amount' => $item_without_gst];
        }
    } else {
        // Other state - apply IGST
        $total_igst += $gst_amount;
        
        // Track IGST percentage
        if ($gst_percentage > 0) {
            $igst_percentages[] = ['rate' => $gst_percentage, 'amount' => $item_without_gst];
        }
    }
}

// Calculate weighted average GST percentages
$avg_cgst_percentage = 0;
$avg_sgst_percentage = 0;
$avg_igst_percentage = 0;

if (!empty($cgst_percentages)) {
    $total_cgst_weighted = 0;
    foreach ($cgst_percentages as $cgst) {
        $total_cgst_weighted += $cgst['rate'] * $cgst['amount'];
    }
    $avg_cgst_percentage = $total_taxable_amount > 0 ? ($total_cgst_weighted / $total_taxable_amount) : 0;
}

if (!empty($sgst_percentages)) {
    $total_sgst_weighted = 0;
    foreach ($sgst_percentages as $sgst) {
        $total_sgst_weighted += $sgst['rate'] * $sgst['amount'];
    }
    $avg_sgst_percentage = $total_taxable_amount > 0 ? ($total_sgst_weighted / $total_taxable_amount) : 0;
}

if (!empty($igst_percentages)) {
    $total_igst_weighted = 0;
    foreach ($igst_percentages as $igst) {
        $total_igst_weighted += $igst['rate'] * $igst['amount'];
    }
    $avg_igst_percentage = $total_taxable_amount > 0 ? ($total_igst_weighted / $total_taxable_amount) : 0;
}

$grand_total = $subtotal_without_gst + $total_cgst + $total_sgst + $total_igst;
$paid = (float)$bill['paid_amount'];
$balance = $grand_total - $paid;

// Format date
$date = new DateTime($bill['created_at']);
$formatted_date = $date->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Bill #<?php echo htmlspecialchars($bill['bill_number']); ?></title>
    
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
        }
        
        body {
            width: 80mm; /* 3.15 inches */
            max-width: 80mm;
            margin: 0 auto;
            padding: 2mm;
            background: white;
            color: #000;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #000;
        }
        
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2mm;
            text-transform: uppercase;
        }
        
        .header p {
            margin: 1mm 0;
        }
        
        /* Bill info */
        .bill-info {
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #000;
        }
        
        .bill-info p {
            display: flex;
            justify-content: space-between;
            margin: 1mm 0;
        }
        
        .bill-info p span:first-child {
            font-weight: bold;
            margin-right: 2mm;
        }
        
        /* Items table */
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3mm;
        }
        
        .items th {
            text-align: left;
            padding: 1mm 0;
            border-bottom: 1px solid #000;
            font-weight: bold;
        }
        
        .items td {
            padding: 1mm 0;
            vertical-align: top;
        }
        
        .items tr:not(:last-child) td {
            border-bottom: 1px dashed #ddd;
        }
        
        .items .name {
            width: 30%;
        }
        
        .items .qty, .items .rate, .items .amount {
            width: 16.66%;
            text-align: right;
        }
        
        /* Totals */
        .totals {
            width: 100%;
            margin: 3mm 0;
        }
        
        .totals tr:last-child {
            border-top: 1px solid #000;
            font-weight: bold;
        }
        
        .totals td {
            padding: 1mm 0;
            text-align: right;
        }
        
        .totals td:first-child {
            text-align: left;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            margin-top: 5mm;
            padding-top: 2mm;
            border-top: 1px dashed #000;
            font-size: 10px;
        }
        
        .thank-you {
            text-align: center;
            margin: 3mm 0;
            font-weight: bold;
        }
        
        /* Print styles */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            
            body {
                padding: 0;
                width: 80mm;
            }
            
            body.a4-mode {
                width: 100% !important;
            }
            body.a4-mode @page {
                size: A4;
                margin: 10mm;
            }
            
            .no-print {
                display: none !important;
            }
        }

        
        /* Print button */
        .print-btn {
            display: block;
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        
        .print-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="cash_bill_list.php" class="print-btn">← Back to List</a>
        <button onclick="printNormal()" class="print-btn" style="background:#0284c7"><i class="fa-solid fa-print"></i> Print Normal (A4)</button>
        <button onclick="printThermal()" class="print-btn" style="background:#f59e0b"><i class="fa-solid fa-receipt"></i> Print Thermal (POS)</button>
    </div>

    
    <div class="header">
        <?php if (!empty($company['company_logo']) && file_exists($company['company_logo'])): ?>
            <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" alt="Company Logo" class="company-logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($company ? $company['company_name'] : 'Bright Brush'); ?></h1>
        <?php if ($company && !empty($company['company_address'])): ?>
            <p><?php echo nl2br(htmlspecialchars($company['company_address'])); ?></p>
        <?php endif; ?>
        <?php if ($company && !empty($company['phone'])): ?>
            <p>Phone: <?php echo htmlspecialchars($company['phone']); ?></p>
        <?php endif; ?>
        <?php if ($company && !empty($company['email'])): ?>
            <p>Email: <?php echo htmlspecialchars($company['email']); ?></p>
        <?php endif; ?>
       
        <?php if ($company && !empty($company['registration_number'])): ?>
            <p>Reg. No.: <?php echo htmlspecialchars($company['registration_number']); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="bill-info">
        <p><span>Bill No:</span> <span><?php echo htmlspecialchars($bill['bill_number']); ?></span></p>
        <p><span>Date:</span> <span><?php echo $formatted_date; ?></span></p>
        <?php if (!empty($bill['customer_name'])): ?>
        <p><span>Customer:</span> <span><?php echo htmlspecialchars($bill['customer_name']); ?></span></p>
        <?php endif; ?>
        <p><span>Mobile:</span> <span><?php echo htmlspecialchars($bill['mobile_number']); ?></span></p>
        <p><span>Created by:</span> <span><?php echo htmlspecialchars($bill['created_by_name'] ?? 'System'); ?></span></p>
    </div>
    
    <table class="items" style="border-bottom: 2px solid #797878;">
        <thead>
            <tr>
                <th class="name">Item</th>
                <th class="qty" style="text-align: left;">Qty</th>
                <th class="rate"  style="text-align: left; margin-right: 10px;" >Rate</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="qty" style="text-align: left;"><?php echo (int)$item['quantity']; ?></td>
                <td class="rate"  style="text-align: left;"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="amount"><?php echo number_format($item['total_price'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <table class="totals">
        <tr>
            <td>Subtotal (Without GST):</td>
            <td>₹<?php echo number_format($subtotal_without_gst, 2); ?></td>
        </tr>
        <?php if ($total_cgst > 0): ?>
        <tr>
            <td>CGST (<?php echo number_format($avg_cgst_percentage, 2); ?>%):</td>
            <td>₹<?php echo number_format($total_cgst, 2); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($total_sgst > 0): ?>
        <tr>
            <td>SGST (<?php echo number_format($avg_sgst_percentage, 2); ?>%):</td>
            <td>₹<?php echo number_format($total_sgst, 2); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($total_igst > 0): ?>
        <tr>
            <td>IGST (<?php echo number_format($avg_igst_percentage, 2); ?>%):</td>
            <td>₹<?php echo number_format($total_igst, 2); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="font-weight: bold;">Grand Total:</td>
            <td>₹<?php echo number_format($grand_total, 2); ?></td>
        </tr>
        <tr>
            <td>Paid:</td>
            <td>₹<?php echo number_format($paid, 2); ?></td>
        </tr>
        <tr>
            <td>Balance:</td>
            <td>₹<?php echo number_format($balance, 2); ?></td>
        </tr>
    </table>
    
    <div class="thank-you">
        Thank you for your visit!
    </div>
    
    <div class="footer">
        <p>This is a computer generated bill.</p>
        
        <p>Goods once sold will not be taken back.</p>
        <p>E. & O.E.</p>
    </div>
    
    <script>
        // Auto-print when page loads (only if coming from form submission)
        function printNormal() {
            document.body.classList.add('a4-mode');
            window.print();
            setTimeout(() => { document.body.classList.remove('a4-mode'); }, 1000);
        }

        function printThermal() {
            document.body.classList.remove('a4-mode');
            window.print();
        }

        if (window.location.search.includes('print=1')) {
            printThermal();
        }

    </script>
</body>
</html>
