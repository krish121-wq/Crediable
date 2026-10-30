<?php
header('Content-Type: application/json');
require_once 'config.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

try {
    $query = "SELECT id, product_name, product_code, product_design_code, product_description, 
                     hsn_code, unit, gst_percentage, purchase_price, sell_price 
              FROM products 
              WHERE product_name LIKE ? OR product_code LIKE ?";
    
    $searchTerm = "%$term%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'product_name' => $row['product_name'],
            'product_code' => $row['product_code'] ?? '',
            'product_design_code' => $row['product_design_code'] ?? '',
            'product_description' => $row['product_description'] ?? '',
            'hsn_code' => $row['hsn_code'] ?? '',
            'unit' => $row['unit'] ?? '',
            'gst_percentage' => floatval($row['gst_percentage'] ?? 0),
            'purchase_price' => floatval($row['purchase_price'] ?? 0),
            'sell_price' => floatval($row['sell_price'] ?? 0)
        ];
    }
    
    $stmt->close();
    echo json_encode($products);
} catch (Exception $e) {
    error_log("Error in fetch-products-cash-bill.php: " . $e->getMessage());
    echo json_encode([]);
}
$conn->close();
?>

