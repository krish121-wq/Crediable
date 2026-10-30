<?php
// Simple user creation page for testing
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set admin session for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_type'] = 'admin';
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    try {
        // Get form data
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $number = $_POST['number'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';
        $designation = $_POST['designation'] ?? '';
        $department = $_POST['department'] ?? '';
        $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
        
        // Basic validation
        if (empty($name) || empty($email) || empty($number) || empty($password)) {
            throw new Exception("All required fields must be filled");
        }
        
        // Check if email exists
        $email_check = $conn->prepare("SELECT id FROM create_users WHERE email = ?");
        $email_check->bind_param("s", $email);
        $email_check->execute();
        if ($email_check->get_result()->num_rows > 0) {
            throw new Exception("Email already exists");
        }
        
        // Generate employee ID
        $count_result = $conn->query("SELECT COUNT(*) as count FROM create_users WHERE created_by = 1");
        $current_count = $count_result->fetch_assoc()['count'];
        $employee_id = 'EMP' . date('Y') . str_pad($current_count + 1, 3, '0', STR_PAD_LEFT);
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $conn->prepare("INSERT INTO create_users (name, number, email, password, status, role, employee_id, designation, department, joining_date, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 1, NOW())");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssss", $name, $number, $email, $hashed_password, $role, $employee_id, $designation, $department, $joining_date);
        
        if ($stmt->execute()) {
            $success_message = "User created successfully! Employee ID: $employee_id";
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get existing users
$users = [];
$result = $conn->query("SELECT * FROM create_users WHERE created_by = 1 ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Simple Staff Management</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #20b2aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #008b8b; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-container { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Simple Staff Management</h1>
    
    <?php if ($success_message): ?>
        <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    
    <div class="form-container">
        <h2>Add New Staff User</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="number" required>
            </div>
            
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>Role</label>
                <select name="role">
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Designation</label>
                <input type="text" name="designation" placeholder="e.g., Sales Executive">
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <select name="department">
                    <option value="">Select Department</option>
                    <option value="Sales">Sales</option>
                    <option value="Purchase">Purchase</option>
                    <option value="Inventory">Inventory</option>
                    <option value="Accounts">Accounts</option>
                    <option value="Operations">Operations</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Joining Date</label>
                <input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <button type="submit" name="add_user">Add Staff User</button>
        </form>
    </div>
    
    <h2>Existing Staff Users (<?php echo count($users); ?>/5)</h2>
    
    <?php if (count($users) > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Employee ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Designation</th>
                <th>Department</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo $user['employee_id']; ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['number']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['designation']); ?></td>
                    <td><?php echo htmlspecialchars($user['department']); ?></td>
                    <td><?php echo $user['status'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No staff users created yet.</p>
    <?php endif; ?>
    
    <hr>
    <p><a href="create-user.php">Go to Main Staff Management</a> | <a href="debug_create_user.php">Debug Page</a></p>
</body>
</html>
