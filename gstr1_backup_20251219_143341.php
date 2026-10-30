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

$page_title = "GSTR-1 Reports";
$success_message = '';
$error_message = '';
$debug_messages = [];
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];


$filter_type = $_POST['filter_type'] ?? 'monthly';
$selected_month = $_POST['month'] ?? date('Y-m');
$selected_year = $_POST['year'] ?? date('Y');
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$timezone = new DateTimeZone('Asia/Kolkata');


$sanitized_sales = [];
$total_tax = 0;
$total_price = 0;

// Build query based on filter
try {
    $query = "
        SELECT s.id, s.customer_name, s.customer_phone, s.customer_address, s.quantity, s.total_price, s.created_at,
               p.product_name, p.sell_price as price_per_piece, p.gst_percentage, p.id as product_id,
               c.company_gst as customer_gst
        FROM sales s
        JOIN products p ON s.product_id = p.id
        LEFT JOIN company_details c ON 1=1
        WHERE s.created_by = ?
    ";
    $params = [$created_by_id];
    $param_types = "i";

    if ($filter_type === 'monthly') {
        $query .= " AND DATE_FORMAT(s.created_at, '%Y-%m') = ?";
        $params[] = $selected_month;
        $param_types .= "s";
    } elseif ($filter_type === 'yearly') {
        $query .= " AND YEAR(s.created_at) = ?";
        $params[] = $selected_year;
        $param_types .= "i";
    } elseif ($filter_type === 'custom') {
        if (empty($start_date) || empty($end_date)) {
            throw new Exception("Start date and end date are required for custom filter.");
        }
        $start = new DateTime($start_date, $timezone);
        $end = new DateTime($end_date, $timezone);
        if ($start > $end) {
            throw new Exception("Start date cannot be after end date.");
        }
        $query .= " AND s.created_at BETWEEN ? AND ?";
        $params[] = $start->format('Y-m-d 00:00:00');
        $params[] = $end->format('Y-m-d 23:59:59');
        $param_types .= "ss";
    }

    $query .= " ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $sales = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    // Group sales by invoice (same customer and created_at)
    $grouped_sales = [];
    foreach ($sales as $sale) {
        $key = $sale['customer_name'] . '|' . $sale['customer_phone'] . '|' . $sale['customer_address'] . '|' . $sale['created_at'];
        if (!isset($grouped_sales[$key])) {
            $grouped_sales[$key] = [
                'ids' => [],
                'customer_name' => $sale['customer_name'],
                'customer_phone' => $sale['customer_phone'],
                'customer_address' => $sale['customer_address'],
                'customer_gst' => $sale['customer_gst'],
                'created_at' => $sale['created_at'],
                'products' => [],
                'total_price' => 0
            ];
        }
        
        // Calculate tax (assuming GST is SGST + CGST, or IGST for inter-state)
        $taxable_value = $sale['price_per_piece'] * $sale['quantity'];
        $tax_rate = $sale['gst_percentage']; // Total tax rate
        $tax_amount = $taxable_value * ($tax_rate / 100);
        
        $grouped_sales[$key]['ids'][] = $sale['id'];
        $grouped_sales[$key]['products'][] = [
            'product_id' => $sale['product_id'],
            'product_name' => $sale['product_name'],
            'quantity' => $sale['quantity'],
            'price_per_piece' => $sale['price_per_piece'],
            'tax_rate' => $tax_rate,
           
            'taxable_value' => $taxable_value,
            'tax_amount' => $tax_amount,
            'total_price' => $sale['total_price']
        ];
        $grouped_sales[$key]['total_price'] += $sale['total_price'];
        $total_tax += $tax_amount;
        $total_price += $sale['total_price'];
    }

    // Sanitize sales data for display and export
    $sanitized_sales = array_map(function($sale) {
        return [
            'ids' => $sale['ids'],
            'customer_name' => htmlspecialchars($sale['customer_name'], ENT_QUOTES, 'UTF-8'),
            'customer_phone' => htmlspecialchars($sale['customer_phone'], ENT_QUOTES, 'UTF-8'),
            'customer_address' => htmlspecialchars(str_replace(["\r", "\n"], ' ', $sale['customer_address']), ENT_QUOTES, 'UTF-8'),
            'customer_gst' => htmlspecialchars($sale['customer_gst'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
            'products' => array_map(function($prod) {
                return [
                    'product_id' => (int)$prod['product_id'],
                    'product_name' => htmlspecialchars($prod['product_name'], ENT_QUOTES, 'UTF-8'),
                    'quantity' => (int)$prod['quantity'],
                    'price_per_piece' => (float)$prod['price_per_piece'],
                    'tax_rate' => (float)$prod['tax_rate'],
                    
                    'taxable_value' => (float)$prod['taxable_value'],
                    'tax_amount' => (float)$prod['tax_amount'],
                    'total_price' => (float)$prod['total_price']
                ];
            }, $sale['products']),
            'total_price' => (float)$sale['total_price'],
            'created_at' => $sale['created_at']
        ];
    }, array_values($grouped_sales));

} catch (Exception $e) {
    $error_message = "Error fetching sales data: " . $e->getMessage();
    error_log("GSTR-1 Fetch Error: " . $e->getMessage());
    file_put_contents('debug.log', "GSTR-1 Fetch Error: " . $e->getMessage() . " | Line: " . $e->getLine() . "\n", FILE_APPEND);
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

    .table-top-head .excel-icon {
        color: var(--green);
    }

    .btn-custom {
        background-color: var(--orange) !important;
        border-color: var(--orange) !important;
    }

    .btn-custom:hover {
        background-color: #e68f3b !important;
        border-color: #e68f3b !important;
    }

    .table-responsive table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        vertical-align: middle;
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid var(--grey-light);
    }

    .table th {
        background: var(--grey-light);
        border-bottom: 2px solid var(--secondary);
        font-weight: bold;
    }

    .alert-success {
        background-color: var(--green);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .alert-danger {
        background-color: var(--red);
        color: #fff;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 5px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-control {
        border-radius: 5px;
    }

    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        margin-bottom: 20px;
    }

    .filter-form select, .filter-form input {
        max-width: 200px;
    }

    .total-row {
        font-weight: bold;
        background: var(--grey-light);
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
        .filter-form {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>GSTR-1 Reports</h4>
            <h6>Generate and download GSTR-1 sales reports</h6>
        </div>
        <ul class="table-top-head">
            <li>
                <a href="javascript:void(0);" class="excel-icon" data-bs-toggle="tooltip" title="Export to Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </li>
        </ul>
    </div>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form class="filter-form" method="post">
                <div class="form-group">
                    <label for="filter_type">Filter By</label>
                    <select class="form-control" id="filter_type" name="filter_type" onchange="toggleFilterFields()">
                        <option value="monthly" <?php echo $filter_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $filter_type === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                        <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="form-group" id="month_field" style="<?php echo $filter_type !== 'monthly' ? 'display:none;' : ''; ?>">
                    <label for="month">Select Month</label>
                    <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
                </div>
                <div class="form-group" id="year_field" style="<?php echo $filter_type !== 'yearly' ? 'display:none;' : ''; ?>">
                    <label for="year">Select Year</label>
                    <select class="form-control" id="year" name="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selected_year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" id="start_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    <label for="start_date">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="form-group" id="end_date_field" style="<?php echo $filter_type !== 'custom' ? 'display:none;' : ''; ?>">
                    <label for="end_date">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-custom">Apply Filter</button>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table datatable" id="gstr1Table">
                    <thead class="thead-light">
                        <tr>
                            <th>Invoice ID</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>GSTIN</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Taxable Value</th>
                            <th>Tax Rate</th>
                            <th>Tax Amount</th>
                            <th>Total Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sanitized_sales)): ?>
                            <?php foreach ($sanitized_sales as $sale): ?>
                                <?php foreach ($sale['products'] as $index => $product): ?>
                                    <tr>
                                        <?php if ($index === 0): ?>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo htmlspecialchars(implode('-', $sale['ids'])); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo (new DateTime($sale['created_at'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('d-m-Y'); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo htmlspecialchars($sale['customer_name']); ?>
                                            </td>
                                            <td rowspan="<?php echo count($sale['products']); ?>">
                                                <?php echo htmlspecialchars($sale['customer_gst']); ?>
                                            </td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td><?php echo $product['quantity']; ?></td>
                                        <td><?php echo number_format($product['taxable_value'], 2); ?></td>
                                        <td><?php echo number_format($product['tax_rate'], 2); ?>%</td>
                                        <td><?php echo number_format($product['tax_amount'], 2); ?></td>
                                        <td><?php echo number_format($product['total_price'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="6"><strong>Total</strong></td>
                                <td><strong><?php echo number_format(array_sum(array_map(function($sale) {
                                    return array_sum(array_map(function($prod) { return $prod['taxable_value']; }, $sale['products']));
                                }, $sanitized_sales)), 2); ?></strong></td>
                                <td></td>
                                <td><strong><?php echo number_format($total_tax, 2); ?></strong></td>
                                <td><strong><?php echo number_format($total_price, 2); ?></strong></td>
                            </tr>
                        <?php else: ?>
                            <tr><td colspan="10">No sales found for the selected period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-bs-toggle="tooltip"]').tooltip();

    // Toggle filter fields based on filter type
    window.toggleFilterFields = function() {
        const filterType = $('#filter_type').val();
        $('#month_field').toggle(filterType === 'monthly');
        $('#year_field').toggle(filterType === 'yearly');
        $('#start_date_field').toggle(filterType === 'custom');
        $('#end_date_field').toggle(filterType === 'custom');
    };

    // Export to Excel
    window.exportToExcel = function() {
        try {
            const table = document.getElementById('gstr1Table');
            const rows = table.querySelectorAll('tr');
            let csvContent = "Invoice ID,Date,Customer Name,GSTIN,Product Name,Quantity,Taxable Value,Tax Rate,Tax Amount,Total Price\n";

            let totalTaxable = 0, totalTax = 0, totalPrice = 0;
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].querySelectorAll('td');
                if (cols.length < 10) continue; // Skip empty or invalid rows
                const rowData = [
                    cols[0].innerText.replace(/,/g, ''),
                    cols[1].innerText.replace(/,/g, ''),
                    cols[2].innerText.replace(/,/g, ''),
                    cols[3].innerText.replace(/,/g, ''),
                    cols[4].innerText.replace(/,/g, ''),
                    cols[5].innerText.replace(/,/g, ''),
                    cols[6].innerText.replace(/,/g, ''),
                    cols[7].innerText.replace(/,/g, ''),
                    cols[8].innerText.replace(/,/g, ''),
                    cols[9].innerText.replace(/,/g, '')
                ];
                csvContent += rowData.join(',') + '\n';
                if (i < rows.length - 1) { // Exclude total row from calculations
                    totalTaxable += parseFloat(cols[6].innerText.replace(/,/g, '')) || 0;
                    totalTax += parseFloat(cols[8].innerText.replace(/,/g, '')) || 0;
                    totalPrice += parseFloat(cols[9].innerText.replace(/,/g, '')) || 0;
                }
            }

            // Append totals row
            csvContent += `,,,,,Total,${totalTaxable.toFixed(2)},,${totalTax.toFixed(2)},${totalPrice.toFixed(2)}\n`;

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'gstr1_report.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (e) {
            console.error('Error in exportToExcel:', e);
            alert('Failed to export to Excel. Check console for details.');
        }
    };
});
</script>

<?php
ob_end_flush();
?>