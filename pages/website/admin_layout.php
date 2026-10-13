<?php
/**
 * admin_layout.php
 * Shared shell (page heading + content area) for every website-admin page. The section
 * navigation, notifications and user menu live in website_admin.php, outside the iframe.
 *
 * This file is presentation only. It does not start the session, check roles
 * or touch permissions: every page still does that itself, above the call to
 * admin_layout_start(), exactly as before. It only *reads* the session and
 * permission helpers (can()) to decide what to display.
 *
 * Usage (in a page, after its own auth / CSRF / POST handling):
 *
 *   require_once 'admin_layout.php';
 *   admin_layout_start([
 *       'page'     => 'orders',                 // dashboard|orders|customers|products|pricing|reports|chat
 *       'title'    => 'Orders',                 // <h1> and <title>
 *       'subtitle' => '42 orders',              // optional, plain text
 *       'actions'  => '<a class="btn ...">',    // optional, trusted HTML for the right side of the heading
 *       'chart'    => true,                     // load Chart.js
 *       'fill'     => true,                     // full-height page (chat)
 *   ]);
 *   ... page content ...
 *   admin_layout_end();
 */

function wa_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Read-only counts for nav badges and the notification menu. Cached per request. */
function wa_layout_counts(array $opts = []): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = ['orders' => [], 'orders_all' => 0, 'pricing_pending' => 0, 'chat_unread' => 0];
    $db = $GLOBALS['inventory'] ?? null;

    if ($db instanceof mysqli) {
        try {
            $res = $db->query("SELECT status, COUNT(*) AS c FROM orders GROUP BY status");
            while ($res && ($row = $res->fetch_assoc())) {
                $cache['orders'][$row['status']] = (int) $row['c'];
                $cache['orders_all'] += (int) $row['c'];
            }
        } catch (Throwable $e) {
            // badges are a convenience; never break a page over them
        }
        try {
            $res = $db->query("SELECT COUNT(*) AS c FROM pricing_requests WHERE status = 'pending'");
            if ($res) {
                $cache['pricing_pending'] = (int) $res->fetch_assoc()['c'];
            }
        } catch (Throwable $e) {
        }
    }

    if (isset($opts['chat_unread'])) {
        $cache['chat_unread'] = (int) $opts['chat_unread'];
    } elseif ($db instanceof mysqli && !empty($_SESSION['user_id'])) {
        try {
            if (!class_exists('ChatController')) {
                $file = __DIR__ . '/../../config/ChatController.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
            if (class_exists('ChatController')) {
                $chat = new ChatController($db);
                $cache['chat_unread'] = (int) $chat->getUnreadCount($_SESSION['user_id']);
            }
        } catch (Throwable $e) {
        }
    }

    return $cache;
}

function wa_layout_nav(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high', 'href' => 'admin_dashboard.php'],
        ['key' => 'orders', 'label' => 'Orders', 'icon' => 'fa-receipt', 'href' => 'admin_orders.php', 'children' => [
            ['status' => '', 'label' => 'All orders'],
            ['status' => 'pending', 'label' => 'Pending'],
            ['status' => 'paid', 'label' => 'Paid'],
            ['status' => 'processing', 'label' => 'Processing'],
            ['status' => 'ready_for_pickup', 'label' => 'Ready for pickup'],
            ['status' => 'completed', 'label' => 'Completed'],
            ['status' => 'cancelled', 'label' => 'Cancelled'],
        ]],
        ['key' => 'customers', 'label' => 'Customers', 'icon' => 'fa-users', 'href' => 'admin_customers.php'],
        ['key' => 'products', 'label' => 'Products', 'icon' => 'fa-box-open', 'href' => 'admin_products.php'],
        ['key' => 'pricing', 'label' => 'Pricing estimates', 'icon' => 'fa-file-invoice-dollar', 'href' => 'admin_pricing_estimates.php'],
        ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fa-chart-line', 'href' => 'admin_reports.php', 'permission' => 'web_finance'],
        ['key' => 'chat', 'label' => 'Chat', 'icon' => 'fa-comments', 'href' => 'admin_chat.php'],
    ];
}

function admin_layout_start(array $o = []): void
{
    $page = $o['page'] ?? '';
    $title = $o['title'] ?? 'Admin';
    $subtitle = $o['subtitle'] ?? '';
    $actions = $o['actions'] ?? '';
    $fill = !empty($o['fill']);
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo wa_h($title); ?> - Active Media Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($o['chart'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-<?php echo wa_h($page); ?>" data-page="<?php echo wa_h($page); ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="app" id="app">
        <div class="shell">
            <main class="page<?php echo $fill ? ' page--fill' : ''; ?>" id="main" tabindex="-1">
                <header class="page-head">
                    <div class="page-head-text">
                        <h1 class="page-title"><?php echo wa_h($title); ?></h1>
                        <?php if ($subtitle !== ''): ?><p class="page-sub"><?php echo wa_h($subtitle); ?></p><?php endif; ?>
                    </div>
                    <?php if ($actions !== ''): ?><div class="page-actions"><?php echo $actions; ?></div><?php endif; ?>
                </header>
<?php
}

function admin_layout_end(): void
{
    ?>
            </main>
        </div>
    </div>
    <div class="toast-stack" id="toastStack" aria-live="polite"></div>
<?php
}