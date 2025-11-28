<?php
/**
 * Authentication System
 * Handles user login, session management, and role-based access control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'dbconnection.php';

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if current user is a manager
 * @return bool True if user is a manager, false otherwise
 */
function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

/**
 * Check if current user is an employee
 * @return bool True if user is an employee, false otherwise
 */
function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

/**
 * Get current user's information
 * @return array|null User information array or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Require user to be a manager
 * Redirects to home page if not a manager
 */
function requireManager() {
    requireLogin();
    if (!isManager()) {
        header("Location: home.php?error=access_denied");
        exit();
    }
}

/**
 * Login user with username and password
 * @param string $username Username
 * @param string $password Plain text password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function login($username, $password) {
    try {
        $conn = getDBConnection();
        
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection failed', 'user' => null];
        }
        
        // Use case-insensitive comparison for username (cross-platform compatibility)
        // Trim username to handle whitespace issues
        $username = trim($username);
        $stmt = $conn->prepare("SELECT user_id, username, password, full_name, role FROM `users` WHERE LOWER(username) = LOWER(?)");
        
        if (!$stmt) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error, 'user' => null];
        }
        
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Database query execution failed', 'user' => null];
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
        }
        
        $user = $result->fetch_assoc();
        $stmt->close();
        closeDBConnection($conn);
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
        } else {
            return ['success' => false, 'message' => 'Invalid username or password', 'user' => null];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Login error: ' . $e->getMessage(), 'user' => null];
    } catch (Error $e) {
        return ['success' => false, 'message' => 'Fatal error: ' . $e->getMessage(), 'user' => null];
    }
}

/**
 * Register a new user
 * @param string $username Username (must be unique)
 * @param string $password Plain text password
 * @param string $full_name Full name of the user
 * @param string $role User role ('employee' or 'manager', defaults to 'employee')
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function register($username, $password, $full_name, $role = 'employee') {
    try {
        $conn = getDBConnection();
        
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection failed', 'user' => null];
        }
        
        // Validate inputs
        $username = trim($username);
        $full_name = trim($full_name);
        
        if (empty($username) || empty($password) || empty($full_name)) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'All fields are required', 'user' => null];
        }
        
        // Validate username length
        if (strlen($username) < 3 || strlen($username) > 100) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Username must be between 3 and 100 characters', 'user' => null];
        }
        
        // Validate password length
        if (strlen($password) < 6) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Password must be at least 6 characters long', 'user' => null];
        }
        
        // Validate full name length
        if (strlen($full_name) < 2 || strlen($full_name) > 255) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Full name must be between 2 and 255 characters', 'user' => null];
        }
        
        // Validate role
        if (!in_array($role, ['employee', 'manager'])) {
            $role = 'employee'; // Default to employee if invalid role
        }
        
        // Check if username already exists (case-insensitive)
        $stmt = $conn->prepare("SELECT user_id FROM `users` WHERE LOWER(username) = LOWER(?)");
        
        if (!$stmt) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error, 'user' => null];
        }
        
        $stmt->bind_param("s", $username);
        
        if (!$stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Database query execution failed', 'user' => null];
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt->close();
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Username already exists. Please choose a different username.', 'user' => null];
        }
        
        $stmt->close();
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO `users` (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        
        if (!$stmt) {
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error, 'user' => null];
        }
        
        $stmt->bind_param("ssss", $username, $password_hash, $full_name, $role);
        
        if (!$stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            return ['success' => false, 'message' => 'Failed to create account: ' . $stmt->error, 'user' => null];
        }
        
        $user_id = $conn->insert_id;
        $stmt->close();
        closeDBConnection($conn);
        
        // Return user data (without password)
        return [
            'success' => true,
            'message' => 'Account created successfully! You can now login.',
            'user' => [
                'user_id' => $user_id,
                'username' => $username,
                'full_name' => $full_name,
                'role' => $role
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Registration error: ' . $e->getMessage(), 'user' => null];
    } catch (Error $e) {
        return ['success' => false, 'message' => 'Fatal error: ' . $e->getMessage(), 'user' => null];
    }
}

/**
 * Logout current user
 */
function logout() {
    $_SESSION = array();
    
    // Destroy session cookie if it exists
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}
?>

