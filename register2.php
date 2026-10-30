<?php
require_once 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $user_role = trim($_POST['user_role']);
    $company_name = trim($_POST['company_name']);
    $company_address = trim($_POST['company_address']);
    $gstin = trim($_POST['gstin']);
    $errors = [];

    // Validation
    if (empty($full_name)) $errors[] = "Full Name is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (!preg_match("/^[0-9]{10}$/", $mobile)) $errors[] = "Mobile number must be 10 digits.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
    if (empty($user_role)) $errors[] = "User Role is required.";
    if (empty($company_name)) $errors[] = "Company Name is required.";
    if (empty($company_address)) $errors[] = "Company Address is required.";

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already registered.";
    }
    $stmt->close();

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $trial_end_date = date('Y-m-d H:i:s', strtotime('+3 days'));
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, mobile, password, trial_end_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $full_name, $email, $mobile, $hashed_password, $trial_end_date);
            $stmt->execute();
            $user_id = $conn->insert_id;
            $stmt->close();

            // Insert company
            $stmt = $conn->prepare("INSERT INTO companies (company_name, company_address, gstin) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $company_name, $company_address, $gstin);
            $stmt->execute();
            $company_id = $conn->insert_id;
            $stmt->close();

            // Insert user-company-role mapping
            $stmt = $conn->prepare("INSERT INTO user_company_roles (user_id, company_id, user_role) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $company_id, $user_role);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: login.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Credible</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    :root {
        --primary: #0284c7;
        --primary-dark: #0b1220;
        --accent: #0ea5e9;
        --shadow: 0 20px 50px rgba(2, 132, 199, 0.28);
        --light-blue: rgba(2, 132, 199, 0.10);
    }

    body {
        background: linear-gradient(160deg, #04060a 0%, #0b1220 45%, #0c2233 100%);
        font-family: 'Outfit', sans-serif;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
    }

    .register-container {
        max-width: 680px;
        width: 100%;
        background: rgba(255,255,255,0.98);
        backdrop-filter: blur(20px);
        border-radius: 28px;
        box-shadow: var(--shadow);
        padding: 45px 40px;
        border: 1px solid rgba(2, 132, 199, 0.20);
    }

    .brand-title {
        font-size: 2.35rem;
        font-weight: 800;
        color: var(--primary-dark);
        text-align: center;
        margin-bottom: 8px;
        letter-spacing: -0.6px;
    }

    .brand-title span {
        color: var(--primary);
    }

    .subheading {
        text-align: center;
        font-size: 1.05rem;
        color: #555;
        margin-bottom: 2rem;
        font-weight: 500;
    }

    .section-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--primary);
        margin: 25px 0 12px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #e0f2fe;
    }

    .form-label {
        font-weight: 600;
        color: #2c2c2c;
        font-size: 1.02rem;
        margin-bottom: 6px;
    }

    .form-control, .form-select {
        border-radius: 14px;
        padding: 12px 16px 12px 50px;
        font-size: 1.02rem;
        border: 2px solid #d5e8f2;
        background-color: #fff;
        transition: all 0.3s ease;
        height: 52px;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.3rem rgba(2, 132, 199, 0.15);
        background-color: #fff;
    }

    .input-group-text {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        color: white;
        border: none;
        border-radius: 14px 0 0 14px;
        width: 48px;
        justify-content: center;
        font-size: 1.2rem;
    }

    /* Buttons */
    .btn-primary {
        background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        border: none;
        border-radius: 16px;
        padding: 16px;
        font-weight: 600;
        font-size: 1.1rem;
        height: 62px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .btn-primary:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(2, 132, 199, 0.35);
    }

    .btn-primary i {
        transition: transform 0.3s ease;
    }

    .btn-primary:hover i {
        transform: translateX(6px);
    }

    /* Powered By */
    .powered-by {
        text-align: center;
        margin-top: 2.5rem;
        font-size: 0.96rem;
        color: #666;
    }

    .powered-by a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
    }

    .powered-by a:hover {
        color: var(--accent);
    }

    /* Logo */
    .logo {
        position: absolute;
        top: 25px;
        left: 25px;
        width: 105px;
        background: rgba(255,255,255,0.95);
        padding: 8px;
        border-radius: 18px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        z-index: 1000;
    }

    .back-button {
        position: absolute;
        top: 25px;
        right: 25px;
        background: linear-gradient(135deg, var(--primary-dark), var(--primary));
        color: white;
        border: none;
        border-radius: 12px;
        padding: 10px 18px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s;
    }

    .back-button:hover {
        background: var(--primary-dark);
        color: #fff;
        transform: translateY(-2px);
    }

    .alert {
        border-radius: 12px;
        border-left: 5px solid var(--primary);
    }

    /* Responsive */
    @media (max-width: 576px) {
        .register-container {
            padding: 30px 20px;
            margin: 80px 15px 20px;
        }
        .brand-title { font-size: 2.1rem; }
        .btn-primary, .form-control, .form-select { height: 56px; font-size: 1.02rem; }
        .logo { width: 90px; top: 20px; left: 20px; }
        .back-button { top: 20px; right: 20px; padding: 8px 15px; font-size: 0.95rem; }
    }
