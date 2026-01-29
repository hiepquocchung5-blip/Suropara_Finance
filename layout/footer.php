</div> <!-- End Container -->

    <!-- BOTTOM NAV -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i>
            <span>Home</span>
        </a>
        <a href="queue.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF']) == 'queue.php' ? 'active' : '' ?>">
            <i class="bi bi-list-check"></i>
            <span>Queue</span>
        </a>
        <a href="history.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i>
            <span>History</span>
        </a>
        <a href="wallets.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF']) == 'wallets.php' ? 'active' : '' ?>">
            <i class="bi bi-credit-card-2-front"></i>
            <span>Wallets</span>
        </a>
        <a href="profile.php" class="nav-item-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Me</span>
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-bs-theme');
            html.setAttribute('data-bs-theme', current === 'dark' ? 'light' : 'dark');
        }
    </script>
</body>
</html>