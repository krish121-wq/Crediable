<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sample_products.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Product Name', 'SKU (Leave empty for auto)', 'Category ID', 'Unit ID', 'Price Per Piece', 'Quantity', 'Tax Rate (0,5,8,10,12,18)', 'Quantity Alert', 'Expiring Date'));
    fputcsv($output, array('Test Product', '', '1', '1', '100.00', '50', '18', '5', date('Y-m-d', strtotime('+1 year'))));
    fclose($output);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (empty($file)) {
        header("Location: product-list.php?error=" . urlencode("No file uploaded"));
        exit();
    }

    $handle = fopen($file, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Skip header
        $successCount = 0;
        $errorCount = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $product_name = $conn->real_escape_string($data[0] ?? '');
            if (empty($product_name)) continue;

            $sku = trim($data[1] ?? '');
            if (empty($sku)) {
                $sku_result = $conn->query("SELECT MAX(sku) as max_sku FROM products WHERE sku LIKE 'PT%' AND created_by = $created_by_id");
                $max_sku = $sku_result->fetch_assoc()['max_sku'];
                $sku = 'PT' . str_pad($max_sku ? ((int)substr($max_sku, 2) + 1) : 1, 4, '0', STR_PAD_LEFT);
            }

            $category_id = (int)($data[2] ?? 0);
            $unit_id = (int)($data[3] ?? 0);
            $price_per_piece = (float)($data[4] ?? 0);
            $quantity = (int)($data[5] ?? 0);
            $tax_rate = (int)($data[6] ?? 0);
            $quantity_alert = (int)($data[7] ?? 0);
            $expiring_date = $conn->real_escape_string($data[8] ?? date('Y-m-d', strtotime('+1 year')));

            $total_price_without_gst = $quantity * $price_per_piece;
            $total_price_with_gst = $total_price_without_gst * (1 + $tax_rate / 100);

            // Generate barcode
            do {
                $barcode = 'BAR' . rand(100000, 999999);
                $bc_check = $conn->query("SELECT COUNT(*) as count FROM products WHERE item_barcode = '$barcode' AND created_by = $created_by_id");
            } while ($bc_check->fetch_assoc()['count'] > 0);

            $query = "INSERT INTO products (product_name, sku, category_id, unit_id, item_barcode, quantity, price_per_piece, tax_rate, total_price_without_gst, total_price_with_gst, quantity_alert, expiring_date, created_by, created_at) 
                      VALUES ('$product_name', '$sku', $category_id, $unit_id, '$barcode', $quantity, $price_per_piece, $tax_rate, $total_price_without_gst, $total_price_with_gst, $quantity_alert, '$expiring_date', $created_by_id, NOW())";
            
            if ($conn->query($query)) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        fclose($handle);
        header("Location: product-list.php?success=" . urlencode("Imported $successCount products. Errors: $errorCount"));
        exit();
    }
}
?>
