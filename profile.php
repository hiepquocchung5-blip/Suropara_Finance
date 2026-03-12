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

<!-- Sakura Particles Integration -->
<style>
    #sakura-container-profile { position: fixed; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
    .sakura-petal { position: absolute; background: linear-gradient(135deg, #ffb3c6, #ff6699); border-radius: 15px 0px 15px 0px; opacity: 0.3; animation: fall linear infinite; box-shadow: 0 0 5px rgba(255, 182, 193, 0.3); }
    @keyframes fall { 0% { transform: translate(0, -10vh) rotate(0deg); opacity: 0; } 10% { opacity: 0.3; } 90% { opacity: 0.3; } 100% { transform: translate(20vw, 110vh) rotate(360deg); opacity: 0; } }
    .dash-wrapper { position: relative; z-index: 10; }
    .badge-purple { background-color: rgba(168, 85, 247, 0.2); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.5); }
</style>
<div id="sakura-container-profile"></div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const container = document.getElementById('sakura-container-profile');
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
            <h3 class="text-white fw-black mb-0 italic tracking-widest">AGENT PROFILE</h3>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.7rem; letter-spacing: 2px;">プロフィール</div>
        </div>
    </div>

    <!-- PROFILE IDENTITY CARD -->
    <div class="glass-card p-4 text-center mb-4 border border-pink-500 border-opacity-30 position-relative overflow-hidden" style="background: linear-gradient(135deg, rgba(236,72,153,0.1), rgba(139,92,246,0.1));">
        <div class="position-absolute top-0 start-50 translate-middle-x w-100 h-100 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')] opacity-20 pointer-events-none mix-blend-color-dodge"></div>
        
        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center text-white fw-black display-4 shadow-lg mb-3 position-relative z-10" style="width: 90px; height: 90px; background: linear-gradient(135deg, #ec4899, #8b5cf6); box-shadow: 0 0 30px rgba(236,72,153,0.5);">
            <?= strtoupper(substr($_SESSION['finance_name'], 0, 1)) ?>
        </div>
        
        <h2 class="fw-black text-white mb-1 position-relative z-10 tracking-wide"><?= htmlspecialchars($_SESSION['finance_name']) ?></h2>
        <span class="badge bg-black bg-opacity-50 border border-secondary text-info mt-1 px-3 py-2 rounded-pill font-mono tracking-widest position-relative z-10">
            <?= $_SESSION['finance_role'] ?> TIER
        </span>
        
        <!-- BADGES DISPLAY -->
        <?php if (!empty($badges)): ?>
        <div class="mt-4 d-flex justify-content-center flex-wrap gap-2 position-relative z-10">
            <?php foreach($badges as $b): 
                $badgeClass = $b['color'] === 'purple' ? 'badge-purple' : "bg-{$b['color']} bg-opacity-20 text-{$b['color']} border border-{$b['color']} border-opacity-50";
            ?>
                <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill shadow-sm" title="<?= $b['name'] ?>">
                    <i class="bi <?= $b['icon'] ?> me-1"></i> <?= $b['name'] ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- CURRENT SESSION -->
    <div class="glass-card mb-4 border border-info border-opacity-30 p-0 overflow-hidden">
        <div class="bg-info bg-opacity-20 text-info fw-black p-3 border-b border-info border-opacity-25 tracking-widest italic d-flex align-items-center">
            <i class="bi bi-clock-history me-2"></i> CURRENT SHIFT
        </div>
        <div class="p-3 bg-black bg-opacity-40">
            <div class="d-flex justify-content-between align-items-center border-bottom border-white border-opacity-10 pb-2 mb-2">
                <span class="text-muted small text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Clocked In</span>
                <span class="fw-bold text-white font-mono"><?= $loginTime ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center border-bottom border-white border-opacity-10 pb-2 mb-2">
                <span class="text-muted small text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Duration</span>
                <span class="fw-bold text-info font-mono"><?= $shiftHours ?>h <?= $shiftMins ?>m</span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Status</span>
                <span class="badge <?= $isOnline ? 'bg-success bg-opacity-20 text-success border border-success border-opacity-50' : 'bg-secondary bg-opacity-20 text-secondary border border-secondary' ?> px-2 py-1 rounded-pill d-flex align-items-center gap-1">
                    <?php if($isOnline): ?>
                        <span class="spinner-grow spinner-grow-sm" style="width: 4px; height: 4px;"></span> ONLINE
                    <?php else: ?>
                        AWAY
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- LIFETIME STATS -->
    <div class="glass-card mb-4 border border-secondary p-0 overflow-hidden">
        <div class="bg-black bg-opacity-60 text-white fw-bold p-3 border-b border-white border-opacity-10 tracking-widest italic">
            <i class="bi bi-bar-chart-line text-yellow-500 me-2"></i> CAREER PERFORMANCE
        </div>
        <div class="p-3">
            <div class="row text-center g-2 mb-3">
                <div class="col-12">
                    <div class="bg-black bg-opacity-50 p-3 rounded-3 border border-secondary border-opacity-50 d-flex flex-column justify-content-center">
                        <div class="small text-muted mb-1 text-uppercase fw-bold tracking-widest" style="font-size: 0.65rem;">Total Lifetime Hits</div>
                        <div class="fs-2 fw-black text-white font-mono lh-1"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-success bg-opacity-10 p-2 rounded-3 border border-success border-opacity-25 h-100 d-flex flex-column justify-content-center">
                        <div class="small text-success mb-1 text-uppercase fw-bold tracking-widest" style="font-size: 0.6rem;">Approved</div>
                        <div class="fs-4 fw-black text-success font-mono">+<?= number_format($stats['approved']) ?></div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-danger bg-opacity-10 p-2 rounded-3 border border-danger border-opacity-25 h-100 d-flex flex-column justify-content-center">
                        <div class="small text-danger mb-1 text-uppercase fw-bold tracking-widest" style="font-size: 0.6rem;">Rejected</div>
                        <div class="fs-4 fw-black text-danger font-mono">-<?= number_format($stats['rejected']) ?></div>
                    </div>
                </div>
            </div>
            <div class="text-center text-muted d-flex justify-content-center align-items-center gap-2" style="font-size: 0.65rem;">
                <span class="text-uppercase fw-bold">Last Action <span class="font-serif">[最終操作]</span>:</span>
                <span class="text-info font-mono bg-black px-2 py-1 rounded border border-white border-opacity-10">
                    <?= $stats['last_active'] ? date('M d, Y H:i', strtotime($stats['last_active'])) : 'N/A' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- SECURITY / RISK LINK -->
    <a href="risk.php" class="btn w-100 py-3 rounded-4 shadow-lg mb-3 d-flex justify-content-between align-items-center px-4 text-decoration-none transition-transform active:scale-95" style="background: linear-gradient(to right, #ef4444, #991b1b); color: #fff; border: 1px solid rgba(239,68,68,0.5);">
        <span class="fw-black tracking-widest fs-6 text-shadow-sm"><i class="bi bi-shield-lock me-2"></i> RISK RADAR</span>
        <i class="bi bi-chevron-right"></i>
    </a>

    <!-- LOGOUT ACTION -->
    <form method="POST" action="index.php" class="mb-4">
        <button type="submit" class="btn w-100 py-3 rounded-4 fw-black shadow-lg d-flex justify-content-center align-items-center gap-2 transition-transform active:scale-95" style="background: rgba(0,0,0,0.6); color: #ef4444; border: 1px solid rgba(239,68,68,0.3);" onclick="showLoader()">
            <i class="bi bi-power"></i> END SHIFT & LOGOUT
        </button>
    </form>
</div>

<?php require_once 'layout/footer.php'; ?>