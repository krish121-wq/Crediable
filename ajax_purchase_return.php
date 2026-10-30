<?php
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

header('Content-Type: application/json');
$response = ['error' => null];

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

try {
    if (!isset($_POST['action']) || !isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $response['error'] = 'Invalid or missing CSRF token';
        echo json_encode($response);
        exit();
    }

    $action = $_POST['action'];
    $created_by_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($action === 'fetch_purchase') {
        $purchase_entry_no = isset($_POST['purchase_bill_id']) ? trim($_POST['purchase_bill_id']) : '';
        if (empty($purchase_entry_no)) {
            $response['error'] = 'Purchase Entry No is required';
            echo json_encode($response);
            exit();
        }

        $query = "SELECT p.id, p.cgst, p.sgst, p.igst
                  FROM purchases p
                  WHERE p.entry_no = ? AND p.created_at <= NOW()";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $purchase_entry_no);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $purchase = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$purchase) {
            $response['error'] = 'Purchase not found';
            echo json_encode($response);
            exit();
        }

        $query = "SELECT pi.id, pi.hsn_code, pi.purchase_price, pi.quantity, 
                         (SELECT product_name FROM products WHERE id = pi.product_id) as product_name
                  FROM purchase_items pi
                  WHERE pi.purchase_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $purchase['id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        $response = array_merge($purchase, ['items' => $items]);
        echo json_encode($response);
        exit();
    }

    if ($action === 'view_return') {
        $view_id = isset($_POST['view_id']) ? (int)$_POST['view_id'] : 0;
        if ($view_id <= 0) {
            $response['error'] = 'Invalid purchase return ID';
            echo json_encode($response);
            exit();
        }

        $query = "SELECT pr.purchase_bill_id, pr.gross_amount, pr.cgst, pr.sgst, pr.igst, pr.total_discount, pr.net_amount
                  FROM purchase_returns pr
                  WHERE pr.id = ? AND pr.added_by = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $view_id, $created_by_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $return = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$return) {
            $response['error'] = 'Purchase return not found';
            echo json_encode($response);
            exit();
        }

        $query = "SELECT pri.purchase_item_id, pri.product_name, pri.hsn_code, pri.per_piece_price, pri.return_qty
                  FROM purchase_return_items pri
                  WHERE pri.purchase_return_id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $view_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);

        $response = array_merge($return, ['items' => $items]);
        echo json_encode($response);
        exit();
    }

    if (isset($_POST['update_purchase'])) {
        $purchase_id = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $gross_amount = isset($_POST['gross_amount']) ? (float)$_POST['gross_amount'] : 0;
        $total_discount = isset($_POST['total_discount']) ? (float)$_POST['total_discount'] : 0;
        $net_amount = isset($_POST['net_amount']) ? (float)$_POST['net_amount'] : 0;

        if (empty($purchase_id)) {
            $response['error'] = 'Purchase ID is required';
            echo json_encode($response);
            exit();
        }

        $query = "SELECT id FROM purchases WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $purchase_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!mysqli_fetch_assoc($result)) {
            $response['error'] = 'Invalid purchase ID';
            echo json_encode($response);
            mysqli_stmt_close($stmt);
            exit();
        }
        mysqli_stmt_close($stmt);

        $items = [];
        if (isset($_POST['purchase_item_id']) && is_array($_POST['purchase_item_id'])) {
            foreach ($_POST['purchase_item_id'] as $index => $item_id) {
                if (empty($item_id)) continue;
                $quantity = isset($_POST['quantity'][$index]) ? (int)$_POST['quantity'][$index] : 0;
                $amount = isset($_POST['amount'][$index]) ? (float)$_POST['amount'][$index] : 0;
                if ($quantity < 0) {
                    $response['error'] = 'Invalid quantity for item at index ' . $index;
                    echo json_encode($response);
                    exit();
                }
                $items[] = [
                    'purchase_item_id' => (int)$item_id,
                    'quantity' => $quantity,
                    'amount' => $amount
                ];
            }
        }

        if (empty($items)) {
            $response['error'] = 'At least one item is required';
            echo json_encode($response);
            exit();
        }

        mysqli_begin_transaction($conn);

        try {
            // Update purchase_returns table
            $query = "UPDATE purchase_returns SET gross_amount = ?, total_discount = ?, net_amount = ?
                      WHERE purchase_bill_id = ? AND added_by = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "dddi", $gross_amount, $total_discount, $net_amount, $purchase_id, $created_by_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Delete existing return items
            $query = "DELETE FROM purchase_return_items WHERE purchase_return_id IN
                      (SELECT id FROM purchase_returns WHERE purchase_bill_id = ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "i", $purchase_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Insert updated return items
            $query = "INSERT INTO purchase_return_items (purchase_return_id, purchase_item_id, product_name, hsn_code, per_piece_price, qty, return_qty, amount)
                      VALUES ((SELECT id FROM purchase_returns WHERE purchase_bill_id = ?), ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            foreach ($items as $item) {
                $product_query = "SELECT p.product_name, pi.hsn_code, pi.purchase_price, pi.quantity
                                  FROM purchase_items pi
                                  JOIN products p ON pi.product_id = p.id
                                  WHERE pi.id = ?";
                $product_stmt = mysqli_prepare($conn, $product_query);
                mysqli_stmt_bind_param($product_stmt, "i", $item['purchase_item_id']);
                mysqli_stmt_execute($product_stmt);
                $product_result = mysqli_stmt_get_result($product_stmt);
                $product = mysqli_fetch_assoc($product_result);
                mysqli_stmt_close($product_stmt);

                mysqli_stmt_bind_param($stmt, "iissdiid", $purchase_id, $item['purchase_item_id'], $product['product_name'],
                                      $product['hsn_code'], $product['purchase_price'], $product['quantity'], $item['quantity'], $item['amount']);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            // Update purchase_items table
            $query = "UPDATE purchase_items SET quantity = ?, amount = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            foreach ($items as $item) {
                mysqli_stmt_bind_param($stmt, "idi", $item['quantity'], $item['amount'], $item['purchase_item_id']);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);

            // Update purchases table
            $query = "UPDATE purchases SET gross_amount = ?, net_amount = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ddi", $gross_amount, $net_amount, $purchase_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $response['success'] = true;
            echo json_encode($response);
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response['error'] = 'Database error: ' . $e->getMessage();
            error_log("Update purchase error: " . $e->getMessage());
            echo json_encode($response);
            exit();
        }
    }

    $response['error'] = 'Invalid action';
    echo json_encode($response);
    exit();
} catch (Exception $e) {
    $response['error'] = 'Server error: ' . $e->getMessage();
    error_log("AJAX error: " . $e->getMessage());
    echo json_encode($response);
    exit();
}
?>