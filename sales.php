<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_start();
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ---- TERMS AJAX (must run before layout.php) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'manage_terms') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    try {
        if (!isset($_SESSION['user_id'])) throw new Exception("Session expired, login again.");
        $uid = (int)$_SESSION['user_id'];

        $conn->query("CREATE TABLE IF NOT EXISTS terms_conditions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(100) NOT NULL,
            content TEXT NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $op = $_POST['operation'] ?? '';
        if ($op === 'fetch') {
            $res = $conn->query("SELECT id, title, content FROM terms_conditions ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['status'=>'success','data'=>$res]);
        } elseif ($op === 'add') {
            $title = trim($_POST['title'] ?? ''); $content = trim($_POST['content'] ?? '');
            if (!$title || !$content) throw new Exception("Title and content required.");
            $stmt = $conn->prepare("INSERT INTO terms_conditions (title, content, created_by) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $title, $content, $uid);
            $stmt->execute();
            echo json_encode(['status'=>'success','id'=>$stmt->insert_id]);
        } elseif ($op === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? ''); $content = trim($_POST['content'] ?? '');
            if (!$id || !$title || !$content) throw new Exception("Invalid data.");
            $stmt = $conn->prepare("UPDATE terms_conditions SET title=?, content=? WHERE id=?");
            $stmt->bind_param("ssi", $title, $content, $id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
        } elseif ($op === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM terms_conditions WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['status'=>'success']);
        } else throw new Exception("Invalid operation.");
    } catch (Throwable $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}
// -------------------------------------------------

include('layout.php');// Ensure tables exist
try {
    $conn->query("CREATE TABLE IF NOT EXISTS received_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        customer_address TEXT,
        total_amount DECIMAL(12,2) NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

try { $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS invoice_id INT DEFAULT NULL"); } catch (Exception $e) {}
try { $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS transaction_type VARCHAR(20) DEFAULT 'sale'"); } catch (Exception $e) {}
try { $conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS tds_percent DECIMAL(5,2) DEFAULT 0.00, ADD COLUMN IF NOT EXISTS tds_amount DECIMAL(10,2) DEFAULT 0.00"); } catch (Exception $e) {}
try { $conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS tds_percent DECIMAL(5,2) DEFAULT 0.00, ADD COLUMN IF NOT EXISTS tds_amount DECIMAL(10,2) DEFAULT 0.00, ADD COLUMN IF NOT EXISTS invoice_no VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
try { $conn->query("ALTER TABLE invoices ADD COLUMN IF NOT EXISTS terms_text TEXT DEFAULT NULL"); } catch (Exception $e) {}
try {
    $conn->query("CREATE TABLE IF NOT EXISTS terms_conditions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}


require_once 'includes/company_functions.php';
$next_sales_invoice_no = getUnifiedNextInvoiceNumber($conn, 'INV');

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$page_title      = "Sales";
$success_message = $_SESSION['success_message'] ?? '';
$error_message   = '';
$user_type       = $_SESSION['user_type'] ?? 'admin';
$created_by_id   = (int)$_SESSION['user_id'];
$base_url        = "https://growbiller.com/";
$invoice_dir     = __DIR__ . "/invoices/";
$invoice_url     = $base_url . "invoices/";

if ($success_message) unset($_SESSION['success_message']);
if (!is_dir($invoice_dir)) mkdir($invoice_dir, 0755, true);

// Fetch company details
try {
    $stmt = $conn->prepare("SELECT * FROM company_details LIMIT 1");
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$company) $company = ['company_name'=>'Your Company','company_address'=>'','company_gst'=>'','company_logo'=>'','contact_name'=>'','email'=>'','phone'=>''];
    else {
        if ($company['company_logo'])
            $company['company_logo'] = str_starts_with($company['company_logo'], 'http') ? $company['company_logo'] : $base_url . ltrim($company['company_logo'], '/');
    }
} catch (Exception $e) {
    $company = ['company_name'=>'Your Company','company_address'=>'','company_gst'=>'','company_logo'=>'','contact_name'=>'','email'=>'','phone'=>''];
}

// ── HANDLE POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_sale'])) {
            $customer_name    = trim($_POST['customer_name'] ?? '');
            $customer_phone   = trim($_POST['customer_phone'] ?? '');
            $customer_address = trim($_POST['customer_address'] ?? '');
            $products         = json_decode($_POST['products_json'] ?? '[]', true);
            $payment_method   = trim($_POST['payment_method'] ?? '');
            $paid_amount      = floatval($_POST['paid_amount'] ?? 0);

            if (empty($customer_name) || empty($products))
                throw new Exception("Customer name and at least one product are required.");
            if (!empty($customer_phone) && !preg_match('/^\+?[1-9]\d{9,14}$/', $customer_phone))
                throw new Exception("Invalid phone number format (e.g., +919876543210).");

            $line_items = []; $invoice_total = 0;
            foreach ($products as $prod) {
                $product_id = (int)($prod['product_id'] ?? 0);
                $quantity   = (int)($prod['quantity'] ?? 0);
                if (!$product_id || $quantity <= 0) throw new Exception("Invalid product or quantity.");
                $stmt = $conn->prepare("SELECT id, product_name, quantity, sell_price AS price_per_piece, gst_percentage, COALESCE(sgst,0) AS sgst, COALESCE(cgst,0) AS cgst, COALESCE(igst,0) AS igst FROM products WHERE id = ?");
                $stmt->bind_param("i", $product_id); $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc(); $stmt->close();
                if (!$product) throw new Exception("Selected product not found.");
                if ($product['quantity'] < $quantity) throw new Exception("Insufficient stock for {$product['product_name']}.");

                $price_incl_gst   = (float)$product['price_per_piece'];
                $gst_percent      = (float)($product['gst_percentage'] ?? 0);
                $sgst_rate        = (float)($product['sgst'] ?? 0);
                $cgst_rate        = (float)($product['cgst'] ?? 0);
                $igst_rate        = (float)($product['igst'] ?? 0);
                
                // Auto-calculate if rates are missing
                if ($gst_percent > 0 && $sgst_rate == 0 && $cgst_rate == 0 && $igst_rate == 0) {
                    $sgst_rate = $gst_percent / 2;
                    $cgst_rate = $gst_percent / 2;
                    $igst_rate = 0;
                }
                
                $tax_rate         = $sgst_rate + $cgst_rate + $igst_rate;
                $price_excl_gst   = $tax_rate > 0 ? $price_incl_gst / (1 + $tax_rate / 100) : $price_incl_gst;
                $discount_percent = (float)($prod['discount'] ?? 0);
                $discount_amount  = $price_excl_gst * $quantity * ($discount_percent / 100);
                $taxable_amount   = $price_excl_gst * $quantity - $discount_amount;
                $sgst_amt         = $taxable_amount * ($sgst_rate / 100);
                $cgst_amt         = $taxable_amount * ($cgst_rate / 100);
                $igst_amt         = $taxable_amount * ($igst_rate / 100);
                $tax_amount       = $sgst_amt + $cgst_amt + $igst_amt;
                $line_total       = $taxable_amount + $tax_amount;
                $invoice_total   += $line_total;
                $line_items[] = [
                    'product_id'       => $product_id,
                    'quantity'         => $quantity,
                    'discount_percent' => $discount_percent,
                    'discount_amount'  => $discount_amount,
                    'line_total'       => $line_total,
                    'price_per_piece'  => $price_incl_gst,
                    'price_excl_gst'   => $price_excl_gst,
                    'sgst_amt'         => $sgst_amt,
                    'cgst_amt'         => $cgst_amt,
                    'igst_amt'         => $igst_amt,
                    'tax_amount'       => $tax_amount,
                    'tax_rate'         => $tax_rate,
                    'sgst_rate'        => $sgst_rate,
                    'cgst_rate'        => $cgst_rate,
                    'igst_rate'        => $igst_rate,
                    'transaction_type' => $prod['transaction_type'] ?? 'sale',
                    'serial_numbers'   => $prod['serial_numbers'] ?? '',
                ];
            }

            $tds_percent = floatval($_POST['tds_percent'] ?? 0);
            $tds_amount  = $invoice_total * ($tds_percent / 100);
            $final_payable = $invoice_total - $tds_amount;
            $invoice_no    = trim($_POST['invoice_no'] ?? '');
            if (empty($invoice_no)) {
                $invoice_no = getUnifiedNextInvoiceNumber($conn, 'INV');
            }

            $terms_text    = trim($_POST['terms_text'] ?? '');
            
            $conn->begin_transaction();
            $invStmt = $conn->prepare("INSERT INTO invoices (customer_name, customer_phone, customer_address, total_amount, tds_percent, tds_amount, invoice_no, terms_text, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $invStmt->bind_param("sssdddssi", $customer_name, $customer_phone, $customer_address, $final_payable, $tds_percent, $tds_amount, $invoice_no, $terms_text, $created_by_id);
            if (!$invStmt->execute()) throw new Exception("Invoice insert failed: " . $invStmt->error);
            $invoice_id = $invStmt->insert_id; $invStmt->close();


            foreach ($line_items as $item) {
                $ttype = $item['transaction_type'] ?? 'sale';
                $stmt = $conn->prepare("INSERT INTO sales (invoice_id, customer_name, customer_phone, customer_address, product_id, quantity, transaction_type, price_per_piece, discount_percent, discount_amount, total_price, created_by, created_at, serial_numbers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->bind_param("isssiisddddis", $invoice_id, $customer_name, $customer_phone, $customer_address, $item['product_id'], $item['quantity'], $ttype, $item['price_per_piece'], $item['discount_percent'], $item['discount_amount'], $item['line_total'], $created_by_id, $item['serial_numbers']);
                if (!$stmt->execute()) throw new Exception("Sale insert failed: " . $stmt->error); $stmt->close();
                
                // Point 6: Deduct stock ONLY if item is for 'sale'. If item is left for 'rent', keep inventory quantity unchanged!
                if ($ttype === 'sale') {
                    $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    if (!$stmt->execute()) throw new Exception("Stock update failed."); $stmt->close();
                }

                if (!empty($item['serial_numbers'])) {
                    $slist = array_filter(array_map('trim', explode(',', $item['serial_numbers'])));
                    foreach ($slist as $sn) {
                        $sn = $conn->real_escape_string($sn);
                        $status = ($ttype === 'sale') ? 'sold' : 'rented';
                        $conn->query("UPDATE product_serials SET status='$status', invoice_id=$invoice_id WHERE product_id={$item['product_id']} AND serial_number='$sn'");
                    }
                }
            }


            if ($paid_amount > 0 && $invoice_id) {
                $stmt = $conn->prepare("INSERT INTO received_payments (invoice_id, payment_method, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("isdi", $invoice_id, $payment_method, $paid_amount, $created_by_id);
                if (!$stmt->execute()) throw new Exception("Recording payment failed."); $stmt->close();
            }

            $conn->commit();
            $_SESSION['success_message'] = "Sale recorded successfully. Invoice #" . $invoice_id;
            header("Location: sales.php"); exit();

        } elseif (isset($_POST['edit_sale'])) {
            $invoice_id       = (int)($_POST['invoice_id'] ?? 0);
            $customer_name    = trim($_POST['customer_name'] ?? '');
            $customer_phone   = trim($_POST['customer_phone'] ?? '');
            $customer_address = trim($_POST['customer_address'] ?? '');
            $products         = json_decode($_POST['products_json'] ?? '[]', true);
            $payment_method   = trim($_POST['payment_method'] ?? '');
            $paid_amount      = floatval($_POST['paid_amount'] ?? 0);

            if (empty($customer_name)||empty($products)||!$invoice_id)
                throw new Exception("All required fields must be filled.");
            if (!empty($customer_phone) && !preg_match('/^\+?[1-9]\d{9,14}$/', $customer_phone))
                throw new Exception("Invalid phone number format.");

            if ($user_type === 'admin') { $istmt = $conn->prepare("SELECT id FROM invoices WHERE id = ?"); $istmt->bind_param("i", $invoice_id); }
            else { $istmt = $conn->prepare("SELECT id FROM invoices WHERE id = ? AND created_by = ?"); $istmt->bind_param("ii", $invoice_id, $created_by_id); }
            $istmt->execute(); $invRow = $istmt->get_result()->fetch_assoc(); $istmt->close();
            if (!$invRow) throw new Exception("Invoice not found or no permission.");

            $stmt = $conn->prepare("SELECT id, product_id, quantity FROM sales WHERE invoice_id = ?");
            $stmt->bind_param("i", $invoice_id); $stmt->execute();
            $existing_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $existing_qty = [];
            foreach ($existing_sales as $es) $existing_qty[$es['product_id']] = ($existing_qty[$es['product_id']] ?? 0) + $es['quantity'];

            $line_items = []; $invoice_total = 0;
            foreach ($products as $prod) {
                $product_id = (int)($prod['product_id'] ?? 0); $quantity = (int)($prod['quantity'] ?? 0);
                if (!$product_id || $quantity <= 0) throw new Exception("Invalid product or quantity.");
                $pstmt = $conn->prepare("SELECT id, product_name, quantity, sell_price AS price_per_piece, gst_percentage, COALESCE(sgst,0) AS sgst, COALESCE(cgst,0) AS cgst, COALESCE(igst,0) AS igst FROM products WHERE id = ?");
                $pstmt->bind_param("i", $product_id); $pstmt->execute();
                $product = $pstmt->get_result()->fetch_assoc(); $pstmt->close();
                if (!$product) throw new Exception("Product not found.");
                $avail = $product['quantity'] + ($existing_qty[$product_id] ?? 0);
                if ($avail < $quantity) throw new Exception("Insufficient stock for {$product['product_name']}.");

                $price_incl_gst   = (float)$product['price_per_piece'];
                $gst_percent      = (float)($product['gst_percentage'] ?? 0);
                $sgst_rate        = (float)($product['sgst'] ?? 0);
                $cgst_rate        = (float)($product['cgst'] ?? 0);
                $igst_rate        = (float)($product['igst'] ?? 0);
                
                if ($gst_percent > 0 && $sgst_rate == 0 && $cgst_rate == 0 && $igst_rate == 0) {
                    $sgst_rate = $gst_percent / 2;
                    $cgst_rate = $gst_percent / 2;
                    $igst_rate = 0;
                }
                
                $tax_rate         = $sgst_rate + $cgst_rate + $igst_rate;
                $price_excl_gst   = $tax_rate > 0 ? $price_incl_gst / (1 + $tax_rate / 100) : $price_incl_gst;
                $discount_percent = (float)($prod['discount'] ?? 0);
                $discount_amount  = $price_excl_gst * $quantity * ($discount_percent / 100);
                $taxable_amount   = $price_excl_gst * $quantity - $discount_amount;
                $sgst_amt         = $taxable_amount * ($sgst_rate / 100);
                $cgst_amt         = $taxable_amount * ($cgst_rate / 100);
                $igst_amt         = $taxable_amount * ($igst_rate / 100);
                $tax_amount       = $sgst_amt + $cgst_amt + $igst_amt;
                $line_total       = $taxable_amount + $tax_amount;
                $invoice_total   += $line_total;
                $line_items[]     = ['product_id'=>$product_id,'quantity'=>$quantity,'discount_percent'=>$discount_percent,'discount_amount'=>$discount_amount,'line_total'=>$line_total,'price_per_piece'=>$price_incl_gst,'tax_rate'=>$tax_rate,'sgst_rate'=>$sgst_rate,'cgst_rate'=>$cgst_rate,'igst_rate'=>$igst_rate];
            }

            $terms_text    = trim($_POST['terms_text'] ?? '');
            
            $conn->begin_transaction();
            $ustmt = $conn->prepare("UPDATE invoices SET customer_name=?, customer_phone=?, customer_address=?, total_amount=?, terms_text=? WHERE id=?");
            $ustmt->bind_param("sssdsi", $customer_name, $customer_phone, $customer_address, $invoice_total, $terms_text, $invoice_id);
            if (!$ustmt->execute()) throw new Exception("Invoice update failed."); $ustmt->close();

            foreach ($existing_sales as $es) {
                $rstmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                $rstmt->bind_param("ii", $es['quantity'], $es['product_id']);
                if (!$rstmt->execute()) throw new Exception("Stock restore failed."); $rstmt->close();
            }
            $dstmt = $conn->prepare("DELETE FROM sales WHERE invoice_id = ?");
            $dstmt->bind_param("i", $invoice_id);
            if (!$dstmt->execute()) throw new Exception("Failed to remove old sales."); $dstmt->close();

            foreach ($line_items as $item) {
                $istmt2 = $conn->prepare("INSERT INTO sales (invoice_id, customer_name, customer_phone, customer_address, product_id, quantity, price_per_piece, discount_percent, discount_amount, total_price, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $istmt2->bind_param("isssiidddii", $invoice_id, $customer_name, $customer_phone, $customer_address, $item['product_id'], $item['quantity'], $item['price_per_piece'], $item['discount_percent'], $item['discount_amount'], $item['line_total'], $created_by_id);
                if (!$istmt2->execute()) throw new Exception("Sale insert failed."); $istmt2->close();
                $ust = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $ust->bind_param("ii", $item['quantity'], $item['product_id']);
                if (!$ust->execute()) throw new Exception("Stock update failed."); $ust->close();
            }

            $checkStmt = $conn->prepare("SELECT id, amount FROM received_payments WHERE invoice_id = ? ORDER BY created_at DESC LIMIT 1");
            $checkStmt->bind_param("i", $invoice_id); $checkStmt->execute();
            $existingPayment = $checkStmt->get_result()->fetch_assoc(); $checkStmt->close();

            if ($paid_amount > 0) {
                if ($existingPayment) {
                    $pstmt = $conn->prepare("UPDATE received_payments SET payment_method=?, amount=? WHERE id=?");
                    $pstmt->bind_param("sdi", $payment_method, $paid_amount, $existingPayment['id']);
                } else {
                    $pstmt = $conn->prepare("INSERT INTO received_payments (invoice_id, payment_method, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $pstmt->bind_param("isdi", $invoice_id, $payment_method, $paid_amount, $created_by_id);
                }
                if (!$pstmt->execute()) throw new Exception("Updating payment failed."); $pstmt->close();
            } elseif ($existingPayment) {
                $pstmt = $conn->prepare("DELETE FROM received_payments WHERE id = ?");
                $pstmt->bind_param("i", $existingPayment['id']);
                if (!$pstmt->execute()) throw new Exception("Failed to remove payment."); $pstmt->close();
            }

            $conn->commit();
            $_SESSION['success_message'] = "Sale updated successfully.";
            header("Location: sales.php"); exit();

        } elseif (isset($_POST['delete_sale']) && $user_type === 'admin') {
            $sale_id = (int)($_POST['sale_id'] ?? 0);
            if (!$sale_id) throw new Exception("Invalid sale ID.");
            $stmt = $conn->prepare("SELECT product_id, quantity FROM sales WHERE id = ?");
            $stmt->bind_param("i", $sale_id); $stmt->execute();
            $sale = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$sale) throw new Exception("Sale not found.");
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param("ii", $sale['quantity'], $sale['product_id']);
            if (!$stmt->execute()) throw new Exception("Stock restore failed."); $stmt->close();
            $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
            $stmt->bind_param("i", $sale_id);
            if (!$stmt->execute()) throw new Exception("Sale delete failed."); $stmt->close();
            $conn->commit();
            $_SESSION['success_message'] = "Sale deleted successfully.";
            header("Location: sales.php"); exit();

        } elseif (isset($_POST['add_payment'])) {
            $invoice_id    = (int)($_POST['invoice_id'] ?? 0);
            $pay_method    = trim($_POST['pay_method'] ?? '');
            $pay_amount    = floatval($_POST['pay_amount'] ?? 0);
            if (!$invoice_id || $pay_amount <= 0) throw new Exception("Invalid payment details.");
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO received_payments (invoice_id, payment_method, amount, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isdi", $invoice_id, $pay_method, $pay_amount, $created_by_id);
            if (!$stmt->execute()) throw new Exception("Payment recording failed."); $stmt->close();
            $conn->commit();
            $_SESSION['success_message'] = "Payment of ₹" . number_format($pay_amount, 2) . " recorded successfully.";
            header("Location: sales.php"); exit();
        }
    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        $error_message = $e->getMessage();
        error_log("Sale Error: $error_message");
    }
}

// ── FETCH SALES ──────────────────────────────────────────────────────────────
try {
    if ($user_type === 'admin') {
        $stmt = $conn->prepare("
            SELECT s.id, s.invoice_id, inv.invoice_no, inv.terms_text, s.customer_name, s.customer_phone, s.customer_address,
                   s.quantity, s.total_price, s.discount_percent, s.created_at, s.created_by,
                   p.product_name, p.sell_price AS price_per_piece,
                   p.gst_percentage,
                   COALESCE(p.sgst,0) AS sgst, 
                   COALESCE(p.cgst,0) AS cgst, 
                   COALESCE(p.igst,0) AS igst,
                   p.id AS product_id,
                   CASE WHEN au.id IS NOT NULL THEN COALESCE(au.full_name,'Admin')
                        WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name
            FROM sales s
            LEFT JOIN invoices inv ON s.invoice_id = inv.id
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users au ON s.created_by = au.id
            LEFT JOIN create_users cu ON s.created_by = cu.id
            ORDER BY s.created_at DESC");
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            SELECT s.id, s.invoice_id, inv.invoice_no, inv.terms_text, s.customer_name, s.customer_phone, s.customer_address,
                   s.quantity, s.total_price, s.discount_percent, s.created_at, s.created_by,
                   p.product_name, p.sell_price AS price_per_piece,
                   p.gst_percentage,
                   COALESCE(p.sgst,0) AS sgst, 
                   COALESCE(p.cgst,0) AS cgst, 
                   COALESCE(p.igst,0) AS igst,
                   p.id AS product_id,
                   CASE WHEN au.id IS NOT NULL THEN COALESCE(au.full_name,'Admin')
                        WHEN cu.name IS NOT NULL THEN cu.name ELSE 'Unknown' END AS created_by_name
            FROM sales s
            LEFT JOIN invoices inv ON s.invoice_id = inv.id
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users au ON s.created_by = au.id
            LEFT JOIN create_users cu ON s.created_by = cu.id
            WHERE s.created_by = ?
            ORDER BY s.created_at DESC");
        $stmt->bind_param("i", $created_by_id); $stmt->execute();
    }
    $sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

    $grouped_sales = [];
    foreach ($sales as $sale) {
        $key = (isset($sale['invoice_id']) && $sale['invoice_id']) ? 'inv|'.$sale['invoice_id'] : ($sale['customer_name'].'|'.$sale['customer_phone'].'|'.$sale['created_at']);
        if (!isset($grouped_sales[$key])) {
            $grouped_sales[$key] = [
                'ids'=>[],'invoice_id'=>$sale['invoice_id']??null,
                'invoice_no'=>!empty($sale['invoice_no']) ? $sale['invoice_no'] : ('INV-' . str_pad($sale['invoice_id'] ?? 0, 5, '0', STR_PAD_LEFT)),
                'terms_text'=>$sale['terms_text'] ?? '',
                'customer_name'=>$sale['customer_name'],'customer_phone'=>$sale['customer_phone'],
                'customer_address'=>$sale['customer_address'],'created_at'=>$sale['created_at'],
                'created_by_name'=>$sale['created_by_name'],'products'=>[],'total_price'=>0,
                'total_tax'=>0,'total_excl'=>0,'total_sgst'=>0,'total_cgst'=>0,'total_igst'=>0,
            ];
        }
        $grouped_sales[$key]['ids'][] = $sale['id'];

        $price_incl      = (float)$sale['price_per_piece'];
        $qty             = (int)$sale['quantity'];
        $disc_pct        = (float)($sale['discount_percent'] ?? 0);
        $sgst_rate       = (float)($sale['sgst'] ?? 0);
        $cgst_rate       = (float)($sale['cgst'] ?? 0);
        $igst_rate       = (float)($sale['igst'] ?? 0);
        $gst_percent     = (float)($sale['gst_percentage'] ?? 0);
        
        if ($gst_percent > 0 && $sgst_rate == 0 && $cgst_rate == 0 && $igst_rate == 0) {
            $sgst_rate = $gst_percent / 2;
            $cgst_rate = $gst_percent / 2;
            $igst_rate = 0;
        }
        
        $tax_rate        = $sgst_rate + $cgst_rate + $igst_rate;
        $price_excl      = $tax_rate > 0 ? $price_incl / (1 + $tax_rate / 100) : $price_incl;
        $disc_amt        = $price_excl * $qty * ($disc_pct / 100);
        $taxable         = ($price_excl * $qty) - $disc_amt;
        $sgst_amt        = $taxable * ($sgst_rate / 100);
        $cgst_amt        = $taxable * ($cgst_rate / 100);
        $igst_amt        = $taxable * ($igst_rate / 100);
        $tax_amt         = $sgst_amt + $cgst_amt + $igst_amt;

        $product_exists = false;
        foreach ($grouped_sales[$key]['products'] as &$ep) {
            if ($ep['product_id'] == $sale['product_id']) {
                $ep['quantity']    += $qty;
                $ep['total_price'] += (float)$sale['total_price'];
                $ep['tax_amount']  += $tax_amt;
                $ep['sgst_amount'] += $sgst_amt;
                $ep['cgst_amount'] += $cgst_amt;
                $ep['igst_amount'] += $igst_amt;
                $ep['taxable_amount'] += $taxable;
                $product_exists = true; break;
            }
        }
        if (!$product_exists) {
            $grouped_sales[$key]['products'][] = [
                'product_id'      => $sale['product_id'],
                'product_name'    => $sale['product_name'],
                'quantity'        => $qty,
                'price_per_piece' => $price_incl,
                'price_excl_gst'  => $price_excl,
                'tax_rate'        => $tax_rate,
                'sgst_rate'       => $sgst_rate,
                'cgst_rate'       => $cgst_rate,
                'igst_rate'       => $igst_rate,
                'sgst_amount'     => $sgst_amt,
                'cgst_amount'     => $cgst_amt,
                'igst_amount'     => $igst_amt,
                'tax_amount'      => $tax_amt,
                'taxable_amount'  => $taxable,
                'total_price'     => (float)$sale['total_price'],
                'discount_percent'=> $disc_pct,
            ];
        }
        $grouped_sales[$key]['total_price'] += $sale['total_price'];
        $grouped_sales[$key]['total_tax']   += $tax_amt;
        $grouped_sales[$key]['total_excl']  += $taxable;
        $grouped_sales[$key]['total_sgst']  += $sgst_amt;
        $grouped_sales[$key]['total_cgst']  += $cgst_amt;
        $grouped_sales[$key]['total_igst']  += $igst_amt;
    }

    // Fetch payments per invoice
    foreach ($grouped_sales as $gkey => &$gdata) {
        $invoice_id = (!empty($gdata['invoice_id']) ? $gdata['invoice_id'] : (!empty($gdata['ids']) ? max($gdata['ids']) : 0));
        $gdata['invoice_id'] = $invoice_id;
        $gdata['paid_amount'] = 0.0; $gdata['payment_status'] = 'pending';
        $gdata['payment_history'] = [];
        if ($invoice_id) {
            try {
                $pstmt = $conn->prepare("SELECT id, amount, payment_method, created_at FROM received_payments WHERE invoice_id = ? ORDER BY created_at ASC");
                $pstmt->bind_param("i", $invoice_id); $pstmt->execute();
                $pay_rows = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC); $pstmt->close();
                $paid = 0;
                foreach ($pay_rows as $pr) {
                    $paid += (float)$pr['amount'];
                    $gdata['payment_history'][] = $pr;
                }
                $gdata['paid_amount'] = $paid;
                $methods = array_unique(array_column($pay_rows, 'payment_method'));
                $gdata['payment_methods'] = implode(', ', $methods);
                if ($paid <= 0.004) $gdata['payment_status'] = 'pending';
                elseif ($paid < $gdata['total_price'] - 0.01) $gdata['payment_status'] = 'partial';
                else $gdata['payment_status'] = 'paid';
                $gdata['outstanding'] = round($gdata['total_price'] - $paid, 2);
            } catch (Exception $pe) {
                $gdata['paid_amount'] = 0.0; $gdata['payment_status'] = 'pending'; $gdata['outstanding'] = $gdata['total_price'];
            }
        } else { $gdata['outstanding'] = $gdata['total_price']; }
    }
    unset($gdata);
} catch (Exception $e) {
    $error_message = "Error fetching sales: " . $e->getMessage();
    $grouped_sales = [];
}

// Fetch products
try {
    $stmt = $conn->prepare("SELECT id, product_name, quantity, sell_price AS price_per_piece, gst_percentage, COALESCE(sgst,0) AS sgst, COALESCE(cgst,0) AS cgst, COALESCE(igst,0) AS igst FROM products");
    $stmt->execute(); $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Exception $e) { $products = []; }

// Fetch customers
try {
    $stmt = $conn->prepare("SELECT id, name, phone, address, state FROM customers ORDER BY name ASC");
    $stmt->execute(); $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} catch (Exception $e) { $customers = []; }

// Sanitize
$sanitized_sales = array_map(function($sale) {
    return [
        'ids'              => $sale['ids'],
        'customer_name'    => htmlspecialchars($sale['customer_name'], ENT_QUOTES, 'UTF-8'),
        'customer_phone'   => htmlspecialchars($sale['customer_phone'], ENT_QUOTES, 'UTF-8'),
        'customer_address' => htmlspecialchars(str_replace(["\r","\n"],' ',$sale['customer_address']), ENT_QUOTES, 'UTF-8'),
        'products'         => array_map(function($prod) {
            return [
                'product_id'      => (int)$prod['product_id'],
                'product_name'    => htmlspecialchars($prod['product_name'],ENT_QUOTES,'UTF-8'),
                'quantity'        => (int)$prod['quantity'],
                'price_per_piece' => (float)$prod['price_per_piece'],
                'price_excl_gst'  => (float)($prod['price_excl_gst'] ?? $prod['price_per_piece']),
                'tax_rate'        => (float)($prod['tax_rate'] ?? 0),
                'sgst_rate'       => (float)($prod['sgst_rate'] ?? 0),
                'cgst_rate'       => (float)($prod['cgst_rate'] ?? 0),
                'igst_rate'       => (float)($prod['igst_rate'] ?? 0),
                'sgst_amount'     => (float)($prod['sgst_amount'] ?? 0),
                'cgst_amount'     => (float)($prod['cgst_amount'] ?? 0),
                'igst_amount'     => (float)($prod['igst_amount'] ?? 0),
                'tax_amount'      => (float)($prod['tax_amount'] ?? 0),
                'taxable_amount'  => (float)($prod['taxable_amount'] ?? 0),
                'total_price'     => (float)$prod['total_price'],
                'discount_percent'=> (float)($prod['discount_percent']??0),
            ];
        }, $sale['products']),
        'total_price'      => (float)$sale['total_price'],
        'total_tax'        => (float)($sale['total_tax']??0),
        'total_excl'       => (float)($sale['total_excl']??0),
        'total_sgst'       => (float)($sale['total_sgst']??0),
        'total_cgst'       => (float)($sale['total_cgst']??0),
        'total_igst'       => (float)($sale['total_igst']??0),
        'paid_amount'      => (float)($sale['paid_amount']??0),
        'outstanding'      => (float)($sale['outstanding']??0),
        'payment_status'   => $sale['payment_status']??'pending',
        'invoice_id'       => (int)($sale['invoice_id']??0),
        'created_at'       => $sale['created_at'],
        'created_by_name'  => htmlspecialchars($sale['created_by_name'],ENT_QUOTES,'UTF-8'),
        'payment_methods'  => htmlspecialchars($sale['payment_methods']??'',ENT_QUOTES,'UTF-8'),
        'payment_history'  => $sale['payment_history'] ?? [],
    ];
}, array_values($grouped_sales));

$total_revenue     = array_sum(array_column($sanitized_sales, 'total_price'));
$total_paid        = array_sum(array_column($sanitized_sales, 'paid_amount'));
$total_outstanding = array_sum(array_column($sanitized_sales, 'outstanding'));
$total_invoices    = count($sanitized_sales);
$total_gst         = array_sum(array_column($sanitized_sales, 'total_tax'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sales — <?php echo htmlspecialchars($company['company_name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
:root{
  --brand:#0284c7;--brand-dark:#0b1220;--brand-mid:#0ea5e9;--brand-light:#e0f2fe;
  --success:#16a34a;--success-bg:#dcfce7;--danger:#dc2626;--danger-bg:#fee2e2;
  --warning:#d97706;--warning-bg:#fef3c7;--info:#0284c7;--info-bg:#e0f2fe;
  --teal:#0f766e;--teal-bg:#ccfbf1;
  --bg:#f4f8fb;--card:#ffffff;--border:#e2e8f0;--text:#0b1220;
  --text-mid:#475569;--text-muted:#94a3b8;
  --radius-sm:8px;--radius-md:14px;--radius-lg:20px;
  --shadow-sm:0 2px 8px rgba(2,132,199,.07);--shadow-md:0 8px 28px rgba(2,132,199,.11);
  --shadow-lg:0 16px 48px rgba(2,132,199,.16);--transition:all .25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}

.page-wrap{margin-top:70px;margin-left:260px;padding:24px 20px 60px;transition:var(--transition);}
@media(max-width:992px){.page-wrap{margin-left:0;padding:16px 12px 50px;}}

.page-hero{background:linear-gradient(135deg,var(--brand-dark) 0%,var(--brand) 55%,var(--brand-mid) 100%);border-radius:var(--radius-lg);padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px;box-shadow:var(--shadow-lg);position:relative;overflow:hidden;flex-wrap:wrap;}
.page-hero::before{content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.06);}
.hero-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.45rem;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;}
.hero-sub{color:rgba(255,255,255,.75);font-size:.82rem;margin-top:2px;}
.hero-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}

.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px;}
@media(max-width:1100px){.stats-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:600px){.stats-grid{grid-template-columns:repeat(2,1fr);gap:10px;}}
.stat-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);padding:16px 18px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);transition:var(--transition);position:relative;overflow:hidden;}
.stat-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px);}
.stat-card::after{content:'';position:absolute;right:-10px;top:-10px;width:60px;height:60px;border-radius:50%;background:currentColor;opacity:.04;}
.stat-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.stat-icon.purple{background:var(--brand-light);color:var(--brand);}
.stat-icon.green{background:var(--success-bg);color:var(--success);}
.stat-icon.red{background:var(--danger-bg);color:var(--danger);}
.stat-icon.blue{background:var(--info-bg);color:var(--info);}
.stat-icon.teal{background:var(--teal-bg);color:var(--teal);}
.stat-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:1.2rem;font-weight:800;line-height:1;}
.stat-label{font-size:.7rem;color:var(--text-muted);margin-top:3px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}

.table-card{background:var(--card);border-radius:var(--radius-md);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;}
.search-bar-wrap{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.search-input-wrap{position:relative;flex:1;min-width:200px;}
.search-input-wrap input{width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:50px;font-size:.85rem;outline:none;transition:var(--transition);background:#fafafa;}
.search-input-wrap input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(2,132,199,.1);background:#fff;}
.search-input-wrap .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.78rem;}
.filter-select{
    padding: 8px 34px 8px 16px;
    border: 1.5px solid var(--border);
    border-radius: 50px;
    font-size: .82rem;
    font-weight: 600;
    background-color: #fafafa;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%230284c7' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");

    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 11px;
    color: var(--text);
    outline: none;
    cursor: pointer;
    transition: var(--transition);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}
.filter-select:hover { border-color: var(--brand); background-color: #fff; }
.filter-select:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(2,132,199,.12); background-color: #fff; }


.table-scroll{overflow-x:auto;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead th{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;padding:11px 13px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;white-space:nowrap;text-align:left;border:none;}
.data-table tbody tr{border-bottom:1px solid #eef2f6;transition:background .15s;}
.data-table tbody tr:last-child{border-bottom:none;}
.data-table tbody tr:hover{background:#f8fbfd;}
.data-table td{padding:12px 13px;vertical-align:middle;font-size:.85rem;}

.invoice-num{font-family:monospace;font-size:.8rem;background:var(--brand-light);color:var(--brand);border-radius:6px;padding:4px 8px;font-weight:700;letter-spacing:.3px;white-space:nowrap;}
.customer-cell{display:flex;align-items:center;gap:9px;}
.customer-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;font-weight:700;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.customer-name{font-weight:600;font-size:.88rem;}
.customer-phone{font-size:.73rem;color:var(--text-muted);margin-top:1px;}

.product-badge-list{display:flex;flex-wrap:wrap;gap:4px;max-width:260px;}
.pbadge{background:#f8fbfd;border:1px solid var(--border);border-radius:7px;padding:5px 8px;font-size:.72rem;}
.pbadge-name{font-weight:700;color:var(--text);display:flex;align-items:center;gap:4px;}
.pbadge-qty{background:var(--brand-light);color:var(--brand);border-radius:4px;padding:1px 5px;font-size:.65rem;font-weight:800;}
.pbadge-tax{display:flex;gap:4px;margin-top:3px;flex-wrap:wrap;}
.pbadge-tax span{padding:1px 5px;border-radius:4px;font-size:.62rem;font-weight:700;}
.tax-sgst{background:#dbeafe;color:#1d4ed8;}
.tax-cgst{background:#fce7f3;color:#9d174d;}
.tax-igst{background:#fef3c7;color:#92400e;}
.tax-rate-chip{background:var(--brand-light);color:var(--brand);}

.amount-wrap .main-amt{font-weight:800;font-size:.95rem;color:var(--brand);font-family:'Plus Jakarta Sans',sans-serif;}
.amount-wrap .sub-line{font-size:.7rem;color:var(--text-muted);margin-top:1px;display:flex;align-items:center;gap:3px;}
.amount-wrap .sgst-line{color:#1d4ed8;}
.amount-wrap .cgst-line{color:#9d174d;}
.amount-wrap .igst-line{color:#92400e;}
.amount-wrap .paid-line{font-size:.7rem;color:var(--success);margin-top:1px;font-weight:600;}
.amount-wrap .due-line{font-size:.7rem;color:var(--danger);font-weight:700;}

.status-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:50px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
.status-badge.paid{background:var(--success-bg);color:var(--success);}
.status-badge.partial{background:var(--warning-bg);color:var(--warning);}
.status-badge.pending{background:var(--danger-bg);color:var(--danger);}
.status-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}
.creator-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:50px;font-size:.68rem;font-weight:700;}
.creator-badge.admin{background:var(--brand-light);color:var(--brand);}
.creator-badge.staff{background:var(--success-bg);color:var(--success);}

.action-group{display:flex;gap:5px;align-items:center;justify-content:center;}
.act-btn{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:#fff;border:none;cursor:pointer;transition:var(--transition);text-decoration:none;flex-shrink:0;position:relative;}
.act-btn:hover{transform:translateY(-3px) scale(1.1);}
.act-btn.print{background:linear-gradient(135deg,#0ea5e9,#0284c7);box-shadow:0 3px 10px rgba(14,165,233,.3);}
.act-btn.whatsapp{background:linear-gradient(135deg,#25D366,#128C7E);box-shadow:0 3px 10px rgba(37,211,102,.3);}
.act-btn.edit{background:linear-gradient(135deg,var(--brand),var(--brand-mid));box-shadow:0 3px 10px rgba(2,132,199,.3);}
.act-btn.pay{background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 3px 10px rgba(245,158,11,.3);}
.act-btn.del{background:linear-gradient(135deg,#ef4444,#b91c1c);box-shadow:0 3px 10px rgba(239,68,68,.3);}
.act-btn::after{content:attr(data-tip);position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#1a1625;color:#fff;font-size:.65rem;padding:3px 7px;border-radius:5px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s;z-index:100;}
.act-btn:hover::after{opacity:1;}

.btn-primary-custom{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff!important;border:2px solid rgba(255,255,255,.7);padding:9px 20px;border-radius:50px;font-size:.85rem;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;display:inline-flex;align-items:center;gap:6px;cursor:pointer;transition:var(--transition);box-shadow:0 5px 16px rgba(2,132,199,.28);text-decoration:none;white-space:nowrap;}
.btn-primary-custom:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(2,132,199,.38);border-color:#fff;}
.btn-export{padding:9px 16px;border-radius:50px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:var(--transition);color:#fff;white-space:nowrap;}
.btn-export.pdf{background:linear-gradient(135deg,#e11d48,#be123c);box-shadow:0 4px 12px rgba(225,29,72,.25);}
.btn-export.excel{background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 4px 12px rgba(22,163,74,.25);}
.btn-export:hover{transform:translateY(-2px);}

.alert-top{border-radius:var(--radius-sm);padding:11px 15px;margin-bottom:16px;display:flex;align-items:center;gap:9px;font-size:.85rem;font-weight:500;animation:slideDown .3s ease;}
@keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.alert-top.success{background:var(--success-bg);color:var(--success);border:1px solid #86efac;}
.alert-top.danger{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

.modal-content{border-radius:var(--radius-md);border:none;box-shadow:var(--shadow-lg);}
.modal-header-grad{background:linear-gradient(135deg,var(--brand),var(--brand-mid));color:#fff;border-radius:var(--radius-md) var(--radius-md) 0 0;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;}
.modal-body{padding:20px;max-height:72vh;overflow-y:auto;}
.modal-footer{border-top:1px solid var(--border);padding:12px 20px;}

.section-label{font-size:.68rem;font-weight:800;color:var(--brand);text-transform:uppercase;letter-spacing:.8px;margin:18px 0 10px;display:flex;align-items:center;gap:8px;}
.section-label::after{content:'';flex:1;height:1px;background:linear-gradient(to right,rgba(2,132,199,.2),transparent);}

.mfield{margin-bottom:12px;}
.mfield label{display:block;font-size:.72rem;font-weight:700;color:var(--text-mid);text-transform:uppercase;letter-spacing:.3px;margin-bottom:5px;}
.mfield input,.mfield select,.mfield textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.88rem;font-family:'Inter',sans-serif;color:var(--text);background:#fafafa;outline:none;transition:var(--transition);}
.mfield input:focus,.mfield select:focus,.mfield textarea:focus{border-color:var(--brand);background:#fff;box-shadow:0 0 0 3px rgba(2,132,199,.1);}
.mfield textarea{resize:vertical;min-height:54px;}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:576px){.grid-2{grid-template-columns:1fr;}}
.grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;}
@media(max-width:576px){.grid-3{grid-template-columns:1fr;}}

.product-row{background:#f8fbfd;border:1.5px solid var(--border);border-radius:10px;padding:12px;margin-bottom:8px;display:grid;grid-template-columns:2fr 1fr 1fr 36px;gap:8px;align-items:end;}
.product-row:hover{border-color:rgba(2,132,199,.25);}
@media(max-width:576px){.product-row{grid-template-columns:1fr 1fr;}}
.remove-product-btn{width:34px;height:34px;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;transition:var(--transition);}
.remove-product-btn:hover{transform:scale(1.1);}

.price-preview{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:9px 11px;margin-top:6px;font-size:.75rem;display:none;}
.price-preview.show{display:block;}
.price-preview .pp-row{display:flex;justify-content:space-between;color:var(--text-mid);padding:2px 0;}
.pp-tax-detail{display:flex;gap:8px;flex-wrap:wrap;margin-top:3px;}
.pp-tax-chip{padding:2px 6px;border-radius:4px;font-size:.65rem;font-weight:700;}
.price-preview .pp-row.total{font-weight:800;color:var(--success);border-top:1px solid #bbf7d0;margin-top:4px;padding-top:6px;}

.total-display{background:linear-gradient(135deg,var(--brand-light),#fff);border:1.5px solid rgba(2,132,199,.15);border-radius:10px;padding:14px 16px;margin:14px 0;}
.total-display .tline{display:flex;justify-content:space-between;font-size:.82rem;color:var(--text-mid);padding:3px 0;}
.total-display .tline.tax-detail{font-size:.75rem;padding-left:12px;}
.total-display .tline.grand{font-weight:800;font-size:1rem;color:var(--brand);border-top:1.5px solid rgba(2,132,199,.18);margin-top:6px;padding-top:8px;}

.payment-section{background:#fff8ff;border:1.5px solid rgba(2,132,199,.12);border-radius:10px;padding:12px 14px;margin-top:4px;}
.payment-hint{margin-top:8px;font-size:.75rem;padding:7px 10px;border-radius:7px;display:none;}
.payment-hint.full{background:var(--success-bg);color:var(--success);border:1px solid #86efac;}
.payment-hint.partial{background:var(--warning-bg);color:var(--warning);border:1px solid #fcd34d;}
.payment-hint.none{background:var(--danger-bg);color:var(--danger);border:1px solid #fca5a5;}

.add-product-btn{width:100%;background:var(--brand-light);border:1.5px dashed rgba(2,132,199,.3);color:var(--brand);padding:9px 14px;border-radius:9px;font-size:.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:var(--transition);margin-bottom:4px;}
.add-product-btn:hover{background:rgba(2,132,199,.1);border-color:var(--brand);}

.select2-container--default .select2-selection--single{height:40px!important;border:1.5px solid var(--border)!important;border-radius:8px!important;background:#fafafa!important;}
.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:38px!important;padding-left:12px;font-size:.88rem;}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:38px!important;}
.select2-container--default .select2-results__option--highlighted[aria-selected]{background:var(--brand)!important;}
.select2-dropdown{border:1.5px solid var(--border)!important;border-radius:8px!important;box-shadow:var(--shadow-md)!important;}

.no-results-row td{text-align:center;padding:60px 20px;color:var(--text-muted);}
.no-results-icon{font-size:2.5rem;opacity:.3;margin-bottom:10px;}

.duration-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px;}
.duration-card{border:2px solid var(--border);border-radius:var(--radius-sm);padding:13px;cursor:pointer;transition:var(--transition);text-align:center;}
.duration-card:hover,.duration-card.active{border-color:var(--brand);background:var(--brand-light);}
.duration-card i{font-size:1.4rem;color:var(--brand);margin-bottom:6px;}
.duration-card h5{font-size:.85rem;font-weight:700;margin:0 0 2px;}
.duration-card p{font-size:.7rem;color:var(--text-muted);margin:0;}
.duration-card.all-data{grid-column:span 2;}
</style>
</head>
<body>
<?php ?>
<div class="page-wrap">

  <div class="page-hero">
    <div>
      <div class="hero-title"><i class="fas fa-chart-line" style="opacity:.85"></i>Sales Dashboard</div>
      <div class="hero-sub">Manage invoices, GST, payments & customer accounts</div>
    </div>
    <div class="hero-actions">
      <a href="javascript:void(0)" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSaleModal">
        <i class="fas fa-plus-circle"></i> New Sale
      </a>
      <button class="btn-export pdf" onclick="showExportModal('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
      <button class="btn-export excel" onclick="showExportModal('excel')"><i class="fas fa-file-excel"></i> Excel</button>
    </div>
  </div>

  <?php if ($success_message): ?>
  <div class="alert-top success" id="successAlert"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($success_message); ?></div>
  <?php endif; ?>
  <?php if ($error_message): ?>
  <div class="alert-top danger"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error_message); ?></div>
  <?php endif; ?>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon purple"><i class="fas fa-receipt"></i></div>
      <div><div class="stat-val" style="color:var(--brand)"><?php echo number_format($total_invoices); ?></div><div class="stat-label">Total Invoices</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-indian-rupee-sign"></i></div>
      <div><div class="stat-val" style="color:var(--info)">₹<?php echo number_format($total_revenue, 0); ?></div><div class="stat-label">Revenue (incl. GST)</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon teal"><i class="fas fa-percent"></i></div>
      <div><div class="stat-val" style="color:var(--teal)">₹<?php echo number_format($total_gst, 0); ?></div><div class="stat-label">Total GST Collected</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
      <div><div class="stat-val" style="color:var(--success)">₹<?php echo number_format($total_paid, 0); ?></div><div class="stat-label">Amount Collected</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon red"><i class="fas fa-hourglass-half"></i></div>
      <div><div class="stat-val" style="color:var(--danger)">₹<?php echo number_format($total_outstanding, 0); ?></div><div class="stat-label">Outstanding</div></div>
    </div>
  </div>

  <div class="table-card">
    <div class="search-bar-wrap">
      <div class="search-input-wrap">
        <i class="fas fa-search si"></i>
        <input type="text" id="tableSearch" placeholder="Search by customer, invoice, phone…" oninput="filterTable()">
      </div>
      <select class="filter-select" id="statusFilter" onchange="filterTable()">
        <option value="">All Status</option>
        <option value="paid">Paid</option>
        <option value="partial">Partial</option>
        <option value="pending">Pending</option>
      </select>
      <span style="font-size:.76rem;color:var(--text-muted)" id="tableCount"></span>
    </div>
    <div class="table-scroll">
      <table class="data-table" id="salesTable">
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Customer</th>
            <th>Products & GST</th>
            <th>Amount Breakup</th>
            <th>Status</th>
            <th>Date</th>
            <th>By</th>
            <th style="text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody id="salesTableBody">
        <?php if (empty($sanitized_sales)): ?>
          <tr class="no-results-row">
            <td colspan="8">
              <div class="no-results-icon"><i class="fas fa-receipt"></i></div>
              <div style="font-weight:600;margin-bottom:5px">No sales yet</div>
              <div style="font-size:.8rem">Click <strong>New Sale</strong> to record your first sale.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($sanitized_sales as $sale):
            $initials = strtoupper(substr($sale['customer_name'], 0, 1) . (strpos($sale['customer_name'], ' ') !== false ? substr($sale['customer_name'], strpos($sale['customer_name'], ' ') + 1, 1) : ''));
            $dateObj = new DateTime($sale['created_at'], new DateTimeZone('UTC'));
            $dateObj->setTimezone(new DateTimeZone('Asia/Kolkata'));
          ?>
          <tr data-search="<?php echo strtolower($sale['customer_name'].' '.$sale['customer_phone'].' '.$sale['invoice_id'].' '.($sale['invoice_no']??'')); ?>" data-status="<?php echo $sale['payment_status']; ?>">
            <td><span class="invoice-num" style="font-size:0.75rem; font-weight:700; color:var(--brand);"><?php echo htmlspecialchars($sale['invoice_no'] ?? ('#'.$sale['invoice_id'])); ?></span></td>
            <td>
              <div class="customer-cell">
                <div class="customer-avatar"><?php echo $initials; ?></div>
                <div>
                  <div class="customer-name"><?php echo $sale['customer_name']; ?></div>
                  <?php if ($sale['customer_phone']): ?>
                  <div class="customer-phone"><i class="fas fa-phone" style="font-size:.62rem;margin-right:2px"></i><?php echo $sale['customer_phone']; ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <div class="product-badge-list">
                <?php foreach ($sale['products'] as $prod): ?>
                <div class="pbadge">
                  <div class="pbadge-name">
                    <?php echo $prod['product_name']; ?>
                    <span class="pbadge-qty">x<?php echo $prod['quantity']; ?></span>
                  </div>
                  <div class="pbadge-tax">
                    <?php if ($prod['tax_rate'] == 0): ?>
                      <span class="pp-tax-chip tax-rate-chip">GST 0%</span>
                    <?php else: ?>
                      <?php if ($prod['sgst_rate'] > 0): ?>
                        <span class="pp-tax-chip tax-sgst">SGST <?php echo number_format($prod['sgst_rate'],1); ?>% ₹<?php echo number_format($prod['sgst_amount'],2); ?></span>
                      <?php endif; ?>
                      <?php if ($prod['cgst_rate'] > 0): ?>
                        <span class="pp-tax-chip tax-cgst">CGST <?php echo number_format($prod['cgst_rate'],1); ?>% ₹<?php echo number_format($prod['cgst_amount'],2); ?></span>
                      <?php endif; ?>
                      <?php if ($prod['igst_rate'] > 0): ?>
                        <span class="pp-tax-chip tax-igst">IGST <?php echo number_format($prod['igst_rate'],1); ?>% ₹<?php echo number_format($prod['igst_amount'],2); ?></span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </td>
            <td>
              <div class="amount-wrap">
                <div class="main-amt">₹<?php echo number_format($sale['total_price'], 2); ?></div>
                <div class="sub-line"><span style="color:var(--text-muted)">Excl. GST:</span> ₹<?php echo number_format($sale['total_excl'], 2); ?></div>
                <?php if ($sale['total_tax'] > 0.004): ?>
                  <?php if ($sale['total_sgst'] > 0): ?><div class="sub-line sgst-line"><i class="fas fa-circle" style="font-size:.45rem"></i> SGST ₹<?php echo number_format($sale['total_sgst'],2); ?></div><?php endif; ?>
                  <?php if ($sale['total_cgst'] > 0): ?><div class="sub-line cgst-line"><i class="fas fa-circle" style="font-size:.45rem"></i> CGST ₹<?php echo number_format($sale['total_cgst'],2); ?></div><?php endif; ?>
                  <?php if ($sale['total_igst'] > 0): ?><div class="sub-line igst-line"><i class="fas fa-circle" style="font-size:.45rem"></i> IGST ₹<?php echo number_format($sale['total_igst'],2); ?></div><?php endif; ?>
                <?php else: ?>
                  <div class="sub-line">GST: ₹0.00</div>
                <?php endif; ?>
                <?php if ($sale['paid_amount'] > 0): ?>
                <div class="paid-line"><i class="fas fa-check-circle" style="font-size:.6rem"></i> Paid ₹<?php echo number_format($sale['paid_amount'],2); ?></div>
                <?php endif; ?>
                <?php if ($sale['outstanding'] > 0.009): ?>
                <div class="due-line"><i class="fas fa-clock" style="font-size:.6rem"></i> Due ₹<?php echo number_format($sale['outstanding'],2); ?></div>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <span class="status-badge <?php echo $sale['payment_status']; ?>">
                <span class="status-dot"></span><?php echo ucfirst($sale['payment_status']); ?>
              </span>
              <?php if ($sale['payment_methods']): ?>
              <div style="font-size:.67rem;color:var(--text-muted);margin-top:3px"><i class="fas fa-credit-card" style="font-size:.58rem"></i> <?php echo $sale['payment_methods']; ?></div>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-muted);font-size:.8rem">
              <?php echo $dateObj->format('d M Y'); ?><br>
              <span style="font-size:.68rem"><?php echo $dateObj->format('h:i A'); ?></span>
            </td>
            <td>
              <span class="creator-badge <?php echo $sale['created_by_name'] === 'Admin' ? 'admin' : 'staff'; ?>">
                <i class="fas fa-<?php echo $sale['created_by_name'] === 'Admin' ? 'shield' : 'user'; ?>" style="font-size:.62rem"></i>
                <?php echo $sale['created_by_name']; ?>
              </span>
            </td>
            <td>
              <div class="action-group">
                <a href="sale_invoice.php?invoice_id=<?php echo urlencode($sale['invoice_id']); ?>" target="_blank" class="act-btn print" data-tip="Print Invoice"><i class="fas fa-print"></i></a>
                <a href="javascript:void(0)" class="act-btn whatsapp" data-tip="WhatsApp" data-sale-ids='<?php echo json_encode($sale['ids']); ?>'><i class="fab fa-whatsapp"></i></a>
                <?php if ($sale['outstanding'] > 0.009): ?>
                <a href="javascript:void(0)" class="act-btn pay" data-tip="Add Payment"
                   onclick="openPaymentModal(<?php echo $sale['invoice_id']; ?>, <?php echo $sale['outstanding']; ?>, '<?php echo addslashes($sale['customer_name']); ?>')">
                  <i class="fas fa-indian-rupee-sign"></i>
                </a>
                <?php endif; ?>
                <a href="javascript:void(0)" class="act-btn edit" data-tip="Edit Sale" data-bs-toggle="modal" data-bs-target="#addSaleModal" onclick="editSale(<?php echo htmlspecialchars(json_encode($sale), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-pen"></i></a>
                <?php if ($user_type === 'admin'): ?>
                <a href="javascript:void(0)" class="act-btn del" data-tip="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" onclick="document.getElementById('deleteSaleId').value='<?php echo implode(',', $sale['ids']); ?>'"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD / EDIT SALE MODAL -->
<div class="modal fade" id="addSaleModal" tabindex="-1" aria-hidden="true" data-bs-focus="false">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header-grad">
        <h5 id="saleModalTitle" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0">
          <i class="fas fa-plus-circle me-2"></i>New Sale
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="addSaleForm" action="sales.php" method="post" autocomplete="off">
        <div class="modal-body">
          <input type="hidden" name="products_json" id="products_json">
          <input type="hidden" name="invoice_id" id="invoice_id">

          <div class="section-label"><i class="fas fa-file-invoice"></i> Invoice Details</div>
          <div class="grid-2" style="margin-bottom:14px">
            <div class="mfield" style="margin-bottom:0">
              <label><i class="fas fa-hashtag me-1"></i>Invoice No. <span style="color:var(--danger)">*</span></label>
              <input type="text" id="invoice_no_add" name="invoice_no" value="<?php echo htmlspecialchars($next_sales_invoice_no); ?>" readonly style="background:#f0f9ff; font-weight:700; color:#0284c7; border-color:#bae6fd">
              <div style="font-size:0.68rem; color:#0284c7; margin-top:3px;"><i class="fas fa-bolt me-1"></i>Auto-incremented sequence</div>
            </div>
            <div class="mfield" style="margin-bottom:0">
              <label><i class="fas fa-calendar me-1"></i>Invoice Date</label>
              <input type="date" id="invoice_date_add" name="invoice_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>

          <div class="section-label"><i class="fas fa-user-circle"></i> Customer Details</div>
          <div class="mfield">
            <label><i class="fas fa-user me-1"></i>Select Customer <span style="color:var(--danger)">*</span></label>
            <select id="customer_name_select" name="customer_name" required style="width:100%">
              <option value="">Search or select customer…</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?php echo htmlspecialchars($c['name']); ?>"
                      data-phone="<?php echo htmlspecialchars($c['phone']??''); ?>"
                      data-address="<?php echo htmlspecialchars($c['address']??''); ?>">
                <?php echo htmlspecialchars($c['name']); ?><?php if (!empty($c['phone'])): ?> — <?php echo htmlspecialchars($c['phone']); ?><?php endif; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="grid-2">
            <div class="mfield" style="margin-bottom:0">
              <label><i class="fas fa-phone me-1"></i>Phone</label>
              <input type="text" id="customer_phone_add" name="customer_phone" placeholder="+919876543210">
            </div>
            <div class="mfield" style="margin-bottom:0">
              <label><i class="fas fa-location-dot me-1"></i>Address</label>
              <textarea id="customer_address_add" name="customer_address" rows="2" placeholder="Customer address…"></textarea>
            </div>
          </div>

          <div class="section-label" style="margin-top:20px"><i class="fas fa-box-open"></i> Products</div>
          <div id="product-list"></div>
          <button type="button" class="add-product-btn" id="addProductBtn">
            <i class="fas fa-plus-circle"></i> Add Another Product
          </button>

          <div class="total-display" id="totalDisplay">
            <div class="tline"><span>Subtotal (excl. GST)</span><span id="td_subtotal">₹0.00</span></div>
            <div class="tline"><span>Total Discount</span><span id="td_discount" style="color:var(--success)">– ₹0.00</span></div>
            <div class="tline"><span>Taxable Amount</span><span id="td_taxable">₹0.00</span></div>
            <div class="tline tax-detail sgst-line" id="td_sgst_row" style="display:none"><span><i class="fas fa-circle" style="font-size:.45rem;margin-right:4px"></i>SGST</span><span id="td_sgst">₹0.00</span></div>
            <div class="tline tax-detail cgst-line" id="td_cgst_row" style="display:none"><span><i class="fas fa-circle" style="font-size:.45rem;margin-right:4px"></i>CGST</span><span id="td_cgst">₹0.00</span></div>
            <div class="tline tax-detail igst-line" id="td_igst_row" style="display:none"><span><i class="fas fa-circle" style="font-size:.45rem;margin-right:4px"></i>IGST</span><span id="td_igst">₹0.00</span></div>
            <div class="tline" style="color:var(--info);font-weight:600"><span><i class="fas fa-percent" style="font-size:.65rem;margin-right:4px"></i>Total GST</span><span id="td_tax">₹0.00</span></div>
            <div class="tline" style="color:var(--danger);font-weight:600" id="td_tds_row"><span><i class="fas fa-minus-circle" style="font-size:.65rem;margin-right:4px"></i>TDS Deduction (<span id="tds_percent_lbl">0</span>%)</span><span id="td_tds">– ₹0.00</span></div>
            <div class="tline grand"><span><i class="fas fa-indian-rupee-sign me-1" style="font-size:.82rem"></i>Final Payable Total</span><span id="td_total">₹0.00</span></div>
          </div>

          <div class="section-label"><i class="fas fa-calculator me-1"></i> TDS (Tax Deducted at Source)</div>
          <div class="grid-2" style="background:#fdf2f2; border:1px solid #fecaca; padding:12px; border-radius:10px; margin-bottom:14px">
            <div class="mfield" style="margin:0">
              <label><i class="fas fa-percent me-1"></i>TDS Rate (%)</label>
              <select name="tds_percent" id="tds_percent" onchange="updateTotal()">
                <option value="0">0% (No TDS)</option>
                <option value="1">1% (Sec 194C - Individual/HUF)</option>
                <option value="2">2% (Sec 194C - Company/Others)</option>
                <option value="5">5% (Sec 194H - Commission / Brokerage)</option>
                <option value="10">10% (Sec 194J - Professional / Tech Services)</option>
              </select>
            </div>
            <div class="mfield" style="margin:0">
              <label><i class="fas fa-coins me-1"></i>TDS Amount</label>
              <input type="text" id="tds_amount_display" readonly value="₹0.00" style="background:#fff; font-weight:700; color:var(--danger)">
            </div>
          </div>

          <div class="section-label"><i class="fas fa-file-contract me-1"></i> Terms & Conditions</div>
          <div class="grid-2" style="background:#f4f8fb; border:1px solid #e2e8f0; padding:12px; border-radius:10px; margin-bottom:14px">
            <div class="mfield" style="margin:0; grid-column: span 2;">
              <label><i class="fas fa-heading me-1"></i>Title</label>
              <div style="display: flex; gap: 8px;">
                  <select id="terms_title_select" onchange="applyTermContent()" style="flex: 1;">
                    <option value="">None</option>
                  </select>
                  <button type="button" class="btn-ghost-c" style="padding: 0 14px; border-radius: var(--radius-sm); white-space: nowrap;" onclick="openManageTermsModal()">
                    <i class="fas fa-plus-circle"></i> Add / Manage
                  </button>
              </div>
            </div>
            <div class="mfield" style="margin-bottom:0; margin-top:10px; grid-column: span 2;">
              <label><i class="fas fa-align-left me-1"></i>Content</label>
              <textarea name="terms_text" id="terms_text" rows="3" placeholder="Select a title or type terms here..."></textarea>
            </div>
          </div>

          <div class="section-label"><i class="fas fa-credit-card"></i> Payment Received</div>
          <div class="payment-section">
            <div class="grid-2">
              <div class="mfield" style="margin-bottom:0">
                <label><i class="fas fa-wallet me-1"></i>Payment Method</label>
                <select name="payment_method" id="payment_method">
                  <option value="">— Select —</option>
                  <option value="Cash">Cash</option>
                  <option value="Card">Card</option>
                  <option value="UPI">UPI</option>
                  <option value="Bank Transfer">Bank Transfer</option>
                  <option value="Cheque">Cheque</option>
                </select>
              </div>
              <div class="mfield" style="margin-bottom:0">
                <label><i class="fas fa-indian-rupee-sign me-1"></i>Amount Received</label>
                <input type="number" step="0.01" min="0" name="paid_amount" id="paid_amount" placeholder="0.00">
              </div>
            </div>
            <div id="paymentHint" class="payment-hint"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
          <button type="submit" name="add_sale" id="saleSubmitBtn" class="btn-primary-custom" style="border:none;padding:10px 24px">
            <i class="fas fa-check-circle"></i><span id="saleSubmitText">Save Sale</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MANAGE TERMS MODAL -->
<div class="modal fade" id="manageTermsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" style="z-index: 1060; background: rgba(0,0,0,0.5);">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header-grad" style="background:linear-gradient(135deg,var(--brand-dark),var(--brand))">
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-file-contract me-2"></i>Manage Terms & Conditions</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mfield">
          <label>Select Term to Edit/Delete</label>
          <select id="manage_terms_select" onchange="loadTermToEdit()">
            <option value="">-- Add New Term --</option>
          </select>
        </div>
        <hr>
        <input type="hidden" id="manage_term_id" value="">
        <div class="mfield">
          <label>Title <span style="color:var(--danger)">*</span></label>
          <input type="text" id="manage_term_title" placeholder="e.g. Sale Invoice">
        </div>
        <div class="mfield">
          <label>Content <span style="color:var(--danger)">*</span></label>
          <textarea id="manage_term_content" rows="4" placeholder="Terms details..."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="justify-content: space-between;">
        <button type="button" class="btn btn-danger" id="btnDeleteTerm" onclick="deleteTerm()" style="display:none;"><i class="fas fa-trash me-1"></i>Delete</button>
        <div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn-primary-custom" style="border:none;padding:8px 20px" onclick="saveTerm()"><i class="fas fa-save me-1"></i>Save Term</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QUICK PAYMENT MODAL -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header-grad" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-indian-rupee-sign me-2"></i>Record Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="sales.php">
        <div class="modal-body" style="max-height:none">
          <input type="hidden" name="invoice_id" id="payInvoiceId">
          <div style="background:linear-gradient(135deg,var(--warning-bg),#fff8e7);border:1px solid #fcd34d;border-radius:8px;padding:12px;margin-bottom:14px;text-align:center;">
            <div style="font-size:.72rem;color:var(--warning);font-weight:700;text-transform:uppercase;letter-spacing:.5px">Outstanding Balance</div>
            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.6rem;font-weight:800;color:var(--danger)" id="payOutstanding">₹0.00</div>
            <div style="font-size:.75rem;color:var(--text-muted)" id="payCustomerName"></div>
          </div>
          <div class="mfield">
            <label>Payment Method <span style="color:var(--danger)">*</span></label>
            <select name="pay_method" required>
              <option value="">— Select —</option>
              <option value="Cash">Cash</option>
              <option value="Card">Card</option>
              <option value="UPI">UPI</option>
              <option value="Bank Transfer">Bank Transfer</option>
              <option value="Cheque">Cheque</option>
            </select>
          </div>
          <div class="mfield">
            <label>Amount <span style="color:var(--danger)">*</span></label>
            <input type="number" step="0.01" min="0.01" name="pay_amount" id="payAmountInput" placeholder="0.00" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px">
            <button type="button" id="payFullBtn" onclick="setFullPayment()" style="background:var(--success-bg);color:var(--success);border:1px solid #86efac;border-radius:7px;padding:7px;font-size:.75rem;font-weight:700;cursor:pointer">Pay Full</button>
            <button type="button" id="payHalfBtn" onclick="setHalfPayment()" style="background:var(--warning-bg);color:var(--warning);border:1px solid #fcd34d;border-radius:7px;padding:7px;font-size:.75rem;font-weight:700;cursor:pointer">Pay Half</button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_payment" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif"><i class="fas fa-check me-1"></i>Record</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE MODAL -->
<?php if ($user_type === 'admin'): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header-grad" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
        <h5 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="text-align:center;padding:28px">
        <div style="width:60px;height:60px;background:var(--danger-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.7rem;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i></div>
        <h5 style="font-weight:700;margin-bottom:7px">Delete this sale?</h5>
        <p style="color:var(--text-muted);font-size:.85rem">This action cannot be undone. Product stock will be restored.</p>
        <form method="post" style="margin-top:18px;display:flex;gap:10px;justify-content:center">
          <input type="hidden" name="sale_id" id="deleteSaleId">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="delete_sale" style="background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer"><i class="fas fa-trash me-1"></i>Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- SERIAL MODAL -->
<div class="modal fade" id="serialModal" data-bs-backdrop="static" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header-grad" style="padding: 12px 18px;">
                <h5><i class="fas fa-barcode me-2"></i>Sale Item - Serial No.</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 16px;">
                <p id="serialProductName" style="font-weight: 600; margin-bottom: 8px; color: var(--text-mid);"></p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 0.85rem; font-weight: 600;">Enter Serial No.:</span>
                    <span id="serialCountDisplay" style="font-size: 0.8rem; font-weight: 600; color: var(--brand);">0/1 Entered</span>
                </div>
                <div class="input-group mb-3">
                    <input type="text" id="serialInput" class="form-control" placeholder="Enter/Scan">
                    <button class="btn btn-primary" id="addSerialBtn" style="background: var(--brand); border-color: var(--brand);">Add</button>
                </div>
                <ul id="serialList" class="list-group mb-0" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">
                    <!-- Serial items will go here -->
                </ul>
            </div>
            <div class="modal-footer" style="padding: 10px 16px; justify-content: flex-end;">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveSerialBtn" style="background: var(--brand); border-color: var(--brand);">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- EXPORT MODAL -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header-grad">
        <h5 id="exportModalTitle" style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;margin:0"><i class="fas fa-download me-2"></i>Export Sales</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p style="color:var(--text-muted);font-size:.83rem;margin-bottom:14px">Select time period for export:</p>
        <div class="duration-grid">
          <div class="duration-card" onclick="selectDuration(this,7)"><i class="fas fa-calendar-week"></i><h5>Last 7 Days</h5><p>Past week</p></div>
          <div class="duration-card" onclick="selectDuration(this,30)"><i class="fas fa-calendar-alt"></i><h5>Last Month</h5><p>Past 30 days</p></div>
          <div class="duration-card" onclick="selectDuration(this,180)"><i class="fas fa-calendar"></i><h5>Last 6 Months</h5><p>Past 6 months</p></div>
          <div class="duration-card" onclick="selectDuration(this,365)"><i class="fas fa-calendar-check"></i><h5>Last Year</h5><p>Past 12 months</p></div>
          <div class="duration-card all-data" onclick="selectDuration(this,'all')"><i class="fas fa-database"></i><h5>All Data</h5><p>Complete history</p></div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:14px">
          <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:9px">Custom range:</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div class="mfield" style="margin:0"><label>From</label><input type="date" id="exportFrom"></div>
            <div class="mfield" style="margin:0"><label>To</label><input type="date" id="exportTo"></div>
          </div>
          <button type="button" onclick="applyCustomRange()" style="margin-top:8px;background:var(--brand-light);color:var(--brand);border:1px solid rgba(2,132,199,.2);padding:6px 12px;border-radius:7px;font-size:.78rem;font-weight:600;cursor:pointer"><i class="fas fa-check me-1"></i>Apply</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="proceedExport" class="btn-primary-custom" style="border:none;padding:9px 20px" disabled onclick="doExport()"><i class="fas fa-download"></i><span id="exportBtnText">Export</span></button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const salesData = <?php echo json_encode($sanitized_sales, JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS); ?>;
const productsData = <?php echo json_encode(array_map(function($p){
    $incl = (float)$p['price_per_piece'];
    $sgst = (float)($p['sgst'] ?? 0);
    $cgst = (float)($p['cgst'] ?? 0);
    $igst = (float)($p['igst'] ?? 0);
    $tax = $sgst + $cgst + $igst;
    $excl = $tax > 0 ? $incl / (1 + $tax/100) : $incl;
    return ['id'=>(int)$p['id'],'name'=>$p['product_name'],'stock'=>(int)$p['quantity'],'price'=>$incl,'excl'=>round($excl,4),'tax'=>$tax,'sgst'=>$sgst,'cgst'=>$cgst,'igst'=>$igst];
}, $products)); ?>;

let currentExportType = '', selectedDuration = '', customRange = null, productCount = 0;
let payOutstandingVal = 0;

function buildProductOptions(selectedId) {
    let html = '<option value="">— Select Product —</option>';
    productsData.forEach(p => {
        const sel = (selectedId && p.id == selectedId) ? 'selected' : '';
        let taxLabel = `GST ${p.tax}%`;
        html += `<option value="${p.id}" data-price="${p.price}" data-excl="${p.excl}" data-tax="${p.tax}" data-sgst="${p.sgst}" data-cgst="${p.cgst}" data-igst="${p.igst}" data-stock="${p.stock}" ${sel}>
            ${p.name} | ₹${p.price.toFixed(2)} incl. | ${taxLabel} | Stk:${p.stock}
        </option>`;
    });
    return html;
}

function makeProductRow(idx, item) {
    const qty = item ? item.quantity : '';
    const disc = item ? (item.discount_percent || 0) : 0;
    const pid = item ? item.product_id : '';
    const ttype = item ? (item.transaction_type || 'sale') : 'sale';
    const serialsVal = item ? item.serial_numbers || '' : '';
    const serialsArr = serialsVal ? serialsVal.split(',') : [];
    const serialsBtnHtml = serialsArr.length > 0 
        ? `<i class="fas fa-check text-success"></i> ${serialsArr.length} Added` 
        : `<i class="fas fa-barcode"></i> Add Serials`;
    const serialsBtnStyle = serialsArr.length > 0 ? 'color: var(--text)' : 'color: var(--text-muted)';
    return `
    <div class="product-row" id="prow_${idx}" style="grid-template-columns: 2fr 1.1fr 0.8fr 0.8fr 1.5fr 36px; gap:8px;">
        <div class="mfield" style="margin-bottom:0">
            <label>Product <span style="color:var(--danger)">*</span></label>
            <select class="product-select" id="sales_prod_sel_${idx}" data-idx="${idx}" required>${buildProductOptions(pid)}</select>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Type</label>
            <select class="type-select" name="transaction_type[]" data-idx="${idx}" style="font-weight:700">
                <option value="sale" ${ttype==='sale'?'selected':''}>🏷️ Sale</option>
                <option value="rent" ${ttype==='rent'?'selected':''}>🔄 Rent</option>
            </select>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Qty <span style="color:var(--danger)">*</span></label>
            <input type="number" class="qty-input" data-idx="${idx}" min="1" value="${qty}" placeholder="0" required>
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Disc %</label>
            <input type="number" class="disc-input" data-idx="${idx}" min="0" max="100" step="0.01" value="${disc}" placeholder="0">
        </div>
        <div class="mfield" style="margin-bottom:0">
            <label>Serials</label>
            <div class="input-group input-group-sm" style="flex-wrap: nowrap;">
                <input type="hidden" class="serials-input" data-idx="${idx}" value="${serialsVal}">
                <button type="button" class="btn btn-outline-secondary btn-sm btn-serials w-100" style="border-color: var(--border); background: #fafafa; text-align: left; padding: 7px 9px; font-size: .8rem; ${serialsBtnStyle}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><span class="btn-serials-content">${serialsBtnHtml}</span></button>
            </div>
        </div>
        <div style="display:flex;align-items:flex-end;padding-bottom:0">
            <button type="button" class="remove-product-btn" onclick="removeRow(${idx})"><i class="fas fa-trash"></i></button>
        </div>
    </div>

    <div class="price-preview" id="preview_${idx}">
        <div class="pp-row"><span>Rate (excl. GST)</span><span id="pp_excl_${idx}">—</span></div>
        <div class="pp-row"><span>Taxable Amt</span><span id="pp_taxable_${idx}">—</span></div>
        <div class="pp-tax-detail" id="pp_taxdetail_${idx}"></div>
        <div id="pp_disc_row_${idx}" style="display:none" class="pp-row" style="color:var(--success)"><span>Discount</span><span id="pp_disc_${idx}">—</span></div>
        <div class="pp-row total"><span>Line Total (incl. GST)</span><span id="pp_total_${idx}">—</span></div>
    </div>`;
}

function addRow(item) {
    const idx = productCount++;
    $('#product-list').append(makeProductRow(idx, item));
    $(`#product-list .product-select[data-idx="${idx}"]`).select2({
        placeholder:'— Select Product —', allowClear:true,
        width:'100%', dropdownParent:$('#addSaleModal')
    }).on('change', function(){ updateRowPreview(idx); updateTotal(); });
    if (item) setTimeout(() => { updateRowPreview(idx); }, 120);
    updateTotal();
}

function removeRow(idx) {
    if ($('.product-row').length <= 1) { alert('At least one product is required.'); return; }
    $(`#prow_${idx}, #preview_${idx}`).remove();
    updateTotal();
}

function updateRowPreview(idx) {
    const sel = $(`#product-list .product-select[data-idx="${idx}"]`);
    const opt = sel.find('option:selected');
    const qty = parseFloat($(`#product-list .qty-input[data-idx="${idx}"]`).val() || 0);
    const disc = parseFloat($(`#product-list .disc-input[data-idx="${idx}"]`).val() || 0) / 100;
    const excl = parseFloat(opt.data('excl') || 0);
    const sgst = parseFloat(opt.data('sgst') || 0) / 100;
    const cgst = parseFloat(opt.data('cgst') || 0) / 100;
    const igst = parseFloat(opt.data('igst') || 0) / 100;
    const prev = $(`#preview_${idx}`);
    if (!sel.val() || qty <= 0) { prev.removeClass('show'); return; }

    const grossExcl = excl * qty;
    const discAmt = grossExcl * disc;
    const taxable = grossExcl - discAmt;
    const sgstAmt = taxable * sgst;
    const cgstAmt = taxable * cgst;
    const igstAmt = taxable * igst;
    const taxAmt = sgstAmt + cgstAmt + igstAmt;
    const lineTotal = taxable + taxAmt;

    $(`#pp_excl_${idx}`).text('₹' + grossExcl.toFixed(2));
    $(`#pp_taxable_${idx}`).text('₹' + taxable.toFixed(2));

    let taxHtml = '';
    if (sgstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-sgst">SGST ${parseFloat(opt.data('sgst')||0).toFixed(1)}% ₹${sgstAmt.toFixed(2)}</span>`;
    if (cgstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-cgst">CGST ${parseFloat(opt.data('cgst')||0).toFixed(1)}% ₹${cgstAmt.toFixed(2)}</span>`;
    if (igstAmt > 0) taxHtml += `<span class="pp-tax-chip tax-igst">IGST ${parseFloat(opt.data('igst')||0).toFixed(1)}% ₹${igstAmt.toFixed(2)}</span>`;
    if (taxAmt === 0) taxHtml += `<span class="pp-tax-chip tax-rate-chip">GST 0%</span>`;
    $(`#pp_taxdetail_${idx}`).html(taxHtml);

    if (disc > 0) { $(`#pp_disc_row_${idx}`).show(); $(`#pp_disc_${idx}`).text('– ₹' + discAmt.toFixed(2)); }
    else $(`#pp_disc_row_${idx}`).hide();

    $(`#pp_total_${idx}`).text('₹' + lineTotal.toFixed(2));
    prev.addClass('show');
}

function updateTotal() {
    let subtotal=0, discount=0, taxable=0, sgstSum=0, cgstSum=0, igstSum=0, taxSum=0, total=0;

    $('.product-row').each(function(){
        const sel = $(this).find('.product-select');
        const opt = sel.find('option:selected');
        const qty = parseFloat($(this).find('.qty-input').val() || 0);
        const disc = parseFloat($(this).find('.disc-input').val() || 0) / 100;
        const excl = parseFloat(opt.data('excl') || 0);
        const sgst = parseFloat(opt.data('sgst') || 0) / 100;
        const cgst = parseFloat(opt.data('cgst') || 0) / 100;
        const igst = parseFloat(opt.data('igst') || 0) / 100;
        if (!sel.val() || qty <= 0) return;
        const gross = excl * qty;
        const discAmt = gross * disc;
        const tax_able = gross - discAmt;
        const sa = tax_able * sgst, ca = tax_able * cgst, ia = tax_able * igst;
        subtotal += gross;
        discount += discAmt;
        taxable += tax_able;
        sgstSum += sa;
        cgstSum += ca;
        igstSum += ia;
        taxSum += (sa + ca + ia);
        total += tax_able + sa + ca + ia;
    });

    $('#td_subtotal').text('₹' + subtotal.toFixed(2));
    $('#td_discount').text('– ₹' + discount.toFixed(2));
    $('#td_taxable').text('₹' + taxable.toFixed(2));
    $('#td_sgst').text('₹' + sgstSum.toFixed(2));
    $('#td_cgst').text('₹' + cgstSum.toFixed(2));
    $('#td_igst').text('₹' + igstSum.toFixed(2));
    $('#td_tax').text('₹' + taxSum.toFixed(2));
    $('#td_total').text('₹' + total.toFixed(2));

    sgstSum > 0 ? $('#td_sgst_row').show() : $('#td_sgst_row').hide();
    cgstSum > 0 ? $('#td_cgst_row').show() : $('#td_cgst_row').hide();
    igstSum > 0 ? $('#td_igst_row').show() : $('#td_igst_row').hide();

    const tdsPercent = parseFloat($('#tds_percent').val() || 0);
    const tdsAmount = total * (tdsPercent / 100);
    const finalPayable = total - tdsAmount;

    $('#tds_percent_lbl').text(tdsPercent);
    $('#td_tds').text('– ₹' + tdsAmount.toFixed(2));
    $('#tds_amount_display').val('₹' + tdsAmount.toFixed(2));
    if (tdsPercent > 0) $('#td_tds_row').show(); else $('#td_tds_row').hide();

    $('#td_total').text('₹' + finalPayable.toFixed(2));

    const paid = parseFloat($('#paid_amount').val() || 0);
    const hint = $('#paymentHint');
    hint.show().removeClass('full partial none');
    if (finalPayable <= 0) { hint.hide(); return finalPayable; }
    const due = finalPayable - paid;

    if (paid <= 0) {
        hint.addClass('none').html('<i class="fas fa-clock me-1"></i>No payment — Invoice will be <strong>Pending</strong>');
    } else if (due > 0.005) {
        hint.addClass('partial').html('<i class="fas fa-circle-half-stroke me-1"></i>Due ₹' + due.toFixed(2) + ' — Invoice will be <strong>Partial</strong>');
    } else {
        hint.addClass('full').html('<i class="fas fa-check-circle me-1"></i>Fully paid — Invoice will be <strong>Paid</strong>');
    }
    return total;
}

$(function(){
    function initCustomerSelect2() {
        if (!$.fn.select2) return;
        $('#customer_name_select').select2({placeholder:'Search or select customer…', allowClear:true, width:'100%', dropdownParent:$('#addSaleModal')});
    }

    $(document).on('change','#customer_name_select', function(){
        const opt = $(this).find('option:selected');
        $('#customer_phone_add').val(opt.data('phone') || '');
        $('#customer_address_add').val(opt.data('address') || '');
    });

    $(document).on('input','#paid_amount', updateTotal);
    $(document).on('input','.qty-input, .disc-input', function(){
        const idx = $(this).data('idx');
        updateRowPreview(idx); updateTotal();
    });

    $('#addProductBtn').on('click', function(){ addRow(null); });

    $('#addSaleModal').on('shown.bs.modal', function(){
        initCustomerSelect2();
        if ($('.product-row').length === 0) addRow(null);
    });

    $('#addSaleModal').on('hidden.bs.modal', function(){
        $('#addSaleForm')[0].reset();
        try { $('#customer_name_select').val(null).trigger('change'); } catch(e){}
        $('#product-list').empty();
        productCount = 0;
        $('#products_json, #invoice_id').val('');
        $('#customer_phone_add, #customer_address_add, #paid_amount, #payment_method').val('');
        $('#paymentHint').hide();
        $('#saleModalTitle').html('<i class="fas fa-plus-circle me-2"></i>New Sale');
        $('#saleSubmitBtn').attr('name','add_sale');
        $('#saleSubmitText').text('Save Sale');
        updateTotal();
    });

    $('#addSaleForm').on('submit', function(e){
        const products = []; let hasError = false;
        $('.product-row').each(function(){
            const pid = $(this).find('.product-select').val();
            const qty = parseInt($(this).find('.qty-input').val() || 0);
            const disc = parseFloat($(this).find('.disc-input').val() || 0);
            const ttype = $(this).find('.type-select').val() || 'sale';
            const serials = $(this).find('.serials-input').val() || '';
            if (!pid || qty <= 0) { hasError = true; return; }
            products.push({product_id: pid, quantity: qty, discount: disc, transaction_type: ttype, serial_numbers: serials});
        });
        if (hasError || !products.length) {
            e.preventDefault(); alert('Please select a product and enter valid quantity for all rows.'); return false;
        }
        const total = updateTotal();
        const paid = parseFloat($('#paid_amount').val() || 0);
        if (paid > 0 && paid > total + 0.01) {
            e.preventDefault(); alert('Paid amount (₹' + paid.toFixed(2) + ') cannot exceed invoice total (₹' + total.toFixed(2) + ').'); return false;
        }
        $('#products_json').val(JSON.stringify(products));
        return true;
    });

    $(document).on('click', '.whatsapp', function(e){
        e.preventDefault();
        try {
            const ids = $(this).data('sale-ids');
            const sale = salesData.find(s => JSON.stringify(s.ids) === JSON.stringify(ids));
            if (!sale) { alert('Sale data not found!'); return; }
            let phone = (sale.customer_phone || '').replace(/\D/g, '');
            if (phone.length === 10) phone = '91' + phone;
            if (!phone) { alert('Phone not available.'); return; }
            const invoiceLink = 'https://growbiller.com/sale_invoice.php?invoice_id=' + sale.invoice_id;
            const prods = sale.products.map(p => '  • ' + p.product_name + ' x ' + p.quantity).join('\n');
            const msg = 'Hello ' + sale.customer_name + '\n\nYour invoice is ready!\n\nInvoice #' + sale.invoice_id + '\nProducts:\n' + prods + '\nTotal: ₹' + sale.total_price.toFixed(2) + '\nPaid: ₹' + sale.paid_amount.toFixed(2) + '\n\nView Invoice:\n' + invoiceLink + '\n\nThank you!';
            window.open('https://wa.me/' + phone + '?text=' + encodeURIComponent(msg), '_blank');
        } catch(err) { console.error(err); alert('WhatsApp failed!'); }
    });

    const sAlert = document.getElementById('successAlert');
    if (sAlert) setTimeout(() => { sAlert.style.transition='opacity .5s'; sAlert.style.opacity='0'; setTimeout(()=>sAlert.remove(),500); }, 4000);
    updateTableCount();
});

// --- SERIAL MODAL LOGIC ---
let currentSerialRowIdx = null;
let tempSerials = [];
let targetQty = 0;

$(document).on('click', '.btn-serials', function() {
    const row = $(this).closest('.product-row');
    const idx = $(this).siblings('.serials-input').data('idx');
    currentSerialRowIdx = idx;
    
    const productSelect = row.find('.product-select');
    const productId = productSelect.val();
    
    if (!productId) {
        alert('Please select a product first.');
        return;
    }
    
    const productName = productSelect.find('option:selected').text().split('|')[0].trim();
    targetQty = parseInt(row.find('.qty-input').val()) || 1;
    
    const existingSerials = row.find('.serials-input').val();
    tempSerials = existingSerials ? existingSerials.split(',') : [];
    
    $('#serialProductName').text(productName);
    $('#serialInput').val('');
    updateSerialUI();
    
    const modal = new bootstrap.Modal(document.getElementById('serialModal'));
    modal.show();
    
    setTimeout(() => $('#serialInput').focus(), 500);
});

function updateSerialUI() {
    $('#serialCountDisplay').text(`${tempSerials.length}/${targetQty} Entered`);
    
    let listHtml = '';
    tempSerials.forEach((sn, index) => {
        listHtml += `<li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
            ${sn}
            <button type="button" class="btn btn-sm text-danger remove-serial-btn" data-index="${index}" style="padding: 0; background: none; border: none;"><i class="fas fa-times"></i></button>
        </li>`;
    });
    $('#serialList').html(listHtml);
    
    if (tempSerials.length >= targetQty) {
        $('#serialInput').prop('disabled', true);
        $('#addSerialBtn').prop('disabled', true);
    } else {
        $('#serialInput').prop('disabled', false);
        $('#addSerialBtn').prop('disabled', false);
    }
}

$('#addSerialBtn').on('click', function() {
    const sn = $('#serialInput').val().trim();
    if (!sn) return;
    
    if (tempSerials.includes(sn)) {
        alert('This serial number is already added.');
        return;
    }
    
    if (tempSerials.length < targetQty) {
        tempSerials.push(sn);
        $('#serialInput').val('');
        updateSerialUI();
        $('#serialInput').focus();
    }
});

$('#serialInput').on('keypress', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        $('#addSerialBtn').click();
    }
});

$(document).on('click', '.remove-serial-btn', function() {
    const index = $(this).data('index');
    tempSerials.splice(index, 1);
    updateSerialUI();
});

$('#saveSerialBtn').on('click', function() {
    if (tempSerials.length > 0 && tempSerials.length !== targetQty) {
         if(!confirm(`You have entered ${tempSerials.length} out of ${targetQty} serial numbers. Are you sure you want to save?`)) {
             return;
         }
    }
    
    const row = $(`#prow_${currentSerialRowIdx}`);
    row.find('.serials-input').val(tempSerials.join(','));
    
    const btn = row.find('.btn-serials');
    if (tempSerials.length > 0) {
        btn.find('.btn-serials-content').html(`<i class="fas fa-check text-success"></i> ${tempSerials.length} Added`);
        btn.css('color', 'var(--text)');
    } else {
        btn.find('.btn-serials-content').html('<i class="fas fa-barcode"></i> Add Serials');
        btn.css('color', 'var(--text-muted)');
    }
    
    bootstrap.Modal.getInstance(document.getElementById('serialModal')).hide();
});

$(document).on('change input', '.qty-input', function() {
    const idx = $(this).data('idx');
    const row = $(`#prow_${idx}`);
    const newQty = parseInt($(this).val()) || 1;
    const existing = row.find('.serials-input').val();
    let arr = existing ? existing.split(',') : [];
    if (arr.length > newQty) {
        arr = arr.slice(0, newQty);
        row.find('.serials-input').val(arr.join(','));
        const btn = row.find('.btn-serials');
        btn.find('.btn-serials-content').html(`<i class="fas fa-check text-success"></i> ${arr.length} Added`);
        btn.css('color', 'var(--text)');
    }
});

function editSale(sale) {
    try {
        $('#invoice_id').val(sale.invoice_id || (sale.ids && sale.ids.length ? sale.ids[0] : ''));
        const cname = sale.customer_name || '';
        const $sel = $('#customer_name_select');
        if (cname && !$sel.find('option[value="' + cname + '"]').length)
            $sel.append(new Option(cname, cname, true, true));
        try { $sel.val(cname).trigger('change'); } catch(e) { $sel.val(cname); }
        $('#customer_phone_add').val(sale.customer_phone || '');
        $('#customer_address_add').val(sale.customer_address || '');
        $('#product-list').empty();
        productCount = 0;
        (sale.products || []).forEach(p => addRow(p));
        $('#payment_method').val(sale.payment_methods ? sale.payment_methods.split(',')[0].trim() : '');
        $('#paid_amount').val(sale.paid_amount || '');
        $('#saleModalTitle').html('<i class="fas fa-pen me-2"></i>Edit Sale');
        $('#saleSubmitBtn').attr('name','edit_sale');
        $('#saleSubmitText').text('Update Sale');
        
        $('#terms_text').val(sale.terms_text || '');
        const matchingTerm = termsList.find(t => t.content === (sale.terms_text || '').trim());
        if (matchingTerm) {
            $('#terms_title_select').val(matchingTerm.id);
        } else {
            $('#terms_title_select').val('');
        }
        
        setTimeout(updateTotal, 200);
    } catch(err) { console.error(err); alert('Failed to load sale: ' + err.message); }
}

function openPaymentModal(invoiceId, outstanding, customerName) {
    payOutstandingVal = outstanding;
    document.getElementById('payInvoiceId').value = invoiceId;
    document.getElementById('payOutstanding').textContent = '₹' + outstanding.toFixed(2);
    document.getElementById('payCustomerName').textContent = customerName;
    document.getElementById('payAmountInput').value = outstanding.toFixed(2);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}
function setFullPayment() { document.getElementById('payAmountInput').value = payOutstandingVal.toFixed(2); }
function setHalfPayment() { document.getElementById('payAmountInput').value = (payOutstandingVal / 2).toFixed(2); }

function filterTable() {
    const q = (document.getElementById('tableSearch').value || '').toLowerCase();
    const status = document.getElementById('statusFilter').value;
    let visible = 0;
    document.querySelectorAll('#salesTableBody tr[data-search]').forEach(tr => {
        const matchQ = !q || tr.dataset.search.includes(q);
        const matchS = !status || tr.dataset.status === status;
        tr.style.display = (matchQ && matchS) ? '' : 'none';
        if (matchQ && matchS) visible++;
    });
    updateTableCount(visible);
}
function updateTableCount(n) {
    const total = document.querySelectorAll('#salesTableBody tr[data-search]').length;
    const count = (n === undefined) ? total : n;
    const el = document.getElementById('tableCount');
    if (el) el.textContent = count + ' record' + (count !== 1 ? 's' : '');
}

function showExportModal(type) {
    currentExportType = type; selectedDuration = ''; customRange = null;
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    document.getElementById('proceedExport').disabled = true;
    document.getElementById('exportBtnText').textContent = 'Export';
    document.getElementById('exportModalTitle').innerHTML = '<i class="fas fa-download me-2"></i>Export as ' + type.toUpperCase();
    new bootstrap.Modal(document.getElementById('exportModal')).show();
}
function selectDuration(el, dur) {
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active'); selectedDuration = dur; customRange = null;
    document.getElementById('exportFrom').value = '';
    document.getElementById('exportTo').value = '';
    document.getElementById('proceedExport').disabled = false;
    document.getElementById('exportBtnText').textContent = 'Export ' + currentExportType.toUpperCase();
}
function applyCustomRange() {
    const f = document.getElementById('exportFrom').value;
    const t = document.getElementById('exportTo').value;
    if (!f || !t) { alert('Select both dates.'); return; }
    if (new Date(f) > new Date(t)) { alert('Start date cannot be after end date.'); return; }
    document.querySelectorAll('.duration-card').forEach(c => c.classList.remove('active'));
    selectedDuration = 'custom'; customRange = {start:f, end:t};
    document.getElementById('proceedExport').disabled = false;
    document.getElementById('exportBtnText').textContent = 'Export ' + currentExportType.toUpperCase() + ' (Custom)';
}
function getFilteredData() {
    if (selectedDuration === 'all') return salesData;
    if (selectedDuration === 'custom') {
        const s = new Date(customRange.start); const e = new Date(customRange.end); e.setHours(23,59,59,999);
        return salesData.filter(d => { const dt = new Date(d.created_at); return dt >= s && dt <= e; });
    }
    const cutoff = new Date(Date.now() - parseInt(selectedDuration) * 86400000);
    return salesData.filter(d => new Date(d.created_at) >= cutoff);
}
function doExport() {
    const data = getFilteredData();
    bootstrap.Modal.getInstance(document.getElementById('exportModal')).hide();
    if (currentExportType === 'pdf') exportPDF(data);
    else exportExcel(data);
}
function exportPDF(data) {
    const {jsPDF} = window.jspdf; const doc = new jsPDF();
    const pw = doc.internal.pageSize.width;
    doc.setFillColor(2,132,199); doc.rect(0,0,pw,30,'F');
    doc.setTextColor(255,255,255); doc.setFontSize(16); doc.setFont(undefined,'bold');
    doc.text('Sales Report', pw/2, 14, {align:'center'});
    doc.setFontSize(9); doc.setFont(undefined,'normal');
    doc.text('Generated: ' + new Date().toLocaleDateString('en-IN') + ' | Records: ' + data.length, pw/2, 25, {align:'center'});
    doc.setTextColor(30,30,30);
    const rows = data.map((s,i) => [
        i+1, '#'+s.invoice_id, s.customer_name, s.customer_phone||'—',
        s.products.map(p=>p.product_name + '(x' + p.quantity + ')').join(', '),
        '₹'+s.total_excl.toFixed(2),
        '₹'+s.total_sgst.toFixed(2), '₹'+s.total_cgst.toFixed(2), '₹'+s.total_igst.toFixed(2),
        '₹'+s.total_price.toFixed(2), '₹'+s.paid_amount.toFixed(2),
        s.payment_status.toUpperCase(), new Date(s.created_at).toLocaleDateString('en-IN'), s.created_by_name
    ]);
    doc.autoTable({
        head:[['#','Invoice','Customer','Phone','Products','Taxable','SGST','CGST','IGST','Total','Paid','Status','Date','By']],
        body: rows, startY:38,
        styles:{fontSize:6.5, cellPadding:2},
        headStyles:{fillColor:[2,132,199], textColor:255, fontStyle:'bold'},
        alternateRowStyles:{fillColor:[240,249,255]},
        margin:{left:5, right:5}
    });
    doc.save('sales-' + (selectedDuration === 'custom' ? customRange.start + '_' + customRange.end : selectedDuration) + '.pdf');
}
function exportExcel(data) {
    const ws_data = [['#','Invoice','Customer','Phone','Address','Products','Taxable Amt','SGST','CGST','IGST','Total GST','Total (incl. GST)','Paid','Outstanding','Status','Date','Created By']];
    data.forEach((s,i) => {
        ws_data.push([
            i+1, '#'+s.invoice_id, s.customer_name, s.customer_phone||'', s.customer_address||'',
            s.products.map(p=>p.product_name + 'x' + p.quantity).join('; '),
            s.total_excl.toFixed(2), s.total_sgst.toFixed(2), s.total_cgst.toFixed(2), s.total_igst.toFixed(2),
            s.total_tax.toFixed(2), s.total_price.toFixed(2), s.paid_amount.toFixed(2), s.outstanding.toFixed(2),
            s.payment_status, new Date(s.created_at).toLocaleDateString('en-IN'), s.created_by_name
        ]);
    });
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb,'Sales',ws);
    XLSX.writeFile(wb, 'sales-' + (selectedDuration === 'custom' ? customRange.start + '_' + customRange.end : selectedDuration) + '.xlsx');
}

// --- TERMS & CONDITIONS LOGIC ---
let termsList = [];

function fetchTerms() {
    $.post('sales.php', { action: 'manage_terms', operation: 'fetch' }, function(res) {
        if (res.status === 'success') {
            termsList = res.data;
            updateTermsDropdowns();
        }
    }, 'json');
}

function updateTermsDropdowns() {
    const $mainSel = $('#terms_title_select');
    const $manageSel = $('#manage_terms_select');
    
    // Store selected value
    const currentMain = $mainSel.val();
    
    $mainSel.empty().append('<option value="">None</option>');
    $manageSel.empty().append('<option value="">-- Add New Term --</option>');
    
    termsList.forEach(t => {
        $mainSel.append(`<option value="${t.id}">${t.title}</option>`);
        $manageSel.append(`<option value="${t.id}">${t.title}</option>`);
    });
    
    // Restore selection if exists
    if ($mainSel.find(`option[value="${currentMain}"]`).length) {
        $mainSel.val(currentMain);
    }
}
function applyTermContent() {
    const termId = $('#terms_title_select').val();
    if (!termId) {
        $('#terms_text').val('');
        return;
    }
    const term = termsList.find(t => t.id == termId);
    if (term) {
        $('#terms_text').val(term.content);
    }
}
function prepareManageTermsModal() {
    $('#manage_term_id').val('');
    $('#manage_term_title').val('');
    $('#manage_term_content').val('');
    $('#manage_terms_select').val('');
    $('#btnDeleteTerm').hide();
}

function openManageTermsModal() {
    prepareManageTermsModal();
    const modalEl = document.getElementById('manageTermsModal');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

// nested modal me typing allow karne ke liye (fix Bootstrap focus trap)
document.addEventListener('focusin', function(e) {
    if (e.target.closest('#manageTermsModal')) {
        e.stopImmediatePropagation();
    }
}, true);

function loadTermToEdit() {
    const termId = $('#manage_terms_select').val();
    if (!termId) {
        $('#manage_term_id').val('');
        $('#manage_term_title').val('');
        $('#manage_term_content').val('');
        $('#btnDeleteTerm').hide();
        return;
    }
    const term = termsList.find(t => t.id == termId);
    if (term) {
        $('#manage_term_id').val(term.id);
        $('#manage_term_title').val(term.title);
        $('#manage_term_content').val(term.content);
        $('#btnDeleteTerm').show();
    }
}

function saveTerm() {
    try {
        const id = $('#manage_term_id').val();
        const title = $('#manage_term_title').val();
        const content = $('#manage_term_content').val();
        
        if (!title || !title.trim() || !content || !content.trim()) {
            alert("Title and content are required.");
            return;
        }
        
        const op = id ? 'edit' : 'add';
        const payload = { action: 'manage_terms', operation: op, title: title.trim(), content: content.trim() };
        if (id) payload.id = id;
        
        // Show loading state on button
        const $btn = $('#manageTermsModal .btn-primary-custom');
        const origText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $btn.prop('disabled', true);
        
        $.post('sales.php', payload, function(res) {
            $btn.html(origText).prop('disabled', false);
            if (res && res.status === 'success') {
                fetchTerms();
                // Safely hide the modal by clicking its own cancel button
                $('#manageTermsModal').find('.btn-secondary[data-bs-dismiss="modal"]').click();
                
                setTimeout(() => {
                    const newId = id ? id : res.id;
                    $('#terms_title_select').val(newId);
                    applyTermContent();
                }, 300);
            } else {
                alert((res && res.message) ? res.message : "Failed to save term (unknown server error).");
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            $btn.html(origText).prop('disabled', false);
            alert("Network/Server Error: " + textStatus + "\n" + jqXHR.responseText.substring(0, 100));
        });
    } catch (e) {
        alert("Javascript Error: " + e.message);
    }
}

function deleteTerm() {
    const id = $('#manage_term_id').val();
    if (!id) return;
    if (!confirm("Are you sure you want to delete this term?")) return;
    
    $.post('sales.php', { action: 'manage_terms', operation: 'delete', id: id }, function(res) {
        if (res.status === 'success') {
            fetchTerms();
            // Safely hide the modal by clicking its own cancel button
            $('#manageTermsModal').find('.btn-secondary[data-bs-dismiss="modal"]').click();
            if ($('#terms_title_select').val() == id) {
                $('#terms_title_select').val('');
                $('#terms_text').val('');
            }
        } else {
            alert(res.message || "Failed to delete term.");
        }
    }, 'json');
}

$(document).ready(function() {
    fetchTerms();
});

// Update reset form to clear terms
const origResetForm = window.resetForm;
window.resetForm = function() {
    if (origResetForm) origResetForm();
    $('#terms_title_select').val('');
    $('#terms_text').val('');
};
</script>
</body>
</html>
<?php ob_end_flush(); ?>