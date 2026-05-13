<?php
// =============================================================================
// _common.php — Shared layout, authentication, and data layer
// BaaS Partner Monitoring System (Supabase-only build)
//
// All connection settings come from config.php.
// All data comes from Supabase via _supabase.php. No mock data is shipped.
// =============================================================================

// ----------------------------------------------------------------------------
// LOAD CONFIGURATION
// ----------------------------------------------------------------------------
$cfg = require_once __DIR__ . '/config.php';

// Convenience constants
define('APP_NAME',    $cfg['app']['name']);
define('APP_VERSION', $cfg['app']['version']);

// USE_MOCK_DATA is kept (=true) for backwards compatibility with the page
// files that branch on `if (USE_MOCK_DATA) {…array path…} else {…PDO path…}`.
// In this Supabase-only build the "mock" path IS the live data path — the
// $MOCK_* arrays below get populated from Supabase at request time.
define('USE_MOCK_DATA', true);
define('USE_SUPABASE',  true);

date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Baku');

// ----------------------------------------------------------------------------
// SESSION & AUTH
// ----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_auth(): void {
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function current_user_role(): string {
    return strtolower($_SESSION['user']['role'] ?? 'viewer');
}

function is_admin(): bool {
    return current_user_role() === 'administrator';
}

// ----------------------------------------------------------------------------
// DATA CONTAINERS — populated from Supabase below.
// Page files reference these globals; keep the names intact.
// ----------------------------------------------------------------------------
$MOCK_PARTNERS         = [];
$MOCK_PACKAGES         = [];
$MOCK_PARTNER_SUMMARY  = [];
$MOCK_PARTNER_DOCS     = [];
$MOCK_USERS            = [];
$MOCK_MCC              = [];
$MOCK_COUNTRY          = [];
$MOCK_FEE_PACKAGES     = [];

// Static lookups — these are UI strings, not data, so they live here.
$MOCK_ROLES = [
    ['role_id' => 1, 'role_name' => 'Administrator', 'description' => 'Full access — users, partners, settings'],
    ['role_id' => 2, 'role_name' => 'Manager',       'description' => 'Create/edit partners and packages'],
    ['role_id' => 3, 'role_name' => 'Analyst',       'description' => 'Read-only access to all reports'],
    ['role_id' => 4, 'role_name' => 'Viewer',        'description' => 'Dashboard and transactions view only'],
];

// ----------------------------------------------------------------------------
// LOAD LIVE DATA FROM SUPABASE
// On failure: arrays stay empty, an error is logged, and pages render
// an empty state. There is intentionally no offline fallback.
// ----------------------------------------------------------------------------
require_once __DIR__ . '/_supabase.php';

try {
    $sb_partners      = sb_load_partners();
    $sb_packages      = sb_load_packages();
    $sb_users         = sb_load_users();
    $sb_docs          = sb_load_docs();

    $MOCK_PARTNERS    = $sb_partners ?: [];
    $MOCK_PACKAGES    = $sb_packages ?: [];
    $MOCK_USERS       = $sb_users    ?: [];

    $MOCK_PARTNER_DOCS    = sb_build_partner_docs($MOCK_PARTNERS, $sb_docs);
    $MOCK_PARTNER_SUMMARY = sb_build_partner_summary($MOCK_PARTNERS, $MOCK_PACKAGES);

    // Optional tables — wrap individually so a missing one doesn't kill the page.
    try { $MOCK_MCC          = sb_load_mcc()          ?: []; } catch (RuntimeException $e) { error_log('[BaaS] mcc_data: '          . $e->getMessage()); }
    try { $MOCK_COUNTRY      = sb_load_country()      ?: []; } catch (RuntimeException $e) { error_log('[BaaS] country_data: '      . $e->getMessage()); }
    try { $MOCK_FEE_PACKAGES = sb_load_fee_packages() ?: []; } catch (RuntimeException $e) { error_log('[BaaS] fee_packages: '      . $e->getMessage()); }

} catch (RuntimeException $e) {
    error_log('[BaaS] Supabase load failed: ' . $e->getMessage());
    // Arrays remain empty; pages will render empty states.
}

