<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

include('layout.php');

$quotation_id = (int)($_GET['id'] ?? 0);
if (!$quotation_id) { header('Location: view_quotations.php'); exit(); }

// Fetch existing quotation header
$stmt = $conn->prepare("SELECT * FROM service_quotations WHERE id = ?");
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quotation) { die("Quotation not found."); }

// Fetch existing quotation items
$stmt = $conn->prepare("SELECT * FROM service_quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$customers = $conn->query("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quotation'])) {
    try {
        $conn->begin_transaction();
        
        $q_no = $_POST['quotation_no'];
        $q_date = $_POST['quotation_date'];
        $c_id = $_POST['customer_id'];
        $c_name = $_POST['customer_name'];
        $c_phone = $_POST['customer_phone'];
        $c_address = $_POST['customer_address'];
        $c_state = $_POST['customer_state'];
        $services = json_decode($_POST['services_json'], true);

        // Recalculate totals
        $subtotal = 0;
        foreach($services as $s) { $subtotal += $s['amount']; }
        $total_gst = $subtotal * 0.18;
        $grand_total = $subtotal + $total_gst;

        // Update Header
        $stmt = $conn->prepare("UPDATE service_quotations SET quotation_no=?, quotation_date=?, customer_id=?, customer_name=?, customer_phone=?, customer_address=?, customer_state=?, subtotal=?, gst_amount=?, total_amount=? WHERE id=?");
        $stmt->bind_param("ssissssdddi", $q_no, $q_date, $c_id, $c_name, $c_phone, $c_address, $c_state, $subtotal, $total_gst, $grand_total, $quotation_id);
        $stmt->execute();

        // Update Items (Delete and Re-insert is the safest method for dynamic rows)
        $conn->query("DELETE FROM service_quotation_items WHERE quotation_id = $quotation_id");
        
        $item_stmt = $conn->prepare("INSERT INTO service_quotation_items (quotation_id, service_name, service_description, sac_code, quantity, unit, rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($services as $s) {
            $item_stmt->bind_param("isssdsdd", $quotation_id, $s['service_name'], $s['service_description'], $s['sac_code'], $s['quantity'], $s['unit'], $s['rate'], $s['amount']);
            $item_stmt->execute();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Quotation updated successfully!";
        header("Location: view_quotations.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>

<style>
    :root { --primary: #0284c7; --radius: 8px; --shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .content { margin-top: 65px; margin-left: 260px; padding: 20px; transition: 0.3s; }
    .card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 25px; margin-bottom: 20px; }
    .service-row { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 10px; }
    .btn-custom { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    @media (max-width: 1024px) { .content { margin-left: 0; } }
    
    /* Select2 Styling */
    .select2-container--default .select2-selection--single { border: 1px solid #E5E7EB; border-radius: 8px; height: 38px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 12px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }

    .gst-breakup { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 20px; }
    .gst-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
    .gst-row:last-child { border-bottom: none; font-weight: 600; font-size: 1.1rem; color: var(--primary); border-top: 2px solid var(--primary); }
</style>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Edit Quotation</h4>
            <h6>Modify existing quotation <?php echo $quotation['quotation_no']; ?></h6>
        </div>
    </div>

    <form method="POST" id="quotationForm">
        <input type="hidden" name="services_json" id="services_json">
        
        <div class="card">
            <h5>Quotation Details</h5>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label class="form-label">Quotation No.</label>
                    <input type="text" name="quotation_no" class="form-control" value="<?php echo $quotation['quotation_no']; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date</label>
                    <input type="date" name="quotation_date" class="form-control" value="<?php echo $quotation['quotation_date']; ?>" required>
                </div>
            </div>
        </div>

        <div class="card">
            <h5>Customer Selection</h5>
            <div class="row mt-3">
                <div class="col-md-12 mb-3">
                    <select id="customer_select" class="form-control" name="customer_id" required>
                        <option value="">Search Customer...</option>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" 
                                <?php echo ($quotation['customer_id'] == $c['id']) ? 'selected' : ''; ?>
                                data-name="<?php echo $c['name']; ?>" 
                                data-phone="<?php echo $c['phone']; ?>" 
                                data-addr="<?php echo $c['address']; ?>" 
                                data-state="<?php echo $c['state']; ?>">
                                <?php echo $c['name']; ?> (<?php echo $c['phone']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><input type="text" name="customer_name" id="c_name" class="form-control" value="<?php echo $quotation['customer_name']; ?>" placeholder="Name" readonly required></div>
                <div class="col-md-4"><input type="text" name="customer_phone" id="c_phone" class="form-control" value="<?php echo $quotation['customer_phone']; ?>" placeholder="Phone" readonly required></div>
                <div class="col-md-4"><input type="text" name="customer_state" id="c_state" class="form-control" value="<?php echo $quotation['customer_state']; ?>" placeholder="State" readonly required></div>
                <div class="col-md-12 mt-2"><textarea name="customer_address" id="c_addr" class="form-control" placeholder="Address" readonly required><?php echo $quotation['customer_address']; ?></textarea></div>
            </div>
        </div>

        <div class="card">
            <h5>Service Items</h5>
            <div id="service_container">
                <?php foreach($items as $index => $item): ?>
                <div class="service-row">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" class="form-control s_name" placeholder="Service Name" value="<?php echo $item['service_name']; ?>" required>
                            <textarea class="form-control s_desc" placeholder="Service Description" rows="2" style="margin-top: 5px; resize: vertical;"><?php echo $item['service_description']; ?></textarea>
                        </div>
                        <div class="col-md-2"><input type="number" class="form-control s_qty" placeholder="Qty" value="<?php echo $item['quantity']; ?>"></div>
                        <div class="col-md-2"><input type="number" class="form-control s_rate" placeholder="Rate" value="<?php echo $item['rate']; ?>"></div>
                        <div class="col-md-3"><input type="number" class="form-control s_amt" placeholder="Amount" value="<?php echo $item['amount']; ?>"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-danger remove_row"><i class="fa fa-trash"></i></button></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="addRow" class="btn btn-secondary btn-sm mt-2">+ Add Row</button>
            
            <div class="gst-breakup">
                <h6>Tax Breakup</h6>
                <div class="gst-row">
                    <span class="gst-label">Subtotal:</span>
                    <span class="gst-value" id="subtotal_display">₹ 0.00</span>
                </div>
                <div class="gst-row">
                    <span class="gst-label">GST (18%):</span>
                    <span class="gst-value" id="gst_display">₹ 0.00</span>
                </div>
                <div class="gst-row">
                    <span class="gst-label">Grand Total:</span>
                    <span class="gst-value" id="grand_total_display">₹ 0.00</span>
                </div>
            </div>
        </div>

        <div class="text-end">
            <a href="view_quotations.php" class="btn btn-secondary mt-3">Cancel</a>
            <button type="submit" name="update_quotation" class="btn btn-custom mt-3">Update Quotation</button>
        </div>
    </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#customer_select').select2();
    calc(); // Run calculation on load

    $('#customer_select').on('change', function() {
        let opt = $(this).find(':selected');
        $('#c_name').val(opt.data('name'));
        $('#c_phone').val(opt.data('phone'));
        $('#c_addr').val(opt.data('addr'));
        $('#c_state').val(opt.data('state'));
    });

    $('#addRow').click(function() {
        let row = `
        <div class="service-row">
            <div class="row">
                <div class="col-md-4">
                    <input type="text" class="form-control s_name" placeholder="Service Name" required>
                    <textarea class="form-control s_desc" placeholder="Service Description" rows="2" style="margin-top: 5px; resize: vertical;"></textarea>
                </div>
                <div class="col-md-2"><input type="number" class="form-control s_qty" placeholder="Qty"></div>
                <div class="col-md-2"><input type="number" class="form-control s_rate" placeholder="Rate"></div>
                <div class="col-md-3"><input type="number" class="form-control s_amt" placeholder="Amount"></div>
                <div class="col-md-1"><button type="button" class="btn btn-danger remove_row"><i class="fa fa-trash"></i></button></div>
            </div>
        </div>`;
        $('#service_container').append(row);
    });

    $(document).on('click', '.remove_row', function() {
        if($('.service-row').length > 1) $(this).closest('.service-row').remove();
        calc();
    });

    $(document).on('input', '.s_qty, .s_rate', function() {
        let row = $(this).closest('.service-row');
        let q = parseFloat(row.find('.s_qty').val()) || 0;
        let r = parseFloat(row.find('.s_rate').val()) || 0;
        if (q > 0 && r > 0) row.find('.s_amt').val((q * r).toFixed(2));
        calc();
    });

    $(document).on('input', '.s_amt', function() { calc(); });

    function calc() {
        let total = 0;
        let services = [];
        $('.service-row').each(function() {
            let amt = parseFloat($(this).find('.s_amt').val()) || 0;
            total += amt;
            services.push({
                service_name: $(this).find('.s_name').val(),
                service_description: $(this).find('.s_desc').val(),
                quantity: $(this).find('.s_qty').val(),
                rate: $(this).find('.s_rate').val(),
                amount: amt
            });
        });
        
        let gst = total * 0.18;
        let grand_total = total + gst;
        
        $('#subtotal_display').text('₹ ' + total.toFixed(2));
        $('#gst_display').text('₹ ' + gst.toFixed(2));
        $('#grand_total_display').text('₹ ' + grand_total.toFixed(2));
        $('#services_json').val(JSON.stringify(services));
    }

    $('#quotationForm').submit(function() { calc(); });
});
</script>