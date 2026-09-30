<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

// ── Super admin only ──
if (($_SESSION['role'] ?? '') !== 'super_admin') {
  http_response_code(403);
  exit('Access denied. Super admin only.');
}

// ── Make sure the permissions table + every permission column exists ──
require_once 'permissions.php';
ensure_permission_schema($inventory);

// ── The users.role ENUM has no 'super_admin' yet; add it once ──
$col = $inventory->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch_assoc();
if ($col && strpos($col['Type'], 'super_admin') === false) {
  $inventory->query("ALTER TABLE users MODIFY role ENUM('super_admin','admin','employee','customer') NOT NULL DEFAULT 'employee'");
}

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];
$me = (int)$_SESSION['user_id'];

function json_out($arr, $code = 200)
{
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($arr);
  exit;
}

// ── AJAX: toggle a single permission ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) json_out(['ok' => false, 'error' => 'Bad token'], 403);

  $columns = permission_columns();
  $perm = $_POST['perm'] ?? '';
  $uid = (int)($_POST['user_id'] ?? 0);
  $value = ($_POST['value'] ?? '0') === '1' ? 1 : 0;

  if (!isset($columns[$perm]) || $uid <= 0) json_out(['ok' => false, 'error' => 'Invalid request'], 400);

  // Only admins/employees can be toggled (super admins always have everything)
  $chk = $inventory->prepare("SELECT role FROM users WHERE id = ?");
  $chk->bind_param("i", $uid);
  $chk->execute();
  $target = $chk->get_result()->fetch_assoc();
  $chk->close();
  if (!$target || $target['role'] === 'super_admin') json_out(['ok' => false, 'error' => 'Not allowed'], 400);

  $col = $columns[$perm]; // whitelisted above
  $stmt = $inventory->prepare("INSERT INTO user_permissions (user_id, $col) VALUES (?, ?) ON DUPLICATE KEY UPDATE $col = VALUES($col)");
  $stmt->bind_param("ii", $uid, $value);
  $ok = $stmt->execute();
  $stmt->close();
  json_out(['ok' => $ok]);
}

// ── Form actions: add user / change role / reset password / delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
  if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
    $_SESSION['error_message'] = "Invalid session token. Please try again.";
    header("Location: manage_users.php");
    exit;
  }

  $act = $_POST['form_action'];

  if ($act === 'change_role') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $role = in_array($_POST['role'] ?? '', ['admin', 'employee'], true) ? $_POST['role'] : null;
    if ($role && $uid !== $me) {
      $stmt = $inventory->prepare("UPDATE users SET role = ? WHERE id = ? AND role <> 'super_admin'");
      $stmt->bind_param("si", $role, $uid);
      $stmt->execute();
      $stmt->close();
      $_SESSION['success_message'] = "Role updated.";
    }
  }

  if ($act === 'reset_password') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['new_password'] ?? '';
    if (strlen($password) < 6) {
      $_SESSION['warning_message'] = "Password must be at least 6 characters.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $inventory->prepare("UPDATE users SET password = ? WHERE id = ? AND role <> 'super_admin'");
      $stmt->bind_param("si", $hash, $uid);
      $stmt->execute();
      $stmt->close();
      $_SESSION['success_message'] = "Password reset.";
    }
  }

  if ($act === 'delete_user') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid !== $me) {
      $stmt = $inventory->prepare("DELETE FROM users WHERE id = ? AND role <> 'super_admin'");
      $stmt->bind_param("i", $uid);
      if ($stmt->execute() && $stmt->affected_rows > 0) {
        $p = $inventory->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $p->bind_param("i", $uid);
        $p->execute();
        $p->close();
        $_SESSION['success_message'] = "Account deleted.";
      } else {
        $_SESSION['error_message'] = "Could not delete that account (it may be referenced by other records).";
      }
      $stmt->close();
    }
  }

  header("Location: manage_users.php");
  exit;
}

