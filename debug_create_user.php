<?php
// Debug script to identify the exact issue
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['user_type'] = 'admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Create User</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 20px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>";

echo "<h2>Debug: Create User Issues</h2>";

try {
    // Step 1: Check database connection
    echo "<h3>1. Database Connection Test</h3>";
    if ($conn->ping()) {
        echo "<div class='success'>✅ Database connection successful</div>";
        echo "<div class='info'>Database: " . $conn->get_server_info() . "</div>";
    } else {
        echo "<div class='error'>❌ Database connection failed</div>";
        exit;
    }
    
    // Step 2: Check if create_users table exists
    echo "<h3>2. Table Existence Check</h3>";
    $result = $conn->query("SHOW TABLES LIKE 'create_users'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✅ create_users table exists</div>";
    } else {
        echo "<div class='error'>❌ create_users table does not exist</div>";
        echo "<div class='info'>Creating table now...</div>";
        
        $sql = "CREATE TABLE `create_users` (
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
        
        if ($conn->query($sql)) {
            echo "<div class='success'>✅ Table created successfully</div>";
        } else {
            echo "<div class='error'>❌ Failed to create table: " . $conn->error . "</div>";
            exit;
        }
    }
    
    // Step 3: Check table structure
    echo "<h3>3. Table Structure Check</h3>";
    $result = $conn->query("DESCRIBE create_users");
    if ($result) {
        echo "<div class='success'>✅ Table structure:</div>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Step 4: Test form processing
    echo "<h3>4. Form Processing Test</h3>";
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_create'])) {
        echo "<div class='info'>Form submitted! Processing...</div>";
        echo "<h4>POST Data Received:</h4>";
        echo "<pre>" . print_r($_POST, true) . "</pre>";
        
        // Extract form data
        $name = $_POST['name'] ?? '';
        $number = $_POST['number'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? '1';
        $role = $_POST['role'] ?? 'staff';
        $employee_id = $_POST['employee_id'] ?? '';
        $designation = $_POST['designation'] ?? '';
        $department = $_POST['department'] ?? '';
        $joining_date = $_POST['joining_date'] ?? '';
        
        echo "<h4>Validation:</h4>";
        if (empty($name)) echo "<div class='error'>❌ Name is empty</div>";
        if (empty($number)) echo "<div class='error'>❌ Number is empty</div>";
        if (empty($email)) echo "<div class='error'>❌ Email is empty</div>";
        if (empty($password)) echo "<div class='error'>❌ Password is empty</div>";
        
        if ($name && $number && $email && $password) {
            echo "<div class='success'>✅ All required fields present</div>";
            
            // Generate employee ID if empty
            if (empty($employee_id)) {
                $count_result = $conn->query("SELECT COUNT(*) as count FROM create_users WHERE created_by = 1");
                $current_count = $count_result->fetch_assoc()['count'];
                $employee_id = 'EMP' . date('Y') . str_pad($current_count + 1, 3, '0', STR_PAD_LEFT);
                echo "<div class='info'>Generated Employee ID: $employee_id</div>";
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            echo "<div class='info'>Password hashed successfully</div>";
            
            // Prepare insert statement
            $stmt = $conn->prepare("INSERT INTO create_users (name, number, email, password, status, role, employee_id, designation, department, joining_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt) {
                echo "<div class='success'>✅ Statement prepared successfully</div>";
                
                $stmt->bind_param("ssssisssssi", $name, $number, $email, $hashed_password, $status, $role, $employee_id, $designation, $department, $joining_date, 1);
                
                if ($stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    echo "<div class='success'>✅ User created successfully! ID: $new_user_id</div>";
                    
                    // Verify the user was created
                    $verify = $conn->prepare("SELECT * FROM create_users WHERE id = ?");
                    $verify->bind_param("i", $new_user_id);
                    $verify->execute();
                    $result = $verify->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        echo "<h4>Created User Details:</h4>";
                        echo "<table>";
                        foreach ($user as $key => $value) {
                            if ($key !== 'password') {
                                echo "<tr><td><strong>$key</strong></td><td>$value</td></tr>";
                            }
                        }
                        echo "</table>";
                    }
                } else {
                    echo "<div class='error'>❌ Execute failed: " . $stmt->error . "</div>";
                }
            } else {
                echo "<div class='error'>❌ Prepare failed: " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='error'>❌ Missing required fields</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>

<!-- Test Form -->
<h3>5. Test Form</h3>
<form method="POST" style="background: #f9f9f9; padding: 20px; border-radius: 5px;">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div style="margin-bottom: 10px;">
        <label><strong>Name *</strong></label><br>
        <input type="text" name="name" value="Test User" required style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Email *</strong></label><br>
        <input type="email" name="email" value="test<?php echo time(); ?>@example.com" required style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Phone *</strong></label><br>
        <input type="text" name="number" value="1234567890" required style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Password *</strong></label><br>
        <input type="password" name="password" value="testpass123" required style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Role</strong></label><br>
        <select name="role" style="width: 312px; padding: 5px;">
            <option value="staff">Staff</option>
            <option value="manager">Manager</option>
        </select>
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Designation</strong></label><br>
        <input type="text" name="designation" value="Test Executive" style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Department</strong></label><br>
        <select name="department" style="width: 312px; padding: 5px;">
            <option value="">Select Department</option>
            <option value="Sales" selected>Sales</option>
            <option value="Purchase">Purchase</option>
            <option value="Inventory">Inventory</option>
            <option value="Accounts">Accounts</option>
            <option value="Operations">Operations</option>
        </select>
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Joining Date</strong></label><br>
        <input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>" style="width: 300px; padding: 5px;">
    </div>
    
    <div style="margin-bottom: 10px;">
        <label><strong>Status</strong></label><br>
        <select name="status" style="width: 312px; padding: 5px;">
            <option value="1" selected>Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>
    
    <button type="submit" name="test_create" style="background: #20b2aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
        Test Create User
    </button>
</form>

<hr>
<p><a href="create-user.php">Go to Main Staff Management Page</a></p>
