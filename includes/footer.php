<?php if (($usePageShell ?? true) === true): ?>
    </div>
</main>
<?php endif; ?>
<footer class="app-footer">
    <div class="container">
        <div class="footer-card">
            <div>
                <div class="footer-brand">PHPDevHub</div>
                <p class="footer-copy mb-0">Connecting clients and PHP developers through a polished hiring workspace.</p>
            </div>
            <div class="footer-links">
                <a href="<?php echo htmlspecialchars(appUrl('index.php')); ?>">Home</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (getUserRole() === 'client'): ?>
                        <a href="<?php echo htmlspecialchars(appUrl('client/dashboard.php')); ?>">Dashboard</a>
                    <?php elseif (getUserRole() === 'developer'): ?>
                        <a href="<?php echo htmlspecialchars(appUrl('developer/dashboard.php')); ?>">Dashboard</a>
                    <?php elseif (getUserRole() === 'admin'): ?>
                        <a href="<?php echo htmlspecialchars(appUrl('admin/dashboard.php')); ?>">Admin</a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(appUrl('logout.php')); ?>">Logout</a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars(appUrl('login.php')); ?>">Login</a>
                    <a href="<?php echo htmlspecialchars(appUrl('signup.php')); ?>">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo htmlspecialchars(appUrl('assets/js/script.js')); ?>"></script>
</body>
</html>
