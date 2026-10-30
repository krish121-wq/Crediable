<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
include('layout.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'admin';
$success_message = $_SESSION['success_message'] ?? '';
$error_message = '';

if ($success_message) {
    unset($_SESSION['success_message']);
}

// Get current user data - Check both users and create_users tables
$user = null;

// First try from users table (admin users)
$stmt = $conn->prepare("SELECT id, full_name, email, mobile, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If not found in users table, try create_users table (staff users)
if (!$user) {
    $stmt = $conn->prepare("SELECT id, name as full_name, email, number as mobile, created_at FROM create_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    $error_message = "User not found.";
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['update_profile'])) {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $mobile = trim($_POST['mobile'] ?? '');
            
            if (empty($full_name)) {
                throw new Exception("Full name is required.");
            }
            if (empty($email)) {
                throw new Exception("Email is required.");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }
            
            // Update based on user type (admin or staff)
            if ($user_type === 'admin') {
                // Check if email already exists for another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    throw new Exception("Email already exists.");
                }
                $stmt->close();
                
                // Update profile in users table
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, mobile = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $email, $mobile, $user_id);
            } else {
                // Update profile in create_users table for staff
                $stmt = $conn->prepare("UPDATE create_users SET name = ?, email = ?, number = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $email, $mobile, $user_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile: " . $stmt->error);
            }
            
            $stmt->close();
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            
            // Refresh user data
            if ($user_type === 'admin') {
                $stmt = $conn->prepare("SELECT id, full_name, email, mobile, created_at FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("SELECT id, name as full_name, email, number as mobile, created_at FROM create_users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }
            
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: profile.php");
            exit();
            
        } elseif (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All password fields are required.");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("New password must be at least 6 characters long.");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New password and confirm password do not match.");
            }
            
            // Get password based on user type
            if ($user_type === 'admin') {
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            } else {
                $stmt = $conn->prepare("SELECT password FROM create_users WHERE id = ?");
            }
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            $stmt->close();
            
            if (!password_verify($current_password, $user_data['password'])) {
                throw new Exception("Current password is incorrect.");
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            if ($user_type === 'admin') {
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE create_users SET password = ? WHERE id = ?");
            }
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update password: " . $stmt->error);
            }
            
            $stmt->close();
            
            $_SESSION['success_message'] = "Password changed successfully!";
            header("Location: profile.php");
            exit();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Profile Error: " . $e->getMessage());
    }
}

