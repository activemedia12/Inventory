<?php
/**
 * admin_layout.php
 * Shared shell (page heading + content area) for every website-admin page. The section
 * navigation, notifications and user menu live in website_admin.php, outside the iframe.
 *
 * This file is presentation only. It does not start the session, check roles,
 * touch permissions or run any queries: every page still does that itself,
 * above the call to admin_layout_start().
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
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