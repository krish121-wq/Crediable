<?php
ob_start(); // Enable output buffering
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

$page_title = "Brands List";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    try {
        if (isset($_POST['add_brand'])) {
            $brand_name = $_POST['brand_name'] ?? '';
            $status = isset($_POST['status']) ? 1 : 0;

            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "uploads/";
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        throw new Exception("Failed to create uploads directory. Check permissions.");
                    }
                }
                $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
                $target_file = $target_dir . $image_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png'];
                $max_size = 2 * 1024 * 1024;

                if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= $max_size) {
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        throw new Exception("Image upload failed for file: " . $_FILES['image']['name']);
                    }
                    $image_path = $target_file;
                } else {
                    throw new Exception("Invalid image type or size: " . $imageFileType . ", " . $_FILES['image']['size'] . " bytes");
                }
            }

            if ($brand_name) {
                $stmt = $conn->prepare("INSERT INTO brands (brand_name, status, image_path, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("sisi", $brand_name, $status, $image_path, $user_id);
                if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
                $stmt->close();
            } else {
                throw new Exception("Missing required field: brand_name");
            }
        } elseif (isset($_POST['edit_brand'])) {
            $id = $_POST['id'] ?? '';
            if (!$id) throw new Exception("Missing id for edit");

            // Fetch current record to compare
            $stmt = $conn->prepare("SELECT brand_name, status, image_path FROM brands WHERE id = ? AND created_by = ?");
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) throw new Exception("Record not found for id: $id");
            $current = $result->fetch_assoc();
            $stmt->close();

            $brand_name = $_POST['brand_name'] ?? $current['brand_name'];
            $status = isset($_POST['status']) ? 1 : (int)$current['status'];

            $image_path = $current['image_path'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                $target_dir = "Uploads/";
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        throw new Exception("Failed to create uploads directory. Check permissions.");
                    }
                }
                $image_name = uniqid() . '_' . basename($_FILES['image']['name']);
                $target_file = $target_dir . $image_name;
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png'];
                $max_size = 2 * 1024 * 1024;

                if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= $max_size) {
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        throw new Exception("Image upload failed for edit: " . $_FILES['image']['name']);
                    }
                    $image_path = $target_file;
                    if ($current['image_path'] && $current['image_path'] !== $image_path && file_exists($current['image_path'])) {
                        unlink($current['image_path']);
                    }
                } else {
                    throw new Exception("Invalid image type or size for edit: " . $imageFileType . ", " . $_FILES['image']['size'] . " bytes");
                }
            }

            // Build dynamic UPDATE query for changed fields
            $updates = [];
            $params = [];
            $types = '';
            $values = [$id, $user_id]; // WHERE clause parameters

            if ($brand_name != $current['brand_name']) {
                $updates[] = "brand_name = ?";
                $params[] = $brand_name;
                $types .= 's';
            }
            if ($status != $current['status']) {
                $updates[] = "status = ?";
                $params[] = $status;
                $types .= 'i';
            }
            if ($image_path != $current['image_path']) {
                $updates[] = "image_path = ?";
                $params[] = $image_path;
                $types .= 's';
            }

            if (empty($updates)) {
                // No changes, redirect without update
                header("Location: brand-list.php");
                exit();
            }

            $updates[] = "updated_by = ?";
            $updates[] = "updated_at = NOW()";
            $params[] = $user_id; // updated_by
            $types .= 'i'; // Type for updated_by

            $query = "UPDATE brands SET " . implode(", ", $updates) . " WHERE id = ? AND created_by = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param($types . 'ii', ...array_merge($params, $values));
            if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
            $stmt->close();

            header("Location: brand-list.php");
            exit();
        } elseif (isset($_POST['delete_brand'])) {
            $id = $_POST['id'] ?? '';
            if ($id) {
                // Fetch image path to delete the file
                $stmt = $conn->prepare("SELECT image_path FROM brands WHERE id = ? AND created_by = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("ii", $id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if ($row['image_path'] && file_exists($row['image_path'])) {
                        unlink($row['image_path']);
                    }
                }
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM brands WHERE id = ? AND created_by = ?");
                if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
                $stmt->bind_param("ii", $id, $user_id);
                if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
                $stmt->close();
            } else {
                throw new Exception("Missing id for delete: $id");
            }
            header("Location: brand-list.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo "Error: " . $e->getMessage();
    }
}

