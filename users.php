<?php
// =============================================================================
// users.php — User & Role Management (admin only)
// =============================================================================
require_once '_common.php';
require_auth();

// Admin-only guard
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

// =============================================================================
// DATA LAYER
// =============================================================================
if (!USE_MOCK_DATA) {
    // ── ORACLE: users list ────────────────────────────────────────────────────
    $users_list = get_monitor_pdo()->query('
        SELECT u.user_id, u.full_name, u.login, u.email, u.status,
               TO_CHAR(u.created_at,\'DD.MM.YYYY\') AS created_at,
               TO_CHAR(u.last_login,\'DD.MM.YYYY\') AS last_login,
               u.role_id, r.role_name
        FROM ' . tbl('users') . ' u
        JOIN ' . tbl('roles') . ' r ON r.role_id = u.role_id
        ORDER BY u.full_name')->fetchAll();
    $users_list = array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $users_list);

    $roles_list = get_monitor_pdo()->query('
        SELECT role_id, role_name, description
        FROM ' . tbl('roles') . ' ORDER BY role_name')->fetchAll();
    $roles_list = array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $roles_list);
} else {
    $users_list = $MOCK_USERS;
    $roles_list = $MOCK_ROLES;
}

$total_users  = count($users_list);
$active_users = count(array_filter($users_list, fn($u) => $u['status'] === 'active'));

render_header('Users & Roles', 'users');
render_nav('users');
?>
<div class="main">
<?php render_topbar('Users & Roles / İstifadəçilər və Rollar'); ?>
<div class="page-content">

  <!-- KPI STRIP -->
  <div class="cards-row cards-row-4" style="margin-bottom:20px">
    <div class="kpi-card">
      <div class="kpi-label"><i class="fa-solid fa-users"></i> Total Users</div>
      <div class="kpi-value"><?= $total_users ?></div>
      <div class="kpi-sub">Ümumi istifadəçilər</div>
    </div>
    <div class="kpi-card green">
      <div class="kpi-label"><i class="fa-solid fa-user-check"></i> Active</div>
      <div class="kpi-value"><?= $active_users ?></div>
      <div class="kpi-sub">Aktiv hesablar</div>
    </div>
    <div class="kpi-card purple">
      <div class="kpi-label"><i class="fa-solid fa-user-slash"></i> Inactive</div>
      <div class="kpi-value"><?= $total_users - $active_users ?></div>
      <div class="kpi-sub">Deaktiv hesablar</div>
    </div>
    <div class="kpi-card teal">
      <div class="kpi-label"><i class="fa-solid fa-shield-halved"></i> Roles Defined</div>
      <div class="kpi-value"><?= count($roles_list) ?></div>
      <div class="kpi-sub">Müəyyən edilmiş rollar</div>
    </div>
  </div>

  <!-- USERS TABLE -->
  <div class="table-card" style="margin-bottom:24px">
    <div class="table-card-header">
      <h3><i class="fa-solid fa-users-gear" style="color:#1e88e5;margin-right:6px"></i> System Users</h3>
      <button class="btn btn-primary" onclick="openUserForm()">
        <i class="fa-solid fa-plus"></i> Add User
      </button>
    </div>
    <table id="usersTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Full Name / Ad Soyad</th>
          <th>Login</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Last Login</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users_list as $i => $u): ?>
        <tr id="user-row-<?= $u['user_id'] ?>">
          <td style="color:#94a3b8;font-weight:600"><?= $i + 1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="user-avatar" style="width:32px;height:32px;font-size:12px;border-radius:50%;background:<?= roleColor($u['role_name']) ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">
                <?= mb_strtoupper(mb_substr($u['full_name'], 0, 1)) ?>
              </div>
              <strong><?= htmlspecialchars($u['full_name']) ?></strong>
            </div>
          </td>
          <td class="mono"><?= htmlspecialchars($u['login']) ?></td>
          <td style="color:#64748b;font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
          <td><?= roleBadge($u['role_name']) ?></td>
          <td><?= status_badge($u['status']) ?></td>
          <td style="font-size:12px;color:#64748b"><?= $u['last_login'] ?? '—' ?></td>
          <td style="font-size:12px;color:#64748b"><?= $u['created_at'] ?></td>
          <td>
            <button class="btn btn-outline" style="font-size:11px;padding:4px 10px"
              onclick="openUserForm(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
              <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
            <?php if ((int)$u['user_id'] !== (int)(current_user()['id'] ?? 0)): ?>
            <button class="btn btn-outline" style="font-size:11px;padding:4px 10px;color:#e53935;border-color:#e53935;margin-left:4px"
              onclick="toggleUserStatus(<?= $u['user_id'] ?>, '<?= $u['status'] ?>')">
              <i class="fa-solid fa-<?= $u['status'] === 'active' ? 'ban' : 'check' ?>"></i>
              <?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ROLES TABLE -->
  <div class="table-card">
    <div class="table-card-header">
      <h3><i class="fa-solid fa-shield-halved" style="color:#8e24aa;margin-right:6px"></i> Roles & Permissions</h3>
    </div>
    <table>
      <thead>
        <tr>
          <th>Role</th>
          <th>Description</th>
          <th>Users</th>
          <th>Dashboard</th>
          <th>Partners</th>
          <th>Transactions</th>
          <th>Users Mgmt</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $perms = [
            'Administrator' => ['dashboard'=>true,'partners'=>true,'transactions'=>true,'users'=>true],
            'Manager'       => ['dashboard'=>true,'partners'=>true,'transactions'=>true,'users'=>false],
            'Analyst'       => ['dashboard'=>true,'partners'=>false,'transactions'=>true,'users'=>false],
            'Viewer'        => ['dashboard'=>true,'partners'=>false,'transactions'=>false,'users'=>false],
        ];
        foreach ($roles_list as $r):
            $p = $perms[$r['role_name']] ?? [];
            $cnt = count(array_filter($users_list, fn($u) => $u['role_name'] === $r['role_name']));
        ?>
        <tr>
          <td><?= roleBadge($r['role_name']) ?></td>
          <td style="color:#64748b;font-size:13px"><?= htmlspecialchars($r['description']) ?></td>
          <td><span style="font-weight:600"><?= $cnt ?></span> user<?= $cnt !== 1 ? 's' : '' ?></td>
          <?php foreach (['dashboard','partners','transactions','users'] as $perm): ?>
          <td style="text-align:center"><?= ($p[$perm] ?? false) ? '<span style="color:#2e7d32;font-size:16px">✓</span>' : '<span style="color:#e0e0e0;font-size:16px">—</span>' ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div><!-- .page-content -->
</div><!-- .main -->

<!-- ═══════════════════════════════════ ADD / EDIT USER MODAL ══════════════ -->
<div id="userFormModal" class="modal-overlay" style="display:none">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-header">
      <h2 id="userFormTitle"><i class="fa-solid fa-user-plus"></i> Add User</h2>
      <button class="modal-close" onclick="closeUserForm()">×</button>
    </div>
    <div class="modal-body">
      <form id="userForm" onsubmit="saveUser(event)">
        <input type="hidden" id="uf_user_id" value="">

        <div class="form-row">
          <div class="form-group">
            <label>Full Name / Ad Soyad *</label>
            <input type="text" id="uf_full_name" class="form-input" placeholder="Aynur Həsənova" required>
          </div>
          <div class="form-group">
            <label>Login *</label>
            <input type="text" id="uf_login" class="form-input" placeholder="ahesanova" required autocomplete="off">
          </div>
        </div>

        <div class="form-group">
          <label>Email *</label>
          <input type="email" id="uf_email" class="form-input" placeholder="a.hesanova@bank.az" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Role *</label>
            <select id="uf_role_id" class="form-input" required>
              <?php foreach ($roles_list as $r): ?>
              <option value="<?= $r['role_id'] ?>" data-name="<?= htmlspecialchars($r['role_name']) ?>">
                <?= htmlspecialchars($r['role_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select id="uf_status" class="form-input">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>

        <div id="passwordSection">
          <div class="form-row">
            <div class="form-group">
              <label id="pwLabel">Password *</label>
              <input type="password" id="uf_password" class="form-input" placeholder="Min. 8 characters" autocomplete="new-password">
            </div>
            <div class="form-group">
              <label>Confirm Password</label>
              <input type="password" id="uf_password2" class="form-input" placeholder="Repeat password" autocomplete="new-password">
            </div>
          </div>
          <div id="changePwToggle" style="display:none;margin-bottom:12px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#64748b">
              <input type="checkbox" id="uf_change_pw" onchange="togglePasswordFields()">
              Change password
            </label>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn btn-outline" onclick="closeUserForm()">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Mock users store ──────────────────────────────────────────────────────────
let USERS = <?= json_encode(array_values($users_list)) ?>;
const ROLES = <?= json_encode(array_values($roles_list)) ?>;
let _nextUid = <?= max(array_column($users_list, 'user_id')) + 1 ?>;
let _editingUid = null;

function roleColor(name) {
    const m = {Administrator:'#1e88e5',Manager:'#f57c00',Analyst:'#8e24aa',Viewer:'#607d8b'};
    return m[name] || '#607d8b';
}

function roleBadgeJs(name) {
    const c = roleColor(name);
    return `<span style="background:${c}18;color:${c};border:1px solid ${c}40;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600">${name}</span>`;
}

function openUserForm(user = null) {
    _editingUid = user ? user.user_id : null;
    document.getElementById('userFormTitle').innerHTML = user
        ? '<i class="fa-solid fa-pen-to-square"></i> Edit User'
        : '<i class="fa-solid fa-user-plus"></i> Add User';

    document.getElementById('uf_user_id').value   = user?.user_id  ?? '';
    document.getElementById('uf_full_name').value  = user?.full_name ?? '';
    document.getElementById('uf_login').value      = user?.login    ?? '';
    document.getElementById('uf_email').value      = user?.email    ?? '';
    document.getElementById('uf_status').value     = user?.status   ?? 'active';

    // role
    const sel = document.getElementById('uf_role_id');
    if (user) {
        [...sel.options].forEach(o => { o.selected = parseInt(o.value) === parseInt(user.role_id); });
    } else {
        sel.selectedIndex = 0;
    }

    // password section
    const pwInput  = document.getElementById('uf_password');
    const changeTog = document.getElementById('changePwToggle');
    const pwLabel  = document.getElementById('pwLabel');
    if (user) {
        changeTog.style.display = 'block';
        document.getElementById('uf_change_pw').checked = false;
        pwInput.required = false;
        pwInput.closest('.form-row').style.display = 'none';
        pwLabel.textContent = 'New Password';
    } else {
        changeTog.style.display = 'none';
        pwInput.required = true;
        pwInput.closest('.form-row').style.display = '';
        pwLabel.textContent = 'Password *';
    }
    pwInput.value = '';
    document.getElementById('uf_password2').value = '';

    document.getElementById('userFormModal').style.display = 'flex';
}

function togglePasswordFields() {
    const show = document.getElementById('uf_change_pw').checked;
    const row  = document.getElementById('uf_password').closest('.form-row');
    row.style.display = show ? '' : 'none';
    document.getElementById('uf_password').required = show;
}

function closeUserForm() {
    document.getElementById('userFormModal').style.display = 'none';
}

function saveUser(e) {
    e.preventDefault();
    const pw  = document.getElementById('uf_password').value;
    const pw2 = document.getElementById('uf_password2').value;
    const changePw = document.getElementById('uf_change_pw').checked;

    if (!_editingUid || changePw) {
        if (pw !== pw2) { showToast('Passwords do not match', false); return; }
        if (pw.length > 0 && pw.length < 8) { showToast('Password must be at least 8 characters', false); return; }
    }

    const roleId   = parseInt(document.getElementById('uf_role_id').value);
    const roleObj  = ROLES.find(r => r.role_id === roleId);
    const roleName = roleObj?.role_name ?? '';

    if (_editingUid) {
        const idx = USERS.findIndex(u => u.user_id === _editingUid);
        if (idx > -1) {
            USERS[idx].full_name  = document.getElementById('uf_full_name').value.trim();
            USERS[idx].login      = document.getElementById('uf_login').value.trim();
            USERS[idx].email      = document.getElementById('uf_email').value.trim();
            USERS[idx].role_id    = roleId;
            USERS[idx].role_name  = roleName;
            USERS[idx].status     = document.getElementById('uf_status').value;
        }
        showToast('User updated successfully');
    } else {
        USERS.push({
            user_id:    _nextUid++,
            full_name:  document.getElementById('uf_full_name').value.trim(),
            login:      document.getElementById('uf_login').value.trim(),
            email:      document.getElementById('uf_email').value.trim(),
            role_id:    roleId,
            role_name:  roleName,
            status:     document.getElementById('uf_status').value,
            created_at: new Date().toLocaleDateString('az-AZ'),
            last_login: '—',
        });
        showToast('User created successfully');
    }
    closeUserForm();
    rebuildTable();
}

function toggleUserStatus(uid, currentStatus) {
    const u = USERS.find(u => u.user_id === uid);
    if (!u) return;
    u.status = currentStatus === 'active' ? 'inactive' : 'active';
    showToast(`User ${u.status === 'active' ? 'enabled' : 'disabled'}`);
    rebuildTable();
}

function rebuildTable() {
    const tbody = document.querySelector('#usersTable tbody');
    tbody.innerHTML = USERS.map((u, i) => {
        const av = u.full_name.charAt(0).toUpperCase();
        const c  = roleColor(u.role_name);
        const isSelf = false; // can't determine in mock
        return `<tr>
            <td style="color:#94a3b8;font-weight:600">${i+1}</td>
            <td><div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;font-size:12px;border-radius:50%;background:${c};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0">${av}</div>
                <strong>${u.full_name}</strong></div></td>
            <td class="mono">${u.login}</td>
            <td style="color:#64748b;font-size:13px">${u.email}</td>
            <td>${roleBadgeJs(u.role_name)}</td>
            <td>${u.status === 'active'
                ? '<span class="badge badge-active">active</span>'
                : '<span class="badge badge-inactive">inactive</span>'}</td>
            <td style="font-size:12px;color:#64748b">${u.last_login || '—'}</td>
            <td style="font-size:12px;color:#64748b">${u.created_at}</td>
            <td>
                <button class="btn btn-outline" style="font-size:11px;padding:4px 10px" onclick="openUserForm(USERS[${i}])">
                    <i class="fa-solid fa-pen-to-square"></i> Edit
                </button>
                <button class="btn btn-outline" style="font-size:11px;padding:4px 10px;color:${u.status==='active'?'#e53935':'#2e7d32'};border-color:${u.status==='active'?'#e53935':'#2e7d32'};margin-left:4px"
                    onclick="toggleUserStatus(${u.user_id},'${u.status}')">
                    <i class="fa-solid fa-${u.status==='active'?'ban':'check'}"></i>
                    ${u.status==='active'?'Disable':'Enable'}
                </button>
            </td>
        </tr>`;
    }).join('');
}

function showToast(msg, ok = true) {
    let t = document.getElementById('_toast');
    if (!t) { t = document.createElement('div'); t.id = '_toast'; document.body.appendChild(t); }
    t.textContent = msg;
    t.style.cssText = `position:fixed;bottom:24px;right:24px;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:600;z-index:9999;transition:opacity .3s;background:${ok?'#2e7d32':'#c62828'};color:#fff`;
    t.style.opacity = '1';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.opacity = '0'; }, 3000);
}
</script>
</body>
</html>
<?php

// ── Helpers (PHP-side only, not in _common to keep scope clean) ──────────────
function roleBadge(string $name): string {
    $colors = ['Administrator'=>'#1e88e5','Manager'=>'#f57c00','Analyst'=>'#8e24aa','Viewer'=>'#607d8b'];
    $c = $colors[$name] ?? '#607d8b';
    return "<span style=\"background:{$c}18;color:{$c};border:1px solid {$c}40;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600\">{$name}</span>";
}
