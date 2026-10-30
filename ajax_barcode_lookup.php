<?php
require_once 'config.php';
$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Query is empty']);
    exit();
}

try {
    // Search in active products table by barcode, product_name, or item_code
    $search_like = "%" . $query . "%";
    $result = null;

    $stmt = $conn->prepare("
        SELECT id, product_name, quantity as current_stock, sell_price as sales_price, COALESCE(tax_rate, 18) as gst_rate, hsn_code
        FROM products
        WHERE product_name LIKE ? OR id = ?
        LIMIT 1
    ");
    $stmt->bind_param("si", $search_like, $query);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (isset($_GET['html']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false)) {
        // Render Clean HTML UI for Browser Testing
        include('layout.php');
        echo '<div class="gb-page-wrap" style="margin-top:70px;margin-left:260px;padding:30px;">';
        echo '<div class="card shadow-sm border-0 rounded-4 p-4">';
        echo '<h4 class="fw-bold mb-3 text-primary"><i class="fas fa-barcode me-2"></i>Barcode Search Result</h4>';
        if ($result) {
            echo '<table class="table table-bordered align-middle">';
            echo '<tr class="table-light"><th style="width:200px;">Product Name</th><td class="fw-bold">' . htmlspecialchars($result['product_name']) . '</td></tr>';
            echo '<tr><th>Current Stock</th><td><span class="badge bg-info">' . $result['current_stock'] . ' Units</span></td></tr>';
            echo '<tr><th>Sales Price</th><td class="fw-bold text-success">₹' . number_format($result['sales_price'], 2) . '</td></tr>';
            echo '<tr><th>GST Rate</th><td>' . $result['gst_rate'] . '%</td></tr>';
            echo '<tr><th>HSN Code</th><td>' . htmlspecialchars($result['hsn_code'] ?: 'N/A') . '</td></tr>';
            echo '</table>';
        } else {
            echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No product found matching your search.</div>';
        }
        echo '</div></div>';
        exit();
    } else {
        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
