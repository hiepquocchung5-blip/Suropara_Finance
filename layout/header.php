<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
requireFinanceAuth();

$staffId = $_SESSION['finance_id'];
$isOnline = 0; 
try {
    $stmtS = $pdo->prepare("SELECT is_online FROM admin_users WHERE id = ?");
    $stmtS->execute([$staffId]);
    $isOnline = (int)$stmtS->fetchColumn();
} catch (Exception $e) {}

if (!isset($_SESSION['shift_start']) || $_SESSION['shift_start'] > time()) {
    $_SESSION['shift_start'] = time();
}
$shiftTime = time() - $_SESSION['shift_start'];
$shiftHours = floor($shiftTime / 3600);
$shiftMins = floor(($shiftTime % 3600) / 60);

// Ensure we have a defined API_BASE_URL to prevent JS fetch errors
if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', getEnvSafe('API_PUBLIC_URL', 'https://apisuro.online')); 
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <!-- PWA Meta Tags -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#050505">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.json">
    
    <title>Suro Bank V2</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&family=JetBrains+Mono:wght@700&display=swap');
        
        :root {
            --bg-dark: #050505;
            --bg-card: rgba(30, 41, 59, 0.7);
            --accent-gold: #FFD700;
            --accent-cyan: #00f3ff;
        }

        body { 
            background-color: var(--bg-dark); 
            color: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            padding-bottom: 90px; 
            padding-top: 70px;
            -webkit-font-smoothing: antialiased;
            /* Prevent pull-to-refresh on mobile app */
            overscroll-behavior-y: none;
        }

        /* Glassmorphism V2 */
        .glass-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .font-mono { font-family: 'JetBrains Mono', monospace !important; }
        
        /* Mobile App Top Bar */
        .app-header {
            background: rgba(5, 5, 5, 0.85);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            height: 60px;
            /* Support iOS Notch */
            padding-top: env(safe-area-inset-top);
        }

        /* Floating Bottom Dock */
        .bottom-dock {
            position: fixed;
            bottom: max(15px, env(safe-area-inset-bottom));
            left: 15px;
            right: 15px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 8px 5px;
            display: flex;
            justify-content: space-around;
            z-index: 1000;
            box-shadow: 0 10px 40px rgba(0,0,0,0.8);
        }

        .dock-item {
            color: #64748b;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 0.65rem;
            font-weight: 800;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 20%;
        }
        
        .dock-item i { font-size: 1.4rem; margin-bottom: 2px; transition: transform 0.3s; }
        .dock-item.active { color: var(--accent-cyan); }
        .dock-item.active i { transform: translateY(-3px) scale(1.1); filter: drop-shadow(0 0 8px rgba(0,243,255,0.5)); }

        /* Action Loader Overlay */
        #action-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        /* Utils */
        .btn-gold { background: linear-gradient(to right, #FFD700, #FDB931); color: #000; font-weight: 900; border: none; }
        .btn-gold:active { transform: scale(0.95); }
    </style>
</head>
<body>

<!-- PWA Service Worker Registration -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js').catch(err => {
                console.log('SW registration failed: ', err);
            });
        });
    }
</script>

<!-- TOP APP BAR -->
<nav class="fixed-top app-header d-flex align-items-center justify-content-between px-3 z-3">
    <div class="d-flex align-items-center gap-2">
        <div class="rounded-circle d-flex align-items-center justify-content-center text-dark fw-bold" style="width: 32px; height: 32px; background: var(--accent-cyan);">
            <?= strtoupper(substr($_SESSION['finance_name'], 0, 1)) ?>
        </div>
        <div class="lh-1">
            <div class="fw-black" style="font-size: 0.85rem; letter-spacing: 1px;">SURO<span class="text-info">BANK</span></div>
            <small class="text-muted" style="font-size: 0.65rem;">Shift: <?= $shiftHours ?>h <?= $shiftMins ?>m</small>
        </div>
    </div>
    
    <!-- Status Toggle -->
    <div class="d-flex align-items-center gap-2">
        <span id="statusLabel" class="badge <?= $isOnline ? 'bg-success' : 'bg-secondary' ?>" style="font-size: 0.6rem; letter-spacing: 1px;">
            <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
        </span>
        <div class="form-check form-switch m-0 fs-5">
            <input class="form-check-input" type="checkbox" id="workStatus" <?= $isOnline ? 'checked' : '' ?> onchange="toggleStatus(this.checked)">
        </div>
    </div>
</nav>

<!-- FULLSCREEN ACTION LOADER -->
<div id="action-overlay">
    <div class="spinner-border text-info" style="width: 3rem; height: 3rem;" role="status"></div>
    <div class="text-info font-mono fw-bold mt-3 tracking-widest animate-pulse">PROCESSING...</div>
</div>

<!-- API Handshake Script -->
<script>
const API_BASE_URL = "<?= rtrim(API_BASE_URL, '/') ?>";

async function toggleStatus(isOnline) {
    const label = document.getElementById('statusLabel');
    label.className = isOnline ? 'badge bg-success' : 'badge bg-secondary';
    label.innerText = isOnline ? 'ONLINE' : 'OFFLINE';

    try {
        const res = await fetch(`${API_BASE_URL}/staff/status.php`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ status: isOnline })
        });
        const data = await res.json();
        if(data.status !== 'success') throw new Error(data.error);
    } catch(e) {
        document.getElementById('workStatus').checked = !isOnline;
        label.className = !isOnline ? 'badge bg-success' : 'badge bg-secondary';
        label.innerText = !isOnline ? 'ONLINE' : 'OFFLINE';
        alert("API Sync Error: Could not update status. Check your connection.");
    }
}

function showLoader() {
    document.getElementById('action-overlay').style.display = 'flex';
}
</script>

<div class="container-fluid px-3">