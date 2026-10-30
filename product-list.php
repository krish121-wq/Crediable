<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Determine user type and created_by_id
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Generate unique SKU and Barcode
function generateSKU($conn, $created_by_id) {
    $result = $conn->query("SELECT MAX(sku) as max_sku FROM products WHERE sku LIKE 'PT%' AND created_by = $created_by_id");
    $row = $result->fetch_assoc();
    $max_sku = $row['max_sku'];
    $number = $max_sku ? (int)substr($max_sku, 2) + 1 : 1;
    return 'PT' . str_pad($number, 4, '0', STR_PAD_LEFT);
}

function generateBarcode($conn, $created_by_id) {
    do {
        $barcode = 'BAR' . rand(100000, 999999);
        $result = $conn->query("SELECT COUNT(*) as count FROM products WHERE item_barcode = '$barcode' AND created_by = $created_by_id");
        $row = $result->fetch_assoc();
    } while ($row['count'] > 0);
    return $barcode;
}

// Handle AJAX requests for SKU and Barcode
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: text/plain');
    ob_clean();
    if ($_POST['action'] == 'generate_sku') {
        echo generateSKU($conn, $created_by_id);
        exit();
    }
    if ($_POST['action'] == 'generate_barcode') {
        echo generateBarcode($conn, $created_by_id);
        exit();
    }
}

