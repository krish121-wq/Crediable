<?php
header('Content-Type: application/json');
require_once 'config.php';

$term = isset($_GET['term']) ? $_GET['term'] : '';

try {
    $stmt = $conn->prepare("SELECT id, product_name, hsn_code, purchase_price FROM products WHERE product_name LIKE ?");
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
            'purchase_price' => $row['purchase_price']
        ];
    }
    
    $stmt->close();
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode([]);
}
$conn->close();
?>