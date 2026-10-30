<?php
header('Content-Type: application/json');
require_once 'config.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
error_reporting(E_ALL);

$term = isset($_GET['term']) ? $_GET['term'] : '';

try {
    $stmt = $conn->prepare("SELECT id, product_name, hsn_code, purchase_price, cgst, sgst, igst FROM products WHERE product_name LIKE ?");
    $term = "%$term%";
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'product_name' => $row['product_name'],
            'hsn_code' => $row['hsn_code'],
            'purchase_price' => floatval($row['purchase_price'] ?: 0),
            'cgst' => floatval($row['cgst'] ?: 0),
            'sgst' => floatval($row['sgst'] ?: 0),
            'igst' => floatval($row['igst'] ?: 0)
        ];
    }
    
    $stmt->close();
    error_log("fetch-products.php response: " . json_encode($products));
    echo json_encode($products);
} catch (Exception $e) {
    error_log("Error in fetch-products.php: " . $e->getMessage());
    echo json_encode([]);
}
$conn->close();
?>