$page_title = "Products List";
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_product']) || isset($_POST['edit_product']) || isset($_POST['delete_product']))) {
    try {
        if (isset($_POST['add_product']) || isset($_POST['edit_product'])) {
            $product_name = $conn->real_escape_string(trim($_POST['product_name'] ?? ''));
            $sku = $conn->real_escape_string(trim($_POST['sku'] ?? (isset($_POST['add_product']) ? generateSKU($conn, $created_by_id) : '')));
            $category_id = (int)($_POST['category_id'] ?? 0);
            $sub_category_id = (int)($_POST['sub_category_id'] ?? 0) ?: null;
            $brand_id = (int)($_POST['brand_id'] ?? 0) ?: null;
            $unit_id = (int)($_POST['unit_id'] ?? 0);
            $item_barcode = $conn->real_escape_string(trim($_POST['item_barcode'] ?? (isset($_POST['add_product']) ? generateBarcode($conn, $created_by_id) : '')));
            $description = $conn->real_escape_string(trim($_POST['description'] ?? ''));
            $quantity = (int)($_POST['quantity'] ?? 0);
            $price_per_piece = (float)($_POST['price_per_piece'] ?? 0.00);
            $tax_rate = (int)($_POST['tax_rate'] ?? 0); // Changed to int
            $total_price_without_gst = $quantity * $price_per_piece;
            $total_price_with_gst = $total_price_without_gst * (1 + $tax_rate / 100);
            $quantity_alert = (int)($_POST['quantity_alert'] ?? 0);
            $expiring_date = $conn->real_escape_string(trim($_POST['expiring_date'] ?? ''));

            // Validate SKU and Barcode uniqueness
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $sku_check = $conn->query("SELECT COUNT(*) as count FROM products WHERE sku = '$sku' AND id != $id AND created_by = $created_by_id");
            if ($sku_check->fetch_assoc()['count'] > 0) {
                throw new Exception("SKU already exists.");
            }
            $barcode_check = $conn->query("SELECT COUNT(*) as count FROM products WHERE item_barcode = '$item_barcode' AND id != $id AND created_by = $created_by_id");
            if ($barcode_check->fetch_assoc()['count'] > 0) {
                throw new Exception("Barcode already exists.");
            }

            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        throw new Exception("Failed to create uploads directory.");
                    }
                }
                $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
                $target_file = $target_dir . $image_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png'];
                $max_size = 2 * 1024 * 1024;

                if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= $max_size) {
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        throw new Exception("Image upload failed.");
                    }
                    $image_path = $target_file;
                } else {
                    throw new Exception("Invalid image type or size.");
                }
            }

            if ($product_name && $sku && $category_id && $unit_id && $quantity > 0 && $price_per_piece > 0 && $quantity_alert >= 0 && $expiring_date) {
                if (isset($_POST['add_product'])) {
                    $image_path_sql = $image_path ? "'$image_path'" : 'NULL';
                    $sub_category_id_sql = $sub_category_id ? $sub_category_id : 'NULL';
                    $brand_id_sql = $brand_id ? $brand_id : 'NULL';
                    $query = "INSERT INTO products (product_name, image_path, sku, category_id, sub_category_id, brand_id, unit_id, item_barcode, description, quantity, price_per_piece, tax_rate, total_price_without_gst, total_price_with_gst, quantity_alert, expiring_date, created_by, created_at) 
                              VALUES ('$product_name', $image_path_sql, '$sku', $category_id, $sub_category_id_sql, $brand_id_sql, $unit_id, '$item_barcode', '$description', $quantity, $price_per_piece, $tax_rate, $total_price_without_gst, $total_price_with_gst, $quantity_alert, '$expiring_date', $created_by_id, NOW())";
                    if (!$conn->query($query)) {
                        throw new Exception("Insert failed: " . $conn->error);
                    }
                } elseif (isset($_POST['edit_product'])) {
                    if (!$id) throw new Exception("Missing id for edit");

                    // Fetch current record
                    $result = $conn->query("SELECT product_name, image_path FROM products WHERE id = $id AND created_by = $created_by_id");
                    if ($result->num_rows === 0) throw new Exception("Record not found for id: $id");
                    $current = $result->fetch_assoc();

                    $image_path = $image_path ?? $current['image_path'];
                    if ($image_path != $current['image_path'] && $current['image_path'] && file_exists($current['image_path'])) {
                        unlink($current['image_path']);
                    }

                    $image_path_sql = $image_path ? "'$image_path'" : 'NULL';
                    $sub_category_id_sql = $sub_category_id ? $sub_category_id : 'NULL';
                    $brand_id_sql = $brand_id ? $brand_id : 'NULL';
                    $query = "UPDATE products SET 
                              product_name = '$product_name',
                              image_path = $image_path_sql,
                              sku = '$sku',
                              category_id = $category_id,
                              sub_category_id = $sub_category_id_sql,
                              brand_id = $brand_id_sql,
                              unit_id = $unit_id,
                              item_barcode = '$item_barcode',
                              description = '$description',
                              quantity = $quantity,
                              price_per_piece = $price_per_piece,
                              tax_rate = $tax_rate,
                              total_price_without_gst = $total_price_without_gst,
                              total_price_with_gst = $total_price_with_gst,
                              quantity_alert = $quantity_alert,
                              expiring_date = '$expiring_date',
                              updated_by = $created_by_id,
                              updated_at = NOW()
                              WHERE id = $id AND created_by = $created_by_id";
                    if (!$conn->query($query)) {
                        throw new Exception("Update failed: " . $conn->error);
                    }
                }
            } else {
                throw new Exception("Please fill all required fields.");
            }
        } elseif (isset($_POST['delete_product']) && $user_type === 'admin') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                // Check for associated sales
                $sales_check = $conn->query("SELECT COUNT(*) as count FROM sales WHERE product_id = $id AND created_by = $created_by_id");
                if ($sales_check->fetch_assoc()['count'] > 0) {
                    throw new Exception("Cannot delete product because it is referenced in sales records. Please delete or reassign related sales first.");
                }

                $result = $conn->query("SELECT image_path FROM products WHERE id = $id AND created_by = $created_by_id");
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['image_path'] && file_exists($row['image_path'])) {
                        unlink($row['image_path']);
                    }
                }
                if (!$conn->query("DELETE FROM products WHERE id = $id AND created_by = $created_by_id")) {
                    throw new Exception("Delete failed: " . $conn->error);
                }
            } else {
                throw new Exception("Missing id for delete.");
            }
        } else {
            throw new Exception("Unauthorized action: Staff cannot delete products.");
        }
        header("Location: product-list.php");
        exit();
    } catch (Exception $e) {
        $error_message = htmlspecialchars($e->getMessage());
        error_log("Error: " . $e->getMessage());
    }
}

