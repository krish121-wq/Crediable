<?php
session_start();
require_once 'config.php';

// Check user login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access denied. Please log in.");
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
error_reporting(E_ALL);



// Get form inputs with fallback to prevent undefined index errors
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$entry_no = isset($_POST['entry_no']) ? trim($_POST['entry_no']) : '';
$party_no = isset($_POST['party_no']) ? trim($_POST['party_no']) : '';
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$dt = isset($_POST['dt']) ? trim($_POST['dt']) : null;
$supplier_gstin = isset($_POST['supplier_gstin']) ? trim($_POST['supplier_gstin']) : null;
$recipient_gstin = isset($_POST['recipient_gstin']) ? trim($_POST['recipient_gstin']) : null;
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$product_ids = isset($_POST['product_id']) ? (array)$_POST['product_id'] : [];
$hsn_codes = isset($_POST['hsn_code']) ? (array)$_POST['hsn_code'] : [];
$purchase_prices = isset($_POST['purchase_price']) ? (array)$_POST['purchase_price'] : [];
$quantities = isset($_POST['qty']) ? (array)$_POST['qty'] : [];
$discounts = isset($_POST['discount']) ? (array)$_POST['discount'] : [];
$gst_rates = isset($_POST['gst_rate']) ? (array)$_POST['gst_rate'] : [];
$amounts = isset($_POST['amount']) ? (array)$_POST['amount'] : [];
$cgst = isset($_POST['cgst']) ? floatval($_POST['cgst']) : 0.00;
$sgst = isset($_POST['sgst']) ? floatval($_POST['sgst']) : 0.00;
$igst = isset($_POST['igst']) ? floatval($_POST['igst']) : 0.00;
$gross_amount = isset($_POST['gross_amount']) ? floatval($_POST['gross_amount']) : 0.00;
$total_discount = isset($_POST['total_discount']) ? floatval($_POST['total_discount']) : 0.00;
$round_off = isset($_POST['round_off']) ? floatval($_POST['round_off']) : 0.00;
$net_amount = isset($_POST['net_amount']) ? floatval($_POST['net_amount']) : 0.00;

// Validate required fields
$errors = [];
if (empty($name)) $errors[] = "Name is required.";
if (empty($entry_no)) $errors[] = "Purchase Bill ID is required.";
if (empty($party_no)) $errors[] = "Party No is required.";
if (empty($date)) $errors[] = "Date is required.";
if (empty($type) || !in_array($type, ['Cash', 'Credit', 'Online'])) $errors[] = "Valid Type is required.";
if (empty($product_ids) || count(array_filter($product_ids)) === 0) {
    $errors[] = "At least one product must be selected.";
}
foreach ($quantities as $i => $qty) {
    if (!empty($product_ids[$i]) && (empty($qty) || $qty <= 0)) {
        $errors[] = "Quantity must be greater than 0 for product in row " . ($i + 1) . ".";
    }
}

if (!empty($errors)) {
    error_log("Validation errors: " . implode("; ", $errors));
    http_response_code(400);
    die("Validation errors: " . implode(" ", $errors));
}

