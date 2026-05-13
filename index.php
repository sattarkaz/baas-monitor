<?php
// =============================================================================
// index.php — Login page (Supabase-only)
// BaaS Partner Monitoring System
//
// Authentication: sys_users table on Supabase, bcrypt password verification.
// No offline / demo-credential fallback. If Supabase is unreachable, login
// fails with a clear error.
// =============================================================================
require_once '_common.php';

// Already logged in → straight to dashboard
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $sb_user = sb_verify_login($login, $password);

        if ($sb_user !== null) {
            $role = ucfirst($sb_user['role'] ?? 'viewer');
            $_SESSION['user'] = [
                'login' => $sb_user['login'] ?? $login,
                'name'  => $sb_user['name']  ?? $login,
                'role'  => $role,
                'email' => $sb_user['email'] ?? '',
                'id'    => $sb_user['id']    ?? null,
            ];
            session_regenerate_id(true);
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Yanlış giriş adı və ya şifrə. / Invalid login or password.';

    } catch (RuntimeException $e) {
        error_log('[BaaS] Login error: ' . $e->getMessage());
        $error = 'Autentifikasiya xidməti əlçatmazdır. Sonra yenidən cəhd edin. / '
               . 'Authentication service unavailable, please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Giriş — BaaS Monitor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0a1628 0%,#0f1e3c 50%,#162447 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.login-wrapper{width:100%;max-width:440px}
.login-brand{text-align:center;margin-bottom:32px}
.brand-logo{width:64px;height:64px;background:linear-gradient(135deg,#1e88e5,#0d47a1);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.brand-logo i{color:#fff;font-size:28px}
.brand-name{color:#fff;font-size:22px;font-weight:700;letter-spacing:.3px}
.brand-sub{color:#6b8aad;font-size:13px;margin-top:4px}

.login-card{background:#fff;border-radius:16px;padding:36px;box-shadow:0 24px 64px rgba(0,0,0,.4)}
.login-card h2{font-size:20px;font-weight:700;color:#1a2332;margin-bottom:6px}
.login-card p.sub{font-size:13px;color:#64748b;margin-bottom:28px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.form-group .input-wrap{position:relative}
.form-group .input-wrap i{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px}
.form-group input{width:100%;border:1.5px solid #e2e8f0;border-radius:8px;padding:11px 14px 11px 38px;font-size:14px;color:#1a2332;font-family:inherit;outline:none;transition:border-color .18s}
.form-group input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12)}
.error-msg{background:#fce4ec;color:#c62828;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.btn-login{width:100%;background:linear-gradient(135deg,#1e88e5,#1565c0);color:#fff;border:none;border-radius:8px;padding:13px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .18s;margin-top:4px}
.btn-login:hover{opacity:.9}
</style>
</head>
<body>
<div class="login-wrapper">
  <div class="login-brand">
    <div class="brand-logo"><i class="fa-solid fa-building-columns"></i></div>
    <div class="brand-name">BaaS Monitor</div>
    <div class="brand-sub">BaaS Partner Monitoring System · v<?= APP_VERSION ?></div>
  </div>

  <div class="login-card">
    <h2>Giriş / Sign In</h2>
    <p class="sub">Monitorinq platformasına daxil olmaq üçün etimadnamələrinizi daxil edin.</p>

    <?php if ($error): ?>
    <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>İstifadəçi adı / Login</label>
        <div class="input-wrap">
          <i class="fa-solid fa-user"></i>
          <input type="text" name="login" required autocomplete="username"
                 value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Şifrə / Password</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" required autocomplete="current-password">
        </div>
      </div>
      <button class="btn-login" type="submit"><i class="fa-solid fa-right-to-bracket"></i>&nbsp; Daxil ol</button>
    </form>
  </div>
</div>
</body>
</html>
