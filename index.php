<?php
// =============================================================================
// index.php — Login page
// =============================================================================
require_once '_common.php';

// Already logged in → redirect
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Demo credentials
$DEMO_USERS = [
    'admin'   => ['pass' => 'admin123',   'name' => 'Алексей Волков',   'role' => 'Administrator'],
    'analyst' => ['pass' => 'analyst123', 'name' => 'Мария Смирнова',   'role' => 'Analyst'],
    'manager' => ['pass' => 'manager123', 'name' => 'Дмитрий Козлов',   'role' => 'Partner Manager'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    /*
     * Oracle query (production):
     * SELECT u.user_id, u.full_name, u.email, r.role_name
     * FROM users u
     * JOIN roles r ON r.role_id = u.role_id
     * WHERE u.email = :login AND u.status = 'active'
     * Then verify password_hash with password_verify()
     */

    if (isset($DEMO_USERS[$login]) && $DEMO_USERS[$login]['pass'] === $password) {
        $_SESSION['user'] = [
            'login' => $login,
            'name'  => $DEMO_USERS[$login]['name'],
            'role'  => $DEMO_USERS[$login]['role'],
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — BaaS Monitor</title>
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

.demo-box{margin-top:24px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px}
.demo-box h4{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
.demo-cred{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #e2e8f0;font-size:12px}
.demo-cred:last-child{border-bottom:none;padding-bottom:0}
.demo-cred span.login-val{font-family:monospace;background:#e2e8f0;padding:2px 7px;border-radius:4px;color:#334155;font-size:11px}
.demo-cred span.pass-val{color:#94a3b8;font-size:11px}
.demo-cred .role-tag{color:#1e88e5;font-weight:600}

.db-note{margin-top:16px;background:#e3f2fd;border-radius:8px;padding:12px 14px;font-size:11px;color:#0d47a1;line-height:1.6}
.db-note strong{display:block;margin-bottom:3px}
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
    <h2>Sign In / Войти</h2>
    <p class="sub">Enter your credentials to access the monitoring platform.</p>

    <?php if ($error): ?>
    <div class="error-msg"><i class="fa-solid fa-triangle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label>Username / Логин</label>
        <div class="input-wrap">
          <i class="fa-solid fa-user"></i>
          <input type="text" name="login" placeholder="admin" required autocomplete="username"
                 value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Password / Пароль</label>
        <div class="input-wrap">
          <i class="fa-solid fa-lock"></i>
          <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>
      </div>
      <button class="btn-login" type="submit"><i class="fa-solid fa-right-to-bracket"></i> &nbsp; Sign In</button>
    </form>

    <div class="demo-box">
      <h4><i class="fa-solid fa-flask"></i> Demo Credentials</h4>
      <div class="demo-cred">
        <span><span class="login-val">admin</span> / <span class="pass-val">admin123</span></span>
        <span class="role-tag">Administrator</span>
      </div>
      <div class="demo-cred">
        <span><span class="login-val">analyst</span> / <span class="pass-val">analyst123</span></span>
        <span class="role-tag">Analyst</span>
      </div>
      <div class="demo-cred">
        <span><span class="login-val">manager</span> / <span class="pass-val">manager123</span></span>
        <span class="role-tag">Partner Manager</span>
      </div>
    </div>

    <div class="db-note">
      <strong><i class="fa-solid fa-database"></i> Oracle DB Mode</strong>
      Running with mock data. Connect to Oracle 19c+ by setting <code>USE_MOCK_DATA = false</code>
      and configuring <code>DB_DSN / DB_USER / DB_PASS</code> in <code>_common.php</code>.
      Schema: <code>schema_oracle.sql</code>.
    </div>
  </div>
</div>
</body>
</html>