// ----------------------------------------------------------------------------
// Placeholder for the 30-day txn chart series.
// Real transactions are not yet stored in Supabase — this function returns
// an empty 30-day series so dashboards and the transactions page render
// "no data" without PHP errors. Wire to a real txn_daily_agg table when ready.
// ----------------------------------------------------------------------------
function mock_daily_txn_data(int $partner_id = 0): array {
    $data = [];
    for ($i = 29; $i >= 0; $i--) {
        $ts = strtotime("-{$i} days");
        $data[] = [
            'date'      => date('d.m', $ts),
            'date_full' => date('Y-m-d', $ts),
            'volume'    => 0,
            'count'     => 0,
        ];
    }
    return $data;
}

// ----------------------------------------------------------------------------
// HELPERS
// ----------------------------------------------------------------------------
function fmt_num(float $n, int $dec = 0): string {
    return number_format($n, $dec, '.', ' ');
}

function fmt_rub(float $n): string {
    if ($n >= 1_000_000) return fmt_num($n / 1_000_000, 2) . ' mln ₼';
    if ($n >= 1_000)     return fmt_num($n / 1_000, 1) . ' min ₼';
    return fmt_num($n, 2) . ' ₼';
}

function status_badge(string $status): string {
    $map = [
        'active'    => ['Active',    'badge-active'],
        'inactive'  => ['Inactive',  'badge-inactive'],
        'exhausted' => ['Exhausted', 'badge-exhausted'],
        'closed'    => ['Closed',    'badge-closed'],
        'suspended' => ['Suspended', 'badge-inactive'],
    ];
    [$label, $cls] = $map[$status] ?? [$status, 'badge-inactive'];
    return "<span class=\"badge {$cls}\">{$label}</span>";
}

function usage_bar(float $pct): string {
    $color = $pct >= 90 ? 'bar-danger' : ($pct >= 70 ? 'bar-warning' : 'bar-ok');
    $w     = min(100, $pct);
    return "<div class=\"usage-bar-wrap\">
              <div class=\"usage-bar {$color}\" style=\"width:{$w}%\"></div>
            </div>
            <small class=\"usage-label\">{$pct}%</small>";
}

