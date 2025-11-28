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
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const navMenu = document.querySelector('.nav-menu');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>

