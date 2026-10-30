<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include('layout.php');
$page_title = "Low Stock Products";

// Handle form submission for editing quantity and quantity_alert
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $user_id = (int)$_SESSION['user_id'];
    try {
        $id = (int)($_POST['id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $quantity_alert = (int)($_POST['quantity_alert'] ?? 0);

        if ($id && $quantity >= 0 && $quantity_alert >= 0) {
            // Fetch current record to get price_per_piece for total calculations
            $result = $conn->query("SELECT price_per_piece FROM products WHERE id = $id AND created_by = $user_id");
            if ($result->num_rows === 0) throw new Exception("Record not found for id: $id");
            $row = $result->fetch_assoc();
            $price_per_piece = (float)$row['price_per_piece'];

            // Recalculate totals
            $total_price_without_gst = $quantity * $price_per_piece;
            $result = $conn->query("SELECT tax_rate FROM products WHERE id = $id");
            $row = $result->fetch_assoc();
            $tax_rate = (float)$row['tax_rate'];
            $total_price_with_gst = $total_price_without_gst * (1 + $tax_rate / 100);

            // Update only quantity, quantity_alert, and totals
            $query = "UPDATE products SET 
                      quantity = $quantity,
                      quantity_alert = $quantity_alert,
                      total_price_without_gst = $total_price_without_gst,
                      total_price_with_gst = $total_price_with_gst,
                      updated_by = $user_id,
                      updated_at = NOW()
                      WHERE id = $id AND created_by = $user_id";
            if (!$conn->query($query)) {
                throw new Exception("Update failed: " . $conn->error);
            }
            header("Location: low-stock.php");
            exit();
        } else {
            throw new Exception("Invalid input data.");
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "<div class='alert alert-danger'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Fetch low stock products
$user_id = (int)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'admin';

try {
    $where = ($user_type === 'staff') ? "WHERE p.created_by = $user_id AND p.quantity <= COALESCE(p.quantity_alert, 5)" : "WHERE p.quantity <= COALESCE(p.quantity_alert, 5)";
    $query = "SELECT p.id, p.product_name, p.image_path, p.quantity, COALESCE(p.quantity_alert, 5) as quantity_alert, COALESCE(c.category_name, 'General') as category_name, COALESCE(b.brand_name, 'General') as brand_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN brands b ON p.brand_id = b.id 
              $where
              ORDER BY p.quantity ASC";
    $result = $conn->query($query);
    if (!$result) throw new Exception("Query failed: " . $conn->error);
} catch (Exception $e) {
    error_log("Query Error: " . $e->getMessage());
    $result = null;
}
?>

<div class="gb-page-wrap">
    <style>
        .gb-page-wrap { margin-top: 62px; margin-left: 262px; padding: 24px; }
        @media (max-width: 992px) { .gb-page-wrap { margin-left: 0; padding: 16px; } }

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
            color: #64748b;
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
            color: #334155;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .table-top-head a:hover {
            color: #0284c7;
        }

        .table-top-head .pdf-icon {
            color: #dc2626;
        }

        .table-top-head .excel-icon {
            color: #16a34a;
        }

        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th, .table td {
            vertical-align: middle;
            padding: 12px 14px;
        }

        .action-table-data a {
            color: #0284c7;
            margin-right: 5px;
        }
    </style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>Low Stock Products</h4>
            <h6>Manage products with low stock</h6>
        </div>
        <ul class="table-top-head">
            <li>
                <a href="#" class="pdf-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pdf" data-bs-original-title="Pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
            </li>
            <li>
                <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </li>
        </ul>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="lowStockTable" aria-describedby="lowStockTable_info">
                    <thead class="thead-light">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Quantity</th>
                            <th>Quantity Alert</th>
                            <th class="no-sort">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($row['image_path']) { ?>
                                            <img src="<?php echo $row['image_path']; ?>" alt="Product Image" style="max-width: 50px; max-height: 50px; margin-right: 10px;">
                                        <?php } else { ?>
                                            <span>No Image</span>
                                        <?php } ?>
                                        <?php echo htmlspecialchars($row['product_name']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['category_name'] ?: 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($row['brand_name'] ?: 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($row['quantity_alert']); ?></td>
                                    <td class="action-table-data">
                                        <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#editModal" onclick='editProduct(<?php echo json_encode($row); ?>)'>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='6'>No low stock products found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="editModalLabel">Edit Low Stock Product</h4>
                <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form action="low-stock.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="id" id="editProductId">
                    <div class="mb-3">
                        <label class="form-label">Quantity<span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" id="editQuantity" name="quantity" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity Alert<span class="text-danger ms-1">*</span></label>
                        <input type="number" class="form-control" id="editQuantityAlert" name="quantity_alert" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_product" class="btn btn-custom">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProduct(product) {
    $('#editProductId').val(product.id || '');
    $('#editQuantity').val(product.quantity || 0);
    $('#editQuantityAlert').val(product.quantity_alert || 0);
}
</div>

<script>
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({
        html: '#lowStockTable',
        columns: [
            { header: 'Product', dataKey: 'product' },
            { header: 'Category', dataKey: 'category' },
            { header: 'Brand', dataKey: 'brand' },
            { header: 'Quantity', dataKey: 'quantity' },
            { header: 'Quantity Alert', dataKey: 'quantity_alert' }
        ],
        didParseCell: function(data) {
            if (data.column.dataKey === 'product' && data.cell.raw) {
                const text = data.cell.raw.textContent || data.cell.raw.innerText;
                data.cell.text = [text];
            }
        }
    });
    doc.save('low_stock_products.pdf');
}

function exportToExcel() {
    const table = document.getElementById('lowStockTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "Product,Category,Brand,Quantity,Quantity Alert\n";
    
    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td');
        const rowData = [
            cols[0].innerText.replace(/,/g, ''),
            cols[1].innerText.replace(/,/g, ''),
            cols[2].innerText.replace(/,/g, ''),
            cols[3].innerText.replace(/,/g, ''),
            cols[4].innerText.replace(/,/g, '')
        ];
        csvContent += rowData.join(',') + '\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'low_stock_products.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    $('#editModal').on('hidden.bs.modal', function() {
        $('#editModal form')[0].reset();
        $('#editProductId').val('');
    });
});
</script>

<?php
ob_end_flush(); // Flush the output buffer
?>