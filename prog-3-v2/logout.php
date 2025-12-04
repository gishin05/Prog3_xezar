<?php
require_once 'auth.php';

// Logout user
logout();

// Redirect to login page
header("Location: login.php");
exit();
?>