// ── Flash messages ──
function flash_html($cls, $icon, $text, $timeout)
{
  return "<div id='flash-message' class='alert alert-dismissible $cls' role='alert' data-timeout='$timeout'>"
    . "<i class='fas $icon'></i><span>" . htmlspecialchars($text) . "</span>"
    . "<button type='button' class='alert-close' aria-label='Dismiss'><i class='fas fa-times'></i></button></div>";
}

$message = "";
if (isset($_SESSION['success_message'])) {
  $message = flash_html('alert-success', 'fa-check-circle', $_SESSION['success_message'], 4000);
  unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
  $message = flash_html('alert-danger', 'fa-exclamation-circle', $_SESSION['error_message'], 0);
  unset($_SESSION['error_message']);
} elseif (isset($_SESSION['warning_message'])) {
  $message = flash_html('alert-warning', 'fa-exclamation-triangle', $_SESSION['warning_message'], 0);
  unset($_SESSION['warning_message']);
}

// ── Load users + permissions ──
$search = trim($_GET['q'] ?? '');
$like = '%' . $search . '%';
$stmt = $inventory->prepare("
  SELECT u.id, u.username, u.role, p.*
  FROM users u
  LEFT JOIN user_permissions p ON p.user_id = u.id
  WHERE u.username LIKE ? AND u.role IN ('super_admin', 'admin', 'employee')
  ORDER BY FIELD(u.role, 'super_admin', 'admin', 'employee'), u.username
");
$stmt->bind_param("s", $like);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$counts = ['super_admin' => 0, 'admin' => 0, 'employee' => 0];
foreach ($users as $u) if (isset($counts[$u['role']])) $counts[$u['role']]++;

$perm_cols = permission_columns();
$perm_total = count($perm_cols);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users - Active Media Printing</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <!-- Reuse the shared layout/table styles, then the page-specific ones -->
  <link rel="stylesheet" href="../assets/css/pages/papers.css">
  <link rel="stylesheet" href="../assets/css/pages/manage_users.css">
</head>

<body>
  <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

  <div class="sidebar-con">
    <div class="sidebar">
      <div class="brand">
        <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
      </div>
      <ul class="nav-menu">
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        <li>
          <a href="papers.php"><i class="fas fa-boxes"></i> <span>Products</span></a>
        </li>
        <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
        <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
        <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
        <li class="active"><a href="manage_users.php"><i class="fas fa-user-shield"></i> <span>Manage Users</span></a></li>
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>

  <div class="main-content">
    <header class="header">
      <div>
        <h1>Manage Users</h1>
        <p class="header-date">
          <i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
        </p>
      </div>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
          <small><?= htmlspecialchars($_SESSION['role']) ?></small>
        </div>
      </div>
    </header>

    <?= $message ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Super Admins</p>
            <h3 data-count="<?= $counts['super_admin'] ?>"><?= $counts['super_admin'] ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-crown"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Admins</p>
            <h3 data-count="<?= $counts['admin'] ?>"><?= $counts['admin'] ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-user-shield"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Employees</p>
            <h3 data-count="<?= $counts['employee'] ?>"><?= $counts['employee'] ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-users"></i></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Total Accounts</p>
            <h3 data-count="<?= count($users) ?>"><?= count($users) ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-id-badge"></i></div>
        </div>
      </div>
    </div>

    <!-- Add user: handled on the staff signup page -->
    <div class="form-card create-card">
      <h3><i class="fas fa-user-plus"></i> Create Staff Account</h3>
      <a href="../accounts/signup.php" class="btn"><i class="fas fa-user-plus"></i> Create Account</a>
    </div>

    <!-- Users + permissions -->
    <div class="table-card">
      <h3>
        <span><i class="fas fa-user-lock"></i> Accounts &amp; Permissions</span>
      </h3>

      <div class="users-toolbar">
        <div class="filter-chips" id="roleFilters" role="group" aria-label="Filter by role">
          <button type="button" class="chip active" data-filter="all">All <span class="chip-count"><?= count($users) ?></span></button>
          <button type="button" class="chip" data-filter="super_admin">Super Admin <span class="chip-count"><?= $counts['super_admin'] ?></span></button>
          <button type="button" class="chip" data-filter="admin">Admin <span class="chip-count"><?= $counts['admin'] ?></span></button>
          <button type="button" class="chip" data-filter="employee">Employee <span class="chip-count"><?= $counts['employee'] ?></span></button>
        </div>

        <form method="GET" class="search-form" id="searchForm" role="search">
          <div class="search-box<?= $search !== '' ? ' has-value' : '' ?>" id="searchBox">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="q" id="searchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search username…" autocomplete="off" aria-label="Search username">
            <kbd>/</kbd>
            <button type="button" class="search-clear" id="searchClear" aria-label="Clear search"><i class="fas fa-times"></i></button>
          </div>
        </form>
      </div>

      <div class="table-scroll">
        <table class="users-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Permissions</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr class="empty-row<?= $users ? ' is-hidden' : '' ?>" id="emptyRow">
              <td colspan="4">
                <i class="fas fa-user-slash empty-icon"></i>
                No accounts found.
              </td>
            </tr>
            <?php foreach ($users as $idx => $u):
              $isSuper = $u['role'] === 'super_admin';
              $isSelf = (int)$u['id'] === $me;
              $uid = (int)$u['id'];

              $perm_on = 0;
              foreach ($perm_cols as $pcol) if (!empty($u[$pcol])) $perm_on++;
              if ($isSuper) $perm_on = $perm_total;
              $perm_pct = $perm_total ? round($perm_on / $perm_total * 100) : 0;

              $hue = crc32($u['username']) % 360;
              $initials = mb_strtoupper(mb_substr($u['username'], 0, 2));
            ?>
              <tr class="user-row" data-id="<?= $uid ?>" data-role="<?= htmlspecialchars($u['role']) ?>" data-username="<?= htmlspecialchars(mb_strtolower($u['username'])) ?>" style="--i:<?= min($idx, 12) ?>">
                <td>
                  <div class="user-cell">
                    <span class="avatar" style="--h:<?= $hue ?>"><?= htmlspecialchars($initials) ?></span>
                    <div>
                      <strong><?= htmlspecialchars($u['username']) ?></strong><?= $isSelf ? '<span class="you-tag">you</span>' : '' ?>
                    </div>
                  </div>
                </td>
                <td>
                  <?php if ($isSuper || $isSelf): ?>
                    <span class="role-badge role-<?= htmlspecialchars($u['role']) ?>"><?= ucwords(str_replace('_', ' ', $u['role'])) ?></span>
                  <?php else: ?>
                    <form method="POST" class="role-form">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="form_action" value="change_role">
                      <input type="hidden" name="user_id" value="<?= $uid ?>">
                      <select name="role" class="role-badge role-select role-<?= htmlspecialchars($u['role']) ?>" aria-label="Role for <?= htmlspecialchars($u['username']) ?>">
                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="employee" <?= $u['role'] === 'employee' ? 'selected' : '' ?>>Employee</option>
                      </select>
                    </form>
                  <?php endif; ?>
                </td>

                <td>
                  <button type="button" class="btn-sm perm-btn" data-id="<?= $uid ?>" data-total="<?= $perm_total ?>" aria-expanded="false" aria-controls="perm-row-<?= $uid ?>">
                    <i class="fas fa-sliders-h"></i>
                    <span><span class="perm-count" id="perm-count-<?= $uid ?>"><?= $perm_on ?></span> / <?= $perm_total ?> on</span>
                    <span class="perm-meter" aria-hidden="true"><span id="perm-meter-<?= $uid ?>" style="--pct:<?= $perm_pct ?>%"></span></span>
                    <i class="fas fa-chevron-down perm-chevron"></i>
                  </button>
                </td>

                <td>
                  <?php if (!$isSuper && !$isSelf): ?>
                    <div class="row-actions">
                      <button type="button" class="btn-sm js-reset" data-id="<?= $uid ?>" data-name="<?= htmlspecialchars($u['username']) ?>" title="Reset password" aria-label="Reset password for <?= htmlspecialchars($u['username']) ?>"><i class="fas fa-key"></i></button>
                      <button type="button" class="btn-sm danger js-delete" data-id="<?= $uid ?>" data-name="<?= htmlspecialchars($u['username']) ?>" title="Delete account" aria-label="Delete <?= htmlspecialchars($u['username']) ?>"><i class="fas fa-trash"></i></button>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
              <tr class="perm-row" id="perm-row-<?= $uid ?>">
                <td colspan="4">
                  <div class="perm-panel">
                    <div class="perm-panel-inner">
                      <div class="perm-groups">
                        <?php foreach (permission_groups() as $group => $items): ?>
                          <div class="perm-group">
                            <div class="perm-group-head">
                              <div class="perm-group-title">
                                <?= htmlspecialchars($group) ?>
                                <span class="perm-group-count"></span>
                              </div>
                              <?php if (!$isSuper): ?>
                                <div class="perm-group-actions">
                                  <button type="button" class="link-btn" data-bulk="on">All</button>
                                  <button type="button" class="link-btn" data-bulk="off">None</button>
                                </div>
                              <?php endif; ?>
                            </div>
                            <?php foreach ($items as $key => [$label, $col]): ?>
                              <label class="perm-item<?= $isSuper ? ' is-disabled' : '' ?>" title="<?= $isSuper ? 'Super admins always have full access' : 'Toggle: ' . htmlspecialchars($label) ?>">
                                <span class="perm-label"><?= htmlspecialchars($label) ?></span>
                                <span class="switch">
                                  <input type="checkbox" role="switch"
                                    data-user="<?= $uid ?>"
                                    data-perm="<?= $key ?>"
                                    <?= ($isSuper || !empty($u[$col])) ? 'checked' : '' ?>
                                    <?= $isSuper ? 'disabled' : '' ?>>
                                  <span class="slider">
                                    <span class="thumb">
                                      <svg class="check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" pathLength="1"/></svg>
                                      <span class="spin"></span>
                                    </span>
                                  </span>
                                </span>
                              </label>
                            <?php endforeach; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Reset password modal -->
  <div class="mu-modal" id="resetModal" role="dialog" aria-modal="true" aria-labelledby="resetTitle" aria-hidden="true">
    <div class="mu-modal-card">
      <button type="button" class="mu-modal-close" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      <div class="mu-modal-icon"><i class="fas fa-key"></i></div>
      <h3 id="resetTitle">Reset password</h3>
      <p class="mu-modal-sub">Choose a new password for <strong id="resetName"></strong>.</p>
      <form method="POST" id="resetForm" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="form_action" value="reset_password">
        <input type="hidden" name="user_id" id="resetUserId">
        <div class="pw-field">
          <input type="password" name="new_password" id="resetPassword" placeholder="New password" minlength="6" autocomplete="new-password" required>
          <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password"><i class="fas fa-eye"></i></button>
        </div>
        <div class="pw-meter" id="pwMeter" data-level=""><span></span></div>
        <div class="pw-hint" id="pwHint">Use at least 6 characters.</div>
        <div class="mu-modal-actions">
          <button type="button" class="mu-btn mu-btn-ghost" data-close>Cancel</button>
          <button type="submit" class="mu-btn mu-btn-primary" id="resetSubmit" disabled><i class="fas fa-check"></i> Reset password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete account modal -->
  <div class="mu-modal" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle" aria-hidden="true">
    <div class="mu-modal-card">
      <button type="button" class="mu-modal-close" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      <div class="mu-modal-icon danger"><i class="fas fa-trash"></i></div>
      <h3 id="deleteTitle">Delete account?</h3>
      <p class="mu-modal-sub">This will permanently delete <strong id="deleteName"></strong> and their permissions. This can't be undone.</p>
      <form method="POST" id="deleteForm">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="form_action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserId">
        <div class="mu-modal-actions">
          <button type="button" class="mu-btn mu-btn-ghost" id="deleteCancel" data-close>Cancel</button>
          <button type="submit" class="mu-btn mu-btn-danger"><i class="fas fa-trash"></i> Delete</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast-stack" id="toastStack" aria-live="polite"></div>

  <script>
    (function () {
      'use strict';

      var CSRF = <?= json_encode($csrf) ?>;
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function $(sel, ctx) { return (ctx || document).querySelector(sel); }
      function $$(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
      function restart(el, cls) { el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); }
      function haptic() { if (navigator.vibrate) { try { navigator.vibrate(8); } catch (e) {} } }

      /* ── Toasts ── */
      var stack = $('#toastStack');
      var TOAST_ICONS = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };

      function showToast(msg, type) {
        type = type || 'error';
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        t.innerHTML = '<i class="fas ' + TOAST_ICONS[type] + '"></i><span></span><div class="toast-bar"></div>';
        t.querySelector('span').textContent = msg;
        var life = type === 'error' ? 4200 : 2800;
        t.querySelector('.toast-bar').style.animationDuration = life + 'ms';
        stack.appendChild(t);
        while (stack.children.length > 4) stack.removeChild(stack.firstChild);
        requestAnimationFrame(function () { requestAnimationFrame(function () { t.classList.add('show'); }); });

        var timer = setTimeout(hide, life);
        function hide() {
          clearTimeout(timer);
          t.classList.remove('show');
          t.classList.add('hide');
          setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 400);
        }
        t.addEventListener('click', hide);
      }

      /* ── Permission counts / meters ── */
      function updateGroupCounts(row) {
        $$('.perm-group', row).forEach(function (g) {
          var boxes = $$('input[data-perm]', g);
          var on = boxes.filter(function (b) { return b.checked; }).length;
          var badge = $('.perm-group-count', g);
          if (badge) badge.textContent = on + '/' + boxes.length;
        });
      }

      function updatePermCount(userId) {
        var row = document.getElementById('perm-row-' + userId);
        if (!row) return;
        var boxes = $$('input[data-perm]', row);
        var on = boxes.filter(function (b) { return b.checked; }).length;
        var label = document.getElementById('perm-count-' + userId);
        var meter = document.getElementById('perm-meter-' + userId);
        if (label && label.textContent !== String(on)) {
          label.textContent = on;
          restart(label, 'bump');
        }
        if (meter && boxes.length) meter.style.setProperty('--pct', Math.round(on / boxes.length * 100) + '%');
        updateGroupCounts(row);
      }

      $$('.perm-row').forEach(function (row) { updateGroupCounts(row); });

      /* ── The switch ── */
      function pop(box) {
        var sw = box.closest('.switch');
        sw.classList.remove('pop-on', 'pop-off');
        void sw.offsetWidth;
        sw.classList.add(box.checked ? 'pop-on' : 'pop-off');
      }

      function savePerm(box, silent) {
        var sw = box.closest('.switch');
        var item = box.closest('.perm-item');
        var wanted = box.checked;
        sw.classList.add('saving');
        updatePermCount(box.dataset.user);

        var body = new URLSearchParams({
          action: 'toggle',
          csrf: CSRF,
          user_id: box.dataset.user,
          perm: box.dataset.perm,
          value: wanted ? '1' : '0'
        });

        return fetch('manage_users.php', { method: 'POST', body: body, credentials: 'include' })
          .then(function (r) { return r.json(); })
          .then(function (data) {
            if (!data.ok) throw new Error(data.error || 'Failed');
            return true;
          })
          .catch(function () { return false; })
          .then(function (ok) {
            sw.classList.remove('saving');
            if (ok) {
              restart(item, 'saved');
              // toggled again while the request was in flight → sync the latest state
              if (box.checked !== wanted) savePerm(box, silent);
            } else {
              if (box.checked === wanted) box.checked = !wanted; // revert
              restart(sw, 'shake');
              pop(box);
              updatePermCount(box.dataset.user);
              if (!silent) showToast('Could not save. Please try again.', 'error');
            }
            return ok;
          });
      }

      $$('.switch input[data-perm]').forEach(function (box) {
        box.addEventListener('change', function () {
          haptic();
          pop(box);
          savePerm(box);
        });
      });

      /* ── Delegated clicks ── */
      var lastFocus = null;
      var activeModal = null;

      document.addEventListener('click', function (e) {
        var t = e.target;

        // expand / collapse permissions
        var permBtn = t.closest('.perm-btn');
        if (permBtn) {
          var row = document.getElementById('perm-row-' + permBtn.dataset.id);
          var open = !row.classList.contains('open');
          row.classList.toggle('open', open);
          permBtn.classList.toggle('perm-open', open);
          permBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
          permBtn.closest('tr').classList.toggle('is-open', open);
          return;
        }

        // bulk "All / None" per group
        var bulk = t.closest('[data-bulk]');
        if (bulk) {
          var target = bulk.dataset.bulk === 'on';
          var group = bulk.closest('.perm-group');
          var changed = $$('input[data-perm]:not(:disabled)', group).filter(function (b) { return b.checked !== target; });
          if (!changed.length) return;
          haptic();
          Promise.all(changed.map(function (b, i) {
            return new Promise(function (resolve) {
              setTimeout(function () {
                b.checked = target;
                pop(b);
                savePerm(b, true).then(resolve);
              }, reduceMotion ? 0 : i * 55);
            });
          })).then(function (results) {
            var failed = results.filter(function (r) { return !r; }).length;
            if (failed) showToast(failed + ' permission' + (failed > 1 ? 's' : '') + ' could not be saved.', 'error');
            else showToast((target ? 'Enabled ' : 'Disabled ') + results.length + ' permission' + (results.length > 1 ? 's' : ''), 'success');
          });
          return;
        }

        // reset password
        var resetBtn = t.closest('.js-reset');
        if (resetBtn) {
          $('#resetUserId').value = resetBtn.dataset.id;
          $('#resetName').textContent = resetBtn.dataset.name;
          pw.value = '';
          updateStrength();
          openModal($('#resetModal'), pw);
          return;
        }

        // delete account
        var delBtn = t.closest('.js-delete');
        if (delBtn) {
          $('#deleteUserId').value = delBtn.dataset.id;
          $('#deleteName').textContent = delBtn.dataset.name;
          openModal($('#deleteModal'), $('#deleteCancel'));
          return;
        }

        // modal close (X, cancel, backdrop)
        if (t.closest('[data-close]') || t.classList.contains('mu-modal')) {
          closeModal();
          return;
        }

        // dismiss flash alert
        var closeAlert = t.closest('.alert-close');
        if (closeAlert) dismissAlert(closeAlert.closest('.alert'));
      });

      /* ── Role select: show busy state, then submit ── */
      $$('.role-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
          sel.classList.add('busy');
          sel.form.submit();
        });
      });

      /* ── Modals ── */
      function openModal(modal, focusEl) {
        lastFocus = document.activeElement;
        activeModal = modal;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        setTimeout(function () { if (focusEl) focusEl.focus(); }, 80);
      }

      function closeModal() {
        if (!activeModal) return;
        activeModal.classList.remove('open');
        activeModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        activeModal = null;
        if (lastFocus && lastFocus.focus) lastFocus.focus();
      }

      document.addEventListener('keydown', function (e) {
        if (activeModal) {
          if (e.key === 'Escape') { closeModal(); return; }
          if (e.key === 'Tab') { // keep focus inside the modal
            var f = $$('button:not(:disabled), input:not([type=hidden]):not(:disabled)', activeModal)
              .filter(function (el) { return el.offsetParent !== null; });
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
          }
          return;
        }
        // "/" focuses the search box
        var tag = (e.target.tagName || '').toLowerCase();
        if (e.key === '/' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') {
          e.preventDefault();
          searchInput.focus();
        }
      });

      /* ── Reset password modal ── */
      var pw = $('#resetPassword');
      var pwMeter = $('#pwMeter');
      var pwHint = $('#pwHint');
      var resetSubmit = $('#resetSubmit');

      function updateStrength() {
        var v = pw.value, level = '', hint = 'Use at least 6 characters.';
        if (v.length) {
          if (v.length < 6) {
            level = 'weak';
            hint = (6 - v.length) + ' more character' + (6 - v.length > 1 ? 's' : '') + ' to go';
          } else if (v.length >= 12 && /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v) && /[^A-Za-z0-9]/.test(v)) {
            level = 'strong'; hint = 'Strong password';
          } else if (v.length >= 10 && /[A-Za-z]/.test(v) && /\d/.test(v)) {
            level = 'good'; hint = 'Good password';
          } else {
            level = 'fair'; hint = 'Okay — add length, numbers or symbols to strengthen it';
          }
        }
        pwMeter.dataset.level = level;
        pwHint.textContent = hint;
        resetSubmit.disabled = v.length < 6;
      }

      pw.addEventListener('input', updateStrength);

      $('#pwToggle').addEventListener('click', function () {
        var show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        this.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        pw.focus();
      });

      $('#resetForm').addEventListener('submit', function (e) {
        if (pw.value.length < 6) { e.preventDefault(); return; }
        resetSubmit.classList.add('loading');
      });

      $('#deleteForm').addEventListener('submit', function () {
        $('.mu-btn-danger', this).classList.add('loading');
      });

      /* ── Live search + role filter ── */
      var searchInput = $('#searchInput');
      var searchBox = $('#searchBox');
      var userRows = $$('.user-row');
      var emptyRow = $('#emptyRow');
      var activeRole = 'all';

      function applyFilter() {
        var q = searchInput.value.trim().toLowerCase();
        var shown = 0;
        userRows.forEach(function (r) {
          var match = (!q || r.dataset.username.indexOf(q) > -1) &&
                      (activeRole === 'all' || r.dataset.role === activeRole);
          r.classList.toggle('is-hidden', !match);
          document.getElementById('perm-row-' + r.dataset.id).classList.toggle('is-hidden', !match);
          if (match) shown++;
        });
        emptyRow.classList.toggle('is-hidden', shown > 0);
        searchBox.classList.toggle('has-value', q.length > 0);
      }

      searchInput.addEventListener('input', applyFilter);
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { searchInput.value = ''; applyFilter(); searchInput.blur(); }
      });
      $('#searchForm').addEventListener('submit', function (e) { e.preventDefault(); });
      $('#searchClear').addEventListener('click', function () {
        searchInput.value = '';
        applyFilter();
        searchInput.focus();
      });

      $$('#roleFilters .chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          $$('#roleFilters .chip').forEach(function (c) { c.classList.remove('active'); });
          chip.classList.add('active');
          activeRole = chip.dataset.filter;
          applyFilter();
        });
      });

      applyFilter();

      /* ── Flash alert ── */
      function dismissAlert(el) {
        if (!el || el.classList.contains('dismissing')) return;
        el.style.maxHeight = el.offsetHeight + 'px';
        void el.offsetWidth;
        el.classList.add('dismissing');
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 450);
      }

      var flash = $('#flash-message');
      if (flash) {
        var ms = parseInt(flash.dataset.timeout, 10) || 0;
        if (ms) setTimeout(function () { dismissAlert(flash); }, ms);
      }

      /* ── Count-up for the stat cards ── */
      $$('[data-count]').forEach(function (el) {
        var end = parseInt(el.dataset.count, 10) || 0;
        if (reduceMotion || end === 0) return;
        var start = null, dur = 700;
        el.textContent = '0';
        function tick(ts) {
          if (start === null) start = ts;
          var p = Math.min((ts - start) / dur, 1);
          el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
          if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
      });
    })();
  </script>
</body>

</html>