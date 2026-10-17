<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin', 'employee'])) {
    header("Location: ../accounts/login.php");
    exit;
}

// Used to hide the Reports tab from people without the web_finance permission
// (the Reports page still enforces it on its own). If the permission helpers
// can't be loaded from here, the tab simply stays visible.
$wa_can_finance = true;
if (is_file(__DIR__ . '/../config/db.php') && is_file(__DIR__ . '/permissions.php')) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/permissions.php';
    if (function_exists('can')) {
        $wa_can_finance = (bool) can('web_finance');
    }
}

$wa_role = (string) ($_SESSION['role'] ?? 'admin');
$wa_role_label = ucwords(str_replace('_', ' ', $wa_role));
$wa_username = (string) ($_SESSION['username'] ?? ($_SESSION['display_name'] ?? ''));
$wa_name = $wa_username !== '' ? $wa_username : $wa_role_label;
$wa_initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $wa_name) ?: 'A', 0, 2));

// Section navigation for the website admin pages (these load in the iframe below)
$wa_nav = [
    ['key' => 'admin_dashboard.php', 'label' => 'Dashboard', 'icon' => 'fa-gauge-high'],
    ['key' => 'admin_orders.php', 'label' => 'Orders', 'icon' => 'fa-receipt', 'badge' => 'orders', 'children' => [
        ['status' => '', 'label' => 'All orders'],
        ['status' => 'pending', 'label' => 'Pending'],
        ['status' => 'paid', 'label' => 'Paid'],
        ['status' => 'processing', 'label' => 'Processing'],
        ['status' => 'ready_for_pickup', 'label' => 'Ready for pickup'],
        ['status' => 'completed', 'label' => 'Completed'],
        ['status' => 'cancelled', 'label' => 'Cancelled'],
    ]],
    ['key' => 'admin_customers.php', 'label' => 'Customers', 'icon' => 'fa-users'],
    ['key' => 'admin_products.php', 'label' => 'Products', 'icon' => 'fa-box-open'],
    ['key' => 'admin_pricing_estimates.php', 'label' => 'Pricing estimates', 'icon' => 'fa-file-invoice-dollar', 'badge' => 'pricing'],
    ['key' => 'admin_reports.php', 'label' => 'Reports', 'icon' => 'fa-chart-line', 'permission' => 'web_finance'],
    ['key' => 'admin_chat.php', 'label' => 'Chat', 'icon' => 'fa-comments', 'badge' => 'chats'],
];
$wa_nav = array_values(array_filter($wa_nav, function ($item) use ($wa_can_finance) {
    return ($item['permission'] ?? '') !== 'web_finance' || $wa_can_finance;
}));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Website Administrator</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbced3;
            border-radius: 8px;
        }

        :root {
            --primary: #4f5eff;
            --secondary: #4048e0;
            --primary-bg: #eef1ff;
            --light: #f6f6f7;
            --dark: #14171f;
            --gray: #6b7280;
            --light-gray: #e2e4e7;
            --card-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            border-right: 1px solid var(--light-gray);
            padding: 20px 0;
        }

        .brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 0;
            transform: none;
        }

        .nav-menu {
            list-style: none;
            padding: 0 12px;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--gray);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .nav-menu li a:hover {
            background-color: var(--light);
            color: var(--dark);
        }

        .nav-menu li a.active {
            background-color: var(--primary-bg);
            color: var(--secondary);
        }

        .nav-menu li a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: var(--gray);
        }

        .nav-menu li a.active i,
        .nav-menu li a:hover i {
            color: inherit;
        }

        /* Fixed top bar for the website admin: section nav (centred), notifications
           and user menu. It lives here, outside the iframe, so it never reloads
           (and never blinks) when you change pages. */
        .site-bar {
            position: fixed;
            top: 0;
            left: 240px;
            right: 0;
            height: 56px;
            /* Must be above .content-frame (1000), otherwise the bell / user /
               order-status dropdowns, which hang below the 56px bar, are drawn
               underneath the iframe. Lowered under the dim while a modal is open
               (see body.shell-modal-open below). */
            z-index: 1100;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 10px;
            padding: 0 24px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--light-gray);
        }

        .site-nav {
            grid-column: 2;
            display: flex;
            align-items: stretch;
            gap: 2px;
            height: 100%;
            min-width: 0;
        }

        .site-item {
            position: relative;
            display: flex;
            align-items: center;
        }

        .site-link {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 36px;
            padding: 0 12px;
            border-radius: 6px;
            color: #4a5262;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            text-decoration: none;
            transition: background-color 0.12s ease, color 0.12s ease;
        }

        .site-link i {
            width: 16px;
            text-align: center;
            font-size: 14px;
        }

        .site-link:hover,
        .site-item:focus-within>.site-link {
            background: #f8f9fb;
            color: var(--dark);
        }

        /* The selectors also cover :hover and :focus-within, which have higher
           specificity than a plain .is-active; without them the highlight fades to
           the light hover grey as soon as you click a link (it keeps focus). */
        .site-link.is-active,
        .site-link.is-active:hover,
        .site-item:focus-within>.site-link.is-active {
            background: var(--primary-bg);
            color: var(--secondary);
        }

        .site-badge {
            min-width: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            line-height: 18px;
            text-align: center;
        }

        .site-badge[hidden] {
            display: none;
        }

        .site-pop {
            position: absolute;
            top: 100%;
            z-index: 950;
            min-width: 230px;
            padding: 6px;
            list-style: none;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(20, 23, 31, 0.14);
        }

        .site-pop[hidden] {
            display: none;
        }

        .site-menu {
            display: none;
            left: 0;
        }

        .site-item.has-menu:hover .site-menu,
        .site-item.has-menu:focus-within .site-menu {
            display: block;
        }

        .site-sublink,
        .site-pop-item {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 32px;
            padding: 0 10px;
            border-radius: 6px;
            color: #4a5262;
            font-size: 13px;
            white-space: nowrap;
            text-decoration: none;
        }

        .site-sublink:hover,
        .site-pop-item:hover {
            background: #f8f9fb;
            color: var(--dark);
        }

        .site-sublink.is-active {
            background: var(--primary-bg);
            color: var(--secondary);
            font-weight: 600;
        }

        .site-pop-title {
            padding: 8px 10px 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--gray);
        }

        .site-pop-title small {
            display: block;
            font-weight: 400;
        }

        /* Notification bell: wider panel, icon chip + title/detail + count */
        #notifyPop {
            width: 320px;
            padding: 6px 6px 8px;
        }

        #notifyPop .site-pop-item {
            height: auto;
            min-height: 52px;
            padding: 8px 10px;
            white-space: normal;
        }

        .site-pop-icon {
            flex: none;
            display: grid;
            place-items: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-size: 13px;
        }

        .site-pop-icon--warn {
            background: #fff3d4;
            color: #8a5a00;
        }

        .site-pop-icon--info {
            background: #e4eeff;
            color: #1d5fb8;
        }

        .site-pop-text {
            flex: 1;
            min-width: 0;
        }

        .site-pop-text strong,
        .site-pop-text small {
            display: block;
        }

        .site-pop-text strong {
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
        }

        .site-pop-text small {
            margin-top: 1px;
            font-size: 12px;
            color: var(--gray);
        }

        .site-pop-count {
            flex: none;
            min-width: 22px;
            padding: 0 7px;
            border-radius: 999px;
            background: #f1f3f7;
            color: #4a5262;
            font-size: 12px;
            font-weight: 600;
            line-height: 22px;
            text-align: center;
        }

        .site-pop-empty {
            padding: 8px 10px 10px;
            font-size: 13px;
            color: var(--gray);
        }

        .site-tools {
            grid-column: 3;
            justify-self: end;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .site-tool {
            position: relative;
        }

        .site-pop--end {
            right: 0;
            top: calc(100% + 8px);
        }

        .site-icon-btn,
        .site-user-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 36px;
            border: 1px solid transparent;
            border-radius: 6px;
            background: transparent;
            color: #4a5262;
            font-size: 13px;
            cursor: pointer;
        }

        .site-icon-btn {
            position: relative;
            width: 36px;
            justify-content: center;
            font-size: 15px;
        }

        .site-user-btn {
            padding: 0 6px;
        }

        .site-icon-btn:hover,
        .site-user-btn:hover {
            background: #f8f9fb;
        }

        .site-dot {
            position: absolute;
            top: 7px;
            right: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ef4444;
            border: 2px solid var(--card-bg);
        }

        .site-dot[hidden] {
            display: none;
        }

        .site-avatar {
            display: grid;
            place-items: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary-bg);
            color: var(--secondary);
            font-size: 11px;
            font-weight: 700;
        }

        .site-user-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.15;
            font-weight: 500;
        }

        .site-user-text small {
            font-size: 11px;
            font-weight: 400;
            color: var(--gray);
        }

        @media (max-width: 1240px) {
            .site-user-text {
                display: none;
            }
        }

        @media (max-width: 1000px) {
            .site-link:not(.is-active) span:not(.site-badge) {
                display: none;
            }

            .site-link:not(.is-active) {
                padding: 0 10px;
            }
        }

        /* Dims the sidebar and top bar while a modal is open inside the iframe.
           It sits above them (z-index) but below the iframe, which dims itself,
           so the two layers never stack on the content area. */
        .shell-dim {
            position: fixed;
            inset: 0;
            z-index: 960;
            background: rgb(20, 23, 31);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.16s ease, visibility 0s linear 0.16s;
        }

        .shell-dim.open {
            opacity: var(--dim, 0.5);
            visibility: visible;
            transition-delay: 0s;
        }

        /* While a modal is open the top bar drops back under the dim (960). */
        body.shell-modal-open .site-bar {
            z-index: 900;
        }

        .content-frame {
            position: relative;
            z-index: 1000;
            width: calc(100% - 240px);
            height: calc(100vh - 56px);
            border: none;
            margin-left: 240px;
            margin-top: 56px;
            background: var(--light);
        }

        /* This admin panel embeds a full desktop dashboard (iframe) with a
           lot of dense tables/controls that were never designed to reflow
           for small screens, so instead of trying to make it responsive we
           block it outright below the breakpoint and show a clear note
           telling the person to switch to a desktop/laptop. Everything in
           this hidden-by-default block only appears on small screens. */
        .desktop-only-notice {
            display: none;
        }

        @media (max-width: 768px) {

            .sidebar-con,
            .site-bar,
            .content-frame {
                display: none !important;
            }

            .desktop-only-notice {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 14px;
                width: 100%;
                min-height: 100vh;
                padding: 32px;
                text-align: center;
            }

            .desktop-only-notice i {
                font-size: 40px;
                color: var(--primary);
            }

            .desktop-only-notice h1 {
                font-size: 18px;
                font-weight: 700;
                color: var(--dark);
            }

            .desktop-only-notice p {
                font-size: 14px;
                color: var(--gray);
                max-width: 340px;
                line-height: 1.5;
            }

            .desktop-only-notice a {
                margin-top: 6px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                border-radius: 8px;
                background: var(--primary);
                color: #fff;
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php" onclick="goToLastProductPage()"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="quotation.php"><i class="fas fa-calculator"></i> <span>Quotation</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php" class="active"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                    <li><a href="manage_users.php"><i class="fas fa-user-shield"></i> <span>Manage Users</span></a></li>
                <?php endif; ?>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Shown only on small screens (see @media max-width:768px) in place of
         the sidebar/iframe, which are desktop-only. -->
    <div class="desktop-only-notice">
        <i class="fas fa-desktop" aria-hidden="true"></i>
        <h1>Desktop Only</h1>
        <p>The Website Administrator panel isn't available on mobile. Please switch to a desktop or laptop computer to manage the website.</p>
        <a href="dashboard.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>

    <!-- Fixed top bar: section nav, notifications, user menu. Links load into the iframe below. -->
    <header class="site-bar" id="siteBar">
        <nav class="site-nav" aria-label="Website admin sections">
            <?php foreach ($wa_nav as $item): ?>
                <div class="site-item<?php echo !empty($item['children']) ? ' has-menu' : ''; ?>">
                    <a class="site-link" href="website/<?php echo htmlspecialchars($item['key']); ?>" target="contentFrame"
                        data-page="<?php echo htmlspecialchars($item['key']); ?>" title="<?php echo htmlspecialchars($item['label']); ?>">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="site-badge" data-badge-key="<?php echo htmlspecialchars($item['badge']); ?>" hidden>0</span>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                        <ul class="site-pop site-menu" aria-label="Order status shortcuts">
                            <?php foreach ($item['children'] as $child): ?>
                                <li>
                                    <a class="site-sublink" target="contentFrame" data-status="<?php echo htmlspecialchars($child['status']); ?>"
                                        href="website/<?php echo htmlspecialchars($item['key'] . ($child['status'] !== '' ? '?status=' . $child['status'] : '')); ?>"><?php echo htmlspecialchars($child['label']); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="site-tools">
            <div class="site-tool">
                <button type="button" class="site-icon-btn" id="notifyBtn" data-pop="notifyPop" aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
                    <i class="fas fa-bell" aria-hidden="true"></i>
                    <span class="site-dot" id="notifyDot" hidden></span>
                </button>
                <div class="site-pop site-pop--end" id="notifyPop" hidden>
                    <div class="site-pop-title">Needs attention</div>
                    <div id="notifyList">
                        <div class="site-pop-empty">You're all caught up.</div>
                    </div>
                </div>
            </div>
            <div class="site-tool">
                <button type="button" class="site-user-btn" data-pop="userPop" aria-haspopup="true" aria-expanded="false">
                    <span class="site-avatar" aria-hidden="true"><?php echo htmlspecialchars($wa_initials); ?></span>
                    <span class="site-user-text"><?php echo htmlspecialchars($wa_name); ?><small><?php echo htmlspecialchars($wa_role_label); ?></small></span>
                </button>
            </div>
        </div>
    </header>

    <!-- Content Frame: src is set from JS (not here) so mobile visitors,
         who only ever see the notice above, never pay the cost of loading
         this desktop dashboard in the background. -->
    <div class="shell-dim" id="shellDim" aria-hidden="true"></div>

    <iframe id="contentFrame" name="contentFrame" class="content-frame" title="Website admin content"></iframe>

    <script>
        const WEBSITE_ADMIN_PAGE_KEY = 'lastWebsiteAdminPage';
        const DESKTOP_BREAKPOINT = 768; // keep in sync with the CSS media query above
        const DEFAULT_PAGE = 'website/admin_dashboard.php';
        const BADGE_COUNTS_URL = 'website/get_badge_counts.php';
        const BADGE_POLL_INTERVAL_MS = 30000;
        // The nav links above load into the iframe; this shell remembers which page was open.
        const WEBSITE_ADMIN_PAGES = [
            'website/admin_dashboard.php',
            'website/admin_customers.php',
            'website/admin_orders.php',
            'website/admin_pricing_estimates.php',
            'website/admin_products.php',
            'website/admin_reports.php',
            'website/admin_chat.php'
        ];

        const frame = () => document.getElementById('contentFrame');

        function isDesktop() {
            return window.innerWidth > DESKTOP_BREAKPOINT;
        }

        function goToLastProductPage() {
            const last = localStorage.getItem('lastProductPage');
            window.location.href = last || 'papers.php';
        }

        function restoreLastWebsiteAdminPage() {
            const savedPage = localStorage.getItem(WEBSITE_ADMIN_PAGE_KEY);
            return WEBSITE_ADMIN_PAGES.includes(savedPage) ? savedPage : DEFAULT_PAGE;
        }

        // Highlight the nav item for whatever page the iframe is on (same origin),
        // including when a page inside the iframe links somewhere else, and
        // remember it to reopen after a refresh.
        function syncNavToFrame() {
            try {
                const loc = frame().contentWindow.location;
                const name = loc.pathname.split('/').pop();
                const status = new URLSearchParams(loc.search).get('status') || '';
                document.querySelectorAll('.site-link').forEach(a => {
                    const on = a.dataset.page === name;
                    a.classList.toggle('is-active', on);
                    if (on) a.setAttribute('aria-current', 'page');
                    else a.removeAttribute('aria-current');
                });
                document.querySelectorAll('.site-sublink').forEach(a => {
                    a.classList.toggle('is-active', name === 'admin_orders.php' && a.dataset.status === status);
                });
                if (WEBSITE_ADMIN_PAGES.includes('website/' + name)) {
                    localStorage.setItem(WEBSITE_ADMIN_PAGE_KEY, 'website/' + name);
                }
            } catch (error) {
                // cross-origin or blocked storage: leave the highlight as it is
            }
        }

        // ---------- notification badges + bell ----------
        function renderNotices(counts) {
            const n = key => Number(counts[key]) || 0;
            const plural = (num, one, many) => num + ' ' + (num === 1 ? one : many);
            // [icon, tone, title, detail, count, link]. "payments" is optional: it only shows when
            // get_badge_counts.php returns it (proofs waiting to be verified).
            const items = [];
            if (n('payments') > 0) items.push(['fa-file-invoice-dollar', 'warn', plural(n('payments'), 'payment proof', 'payment proofs') + ' to verify', 'Customers are waiting on you', n('payments'), 'website/admin_orders.php?status=pending']);
            if (n('orders') > 0) items.push(['fa-clock', 'info', plural(n('orders'), 'pending order', 'pending orders'), 'Waiting for payment or review', n('orders'), 'website/admin_orders.php?status=pending']);
            if (n('pricing') > 0) items.push(['fa-file-invoice', 'info', plural(n('pricing'), 'pricing request', 'pricing requests'), 'Needs a quote', n('pricing'), 'website/admin_pricing_estimates.php']);
            if (n('chats') > 0) items.push(['fa-comments', 'info', plural(n('chats'), 'unread message', 'unread messages'), 'Customer chat', n('chats'), 'website/admin_chat.php']);
            const total = items.reduce((sum, i) => sum + i[4], 0);
            document.getElementById('notifyList').innerHTML = items.length ?
                items.map(i => '<a class="site-pop-item" target="contentFrame" href="' + i[5] + '">' +
                    '<span class="site-pop-icon site-pop-icon--' + i[1] + '"><i class="fas ' + i[0] + '" aria-hidden="true"></i></span>' +
                    '<span class="site-pop-text"><strong>' + i[2] + '</strong><small>' + i[3] + '</small></span>' +
                    '<span class="site-pop-count">' + (i[4] > 99 ? '99+' : i[4]) + '</span></a>').join('') :
                '<div class="site-pop-empty">You\'re all caught up.</div>';
            document.getElementById('notifyDot').hidden = items.length === 0;
            document.getElementById('notifyBtn').setAttribute('aria-label', items.length ? 'Notifications, ' + total + ' need attention' : 'Notifications');
        }

        async function refreshBadgeCounts() {
            if (document.hidden) return;
            try {
                const response = await fetch(BADGE_COUNTS_URL, {
                    credentials: 'include'
                });
                if (!response.ok) return;
                const counts = await response.json();
                document.querySelectorAll('.site-badge[data-badge-key]').forEach(badge => {
                    const n = Number(counts[badge.dataset.badgeKey]) || 0;
                    badge.textContent = n > 99 ? '99+' : n;
                    badge.hidden = n === 0;
                });
                renderNotices(counts);
            } catch (error) {
                console.log('Badge count refresh failed');
            }
        }

        // ---------- popovers (bell, user) ----------
        function closePops(except) {
            document.querySelectorAll('[data-pop]').forEach(btn => {
                const pop = document.getElementById(btn.dataset.pop);
                if (pop && pop !== except && !pop.hidden) {
                    pop.hidden = true;
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        function initPops() {
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('[data-pop]');
                if (btn) {
                    const pop = document.getElementById(btn.dataset.pop);
                    const willOpen = pop.hidden;
                    closePops(pop);
                    pop.hidden = !willOpen;
                    btn.setAttribute('aria-expanded', String(willOpen));
                    if (willOpen && btn.dataset.pop === 'notifyPop') refreshBadgeCounts();
                    return;
                }
                if (!e.target.closest('.site-pop')) closePops();
                else if (e.target.closest('a.site-pop-item')) closePops();
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closePops();
            });
        }

        function initNavLinks() {
            // Order-status dropdown is hover-driven: close it after a pick (a sub-link OR the
            // parent link itself), reopen on next hover.
            document.querySelectorAll('.site-sublink, .has-menu > .site-link').forEach(link => {
                link.addEventListener('click', function() {
                    const item = link.closest('.site-item');
                    const menu = item.querySelector('.site-menu');
                    if (!menu) return;
                    menu.style.display = 'none';
                    item.addEventListener('mouseleave', function reset() {
                        menu.style.display = '';
                        this.removeEventListener('mouseleave', reset);
                    });
                    if (document.activeElement) document.activeElement.blur();
                });
            });
            // Highlight right away on click, before the new page has loaded.
            document.querySelectorAll('.site-link').forEach(link => {
                link.addEventListener('click', function() {
                    document.querySelectorAll('.site-link').forEach(a => a.classList.toggle('is-active', a === link));
                });
            });
        }

        function initWebsiteAdmin() {
            // Small screens only ever see the "Desktop Only" notice, so
            // don't bother loading the (heavy, desktop-only) dashboard
            // iframe in the background at all.
            if (!isDesktop()) return;
            initPops();
            initNavLinks();

            const contentFrame = frame();
            const shellDim = document.getElementById('shellDim');
            // Pages inside the iframe report modal open/close so the sidebar and
            // top bar (which they can't reach) get dimmed too.
            window.addEventListener('message', function(e) {
                if (e.origin !== window.location.origin || e.source !== contentFrame.contentWindow) return;
                const msg = e.data;
                if (!msg || msg.type !== 'wa-modal') return;
                if (msg.open) {
                    closePops();
                    shellDim.style.setProperty('--dim', String(Number(msg.dim) || 0.5));
                }
                shellDim.classList.toggle('open', !!msg.open);
                document.body.classList.toggle('shell-modal-open', !!msg.open);
            });
            // Fires after every page change inside the iframe. Pages do a normal
            // POST + redirect after actions (updating an order, a pricing request…),
            // so this also keeps the badges current right after you act.
            contentFrame.addEventListener('load', function() {
                shellDim.classList.remove('open'); // a reload (e.g. after a form post) closes any modal
                document.body.classList.remove('shell-modal-open');
                syncNavToFrame();
                refreshBadgeCounts();
                try {
                    // clicks inside the iframe don't reach this document, so close open menus
                    contentFrame.contentDocument.addEventListener('click', () => closePops());
                } catch (error) {}
            });
            contentFrame.src = restoreLastWebsiteAdminPage();

            setInterval(refreshBadgeCounts, BADGE_POLL_INTERVAL_MS);
        }

        document.addEventListener('DOMContentLoaded', initWebsiteAdmin);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && isDesktop()) refreshBadgeCounts();
        });
    </script>

</body>

</html>