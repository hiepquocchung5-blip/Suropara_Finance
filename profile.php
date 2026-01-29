<?php
require_once 'layout/header.php';

$staffId = $_SESSION['finance_id'];
$loginTime = date('h:i A', $_SESSION['shift_start']);

// Fetch Career Stats
$career = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
        MAX(updated_at) as last_active
    FROM transactions 
    WHERE processed_by_admin_id = ?
");
$career->execute([$staffId]);
$stats = $career->fetch();

// --- BADGE LOGIC ---
$badges = [];
if ($stats['total'] >= 100) $badges[] = ['icon' => 'bi-stars', 'name' => 'Veteran', 'color' => 'warning'];
if ($stats['total'] >= 1000) $badges[] = ['icon' => 'bi-gem', 'name' => 'Legend', 'color' => 'info'];
if ($stats['total'] >= 5000) $badges[] = ['icon' => 'bi-trophy-fill', 'name' => 'Master', 'color' => 'danger'];

// "Night Owl" badge if currently working late (10 PM - 5 AM)
$currentHour = (int)date('H');
if ($currentHour >= 22 || $currentHour <= 5) {
    $badges[] = ['icon' => 'bi-moon-stars-fill', 'name' => 'Night Owl', 'color' => 'purple'];
}
?>

<!-- PROFILE HEADER -->
<div class="card mb-4 border-0 bg-transparent text-center">
    <div class="card-body">
        <div class="avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 3rem; box-shadow: 0 0 20px rgba(255, 215, 0, 0.2);">
            <?= substr($_SESSION['finance_name'], 0, 1) ?>
        </div>
        <h3 class="fw-black text-white mb-0"><?= htmlspecialchars($_SESSION['finance_name']) ?></h3>
        <span class="badge bg-warning text-dark mt-2"><?= $_SESSION['finance_role'] ?> OFFICER</span>
        
        <!-- BADGES DISPLAY -->
        <?php if (!empty($badges)): ?>
        <div class="mt-3 d-flex justify-content-center flex-wrap gap-2">
            <?php foreach($badges as $b): ?>
                <span class="badge bg-<?= $b['color'] ?> text-dark border border-white bg-opacity-75" title="<?= $b['name'] ?>">
                    <i class="bi <?= $b['icon'] ?>"></i> <?= $b['name'] ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CURRENT SESSION -->
<div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10 border-info text-info fw-bold">
        <i class="bi bi-clock-history me-2"></i> CURRENT SHIFT
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-50 pb-2 mb-2">
            <span class="text-muted">Clocked In</span>
            <span class="fw-bold text-white"><?= $loginTime ?></span>
        </div>
        <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-50 pb-2 mb-2">
            <span class="text-muted">Duration</span>
            <span class="fw-bold text-success"><?= $shiftHours ?>h <?= $shiftMins ?>m</span>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-muted">Status</span>
            <span class="badge <?= $isOnline ? 'bg-success' : 'bg-secondary' ?>"><?= $isOnline ? 'ONLINE' : 'AWAY' ?></span>
        </div>
    </div>
</div>

<!-- LIFETIME STATS -->
<div class="card mb-4 border-secondary">
    <div class="card-header bg-transparent border-secondary text-white fw-bold">
        <i class="bi bi-bar-chart-line me-2"></i> CAREER PERFORMANCE
    </div>
    <div class="card-body">
        <div class="row text-center g-2">
            <div class="col-4">
                <div class="bg-dark p-2 rounded border border-secondary h-100 d-flex flex-column justify-content-center">
                    <div class="small text-muted mb-1">TOTAL</div>
                    <div class="fs-4 fw-bold text-white"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="bg-dark p-2 rounded border border-success h-100 d-flex flex-column justify-content-center">
                    <div class="small text-success mb-1">APPROVED</div>
                    <div class="fs-4 fw-bold text-success"><?= number_format($stats['approved']) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="bg-dark p-2 rounded border border-danger h-100 d-flex flex-column justify-content-center">
                    <div class="small text-danger mb-1">REJECTED</div>
                    <div class="fs-4 fw-bold text-danger"><?= number_format($stats['rejected']) ?></div>
                </div>
            </div>
        </div>
        <div class="mt-3 text-center small text-muted">
            Last Action: <span class="text-info"><?= $stats['last_active'] ? date('M d, Y H:i', strtotime($stats['last_active'])) : 'N/A' ?></span>
        </div>
    </div>
</div>

<!-- ADVANCED TOOLS (Risk & Security) -->
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger bg-opacity-10 border-danger text-danger fw-bold">
        <i class="bi bi-shield-lock me-2"></i> SECURITY TOOLS
    </div>
    <div class="card-body d-grid gap-2">
        <a href="risk.php" class="btn btn-outline-danger fw-bold text-start">
            <i class="bi bi-graph-up-arrow me-2"></i> Risk Analysis Dashboard
        </a>
    </div>
</div>

<!-- ACTIONS -->
<div class="d-grid gap-2">
    <button class="btn btn-outline-light" onclick="toggleTheme()">
        <i class="bi bi-moon-stars me-2"></i> Toggle Dark/Light Mode
    </button>
    <form method="POST" action="index.php" class="mt-2">
        <!-- Logs out by redirecting to login page which handles session destruction via script or self-submit -->
        <button type="button" onclick="location.href='index.php'" class="btn btn-danger fw-bold w-100 py-3">
            <i class="bi bi-box-arrow-right me-2"></i> END SHIFT & LOGOUT
        </button>
    </form>
</div>

<?php require_once 'layout/footer.php'; ?>