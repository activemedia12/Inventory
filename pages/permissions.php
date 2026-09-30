<?php
// permissions.php — include after session_start() and config/db.php
//
// Usage on any page:
//   require_once 'permissions.php';
//   if (can('delete')) { ...show delete button / run delete... }
//   require_permission('add_job');   // stops the request with 403 if not allowed
//
// Super admins always pass every check.

/** Every toggle, grouped for the Manage Users screen: group => [key => [label, db column]] */
function permission_groups(): array
{
  return [
    'Products, deliveries & clients' => [
      'add'    => ['Add records',    'can_add'],
      'edit'   => ['Edit records',   'can_edit'],
      'update' => ['Update records (stock usage, job status)', 'can_update'],
      'delete' => ['Delete records', 'can_delete'],
    ],
    'Dashboard' => [
      'view_finance' => ['View financial performance', 'can_view_finance'],
    ],
    'Job orders' => [
      'add_job'        => ['Add job order',           'can_add_job'],
      'edit_job'       => ['Edit job order',          'can_edit_job'],
      'delete_job'     => ['Delete job order',        'can_delete_job'],
      'enter_expenses' => ['Enter expenses',          'can_enter_expenses'],
      'job_total_cost' => ['Enter job total cost',    'can_job_total_cost'],
      'billing_info'   => ['Save billing info',       'can_billing_info'],
    ],
    'Admin tools' => [
      'manage_prices' => ['Manage prices',         'can_manage_prices'],
      'manage_types'  => ['Manage product types',  'can_manage_types'],
    ],
    'Website section' => [
      'web_orders'    => ['Update website order status',              'can_web_orders'],
      'web_pricing'   => ['Respond to price requests',                'can_web_pricing'],
      'web_products'  => ['Add / edit website products',              'can_web_products'],
      'web_customers' => ['Edit website customers',                   'can_web_customers'],
      'web_chat'      => ['Start conversations & reply in chats',     'can_web_chat'],
      'web_finance'   => ['View website revenue & reports',           'can_web_finance'],
      'web_delete'    => ['Delete website records (customers, products, price requests, chats)', 'can_web_delete'],
    ],
  ];
}

/** Short phrase used in "You don't have permission to ___" messages. */
function permission_verbs(): array
{
  return [
    'add' => 'add records', 'edit' => 'edit records', 'update' => 'update records', 'delete' => 'delete records',
    'view_finance' => 'view financial performance',
    'add_job' => 'add job orders', 'edit_job' => 'edit job orders', 'delete_job' => 'delete job orders',
    'enter_expenses' => 'enter expenses', 'job_total_cost' => 'enter a job total cost',
    'billing_info' => 'save billing info',
    'manage_prices' => 'manage prices', 'manage_types' => 'manage product types',
    'web_orders' => 'update website orders', 'web_pricing' => 'respond to price requests',
    'web_products' => 'add or edit website products', 'web_customers' => 'edit website customers',
    'web_chat' => 'start or reply to website chats', 'web_delete' => 'delete website records',
    'web_finance' => 'view website revenue and reports',
  ];
}

/** Full sentence for flash messages / JSON responses. */
function permission_denied_message(string $action): string
{
  $verbs = permission_verbs();
  return "You don't have permission to " . ($verbs[$action] ?? $action) . ". Ask a super admin to turn it on.";
}

/** key => column, flattened */
function permission_columns(): array
{
  $map = [];
  foreach (permission_groups() as $items) {
    foreach ($items as $key => [$label, $col]) $map[$key] = $col;
  }
  return $map;
}

/**
 * Creates user_permissions and adds any missing columns. Called by manage_users.php.
 * Columns added here for the first time are switched ON for existing admins so
 * they don't suddenly lose things they could do before; everyone else starts off.
 */
