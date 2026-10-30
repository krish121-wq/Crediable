<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Barcode - <?= htmlspecialchars($product['product_code']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body { background: #f4f5f7; font-family: 'Inter', sans-serif; }
        
        /* Barcode Label Design (Thermal Printer Style) */
        .label-container {
            width: 50mm; /* Standard 2-inch label width */
            height: 25mm; /* 1-inch label height */
            padding: 2mm;
            border: 1px dashed #ccc;
            background: white;
            margin: 10px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: black;
        }

        .label-item-name {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            width: 100%;
            text-align: center;
        }

        .label-design {
            font-size: 8px;
            margin: 0;
        }

        .label-price {
            font-size: 10px;
            font-weight: bold;
            margin-top: 1px;
        }

        #barcode {
            width: 100%;
            height: auto;
            max-height: 12mm;
        }

        @media print {
            .no-print { display: none;  }
            body { background: white; padding: 0; margin: 0; }
            .label-container { 
                margin: 0; 
                border: none; 
                page-break-after: always; 
            }
            @page {
                size: 50mm 25mm; /* Set your specific label size here */
                margin: 0;
            }
        }

        .control-panel {
            max-width: 400px;
            margin: 66px auto;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>
    <div class="control-panel no-print">
        <h5 class="text-center mb-3">Barcode Generator</h5>
        <div class="mb-3">
            <label class="form-label">Quantity to Print</label>
            <input type="number" id="printQty" class="form-control" value="1" min="1" max="100">
        </div>
        <div class="d-grid gap-2">
            <button onclick="generateLabels()" class="btn btn-primary" style="background: #571F5A; border:none;">Set Qty</button>
            <button onclick="window.print()" class="btn btn-success">Print Labels</button>
            <a href="fetch_master_list.php" class="btn btn-link text-secondary">Back to List</a>
        </div>
    </div>

    <!-- This is where labels will be injected -->
    <div id="printArea"></div>

    <script>
        function generateLabels() {
            const qty = document.getElementById('printQty').value;
            const container = document.getElementById('printArea');
            container.innerHTML = ''; // Clear previous

            for (let i = 0; i < qty; i++) {
                const label = document.createElement('div');
                label.className = 'label-container';
                label.innerHTML = `
                    <div class="label-item-name"><?= htmlspecialchars($product['product_name']) ?></div>
                    <div class="label-design">Design No.: <?= htmlspecialchars($product['product_design_code']) ?></div>
                    <svg class="barcode-svg"></svg>
                    <div class="label-price">MRP: ₹<?= number_format($product['sell_price'], 2) ?></div>
                `;
                container.appendChild(label);
                
                // Initialize barcode for this SVG
                const svg = label.querySelector('.barcode-svg');
                JsBarcode(svg, "<?= $product['product_code'] ?>", {
                    format: "CODE128",
                    width: 1.2,
                    height: 35,
                    displayValue: true,
                    fontSize: 10,
                    margin: 0
                });
            }
        }

        // Run once on load
        window.onload = generateLabels;
    </script>
</body>
</html>