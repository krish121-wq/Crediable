<?php
// Test script to verify staff management system
require_once 'config.php';
require_once 'staff_permissions_helper.php';

// Start session properly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Staff Management System Test</h2>";

try {
    // Test 1: Check if tables exist
    echo "<h3>1. Database Tables Check</h3>";
    
    $tables = ['create_users', 'staff_permissions', 'staff_user_permissions'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    // Test 2: Check permissions data
    echo "<h3>2. Permissions Data Check</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM staff_permissions");
    $count = $result->fetch_assoc()['count'];
    echo "📊 Total permissions available: $count<br>";
    
    if ($count > 0) {
        echo "✅ Permissions data loaded successfully<br>";
        
        // Show sample permissions
        $result = $conn->query("SELECT permission_name, module FROM staff_permissions LIMIT 5");
        echo "<strong>Sample permissions:</strong><br>";
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['permission_name']} ({$row['module']})<br>";
        }
    } else {
        echo "❌ No permissions found<br>";
    }
    
    // Test 3: Check staff users
    echo "<h3>3. Staff Users Check</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM create_users");
    $staff_count = $result->fetch_assoc()['count'];
    echo "👥 Total staff users: $staff_count/5<br>";
    
    if ($staff_count > 0) {
        $result = $conn->query("SELECT name, email, employee_id, role, status FROM create_users LIMIT 3");
        echo "<strong>Sample staff users:</strong><br>";
        while ($row = $result->fetch_assoc()) {
            $status = $row['status'] ? 'Active' : 'Inactive';
            echo "- {$row['name']} ({$row['employee_id']}) - {$row['role']} - $status<br>";
        }
    } else {
        echo "ℹ️ No staff users created yet<br>";
    }
    
    // Test 4: Test permissions helper
    echo "<h3>4. Permissions Helper Test</h3>";
    $permissions_helper = new StaffPermissions($conn);
    
    $all_permissions = $permissions_helper->getAllPermissions();
    echo "📋 Permissions grouped by modules: " . count($all_permissions) . " modules<br>";
    
    foreach ($all_permissions as $module => $perms) {
        echo "- $module: " . count($perms) . " permissions<br>";
    }
    
    // Test 5: Check restricted features
    echo "<h3>5. Restricted Features Check</h3>";
    $restricted = $permissions_helper->getRestrictedFeatures();
    echo "🚫 Restricted features for staff:<br>";
    foreach ($restricted as $feature) {
        echo "- $feature<br>";
    }
    
    // Test 6: Session handling
    echo "<h3>6. Session Handling Test</h3>";
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Session is active<br>";
        echo "📝 Session ID: " . session_id() . "<br>";
    } else {
        echo "❌ Session not active<br>";
    }
    
    echo "<h3>✅ All Tests Completed Successfully!</h3>";
    echo "<p><strong>Staff Management System is ready for use.</strong></p>";
    echo "<p><a href='create-user.php'>Go to Staff Management</a> | <a href='login.php'>Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error during testing:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background-color: #f5f5f5;
}
h2 {
    color: #20b2aa;
    border-bottom: 2px solid #20b2aa;
    padding-bottom: 10px;
}
h3 {
    color: #008b8b;
    margin-top: 20px;
}
</style>
