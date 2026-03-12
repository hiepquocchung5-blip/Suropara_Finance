<?php
require_once 'layout/header.php';

$staffId = $_SESSION['finance_id'];
$today = date('Y-m-d 00:00:00');

// --- DATA FETCH ---

// 1. My Stats Today
$myStats = $pdo->prepare("
    SELECT 
        COUNT(*) as count, 
        SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as vol_in,
        SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END) as vol_out
    FROM transactions 
    WHERE processed_by_admin_id = ? AND updated_at >= ?
");
$myStats->execute([$staffId, $today]);
$myMetrics = $myStats->fetch();

// 2. Staff Leaderboard (Gamification)
$leaderboard = $pdo->query("
    SELECT a.username, COUNT(t.id) as processed_count
    FROM transactions t
    JOIN admin_users a ON t.processed_by_admin_id = a.id
    WHERE t.updated_at >= '$today'
    GROUP BY a.id
    ORDER BY processed_count DESC
    LIMIT 5
")->fetchAll();

// 3. Active Staff (Real-time)
$activeStaff = $pdo->query("
    SELECT username, role, last_login 
    FROM admin_users 
    WHERE is_online = 1 AND is_active = 1
    ORDER BY last_login DESC
")->fetchAll();

// 4. Payment Methods Status
$banks = $pdo->query("SELECT * FROM payment_methods ORDER BY is_active DESC")->fetchAll();

// 5. Pending Queue Counts
$pendingDep = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='pending'")->fetchColumn();
$pendingWith = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type='withdraw' AND status='pending'")->fetchColumn();

?>

<!-- Sakura Particles Integration -->
<style>
    /* Scoped Sakura Engine for internal pages */
    #sakura-container-dash { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.4; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.4; } 90% { opacity: 0.4; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    
    /* V2 Dashboard Overrides */
    .dash-wrapper { position: relative; z-index: 10; }
    .stat-block { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 10px; }
</style>
<div id="sakura-container-dash"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-dash');
        if(!container) return;
        const petalCount = window.innerWidth < 768 ? 10 : 20;
        for(let i=0; i<petalCount; i++) {
            let p = document.createElement('div');
            p.className = 'sakura-petal';
            p.style.width = p.style.height = (Math.random()*6+4) + 'px';
            p.style.left = Math.random()*100 + 'vw';
            p.style.animationDuration = (Math.random()*8+7) + 's';
            p.style.animationDelay = (Math.random()*5) + 's';
            container.appendChild(p);
        }
    });
</script>

