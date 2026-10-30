<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');
// session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$page_title = "Categories List";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    if (isset($_POST['add_category'])) {
        $category = $_POST['category'];
        $slug = $_POST['slug'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("INSERT INTO categories (category_name, slug, status, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $category, $slug, $status, $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $category = $_POST['category'];
        $slug = $_POST['slug'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, slug = ?, status = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
        $stmt->bind_param("sssiii", $category, $slug, $status, $user_id, $id, $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['delete_category'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Pagination setup
$user_id = $_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

$stmt = $conn->prepare("SELECT * FROM categories WHERE created_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->num_rows;
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM categories WHERE created_by = ? LIMIT ?, ?");
$stmt->bind_param("iii", $user_id, $start_from, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$total_pages = ceil($total_records / $records_per_page);
?>

<!-- Dashboard-specific CSS -->
<style>
    :root {
        --brand: #0284c7;
        --brand-dark: #0b1220;
        --brand-mid: #0ea5e9;
        --brand-light: #e0f2fe;
        --success: #16a34a;
        --danger: #dc2626;
        --warning: #d97706;
        --bg: #f4f8fb;
        --card: #ffffff;
        --border: #e2e8f0;
        --text: #0b1220;
        --text-mid: #475569;
        --text-muted: #94a3b8;
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 18px;
        --shadow-sm: 0 2px 8px rgba(2,132,199,.07);
        --shadow-md: 0 8px 24px rgba(2,132,199,.11);
        --shadow-lg: 0 16px 48px rgba(2,132,199,.16);
        --t: all .22s cubic-bezier(.4,0,.2,1);
    }

    .page-wrap {
        margin-top: 60px;
        margin-left: 260px;
        padding: 22px 24px 48px;
        transition: var(--t);
    }
    @media (max-width: 992px) { .page-wrap { margin-left: 0; padding: 16px 14px 40px; } }

    .page-hero {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
        border-radius: var(--radius-lg);
        padding: 20px 26px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 14px; margin-bottom: 20px;
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
    .hero-title {
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.25rem; font-weight: 800; color: #fff;
    }
    .hero-sub { color: rgba(255,255,255,.72); font-size: .8rem; margin-top: 2px; }

    .btn-hero-add {
        background: rgba(255,255,255,.18);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,.3);
        color: #fff; font-size: .82rem; font-weight: 700;
        border-radius: 50px; padding: 8px 18px;
        display: flex; align-items: center; gap: 6px; white-space: nowrap;
        transition: var(--t); text-decoration: none;
    }
    .btn-hero-add:hover {
        background: #fff; color: var(--brand-dark); transform: translateY(-1px);
    }

    .main-card {
        background: var(--card);
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .card-filter-bar {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; flex-wrap: wrap;
    }

    .search-box {
        position: relative; flex: 1; max-width: 320px;
    }
    .search-box i {
        position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
        color: var(--text-muted); font-size: .85rem;
    }
    .search-box input {
        width: 100%; padding: 8px 14px 8px 36px;
        border-radius: var(--radius-sm); border: 1px solid var(--border);
        font-size: .82rem; transition: var(--t); background: #f8fafc;
    }
    .search-box input:focus {
        background: #fff; border-color: var(--brand); outline: none; box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    }

    .export-btns {
        display: flex; align-items: center; gap: 8px;
    }
    .btn-export {
        width: 34px; height: 34px; border-radius: var(--radius-sm);
        border: 1px solid var(--border); background: #fff;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-mid); font-size: .9rem; transition: var(--t); text-decoration: none;
    }
    .btn-export.pdf:hover { background: #fef2f2; color: var(--danger); border-color: #fca5a5; }
    .btn-export.excel:hover { background: #f0fdf4; color: var(--success); border-color: #86efac; }

    .custom-table {
        width: 100%; border-collapse: collapse; font-size: .83rem;
    }
    .custom-table th {
        background: #f8fafc; color: var(--text-mid); font-size: .75rem;
        font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
        padding: 12px 18px; border-bottom: 1px solid var(--border); text-align: left;
    }
    .custom-table td {
        padding: 13px 18px; border-bottom: 1px solid var(--border);
        color: var(--text); vertical-align: middle;
    }
    .custom-table tbody tr:last-child td { border-bottom: none; }
    .custom-table tbody tr:hover { background: #f8fafc; }

    .badge-status {
        padding: 4px 10px; border-radius: 50px; font-size: .72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;
    }
    .badge-status.active { background: #dcfce7; color: #15803d; }
    .badge-status.inactive { background: #fef3c7; color: #b45309; }

    .act-btn {
        width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--border);
        display: inline-flex; align-items: center; justify-content: center;
        color: var(--text-mid); transition: var(--t); background: #fff; text-decoration: none;
    }
    .act-btn.edit:hover { background: var(--brand-light); color: var(--brand); border-color: var(--brand-mid); }
    .act-btn.del:hover { background: #fef2f2; color: var(--danger); border-color: #fca5a5; }

    .custom-pagination {
        display: flex; justify-content: center; gap: 6px; padding: 16px; border-top: 1px solid var(--border);
    }
    .custom-pagination a {
        padding: 6px 12px; border-radius: 6px; font-size: .8rem; font-weight: 600;
        color: var(--text-mid); border: 1px solid var(--border); text-decoration: none; transition: var(--t);
    }
    .custom-pagination a.active { background: var(--brand); color: #fff; border-color: var(--brand); }
    .custom-pagination a:hover:not(.active) { background: #f1f5f9; }

    /* Modal styling */
    .modal-content { border-radius: var(--radius-md); border: none; box-shadow: var(--shadow-lg); overflow: hidden; }
    .modal-hdr {
        background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 100%);
        color: #fff; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
    }
    .modal-hdr h5 { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; margin: 0; font-size: 1.05rem; }
    .modal-body { padding: 20px; }
    .form-group-custom { margin-bottom: 16px; }
    .form-group-custom label { font-size: .78rem; font-weight: 700; color: var(--text-mid); margin-bottom: 6px; display: block; }
    .form-group-custom input, .form-group-custom select {
        width: 100%; padding: 9px 13px; border-radius: var(--radius-sm); border: 1px solid var(--border); font-size: .84rem;
        transition: var(--t);
    }
    .form-group-custom input:focus, .form-group-custom select:focus {
        border-color: var(--brand); outline: none; box-shadow: 0 0 0 3px rgba(2,132,199,.12);
    }
    .btn-primary-c {
        background: var(--brand); color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 9px 20px; font-size: .83rem; font-weight: 700; transition: var(--t); cursor: pointer;
    }
    .btn-primary-c:hover { background: #0274b3; }
    .btn-ghost-c {
        background: #f1f5f9; color: var(--text-mid); border: 1px solid var(--border); border-radius: var(--radius-sm);
        padding: 9px 18px; font-size: .83rem; font-weight: 600; transition: var(--t); cursor: pointer;
    }
    .btn-ghost-c:hover { background: #e2e8f0; }
</style>


<!-- Dashboard Content -->
<div class="page-wrap">
    <div class="page-hero">
        <div>
            <div class="hero-title"><i class="fas fa-layer-group me-2" style="opacity:.8"></i>Category Management</div>
            <div class="hero-sub">Organize and manage your product categories</div>
        </div>
        <a href="#" class="btn-hero-add" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetModal()">
            <i class="fas fa-plus-circle"></i> Add Category
        </a>
    </div>

    <div class="main-card">
        <div class="card-filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="search" placeholder="Search categories..." aria-controls="categoryTable" onkeyup="searchTable()">
            </div>
            <div class="export-btns">
                <a href="#" class="btn-export pdf" title="Export PDF" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
                <a href="#" class="btn-export excel" title="Export Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="custom-table" id="categoryTable">
                <thead>
                    <tr>
                        <th style="width:70px">S.No.</th>
                        <th>Category Name</th>
                        <th>Category Slug</th>
                        <th>Created On</th>
                        <th>Status</th>
                        <th style="width:100px; text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sno = $start_from + 1;
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $created_at = new DateTime($row['created_at'], new DateTimeZone('UTC'));
                            $created_at->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            ?>
                            <tr>
                                <td><strong>#<?php echo $sno++; ?></strong></td>
                                <td><span style="font-weight:600; color:var(--brand-dark)"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                <td><code style="background:#f1f5f9; padding:2px 7px; border-radius:4px; font-size:.8rem; color:var(--text-mid)"><?php echo htmlspecialchars($row['slug']); ?></code></td>
                                <td><?php echo $created_at->format('d M Y, h:i A'); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $row['status'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas <?php echo $row['status'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                                        <?php echo $row['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <a class="act-btn edit me-1" href="#" data-bs-toggle="modal" data-bs-target="#categoryModal" title="Edit" onclick="editCategory(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['category_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['slug'], ENT_QUOTES); ?>', '<?php echo $row['status']; ?>')">
                                        <i class="fas fa-pen" style="font-size:11px"></i>
                                    </a>
                                    <a class="act-btn del" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" title="Delete" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;">
                                        <i class="fas fa-trash-can" style="font-size:11px"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php }
                    } else { ?>
                        <tr>
                            <td colspan="6" class="text-center py-4" style="color:var(--text-muted)">
                                <i class="fas fa-layer-group fs-3 mb-2 d-block opacity-50"></i>
                                No categories found. Click <strong>Add Category</strong> to create one.
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="custom-pagination">
                <?php
                for ($i = 1; $i <= $total_pages; $i++) {
                    echo "<a href='?page=" . $i . "' class='" . ($page == $i ? 'active' : '') . "'>" . $i . "</a>";
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-hdr">
                    <h5 id="categoryModalLabel"><i class="fas fa-layer-group me-2"></i>Add Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="categories-list.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="categoryId">
                        <div class="form-group-custom">
                            <label><i class="fas fa-tag me-1"></i> Category Name <span class="text-danger">*</span></label>
                            <input type="text" id="category" name="category" placeholder="e.g. Electronics" required onkeyup="generateSlug()">
                        </div>
                        <div class="form-group-custom">
                            <label><i class="fas fa-link me-1"></i> Category Slug <span class="text-danger">*</span></label>
                            <input type="text" id="slug" name="slug" placeholder="e.g. electronics" required>
                        </div>
                        <div class="form-group-custom mb-0">
                            <label><i class="fas fa-toggle-on me-1"></i> Status <span class="text-danger">*</span></label>
                            <select id="status" name="status" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="gap:8px">
                        <button type="button" class="btn-ghost-c" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_category" id="submitBtn" class="btn-primary-c">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-hdr" style="background: linear-gradient(135deg, #991b1b, #dc2626);">
                    <h5 id="deleteModalLabel"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="categories-list.php" method="post">
                    <div class="modal-body text-center py-4">
                        <input type="hidden" name="id" id="deleteId">
                        <div style="width:50px;height:50px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#dc2626;font-size:1.4rem">
                            <i class="fas fa-trash-can"></i>
                        </div>
                        <p style="font-weight:600; color:var(--text); margin-bottom:4px">Are you sure you want to delete this category?</p>
                        <span style="font-size:.78rem; color:var(--text-muted)">This action cannot be undone.</span>
                    </div>
                    <div class="modal-footer justify-content-center" style="gap:8px">
                        <button type="button" class="btn-ghost-c" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_category" class="btn-primary-c" style="background:var(--danger)">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsPDF/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
            body: rows,
        });
        doc.save('categories_list.pdf');
    }

    function exportToExcel() {
        const table = document.getElementById('categoryTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Categories" });
        XLSX.writeFile(wb, 'categories_list.xlsx');
    }
</script>