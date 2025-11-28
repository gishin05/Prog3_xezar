<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'auth.php';

    // Redirect if already logged in
    if (isLoggedIn()) {
        header("Location: home.php");
        exit();
    }

    $error = '';
    $success = '';

    // Process registration form
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'employee';
        
        // Normalize role
        $role = $role === 'manager' ? 'manager' : 'employee';
        
        // Validate all fields are filled
        if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name)) {
            $error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            try {
                $result = register($username, $password, $full_name, $role);
                if ($result['success']) {
                    $success = $result['message'];
                    // Clear form data on success
                    $_POST = array();
                } else {
                    $error = $result['message'];
                }
            } catch (Exception $e) {
                $error = "Registration error: " . $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error = "System error: " . $e->getMessage();
} catch (Error $e) {
    $error = "Fatal error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create an account for Store Management System">
    <title>Register - INCONVINIENCE STORE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo"><i class="fas fa-user-plus"></i></div>
                <h1>Create Account</h1>
                <h2>Join Inconvinience Store</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form-modern">
                <div class="form-group-modern">
                    <label for="full_name" class="form-label">
                        <span class="label-icon"><i class="fas fa-id-card"></i></span>
                        Full Name
                    </label>
                    <input type="text" 
                           id="full_name" 
                           name="full_name" 
                           class="form-input-modern"
                           placeholder="Enter your full name"
                           required 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                           autofocus>
                </div>
                
                <div class="form-group-modern">
                    <label for="username" class="form-label">
                        <span class="label-icon"><i class="fas fa-user"></i></span>
                        Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input-modern"
                           placeholder="Choose a username (min. 3 characters)"
                           required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           minlength="3"
                           maxlength="100">
                </div>
                
                <div class="form-group-modern">
                    <label for="role" class="form-label">
                        <span class="label-icon"><i class="fas fa-users-cog"></i></span>
                        Account Type
                    </label>
                    <select id="role"
                            name="role"
                            class="form-input-modern"
                            required>
                        <option value="employee" <?php echo (($_POST['role'] ?? '') !== 'manager') ? 'selected' : ''; ?>>
                            Employee
                        </option>
                        <option value="manager" <?php echo (($_POST['role'] ?? '') === 'manager') ? 'selected' : ''; ?>>
                            Manager
                        </option>
                    </select>
                </div>
                
                <div class="form-group-modern">
                    <label for="password" class="form-label">
                        <span class="label-icon"><i class="fas fa-lock"></i></span>
                        Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input-modern"
                           placeholder="Enter your password (min. 6 characters)"
                           required
                           minlength="6">
                </div>
                
                <div class="form-group-modern">
                    <label for="confirm_password" class="form-label">
                        <span class="label-icon"><i class="fas fa-lock"></i></span>
                        Confirm Password
                    </label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-input-modern"
                           placeholder="Confirm your password"
                           required
                           minlength="6">
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <span>Create Account</span>
                    <span class="btn-arrow">→</span>
                </button>
            </form>
            
            <div class="login-info-modern" style="margin-top: 24px;">
                <div class="info-header">
                    <span class="info-icon"><i class="fas fa-info-circle"></i></span>
                    <strong>Already have an account?</strong>
                </div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="login.php" class="btn btn-secondary" style="width: 100%; text-decoration: none; display: inline-block;">
                        <i class="fas fa-sign-in-alt"></i> Sign In Instead
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

