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

        @media (max-width: 768px) {
            .sidebar-con {
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                position: fixed;
            }

            .sidebar {
                position: fixed;
                overflow: hidden;
                height: auto;
                width: auto;
                bottom: 12px;
                left: 50%;
                transform: translateX(-50%);
                padding: 6px;
                background-color: rgba(255, 255, 255, 0.85);
                backdrop-filter: blur(6px);
                box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
                border-radius: 100px;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid var(--light-gray);
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
                padding: 0;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 12px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .content-frame {
                width: 100%;
                margin-left: 0;
                height: calc(100vh - 90px);
            }

            .floating-nav {
                left: 50%;
                bottom: 80px;
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

    <!-- Content Frame -->
    <iframe id="contentFrame" class="content-frame" src="website/admin_dashboard.php"></iframe>

    <div class="floating-nav" aria-label="Website admin sections">
        <button class="active" data-page="website/admin_dashboard.php" onclick="loadPage(this, 'website/admin_dashboard.php')" title="Dashboard" aria-label="Dashboard">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            <span class="tooltip">Dashboard</span>
        </button>

        <button data-page="website/admin_customers.php" onclick="loadPage(this, 'website/admin_customers.php')" title="Customers" aria-label="Customers">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span class="tooltip">Customers</span>
        </button>

        <button data-page="website/admin_orders.php" onclick="loadPage(this, 'website/admin_orders.php')" title="Orders" aria-label="Orders">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <span class="tooltip">Orders</span>
        </button>

        <button data-page="website/admin_pricing_estimates.php" onclick="loadPage(this, 'website/admin_pricing_estimates.php')" title="Price Consultation" aria-label="Price Consultation">
            <i class="fas fa-dollar-sign" aria-hidden="true"></i>
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

        <button data-page="website/admin_chat.php" onclick="loadPage(this, 'website/admin_chat.php')" title="Chats" aria-label="Chats">
            <i class="fas fa-message" aria-hidden="true"></i>
            <span class="tooltip">Chats</span>
        </button>
    </div>

    <script>
        const WEBSITE_ADMIN_PAGE_KEY = 'lastWebsiteAdminPage';

        function loadPage(btn, page) {
            document.getElementById('contentFrame').src = page;
            document.querySelectorAll('.floating-nav button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            localStorage.setItem(WEBSITE_ADMIN_PAGE_KEY, page);
        }

        function goToLastProductPage() {
            const last = localStorage.getItem('lastProductPage');
            window.location.href = last || 'papers.php';
        }

        function restoreLastWebsiteAdminPage() {
            const savedPage = localStorage.getItem(WEBSITE_ADMIN_PAGE_KEY);
            if (!savedPage) return;

            const matchingBtn = document.querySelector(`.floating-nav button[data-page="${savedPage}"]`);
            if (!matchingBtn) return; // unknown/stale value, keep the default page

            document.getElementById('contentFrame').src = savedPage;
            document.querySelectorAll('.floating-nav button').forEach(b => b.classList.remove('active'));
            matchingBtn.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', restoreLastWebsiteAdminPage);
    </script>

</body>

</html>