<?php
require_once 'config.php';
if (session_status() == PHP_SESSION_NONE) session_start();
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

include('layout.php');

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) {
    header('Location: service_invoice.php');
    exit();
}

$page_title = "Edit Service Invoice";
$success_message = '';
$error_message = '';
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = (int)$_SESSION['user_id'];

// Fetch Company State for GST logic
try {
    $stmt = $conn->prepare("SELECT state FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $company_state = $company['state'] ?? '';
    $stmt->close();
} catch (Exception $e) { $company_state = ''; }

// Handle Update Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_service_invoice'])) {
    try {
        $conn->begin_transaction();

        $customer_id = (int)$_POST['customer_id'];
        $services = json_decode($_POST['services_json'], true);
        
        // Calculate Totals
        $subtotal = 0; $total_discount = 0; $total_gst = 0; $grand_total = 0;
        foreach ($services as $s) {
            $amt = floatval($s['amount']);
            $disc = ($s['discount_type'] === 'percentage') ? ($amt * (floatval($s['discount']) / 100)) : floatval($s['discount']);
            $after_disc = $amt - $disc;
            $gst = (strtolower($s['customer_state']) === strtolower($company_state)) ? ($after_disc * 0.18) : ($after_disc * 0.18);
            
            $subtotal += $amt;
            $total_discount += $disc;
            $total_gst += $gst;
            $grand_total += ($after_disc + $gst);
        }

        // Update Main Table
        $stmt = $conn->prepare("UPDATE service_invoices SET 
            invoice_no=?, invoice_date=?, delivery_note=?, payment_terms=?, reference_no=?, 
            buyer_order_no=?, dated=?, dispatch_doc_no=?, delivery_note_date=?, dispatched_through=?, 
            destination=?, terms_of_delivery=?, customer_id=?, customer_name=?, customer_phone=?, 
            customer_address=?, shipping_address=?, subtotal=?, discount_amount=?, gst_amount=?, 
            total_amount=?, payment_method=?, paid_amount=? WHERE id=?");

        $stmt->bind_param("ssssssssssssissssddddsdi", 
            $_POST['invoice_no'], $_POST['invoice_date'], $_POST['delivery_note'], $_POST['payment_terms'], $_POST['reference_no'], 
            $_POST['buyer_order_no'], $_POST['dated'], $_POST['dispatch_doc_no'], $_POST['delivery_note_date'], $_POST['dispatched_through'], 
            $_POST['destination'], $_POST['terms_of_delivery'], $customer_id, $_POST['customer_name'], $_POST['customer_phone'], 
            $_POST['customer_address'], $_POST['shipping_address'], $subtotal, $total_discount, $total_gst, 
            $grand_total, $_POST['payment_method'], $_POST['paid_amount'], $invoice_id);
        
        $stmt->execute();
        $stmt->close();

        // Refresh Items
        $conn->query("DELETE FROM service_invoice_items WHERE invoice_id = $invoice_id");
        $stmt = $conn->prepare("INSERT INTO service_invoice_items (invoice_id, service_name, service_description, sac_code, quantity, unit, rate, amount, discount_type, discount_value, customer_state, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        foreach ($services as $s) {
            $stmt->bind_param("isssdsdssss", $invoice_id, $s['service_name'], $s['service_description'], $s['sac_code'], $s['quantity'], $s['unit'], $s['rate'], $s['amount'], $s['discount_type'], $s['discount'], $s['customer_state']);
            $stmt->execute();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Invoice updated successfully!";
        header("Location: service_invoice.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// Fetch existing data
$invoice = $conn->query("SELECT * FROM service_invoices WHERE id = $invoice_id")->fetch_assoc();
$items = $conn->query("SELECT * FROM service_invoice_items WHERE invoice_id = $invoice_id")->fetch_all(MYSQLI_ASSOC);
$customers = $conn->query("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<style>
    /* Color theme matched to service_invoice.php (black / blue / white) */
    :root {
        --primary: #0284c7; --primary-light: #0ea5e9; --success: #22c55e; --danger: #ef4444;
        --text-dark: #0b1220; --bg-main: #f4f8fb; --card-bg: #ffffff; --border: #e2e8f0;
        --radius: 8px; --shadow: 0 4px 12px rgba(2,132,199,0.08);
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-dark); font-size: 14.2px; }
    .content { margin-top: 65px; margin-left: 260px; padding: 20px; min-height: calc(100vh - 65px); transition: margin-left 0.3s ease; }
    @media (max-width: 1024px) { .content { margin-left: 0; } }
    .page-header { background: linear-gradient(135deg, var(--text-dark), var(--primary) 55%, var(--primary-light)); color: white; padding: 18px 24px; border-radius: var(--radius); margin-bottom: 20px; box-shadow: var(--shadow); }
    .card { background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 20px; padding: 20px; }
    .form-label { font-weight: 600; margin-top: 10px; margin-left: 5px; display: block; }
    .form-control { border: 2px solid var(--border); border-radius: 6px; padding: 10px; width: 100%; }
    .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12); }
    .service-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 60px; gap: 8px; background: #f8fafc; padding: 14px; border-radius: var(--radius); border: 1px solid var(--border); margin-bottom: 12px; align-items: end; }
    .btn-custom { background: linear-gradient(135deg, var(--text-dark), var(--primary)); color: white; border: 2px solid rgba(255,255,255,0.85); padding: 12px 26px; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
    #addServiceInvoiceModal .select2-container--default .select2-selection--single { height: 38px; border: 1px solid #ced4da; border-radius: 6px; display: flex; align-items: center; }
    @media (max-width: 768px) { .service-row { grid-template-columns: 1fr; } }
</style>

<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>Edit Service Invoice</h4>
            <h6>Modify details for <?php echo htmlspecialchars($invoice['invoice_no']); ?></h6>
        </div>
        <a href="service_invoice.php" class="btn-custom" style="padding: 8px 15px; font-size: 13px;">Back to List</a>
    </div>

    <form id="editServiceInvoiceForm" method="post">
        <input type="hidden" name="services_json" id="services_json">
        
        <div class="card">
            <h5 class="mb-3">Invoice Details</h5>
            <div class="row">
                <div class="col-md-3"><label class="form-label">Invoice No.</label><input type="text" name="invoice_no" class="form-control" value="<?php echo $invoice['invoice_no']; ?>" required></div>
                <div class="col-md-3"><label class="form-label">Invoice Date</label><input type="date" name="invoice_date" class="form-control" value="<?php echo $invoice['invoice_date']; ?>"></div>
                <div class="col-md-6"><label class="form-label">Delivery Note</label><input type="text" name="delivery_note" class="form-control" value="<?php echo $invoice['delivery_note']; ?>"></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-4"><label class="form-label">Mode/Terms of Payment</label><input type="text" name="payment_terms" class="form-control" value="<?php echo $invoice['payment_terms']; ?>"></div>
                <div class="col-md-4"><label class="form-label">Reference No.</label><input type="text" name="reference_no" class="form-control" value="<?php echo $invoice['reference_no']; ?>"></div>
                <div class="col-md-4"><label class="form-label">Buyer's Order No.</label><input type="text" name="buyer_order_no" class="form-control" value="<?php echo $invoice['buyer_order_no']; ?>"></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-3"><label class="form-label">Dated</label><input type="date" name="dated" class="form-control" value="<?php echo $invoice['dated']; ?>"></div>
                <div class="col-md-3"><label class="form-label">Dispatch Doc No.</label><input type="text" name="dispatch_doc_no" class="form-control" value="<?php echo $invoice['dispatch_doc_no']; ?>"></div>
                <div class="col-md-3"><label class="form-label">Delivery Note Date</label><input type="date" name="delivery_note_date" class="form-control" value="<?php echo $invoice['delivery_note_date']; ?>"></div>
                <div class="col-md-3"><label class="form-label">Dispatched through</label><input type="text" name="dispatched_through" class="form-control" value="<?php echo $invoice['dispatched_through']; ?>"></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6"><label class="form-label">Destination</label><input type="text" name="destination" class="form-control" value="<?php echo $invoice['destination']; ?>"></div>
                <div class="col-md-6"><label class="form-label">Terms of Delivery</label><textarea name="terms_of_delivery" class="form-control" rows="2"><?php echo $invoice['terms_of_delivery']; ?></textarea></div>
            </div>
        </div>

        <div class="card">
            <h5 class="mb-3">Customer Details</h5>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Select Customer</label>
                    <select class="form-control" id="customer_id" name="customer_id" required>
                        <?php foreach($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($invoice['customer_id'] == $c['id']) ? 'selected' : ''; ?> data-name="<?php echo $c['name']; ?>" data-phone="<?php echo $c['phone']; ?>" data-address="<?php echo $c['address']; ?>" data-state="<?php echo $c['state']; ?>"><?php echo $c['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Customer Name</label><input type="text" id="customer_name" name="customer_name" class="form-control" value="<?php echo $invoice['customer_name']; ?>" readonly></div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6"><label class="form-label">Customer Phone</label><input type="text" id="customer_phone" name="customer_phone" class="form-control" value="<?php echo $invoice['customer_phone']; ?>" readonly></div>
                <div class="col-md-6"><label class="form-label">Customer State</label><input type="text" id="customer_state" name="customer_state" class="form-control" value="<?php echo $invoice['customer_address']; ?>" readonly></div>
            </div>
            <div class="mt-2"><label class="form-label">Billing Address</label><textarea id="customer_address" name="customer_address" class="form-control" readonly><?php echo $invoice['customer_address']; ?></textarea></div>
            <div class="mt-2"><label class="form-label">Shipping Address</label><textarea name="shipping_address" class="form-control" required><?php echo $invoice['shipping_address']; ?></textarea></div>
        </div>

        <div class="card">
            <h5 class="mb-3">Service Details</h5>
            <div id="service-list">
                <?php foreach($items as $idx => $item): ?>
                <div class="service-row">
                    <div class="form-group"><label class="form-label">Service Name</label><input type="text" class="form-control service-name" value="<?php echo $item['service_name']; ?>" required></div>
                    <div class="form-group"><label class="form-label">SAC Code</label><input type="text" class="form-control sac-code" value="<?php echo $item['sac_code']; ?>"></div>
                    <div class="form-group"><label class="form-label">Qty</label><input type="number" class="form-control quantity" step="0.01" value="<?php echo $item['quantity']; ?>"></div>
                    <div class="form-group"><label class="form-label">Unit</label><input type="text" class="form-control unit" value="<?php echo $item['unit']; ?>"></div>
                    <div class="form-group"><label class="form-label">Rate</label><input type="number" class="form-control rate" step="0.01" value="<?php echo $item['rate']; ?>"></div>
                    <div class="form-group"><label class="form-label">Amount</label><input type="number" class="form-control amount" step="0.01" value="<?php echo $item['amount']; ?>" required></div>
                    <button type="button" class="btn btn-danger btn-sm remove-service"><i class="fas fa-trash"></i></button>
                    <div class="form-group" style="grid-column: span 6;"><textarea class="form-control service-description" placeholder="Description"><?php echo $item['service_description']; ?></textarea></div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" id="addService">+ Add Service</button>
            <div class="row mt-4">
                <div class="col-md-3"><label class="form-label">Discount Type</label><select id="discount_type" class="form-control"><option value="amount" <?php if($items[0]['discount_type']=='amount') echo 'selected';?>>Amount</option><option value="percentage" <?php if($items[0]['discount_type']=='percentage') echo 'selected';?>>Percentage</option></select></div>
                <div class="col-md-3"><label class="form-label">Discount Value</label><input type="number" id="discount_value" class="form-control" value="<?php echo $items[0]['discount_value']; ?>"></div>
                <div class="col-md-6 text-end"><div id="total_display" style="font-weight:700; font-size:20px; color:var(--primary);">Rs. 0.00</div></div>
            </div>
        </div>

        <div class="card">
            <h5 class="mb-3">Payment</h5>
            <div class="row">
                <div class="col-md-6"><label class="form-label">Method</label><select name="payment_method" class="form-control"><option value="Cash" <?php if($invoice['payment_method']=='Cash')echo 'selected';?>>Cash</option><option value="Upi" <?php if($invoice['payment_method']=='Upi')echo 'selected';?>>UPI</option></select></div>
                <div class="col-md-6"><label class="form-label">Paid Amount</label><input type="number" name="paid_amount" id="paid_amount" class="form-control" value="<?php echo $invoice['paid_amount']; ?>"></div>
            </div>
        </div>

        <div class="text-center pb-5"><button type="submit" name="update_service_invoice" class="btn-custom">Update Service Invoice</button></div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#customer_id').select2();

    $('#customer_id').on('change', function() {
        const opt = $(this).find(':selected');
        $('#customer_name').val(opt.data('name'));
        $('#customer_phone').val(opt.data('phone'));
        $('#customer_address').val(opt.data('address'));
        $('#customer_state').val(opt.data('state'));
        calculateTotal();
    });

    $('#addService').on('click', function() {
        const row = `<div class="service-row">
            <div class="form-group"><label class="form-label">Service Name</label><input type="text" class="form-control service-name" required></div>
            <div class="form-group"><label class="form-label">SAC Code</label><input type="text" class="form-control sac-code"></div>
            <div class="form-group"><label class="form-label">Qty</label><input type="number" class="form-control quantity" step="0.01"></div>
            <div class="form-group"><label class="form-label">Unit</label><input type="text" class="form-control unit"></div>
            <div class="form-group"><label class="form-label">Rate</label><input type="number" class="form-control rate" step="0.01"></div>
            <div class="form-group"><label class="form-label">Amount</label><input type="number" class="form-control amount" step="0.01" required></div>
            <button type="button" class="btn btn-danger btn-sm remove-service"><i class="fas fa-trash"></i></button>
            <div class="form-group" style="grid-column: span 6;"><textarea class="form-control service-description" placeholder="Description"></textarea></div>
        </div>`;
        $('#service-list').append(row);
    });

    $(document).on('click', '.remove-service', function() { $(this).closest('.service-row').remove(); calculateTotal(); });

    function calculateTotal() {
        let sub = 0;
        $('.service-row').each(function() {
            const q = parseFloat($(this).find('.quantity').val()) || 0;
            const r = parseFloat($(this).find('.rate').val()) || 0;
            if(q && r) $(this).find('.amount').val((q * r).toFixed(2));
            sub += parseFloat($(this).find('.amount').val()) || 0;
        });

        const dt = $('#discount_type').val();
        const dv = parseFloat($('#discount_value').val()) || 0;
        const da = (dt === 'percentage') ? (sub * (dv/100)) : dv;
        const total = (sub - da) + ((sub - da) * 0.18);
        $('#total_display').text('Grand Total: Rs. ' + total.toFixed(2));
    }

    $(document).on('input', '.quantity, .rate, .amount, #discount_value', calculateTotal);
    $('#discount_type').on('change', calculateTotal);

    $('#editServiceInvoiceForm').on('submit', function() {
        const services = [];
        $('.service-row').each(function() {
            services.push({
                service_name: $(this).find('.service-name').val(),
                service_description: $(this).find('.service-description').val(),
                sac_code: $(this).find('.sac-code').val(),
                quantity: $(this).find('.quantity').val(),
                unit: $(this).find('.unit').val(),
                rate: $(this).find('.rate').val(),
                amount: $(this).find('.amount').val(),
                discount_type: $('#discount_type').val(),
                discount: $('#discount_value').val(),
                customer_state: $('#customer_state').val()
            });
        });
        $('#services_json').val(JSON.stringify(services));
    });
    calculateTotal();
});
</script>