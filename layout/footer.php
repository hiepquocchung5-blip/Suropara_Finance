</div> <!-- End Container -->

    <!-- MOBILE FLOATING DOCK -->
    <div class="bottom-dock">
        <a href="dashboard.php" class="dock-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-grid-fill"></i>
            <span>HOME</span>
        </a>
        <a href="queue.php" class="dock-item <?= basename($_SERVER['PHP_SELF']) == 'queue.php' ? 'active' : '' ?>">
            <i class="bi bi-lightning-charge-fill"></i>
            <span>QUEUE</span>
        </a>
        <a href="history.php" class="dock-item <?= basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : '' ?>">
            <i class="bi bi-clock-history"></i>
            <span>LOGS</span>
        </a>
        <a href="wallets.php" class="dock-item <?= basename($_SERVER['PHP_SELF']) == 'wallets.php' ? 'active' : '' ?>">
            <i class="bi bi-wallet-fill"></i>
            <span>BANKS</span>
        </a>
        <a href="profile.php" class="dock-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>ME</span>
        </a>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form Auto-Loader: Prevents double clicks and gives visual feedback -->
    <script>
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                // Don't show full loader for quick toggles, only for main actions
                if (!this.classList.contains('no-loader')) {
                    showLoader();
                }
            });
        });
    </script>
</body>
</html>