</style>
</head>
<body>
   <a href="login.php" class="back-button"><i class="fas fa-arrow-left me-2"></i>Back to Login</a>
   <img src="images/logo1.png" alt="Credible Logo" class="logo" onerror="this.src='images/favicon.png'">
    <div class="register-container">
       <h2 class="brand-title">Cred<span>ible</span></h2>
       <p class="subheading">Streamline your business with our powerful billing & accounting solution.</p>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="" id="registerForm">
            <div class="section-title">Personal Information</div>
            <div class="row row-cols-1 row-cols-md-2 mb-3">
                <div class="form-group" style="--i: 1;">
                    <label for="full_name" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Full name" value="<?php echo isset($full_name) ? htmlspecialchars($full_name) : ''; ?>" required aria-describedby="full_nameHelp">
                    </div>
                </div>
                <div class="form-group" style="--i: 2;">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required aria-describedby="emailHelp">
                    </div>
                </div>
                <div class="form-group" style="--i: 3;">
                    <label for="mobile" class="form-label">Mobile Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="text" class="form-control" id="mobile" name="mobile" placeholder="10-digit mobile" value="<?php echo isset($mobile) ? htmlspecialchars($mobile) : ''; ?>" required aria-describedby="mobileHelp">
                    </div>
                </div>
                <div class="form-group" style="--i: 4;">
                    <label for="user_role" class="form-label">User Role</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                        <select class="form-control form-select" id="user_role" name="user_role" required aria-describedby="user_roleHelp">
                            <option value="">Select Role</option>
                            <option value="Admin" <?php echo isset($user_role) && $user_role == 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="section-title">Password</div>
            <div class="row row-cols-1 row-cols-md-2 mb-3">
                <div class="form-group" style="--i: 5;">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required aria-describedby="passwordHelp">
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                <div class="form-group" style="--i: 6;">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required aria-describedby="confirm_passwordHelp">
                    </div>
                </div>
            </div>
            <div class="section-title">Company Information</div>
            <div class="row row-cols-1 mb-3">
                <div class="form-group" style="--i: 7;">
                    <label for="company_name" class="form-label">Company Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                        <input type="text" class="form-control" id="company_name" name="company_name" placeholder="Company name" value="<?php echo isset($company_name) ? htmlspecialchars($company_name) : ''; ?>" required aria-describedby="company_nameHelp">
                    </div>
                </div>
                <div class="form-group" style="--i: 8;">
                    <label for="company_address" class="form-label">Company Address</label>
                    <div class="input-group textarea-group">
                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                        <textarea class="form-control" id="company_address" name="company_address" placeholder="Company address" required aria-describedby="company_addressHelp"><?php echo isset($company_address) ? htmlspecialchars($company_address) : ''; ?></textarea>
                    </div>
                </div>
                <div class="form-group" style="--i: 9;">
                    <label for="gstin" class="form-label">GSTIN (Optional) <i class="fas fa-info-circle tooltip-icon" data-bs-toggle="tooltip" title="Enter your GST Identification Number if applicable"></i></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-file-invoice"></i></span>
                        <input type="text" class="form-control" id="gstin" name="gstin" placeholder="GSTIN (if applicable)" value="<?php echo isset($gstin) ? htmlspecialchars($gstin) : ''; ?>" aria-describedby="gstinHelp">
                    </div>
                </div>
            </div>
         <button type="submit" class="btn btn-primary" id="submitBtn">
    Create Account <i class="fas fa-arrow-right"></i>
    <span class="spinner-border spinner-border-sm spinner" role="status" aria-hidden="true" style="display:none;"></span>
</button>

<div class="powered-by">
    Powered by <a href="https://ai-digitalsolution.com/" target="_blank">AI Digital Innovation</a>
</div>
        </form>
        <p class="text-center mt-3">Already have an account? <a href="login.php" class="login-link" style="color: var(--primary); font-weight:600;">Login here</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            $('#registerForm').submit(function(e) {
                let password = $('#password').val();
                let confirm_password = $('#confirm_password').val();
                if (password !== confirm_password) {
                    e.preventDefault();
                    $('#confirm_password').addClass('is-invalid');
                    $('.alert-danger').remove();
                    $('<div class="alert alert-danger">Passwords do not match!</div>').insertBefore('#registerForm');
                    $('#submitBtn').prop('disabled', false);
                    $('.spinner').hide();
                }
            });

            $('#registerForm').on('submit', function() {
                $('#submitBtn').prop('disabled', true);
                $('.spinner').show();
            });
        });
    </script>
</body>
</html>