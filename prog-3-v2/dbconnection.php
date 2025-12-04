<?php
/**
 * Database Connection File
 * Configure your database credentials here
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'store_db');

/**
 * Create database connection
 * @return mysqli|null Returns mysqli connection object or null on failure
 */
function getDBConnection() {
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        // Return null on connection failure
        return null;
    }
    
    // Set charset to utf8mb4 for cross-platform compatibility
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

/**
 * Close database connection
 * @param mysqli $conn Database connection object
 */
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}
?>


