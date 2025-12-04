<?php
// Main entry point - show welcome page first
// Users can login from the welcome page
if (file_exists('auth.php')) {
    require_once 'auth.php';
    // Always show welcome page first, login page handles redirection to dashboard
    header("Location: welcome.php");
    exit();
} else {
    // Show setup instructions if files don't exist
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Required</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .setup-box { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
            code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
            pre { background: #2c3e50; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; }
            .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        </style>
    </head>
    <body>
        <h1>Authentication System Setup Required</h1>
        <div class="setup-box">
            <p><strong>The authentication files need to be created.</strong></p>
            <p>Due to file permissions, you need to create these files manually:</p>
            <ul>
                <li><code>auth.php</code> - Authentication system</li>
                <li><code>login.php</code> - Login page</li>
                <li><code>home.php</code> - Dashboard</li>
                <li><code>logout.php</code> - Logout handler</li>
                <li><code>items.php</code> - Items management page</li>
            </ul>
            
            <h3>Quick Setup:</h3>
            <p>Run this command in your terminal:</p>
            <pre>cd /opt/lampp/htdocs/porg-3-project
sudo nano auth.php</pre>
            <p>Then copy the file contents from the setup instructions below.</p>
            
            <h3>Or use this PHP setup helper:</h3>
            <a href="setup.php" class="btn">Run Setup Helper</a>
        </div>
    </body>
    </html>
    <?php
}
?>


