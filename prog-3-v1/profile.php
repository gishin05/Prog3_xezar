<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser();

$page_title = "Profile";
require_once 'includes/header.php';
?>

<main class="main-content">
    <!-- Floating profile panel in the top-right corner -->
    <div class="profile-overlay">
        <div class="action-card profile-card">
            <div class="action-icon"><i class="fas fa-user-circle"></i></div>
            <h3>Account Details</h3>
            <div class="profile-details">
                <div class="profile-item">
                    <span class="profile-label">Username:</span>
                    <span class="profile-value">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Full Name:</span>
                    <span class="profile-value">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Role:</span>
                    <span class="profile-value role-badge">
                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                    </span>
                </div>
            </div>
            <div class="form-actions-modern" style="border-top:none; padding-top:16px; margin-top:8px;">
                <a href="home.php" class="btn btn-secondary" style="width:100%; text-align:center;">
                    Close
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>