<div class="dash-wrapper">
    <!-- PERSONAL PERFORMANCE CARD -->
    <div class="glass-card p-4 mb-4 position-relative overflow-hidden border border-pink-500 border-opacity-25" style="background: linear-gradient(135deg, rgba(236,72,153,0.1), rgba(139,92,246,0.1));">
        <div class="position-absolute top-0 end-0 p-3 opacity-10"><i class="bi bi-graph-up-arrow" style="font-size: 5rem;"></i></div>
        
        <div class="text-center mb-4 position-relative z-10">
            <span class="badge bg-pink-500 bg-opacity-20 text-pink-300 border border-pink-500 border-opacity-50 mb-2 px-3 py-1 rounded-pill fw-bold tracking-widest uppercase" style="font-size: 0.6rem;">Shift Performance</span>
            <h2 class="fw-black text-white display-3 mb-0 lh-1" style="text-shadow: 0 0 20px rgba(236,72,153,0.5);"><?= number_format($myMetrics['count']) ?></h2>
            <small class="text-muted text-uppercase tracking-widest font-mono" style="font-size: 0.7rem;">Requests Processed</small>
        </div>
        
        <div class="row text-center pt-3 position-relative z-10">
            <div class="col-6">
                <div class="stat-block h-100 d-flex flex-column justify-content-center border-success border-opacity-25">
                    <div class="text-success fw-black fs-5 font-mono">+<?= number_format($myMetrics['vol_in']) ?></div>
                    <small class="text-muted fw-bold mt-1" style="font-size: 0.55rem; letter-spacing: 1px;">DEPOSITS APPROVED</small>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-block h-100 d-flex flex-column justify-content-center border-danger border-opacity-25">
                    <div class="text-danger fw-black fs-5 font-mono">-<?= number_format($myMetrics['vol_out']) ?></div>
                    <small class="text-muted fw-bold mt-1" style="font-size: 0.55rem; letter-spacing: 1px;">WITHDRAWALS SENT</small>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK ACTION WIDGET -->
    <a href="queue.php" class="btn w-100 py-3 rounded-4 shadow-lg mb-4 d-flex justify-content-between align-items-center px-4 text-decoration-none" style="background: linear-gradient(to right, #00f3ff, #0066ff); color: #fff; border: 1px solid rgba(0,243,255,0.5);">
        <span class="fw-black tracking-widest fs-5 text-shadow-sm">OPEN QUEUE</span>
        <div class="d-flex gap-2">
            <?php if($pendingDep > 0): ?><span class="badge bg-black bg-opacity-50 text-success rounded-pill border border-success border-opacity-50 px-2 py-1"><i class="bi bi-arrow-down-circle-fill me-1"></i> <?= $pendingDep ?></span><?php endif; ?>
            <?php if($pendingWith > 0): ?><span class="badge bg-black bg-opacity-50 text-danger rounded-pill border border-danger border-opacity-50 px-2 py-1"><i class="bi bi-arrow-up-circle-fill me-1"></i> <?= $pendingWith ?></span><?php endif; ?>
            <?php if($pendingDep==0 && $pendingWith==0): ?><span class="badge bg-black bg-opacity-50 text-white rounded-pill px-2 py-1"><i class="bi bi-check-circle-fill me-1 text-success"></i> 0</span><?php endif; ?>
        </div>
    </a>

    <div class="row g-3">
        <!-- LEADERBOARD WIDGET -->
        <div class="col-md-6">
            <div class="glass-card h-100 overflow-hidden border border-warning border-opacity-25 p-0">
                <div class="bg-black bg-opacity-40 text-white fw-bold d-flex justify-content-between align-items-center p-3 border-b border-white border-opacity-10">
                    <span class="tracking-widest fs-6 italic"><i class="bi bi-trophy-fill text-warning me-2"></i> TOP AGENTS</span>
                    <span class="badge bg-warning text-dark px-2 rounded-1 font-mono">TODAY</span>
                </div>
                <div class="p-2 space-y-2">
                    <?php foreach($leaderboard as $idx => $l): 
                        $isMe = $l['username'] === $_SESSION['finance_name'];
                        $rankColor = $idx == 0 ? 'text-warning' : ($idx == 1 ? 'text-secondary' : ($idx == 2 ? 'text-orange' : 'text-muted'));
                    ?>
                    <div class="d-flex justify-content-between align-items-center bg-black bg-opacity-50 p-2 rounded-3 <?= $isMe ? 'border border-info bg-info bg-opacity-10' : '' ?>">
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-black font-mono fs-5 <?= $rankColor ?>" style="width: 25px; text-align:center;">#<?= $idx+1 ?></span>
                            <div class="lh-1">
                                <div class="fw-bold text-white fs-6"><?= htmlspecialchars($l['username']) ?></div>
                                <?php if($isMe): ?><small class="text-info fw-bold" style="font-size: 0.6rem;">CURRENT SESSION</small><?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-dark border border-secondary text-light font-mono fs-6 px-3 py-2"><?= $l['processed_count'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($leaderboard)): ?>
                        <div class="text-center text-muted py-4 small">No activity recorded today.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- STATUS WIDGETS -->
        <div class="col-md-6 d-flex flex-column gap-3">
            
            <!-- Online Staff -->
            <div class="glass-card overflow-hidden p-0 border border-info border-opacity-25">
                <div class="bg-black bg-opacity-40 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic fs-6">
                    <i class="bi bi-broadcast text-info me-2"></i> NETWORK STATUS
                </div>
                <div class="p-3">
                    <div class="text-muted small fw-bold mb-2 uppercase" style="font-size: 0.6rem;">Online Operators</div>
                    <?php if(!empty($activeStaff)): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach($activeStaff as $online): ?>
                                <span class="badge bg-black border <?= $online['role'] == 'GOD' ? 'border-danger text-danger' : 'border-success text-success' ?> px-3 py-2 d-flex align-items-center gap-2 rounded-pill shadow-sm">
                                    <span class="spinner-grow spinner-grow-sm" style="width: 0.5rem; height: 0.5rem;"></span>
                                    <?= htmlspecialchars($online['username']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small italic">System isolated. No operators online.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bank Status -->
            <div class="glass-card overflow-hidden p-0 border border-secondary">
                <div class="bg-black bg-opacity-40 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic fs-6">
                    <i class="bi bi-hdd-network text-light me-2"></i> GATEWAY HEALTH
                </div>
                <div class="p-2 space-y-1">
                    <?php foreach($banks as $bank): ?>
                    <div class="d-flex justify-content-between align-items-center bg-black bg-opacity-30 p-2 rounded-3 border border-white border-opacity-5">
                        <div class="d-flex align-items-center gap-2">
                            <?php if($bank['logo_url']): ?>
                                <img src="<?= htmlspecialchars($bank['logo_url']) ?>" width="20" height="20" class="rounded-circle object-fit-cover">
                            <?php else: ?>
                                <i class="bi bi-wallet2 text-secondary"></i>
                            <?php endif; ?>
                            <span class="text-light fw-bold small"><?= htmlspecialchars($bank['provider_name']) ?></span>
                        </div>
                        <?php if($bank['is_active']): ?>
                            <i class="bi bi-check-circle-fill text-success fs-6 drop-shadow"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-danger fs-6 drop-shadow"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'layout/footer.php'; ?>