<?php
require_once 'config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'You must be logged in to perform this action.';
    header('Location: login.php');
    exit();
}

// Check if bill ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid bill ID';
    header('Location: cash_bill_list.php');
    exit();
}

$bill_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$is_admin = $_SESSION['is_admin'] ?? 0;

// Begin transaction
$conn->begin_transaction();

try {
    // First, check if bill exists and get its details
    $check_sql = "SELECT * FROM cash_bills WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $bill_id);
    $check_stmt->execute();
    $bill = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if (!$bill) {
        throw new Exception('Bill not found');
    }

    // Delete related payments first (if any)
    $delete_payments_sql = "DELETE FROM cash_bill_payments WHERE cash_bill_id = ?";
    $delete_payments_stmt = $conn->prepare($delete_payments_sql);
    $delete_payments_stmt->bind_param("i", $bill_id);
    $delete_payments_stmt->execute();
    $delete_payments_stmt->close();

    // Delete related items
    $delete_items_sql = "DELETE FROM cash_bill_items WHERE cash_bill_id = ?";
    $delete_items_stmt = $conn->prepare($delete_items_sql);
    $delete_items_stmt->bind_param("i", $bill_id);
    $delete_items_stmt->execute();
    $delete_items_stmt->close();

    // Finally, delete the bill
    $delete_bill_sql = "DELETE FROM cash_bills WHERE id = ?";
    $delete_bill_stmt = $conn->prepare($delete_bill_sql);
    $delete_bill_stmt->bind_param("i", $bill_id);
    $delete_bill_stmt->execute();
    $rows_affected = $delete_bill_stmt->affected_rows;
    $delete_bill_stmt->close();

    if ($rows_affected > 0) {
        // If everything is successful, commit the transaction
        $conn->commit();
        $_SESSION['success_message'] = 'Bill #' . $bill['bill_number'] . ' has been deleted successfully.';
    } else {
        throw new Exception('Failed to delete bill');
    }

} catch (Exception $e) {
    // If any error occurs, rollback the transaction
    $conn->rollback();
    $_SESSION['error_message'] = 'Error deleting bill: ' . $e->getMessage();
    error_log('Delete Bill Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
}

// Redirect back to the cash bill list
header('Location: cash_bill_list.php');
exit();
?>