// Fetch dropdown options
try {
    $categories = [];
    $result = $conn->query("SELECT id, category_name FROM categories WHERE created_by = $created_by_id AND status = 1");
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }

    $sub_categories = [];
    $result = $conn->query("SELECT id, sub_category_name FROM sub_categories WHERE created_by = $created_by_id AND status = 1");
    while ($row = $result->fetch_assoc()) {
        $sub_categories[] = $row;
    }

    $brands = [];
    $result = $conn->query("SELECT id, brand_name FROM brands WHERE created_by = $created_by_id AND status = 1");
    while ($row = $result->fetch_assoc()) {
        $brands[] = $row;
    }

    $units = [];
    $result = $conn->query("SELECT id, unit_name FROM units WHERE created_by = $created_by_id AND status = 1");
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
} catch (Exception $e) {
    $error_message = "Dropdown Fetch Error: " . htmlspecialchars($e->getMessage());
    error_log("Dropdown Fetch Error: " . $e->getMessage());
}

// Pagination and Filters
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;
$category_filter = isset($_GET['category_filter']) ? (int)$_GET['category_filter'] : 0;
$brand_filter = isset($_GET['brand_filter']) ? (int)$_GET['brand_filter'] : 0;

try {
    $base_query = "SELECT p.*, c.category_name, sc.sub_category_name, b.brand_name, u.unit_name 
                   FROM products p 
                   LEFT JOIN categories c ON p.category_id = c.id 
                   LEFT JOIN sub_categories sc ON p.sub_category_id = sc.id 
                   LEFT JOIN brands b ON p.brand_id = b.id 
                   LEFT JOIN units u ON p.unit_id = u.id 
                   WHERE p.created_by = $created_by_id";
    if ($category_filter) {
        $base_query .= " AND p.category_id = $category_filter";
    }
    if ($brand_filter) {
        $base_query .= " AND p.brand_id = $brand_filter";
    }

    $result = $conn->query($base_query);
    $total_records = $result->num_rows;

    $query = $base_query . " LIMIT $start_from, $records_per_page";
    $result = $conn->query($query);
    if (!$result) throw new Exception("Query failed: " . $conn->error);

    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    $error_message = "Pagination Error: " . htmlspecialchars($e->getMessage());
    error_log("Pagination Error: " . $e->getMessage());
    $result = null;
}
?>

