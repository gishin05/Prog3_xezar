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

    // Process login form
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            try {
                $result = login($username, $password);
                if ($result['success']) {
                    header("Location: home.php");
                    exit();
                } else {
                    $error = $result['message'];
                }
            } catch (Exception $e) {
                $error = "Login error: " . $e->getMessage();
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
    <meta name="description" content="Login to Store Management System">
    <title>Login - INCONVINIENCE STORE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo"><i class="fas fa-store"></i></div>
                <h1>Inconvinience Store</h1>
                <h2>Store Management System</h2>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form-modern">
                <div class="form-group-modern">
                    <label for="username" class="form-label">
                        <span class="label-icon"><i class="fas fa-user"></i></span>
                        Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input-modern"
                           placeholder="Enter your username"
                           required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           autofocus>
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
                           placeholder="Enter your password"
                           required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    <span>Sign In</span>
                    <span class="btn-arrow">→</span>
                </button>
            </form>
            
            <div class="login-info-modern">
                <div class="info-header">
                    <span class="info-icon"><i class="fas fa-info-circle"></i></span>
                    <strong>Don't have an account?</strong>
                </div>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="register.php" class="btn btn-secondary" style="width: 100%; text-decoration: none; display: inline-block;">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
            </div>
            
            <div class="login-info-modern" style="margin-top: 16px;">
                <div class="info-header">
                    <span class="info-icon"><i class="fas fa-info-circle"></i></span>
                    <strong>Sample Login Credentials</strong>
                </div>
                <div class="credentials-list">
                    <div class="credential-item">
                        <span class="credential-role">Manager</span>
                        <div class="credential-details">
                            <code>manager</code> / <code>password123</code>
                        </div>
                    </div>
                    <div class="credential-item">
                        <span class="credential-role">Employee</span>
                        <div class="credential-details">
                            <code>employee1</code> / <code>employee123</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

