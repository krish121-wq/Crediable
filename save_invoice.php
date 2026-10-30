<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$invoice_dir = __DIR__ . "/invoices/";

if (!is_dir($invoice_dir)) {
    mkdir($invoice_dir, 0755, true);
    file_put_contents('debug.log', "Created invoices directory: $invoice_dir\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pdf_data']) && isset($_POST['filename'])) {
    $pdf_data = $_POST['pdf_data'];
    $filename = basename($_POST['filename']);
    $file_path = $invoice_dir . $filename;

    try {
        if (file_put_contents($file_path, base64_decode($pdf_data))) {
            chmod($file_path, 0644);
            echo json_encode(['status' => 'success', 'file' => $filename]);
        } else {
            throw new Exception("Failed to save PDF to $file_path.");
        }
    } catch (Exception $e) {
        error_log("Save Invoice Error: " . $e->getMessage());
        file_put_contents('debug.log', "Save Invoice Error: " . $e->getMessage() . " | Line: " . $e->getLine() . "\n", FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>