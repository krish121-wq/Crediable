<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_product'])) {
    
    $id = intval($_POST['id']);
    $product_name = trim($_POST['product_name']);
    $product_code = trim($_POST['product_code']);
    $product_design_code = trim($_POST['product_design_code']);
    $product_description = $_POST['product_description'] ?? null;
    $hsn_code = $_POST['hsn_code'] ?? null;
    $unit = $_POST['unit'];
    $gst_percentage = floatval($_POST['gst_percentage'] ?? 0);
    $sgst = floatval($_POST['sgst'] ?? 0);
    $cgst = floatval($_POST['cgst'] ?? 0);
    $igst = floatval($_POST['igst'] ?? 0);
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sell_price = floatval($_POST['sell_price'] ?? 0);
    
    // Auto-calculate GST breakup if missing
    if ($gst_percentage > 0) {
        if ($igst > 0) {
            // Inter-state: only IGST
            $sgst = 0;
            $cgst = 0;
        } else {
            // Intra-state: SGST = CGST = GST/2
            $half = $gst_percentage / 2;
            $sgst = round($half, 2);
            $cgst = round($half, 2);
        }
    }
    
    // First, get current product data to preserve stock
    $stock_stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
    $stock_stmt->bind_param("i", $id);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $current_stock = $stock_result->fetch_assoc();
    $stock_stmt->close();
    
    $quantity = $current_stock['quantity'] ?? 0;
    
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
        quantity = ?
        WHERE id = ?");
    
    $stmt->bind_param("ssssssddddddi", 
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
        $id
    );
    
    if ($stmt->execute()) {
        $_SESSION['product_saved'] = true;
        header("Location: fetch_master_list.php?success=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}
?>