try {
    // Verify database connection
    if (!$conn) {
        error_log("Database connection failed: " . mysqli_connect_error());
        throw new Exception("Database connection failed.");
    }

    $conn->begin_transaction();

    // Escape inputs for purchases table
    $name = mysqli_real_escape_string($conn, $name);
    $entry_no = mysqli_real_escape_string($conn, $entry_no);
    $party_no = mysqli_real_escape_string($conn, $party_no);
    $date = mysqli_real_escape_string($conn, $date);
    $dt = $dt ? "'" . mysqli_real_escape_string($conn, $dt) . "'" : 'NULL';
    $supplier_gstin = $supplier_gstin ? "'" . mysqli_real_escape_string($conn, $supplier_gstin) . "'" : 'NULL';
    $recipient_gstin = $recipient_gstin ? "'" . mysqli_real_escape_string($conn, $recipient_gstin) . "'" : 'NULL';
    $type = mysqli_real_escape_string($conn, $type);
    $gross_amount = number_format($gross_amount, 2, '.', '');
    $cgst = number_format($cgst, 2, '.', '');
    $sgst = number_format($sgst, 2, '.', '');
    $igst = number_format($igst, 2, '.', '');
    $total_discount = number_format($total_discount, 2, '.', '');
    $round_off = number_format($round_off, 2, '.', '');
    $net_amount = number_format($net_amount, 2, '.', '');

    // Insert into purchases table
    $query = "INSERT INTO purchases (entry_no, name, party_no, date, bill_date, type, supplier_gstin, recipient_gstin, gross_amount, cgst, sgst, igst, total_discount, round_off, net_amount)
              VALUES ('$entry_no', '$name', '$party_no', '$date', $dt, '$type', $supplier_gstin, $recipient_gstin, $gross_amount, $cgst, $sgst, $igst, $total_discount, $round_off, $net_amount)";
    if (!mysqli_query($conn, $query)) {
        error_log("Failed to insert into purchases: " . mysqli_error($conn));
        throw new Exception("Failed to insert purchase entry: " . mysqli_error($conn));
    }
    $purchase_id = mysqli_insert_id($conn);

    // Process purchase items and update products
    for ($i = 0; $i < count($product_ids); $i++) {
        if (!empty($product_ids[$i]) && !empty($quantities[$i]) && $quantities[$i] > 0) {
            $product_id = intval($product_ids[$i]);
            $hsn_code = isset($hsn_codes[$i]) ? mysqli_real_escape_string($conn, $hsn_codes[$i]) : '';
            $purchase_price = isset($purchase_prices[$i]) ? number_format(floatval($purchase_prices[$i]), 2, '.', '') : 0.00;
            $quantity = intval($quantities[$i]);
            $discount = isset($discounts[$i]) ? number_format(floatval($discounts[$i]), 2, '.', '') : 0.00;
            $gst_rate = isset($gst_rates[$i]) ? number_format(floatval($gst_rates[$i]), 2, '.', '') : 0.00;
            $amount = isset($amounts[$i]) ? number_format(floatval($amounts[$i]), 2, '.', '') : 0.00;

            // Calculate GST amount (since purchase price includes GST)
            $gst_amount = 0.00;
            if ($gst_rate > 0 && $amount > 0) {
                // Calculate the base price (price before GST)
                $base_price = ($amount * 100) / (100 + $gst_rate);
                // GST amount is the difference between total price and base price
                $gst_amount = $amount - $base_price;
                $gst_amount = number_format($gst_amount, 2, '.', '');
            }

            // Verify product exists
            $query = "SELECT id FROM products WHERE id = $product_id";
            $result = mysqli_query($conn, $query);
            if (!$result || mysqli_num_rows($result) === 0) {
                error_log("Invalid product_id: $product_id in row " . ($i + 1));
                throw new Exception("Invalid product selected in row " . ($i + 1) . ".");
            }

            // Insert into purchase_items
            $hsn_code = $hsn_code ? "'$hsn_code'" : 'NULL';
            $query = "INSERT INTO purchase_items (purchase_id, product_id, hsn_code, purchase_price, quantity, discount, gst_percentage, gst_amount, amount)
                      VALUES ($purchase_id, $product_id, $hsn_code, $purchase_price, $quantity, $discount, $gst_rate, $gst_amount, $amount)";
            if (!mysqli_query($conn, $query)) {
                error_log("Failed to insert into purchase_items: " . mysqli_error($conn));
                throw new Exception("Failed to insert purchase item in row " . ($i + 1) . ": " . mysqli_error($conn));
            }

            // Process serial numbers
            $serials = $_POST['serials'] ?? [];
            $serial_str = trim($serials[$i] ?? '');
            if (!empty($serial_str)) {
                $serial_list = array_map('trim', explode(',', $serial_str));
                $serial_list = array_filter($serial_list);
                if (count($serial_list) !== $quantity) {
                    throw new Exception("Row " . ($i + 1) . ": You entered " . count($serial_list) . " serial numbers but quantity is $quantity.");
                }
                foreach ($serial_list as $sn) {
                    $sn = mysqli_real_escape_string($conn, $sn);
                    $squery = "INSERT INTO product_serials (product_id, serial_number, purchase_id, status) VALUES ($product_id, '$sn', $purchase_id, 'in_stock')";
                    if (!mysqli_query($conn, $squery)) {
                        throw new Exception("Failed to insert serial number '$sn'. It might already exist for this product.");
                    }
                }
            }

            // Update product quantity
            $query = "UPDATE products SET quantity = quantity + $quantity WHERE id = $product_id";
            if (!mysqli_query($conn, $query)) {
                error_log("Failed to update products quantity: " . mysqli_error($conn));
                throw new Exception("Failed to update product quantity in row " . ($i + 1) . ": " . mysqli_error($conn));
            }
        }
    }

    $conn->commit();
    
    // Set session variable for success message
    $_SESSION['purchase_saved'] = true;
    header("Location: purchase_entry.php?success=1");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in save-purchase.php: " . $e->getMessage());
    http_response_code(500);
    die("❌ Error: " . $e->getMessage());
} finally {
    $conn->close();
}
?>