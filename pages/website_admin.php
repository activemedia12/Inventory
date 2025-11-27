<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>Website Administrator</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --secondary: #166fe5;
            --light: #f0f2f5;
            --dark: #1c1e21;
            --gray: #65676b;
            --light-gray: #e4e6eb;
            --card-bg: #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.06);
            --glass-border: rgba(255, 255, 255, 0.12);
            --accent: rgba(255, 255, 255, 0.6);
            --nav-size: 64px;
            --nav-gap: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }

        .brand {
            padding: 0 20px 40px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 20px;
        }

        .brand img {
            height: 100px;
            width: auto;
            padding-left: 40px;
            transform: rotate(45deg);
        }

        .brand h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .nav-menu li a:hover,
        .nav-menu li a.active {
            background-color: var(--light-gray);
        }

        .nav-menu li a i {
            margin-right: 10px;
            color: var(--gray);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .user-details h4 {
            font-weight: 500;
            font-size: 16px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 14px;
        }

        /* Liquid glass floating nav container */
        .floating-nav {
            position: fixed;
            bottom: 20px;
            left: 30%;
            margin-left: 250px;
            display: flex;
            gap: var(--nav-gap);
            align-items: center;
            padding: 12px;
            border-radius: 36px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.04), rgba(255, 255, 255, 0.02));
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(5px) saturate(120%);
            box-shadow: 0 4px 7px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.02);
            transition: transform .25s ease, box-shadow .25s ease;
            z-index: 1000;
            border: 1px solid #1c1c1c1a;
        }


        /* Slight lift on hover of the whole nav */
        .floating-nav:hover {
            transform: translateY(-6px);
            box-shadow: 0 5px 8px rgba(0, 0, 0, 0.2);
        }

        .floating-nav::before {
            /* soft sheen */
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            border-radius: 36px;
            pointer-events: none;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 20%, rgba(255, 255, 255, 0) 60%);
            mix-blend-mode: screen;
        }


        /* individual round buttons */
        .floating-nav button {
            position: relative;
            width: var(--nav-size);
            height: var(--nav-size);
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.01));
            box-shadow: 0 4px 7px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.02);
            color: var(--accent);
            cursor: pointer;
            transition: transform .18s cubic-bezier(.2, .9, .3, 1), box-shadow .18s;
            outline: none;
            border: 1px solid #1c1c1c1a;
            outline: none;
        }


        .floating-nav button:active {
            transform: translateY(2px) scale(.995);
        }

        .floating-nav button:hover {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 5px 8px rgba(0, 0, 0, 0.2);
        }


        /* Icon styling */
        .floating-nav i {
            font-size: 20px;
            filter: drop-shadow(0 1px 0 rgba(255, 255, 255, 0.03));
            color: #1c1c1c;
        }


        /* Tooltip label */
        .tooltip {
            position: absolute;
            bottom: 70px;
            background: rgba(2, 6, 23, 0.7);
            color: #dbeafe;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 13px;
            white-space: nowrap;
            transform-origin: right center;
            opacity: 0;
            pointer-events: none;
            transition: all .16s ease;
            border: 1px solid rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(8px);
        }

        .floating-nav button:hover .tooltip {
            opacity: 1;
        }

        /* Respect reduce motion */
        @media (prefers-reduced-motion: reduce) {

            .floating-nav,
            .floating-nav button {
                transition: none
            }
        }


        /* Nice focus ring for accessibility */
        .floating-nav button:focus {
            transform: translateY(-6px) scale(1.03);
            box-shadow: 0 5px 8px rgba(0, 0, 0, 0.2);
        }


        .content-frame {
            width: 100%;
            height: 100vh;
            border: none;
            margin-left: 250px;
        }
    </style>
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="">
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
    
    <div class="floating-nav" aria-label="Floating navigation">
        <button onclick="loadPage('website/admin_dashboard.php')" title="Dashboard" aria-label="Dashboard">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            <span class="tooltip">Dashboard</span>
        </button>

        <button onclick="loadPage('website/admin_customers.php')" title="Customers" aria-label="Customers">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span class="tooltip">Customers</span>
        </button>

        <button onclick="loadPage('website/admin_orders.php')" title="Orders" aria-label="Orders">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
            <span class="tooltip">Orders</span>
        </button>

        <button onclick="loadPage('website/admin_pricing_estimates.php')" title="Price Consultation" aria-label="Price Consultation">
            <i class="fas fa-dollar-sign" aria-hidden="true"></i>
            <span class="tooltip">Price Consultation</span>
        </button>

        <button onclick="loadPage('website/admin_products.php')" title="Products" aria-label="Products">
            <i class="fas fa-box" aria-hidden="true"></i>
            <span class="tooltip">Products</span>
        </button>

        <button onclick="loadPage('website/admin_reports.php')" title="Reports" aria-label="Reports">
            <i class="fas fa-chart-bar" aria-hidden="true"></i>
            <span class="tooltip">Reports</span>
        </button>
    </div>

    <!-- Content Frame -->
    <iframe id="contentFrame" class="content-frame" src="website/admin_dashboard.php"></iframe>

    <script>
        function loadPage(page) {
            document.getElementById('contentFrame').src = page;
        }
    </script>

</body>

</html>