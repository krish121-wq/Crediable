<?php
require_once 'config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) session_start();

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;
$error_message = '';
$bills = [];

try {
    // Get all cash bills with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Base query
    $query = "SELECT cb.id, cb.bill_number, cb.customer_name, cb.mobile_number, 
                     cb.total_amount, cb.paid_amount, cb.created_at, 
                     COALESCE(u.full_name, cu.name) as created_by,
                     cb.created_by as created_by_id 
              FROM cash_bills cb 
              LEFT JOIN users u ON cb.created_by = u.id 
              LEFT JOIN create_users cu ON cb.created_by = cu.id ";
    
    $where = [];
    $types = "";
    $params = [];
    
    // Show only staff's own bills if user is staff
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff') {
        $where[] = "cb.created_by = ?";
        $types .= "i";
        $params[] = $user_id;
    }
    
    // Build the WHERE clause if needed
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    
    // Add order and limit
    $query .= " ORDER BY cb.created_at DESC 
                LIMIT ? OFFSET ?";
    
    // Add limit and offset to params
    $types .= "ii";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters dynamically
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $bills = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM cash_bills cb";
    $count_where = [];
    $count_params = [];
    $count_types = "";
    
    // Show only staff's own bills if user is staff
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff') {
        $count_where[] = "cb.created_by = ?";
        $count_types .= "i";
        $count_params[] = $user_id;
    }
    
    // Build the WHERE clause if needed
    if (!empty($count_where)) {
        $count_query .= " WHERE " . implode(" AND ", $count_where);
    }
    
    $count_stmt = $conn->prepare($count_query);
    
    // Bind parameters if any
    if (!empty($count_params)) {
        $count_stmt->bind_param($count_types, ...$count_params);
    }
    
    $count_stmt->execute();
    $total_bills = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    $total_pages = ceil($total_bills / $per_page);
    
} catch (Exception $e) {
    $error_message = 'Error fetching cash bills: ' . $e->getMessage();
    error_log($error_message);
}

