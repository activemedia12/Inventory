<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    header("Location: ../accounts/login.php");
    exit;
}
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
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
            --nav-size: 52px;
            --nav-gap: 8px;
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

        /* Floating sub-nav for the website admin sections, rendered over the iframe */
        .floating-nav {
            position: fixed;
            bottom: 20px;
            left: calc(240px + (100% - 240px) / 2);
            transform: translateX(-50%);
            display: flex;
            gap: var(--nav-gap);
            align-items: center;
            padding: 8px;
            border-radius: 20px;
            background: var(--card-bg);
            border: 1px solid var(--light-gray);
            box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
            z-index: 1000;
        }

        .floating-nav button {
            position: relative;
            width: var(--nav-size);
            height: var(--nav-size);
            border-radius: 50%;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            color: var(--gray);
            cursor: pointer;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .floating-nav button:hover,
        .floating-nav button.active {
            background: var(--primary-bg);
            color: var(--secondary);
        }

        .floating-nav i {
            font-size: 17px;
        }

        /* Notification badge shown on a nav button when its page has new
           data (e.g. unread chats, pending orders/price requests). Hidden
           by default; toggled on by JS once counts come back > 0. */
        .nav-badge {
            display: none;
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            border: 2px solid var(--card-bg);
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            align-items: center;
            justify-content: center;
        }

        /* Tooltip label */
        .tooltip {
            position: absolute;
            bottom: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark);
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
        }

        .floating-nav button:hover .tooltip {
            opacity: 1;
        }

        @media (prefers-reduced-motion: reduce) {

            .floating-nav,
            .floating-nav button {
                transition: none
            }
        }

        .content-frame {
            width: calc(100% - 240px);
            height: 100vh;
            border: none;
            margin-left: 240px;
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
            .content-frame,
            .floating-nav {
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
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php" class="active"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Shown only on small screens (see @media max-width:768px) in place of
         the sidebar/iframe/floating-nav, which are desktop-only. -->
    <div class="desktop-only-notice">
        <i class="fas fa-desktop" aria-hidden="true"></i>
        <h1>Desktop Only</h1>
        <p>The Website Administrator panel isn't available on mobile. Please switch to a desktop or laptop computer to manage the website.</p>
        <a href="dashboard.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Dashboard</a>
    </div>

    <!-- Content Frame: src is set from JS (not here) so mobile visitors,
         who only ever see the notice above, never pay the cost of loading
         this desktop dashboard in the background. -->
    <iframe id="contentFrame" class="content-frame"></iframe>

    <div class="floating-nav" aria-label="Website admin sections">
        <button class="active" data-page="website/admin_dashboard.php" onclick="loadPage(this, 'website/admin_dashboard.php')" title="Dashboard" aria-label="Dashboard">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            <span class="tooltip">Dashboard</span>
        </button>

        <button data-page="website/admin_customers.php" onclick="loadPage(this, 'website/admin_customers.php')" title="Customers" aria-label="Customers">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span class="tooltip">Customers</span>
        </button>

        <button data-page="website/admin_orders.php" data-badge-key="orders" onclick="loadPage(this, 'website/admin_orders.php')" title="Orders" aria-label="Orders">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <span class="nav-badge" aria-label="pending orders"></span>
            <span class="tooltip">Orders</span>
        </button>

        <button data-page="website/admin_pricing_estimates.php" data-badge-key="pricing" onclick="loadPage(this, 'website/admin_pricing_estimates.php')" title="Price Consultation" aria-label="Price Consultation">
            <i class="fas fa-dollar-sign" aria-hidden="true"></i>
            <span class="nav-badge" aria-label="pending price requests"></span>
            <span class="tooltip">Price Consultation</span>
        </button>

        <button data-page="website/admin_products.php" onclick="loadPage(this, 'website/admin_products.php')" title="Products" aria-label="Products">
            <i class="fas fa-box" aria-hidden="true"></i>
            <span class="tooltip">Products</span>
        </button>

        <button data-page="website/admin_reports.php" onclick="loadPage(this, 'website/admin_reports.php')" title="Reports" aria-label="Reports">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            <span class="tooltip">Reports</span>
        </button>

        <button data-page="website/admin_chat.php" data-badge-key="chats" onclick="loadPage(this, 'website/admin_chat.php')" title="Chats" aria-label="Chats">
            <i class="fas fa-message" aria-hidden="true"></i>
            <span class="nav-badge" aria-label="unread chats"></span>
            <span class="tooltip">Chats</span>
        </button>
    </div>

    <script>
        const WEBSITE_ADMIN_PAGE_KEY = 'lastWebsiteAdminPage';
        const DESKTOP_BREAKPOINT = 768; // keep in sync with the CSS media query above
        const DEFAULT_PAGE = 'website/admin_dashboard.php';

        function isDesktop() {
            return window.innerWidth > DESKTOP_BREAKPOINT;
        }

        function loadPage(btn, page) {
            document.getElementById('contentFrame').src = page;
            document.querySelectorAll('.floating-nav button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            localStorage.setItem(WEBSITE_ADMIN_PAGE_KEY, page);
        }

        // ========== NOTIFICATION BADGES ==========
        const BADGE_COUNTS_URL = 'website/get_badge_counts.php';
        const BADGE_POLL_INTERVAL_MS = 30000; // keep in step with the chat heartbeat below

        function setBadge(key, count) {
            const badge = document.querySelector(`.floating-nav button[data-badge-key="${key}"] .nav-badge`);
            if (!badge) return;
            const n = Number(count) || 0;
            if (n > 0) {
                badge.textContent = n > 99 ? '99+' : n;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        async function refreshBadgeCounts() {
            try {
                const response = await fetch(BADGE_COUNTS_URL, { credentials: 'include' });
                if (!response.ok) return;
                const counts = await response.json();
                setBadge('chats', counts.chats);
                setBadge('orders', counts.orders);
                setBadge('pricing', counts.pricing);
            } catch (error) {
                console.log('Badge count refresh failed');
            }
        }

        function goToLastProductPage() {
            const last = localStorage.getItem('lastProductPage');
            window.location.href = last || 'papers.php';
        }

        function restoreLastWebsiteAdminPage() {
            const savedPage = localStorage.getItem(WEBSITE_ADMIN_PAGE_KEY);
            if (!savedPage) return DEFAULT_PAGE;

            const matchingBtn = document.querySelector(`.floating-nav button[data-page="${savedPage}"]`);
            if (!matchingBtn) return DEFAULT_PAGE; // unknown/stale value, keep the default page

            document.querySelectorAll('.floating-nav button').forEach(b => b.classList.remove('active'));
            matchingBtn.classList.add('active');
            return savedPage;
        }

        function initWebsiteAdmin() {
            // Small screens only ever see the "Desktop Only" notice, so
            // don't bother loading the (heavy, desktop-only) dashboard
            // iframe in the background at all.
            if (!isDesktop()) return;
            document.getElementById('contentFrame').src = restoreLastWebsiteAdminPage();

            // The admin_*.php pages inside the iframe do a normal
            // POST + redirect after actions like updating an order or
            // pricing-request status, which reloads the iframe's document.
            // Catching that load event (rather than only polling on a
            // timer) is what makes the badges update right after you act,
            // instead of waiting up to BADGE_POLL_INTERVAL_MS or needing a
            // full page refresh.
            document.getElementById('contentFrame').addEventListener('load', refreshBadgeCounts);

            refreshBadgeCounts();
            setInterval(refreshBadgeCounts, BADGE_POLL_INTERVAL_MS);
        }

        document.addEventListener('DOMContentLoaded', initWebsiteAdmin);

        // Also re-check as soon as the tab/window regains focus, so counts
        // don't sit stale while the admin was away.
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && isDesktop()) {
                refreshBadgeCounts();
            }
        });
    </script>

</body>

</html>