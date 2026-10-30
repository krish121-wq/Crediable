<?php
session_start();
require_once 'config.php';

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Invalid request");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get form inputs
$product_name = trim($_POST['product_name'] ?? '');
$product_code = trim($_POST['product_code'] ?? '');
$product_design_code = trim($_POST['product_design_code'] ?? '');
$product_description = $_POST['product_description'] ?? null;
$hsn_code = $_POST['hsn_code'] ?? null;
$unit = $_POST['unit'] ?? '';
$gst_percentage = floatval($_POST['gst_percentage'] ?? 0);
$category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;


// Get exclusive prices
$purchase_price_excl = floatval($_POST['purchase_price_excl'] ?? 0);
$sell_price_excl = floatval($_POST['sell_price_excl'] ?? 0);

// Calculate inclusive prices
$purchase_price = $purchase_price_excl + ($purchase_price_excl * $gst_percentage / 100);
$sell_price = $sell_price_excl + ($sell_price_excl * $gst_percentage / 100);

// Get GST breakup values
$sgst = floatval($_POST['sgst'] ?? 0);
$cgst = floatval($_POST['cgst'] ?? 0);
$igst = floatval($_POST['igst'] ?? 0);

// Auto-calculate if missing
if ($gst_percentage > 0 && $sgst == 0 && $cgst == 0 && $igst == 0) {
    $sgst = $gst_percentage / 2;
    $cgst = $gst_percentage / 2;
    $igst = 0;
}

// Round to 2 decimal places
$sgst = round($sgst, 2);
$cgst = round($cgst, 2);
$igst = round($igst, 2);
$gst_percentage = round($gst_percentage, 2);
$purchase_price = round($purchase_price, 2);
$sell_price = round($sell_price, 2);

try {
    $stmt = $conn->prepare("INSERT INTO products (product_name, product_code, product_design_code, product_description, hsn_code, unit, gst_percentage, sgst, cgst, igst, purchase_price, sell_price, quantity, category_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())");
    
    $stmt->bind_param("ssssssddddddi", 
        $product_name, $product_code, $product_design_code, 
        $product_description, $hsn_code, $unit,
        $gst_percentage, $sgst, $cgst, $igst,
        $purchase_price, $sell_price, $category_id
    );
    
    if ($stmt->execute()) {
        $_SESSION['product_saved'] = true;
        header("Location: master_list.php");
        exit();
    } else {
        throw new Exception($stmt->error);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$stmt->close();
$conn->close();
?>