// Include layout
include('layout.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Bills - CREDIBLE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220; /* Black Theme */
            --brand-mid: #0ea5e9; /* Sky Blue Theme */
            --brand-light: #e0f2fe; /* Light White/Blue Theme */
            --success: #16a34a;
            --success-bg: #dcfce7;
            --danger: #dc2626;
            --danger-bg: #fee2e2;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --info: #0284c7;
            --info-bg: #e0f2fe;
            --bg: #f4f8fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --radius-sm: 7px;
            --radius-md: 12px;
            --radius-lg: 18px;
            --shadow-sm: 0 2px 8px rgba(2,132,199,.07);
            --shadow-md: 0 8px 24px rgba(2,132,199,.11);
            --shadow-lg: 0 16px 48px rgba(2,132,199,.16);
            --transition: all .22s cubic-bezier(.4,0,.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            font-size: 13.5px; 
        }

        /* Main Content Area */
        .page-wrap {
            margin-top: 60px;
            margin-left: 260px;
            padding: 22px 24px 48px;
            transition: var(--transition);
            min-height: calc(100vh - 60px);
        }

        @media (max-width: 992px) { 
            .page-wrap { margin-left: 0; padding: 16px 14px 40px; } 
        }

        /* Hero Banner (Upgraded to match master file) */
        .page-hero {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 20px 26px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: ''; position: absolute;
            right: -40px; top: -40px;
            width: 160px; height: 160px;
            border-radius: 50%; background: rgba(255,255,255,.06);
        }
        @media (max-width: 480px) {
            .page-hero { flex-direction: column; align-items: flex-start; }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem; font-weight: 800; color: #fff;
            display: flex; align-items: center;
        }
        .hero-sub { color: rgba(255,255,255,.72); font-size: .8rem; margin-top: 2px; }
        .hero-badge {
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 50px; padding: 8px 18px;
            color: #fff; font-size: .8rem; font-weight: 600;
            display: flex; align-items: center; gap: 6px; white-space: nowrap;
            text-decoration: none; transition: var(--transition);
        }
        .hero-badge:hover {
            background: rgba(255,255,255,.25); color: #fff;
        }

        /* Section Cards */
        .section-card {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
            overflow: hidden;
            transition: var(--transition);
        }
        .section-card:hover { box-shadow: var(--shadow-md); }

        .sec-head {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 18px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
        }
        .sec-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: .85rem; flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(2,132,199,.22);
        }
        .sec-head h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: .95rem; font-weight: 700; color: var(--brand); margin: 0;
        }
        .sec-head p { font-size: .72rem; color: var(--text-muted); margin-top: 1px; margin-bottom: 0;}
        .sec-body { padding: 20px; }

        /* Buttons */
        .btn-primary-c {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff !important; border: none;
            padding: 9px 20px; border-radius: 50px;
            font-size: .85rem; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: inline-flex; align-items: center; gap: 6px;
            cursor: pointer; transition: var(--transition);
            box-shadow: 0 5px 16px rgba(2,132,199,.30);
            text-decoration: none;
        }
        .btn-primary-c:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(2,132,199,.40); }
        
        .btn-outline-custom {
            background: transparent; color: var(--text-mid);
            border: 1.5px solid var(--border);
            padding: 7px 18px; border-radius: 50px;
            font-size: .75rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px;
            cursor: pointer; transition: var(--transition); text-decoration: none;
        }
        .btn-outline-custom:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }

        /* Table */
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead th {
            /* Black + Sky Blue gradient */
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff; padding: 12px 15px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; border: none;
        }
        .data-table tbody td {
            padding: 12px 15px; font-size: 0.85rem; border-bottom: 1px solid var(--border); vertical-align: middle;
        }
        .data-table tbody tr:hover td { background: #faf9fc; }

        /* Action Buttons */
        .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn {
            width: 30px; height: 30px; border-radius: 7px;
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; text-decoration: none; transition: var(--transition); font-size: 0.75rem;
        }
        .action-btn:hover { transform: translateY(-2px) scale(1.05); color: #fff;}
        .action-btn.view { background: var(--info); }
        .action-btn.print { background: linear-gradient(135deg, #0ea5e9, #0284c7); }
        .action-btn.whatsapp { background: #25D366; }
        .action-btn.edit { background: linear-gradient(135deg, var(--brand), var(--brand-mid)); }
        .action-btn.delete { background: linear-gradient(135deg, #ef4444, #b91c1c); border: none;}

        /* Alert */
        .alert-custom {
            border-radius: var(--radius-sm); padding: 11px 14px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 500;
            animation: slideIn .3s ease;
        }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)} }
        .alert-success-custom { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }
        .alert-danger-custom { background: var(--danger-bg); color: var(--danger); border: 1px solid #fca5a5; }

        /* Pagination */
        .pagination .page-link { color: var(--brand); border-radius: 6px; padding: 6px 12px; font-size: 0.8rem; margin: 0 2px;}
        .pagination .active .page-link { background: var(--brand-dark); border-color: var(--brand-dark); color: #fff; }

        /* Modal */
        .modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); }
        .modal-header {
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            color: #fff; border-radius: var(--radius-md) var(--radius-md) 0 0; border: none; padding: 16px 20px;
        }
        .modal-header .btn-close { filter: invert(1); }

        /* Responsive */
        @media (max-width: 768px) {
            .sec-body { padding: 16px; overflow-x: auto; }
            .action-btns { flex-direction: row; }
        }

        /* DataTables override */
        .dataTables_wrapper .dataTables_filter input {
            border: 1.5px solid var(--border); border-radius: 50px;
            padding: 6px 12px 6px 12px; font-size: 0.8rem; outline: none; transition: var(--transition);
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(2,132,199,.12); }
        .dataTables_wrapper .dataTables_length select {
            border: 1.5px solid var(--border); border-radius: 8px; padding: 5px; outline: none;
        }
        .dataTables_info { font-size: 0.8rem; color: var(--text-muted); }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <!-- Hero Banner with new Theme -->
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-money-bill-wave me-2" style="opacity:.8"></i>Cash Bills</div>
            <div class="hero-sub">View and manage all cash bills</div>
        </div>
        <a href="create_cash_bill.php" class="hero-badge">
            <i class="fas fa-plus-circle"></i> Create New Bill
        </a>
    </div>

    <!-- Alerts -->
    <?php if (!empty($error_message)): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert-custom alert-success-custom">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Main Section Card mapped from master design -->
    <div class="section-card">
        <div class="sec-head">
            <div class="sec-icon"><i class="fas fa-list"></i></div>
            <div>
                <h2>All Cash Bills</h2>
                <p>Manage and track your cash transactions</p>
            </div>
        </div>
        
        <div class="sec-body">
            <div class="table-responsive">
                <table class="data-table" id="cashBillsTable">
                    <thead>
                        <tr>
                            <th>Bill No.</th>
                            <th>Date</th>
                            <th>Customer Name</th>
                            <th>Mobile</th>
                            <th>Total (₹)</th>
                            <th>Paid (₹)</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No cash bills found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($bill['created_at'])); ?></td>
                                    <td><?php echo !empty($bill['customer_name']) ? htmlspecialchars($bill['customer_name']) : 'Walk-in Customer'; ?></td>
                                    <td><?php echo htmlspecialchars($bill['mobile_number']); ?></td>
                                    <td>₹<?php echo number_format($bill['total_amount'], 2); ?></td>
                                    <td>₹<?php echo number_format($bill['paid_amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($bill['created_by']); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="view_cash_bill.php?id=<?php echo $bill['id']; ?>" class="action-btn view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="view_cash_bill.php?id=<?php echo $bill['id']; ?>&print=1" class="action-btn print" title="Print" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php if (!empty($bill['mobile_number'])): ?>
                                            <a href="https://wa.me/91<?php echo ltrim(htmlspecialchars($bill['mobile_number']), '0'); ?>?text=<?php echo urlencode('Your invoice #' . $bill['bill_number'] . ' is ready. Total Amount: ₹' . number_format($bill['total_amount'], 2) . '. View your bill: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/view_cash_bill.php?id=' . $bill['id']); ?>" class="action-btn whatsapp" title="WhatsApp" target="_blank">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="editcash_bill.php?id=<?php echo $bill['id']; ?>" class="action-btn edit" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff'): ?>
                                            <button type="button" class="action-btn delete delete-bill" data-id="<?php echo $bill['id']; ?>" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>">Next <i class="fas fa-chevron-right"></i></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#b91c1c);">
                <h5 class="modal-title" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0;"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div style="width:60px;height:60px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 15px;">
                    <i class="fas fa-trash" style="font-size:1.5rem;color:#dc2626;"></i>
                </div>
                <h6 style="font-weight:700;">Delete this bill?</h6>
                <p style="color:var(--text-muted);font-size:0.85rem;">This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center gap-2">
                <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-c" id="confirmDelete" style="background:linear-gradient(135deg,#ef4444,#b91c1c);">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with custom styling
    $('#cashBillsTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "language": {
            "search": "Search:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "No entries found",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Prev"
            }
        },
        "dom": '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // Delete confirmation
    let billIdToDelete = null;
    $('.delete-bill').on('click', function() {
        billIdToDelete = $(this).data('id');
        $('#deleteBillModal').modal('show');
    });

    $('#confirmDelete').on('click', function() {
        if (billIdToDelete) {
            window.location.href = 'delete_cash_bill.php?id=' + billIdToDelete;
        }
    });
});
</script>
</body>
</html>