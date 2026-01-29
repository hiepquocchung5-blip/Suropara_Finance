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
        $error = "Please enter Agent ID and Key.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suropara Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f172a; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 380px; background: #1e293b; border: 1px solid #334155; border-radius: 1rem; }
        .btn-gold { background: #FFD700; color: #000; font-weight: bold; border: none; transition: 0.3s; }
        .btn-gold:hover { background: #ffcc00; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3); }
        .form-control:focus { border-color: #FFD700; box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25); }
    </style>
</head>
<body>
    <div class="login-card p-5 shadow-lg">
        <div class="text-center mb-4">
            <h3 class="fw-black text-white">SURO BANK</h3>
            <span class="badge bg-warning text-dark">SECURE PORTAL</span>
        </div>
        <?php if($error): ?>
            <div class="alert alert-danger py-2 small text-center rounded-3 border-0 bg-danger bg-opacity-25 text-danger-emphasis fw-bold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="text-secondary small fw-bold ms-1">AGENT ID</label>
                <input type="text" name="username" class="form-control form-control-lg bg-dark text-white border-secondary" required>
            </div>
            <div class="mb-4">
                <label class="text-secondary small fw-bold ms-1">SECURE KEY</label>
                <input type="password" name="password" class="form-control form-control-lg bg-dark text-white border-secondary" required>
            </div>
            <button type="submit" class="btn btn-gold w-100 py-3 rounded-3">START SHIFT</button>
        </form>
        <div class="text-center mt-4 text-secondary small">
            Authorized Personnel Only
        </div>
    </div>
</body>
</html>