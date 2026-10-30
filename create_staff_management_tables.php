<?php
require_once 'config.php';

// Set content type for proper HTML display
header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        h2 { color: #20b2aa; border-bottom: 2px solid #20b2aa; padding-bottom: 10px; }
        h3 { color: #008b8b; margin-top: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>";

echo "<h2>Database Setup for Staff Management System</h2>";

try {
    // Drop existing tables if they exist to start fresh
    echo "<h3>1. Cleaning up existing tables...</h3>";
    $cleanup_tables = ['staff_user_permissions', 'staff_permissions', 'create_users'];
    foreach ($cleanup_tables as $table) {
        $conn->query("DROP TABLE IF EXISTS `$table`");
        echo "<div class='success'>✅ Cleaned up table: $table</div>";
    }

    // Create create_users table
    echo "<h3>2. Creating create_users table...</h3>";
    $sql_create_users = "CREATE TABLE `create_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `number` varchar(20) NOT NULL,
        `email` varchar(255) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `role` enum('staff', 'manager') NOT NULL DEFAULT 'staff',
        `employee_id` varchar(50) DEFAULT NULL,
        `designation` varchar(100) DEFAULT NULL,
        `department` varchar(100) DEFAULT NULL,
        `joining_date` date DEFAULT NULL,
        `created_by` int(11) NOT NULL,
        `updated_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `created_by` (`created_by`),
        KEY `status` (`status`),
        KEY `role` (`role`),
        KEY `employee_id` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql_create_users) === TRUE) {
        echo "<div class='success'>✅ Table 'create_users' created successfully</div>";
    } else {
        throw new Exception("Error creating create_users table: " . $conn->error);
    }

    // Verify table structure
    echo "<h3>3. Verifying table structure...</h3>";
    $result = $conn->query("DESCRIBE create_users");
    if ($result) {
        echo "<div class='success'>✅ Table structure verified</div>";
        echo "<div class='info'>Table has " . $result->num_rows . " columns</div>";
    } else {
        throw new Exception("Error describing table: " . $conn->error);
    }

    // Test insert operation
    echo "<h3>4. Testing insert operation...</h3>";
    $test_data = [
        'name' => 'Test User',
        'number' => '1234567890',
        'email' => 'test' . time() . '@example.com',
        'password' => password_hash('testpass123', PASSWORD_DEFAULT),
        'status' => 1,
        'role' => 'staff',
        'employee_id' => 'EMP2024TEST',
        'designation' => 'Test Executive',
        'department' => 'Sales',
        'joining_date' => date('Y-m-d'),
        'created_by' => 1
    ];

    $stmt = $conn->prepare("INSERT INTO create_users (name, number, email, password, status, role, employee_id, designation, department, joining_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssssisssssi",
            $test_data['name'],
            $test_data['number'],
            $test_data['email'],
            $test_data['password'],
            $test_data['status'],
            $test_data['role'],
            $test_data['employee_id'],
            $test_data['designation'],
            $test_data['department'],
            $test_data['joining_date'],
            $test_data['created_by']
        );

        if ($stmt->execute()) {
            $test_user_id = $conn->insert_id;
            echo "<div class='success'>✅ Test insert successful - User ID: $test_user_id</div>";

            // Clean up test user
            $cleanup = $conn->prepare("DELETE FROM create_users WHERE id = ?");
            $cleanup->bind_param("i", $test_user_id);
            $cleanup->execute();
            echo "<div class='info'>🧹 Test user cleaned up</div>";
        } else {
            throw new Exception("Test insert failed: " . $stmt->error);
        }
    } else {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    echo "<h3>✅ Database Setup Completed Successfully!</h3>";
    echo "<div class='success'>The create_users table is ready for staff management.</div>";
    echo "<p><a href='create-user.php'>Go to Staff Management</a> | <a href='simple_create_user.php'>Go to Simple Version</a></p>";

} catch (Exception $e) {
    echo "<h3>❌ Setup Failed</h3>";
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
$conn->close();
?>
