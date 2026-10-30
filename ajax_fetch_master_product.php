<?php
// ajax_fetch_master_product.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Send JSON error
function sendError($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method');
}

// ==================================================================
// 1. VIEW PRODUCT
// ==================================================================
if (isset($_POST['view_id']) && is_numeric($_POST['view_id'])) {
    $id = (int)$_POST['view_id'];

    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    if (!$stmt) sendError("Database prepare error");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        sendError("Product not found");
    }

    $product = $result->fetch_assoc();
    $stmt->close();
    
    // Format values
    $product['sgst'] = round(floatval($product['sgst']), 2);
    $product['cgst'] = round(floatval($product['cgst']), 2);
    $product['igst'] = round(floatval($product['igst']), 2);
    $product['gst_percentage'] = round(floatval($product['gst_percentage']), 2);
    $product['purchase_price'] = round(floatval($product['purchase_price']), 2);
    $product['sell_price'] = round(floatval($product['sell_price']), 2);
    $product['quantity'] = intval($product['quantity']);
    
    echo json_encode($product);
    exit;
}

// ==================================================================
// 2. EDIT / UPDATE PRODUCT
// ==================================================================
if (isset($_POST['edit_product']) || isset($_POST['update_product'])) {
    
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) sendError("Invalid product ID");

    $product_name = trim($_POST['product_name'] ?? '');
    $product_code = trim($_POST['product_code'] ?? '');
    $product_design_code = trim($_POST['product_design_code'] ?? '');
    $product_description = trim($_POST['product_description'] ?? '');
    $hsn_code = trim($_POST['hsn_code'] ?? '');
    $unit = trim($_POST['unit'] ?? 'Piece');
    $gst_percentage = (float)($_POST['gst_percentage'] ?? 0);
    $sgst = (float)($_POST['sgst'] ?? 0);
    $cgst = (float)($_POST['cgst'] ?? 0);
    $igst = (float)($_POST['igst'] ?? 0);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $sell_price = (float)($_POST['sell_price'] ?? 0);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    if (empty($product_name)) {
        sendError("Product name is required");
    }

    // Auto-calculate GST breakup if missing
    if ($gst_percentage > 0 && $sgst == 0 && $cgst == 0 && $igst == 0) {
        $sgst = $gst_percentage / 2;
        $cgst = $gst_percentage / 2;
        $igst = 0;
    }

    // Round all values
    $sgst = round($sgst, 2);
    $cgst = round($cgst, 2);
    $igst = round($igst, 2);
    $gst_percentage = round($gst_percentage, 2);
    $purchase_price = round($purchase_price, 2);
    $sell_price = round($sell_price, 2);

    $stmt = $conn->prepare("UPDATE products SET 
        product_name = ?, 
        product_code = ?, 
        product_design_code = ?, 
        product_description = ?, 
        hsn_code = ?, 
        unit = ?,
        gst_percentage = ?,
        sgst = ?, 
        cgst = ?, 
        igst = ?, 
        purchase_price = ?, 
        sell_price = ?,
        quantity = ?,
        category_id = ? 
        WHERE id = ?");

    if (!$stmt) sendError("Prepare failed: " . $conn->error);

    $stmt->bind_param("ssssssddddddiii", 
        $product_name,
        $product_code,
        $product_design_code,
        $product_description,
        $hsn_code,
        $unit,
        $gst_percentage,
        $sgst,
        $cgst,
        $igst,
        $purchase_price,
        $sell_price,
        $quantity,
        $category_id,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Product updated successfully',
            'quantity' => $quantity
        ]);
    } else {
        sendError("Update failed: " . $stmt->error);
    }
    $stmt->close();
    exit;
}

// ==================================================================
// 3. DELETE PRODUCT
// ==================================================================
if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];

    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? LIMIT 1");
    if (!$stmt) sendError("Delete prepare failed");

    $stmt->bind_param("i", $id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Product not found or already deleted']);
    }
    $stmt->close();
    exit;
}

sendError("Invalid action");
?>