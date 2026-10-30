<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=products_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Product Name', 'SKU', 'Category ID', 'Sub Category ID', 'Brand ID', 'Unit ID', 'Item Barcode', 'Description', 'Quantity', 'Price Per Piece', 'Tax Rate', 'Total Price Without GST', 'Total Price With GST', 'Quantity Alert', 'Expiring Date'));

$query = "SELECT id, product_name, sku, category_id, sub_category_id, brand_id, unit_id, item_barcode, description, quantity, price_per_piece, tax_rate, total_price_without_gst, total_price_with_gst, quantity_alert, expiring_date 
          FROM products 
          WHERE created_by = $created_by_id";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}
fclose($output);
exit();
?>
