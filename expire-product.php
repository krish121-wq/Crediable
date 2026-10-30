<?php
ob_start(); // Enable output buffering
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

// Include layout
include('layout.php');

$page_title = "Expiring Products";

// Handle filters
$user_id = (int)$_SESSION['user_id'];
$time_filter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'all';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'asc';

// Build query with filters
try {
    $query = "SELECT p.product_name, p.image_path, p.expiring_date, c.category_name, b.brand_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN brands b ON p.brand_id = b.id 
              WHERE p.created_by = $user_id";
    
    // Apply time filter
    $today = date('Y-m-d');
    if ($time_filter === '7days') {
        $query .= " AND p.expiring_date <= DATE_ADD('$today', INTERVAL 7 DAY) AND p.expiring_date >= '$today'";
    } elseif ($time_filter === '1month') {
        $query .= " AND p.expiring_date <= DATE_ADD('$today', INTERVAL 1 MONTH) AND p.expiring_date >= '$today'";
    } elseif ($time_filter === '6months') {
        $query .= " AND p.expiring_date <= DATE_ADD('$today', INTERVAL 6 MONTH) AND p.expiring_date >= '$today'";
    }

    // Apply sort order
    $query .= " ORDER BY p.expiring_date " . ($sort_order === 'desc' ? 'DESC' : 'ASC');

    $result = $conn->query($query);
    if (!$result) throw new Exception("Query failed: " . $conn->error);
} catch (Exception $e) {
    error_log("Query Error: " . $e->getMessage());
    echo "<div class='alert alert-danger'>Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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

    .filter-set {
        display: flex;
        align-items: center;
        gap: 10px;
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

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
            padding: 15px;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .filter-set {
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
            <h4>Expiring Products</h4>
            <h6>View products nearing expiration</h6>
        </div>
        <ul class="table-top-head">
            <li>
                <a href="#" class="pdf-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pdf" data-bs-original-title="Pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
            </li>
            <li>
                <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </li>
        </ul>
        <div class="filter-set">
            <select class="form-control form-control-sm" id="time_filter" onchange="applyFilters()">
                <option value="all" <?php echo $time_filter === 'all' ? 'selected' : ''; ?>>All Expiring</option>
                <option value="7days" <?php echo $time_filter === '7days' ? 'selected' : ''; ?>>Within 7 Days</option>
                <option value="1month" <?php echo $time_filter === '1month' ? 'selected' : ''; ?>>Within 1 Month</option>
                <option value="6months" <?php echo $time_filter === '6months' ? 'selected' : ''; ?>>Within 6 Months</option>
            </select>
            <select class="form-control form-control-sm" id="sort_order" onchange="applyFilters()">
                <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Ascending</option>
                <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Descending</option>
            </select>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="expireProductTable" aria-describedby="expireProductTable_info">
                    <thead class="thead-light">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th>Expiring Date</th>
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
                                    <td><?php echo htmlspecialchars($row['expiring_date'] ?: 'N/A'); ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='4'>No expiring products found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const timeFilter = document.getElementById('time_filter').value;
    const sortOrder = document.getElementById('sort_order').value;
    const url = new URL(window.location.href);
    url.searchParams.set('time_filter', timeFilter);
    url.searchParams.set('sort_order', sortOrder);
    window.location.href = url.toString();
}

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.autoTable({
        html: '#expireProductTable',
        columns: [
            { header: 'Product', dataKey: 'product' },
            { header: 'Category', dataKey: 'category' },
            { header: 'Brand', dataKey: 'brand' },
            { header: 'Expiring Date', dataKey: 'expiring_date' }
        ],
        didParseCell: function(data) {
            if (data.column.dataKey === 'product' && data.cell.raw) {
                const text = data.cell.raw.textContent || data.cell.raw.innerText;
                data.cell.text = [text];
            }
        }
    });
    doc.save('expiring_products.pdf');
}

function exportToExcel() {
    const table = document.getElementById('expireProductTable');
    const rows = table.querySelectorAll('tr');
    let csvContent = "Product,Category,Brand,Expiring Date\n";
    
    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].querySelectorAll('td');
        const rowData = [
            cols[0].innerText.replace(/,/g, ''),
            cols[1].innerText.replace(/,/g, ''),
            cols[2].innerText.replace(/,/g, ''),
            cols[3].innerText.replace(/,/g, '')
        ];
        csvContent += rowData.join(',') + '\n';
    }
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'expiring_products.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

<?php
ob_end_flush(); // Flush the output buffer
?>