<style>
    :root {
        --primary: #007bff;
        --secondary: #6c757d;
        --background: #f5f7fa;
        --card-bg: #ffffff;
        --grey-light: #e9ecef;
        --text-primary: #333;
        --text-secondary: #6c757d;
        --orange: #fe9f43;
        --red: #ff3300;
        --green: #00b328;
    }

    .content {
        margin-top: 80px;
        margin-left: 280px;
        padding: 20px 30px;
        min-height: calc(100vh - 112px);
        transition: margin-left 0.3s ease;
    }

    .content-full {
        margin-left: 0;
    }

    .page-header {
        margin-right: auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .page-title h4 {
        font-weight: 700;
        margin: 0;
    }

    .page-title h6 {
        font-weight: 400;
        color: var(--text-secondary);
        margin: 0;
    }

    .table-top-head {
        display: flex;
        gap: 10px;
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .table-top-head a {
        color: var(--text-primary);
        text-decoration: none;
        display: flex;
        align-items: center;
    }

    .table-top-head a:hover {
        color: var(--primary);
    }

    .table-top-head .pdf-icon {
        color: var(--red);
    }

    .table-top-head .excel-icon {
        color: var(--green);
    }

    .page-btn .btn {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .btn-custom {
        background-color: var(--orange) !important;
        border-color: var(--orange) !important;
    }

    .btn-custom:hover {
        background-color: #e68a00 !important;
        border-color: #e68a00 !important;
    }

    .search-set, .filter-set {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-searchset {
        position: absolute;
        left: 25px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    .search-input {
        position: relative;
    }

    .search-input input {
        padding-left: 35px;
        border-radius: 5px;
        height: 38px;
        width: 200px;
    }

    .filter-set select {
        height: 38px;
        border-radius: 5px;
        width: 150px;
    }

    .table-responsive table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        vertical-align: middle;
        padding: 10px;
        text-align: left;
    }

    .table th {
        background: var(--grey-light);
        border-bottom: 2px solid var(--secondary);
    }

    .action-table-data a {
        color: var(--text-primary);
        margin-right: 5px;
    }

    .action-table-data a:hover {
        color: var(--primary);
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .pagination a {
        padding: 5px 10px;
        text-decoration: none;
        color: #fff;
        background: var(--orange);
        border-radius: 5px;
    }

    .pagination a.active {
        background: #e68a00;
    }

    .pagination a:hover:not(.active) {
        background: #d97b00;
    }

    .add-image-upload {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .add-image {
        background: var(--grey-light);
        padding: 10px;
        border-radius: 5px;
        cursor: pointer;
    }

    .image-upload input[type="file"] {
        display: none;
    }

    .image-uploads {
        border: 2px dashed var(--secondary);
        padding: 20px;
        text-align: center;
        border-radius: 5px;
    }

    .image-preview {
        max-width: 100px;
        max-height: 100px;
        margin-top: 10px;
        display: none;
    }

    .view-modal img {
        max-width: 150px;
        max-height: 150px;
        margin-bottom: 15px;
    }

    .input-group .btn {
        height: 38px;
    }

    .alert-danger {
        background-color: var(--red);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 15px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .table-top-head {
            margin-top: 10px;
        }
        .card-header {
            flex-direction: column;
            align-items: stretch;
        }
        .card-header .search-set, .card-header .filter-set {
            width: 100%;
            margin-bottom: 10px;
        }
        .filter-set select {
            width: 100%;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>Products</h4>
            <h6>Manage your products</h6>
        </div>
        <ul class="table-top-head">
            <li>
                <a href="#" class="pdf-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pdf" data-bs-original-title="Pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
            </li>
            <li>
                <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </li>
            <li>
                <a href="export_products_csv.php" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Export All (CSV)"><i class="fas fa-file-csv" style="color:var(--orange)"></i></a>
            </li>
            <li>
                <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Import CSV" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-upload" style="color:var(--brand)"></i></a>
            </li>
        </ul>
        <div class="page-btn">
            <a href="#" class="btn btn-custom btn-sm" data-bs-toggle="modal" data-bs-target="#productModal"><i class="fas fa-circle-plus me-1"></i>Add Product</a>
        </div>
    </div>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success" style="background-color: var(--green); color: white; padding: 10px; margin-bottom: 20px; border-radius: 5px;"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <span class="btn-searchset"><i class="fas fa-search fs-14"></i></span>
                    <input type="search" class="form-control form-control-sm" placeholder="Search by Product Name" aria-controls="productTable" onkeyup="searchTable()">
                </div>
            </div>
            <div class="filter-set">
                <select class="form-control form-control-sm" id="category_filter" onchange="applyFilters()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category) { ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['category_name']); ?></option>
                    <?php } ?>
                </select>
                <select class="form-control form-control-sm" id="brand_filter" onchange="applyFilters()">
                    <option value="0">All Brands</option>
                    <?php foreach ($brands as $brand) { ?>
                        <option value="<?php echo $brand['id']; ?>" <?php echo $brand_filter == $brand['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($brand['brand_name']); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="productTable" aria-describedby="productTable_info">
                    <thead class="thead-light">
                        <tr>
                            <th class="no-sort">S.No.</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Brand</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th class="no-sort">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td class="sorting_1"><?php echo $sno++; ?></td>
                                    <td>
                                        <?php if ($row['image_path']) { ?>
                                            <img src="<?php echo $row['image_path']; ?>" alt="Product Image" style="max-width: 50px; max-height: 50px; margin-right: 10px;">
                                        <?php } else { ?>
                                            <span>No Image</span>
                                        <?php } ?>
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['category_name'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($row['unit_name'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($row['brand_name'] ?? 'None'); ?></td>
                                    <td><?php echo number_format($row['price_per_piece'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                    <td class="action-table-data">
                                        <div class="edit-delete-action">
                                            <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#productModal" onclick='editProduct(<?php echo json_encode($row); ?>)'>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#viewModal" onclick='viewProduct(<?php echo json_encode($row); ?>)'>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-eye"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                            </a>
                                            <?php if ($user_type === 'admin'): ?>
                                                <a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo "<tr><td colspan='8'>No data available or an error occurred.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="pagination">
        <?php
        if ($result && $total_pages > 1) {
            if ($page > 1) {
                echo "<a href='?page=" . ($page - 1) . "&category_filter=$category_filter&brand_filter=$brand_filter' class='prev'>Previous</a>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page=" . $i . "&category_filter=$category_filter&brand_filter=$brand_filter' class='" . ($page == $i ? 'active' : '') . "'>" . $i . "</a>";
            }
            if ($page < $total_pages) {
                echo "<a href='?page=" . ($page + 1) . "&category_filter=$category_filter&brand_filter=$brand_filter' class='next'>Next</a>";
            }
        }
        ?>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="page-title">
                        <h4>Import Products</h4>
                    </div>
                    <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="import_products.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Upload CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        </div>
                        <div class="mb-3">
                            <a href="import_products.php?download_sample=1" class="text-primary"><i class="fas fa-download"></i> Download Sample CSV Format</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-custom">Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="page-title">
                        <h4 id="modalTitle">Add Product</h4>
                    </div>
                    <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="product-list.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="productId">
                        <input type="hidden" name="existing_image" id="existingImage">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="add-image-upload">
                                        <div class="add-image" onclick="document.getElementById('imageUpload').click()">
                                            <span class="fw-normal"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg> Add Image</span>
                                        </div>
                                        <div class="new-employee-field">
                                            <div class="mb-0">
                                                <div class="image-upload mb-2">
                                                    <input type="file" id="imageUpload" name="image" accept="image/jpeg,image/png" onchange="previewImage(event)">
                                                    <div class="image-uploads" id="imagePreviewContainer">
                                                        <h4 class="fs-13 fw-medium">Upload Image</h4>
                                                        <img id="imagePreview" class="image-preview">
                                                    </div>
                                                </div>
                                                <span>JPEG, PNG up to 2 MB</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name<span class="text-danger ms-1">*</span></label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SKU<span class="text-danger ms-1">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sku" name="sku" required>
                                        <button type="button" class="btn btn-custom" onclick="generateSKU()">Generate</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category<span class="text-danger ms-1">*</span></label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category) { ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Category</label>
                                    <select class="form-control" id="sub_category_id" name="sub_category_id">
                                        <option value="">None</option>
                                        <?php foreach ($sub_categories as $sub_category) { ?>
                                            <option value="<?php echo $sub_category['id']; ?>"><?php echo htmlspecialchars($sub_category['sub_category_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Brand</label>
                                    <select class="form-control" id="brand_id" name="brand_id">
                                        <option value="">None</option>
                                        <?php foreach ($brands as $brand) { ?>
                                            <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['brand_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Unit<span class="text-danger ms-1">*</span></label>
                                    <select class="form-control" id="unit_id" name="unit_id" required>
                                        <option value="">Select Unit</option>
                                        <?php foreach ($units as $unit) { ?>
                                            <option value="<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Item Barcode<span class="text-danger ms-1">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="item_barcode" name="item_barcode" required>
                                        <button type="button" class="btn btn-custom" onclick="generateBarcode()">Generate</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantity<span class="text-danger ms-1">*</span></label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price per Piece<span class="text-danger ms-1">*</span></label>
                                    <input type="number" class="form-control" id="price_per_piece" name="price_per_piece" min="0.01" step="0.01" required>
                                </div>
                            </div>
                           <div class="col-md-6">
    <div class="mb-3">
        <label class="form-label">Tax (GST)<span class="text-danger ms-1">*</span></label>
        <select class="form-control" id="tax_rate" name="tax_rate" required>
            <option value="">Select Tax Rate</option>
            <option value="0">None</option>
            <option value="5">5%</option>
            <option value="8">8%</option>
            <option value="10">10%</option>
            <option value="12">12%</option>
        </select>
    </div>
</div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total Price without GST</label>
                                    <input type="text" class="form-control" id="total_price_without_gst" name="total_price_without_gst" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Total Price with GST</label>
                                    <input type="text" class="form-control" id="total_price_with_gst" name="total_price_with_gst" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantity Alert<span class="text-danger ms-1">*</span></label>
                                    <input type="number" class="form-control" id="quantity_alert" name="quantity_alert" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Expiring Date<span class="text-danger ms-1">*</span></label>
                                    <input type="date" class="form-control" id="expiring_date" name="expiring_date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn btn-custom" id="addProductButton">Add Product</button>
                        <button type="submit" name="edit_product" class="btn btn-custom" style="display:none;" id="editProductButton">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="viewModalLabel">Product Details</h4>
                    <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body view-modal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <img id="viewImage" src="" alt="Product Image" style="display: none;">
                                <span id="viewNoImage" style="display: none;">No Image</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Product Name:</strong> <span id="viewProductName"></span>
                            </div>
                            <div class="mb-3">
                                <strong>SKU:</strong> <span id="viewSku"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Category:</strong> <span id="viewCategory"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Sub Category:</strong> <span id="viewSubCategory"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Brand:</strong> <span id="viewBrand"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Unit:</strong> <span id="viewUnit"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Item Barcode:</strong> <span id="viewBarcode"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Description:</strong> <span id="viewDescription"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Quantity:</strong> <span id="viewQuantity"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Price per Piece:</strong> <span id="viewPrice"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Tax (GST):</strong> <span id="viewTax"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Total Price without GST:</strong> <span id="viewTotalWithoutGST"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Total Price with GST:</strong> <span id="viewTotalWithGST"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Quantity Alert:</strong> <span id="viewQuantityAlert"></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Expiring Date:</strong> <span id="viewExpiringDate"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <?php if ($user_type === 'admin'): ?>
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="deleteModalLabel">Confirm Delete</h4>
                    <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="product-list.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Are you sure you want to delete this product?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_product" class="btn btn-custom">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsPDF/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function generateSKU() {
    $.ajax({
        url: 'product-list.php',
        type: 'POST',
        data: { action: 'generate_sku' },
        success: function(response) {
            $('#sku').val(response);
        },
        error: function(xhr, status, error) {
            alert('Error generating SKU: ' + error);
        }
    });
}

function generateBarcode() {
    $.ajax({
        url: 'product-list.php',
        type: 'POST',
        data: { action: 'generate_barcode' },
        success: function(response) {
            $('#item_barcode').val(response);
        },
        error: function(xhr, status, error) {
            alert('Error generating barcode: ' + error);
        }
    });
}

function previewImage(event) {
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.style.display = 'block';
            imagePreviewContainer.querySelector('h4').style.display = 'none';
        };
        reader.readAsDataURL(file);
    } else {
        imagePreview.style.display = 'none';
        imagePreviewContainer.querySelector('h4').style.display = 'block';
    }
}

function editProduct(product) {
    // Debugging: Log the product object to ensure tax_rate is present
    console.log('Product data:', product);

    $('#modalTitle').text('Edit Product');
    $('#addProductButton').hide();
    $('#editProductButton').show();
    $('#productId').val(product.id || '');
    $('#product_name').val(product.product_name || '');
    $('#sku').val(product.sku || '');
    $('#category_id').val(product.category_id || '');
    $('#sub_category_id').val(product.sub_category_id || '');
    $('#brand_id').val(product.brand_id || '');
    $('#unit_id').val(product.unit_id || '');
    $('#item_barcode').val(product.item_barcode || '');
    $('#description').val(product.description || '');
    $('#quantity').val(product.quantity || 0);
    $('#price_per_piece').val(product.price_per_piece ? parseFloat(product.price_per_piece).toFixed(2) : '0.00');
    
    // Handle tax_rate: Convert to string and ensure it matches select options
    const validTaxRates = ['0', '5', '8', '10', '12'];
    const taxRate = product.tax_rate != null ? String(parseInt(product.tax_rate)) : '0';
    if (validTaxRates.includes(taxRate)) {
        $('#tax_rate').val(taxRate);
    } else {
        $('#tax_rate').val('0'); // Fallback to 'None' if invalid
        console.warn('Invalid tax_rate value:', product.tax_rate);
    }
    
    $('#total_price_without_gst').val(product.total_price_without_gst ? parseFloat(product.total_price_without_gst).toFixed(2) : '0.00');
    $('#total_price_with_gst').val(product.total_price_with_gst ? parseFloat(product.total_price_with_gst).toFixed(2) : '0.00');
    $('#quantity_alert').val(product.quantity_alert || 0);
    $('#expiring_date').val(product.expiring_date || '');
    $('#existingImage').val(product.image_path || '');

    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    if (product.image_path) {
        imagePreview.src = product.image_path;
        imagePreview.style.display = 'block';
        imagePreviewContainer.querySelector('h4').style.display = 'none';
    } else {
        imagePreview.src = '';
        imagePreview.style.display = 'none';
        imagePreviewContainer.querySelector('h4').style.display = 'block';
    }

    // Debugging: Log the selected tax_rate value
    console.log('Set tax_rate to:', $('#tax_rate').val());
}

function viewProduct(product) {
    $('#viewProductName').text(product.product_name || 'N/A');
    $('#viewSku').text(product.sku || 'N/A');
    $('#viewCategory').text(product.category_name || 'None');
    $('#viewSubCategory').text(product.sub_category_name || 'None');
    $('#viewBrand').text(product.brand_name || 'None');
    $('#viewUnit').text(product.unit_name || 'None');
    $('#viewBarcode').text(product.item_barcode || 'N/A');
    $('#viewDescription').text(product.description || 'N/A');
    $('#viewQuantity').text(product.quantity || '0');
    $('#viewPrice').text(product.price_per_piece ? parseFloat(product.price_per_piece).toFixed(2) : '0.00');
    $('#viewTax').text(product.tax_rate ? product.tax_rate + '%' : 'None');
    $('#viewTotalWithoutGST').text(product.total_price_without_gst ? parseFloat(product.total_price_without_gst).toFixed(2) : '0.00');
    $('#viewTotalWithGST').text(product.total_price_with_gst ? parseFloat(product.total_price_with_gst).toFixed(2) : '0.00');
    $('#viewQuantityAlert').text(product.quantity_alert || '0');
    $('#viewExpiringDate').text(product.expiring_date || 'N/A');

    const viewImage = document.getElementById('viewImage');
    const viewNoImage = document.getElementById('viewNoImage');
    if (product.image_path) {
        viewImage.src = product.image_path;
        viewImage.style.display = 'block';
        viewNoImage.style.display = 'none';
    } else {
        viewImage.src = '';
        viewImage.style.display = 'none';
        viewNoImage.style.display = 'block';
    }
}

function calculateTotal() {
    const quantity = parseInt($('#quantity').val()) || 0;
    const price = parseFloat($('#price_per_piece').val()) || 0;
    const tax = parseFloat($('#tax_rate').val()) || 0;
    const totalWithoutGST = (quantity * price).toFixed(2);
    const totalWithGST = (totalWithoutGST * (1 + tax / 100)).toFixed(2);
    $('#total_price_without_gst').val(totalWithoutGST);
    $('#total_price_with_gst').val(totalWithGST);
}

function searchTable() {
    const input = document.querySelector('.search-input input');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('productTable');
    const tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        const td = tr[i].getElementsByTagName('td')[1];
        if (td) {
            const txtValue = td.textContent || td.innerText;
            tr[i].style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
        }
    }
}

function applyFilters() {
    const category = document.getElementById('category_filter').value;
    const brand = document.getElementById('brand_filter').value;
    const url = new URL(window.location.href);
    url.searchParams.set('category_filter', category);
    url.searchParams.set('brand_filter', brand);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({
        html: '#productTable',
        columns: [
            { header: 'S.No.', dataKey: 'sno' },
            { header: 'Product', dataKey: 'product' },
            { header: 'Category', dataKey: 'category' },
            { header: 'Unit', dataKey: 'unit' },
            { header: 'Brand', dataKey: 'brand' },
            { header: 'Price', dataKey: 'price' },
            { header: 'Quantity', dataKey: 'quantity' }
        ],
        didParseCell: function(data) {
            if (data.column.dataKey === 'product' && data.cell.raw) {
                const text = data.cell.raw.textContent || data.cell.raw.innerText;
                data.cell.text = [text];
            }
        }
    });
    doc.save('products.pdf');
}

function exportToExcel() {
    const table = document.getElementById('productTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "S.No.,Product,Category,Unit,Brand,Price,Quantity\n";
    
    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td');
        const rowData = [
            cols[0].innerText,
            cols[1].innerText.replace(/,/g, ''),
            cols[2].innerText,
            cols[3].innerText,
            cols[4].innerText,
            cols[5].innerText,
            cols[6].innerText
        ];
        csvContent += rowData.join(',') + '\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'products.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

$(document).ready(function() {
    $('#quantity, #price_per_piece, #tax_rate').on('input change', calculateTotal);
    
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    $('#productModal').on('hidden.bs.modal', function() {
        $('#modalTitle').text('Add Product');
        $('#addProductButton').show();
        $('#editProductButton').hide();
        $('#productModal form')[0].reset();
        $('#imagePreview').css('display', 'none');
        $('#imagePreviewContainer h4').css('display', 'block');
        $('#productId').val('');
        $('#existingImage').val('');
    });
});
</script>

<?php
ob_end_flush();
?>