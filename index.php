<?php
require_once 'config.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Agent ID and Key are required.";
    } else {
        try {
            // Check username and active status
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $staff = $stmt->fetch();

            if ($staff) {
                if (!$staff['is_active']) {
                    $error = "Account Deactivated. Contact Admin.";
                } elseif (password_verify($password, $staff['password_hash'])) {
                    if (in_array($staff['role'], ['FINANCE', 'GOD'])) {
                        // Security: Prevent Session Fixation
                        session_regenerate_id(true);

                        $_SESSION['finance_id'] = $staff['id'];
                        $_SESSION['finance_name'] = $staff['username'];
                        $_SESSION['finance_role'] = $staff['role'];
                        $_SESSION['shift_start'] = time();
                        
                        // Update Last Login
                        $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$staff['id']]);
                        
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $error = "Insufficient Permissions.";
                    }
                } else {
                    $error = "Invalid Credentials.";
                }
            } else {
                // Generic message to prevent username enumeration
                $error = "Invalid Credentials.";
            }
        } catch (Exception $e) {
            $error = "System Error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>SuroBank V2 - Agent Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&family=Noto+Sans+JP:wght@400;700;900&display=swap');

        body, html { 
            margin: 0; 
            padding: 0; 
            height: 100%; 
            background-color: #050505; 
            font-family: 'Inter', 'Noto Sans JP', sans-serif;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Ambient Japanese Night Background */
        .bg-atmosphere {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 0%, rgba(236, 72, 153, 0.15) 0%, transparent 60%),
                        radial-gradient(circle at 100% 100%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
            z-index: 0;
        }

        /* Mobile-First Glassmorphism Card */
        .glass-login { 
            width: 100%; 
            max-width: 400px; 
            background: rgba(15, 23, 42, 0.6); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 192, 203, 0.15); 
            border-radius: 24px; 
            padding: 2.5rem 2rem; 
            z-index: 10; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8), inset 0 1px 0 rgba(255,255,255,0.1); 
            margin: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Subtle Inner Glow */
        .glass-login::before {
            content: '';
            position: absolute;
            top: 0; left: -50%; width: 200%; height: 100%;
            background: radial-gradient(circle at 50% 0%, rgba(255, 255, 255, 0.05), transparent 60%);
            pointer-events: none;
        }

        /* Custom Inputs */
        .form-floating > .form-control {
            background: rgba(0,0,0,0.5); 
            border: 1px solid rgba(255,255,255,0.1); 
            color: white; 
            border-radius: 16px; 
        }
        .form-floating > .form-control:focus { 
            background: rgba(0,0,0,0.8); 
            border-color: #ec4899; /* Pink */
            box-shadow: 0 0 15px rgba(236, 72, 153, 0.2); 
            color: white;
        }
        .form-floating > label {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #ec4899;
            transform: scale(0.85) translateY(-0.75rem) translateX(0.15rem);
        }

        /* Dynamic Button */
        .btn-auth { 
            background: linear-gradient(135deg, #ec4899, #8b5cf6); /* Pink to Purple */
            color: #fff; 
            font-weight: 900; 
            letter-spacing: 2px;
            border: none; 
            border-radius: 16px; 
            padding: 18px; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            box-shadow: 0 10px 20px rgba(236, 72, 153, 0.3);
        }
        .btn-auth:active { 
            transform: scale(0.96); 
            box-shadow: 0 5px 10px rgba(236, 72, 153, 0.2);
        }

        /* Sakura Particle Engine */
        #sakura-container {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 1;
        }
        .sakura-petal {
            position: absolute;
            background: linear-gradient(135deg, #ffb3c6, #ff6699);
            border-radius: 15px 0px 15px 0px;
            opacity: 0.6;
            animation: fall linear infinite;
            box-shadow: 0 0 5px rgba(255, 182, 193, 0.5);
        }
        @keyframes fall {
            0% { transform: translate(0, -10vh) rotate(0deg) rotateX(0deg); opacity: 0; }
            10% { opacity: 0.6; }
            90% { opacity: 0.6; }
            100% { transform: translate(20vw, 110vh) rotate(360deg) rotateX(360deg); opacity: 0; }
        }
    </style>
</head>
<body>

    <!-- Sakura Particles -->
    <div id="sakura-container"></div>
    <div class="bg-atmosphere"></div>

    <div class="glass-login">
        <div class="text-center mb-4 relative z-10">
            <h2 class="fw-black text-white m-0" style="letter-spacing: 2px; font-size: 2.2rem;">
                SURO<span style="color:#ec4899">BANK</span>
            </h2>
            <div class="text-pink-400 fw-bold mt-1" style="font-size: 0.8rem; letter-spacing: 3px;">財務ポータル</div>
            <div class="badge bg-black border border-secondary text-muted mt-3 px-3 py-2 rounded-pill shadow-sm" style="letter-spacing: 1px;">
                <i class="bi bi-shield-lock-fill text-warning me-1"></i> AUTHORIZED AGENTS ONLY
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="alert bg-danger bg-opacity-25 text-danger border-danger text-center fw-bold small rounded-4 p-3 mb-4 shadow-sm animate-pulse relative z-10">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm" class="relative z-10">
            <div class="form-floating mb-3">
                <input type="text" name="username" id="floatingId" class="form-control fw-bold font-monospace" placeholder="Agent ID" required autocomplete="off">
                <label for="floatingId"><i class="bi bi-person-badge me-1"></i> AGENT ID</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" name="password" id="floatingKey" class="form-control fw-bold font-monospace" placeholder="Secure Key" required>
                <label for="floatingKey"><i class="bi bi-key-fill me-1"></i> SECURE KEY</label>
            </div>
            
            <button type="submit" class="btn btn-auth w-100 d-flex align-items-center justify-content-center gap-2" id="submitBtn">
                <span>AUTHENTICATE</span> <span class="fs-6 font-monospace opacity-75">[ 認証 ]</span>
            </button>
        </form>

        <div class="text-center mt-5 relative z-10">
            <small class="text-muted" style="font-size: 0.65rem; text-transform: uppercase;">
                Suropara Finance System v2.0<br>Encrypted Connection
            </small>
        </div>
    </div>

    <script>
        // Form Loading State
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> CONNECTING...';
            btn.classList.add('disabled');
        });

        // Sakura Particle Engine Generator
        function createSakura() {
            const container = document.getElementById('sakura-container');
            const petalCount = window.innerWidth < 768 ? 15 : 30; // Less on mobile to save battery
            
            for(let i = 0; i < petalCount; i++) {
                let petal = document.createElement('div');
                petal.classList.add('sakura-petal');
                
                // Randomize sizes and start positions
                let size = Math.random() * 8 + 6; // 6px to 14px
                petal.style.width = size + 'px';
                petal.style.height = size + 'px';
                petal.style.left = Math.random() * 100 + 'vw';
                
                // Randomize animation durations for depth
                petal.style.animationDuration = (Math.random() * 5 + 5) + 's'; // 5s to 10s
                petal.style.animationDelay = (Math.random() * 5) + 's';
                
                container.appendChild(petal);
            }
        }
        
        // Initialize particles on load
        window.addEventListener('DOMContentLoaded', createSakura);
    </script>
</body>
</html>