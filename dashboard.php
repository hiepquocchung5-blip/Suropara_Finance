<?php
require_once 'layout/header.php';

$staffId = $_SESSION['finance_id'];
$today = date('Y-m-d 00:00:00');

// --- DATA FETCH ---

// 1. My Stats Today
// Tracks how much volume this specific staff member has processed since the start of the day
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
// Shows top performing staff members for the current day
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
// Updated to use 'is_online' column from Step 51/58 for accurate shift tracking
$activeStaff = $pdo->query("
    SELECT username, role, last_login 
    FROM admin_users 
    WHERE is_online = 1 AND is_active = 1
    ORDER BY last_login DESC
")->fetchAll();

// 4. Payment Methods Status
// Shows which banks are currently turned ON for users
$banks = $pdo->query("SELECT * FROM payment_methods ORDER BY is_active DESC")->fetchAll();

// 5. Pending Queue Counts (Badge indicators)
$pendingDep = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type='deposit' AND status='pending'")->fetchColumn();
$pendingWith = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type='withdraw' AND status='pending'")->fetchColumn();

?>

<!-- PERSONAL PERFORMANCE CARD -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card p-4 border-warning border-opacity-25 bg-gradient shadow-sm">
            <div class="text-center mb-4">
                <span class="badge bg-warning text-dark mb-2 px-3 py-2 rounded-pill fw-bold">YOUR SHIFT PERFORMANCE</span>
                <h2 class="fw-black text-white display-3 mb-0 lh-1"><?= number_format($myMetrics['count']) ?></h2>
                <small class="text-muted text-uppercase tracking-widest">Requests Processed</small>
            </div>
            <div class="row text-center pt-3 border-top border-secondary border-opacity-50">
                <div class="col-6 border-end border-secondary border-opacity-50">
                    <div class="text-success fw-bold fs-5">+<?= number_format($myMetrics['vol_in']) ?></div>
                    <small class="text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">DEPOSITS APPROVED</small>
                </div>
                <div class="col-6">
                    <div class="text-danger fw-bold fs-5">-<?= number_format($myMetrics['vol_out']) ?></div>
                    <small class="text-muted" style="font-size: 0.65rem; letter-spacing: 1px;">WITHDRAWALS SENT</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- LEADERBOARD WIDGET -->
    <div class="col-md-6">
        <div class="card border-secondary h-100 shadow-sm">
            <div class="card-header bg-transparent border-secondary text-white fw-bold d-flex justify-content-between align-items-center py-3">
                <span><i class="bi bi-trophy-fill text-warning me-2"></i> TOP AGENTS</span>
                <span class="badge bg-dark border border-secondary text-muted">TODAY</span>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach($leaderboard as $idx => $l): ?>
                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-secondary text-white py-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-bold text-muted" style="width: 20px;">#<?= $idx+1 ?></span>
                        <div class="d-flex flex-col">
                            <span class="fw-bold"><?= htmlspecialchars($l['username']) ?></span>
                            <?php if($l['username'] === $_SESSION['finance_name']): ?>
                                <span class="badge bg-info text-dark" style="font-size: 0.6rem; width: fit-content;">YOU</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill px-3"><?= $l['processed_count'] ?></span>
                </li>
                <?php endforeach; ?>
                <?php if(empty($leaderboard)): ?>
                    <li class="list-group-item bg-transparent text-center text-muted py-5">
                        <i class="bi bi-cup-hot fs-1 d-block mb-2 opacity-50"></i>
                        No activity recorded today.
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- STATUS WIDGETS -->
    <div class="col-md-6">
        <!-- Online Staff -->
        <div class="card border-secondary mb-3 shadow-sm">
            <div class="card-header bg-transparent border-secondary text-white fw-bold py-3">
                <i class="bi bi-people-fill text-info me-2"></i> WHO IS ONLINE?
            </div>
            <div class="card-body">
                <?php if(!empty($activeStaff)): ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach($activeStaff as $online): ?>
                            <span class="badge bg-dark border <?= $online['role'] == 'GOD' ? 'border-danger text-danger' : 'border-success text-success' ?> p-2 d-flex align-items-center gap-2">
                                <i class="bi bi-circle-fill" style="font-size: 6px;"></i> 
                                <?= htmlspecialchars($online['username']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">No other staff online.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bank Status -->
        <div class="card border-secondary shadow-sm">
            <div class="card-header bg-transparent border-secondary text-white fw-bold py-3">
                <i class="bi bi-bank2 text-light me-2"></i> BANK STATUS
            </div>
            <ul class="list-group list-group-flush small">
                <?php foreach($banks as $bank): ?>
                <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center border-secondary text-white py-2">
                    <div class="d-flex align-items-center gap-2">
                        <?php if($bank['logo_url']): ?>
                            <img src="<?= htmlspecialchars($bank['logo_url']) ?>" width="20" height="20" class="rounded-circle">
                        <?php else: ?>
                            <i class="bi bi-wallet2 text-secondary"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($bank['provider_name']) ?></span>
                    </div>
                    <?php if($bank['is_active']): ?>
                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">ONLINE</span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25">OFFLINE</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- CTA BUTTON -->
<div class="mt-5 text-center">
    <a href="queue.php" class="btn btn-success btn-lg w-100 fw-bold shadow-lg py-3 position-relative overflow-hidden">
        <span class="position-relative z-1">START PROCESSING QUEUE</span>
        <?php if($pendingDep + $pendingWith > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light">
                <?= $pendingDep + $pendingWith ?>
                <span class="visually-hidden">pending requests</span>
            </span>
        <?php endif; ?>
    </a>
    <div class="mt-2 text-muted small">
        <i class="bi bi-arrow-down-circle text-success"></i> <?= $pendingDep ?> Deposits &nbsp; | &nbsp; 
        <i class="bi bi-arrow-up-circle text-danger"></i> <?= $pendingWith ?> Withdrawals
    </div>
</div>

<?php require_once 'layout/footer.php'; ?>