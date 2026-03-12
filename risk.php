<?php
require_once 'layout/header.php';

// 1. Velocity Check (Deposit & Withdraw within 10 mins)
$velocityUsers = $pdo->query("
    SELECT u.username, u.phone, t1.created_at as deposit_time, t2.created_at as withdraw_time, 
           TIMESTAMPDIFF(MINUTE, t1.created_at, t2.created_at) as diff_mins
    FROM transactions t1
    JOIN transactions t2 ON t1.user_id = t2.user_id
    JOIN users u ON t1.user_id = u.id
    WHERE t1.type = 'deposit' AND t2.type = 'withdraw'
    AND t2.created_at > t1.created_at
    AND t2.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND TIMESTAMPDIFF(MINUTE, t1.created_at, t2.created_at) <= 10
    ORDER BY t2.created_at DESC
    LIMIT 20
")->fetchAll();

// 2. Arbitrage/RTP Alert (Users winning > 120% of bets in last 24h)
$arbitrageUsers = $pdo->query("
    SELECT u.username, 
           SUM(g.bet) as total_bet, 
           SUM(g.win) as total_win,
           (SUM(g.win) / NULLIF(SUM(g.bet),0)) * 100 as rtp
    FROM game_logs g
    JOIN users u ON g.user_id = u.id
    WHERE g.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY g.user_id
    HAVING total_bet > 20000 AND rtp > 120
    ORDER BY rtp DESC
    LIMIT 20
")->fetchAll();

// 3. IP Clustering (Accounts sharing IPs)
$ipClusters = $pdo->query("
    SELECT last_ip, COUNT(DISTINCT id) as account_count, 
           GROUP_CONCAT(username SEPARATOR ', ') as accounts
    FROM users
    WHERE last_ip IS NOT NULL AND last_ip != ''
    GROUP BY last_ip
    HAVING account_count > 1
    ORDER BY account_count DESC
    LIMIT 20
")->fetchAll();
?>

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-risk { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
</style>
<div id="sakura-container-risk"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-risk');
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
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white fw-black mb-0 italic tracking-widest">RISK RADAR</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">リスク管理</div>
        </div>
        <button class="btn btn-sm px-3 py-2 rounded-pill fw-bold border border-info text-info shadow-[0_0_10px_rgba(13,202,240,0.3)] hover:bg-info hover:text-dark transition" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i> SCAN
        </button>
    </div>

    <div class="row g-4 pb-4">
        
        <!-- VELOCITY ALERTS -->
        <div class="col-lg-6">
            <div class="glass-card h-100 border border-danger border-opacity-50 overflow-hidden">
                <div class="bg-danger bg-opacity-20 text-danger fw-black p-3 border-b border-danger border-opacity-25 tracking-widest italic d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-speedometer me-2"></i> HIGH VELOCITY</span>
                    <span class="badge bg-danger text-white rounded-pill font-mono"><?= count($velocityUsers) ?></span>
                </div>
                <div class="p-3">
                    <p class="text-xs text-muted mb-3 font-mono">Flags users depositing and withdrawing within 10 minutes. Potential money laundering or arbitrage.</p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach($velocityUsers as $v): ?>
                        <div class="bg-black bg-opacity-50 p-3 rounded-3 border border-danger border-opacity-25 d-flex justify-content-between align-items-center hover:bg-opacity-70 transition">
                            <div>
                                <div class="fw-bold text-white fs-6"><?= htmlspecialchars($v['username']) ?></div>
                                <div class="text-muted font-mono" style="font-size: 0.7rem;"><?= htmlspecialchars($v['phone']) ?></div>
                            </div>
                            <div class="text-end">
                                <div class="text-danger fw-black fs-5 animate-pulse"><?= $v['diff_mins'] ?>m Gap</div>
                                <div class="text-muted font-mono" style="font-size: 0.65rem;">Dep: <?= date('H:i', strtotime($v['deposit_time'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; if(empty($velocityUsers)) echo '<div class="text-center text-muted py-4 small border border-dashed border-secondary rounded-3">System Secure. No velocity threats.</div>'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RTP ANOMALIES -->
        <div class="col-lg-6">
            <div class="glass-card h-100 border border-warning border-opacity-50 overflow-hidden">
                <div class="bg-warning bg-opacity-20 text-warning fw-black p-3 border-b border-warning border-opacity-25 tracking-widest italic d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up-arrow me-2"></i> RTP ANOMALY</span>
                    <span class="badge bg-warning text-dark rounded-pill font-mono"><?= count($arbitrageUsers) ?></span>
                </div>
                <div class="p-3">
                    <p class="text-xs text-muted mb-3 font-mono">Users winning >120% of their bets (over 20k volume) in the last 24h. Monitor for exploitation.</p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach($arbitrageUsers as $a): ?>
                        <div class="bg-black bg-opacity-50 p-3 rounded-3 border border-warning border-opacity-25 d-flex justify-content-between align-items-center hover:bg-opacity-70 transition">
                            <div>
                                <div class="fw-bold text-white fs-6"><?= htmlspecialchars($a['username']) ?></div>
                                <div class="d-flex gap-2 mt-1" style="font-size: 0.65rem;">
                                    <span class="text-muted font-mono">In: <?= number_format($a['total_bet']) ?></span>
                                    <span class="text-success font-mono">Out: <?= number_format($a['total_win']) ?></span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-warning fw-black fs-4"><?= number_format($a['rtp'], 1) ?>%</div>
                            </div>
                        </div>
                        <?php endforeach; if(empty($arbitrageUsers)) echo '<div class="text-center text-muted py-4 small border border-dashed border-secondary rounded-3">System Secure. No RTP anomalies.</div>'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- IP CLUSTERS -->
        <div class="col-12">
            <div class="glass-card border border-info border-opacity-50 overflow-hidden">
                <div class="bg-info bg-opacity-20 text-info fw-black p-3 border-b border-info border-opacity-25 tracking-widest italic d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-diagram-3-fill me-2"></i> MULTI-ACCOUNT CLUSTERS</span>
                    <span class="badge bg-info text-dark rounded-pill font-mono"><?= count($ipClusters) ?></span>
                </div>
                <div class="p-3">
                    <p class="text-xs text-muted mb-3 font-mono">Multiple accounts operating from the exact same IP address. High risk of bonus abuse.</p>
                    <div class="row g-3">
                        <?php foreach($ipClusters as $ip): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="bg-black bg-opacity-50 p-3 rounded-3 border border-info border-opacity-25 h-100 hover:border-opacity-50 transition">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-b border-white border-opacity-10">
                                    <span class="font-mono text-cyan-400 fw-bold letter-spacing-1"><?= htmlspecialchars($ip['last_ip']) ?></span>
                                    <span class="badge bg-danger text-white rounded-pill px-2 py-1 shadow-[0_0_10px_red] animate-pulse">
                                        <?= $ip['account_count'] ?> LINKED
                                    </span>
                                </div>
                                <div class="text-muted small lh-sm">
                                    <?= htmlspecialchars($ip['accounts']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; if(empty($ipClusters)) echo '<div class="col-12 text-center text-muted py-4 small border border-dashed border-secondary rounded-3">System Secure. No suspicious IP clusters found.</div>'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout/footer.php'; ?>