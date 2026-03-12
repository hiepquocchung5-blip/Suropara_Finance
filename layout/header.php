<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

// Ensure user is logged in as Finance or GOD
requireFinanceAuth();

// Fetch Current Status from DB (Real-time check)
$staffId = $_SESSION['finance_id'];
$isOnline = 0; // Default offline

try {
    // Check if column exists or handle potential schema mismatch gracefully in prod
    $stmtS = $pdo->prepare("SELECT is_online FROM admin_users WHERE id = ?");
    $stmtS->execute([$staffId]);
    $result = $stmtS->fetchColumn();
    if ($result !== false) {
        $isOnline = (int)$result;
    }
} catch (Exception $e) {
    // Fallback if column missing (though SQL patch should fix this)
    $isOnline = 0; 
}

// Calculate Shift Duration
// Reset start time if it's somehow missing or huge
if (!isset($_SESSION['shift_start']) || $_SESSION['shift_start'] > time()) {
    $_SESSION['shift_start'] = time();
}

$shiftTime = time() - $_SESSION['shift_start'];
$shiftHours = floor($shiftTime / 3600);
$shiftMins = floor(($shiftTime % 3600) / 60);

// Current Page helper
$curPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Suro Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --bg-body: #0f172a; --card-bg: #1e293b; --text-main: #e2e8f0; --accent-gold: #FFD700; }
        [data-bs-theme="light"] { --bg-body: #f1f5f9; --card-bg: #ffffff; --text-main: #0f172a; }
        
        body { background-color: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; padding-bottom: 80px; transition: background 0.3s; }
        .card { background-color: var(--card-bg); border: 1px solid rgba(128,128,128,0.2); border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        /* Avatar & Status */
        .avatar { width: 35px; height: 35px; background: linear-gradient(135deg, #FFD700, #FDB931); color: #000; font-weight: bold; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .status-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
        .status-switch { cursor: pointer; }
        
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); } 70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); } 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        
        /* Bottom Navigation */
        .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: var(--card-bg); border-top: 1px solid rgba(128,128,128,0.2); padding: 10px 0; z-index: 1000; display: flex; justify-content: space-around; }
        .nav-item-link { color: var(--text-main); text-decoration: none; display: flex; flex-direction: column; align-items: center; font-size: 0.75rem; opacity: 0.7; transition: 0.2s; }
        .nav-item-link.active { color: var(--accent-gold); opacity: 1; font-weight: bold; }
        .nav-item-link i { font-size: 1.25rem; margin-bottom: 2px; }
        
        /* Utils */
        .img-proof { height: 60px; width: 60px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid rgba(128,128,128,0.3); }
        .btn-gold { background-color: var(--accent-gold); color: #000; font-weight: bold; border:none; }
    </style>
</head>
<body>

<!-- APP HEADER -->
<nav class="navbar fixed-top px-3 py-2 shadow-sm" style="background: var(--card-bg); border-bottom: 1px solid rgba(128,128,128,0.2);">
    <div class="container-fluid p-0">
        <!-- User Info -->
        <div class="d-flex align-items-center gap-3">
            <div class="avatar"><?= substr($_SESSION['finance_name'] ?? 'U', 0, 1) ?></div>
            <div class="lh-1">
                <div class="fw-bold" style="font-size: 0.9rem;">Suro Finance</div>
                <small class="text-muted" style="font-size: 0.7rem;">
                    Shift: <?= $shiftHours ?>h <?= $shiftMins ?>m
                </small>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="d-flex align-items-center gap-3">
            <!-- Online Toggle -->
            <div class="form-check form-switch m-0" title="Toggle Online Status">
                <input class="form-check-input status-switch" type="checkbox" id="workStatus" <?= $isOnline ? 'checked' : '' ?> onchange="toggleStatus(this.checked)">
                <span id="statusLabel" class="badge <?= $isOnline ? 'bg-success' : 'bg-secondary' ?> ms-1" style="font-size: 0.6rem; vertical-align: top;">
                    <?= $isOnline ? 'ONLINE' : 'OFF' ?>
                </span>
            </div>

            <!-- Theme Toggle -->
            <button class="btn btn-link text-secondary p-0" onclick="toggleTheme()"><i class="bi bi-moon-stars fs-5"></i></button>
        </div>
    </div>
</nav>

<!-- Status Toggle Logic -->
<script>
async function toggleStatus(isOnline) {
    const label = document.getElementById('statusLabel');
    // Optimistic UI Update
    label.className = isOnline ? 'badge bg-success ms-1' : 'badge bg-secondary ms-1';
    label.innerText = isOnline ? 'ONLINE' : 'OFF';

    try {
        // Point to the API created in Step 58
        // Using absolute path relative to web root is safer in some configs, but relative is fine if structure maintained
        const res = await fetch('https://apisuro.online/staff/status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ status: isOnline })
        });
        
        if (!res.ok) throw new Error('API Error');
        
        const data = await res.json();
        
        if(data.status !== 'success') {
            throw new Error(data.error || 'Failed');
        }
    } catch(e) {
        console.error("Status update failed", e);
        // Revert UI if failed
        document.getElementById('workStatus').checked = !isOnline;
        label.className = !isOnline ? 'badge bg-success ms-1' : 'badge bg-secondary ms-1';
        label.innerText = !isOnline ? 'ONLINE' : 'OFF';
        alert("Connection Error: Could not update status.");
    }
}
</script>

<!-- Content Container Starts -->
<div class="container mt-5 pt-4 mb-5 pb-4">