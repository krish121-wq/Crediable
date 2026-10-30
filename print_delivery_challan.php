<?php
ob_start();
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized access. Please login.");
}

$challan_id = (int)($_GET['id'] ?? 0);
if (!$challan_id) {
    exit("Invalid Challan ID");
}

// Fetch Challan Details
$stmt = $conn->prepare("SELECT * FROM delivery_challans WHERE id = ?");
$stmt->bind_param("i", $challan_id);
$stmt->execute();
$challan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$challan) {
    exit("Delivery Challan Not Found");
}

// Fetch Items with HSN and unit
$stmt = $conn->prepare("
    SELECT dci.*, p.hsn_code, p.unit 
    FROM delivery_challan_items dci 
    LEFT JOIN products p ON (dci.product_name = p.product_name OR dci.id = p.id)
    WHERE dci.challan_id = ?
");
$stmt->bind_param("i", $challan_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fallback items query if empty
if (empty($items)) {
    $stmt = $conn->prepare("SELECT * FROM delivery_challan_items WHERE challan_id = ?");
    $stmt->bind_param("i", $challan_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch Company Details
$company = [
    'company_name' => 'Credible Technologies',
    'company_address' => '',
    'company_gst' => '',
    'company_phone' => '',
    'company_email' => '',
    'company_logo' => ''
];
$comp_res = $conn->query("SELECT * FROM company_details LIMIT 1");
if ($comp_res && $comp_res->num_rows > 0) {
    $company = array_merge($company, $comp_res->fetch_assoc());
}

$grand_total = 0;
$total_qty = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Delivery Challan #<?= htmlspecialchars($challan['challan_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    body {
        font-family: 'Inter', Arial, sans-serif;
        font-size: 12px;
        color: #1e293b;
        background: #f1f5f9;
        padding: 24px 10px;
    }
    .print-actions {
        max-width: 800px;
        margin: 0 auto 16px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    .btn-action {
        background: #0284c7;
        color: #fff;
        border: none;
        padding: 8px 18px;
        font-weight: 700;
        font-size: 13px;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        box-shadow: 0 2px 6px rgba(2,132,199,0.25);
    }
    .btn-action:hover {
        background: #0369a1;
    }

    /* VYAPAR-STYLE BOXED CHALLAN SHEET */
    .challan-container {
        background: #ffffff;
        max-width: 800px;
        margin: 0 auto;
        border: 2px solid #334155;
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    }

    /* Header Banner */
    .challan-banner {
        background: #f1f5f9;
        border-bottom: 2px solid #334155;
        padding: 8px 16px;
        text-align: center;
        font-size: 16px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #0f172a;
    }

    /* Table grid layout */
    .grid-table {
        width: 100%;
        border-collapse: collapse;
    }
    .grid-table td, .grid-table th {
        border: 1px solid #334155;
        padding: 8px 10px;
        vertical-align: top;
    }

    .company-cell {
        width: 65%;
        border-right: 2px solid #334155 !important;
    }
    .logo-cell {
        width: 35%;
        text-align: center;
        vertical-align: middle !important;
        background: #fafafa;
    }
    .logo-cell img {
        max-width: 150px;
        max-height: 70px;
        object-fit: contain;
    }
    .logo-fallback {
        font-size: 20px;
        font-weight: 800;
        color: #64748b;
        letter-spacing: 1px;
    }

    .company-name {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 3px;
        text-transform: uppercase;
    }

    /* Party Details Sub-table */
    .party-title {
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 4px;
        border-bottom: 1px dashed #cbd5e1;
        padding-bottom: 2px;
    }
    .party-name-val {
        font-size: 13.5px;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 2px;
    }

    /* Meta Info Bar */
    .meta-bar {
        background: #f8fafc;
        border-top: 1.5px solid #334155;
        border-bottom: 1.5px solid #334155;
        padding: 6px 10px;
    }

    /* Items Table */
    .items-grid {
        width: 100%;
        border-collapse: collapse;
    }
    .items-grid th {
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 11px;
        border: 1px solid #334155;
        padding: 7px 8px;
        text-align: left;
    }
    .items-grid td {
        border: 1px solid #334155;
        padding: 7px 8px;
        font-size: 11.5px;
    }
    .items-grid tr:nth-child(even) td {
        background: #fbfcfe;
    }

    .total-row td {
        font-weight: 800;
        background: #f1f5f9 !important;
        font-size: 12px;
        border-top: 2px solid #334155;
    }

    /* Terms & Signature Section */
    .terms-box {
        width: 55%;
        border-right: 2px solid #334155 !important;
        background: #ffffff;
    }
    .sign-box {
        width: 45%;
        text-align: center;
        vertical-align: bottom !important;
        height: 100px;
        padding-bottom: 10px;
        background: #ffffff;
    }

    .confirm-box {
        border-top: 1.5px solid #334155;
        padding: 6px 10px;
        font-size: 11px;
        background: #f8fafc;
    }
    .confirm-fields {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-top: 4px;
    }
    .confirm-field {
        border-bottom: 1px dotted #64748b;
        padding-bottom: 2px;
    }

    @media print {
        body {
            background: #fff;
            padding: 0;
        }
        .print-actions {
            display: none !important;
        }
        .challan-container {
            border: 2px solid #000;
            box-shadow: none;
            width: 100%;
            max-width: 100%;
        }
        .grid-table td, .grid-table th, .items-grid th, .items-grid td {
            border-color: #000 !important;
        }
    }
</style>
</head>
<body>

<div class="print-actions">
    <button class="btn-action" onclick="window.print()"><i class="fas fa-print"></i> Print Delivery Challan</button>
</div>

<div class="challan-container">
    
    <!-- TOP BANNER -->
    <div class="challan-banner">Delivery Challan</div>

    <!-- COMPANY & LOGO ROW -->
    <table class="grid-table" style="border-top:none;">
        <tr>
            <td class="company-cell">
                <div class="company-name"><?= htmlspecialchars($company['company_name']) ?></div>
                <?php if (!empty($company['company_address'])): ?>
                    <div><strong>Address:</strong> <?= nl2br(htmlspecialchars($company['company_address'])) ?></div>
                <?php endif; ?>
                <div><strong>Phone No.:</strong> <?= htmlspecialchars($company['phone'] ?? $company['company_phone'] ?? 'N/A') ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($company['email'] ?? $company['company_email'] ?? 'N/A') ?></div>
                <div><strong>GSTIN:</strong> <?= htmlspecialchars($company['company_gst'] ?: 'N/A') ?></div>
            </td>
            <td class="logo-cell">
                <?php if (!empty($company['company_logo']) && file_exists($company['company_logo'])): ?>
                    <img src="<?= htmlspecialchars($company['company_logo']) ?>" alt="Logo">
                <?php else: ?>
                    <div class="logo-fallback"><?= htmlspecialchars($company['company_name']) ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- PARTY & SHIPPING DETAILS (2 COLUMNS) -->
    <table class="grid-table" style="border-top:none;">
        <tr>
            <td style="width: 50%; border-right: 2px solid #334155;">
                <div class="party-title">Delivery Challan For (Billed To):</div>
                <div class="party-name-val"><?= htmlspecialchars($challan['supplier_name']) ?></div>
                <div><strong>Address:</strong> <?= htmlspecialchars($challan['address'] ?? 'As per records') ?></div>
                <div><strong>Phone No.:</strong> <?= htmlspecialchars($challan['party_no'] ?: 'N/A') ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($challan['email'] ?? 'N/A') ?></div>
                <div><strong>GSTIN:</strong> <?= htmlspecialchars($challan['party_gst'] ?? 'N/A') ?></div>
            </td>
            <td style="width: 50%;">
                <div class="party-title">Shipping To (Consignee):</div>
                <div class="party-name-val"><?= htmlspecialchars($challan['supplier_name']) ?></div>
                <div><strong>Shipping Address:</strong> <?= htmlspecialchars($challan['shipping_address'] ?? $challan['address'] ?? 'Same as billing address') ?></div>
                <div><strong>Phone No.:</strong> <?= htmlspecialchars($challan['party_no'] ?: 'N/A') ?></div>
                <div><strong>Dispatch Notes:</strong> <?= htmlspecialchars($challan['additional_notes'] ?: 'Goods Dispatched in good condition') ?></div>
            </td>
        </tr>
    </table>

    <!-- METADATA STRIP -->
    <div class="meta-bar">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 35%;"><strong>Challan No.:</strong> #<?= htmlspecialchars($challan['challan_no']) ?></td>
                <td style="width: 35%;"><strong>Date:</strong> <?= date('d-m-Y', strtotime($challan['challan_date'])) ?></td>
                <td style="width: 30%; text-align: right;"><strong>Invoice Ref:</strong> <?= htmlspecialchars($challan['invoice_no'] ?: '—') ?></td>
            </tr>
        </table>
    </div>

    <!-- ITEM GRID TABLE -->
    <table class="items-grid">
        <thead>
            <tr>
                <th style="width: 6%; text-align: center;">Sl No.</th>
                <th style="width: 44%;">Item Name &amp; Description</th>
                <th style="width: 14%; text-align: center;">HSN / SAC</th>
                <th style="width: 10%; text-align: center;">Quantity</th>
                <th style="width: 8%; text-align: center;">Unit</th>
                <th style="width: 18%; text-align: right;">Total Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $item): 
                $grand_total += (float)$item['total_amount'];
                $total_qty += (int)$item['quantity'];
                $hsn = !empty($item['hsn_code']) ? $item['hsn_code'] : ($item['batch_no'] ?: '—');
                $unit = !empty($item['unit']) ? $item['unit'] : 'Pcs';
            ?>
            <tr>
                <td style="text-align: center;"><?= $idx + 1 ?></td>
                <td>
                    <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                    <?php if (!empty($item['expiry_date'])): ?>
                        <div style="font-size: 10px; color: #64748b;">Expiry: <?= htmlspecialchars($item['expiry_date']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><?= htmlspecialchars($hsn) ?></td>
                <td style="text-align: center; font-weight: 700;"><?= $item['quantity'] ?></td>
                <td style="text-align: center;"><?= htmlspecialchars($unit) ?></td>
                <td style="text-align: right; font-weight: 600;">₹<?= number_format($item['total_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- FILLER ROWS IF NEEDED FOR STANDARD VYAPAR LOOK -->
            <?php for ($i = count($items); $i < 3; $i++): ?>
            <tr>
                <td style="text-align: center; color: #cbd5e1;">&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <?php endfor; ?>

            <!-- TOTAL SUMMARY ROW -->
            <tr class="total-row">
                <td colspan="3" style="text-align: right;"><strong>TOTAL</strong></td>
                <td style="text-align: center;"><strong><?= $total_qty ?></strong></td>
                <td style="text-align: center;"><strong>Pcs</strong></td>
                <td style="text-align: right; font-size: 13px; color: #0284c7;"><strong>₹<?= number_format($grand_total, 2) ?></strong></td>
            </tr>
        </tbody>
    </table>

    <!-- TERMS & SIGNATURE SECTION -->
    <table class="grid-table" style="border-top:none;">
        <tr>
            <td class="terms-box">
                <div style="font-weight: 700; text-transform: uppercase; font-size: 11px; margin-bottom: 4px;">Terms and conditions:</div>
                <div style="font-size: 11px; line-height: 1.5; color: #475569;">
                    1. Goods once dispatched as per this challan must be inspected upon receipt.<br>
                    2. Any discrepancy or damage must be reported immediately upon delivery.<br>
                    3. Subject to local jurisdiction.
                </div>
            </td>
            <td class="sign-box">
                <div style="font-weight: 700; font-size: 11.5px; margin-bottom: 45px;">
                    For, <?= htmlspecialchars($company['company_name']) ?>
                </div>
                <div style="border-top: 1px solid #334155; width: 80%; margin: 0 auto; padding-top: 3px; font-weight: 700; font-size: 11px;">
                    Authorised Signature
                </div>
            </td>
        </tr>
    </table>

    <!-- RECEIVED BY CONFIRMATION BOX -->
    <div class="confirm-box">
        <div style="font-weight: 700; text-transform: uppercase; font-size: 10.5px;">Received By:</div>
        <div class="confirm-fields">
            <div class="confirm-field"><strong>Name:</strong> </div>
            <div class="confirm-field"><strong>Comment / Vehicle:</strong> </div>
            <div class="confirm-field"><strong>Date:</strong> </div>
            <div class="confirm-field"><strong>Signature:</strong> </div>
        </div>
    </div>

    <!-- DELIVERED BY CONFIRMATION BOX -->
    <div class="confirm-box" style="border-top: 1px solid #334155;">
        <div style="font-weight: 700; text-transform: uppercase; font-size: 10.5px;">Delivered By:</div>
        <div class="confirm-fields">
            <div class="confirm-field"><strong>Name:</strong> </div>
            <div class="confirm-field"><strong>Comment / Dispatch Mode:</strong> </div>
            <div class="confirm-field"><strong>Date:</strong> </div>
            <div class="confirm-field"><strong>Signature:</strong> </div>
        </div>
    </div>

</div>

</body>
</html>
<?php ob_end_flush(); ?>
