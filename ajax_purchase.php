<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
error_reporting(E_ALL);

// Check user login
if (!isset($_SESSION['user_id'])) {
    error_log("Access denied: User not logged in");
    echo json_encode(['error' => 'Access denied. Please log in.']);
    http_response_code(403);
    exit;
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Non-POST request to ajax_purchase.php: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// View purchase details
if (isset($_POST['view_id'])) {
    $id = (int)$_POST['view_id'];
    if ($id <= 0) {
        error_log("Invalid view_id: " . $id);
        echo json_encode(['error' => 'Invalid purchase ID']);
        exit;
    }
    error_log("Processing view_id request: " . $id);
    try {
        $query = "SELECT p.*, pi.product_id, pi.hsn_code, pi.purchase_price, pi.quantity, pi.discount, pi.amount, pi.gst_percentage, pi.gst_amount, pr.product_name
                  FROM purchases p
                  LEFT JOIN purchase_items pi ON p.id = pi.purchase_id
                  LEFT JOIN products pr ON pi.product_id = pr.id
                  WHERE p.id = $id";
        $result = mysqli_query($conn, $query);
        if (!$result) {
            throw new Exception("Failed to fetch purchase details: " . mysqli_error($conn));
        }

        $purchase = null;
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (!$purchase) {
                $purchase = [
                    'id' => $row['id'],
                    'party_no' => $row['party_no'],
                    'name' => $row['name'],
                    'date' => $row['date'],
                    'entry_no' => $row['entry_no'],
                    'type' => $row['type'],
                    'bill_date' => $row['bill_date'],
                    'supplier_gstin' => $row['supplier_gstin'],
                    'recipient_gstin' => $row['recipient_gstin'],
                    'gross_amount' => floatval($row['gross_amount']),
                    'cgst' => floatval($row['cgst']),
                    'sgst' => floatval($row['sgst']),
                    'igst' => floatval($row['igst']),
                    'total_discount' => floatval($row['total_discount']),
                    'round_off' => floatval($row['round_off']),
                    'net_amount' => floatval($row['net_amount'])
                ];
            }
            if ($row['product_id']) {
                $items[] = [
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'hsn_code' => $row['hsn_code'],
                    'purchase_price' => floatval($row['purchase_price']),
                    'quantity' => $row['quantity'],
                    'discount' => floatval($row['discount']),
                    'gst_percentage' => floatval($row['gst_percentage']),
                    'gst_amount' => floatval($row['gst_amount']),
                    'amount' => floatval($row['amount'])
                ];
            }
        }

        if (!$purchase) {
            throw new Exception("Purchase entry not found.");
        }

        error_log("View_id response prepared: " . json_encode($purchase));
        echo json_encode([
            'id' => $purchase['id'],
            'party_no' => $purchase['party_no'],
            'name' => $purchase['name'],
            'date' => $purchase['date'],
            'entry_no' => $purchase['entry_no'],
            'type' => $purchase['type'],
            'bill_date' => $purchase['bill_date'],
            'supplier_gstin' => $purchase['supplier_gstin'],
            'recipient_gstin' => $purchase['recipient_gstin'],
            'gross_amount' => $purchase['gross_amount'],
            'cgst' => $purchase['cgst'],
            'sgst' => $purchase['sgst'],
            'igst' => $purchase['igst'],
            'total_discount' => $purchase['total_discount'],
            'round_off' => $purchase['round_off'],
            'net_amount' => $purchase['net_amount'],
            'items' => $items
        ]);
    } catch (Exception $e) {
        error_log("View error in ajax_purchase.php: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Edit purchase entry
if (isset($_POST['edit_purchase'])) {
    error_log("Processing edit_purchase request: " . json_encode($_POST));
    try {
        $conn->begin_transaction();

        $id = (int)($_POST['id'] ?? 0);
        $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
        $party_no = mysqli_real_escape_string($conn, trim($_POST['party_no'] ?? ''));
        $date = mysqli_real_escape_string($conn, trim($_POST['date'] ?? ''));
        $bill_date = !empty($_POST['bill_date']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['bill_date'])) . "'" : 'NULL';
        $type = mysqli_real_escape_string($conn, trim($_POST['type'] ?? ''));
        $supplier_gstin = !empty($_POST['supplier_gstin']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['supplier_gstin'])) . "'" : 'NULL';
        $recipient_gstin = !empty($_POST['recipient_gstin']) ? "'" . mysqli_real_escape_string($conn, trim($_POST['recipient_gstin'])) . "'" : 'NULL';
        $gross_amount = number_format(floatval($_POST['gross_amount'] ?? 0), 2, '.', '');
        $cgst = number_format(floatval($_POST['cgst'] ?? 0), 2, '.', '');
        $sgst = number_format(floatval($_POST['sgst'] ?? 0), 2, '.', '');
        $igst = number_format(floatval($_POST['igst'] ?? 0), 2, '.', '');
        $total_discount = number_format(floatval($_POST['total_discount'] ?? 0), 2, '.', '');
        $round_off = number_format(floatval($_POST['round_off'] ?? 0), 2, '.', '');
        $net_amount = number_format(floatval($_POST['net_amount'] ?? 0), 2, '.', '');

        $product_ids = $_POST['product_id'] ?? [];
        $hsn_codes = $_POST['hsn_code'] ?? [];
        $purchase_prices = $_POST['purchase_price'] ?? [];
        $quantities = $_POST['qty'] ?? [];
        $discounts = $_POST['discount'] ?? [];
        $gst_rates = $_POST['gst_rate'] ?? [];
        $amounts = $_POST['amount'] ?? [];

        // Validate required fields
        if (empty($name) || empty($party_no) || empty($date) || empty($type) || !in_array($type, ['Cash', 'Credit', 'Online'])) {
            throw new Exception("Required fields are missing or invalid.");
        }
        if (empty($product_ids) || count(array_filter($product_ids)) === 0) {
            throw new Exception("At least one product must be selected.");
        }
        foreach ($quantities as $i => $qty) {
            if (!empty($product_ids[$i]) && (empty($qty) || $qty <= 0)) {
                throw new Exception("Quantity must be greater than 0 for product in row " . ($i + 1));
            }
        }

        // Verify purchase exists
        $query = "SELECT id FROM purchases WHERE id = $id";
        $result = mysqli_query($conn, $query);
        if (!$result || mysqli_num_rows($result) === 0) {
            throw new Exception("Purchase entry not found.");
        }

        // Get original quantities from purchase_items to adjust product quantities
        $query = "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = $id";
        $result = mysqli_query($conn, $query);
        $original_items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $original_items[$row['product_id']] = $row['quantity'];
        }

        // Update purchases table
        $query = "UPDATE purchases SET 
                  name = '$name', 
                  party_no = '$party_no', 
                  date = '$date', 
                  bill_date = $bill_date, 
                  type = '$type', 
                  supplier_gstin = $supplier_gstin, 
                  recipient_gstin = $recipient_gstin, 
                  gross_amount = $gross_amount, 
                  cgst = $cgst, 
                  sgst = $sgst, 
                  igst = $igst, 
                  total_discount = $total_discount, 
                  round_off = $round_off, 
                  net_amount = $net_amount 
                  WHERE id = $id";
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Failed to update purchase entry: " . mysqli_error($conn));
        }

        // Delete existing purchase_items
        $query = "DELETE FROM purchase_items WHERE purchase_id = $id";
        if (!mysqli_query($conn, $query)) {
            throw new Exception("Failed to delete existing purchase items: " . mysqli_error($conn));
        }

        // Insert updated purchase_items and adjust product quantities
        for ($i = 0; $i < count($product_ids); $i++) {
            if (!empty($product_ids[$i]) && !empty($quantities[$i]) && $quantities[$i] > 0) {
                $product_id = intval($product_ids[$i]);
                $hsn_code = isset($hsn_codes[$i]) ? "'" . mysqli_real_escape_string($conn, $hsn_codes[$i]) . "'" : 'NULL';
                $purchase_price = number_format(floatval($purchase_prices[$i] ?? 0), 2, '.', '');
                $quantity = intval($quantities[$i]);
                $discount = number_format(floatval($discounts[$i] ?? 0), 2, '.', '');
                $gst_rate = number_format(floatval($gst_rates[$i] ?? 0), 2, '.', '');
                $amount = number_format(floatval($amounts[$i] ?? 0), 2, '.', '');

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
                    throw new Exception("Invalid product selected in row " . ($i + 1));
                }

                // Insert into purchase_items
                $query = "INSERT INTO purchase_items (purchase_id, product_id, hsn_code, purchase_price, quantity, discount, gst_percentage, gst_amount, amount)
                          VALUES ($id, $product_id, $hsn_code, $purchase_price, $quantity, $discount, $gst_rate, $gst_amount, $amount)";
                if (!mysqli_query($conn, $query)) {
                    throw new Exception("Failed to insert purchase item in row " . ($i + 1) . ": " . mysqli_error($conn));
                }

                // Adjust product quantity
                $original_qty = isset($original_items[$product_id]) ? $original_items[$product_id] : 0;
                $qty_diff = $quantity - $original_qty;
                if ($qty_diff != 0) {
                    $query = "UPDATE products SET quantity = quantity + $qty_diff WHERE id = $product_id";
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception("Failed to update product quantity in row " . ($i + 1) . ": " . mysqli_error($conn));
                    }
                }
            }
        }

        $conn->commit();
        error_log("Purchase entry updated successfully: ID " . $id);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Edit error in ajax_purchase.php: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Log unexpected POST requests
error_log("Unexpected POST request to ajax_purchase.php: " . json_encode($_POST));
echo json_encode(['error' => 'Invalid request']);
?>