<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_msg = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_adjustment'])) {
    $product_id = intval($_POST['product_id']);
    $software_stock = intval($_POST['software_stock']);
    $actual_stock = intval($_POST['actual_stock']);
    $adjustment_qty = $actual_stock - $software_stock;
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if ($product_id > 0 && $adjustment_qty != 0) {
        try {
            $conn->begin_transaction();          
            $stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $actual_stock, $product_id);
            $stmt->execute();     
            $conn->commit();
            $success_msg = "Stock adjusted successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    } elseif ($adjustment_qty == 0) {
        $error_message = "No adjustment needed. Stock is already correct.";
    }
}
$products = $conn->query("SELECT id, product_name, product_code, quantity FROM products ORDER BY product_name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustment – CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220;
            --brand-mid: #0ea5e9;
            --brand-light: #e0f2fe;
            --success: #16a34a;
            --danger: #dc2626;
            --bg-light: #f4f8fb;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --radius: 12px;
            --shadow: 0 8px 24px rgba(2,132,199,.11);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            overflow-x: hidden;
            color: var(--text);
        }

        .content-area {
            margin-left: 260px;
            margin-top: 70px;
            padding: 20px 24px 40px;
            transition: 0.3s;
            min-height: calc(100vh - 70px);
        }

        @media (max-width: 992px) {
            .content-area {
                margin-left: 0;
                padding: 16px;
            }
        }

        /* Card styling */
        .adjustment-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            padding: 28px;
            max-width: 1000px;
            margin: auto;
        }

        @media (max-width: 768px) {
            .adjustment-card {
                padding: 18px;
            }
        }

        .header-title {
            color: var(--brand-dark);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 1.25rem;
            margin-bottom: 24px;
            border-bottom: 2px solid var(--brand-light);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
        }
        .header-title i { color: var(--brand); }

        /* Stock boxes */
        .stock-box {
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 0;
            height: 100%;
        }

        .box-software {
            background: var(--brand-light);
            border: 1px solid rgba(2,132,199,.18);
            color: var(--brand-dark);
        }

        .text-shortage {
            background: #fff5f5;
            color: var(--danger);
            border: 1px solid #feb2b2;
        }

        .text-excess {
            background: #f0fff4;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        /* Form elements */
        .form-label {
            font-weight: 700;
            color: var(--text-mid);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: .35px;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            font-size: 0.9rem;
            background: #fafafa;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--brand);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(2,132,199,.12);
        }

        /* Button */
        .btn-adjust {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            width: 100%;
            transition: 0.3s;
            font-size: 0.9rem;
            box-shadow: 0 5px 16px rgba(2,132,199,.30);
        }

        .btn-adjust:hover {
            background: linear-gradient(135deg, #000, var(--brand-dark));
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(2,132,199,.40);
        }

        /* Number display */
        .fs-number {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--brand-dark);
        }
        .text-shortage .fs-number { color: var(--danger); }
        .text-excess .fs-number { color: #2f855a; }

        /* Alert messages */
        .alert-custom {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success-custom {
            background: #f0fdf4;
            color: var(--success);
            border: 1px solid #86efac;
        }

        .alert-danger-custom {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fca5a5;
        }

        /* Row gap fix */
        .row.g-3 {
            --bs-gutter-y: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .fs-number { font-size: 1.3rem; }
            .header-title { font-size: 1.1rem; }
            .stock-box { padding: 10px; }
            .btn-adjust { padding: 10px 20px; }
        }

        @media (max-width: 576px) {
            .content-area { padding: 12px; }
            .adjustment-card { padding: 14px; }
            .fs-number { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
    <?php include('layout.php'); ?>

    <div class="content-area">
        <div class="adjustment-card">
            <h3 class="header-title"><i class="fas fa-sliders-h me-2"></i>Stock Adjustment</h3>

            <?php if ($success_msg): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.remove()" style="background:none; border:none; font-size:1rem;">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert-custom alert-danger-custom">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.remove()" style="background:none; border:none; font-size:1rem;">&times;</button>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="adjustmentForm">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label"><i class="fas fa-box me-1"></i> Select Item</label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">-- Search Item to Adjust --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-qty="<?php echo $p['quantity']; ?>">
                                    <?php echo htmlspecialchars($p['product_name']); ?> (<?php echo $p['product_code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 col-12">
                        <div class="stock-box box-software">
                            <small class="text-muted d-block"><i class="fas fa-database me-1"></i>Software Stock</small>
                            <span id="display_software" class="fs-number">0</span>
                            <input type="hidden" name="software_stock" id="software_stock" value="0">
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-12">
                        <div class="stock-box box-software">
                            <small class="text-muted d-block"><i class="fas fa-clipboard-list me-1"></i>Physical Stock</small>
                            <input type="number" name="actual_stock" id="actual_stock" class="form-control text-center" placeholder="Enter quantity" required disabled style="font-weight:700;">
                        </div>
                    </div>
                    
                    <div class="col-md-4 col-12">
                        <div id="diff_container" class="stock-box box-software">
                            <small class="text-muted d-block"><i class="fas fa-calculator me-1"></i>Difference</small>
                            <span id="display_diff" class="fs-number">0</span>
                        </div>
                    </div>

                    <div class="col-12" id="reason_section" style="display:none;">
                        <label class="form-label"><i class="fas fa-question-circle me-1"></i> Reason for Adjustment</label>
                        <select name="reason" id="reason" class="form-select">
                            <option value="">-- Select Reason --</option>
                        </select>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" name="submit_adjustment" class="btn-adjust" id="submitBtn">
                            <i class="fas fa-sync-alt me-2"></i> Update & Sync Stock
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            const shortageReasons = ["Damage", "Theft", "Wastage", "Expired Items", "Counting Error"];
            const excessReasons = ["Missed Entry", "Wrong Purchase Entry", "Counting Error", "Returns not logged"];

            $('#product_id').change(function() {
                const selected = $(this).find('option:selected');
                const qty = selected.data('qty') || 0;
                
                if($(this).val() !== "") {
                    $('#software_stock').val(qty);
                    $('#display_software').text(qty);
                    $('#actual_stock').prop('disabled', false).val('').focus();
                    $('#diff_container').removeClass('text-shortage text-excess').addClass('box-software');
                    $('#display_diff').text('0');
                    $('#reason_section').hide();
                } else {
                    resetForm();
                }
            });

            $('#actual_stock').on('input', function() {
                const softStock = parseInt($('#software_stock').val()) || 0;
                const actStock = parseInt($(this).val());
                
                if (isNaN(actStock)) {
                    $('#display_diff').text('0');
                    $('#reason_section').hide();
                    return;
                }

                const diff = actStock - softStock;
                $('#display_diff').text(diff > 0 ? '+' + diff : diff);
                
                if (diff !== 0) {
                    $('#reason_section').show();
                    const reasonSelect = $('#reason');
                    reasonSelect.empty().append('<option value="">-- Select Reason --</option>');

                    if (diff < 0) { 
                        $('#diff_container').removeClass('text-excess').addClass('text-shortage');
                        shortageReasons.forEach(r => reasonSelect.append(`<option value="${r}">${r}</option>`));
                    } else if (diff > 0) {
                        $('#diff_container').removeClass('text-shortage').addClass('text-excess');
                        excessReasons.forEach(r => reasonSelect.append(`<option value="${r}">${r}</option>`));
                    }
                } else {
                    $('#diff_container').removeClass('text-shortage text-excess').addClass('box-software');
                    $('#reason_section').hide();
                }
            });
            
            $('#adjustmentForm').on('submit', function(e) {
                const diff = parseInt($('#display_diff').text()) || 0;
                const reason = $('#reason').val();
                
                if (diff !== 0 && !reason) {
                    e.preventDefault();
                    alert('Please select a reason for this adjustment.');
                    $('#reason').focus();
                    return false;
                }
                return true;
            });

            function resetForm() {
                $('#product_id').val('');
                $('#display_software').text('0');
                $('#software_stock').val('0');
                $('#actual_stock').prop('disabled', true).val('');
                $('#display_diff').text('0');
                $('#reason_section').hide();
                $('#diff_container').removeClass('text-shortage text-excess').addClass('box-software');
                $('#reason').empty().append('<option value="">-- Select Reason --</option>');
            }
        });
    </script>
</body>
</html>