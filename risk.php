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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-white fw-bold mb-0">RISK INTELLIGENCE</h4>
    <button class="btn btn-outline-light btn-sm" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise"></i> REFRESH DATA
    </button>
</div>

<div class="row g-4">
    <!-- VELOCITY ALERTS -->
    <div class="col-lg-6">
        <div class="card border-danger h-100 shadow-sm bg-dark">
            <div class="card-header bg-danger bg-opacity-10 text-danger fw-bold border-danger">
                <i class="bi bi-speedometer2 me-2"></i> HIGH VELOCITY (In/Out &lt; 10m)
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0 small">
                    <thead>
                        <tr><th>User</th><th>Dep. Time</th><th>Gap</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($velocityUsers as $v): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($v['username']) ?></div>
                                <span class="text-muted font-monospace"><?= htmlspecialchars($v['phone']) ?></span>
                            </td>
                            <td><?= date('H:i', strtotime($v['deposit_time'])) ?></td>
                            <td class="text-danger fw-bold"><?= $v['diff_mins'] ?> mins</td>
                        </tr>
                        <?php endforeach; if(empty($velocityUsers)) echo '<tr><td colspan="3" class="text-center text-muted py-3">No velocity alerts.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RTP ANOMALIES -->
    <div class="col-lg-6">
        <div class="card border-warning h-100 shadow-sm bg-dark">
            <div class="card-header bg-warning bg-opacity-10 text-warning fw-bold border-warning">
                <i class="bi bi-graph-up-arrow me-2"></i> RTP ANOMALIES (>120%)
            </div>
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0 small">
                    <thead>
                        <tr><th>User</th><th>Bet</th><th>Win</th><th>RTP</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($arbitrageUsers as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['username']) ?></td>
                            <td class="text-muted"><?= number_format($a['total_bet']) ?></td>
                            <td class="text-success"><?= number_format($a['total_win']) ?></td>
                            <td class="text-warning fw-bold"><?= number_format($a['rtp'], 1) ?>%</td>
                        </tr>
                        <?php endforeach; if(empty($arbitrageUsers)) echo '<tr><td colspan="4" class="text-center text-muted py-3">No RTP alerts.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- IP CLUSTERS -->
    <div class="col-12">
        <div class="card border-info shadow-sm bg-dark">
            <div class="card-header bg-info bg-opacity-10 text-info fw-bold border-info">
                <i class="bi bi-diagram-3-fill me-2"></i> MULTI-ACCOUNT CLUSTERS
            </div>
            <div class="card-body p-0">
                <table class="table table-dark table-sm mb-0 small">
                    <thead>
                        <tr><th width="20%">IP Address</th><th width="10%">Count</th><th>Linked Accounts</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($ipClusters as $ip): ?>
                        <tr>
                            <td class="font-monospace text-info"><?= htmlspecialchars($ip['last_ip']) ?></td>
                            <td><span class="badge bg-danger rounded-pill"><?= $ip['account_count'] ?></span></td>
                            <td class="text-muted text-truncate" style="max-width: 400px;" title="<?= htmlspecialchars($ip['accounts']) ?>">
                                <?= htmlspecialchars($ip['accounts']) ?>
                            </td>
                        </tr>
                        <?php endforeach; if(empty($ipClusters)) echo '<tr><td colspan="3" class="text-center text-muted py-3">No suspicious IP clusters found.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'layout/footer.php'; ?>