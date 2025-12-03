    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> INCONVINIENCE STORE. All rights reserved.</p>
            <p class="footer-links">
                <a href="home.php">Dashboard</a> | 
                <a href="items.php">Products</a> | 
                <a href="logout.php">Logout</a>
            </p>
        </div>
    </footer>
    <script>
        // Mobile menu toggle & profile overlay
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navMenu = document.querySelector('.nav-menu');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                });
            }

            // Profile overlay toggle
            const profileToggle = document.querySelector('.profile-toggle');
            const profileOverlay = document.getElementById('profileOverlay');
            const profileOverlayClose = document.getElementById('profileOverlayClose');

            if (profileToggle && profileOverlay) {
                profileToggle.addEventListener('click', function () {
                    profileOverlay.style.display = 'flex';
                });
            }

            if (profileOverlay && profileOverlayClose) {
                profileOverlayClose.addEventListener('click', function () {
                    profileOverlay.style.display = 'none';
                });

                // Close when clicking outside the card
                profileOverlay.addEventListener('click', function (e) {
                    if (e.target === profileOverlay) {
                        profileOverlay.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>