$page_title = "Profile Settings";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile Settings - Grow Biller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --brand: #0284c7;
            --brand-dark: #0b1220;
            --brand-mid: #0ea5e9;
            --brand-light: #e0f2fe;
            --bg: #f4f8fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0b1220;
            --text-mid: #475569;
            --text-muted: #94a3b8;
            --success: #10b981;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 1px 3px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.05);
            --transition: all 0.25s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 14px; }
        
        .page-wrap {
            margin-top: 70px;
            margin-left: 260px;
            padding: 24px 28px 40px;
            transition: var(--transition);
            min-height: calc(100vh - 70px);
        }
        @media (max-width: 992px) {
            .page-wrap { margin-left: 0; padding: 16px; }
        }
        @media (max-width: 576px) {
            .page-wrap { padding: 12px; }
        }
        
        .hero-banner {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 55%, var(--brand-mid) 100%);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        @media (max-width: 768px) {
            .hero-banner {
                padding: 14px 18px;
                flex-direction: column;
                text-align: center;
            }
        }
        .hero-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .hero-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.7rem;
        }
        .hero-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(8px);
            border-radius: 50px;
            padding: 5px 14px;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        .card-premium {
            background: var(--card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            overflow: hidden;
        }
        .card-premium:hover {
            box-shadow: var(--shadow-md);
        }
        .card-header-premium {
            background: linear-gradient(90deg, var(--brand-light) 0%, #fff 100%);
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
        }
        .card-header-premium h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--brand);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body-premium {
            padding: 20px;
        }
        
        .profile-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
            font-weight: 700;
            margin-right: 16px;
            flex-shrink: 0;
        }
        .profile-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text);
        }
        .profile-info p {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin: 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 16px 0;
        }
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        .info-item {
            background: #f8fafc;
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            border-left: 3px solid var(--brand);
        }
        .info-label {
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        .form-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--text-mid);
            margin-bottom: 5px;
            display: block;
        }
        .form-control {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            padding: 8px 12px;
            font-size: 0.8rem;
            transition: var(--transition);
            background: #fafbfc;
            width: 100%;
        }
        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-light);
            outline: none;
            background: #fff;
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(2,132,199,0.3);
        }
        .btn-secondary-premium {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-secondary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107,114,128,0.3);
        }
        
        .alert-custom {
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
        }
        .alert-success-custom {
            background: var(--success-bg);
            color: var(--success);
            border: 1px solid #86efac;
        }
        .alert-danger-custom {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fca5a5;
        }
        
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--brand);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-strength {
            margin-top: 6px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        .strength-weak { background: #dc2626; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #16a34a; width: 100%; }
        
        .security-tips {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .security-tips ul {
            padding-left: 18px;
            margin: 0;
        }
        .security-tips li {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .card-body-premium {
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    
    <div class="hero-banner">
        <div>
            <div class="hero-title">
                <i class="fas fa-user-circle"></i>
                Profile Settings
            </div>
            <div class="hero-sub">Manage your account information and security</div>
        </div>
        <div class="hero-badge">
            <i class="fas fa-shield-alt me-1"></i>
            Account Security
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert-custom alert-success-custom" id="successAlert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert-custom alert-danger-custom">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <!-- Profile Information Card -->
        <div class="card-premium">
            <div class="card-header-premium">
                <h4><i class="fas fa-user-edit"></i> Profile Information</h4>
            </div>
            <div class="card-body-premium">
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <div class="profile-avatar">
                        <?php 
                        $display_name = $user['full_name'] ?? 'U';
                        echo strtoupper(substr($display_name, 0, 2)); 
                        ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user['full_name'] ?? 'Unknown'); ?></h3>
                        <p><i class="fas fa-tag me-1"></i> <?php echo ucfirst($user_type); ?> Account</p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">User ID</div>
                        <div class="info-value">#<?php echo $user['id'] ?? 'N/A'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Type</div>
                        <div class="info-value"><?php echo ucfirst($user_type); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Member Since</div>
                        <div class="info-value">
                            <?php 
                            if (!empty($user['created_at']) && $user['created_at'] != '0000-00-00 00:00:00') {
                                echo date('d M Y', strtotime($user['created_at']));
                            } else {
                                echo date('d M Y');
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value"><span style="color: var(--success);"><i class="fas fa-check-circle"></i> Active</span></div>
                    </div>
                </div>
                
                <form method="post" action="profile.php">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" class="form-control" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" placeholder="Enter mobile number">
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary-premium">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Password Change Card -->
        <div class="card-premium">
            <div class="card-header-premium">
                <h4><i class="fas fa-lock"></i> Change Password</h4>
            </div>
            <div class="card-body-premium">
                <form method="post" action="profile.php" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="text-muted">Password must be at least 6 characters long</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" name="change_password" class="btn-primary-premium">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                        <button type="button" class="btn-secondary-premium" onclick="clearPasswordForm()">
                            <i class="fas fa-undo-alt"></i> Clear
                        </button>
                    </div>
                </form>
                
                <div class="security-tips">
                    <div class="section-title" style="margin-bottom: 10px;">
                        <i class="fas fa-shield-alt"></i> Security Tips
                    </div>
                    <ul>
                        <li>Use a strong password with mix of letters, numbers, and symbols</li>
                        <li>Don't share your password with anyone</li>
                        <li>Change your password regularly</li>
                        <li>Use different passwords for different accounts</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Auto-hide success alert after 3 seconds
    setTimeout(function() {
        $('#successAlert').fadeOut(500, function() { $(this).remove(); });
    }, 3000);
    
    // Password strength checker
    $('#new_password').on('input', function() {
        var password = $(this).val();
        var strengthBar = $('#strengthBar');
        
        if (password.length === 0) {
            strengthBar.removeClass('strength-weak strength-medium strength-strong');
            return;
        }
        
        var strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        strengthBar.removeClass('strength-weak strength-medium strength-strong');
        
        if (strength <= 2) {
            strengthBar.addClass('strength-weak');
        } else if (strength <= 4) {
            strengthBar.addClass('strength-medium');
        } else {
            strengthBar.addClass('strength-strong');
        }
    });
    
    // Password confirmation validation
    $('#confirm_password').on('input', function() {
        var newPassword = $('#new_password').val();
        var confirmPassword = $(this).val();
        
        if (confirmPassword && newPassword !== confirmPassword) {
            $(this).css('border-color', '#dc2626');
        } else {
            $(this).css('border-color', '#e4e2ea');
        }
    });
    
    // Form validation before submit
    $('#passwordForm').on('submit', function(e) {
        var newPassword = $('#new_password').val();
        var confirmPassword = $('#confirm_password').val();
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New password and confirm password do not match!');
            return false;
        }
        
        if (newPassword.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return false;
        }
        
        return true;
    });
});

function clearPasswordForm() {
    $('#passwordForm')[0].reset();
    $('#strengthBar').removeClass('strength-weak strength-medium strength-strong');
    $('#confirm_password').css('border-color', '#e2e8f0');
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>