// Pagination setup
$user_id = $_SESSION['user_id'];
$records_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

try {
    $base_query = "SELECT * FROM brands WHERE created_by = ?";
    $params = [$user_id];
    $types = "i";

    $stmt = $conn->prepare($base_query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_records = $result->num_rows;
    $stmt->close();

    $query = $base_query . " LIMIT ?, ?";
    $params[] = $start_from;
    $params[] = $records_per_page;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) throw new Exception("Result fetch failed: " . $conn->error);
    $stmt->close();

    $total_pages = ceil($total_records / $records_per_page);
} catch (Exception $e) {
    error_log("Pagination Error: " . $e->getMessage());
    echo "Pagination Error: " . $e->getMessage();
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
        --teal: #2a9d8f;
        --blue-light: #4dabf7;
        --red: #ff3300;
        --green: #00b328; 
        --purple: #9b59b6;
    }

    .content {
        margin-top: 80px;
        margin-left: 250px;
        padding: 20px 30px;
        min новымheight: calc(100vh - 112px);
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
        background-color: darken(var(--orange), 10%) !important;
        border-color: darken(var(--orange), 10%) !important;
    }

    .search-set {
        display: flex;
        align-items: center;
    }

    .btn-searchset {
        position: absolute;
        left: 25px;
        top: 8%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    .search-input input {
        padding-left: 30px;
        border-radius: 5px;
        height: 38px;
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

    .badge {
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .badge.bg-success {
        background-color: #28a745;
        color: #fff;
    }

    .badge.bg-warning {
        background-color: #ffc107;
        color: #212529;
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
        color: var(--text-primary);
        background: var(--orange);
        border-radius: 5px;
        color: #fff;
    }

    .pagination a.active {
        background: darken(var(--orange), 10%);
        color: #fff;
    }

    .pagination a:hover:not(.active) {
        background: darken(var(--orange), 20%);
        color: #fff;
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
        .card-header .search-set {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<div class="content <?php echo !$is_restricted ? 'content-full' : ''; ?>">
    <div class="page-header">
        <div class="page-title">
            <h4>Brands</h4>
            <h6>Manage your brands</h6>
        </div>
        <ul class="table-top-head">
            <li>
                <a href="#" class="pdf-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Pdf" data-bs-original-title="Pdf" onclick="exportToPDF()"><i class="fas fa-file-pdf"></i></a>
            </li>
            <li>
                <a href="#" class="excel-icon" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="Excel" data-bs-original-title="Excel" onclick="exportToExcel()"><i class="fas fa-file-excel"></i></a>
            </li>
        </ul>
        <div class="page-btn">
            <a href="#" class="btn btn-custom btn-sm" data-bs-toggle="modal" data-bs-target="#brandModal"><i class="fas fa-circle-plus me-1"></i>Add Brand</a>
        </div>
    </div>
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <span class="btn-searchset"><i class="fas fa-search fs-14"></i></span>
                    <input type="search" class=" instalarcontrol form-control-sm" placeholder="Search" aria-controls="brandTable" onkeyup="searchTable()">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table datatable dataTable no-footer" id="brandTable" aria-describedby="brandTable_info">
                    <thead class="thead-light">
                        <tr>
                            <th class="no-sort">S.No.</th>
                            <th>Brand</th>
                            <th>Created On</th>
                            <th>Status</th>
                            <th class="no-sort">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sno = $start_from + 1;
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                $created_at = new DateTime($row['created_at'], new DateTimeZone('UTC'));
                                $created_at->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                ?>
                                <tr>
                                    <td class="sorting_1"><?php echo $sno++; ?></td>
                                    <td>
                                        <?php if ($row['image_path']) { ?>
                                            <img src="<?php echo $row['image_path']; ?>" alt="Brand Image" style="max-width: 50px; max-height: 50px; margin-right: 10px;">
                                        <?php } else { ?>
                                            <span>No Image</span>
                                        <?php } ?>
                                        <?php echo htmlspecialchars($row['brand_name']); ?>
                                    </td>
                                    <td><?php echo $created_at->format('Y-m-d H:i:s'); ?></td>
                                    <td><span class="badge <?php echo $row['status'] ? 'bg-success' : 'bg-warning'; ?> fw-medium fs-10"><?php echo $row['status'] ? 'Active' : 'Inactive'; ?></span></td>
                                    <td class="action-table-data">
                                        <div class="edit-delete-action">
                                            <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#brandModal" onclick="editBrand(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['brand_name']); ?>', '<?php echo $row['status']; ?>', '<?php echo $row['image_path'] ?? ''; ?>')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-edit"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteId').value = <?php echo $row['id']; ?>;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo "<tr><td colspan='5'>No data available or an error occurred.</td></tr>";
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
                echo "<a href='?page=" . ($page - 1) . "' class='prev'>Previous</a>";
            }
            for ($i = 1; $i <= $total_pages; $i++) {
                echo "<a href='?page=" . $i . "' class='" . ($page == $i ? 'active' : '') . "'>" . $i . "</a>";
            }
            if ($page < $total_pages) {
                echo "<a href='?page=" . ($page + 1) . "' class='next'>Next</a>";
            }
        }
        ?>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="brandModal" tabindex="-1" aria-labelledby="brandModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="page-title">
                        <h4>Add Brand</h4>
                    </div>
                    <button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <form action="brand-list.php" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="brandId">
                        <input type="hidden" name="existing_image" id="existingImage">
                        <div class="mb-3">
                            <div class="add-image-upload">
                                <div class="add-image" onclick="document.getElementById('imageUpload').click()">
                                    <span class="fw-normal"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus-circle plus-down-add"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg> Add Image</span>
                                </div>
                                <div class="new-employee-field">
                                    <div class="mb-0">
                                        <div class="image-upload mb-2">
                                            <input type="file" id="imageUpload" name="image" onchange="previewImage(event)">
                                            <div class="image-uploads" id="imagePreviewContainer">
                                                <h4 class="fs-13 fw-medium">Upload Image</h4>
                                                <img id="imagePreview" class="image-preview" style="display: none;">
                                            </div>
                                        </div>
                                        <span>JPEG, PNG up to 2 MB</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Brand Name<span class="text-danger ms-1">*</span></label>
                            <input type="text" class="form-control" id="brand_name" name="brand_name" required>
                        </div>
                        <div class="mb-0">
                            <div class="status-toggle modal-status d-flex justify-content-between align-items-center">
                                <span class="status-label">Status</span>
                                <input type="checkbox" id="status" name="status" class="check" checked>
                                <label for="status" class="checktoggle"></label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_brand" class="btn btn-custom">Add Brand</button>
                        <button type="submit" name="edit_brand" class="btn btn-custom" style="display:none;" id="editBrandButton">Update Brand</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this brand?
                    <form method="post">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_brand" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
    function previewImage(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('imagePreview');
        const container = document.getElementById('imagePreviewContainer');
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
            container.querySelector('h4').style.display = 'none';
        }
    }

    function editBrand(id, brand_name, status, image_path) {
        document.getElementById('brandId').value = id;
        document.getElementById('brand_name').value = brand_name;
        document.getElementById('status').checked = (status == '1');
        document.getElementById('existingImage').value = image_path;
        const preview = document.getElementById('imagePreview');
        const container = document.getElementById('imagePreviewContainer');
        if (image_path) {
            preview.src = image_path;
            preview.style.display = 'block';
            container.querySelector('h4').style.display = 'none';
        } else {
            preview.style.display = 'none';
            container.querySelector('h4').style.display = 'block';
        }
        document.querySelector('button[name="add_brand"]').style.display = 'none';
        document.getElementById('editBrandButton').style.display = 'inline-block';
    }

    function searchTable() {
        let input = document.querySelector('.search-input input').value.toLowerCase();
        let table = document.getElementById('brandTable');
        let tr = table.getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            let td = tr[i].getElementsByTagName('td')[1]; // Brand column
            if (td) {
                let text = td.textContent || td.innerText;
                tr[i].style.display = text.toLowerCase().indexOf(input) > -1 ? '' : 'none';
            }
        }
    }

    function exportToPDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const table = document.getElementById('brandTable');
        const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent);
        const rows = Array.from(table.querySelectorAll('tbody tr')).map(tr => 
            Array.from(tr.querySelectorAll('td')).slice(0, -1).map(td => td.textContent)
        );

        doc.autoTable({
            head: [headers],
            body: rows,
        });
        doc.save('brands_list.pdf');
    }

    function exportToExcel() {
        const table = document.getElementById('brandTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Brands" });
        XLSX.writeFile(wb, 'brands_list.xlsx');
    }
</script>