// ----------------------------------------------------------------------------
// LAYOUT RENDER
// ----------------------------------------------------------------------------
function render_header(string $title = 'Dashboard', string $active = 'dashboard'): void {
    ?><!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
/* ===== RESET & BASE ===== */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;font-size:14px;background:#f0f4f8;color:#1a2332;display:flex;min-height:100vh}

/* ===== SIDEBAR ===== */
.sidebar{width:240px;min-height:100vh;background:#0f1e3c;display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100}
.sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar-brand .brand-name{color:#fff;font-size:16px;font-weight:700;letter-spacing:.5px}
.sidebar-brand .brand-sub{color:#6b8aad;font-size:11px;margin-top:2px}
.sidebar-brand .brand-icon{width:36px;height:36px;background:linear-gradient(135deg,#1e88e5,#0d47a1);border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px}
.sidebar-brand .brand-icon i{color:#fff;font-size:16px}
.sidebar-nav{flex:1;padding:12px 0}
.nav-item{display:flex;align-items:center;gap:12px;padding:11px 20px;color:#8facc8;text-decoration:none;font-size:13px;font-weight:500;transition:all .18s;cursor:pointer}
.nav-item:hover{background:rgba(255,255,255,.06);color:#fff}
.nav-item.active{background:rgba(30,136,229,.18);color:#4db3ff;border-right:3px solid #1e88e5}
.nav-item i{width:18px;text-align:center;font-size:14px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08)}
.user-info{display:flex;align-items:center;gap:10px}
.user-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1e88e5,#0d47a1);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0}
.user-name{color:#c8d8ea;font-size:12px;font-weight:600}
.user-role{color:#6b8aad;font-size:11px}
.logout-btn{display:block;margin-top:10px;padding:7px 12px;background:rgba(255,255,255,.06);color:#8facc8;text-decoration:none;border-radius:6px;font-size:12px;text-align:center;transition:all .18s}
.logout-btn:hover{background:rgba(229,57,53,.2);color:#ef9a9a}

/* ===== MAIN CONTENT ===== */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:0 28px;height:60px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-title{font-size:18px;font-weight:700;color:#1a2332}
.topbar-meta{display:flex;align-items:center;gap:14px}
.sync-badge{background:#e8f5e9;color:#2e7d32;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
.sync-badge.warn{background:#fff3e0;color:#e65100}
.page-content{padding:24px 28px;flex:1}

/* ===== CARDS ===== */
.cards-row{display:grid;gap:16px;margin-bottom:22px}
.cards-row-6{grid-template-columns:repeat(6,1fr)}
.cards-row-3{grid-template-columns:repeat(3,1fr)}
.cards-row-2{grid-template-columns:repeat(2,1fr)}
.cards-row-4{grid-template-columns:repeat(4,1fr)}
@media(max-width:1400px){.cards-row-6{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.cards-row-6,.cards-row-3{grid-template-columns:repeat(2,1fr)}.cards-row-2{grid-template-columns:1fr}}

.kpi-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);border-left:4px solid #1e88e5;display:flex;flex-direction:column;gap:6px}
.kpi-card.green{border-color:#43a047}
.kpi-card.orange{border-color:#f57c00}
.kpi-card.red{border-color:#e53935}
.kpi-card.purple{border-color:#8e24aa}
.kpi-card.teal{border-color:#00897b}
.kpi-label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.5px}
.kpi-value{font-size:26px;font-weight:700;color:#1a2332;line-height:1}
.kpi-sub{font-size:11px;color:#94a3b8}

.chart-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.07)}
.chart-card-title{font-size:13px;font-weight:700;color:#334155;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.chart-card-title i{color:#1e88e5}

/* ===== TABLES ===== */
.table-card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);overflow:hidden;margin-bottom:22px}
.table-card-header{padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.table-card-header h3{font-size:14px;font-weight:700;color:#334155}
table{width:100%;border-collapse:collapse}
thead th{padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0}
tbody tr{border-bottom:1px solid #f1f5f9;transition:background .12s}
tbody tr:hover{background:#f8fafc}
tbody tr:last-child{border-bottom:none}
td{padding:12px 16px;font-size:13px;color:#334155;vertical-align:middle}
td.mono{font-family:monospace;font-size:12px}
td.right{text-align:right}
td.center{text-align:center}

/* ===== BADGES ===== */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;white-space:nowrap}
.badge-active{background:#e8f5e9;color:#2e7d32}
.badge-inactive{background:#fce4ec;color:#c62828}
.badge-exhausted{background:#fff3e0;color:#e65100}
.badge-closed{background:#f3e5f5;color:#6a1b9a}

/* ===== USAGE BAR ===== */
.usage-bar-wrap{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;width:100%;min-width:80px}
.usage-bar{height:100%;border-radius:4px;transition:width .4s}
.bar-ok{background:linear-gradient(90deg,#43a047,#66bb6a)}
.bar-warning{background:linear-gradient(90deg,#f57c00,#ffb74d)}
.bar-danger{background:linear-gradient(90deg,#e53935,#ef5350)}
.usage-label{color:#64748b;font-size:11px;margin-top:3px;display:block}

/* ===== FILTER BAR ===== */
.filter-bar{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:20px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:5px}
.filter-group label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.filter-group select,.filter-group input{border:1px solid #e2e8f0;border-radius:7px;padding:7px 11px;font-size:13px;color:#334155;background:#fff;outline:none;font-family:inherit;min-width:150px}
.filter-group select:focus,.filter-group input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12)}
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .18s;text-decoration:none}
.btn-primary{background:#1e88e5;color:#fff}
.btn-primary:hover{background:#1565c0}
.btn-outline{background:#fff;color:#334155;border:1px solid #e2e8f0}
.btn-outline:hover{background:#f8fafc}

/* ===== MODAL ===== */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:14px;width:780px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-box{background:#fff;border-radius:14px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-header{padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between}
.modal-header h2{font-size:16px;font-weight:700}
.modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;padding:4px}
.modal-body{padding:24px}
.modal-actions{display:flex;justify-content:flex-end;gap:10px}

/* ===== FORM ===== */
.form-row{display:flex;gap:14px;margin-bottom:14px}
.form-group{display:flex;flex-direction:column;gap:5px;flex:1}
.form-group label{font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.4px}
.form-input{border:1.5px solid #e2e8f0;border-radius:8px;padding:9px 12px;font-size:13px;color:#1a2332;font-family:inherit;outline:none;transition:border-color .18s;background:#fff;width:100%}
.form-input:focus{border-color:#1e88e5;box-shadow:0 0 0 3px rgba(30,136,229,.12)}

/* ===== SECTION LABEL ===== */
.section-label{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px;margin-top:20px}
.section-label:first-child{margin-top:0}
.form-section-label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid #e2e8f0}

/* ===== ALERT / NOTICE ===== */
.notice{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
.notice.info{background:#e3f2fd;color:#0d47a1}
.notice.warn{background:#fff3e0;color:#bf360c}
.notice i{margin-top:1px}

/* ===== DB MODE BADGE ===== */
.db-mode-badge{background:#e0f2f1;color:#004d40;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600}
</style>
<?php
}

function render_nav(string $active): void {
    $nav = [
        ['id' => 'dashboard',    'href' => 'dashboard.php',    'icon' => 'fa-gauge-high',  'label' => 'Dashboard'],
        ['id' => 'partners',     'href' => 'partners.php',     'icon' => 'fa-handshake',   'label' => 'Partners & Packages'],
        ['id' => 'transactions', 'href' => 'transactions.php', 'icon' => 'fa-chart-line',  'label' => 'Transactions'],
    ];
    if (is_admin()) {
        $nav[] = ['id' => 'users', 'href' => 'users.php', 'icon' => 'fa-users-gear', 'label' => 'Users & Roles'];
    }
    $u        = current_user();
    $initials = implode('', array_map(
        fn($w) => mb_strtoupper(mb_substr($w, 0, 1)),
        array_slice(explode(' ', $u['name'] ?? 'Admin'), 0, 2)
    ));
    ?>
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-building-columns"></i></div>
    <div class="brand-name"><?= APP_NAME ?></div>
    <div class="brand-sub">BaaS Partner Monitoring</div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav as $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-item <?= $active === $item['id'] ? 'active' : '' ?>">
      <i class="fa-solid <?= $item['icon'] ?>"></i>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= $initials ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($u['name'] ?? 'Admin') ?></div>
        <div class="user-role"><?= htmlspecialchars($u['role'] ?? 'Administrator') ?></div>
      </div>
    </div>
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
  </div>
</div>
<?php
}

function render_topbar(string $title): void {
    ?>
<div class="topbar">
  <div class="topbar-title"><?= htmlspecialchars($title) ?></div>
  <div class="topbar-meta">
    <span class="db-mode-badge">
      <i class="fa-solid fa-database"></i> Supabase
    </span>
    <span style="color:#94a3b8;font-size:12px"><?= date('d.m.Y H:i') ?></span>
  </div>
</div>
<?php }

function render_footer(): void { ?>
</div><!-- .main -->
</body>
</html>
<?php }
