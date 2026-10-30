<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get user information
$user_type = $_SESSION['user_type'] ?? 'admin';
$created_by_id = ($user_type === 'staff' && isset($_SESSION['created_by'])) ? (int)$_SESSION['created_by'] : (int)$_SESSION['user_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $challan_id = (int)($_POST['challan_id'] ?? 0);
        
        if (!$challan_id) {
            throw new Exception("Please provide a valid Challan ID.");
        }
        
        // Fetch challan details
        $stmt = $conn->prepare("
            SELECT dc.*, 
                   CASE 
                       WHEN admin_users.id IS NOT NULL THEN 'Admin'
                       WHEN staff_users.name IS NOT NULL THEN staff_users.name
                       ELSE 'Unknown'
                   END AS created_by_name
            FROM delivery_challans dc
            LEFT JOIN users admin_users ON dc.created_by = admin_users.id
            LEFT JOIN create_users staff_users ON dc.created_by = staff_users.id
            WHERE dc.id = ?
        ");
        $stmt->bind_param("i", $challan_id);
        $stmt->execute();
        $challan = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$challan) {
            throw new Exception("Delivery challan not found.");
        }
        
        // Fetch challan items
        $stmt = $conn->prepare("
            SELECT * FROM delivery_challan_items 
            WHERE challan_id = ? 
            ORDER BY id
        ");
        $stmt->bind_param("i", $challan_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'challan' => $challan,
                'items' => $items
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}

$conn->close();
?>