function ensure_permission_schema(mysqli $db): void
{
  $db->query("
    CREATE TABLE IF NOT EXISTS user_permissions (
      user_id INT NOT NULL PRIMARY KEY,
      can_add TINYINT(1) NOT NULL DEFAULT 0,
      can_edit TINYINT(1) NOT NULL DEFAULT 0,
      can_update TINYINT(1) NOT NULL DEFAULT 0,
      can_delete TINYINT(1) NOT NULL DEFAULT 0,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
  ");

  $have = [];
  $res = $db->query("SHOW COLUMNS FROM user_permissions");
  while ($c = $res->fetch_assoc()) $have[$c['Field']] = true;

  $base = ['can_add', 'can_edit', 'can_update', 'can_delete'];
  foreach (permission_columns() as $col) {
    if (isset($have[$col])) continue;
    $db->query("ALTER TABLE user_permissions ADD `$col` TINYINT(1) NOT NULL DEFAULT 0");
    if (!in_array($col, $base, true)) {
      $db->query("INSERT INTO user_permissions (user_id, `$col`) SELECT id, 1 FROM users WHERE role = 'admin' ON DUPLICATE KEY UPDATE `$col` = 1");
    }
  }
}

function is_super_admin(): bool
{
  return ($_SESSION['role'] ?? '') === 'super_admin';
}

function can(string $action): bool
{
  global $inventory;
  static $cache = null;

  $columns = permission_columns();
  if (!isset($columns[$action])) return false;
  if (is_super_admin()) return true;
  if (empty($_SESSION['user_id'])) return false;

  if ($cache === null) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $inventory->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $cache = [];
    foreach ($columns as $key => $col) {
      $cache[$key] = !empty($row[$col]); // a missing column/row counts as OFF
    }
  }
  return $cache[$action];
}

function require_permission(string $action): void
{
  if (!can($action)) {
    http_response_code(403);
    exit('You do not have permission to ' . htmlspecialchars($action) . '.');
  }
}

/** For AJAX endpoints that answer in JSON ({success, message}). */
function require_permission_json(string $action, string $what): void
{
  if (!can($action)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => "You don't have permission to $what."]);
    exit;
  }
}

/**
 * Put inside a button/link tag: <a href="..." <?= deny_attr('edit') ?>>
 * Users who lack the permission still see the control, but clicking it shows
 * a "you need permission" notice instead of running the action.
 * (The server-side require_permission() checks stay in place either way.)
 */
function deny_attr(string $action): string
{
  return can($action) ? '' : ' data-denied="' . htmlspecialchars($action, ENT_QUOTES) . '"';
}

/** Print once per page, just before </body>. Adds the notice + click blocking. */
function permission_notice(): void
{
  static $done = false;
  if ($done) return;
  $done = true;
  ?>
  <div id="permToast" role="alert" style="position:fixed;top:20px;right:20px;z-index:99999;display:none;max-width:340px;background:#fff;border-left:4px solid #ff4d4f;box-shadow:0 6px 24px rgba(0,0,0,.18);border-radius:8px;padding:14px 18px;font:14px/1.4 Inter,system-ui,sans-serif;color:#1c1e21;">
    <strong style="display:block;margin-bottom:2px;"><i class="fas fa-lock" style="color:#ff4d4f;margin-right:6px;"></i>Permission required</strong>
    <span id="permToastMsg"></span>
  </div>
  <script>
    (function () {
      var timer;
      var verbs = <?= json_encode(permission_verbs()) ?>;

      function showNoPermission(action) {
        var toast = document.getElementById('permToast');
        document.getElementById('permToastMsg').textContent =
          "You don't have permission to " + (verbs[action] || action) + '. Ask a super admin to turn it on.';
        toast.style.display = 'block';
        clearTimeout(timer);
        timer = setTimeout(function () { toast.style.display = 'none'; }, 3500);
      }
      window.showNoPermission = showNoPermission;

      // Capture phase: runs before row-click handlers, confirm() prompts, and form submits.
      document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-denied]');
        if (!el) return;
        e.preventDefault();
        e.stopPropagation();
        showNoPermission(el.getAttribute('data-denied'));
      }, true);
    })();
  </script>
  <?php
}