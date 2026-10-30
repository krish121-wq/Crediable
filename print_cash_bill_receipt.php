<?php
require_once 'config.php';
require_once 'includes/company_functions.php';

// Get company details
$company = getCompanyDetails($conn);

// Check if bill ID is provided
if (!isset($_GET['bill_id']) || !is_numeric($_GET['bill_id'])) {
    die('Invalid bill ID');
}

$bill_id = (int)$_GET['bill_id'];

// Fetch bill details
$bill = [];
$items = [];

try {
    // Get bill information
    $stmt = $conn->prepare("SELECT cbp.*, cb.bill_number, u.full_name as created_by_name 
                           FROM cash_bill_payments cbp 
                           LEFT JOIN cash_bills cb ON cbp.cash_bill_id = cb.id
                           LEFT JOIN users u ON cbp.created_by = u.id 
                           WHERE cbp.id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Bill not found');
    }
    
    $bill = $result->fetch_assoc();
    $stmt->close();
    
    // Get bill items
    $stmt = $conn->prepare("SELECT cbi.*, p.product_name, p.product_code 
                           FROM cash_bill_items cbi 
                           LEFT JOIN products p ON cbi.product_id = p.id 
                           WHERE cbi.cash_bill_id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Format date and time
$date = new DateTime($bill['created_at']);
$formatted_date = $date->format('d/m/Y');
$formatted_time = $date->format('h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Receipt #<?php echo htmlspecialchars($bill['bill_number']); ?></title>
    <style>
        /* Thermal printer friendly styles */
        @page {
            margin: 0;
            padding: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.2;
        }
        
        body {
            width: 58mm; /* 2.5 inches */
            max-width: 58mm;
            margin: 0 auto;
            padding: 1mm;
            color: #000;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-bold { font-weight: bold; }
        .text-uppercase { text-transform: uppercase; }
        .mt-1 { margin-top: 1mm; }
        .mb-1 { margin-bottom: 1mm; }
        .py-1 { padding-top: 1mm; padding-bottom: 1mm; }
        .border-bottom { border-bottom: 1px dashed #000; }
        .dotted-line { border-top: 1px dashed #000; margin: 2mm 0; }
        
        .header {
            text-align: center;
            margin-bottom: 2mm;
            padding-bottom: 2mm;
            border-bottom: 1px dashed #000;
        }
        
        .company-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }
        
        .company-address {
            font-size: 8px;
            margin-bottom: 1mm;
        }
        
        .receipt-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2mm 0;
        }
        
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2mm;
            font-size: 8px;
        }
        
        .customer-info {
            margin-bottom: 2mm;
            font-size: 8px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 2mm 0;
            font-size: 8px;
        }
        
        .items-table th {
            text-align: left;
            border-bottom: 1px dashed #000;
            padding: 1mm 0;
        }
        
        .items-table td {
            padding: 1mm 0;
            vertical-align: top;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .total-section {
            margin-top: 2mm;
            font-size: 9px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 1mm 0;
        }
        
        .footer {
            margin-top: 3mm;
            font-size: 7px;
            text-align: center;
        }
        
        .barcode {
            text-align: center;
            margin: 2mm 0;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 16px;
        }
        
        @media print {
            @page {
                size: 58mm 100%;
                margin: 0;
                padding: 0;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Your Company'); ?></div>
        <div class="company-address">
            <?php 
            echo htmlspecialchars(($company['company_address'] ?? ''));
            ?>
        </div>
        <div class="contact">
            <?php 
            if (!empty($company['phone'])) {
                echo 'Ph: ' . htmlspecialchars($company['phone']);
                if (!empty($company['email'])) echo ' | ';
            }
            if (!empty($company['email'])) {
                echo htmlspecialchars($company['email']);
            }
            ?>
        </div>
    </div>
    
    <div class="receipt-title">MONEY RECEIPT</div>
    
    <div class="bill-info">
        <div>Bill #: <?php echo htmlspecialchars($bill['bill_number']); ?></div>
        <div>Date: <?php echo $formatted_date; ?></div>
        
    </div>
    
    <div class="customer-info">
        <div><strong>Customer:</strong> <?php echo !empty($bill['customer_name']) ? htmlspecialchars($bill['customer_name']) : 'Walk-in Customer'; ?></div>
        <?php if (!empty($bill['mobile_number'])): ?>
        <div><strong>Mobile:</strong> <?php echo htmlspecialchars($bill['mobile_number']); ?></div>
        <?php endif; ?>
    </div>
    
    <div class="dotted-line"></div>
    
   
    
    <div class="total-section">
        <div class="total-row">
           
        </div>
        <div class="total-row" style="margin: 5mm 0;">
            <div style="font-size: 14px; font-weight: bold;">Amount Paid:</div>
            <div style="font-size: 14px; font-weight: bold;">₹<?php echo number_format($bill['amount'], 2); ?></div>
        </div>
        
       
        
    </div>
     <div class="dotted-line"></div>
    
    <div class="barcode">*<?php echo $bill['bill_number']; ?>*</div>
    
    <div class="footer">
        <div>Thank you for your business!</div>
        <div>For any queries, please contact us</div>
        <div>Generated on <?php echo date('d/m/Y'); ?></div>
        <div class="text-bold">This is a computer-generated receipt</div>
    </div>
    
    <script>
        
        window.onload = function() {
            setTimeout(function() {
                if (window.location.search.indexOf('print=1') > -1) {
                    window.print();
                    setTimeout(window.close, 100);
                }
            }, 500);
        };
    </script>
</body>
</html>
