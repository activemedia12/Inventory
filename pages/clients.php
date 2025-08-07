<?php
// =============================
// 1. clients.php
// =============================
session_start();
require_once '../config/db.php';

$search = $_GET['search_client'] ?? '';
$clients = [];

if (!empty($search)) {
    $stmt = $mysqli->prepare("SELECT * FROM clients WHERE client_name LIKE ? ORDER BY client_name ASC");
    $like = '%' . $search . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $mysqli->query("SELECT * FROM clients ORDER BY client_name ASC");
}

while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
}

// Fetch all saved clients
// $result = $mysqli->query("SELECT * FROM clients ORDER BY created_at DESC");
// $clients = $result->fetch_all(MYSQLI_ASSOC);
$provinces = [];
$result = $mysqli->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $result->fetch_assoc()) {
    $provinces[] = $row['province'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Client Information</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --primary-light: #eef2ff;
            --secondary: #166fe5;
            --light: #f0f2f5;
            --dark: #1c1e21;
            --gray: #65676b;
            --light-gray: #e4e6eb;
            --card-bg: #ffffff;
            --success: #42b72a;
            --danger: #ff4d4f;
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
            overflow: auto;
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

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 18px;
        }

        .alert-success {
            background-color: #e6f4ea;
            border: 1px solid #b8e0c2;
            color: #276738;
        }

        .alert-danger {
            background-color: #fdecea;
            border: 1px solid #f5c6cb;
            color: #a92828;
        }

        .alert-warning {
            background-color: #fff8e1;
            border: 1px solid #ffecb5;
            color: #8c6d1f;
        }

        .alert-info {
            background-color: #e7f3fe;
            border: 1px solid #bee3f8;
            color: #0b5394;
        }


        /* Forms */
        .card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .card h3 {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            color: var(--dark);
        }

        .card h3 i {
            margin-right: 10px;
            color: var(--primary);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .vat-group label {
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
            margin-right: 25px;
        }

        .vatlabels {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
        }

        .vat-group {
            display: flex;
            flex-direction: column;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            min-height: 100px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background-color: var(--secondary);
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        /* Job Orders List */
        .client-block {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .client-block:last-child {
            border-bottom: none;
        }

        .client-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .date-group {
            margin-left: 15px;
            margin-bottom: 20px;
        }

        .date-header {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.2s;
            font-weight: 500;
        }

        .date-header:hover {
            background: rgba(24, 119, 242, 0.05);
        }

        .date-header i {
            margin-right: 10px;
            color: var(--primary);
            transition: transform 0.2s;
        }

        .date-header.collapsed i {
            transform: rotate(-90deg);
        }

        .project-group {
            margin-left: 20px;
            margin-top: 10px;
            display: none;
        }

        .date-header:not(.collapsed)+.project-group {
            display: block;
        }

        .project-header {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: background 0.2s;
            font-weight: 500;
        }

        .project-header:hover {
            background: rgba(24, 119, 242, 0.05);
        }

        .project-header i {
            margin-right: 10px;
            color: var(--success);
        }

        .order-details {
            margin-left: 25px;
            margin-top: 10px;
            display: none;
            background: rgba(24, 119, 242, 0.03);
            border-radius: 8px;
            padding: 15px;
        }

        .project-header:not(.collapsed)+.order-details {
            display: block;
        }

        .order-item {
            margin-bottom: 15px;
        }

        .order-item p {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .order-item strong {
            color: var(--gray);
            font-weight: 500;
        }

        /* Empty State */
        .empty-message {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .hidden {
            display: none;
        }

        /* Compressed Job Orders List */
        .compact-orders {
            max-height: 600px;
            overflow-y: auto;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            padding: 10px;
        }

        .compact-client {
            margin-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 10px;
        }

        .compact-client:last-child {
            border-bottom: none;
        }

        .compact-client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: 0.3s;
            background: rgba(24, 119, 242, 0.05);
        }

        .compact-client-header:hover {
            background: rgba(24, 119, 242, 0.1);
        }

        .compact-client-name {
            font-weight: 500;
            color: var(--dark);
        }

        .compact-client-count {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        .compact-date-group {
            margin-left: 15px;
            display: none;
        }

        .compact-date-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 6px 8px;
            margin-top: 5px;
            border-radius: 4px;
            transition: 0.3s;
        }

        .compact-date-header:hover {
            background: rgba(24, 119, 242, 0.05);
        }

        .compact-date-text {
            font-size: 14px;
            color: var(--dark);
        }

        .compact-project-group {
            margin-left: 15px;
            display: none;
        }

        .compact-project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 6px 8px;
            margin-top: 5px;
            font-size: 13px;
            color: var(--gray);
        }

        .compact-project-header:hover {
            text-decoration: underline;
        }

        .compact-order-item {
            margin-left: 15px;
            padding: 8px;
            background: rgba(24, 119, 242, 0.03);
            border-radius: 6px;
            margin-top: 5px;
            font-size: 13px;
        }

        .compact-order-item p {
            margin: 4px 0;
        }

        /* Collapsible Form */
        .collapsible-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            padding: 12px 15px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            margin-bottom: 0;
        }

        .collapsible-form-header:hover {
            background: var(--secondary);
        }

        .collapsible-form-content {
            display: none;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-top: none;
            border-radius: 0 0 8px 8px;
        }

        /* Small status indicators */
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-active {
            background: var(--success);
        }

        .status-completed {
            background: var(--primary);
        }

        .status-pending {
            background: var(--danger);
        }

        /* Order Details Table */
        .order-details-table-container {
            overflow-x: scroll;
            margin-top: 10px;
            border: 1px solid rgb(0, 0, 0, 0.05);
            border-radius: 8px;
        }

        .order-details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .order-details-table th,
        .order-details-table td {
            transition: 0.3s;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid rgb(0, 0, 0, 0.05);
            vertical-align: top;
        }

        .order-details-table th {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--dark);
            font-weight: 500;
            white-space: nowrap;
        }

        .order-details-table tr:hover td {
            background-color: rgba(0, 0, 0, 0.03);
            cursor: pointer;
        }

        .sequence-item {
            display: inline-block;
            padding: 2px 6px;
            background: #0060b41a;
            border-radius: 4px;
            margin: 2px;
            font-size: 12px;
        }

        fieldset {
            border: 0;
        }

        .action-cell {
            display: flex;
            flex-direction: row;
            align-items: center;
        }

        .action-cell a {
            color: var(--gray);
            margin-right: 10px;
            transition: color 0.3s;
        }

        .action-cell a:hover {
            color: var(--primary);
        }

        legend {
            font-size: 120%;
        }

        input::placeholder {
            opacity: 0.3;
        }

        #jobModal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(3px);
            z-index: 999;
            display: none;
        }

        @keyframes centerZoomIn {
            0% {
                transform: translate(-50%, -50%) scale(0.5);
                opacity: 0;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        .floating-window {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: centerZoomIn 0.3s ease-in-out forwards;
        }

        .window-header {
            padding: 0.5rem 1.5rem;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .window-title {
            display: flex;
            align-items: center;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .window-title i {
            margin-right: 0.8rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        .window-content {
            padding: 1.5rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        /* Product Info Compact Grid */
        .product-info-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .info-item-compact {
            margin-bottom: 0.5rem;
        }

        .info-item-compact strong {
            display: block;
            color: var(--gray);
            font-size: 100%;
            margin-bottom: 0.2rem;
        }

        .info-item-compact span {
            font-size: 85%;
        }

        /* Stock Summary Cards */
        .stock-summary-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stock-card-compact {
            padding: 0.8rem;
            border-radius: 8px;
            background: rgba(67, 97, 238, 0.05);
            text-align: center;
        }

        .stock-card-compact h4 {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stock-value-compact {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .stock-unit-compact {
            color: var(--gray);
            font-size: 0.75rem;
        }

        /* Section Headers */
        .section-header {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary);
            margin: 1.5rem 0 0.5rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }

        .section-header i {
            margin-right: 0.5rem;
        }

        /* Special Instructions */
        .special-instructions {
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            margin-top: 1rem;
        }

        .status-toggle-form {
            display: flex;
        }

        .btn-status {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            background: rgba(67, 238, 76, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            margin: 6px 6px;
            gap: 6px;
        }

        .btn-edit,
        .btn-delete {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            margin: 6px 6px;
        }

        .btn-edit {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-delete {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 1px solid #f44336;
        }

        .btn-status:hover {
            background: rgba(40, 167, 69, 0.2);
        }

        .btn-status.pending:hover {
            background: rgba(255, 152, 0, 0.2);
        }

        .btn-status.completed:hover {
            background: rgba(40, 167, 69, 0.2);
        }

        .btn-edit:hover {
            background: rgba(67, 97, 238, 0.2);
        }

        .btn-delete:hover {
            background: rgba(244, 67, 54, 0.2);
        }

        /* Empty State */
        .empty-state {
            padding: 1rem;
            text-align: center;
            color: var(--gray);
            background: #f9f9f9;
            border-radius: 8px;
        }

        .empty-state i {
            margin-right: 0.5rem;
        }

        /* Form Elements */
        .status-form {
            display: inline;
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
                bottom: 20px;
                padding: 0;
                background-color: rgba(255, 255, 255, 0.3);
                backdrop-filter: blur(2px);
                box-shadow: 1px 1px 10px rgb(190, 190, 190);
                border-radius: 100px;
                cursor: grab;
                transition: left 0.05s ease-in, top 0.05s ease-in;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid white;
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 15px;
            }

            .sidebar .nav-menu li a i {
                margin-right: 0;
            }

            .main-content {
                margin-left: 0;
                margin-bottom: 200px;
            }

            .job-info-grid {
                grid-template-columns: 1fr 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }

            .floating-window {
                width: 95%;
            }

            .product-info-compact {
                grid-template-columns: 1fr 1fr;
            }

            .stock-summary-compact {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .status-toggle-form {
                flex-direction: column;
            }

            .btn-status,
            .btn-edit,
            .btn-delete,
            .status-select {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                margin-top: 10px;
            }

            .compact-client-count {
                font-size: 10px;
                min-width: 50.5px;
            }

            .order-details-table {
                font-size: 10px;
            }

            .sequence-item {
                font-size: 10px;
                text-align: center;
            }

            .search input {
                min-width: 60%;
            }

            .cjo span {
                display: none;
            }

            .btn .eyo {
                margin-right: 0;
            }
        }

        .disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #1c1c1c27;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 600;
            margin-right: 0.75rem;
        }

        .quick-fill-btn {
            color: black;
            height: 100%;
            width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background-color: rgba(0, 0, 0, 0.05);
            border: 2px solid rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: 0.3s;
            padding: 5px;
            margin-bottom: 5px;
        }

        .quick-fill-btn:hover {
            background-color: rgba(0, 0, 0, 0.2);
        }

        .status-select:focus {
            outline: none;
        }

        .status-select {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            background: rgba(67, 97, 238, 0.1);
            color: white;
            border: 1px solid var(--primary);
            display: inline-flex;
            text-align: center;
            gap: 0.5rem;
            transition: all 0.2s;
            margin: 6px 6px;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border-color: #ff9800;
        }

        .status-unpaid {
            background: rgba(255, 0, 0, 0.1);
            color: #ff0000ff;
            border-color: #ff0000ff;
        }

        .status-for_delivery {
            background: rgba(0, 38, 255, 0.1);
            color: var(--primary);
            border-color: var(--primary);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-color: #28a745;
        }

        .client-card {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            font-family: 'Segoe UI', Roboto, sans-serif;
            margin-bottom: 20px;
            width: auto;
        }

        .card-header {
            background-color: var(--primary);
            color: white;
            padding: 15px 20px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 10px 20px;
        }

        .client-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .client-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            transition: background 0.2s;
            background-color: #1c1c1c09;
            margin-bottom: 10px;
            border-radius: 6px;
            cursor: pointer;
        }

        .client-item:last-child {
            border-bottom: none;
        }

        .client-item:hover {
            background: #00000020;
        }

        .client-name {
            font-weight: 500;
            color: #333;
        }

        .empty-state {
            padding: 30px 20px;
            text-align: center;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .empty-state p {
            margin: 0;
        }

        .search {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: start;
        }

        .search input {
            min-height: 40px;
            width: 40%;
            margin: 10px 0;
            padding: 10px 12px;
            border: 1px solid var(--primary);
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .search input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Modal Structure */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(3px);
        }

        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1000px;
            max-height: 80vh;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: centerZoomIn 0.3s ease-in-out forwards;
        }

        /* Header */
        .modal-header {
            padding: 0.5rem 1.5rem;
            background: var(--primary);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.2s;
        }

        .close-btn:hover {
            opacity: 0.8;
        }

        /* Body */
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        /* Client Details Grid */
        .client-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 600px) {
            .client-details-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-group {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-label i {
            width: 20px;
            color: var(--primary);
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 2px;
            padding-left: 28px;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #eee;
            margin: 1.5rem 0;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 1.5rem;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 0.8rem;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Recent Orders */
        .recent-orders h4 {
            font-size: 1.1rem;
            margin: 0 0 0.8rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
        }

        .order-list {
            list-style: none;
            padding: 0;
            margin: 0;
            border: 1px solid #eee;
            border-radius: 6px;
        }

        .order-list li {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        .order-list li:last-child {
            border-bottom: none;
        }

        /* Footer Buttons */
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f5f5f5;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
                <li><a href="clients.php" class="active"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <header class="header">
            <h1>Client Information</h1>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                    <small><?= $_SESSION['role'] ?></small>
                </div>
            </div>
        </header>

        <div class="card">
            <form id="clientForm" action="save_client.php" method="post">
                <fieldset class="form-section">
                    <legend><i class="fa-solid fa-user-plus"></i> Add Client</legend>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="client_name">Company / Trade Name *</label>
                            <input type="text" id="client_name" name="client_name" required>
                        </div>
                        <div class="form-group">
                            <label for="taxpayer_name">Taxpayer Name *</label>
                            <input type="text" id="taxpayer_name" name="taxpayer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="tin">TIN</label>
                            <input type="text" name="tin" id="tin" class="form-control" placeholder="e.g. 123-456-789-0000">
                        </div>
                        <div class="vat-group">
                            <label>Tax Type *</label>
                            <div class="vatlabels">
                                <label><input type="radio" name="tax_type" value="VAT" required> VAT</label>
                                <label><input type="radio" name="tax_type" value="NONVAT"> NONVAT</label>
                                <label><input type="radio" name="tax_type" value="VAT-EXEMPT"> VAT-EXEMPT</label>
                                <label><input type="radio" name="tax_type" value="NON-VAT EXEMPT"> NON-VAT EXEMPT</label>
                                <label><input type="radio" name="tax_type" value="EXEMPT"> EXEMPT</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="rdo_code">BIR RDO Code</label>
                            <input list="rdo_list" id="rdo_code" name="rdo_code" placeholder="Enter or select RDO code">
                            <datalist id="rdo_list">
                                <option value="001 - Laoag City, Ilocos Norte">
                                <option value="002 - Vigan, Ilocos Sur">
                                <option value="003 - San Fernando, La Union">
                                <option value="004 - Calasiao, West Pangasinan">
                                <option value="005 - Alaminos, Pangasinan">
                                <option value="006 - Urdaneta, Pangasinan">
                                <option value="007 - Bangued, Abra">
                                <option value="008 - Baguio City">
                                <option value="009 - La Trinidad, Benguet">
                                <option value="010 - Bontoc, Mt. Province">
                                <option value="011 - Tabuk City, Kalinga">
                                <option value="012 - Lagawe, Ifugao">
                                <option value="013 - Tuguegarao, Cagayan">
                                <option value="014 - Bayombong, Nueva Vizcaya">
                                <option value="015 - Naguilian, Isabela">
                                <option value="016 - Cabarroguis, Quirino">
                                <option value="17A - Tarlac City, Tarlac">
                                <option value="17B - Paniqui, Tarlac">
                                <option value="018 - Olongapo City">
                                <option value="019 - Subic Bay Freeport Zone">
                                <option value="020 - Balanga, Bataan">
                                <option value="21A - North Pampanga">
                                <option value="21B - South Pampanga">
                                <option value="21C - Clark Freeport Zone">
                                <option value="022 - Baler, Aurora">
                                <option value="23A - North Nueva Ecija">
                                <option value="23B - South Nueva Ecija">
                                <option value="024 - Valenzuela City">
                                <option value="25A - Plaridel, Bulacan (now RDO West Bulacan)">
                                <option value="25B - Sta. Maria, Bulacan (now RDO East Bulacan)">
                                <option value="026 - Malabon-Navotas">
                                <option value="027 - Caloocan City">
                                <option value="028 - Novaliches">
                                <option value="029 - Tondo – San Nicolas">
                                <option value="030 - Binondo">
                                <option value="031 - Sta. Cruz">
                                <option value="032 - Quiapo-Sampaloc-San Miguel-Sta. Mesa">
                                <option value="033 - Intramuros-Ermita-Malate">
                                <option value="034 - Paco-Pandacan-Sta. Ana-San Andres">
                                <option value="035 - Romblon">
                                <option value="036 - Puerto Princesa">
                                <option value="037 - San Jose, Occidental Mindoro">
                                <option value="038 - North Quezon City">
                                <option value="039 - South Quezon City">
                                <option value="040 - Cubao">
                                <option value="041 - Mandaluyong City">
                                <option value="042 - San Juan">
                                <option value="043 - Pasig">
                                <option value="044 - Taguig-Pateros">
                                <option value="045 - Marikina">
                                <option value="046 - Cainta-Taytay">
                                <option value="047 - East Makati">
                                <option value="048 - West Makati">
                                <option value="049 - North Makati">
                                <option value="050 - South Makati">
                                <option value="051 - Pasay City">
                                <option value="052 - Parañaque">
                                <option value="53A - Las Piñas City">
                                <option value="53B - Muntinlupa City">
                                <option value="54A - Trece Martirez City, East Cavite">
                                <option value="54B - Kawit, West Cavite">
                                <option value="055 - San Pablo City">
                                <option value="056 - Calamba, Laguna">
                                <option value="057 - Biñan, Laguna">
                                <option value="058 - Batangas City">
                                <option value="059 - Lipa City">
                                <option value="060 - Lucena City">
                                <option value="061 - Gumaca, Quezon">
                                <option value="062 - Boac, Marinduque">
                                <option value="063 - Calapan, Oriental Mindoro">
                                <option value="064 - Talisay, Camarines Norte">
                                <option value="065 - Naga City">
                                <option value="066 - Iriga City">
                                <option value="067 - Legazpi City, Albay">
                                <option value="068 - Sorsogon, Sorsogon">
                                <option value="069 - Virac, Catanduanes">
                                <option value="070 - Masbate, Masbate">
                                <option value="071 - Kalibo, Aklan">
                                <option value="072 - Roxas City">
                                <option value="073 - San Jose, Antique">
                                <option value="074 - Iloilo City">
                                <option value="075 - Zarraga, Iloilo City">
                                <option value="076 - Victorias City, Negros Occidental">
                                <option value="077 - Bacolod City">
                                <option value="078 - Binalbagan, Negros Occidental">
                                <option value="079 - Dumaguete City">
                                <option value="080 - Mandaue City">
                                <option value="081 - Cebu City North">
                                <option value="082 - Cebu City South">
                                <option value="083 - Talisay City, Cebu">
                                <option value="084 - Tagbilaran City">
                                <option value="085 - Catarman, Northern Samar">
                                <option value="086 - Borongan, Eastern Samar">
                                <option value="087 - Calbayog City, Samar">
                                <option value="088 - Tacloban City">
                                <option value="089 - Ormoc City">
                                <option value="090 - Maasin, Southern Leyte">
                                <option value="091 - Dipolog City">
                                <option value="092 - Pagadian City, Zamboanga del Sur">
                                <option value="093A - Zamboanga City, Zamboanga del Sur">
                                <option value="093B - Ipil, Zamboanga Sibugay">
                                <option value="094 - Isabela, Basilan">
                                <option value="095 - Jolo, Sulu">
                                <option value="096 - Bongao, Tawi-Tawi">
                                <option value="097 - Gingoog City">
                                <option value="098 - Cagayan de Oro City">
                                <option value="099 - Malaybalay City, Bukidnon">
                                <option value="100 - Ozamis City">
                                <option value="101 - Iligan City">
                                <option value="102 - Marawi City">
                                <option value="103 - Butuan City">
                                <option value="104 - Bayugan City, Agusan del Sur">
                                <option value="105 - Surigao City">
                                <option value="106 - Tandag, Surigao del Sur">
                                <option value="107 - Cotabato City">
                                <option value="108 - Kidapawan, North Cotabato">
                                <option value="109 - Tacurong, Sultan Kudarat">
                                <option value="110 - General Santos City">
                                <option value="111 - Koronadal City, South Cotabato">
                                <option value="112 - Tagum, Davao del Norte">
                                <option value="113A - West Davao City">
                                <option value="113B - East Davao City">
                                <option value="114 - Mati, Davao Oriental">
                                <option value="115 - Digos, Davao del Sur">
                            </datalist>
                        </div>
                        <input type="hidden" name="client_address" id="client_address" oninput="suggestRDO()" required>
                        <div class="form-group">
                            <label for="province">Province *</label>
                            <select id="province" name="province" required>
                                <option value="">Select Province</option>
                                <?php foreach ($provinces as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">City / Municipality *</label>
                            <select id="city" name="city" required>
                                <option value="">Select City</option>
                            </select>
                        </div>
                        <div class="form-group" style="position: relative;">
                            <label for="barangay">Barangay</label>
                            <span style="
                            position: absolute;
                            top: 70%;
                            left: 12px;
                            transform: translateY(-50%);
                            color: #6c757d;
                            pointer-events: none;
                            font-size: 14px;
                            ">
                                Brgy.
                            </span>
                            <input type="text"
                                id="barangay"
                                name="barangay"
                                class="form-control"
                                placeholder="e.g. San Isidro"
                                style="padding-left: 60px;" pattern="[^,]*" title="Commas are not allowed" />
                        </div>
                        <div class="form-group">
                            <label for="street">Subdivision / Street</label>
                            <input type="text" id="street" name="street" placeholder="e.g. Rizal St." pattern="[^,]*" title="Commas are not allowed">
                        </div>
                        <div class="form-group">
                            <label for="building_no">Building / House No.</label>
                            <input type="text" id="building_no" name="building_no" placeholder="e.g. Bldg 4, Lot 6" pattern="[^,]*" title="Commas are not allowed">
                        </div>
                        <div class="form-group">
                            <label for="floor_no">Floor / Room No.</label>
                            <input type="text" id="floor_no" name="floor_no" placeholder="e.g. 2F, Room 201" pattern="[^,]*" title="Commas are not allowed">
                        </div>
                        <div class="form-group">
                            <label for="zip_code">ZIP Code</label>
                            <input type="text" id="zip_code" name="zip_code" placeholder="e.g. 3020" pattern="[^,]*" title="Commas are not allowed">
                        </div>
                        <div class="form-group">
                            <label for="contact_person">Contact Person *</label>
                            <input type="text" id="contact_person" name="contact_person" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number *</label>
                            <input type="text" id="contact_number" name="contact_number" required>
                        </div>
                        <div class="form-group">
                            <label for="client_by">Client By *</label>
                            <input type="text" name="client_by" id="client_by" class="form-control" required>
                        </div>
                    </div>
                </fieldset>
                <button type="submit" class="btn"><i class="fas fa-save"></i>Save Client</button>
            </form>
        </div>

        <div class="client-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Saved Clients</h3>
            </div>
            <div class="card-body">
                <div class="search">
                    <input type="text" id="clientSearchInput" placeholder="Search clients..." class="form-control">
                </div>
                <ul class="client-list">
                    <?php foreach ($clients as $client): ?>
                        <li class="client-item" data-client='<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>'>
                            <div class="client-info">
                                <span class="client-name"><?= htmlspecialchars($client['client_name']) ?></span>
                            </div>
                            <a href="job_orders.php?client_id=<?= $client['id'] ?>" class="btn cjo">
                                <i class="fas fa-file-alt eyo"></i><span>Create Job Order</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (count($clients) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No saved clients found</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="clientModal" class="modal" style="display: none;">
            <div class="modal-overlay">
                <div class="modal-container animate__animated animate__fadeInUp">
                    <div class="modal-header">
                        <h3>
                            <i class="fas fa-building"></i>
                            <span id="modalClientName"></span>
                        </h3>
                        <span id="clientModal" class="close-btn">&times;</span>
                    </div>

                    <div class="modal-body">
                        <div class="client-details-grid">
                            <!-- Column 1 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-id-card"></i> Taxpayer</span>
                                    <span id="modalTaxpayer" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-hashtag"></i> TIN</span>
                                    <span id="modalTIN" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marker-alt"></i> RDO Code</span>
                                    <span id="modalRDO" class="detail-value"></span>
                                </div>
                            </div>

                            <!-- Column 2 -->
                            <div class="detail-group">
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-phone"></i> Contact</span>
                                    <span id="modalContact" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-user-plus"></i> Client By</span>
                                    <span id="modalClientBy" class="detail-value"></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label"><i class="fas fa-map-marked-alt"></i> Address</span>
                                    <span id="modalAddress" class="detail-value"></span>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <div class="stats-section">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label">Total Job Orders</span>
                                    <span id="modalTotalOrders" class="stat-value">...</span>
                                </div>
                            </div>
                        </div>

                        <div class="recent-orders">
                            <h4><i class="fas fa-clock"></i> Recent Orders</h4>
                            <ul id="modalRecentOrders" class="order-list">
                                <!-- Dynamically populated -->
                            </ul>
                        </div>
                    </div>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div class="modal-footer">
                        <button id="editClientBtn" class="btn btn-edit">
                            <i class="fas fa-edit"></i> Edit Client
                        </button>
                        <button id="deleteClientBtn" class="btn btn-delete">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            function goToLastProductPage() {
                const last = localStorage.getItem('lastProductPage');
                if (last) {
                    window.location.href = last;
                } else {
                    window.location.href = 'papers.php'; // fallback
                }
            }

            document.querySelectorAll('.client-item').forEach(item => {
                item.addEventListener('click', () => {
                    const client = JSON.parse(item.dataset.client);
                    
                    // Populate modal fields (matches new HTML structure)
                    document.getElementById('modalClientName').textContent = client.client_name;
                    document.getElementById('modalTaxpayer').textContent = client.taxpayer_name || '-';
                    document.getElementById('modalTIN').textContent = client.tin || '-';
                    document.getElementById('modalRDO').textContent = client.rdo_code || '-';
                    document.getElementById('modalContact').textContent = `${client.contact_person || ''} ${client.contact_number ? `(${client.contact_number})` : ''}`.trim() || '-';
                    document.getElementById('modalClientBy').textContent = client.client_by || '-';
                    document.getElementById('modalAddress').textContent = client.client_address || '-';

                    // Set up edit button (now matches class "btn-edit")
                    const editBtn = document.getElementById('editClientBtn');
                    if (editBtn) {
                        editBtn.onclick = (e) => {
                            e.preventDefault();
                            window.location.href = `edit_client.php?id=${client.id}`;
                        };
                        
                        // Alternative: If using <a> tag
                        editBtn.href = `edit_client.php?id=${client.id}`;
                    }

                    // Set up delete button (now matches class "btn-delete")
                    const deleteBtn = document.getElementById('deleteClientBtn');
                    if (deleteBtn) {
                        deleteBtn.onclick = () => {
                            if (confirm('Are you sure you want to delete this client?')) {
                                fetch(`delete_client.php?id=${client.id}`, {
                                    method: 'POST'
                                })
                                .then(res => res.json())
                                .then(data => {
                                    alert(data.message || 'Client deleted successfully');
                                    location.reload();
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('Failed to delete client');
                                });
                            }
                        };
                    }

                    // Fetch order data (updated to match new list structure)
                    fetch(`get_client_orders.php?client_id=${client.id}`)
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('modalTotalOrders').textContent = data.total_orders || '0';
                            
                            const ordersList = document.getElementById('modalRecentOrders');
                            ordersList.innerHTML = '';
                            
                            if (!data.recent_orders || data.recent_orders.length === 0) {
                                ordersList.innerHTML = '<li class="no-orders">No recent orders found</li>';
                            } else {
                                data.recent_orders.forEach(order => {
                                    const li = document.createElement('li');
                                    li.innerHTML = `
                                        <span class="order-project">${order.project_name || 'Untitled Project'}</span>
                                        <span class="order-date">${formatDate(order.log_date)}</span>
                                    `;
                                    ordersList.appendChild(li);
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching orders:', error);
                            document.getElementById('modalRecentOrders').innerHTML = 
                                '<li class="error">Failed to load order history</li>';
                        });

                    // Show modal (matches new modal structure)
                    document.getElementById('clientModal').style.display = 'flex';
                });
            });

            // Close modal (updated for new close button class)
            document.querySelector('#clientModal .close-btn').onclick = () => {
                document.getElementById('clientModal').style.display = 'none';
            };

            // Close when clicking overlay
            document.querySelector('.modal-overlay').addEventListener('click', () => {
                document.getElementById('clientModal').style.display = 'none';
            });

            // Helper function to format dates
            function formatDate(dateString) {
                if (!dateString) return '';
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                return new Date(dateString).toLocaleDateString(undefined, options);
            }

            document.getElementById('clientSearchInput').addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const items = document.querySelectorAll('.client-item');

                items.forEach(item => {
                    const name = item.querySelector('.client-name').textContent.toLowerCase();
                    item.style.display = name.includes(query) ? 'flex' : 'none';
                });

                const hasVisible = Array.from(items).some(item => item.style.display !== 'none');
                document.querySelector('.empty-state').style.display = hasVisible ? 'none' : 'block';
            });

            document.addEventListener('DOMContentLoaded', () => {
                // Block commas from being typed or pasted
                document.querySelectorAll('#clientForm input[type="text"], #clientForm textarea').forEach(input => {
                    input.addEventListener('keydown', e => {
                        if (e.key === ',') e.preventDefault();
                    });
                    input.addEventListener('input', () => {
                        input.value = input.value.replace(/,/g, '');
                    });
                });

                // Update address when any field changes
                ["floor_no", "building_no", "street", "barangay", "city", "province", "zip_code"].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.addEventListener("input", updateClientAddress);
                });

                // Province → City dropdown
                const province = document.getElementById("province");
                const city = document.getElementById("city");

                if (province && city) {
                    province.addEventListener("change", function() {
                        const selectedProvince = this.value;
                        city.innerHTML = '<option value="">Select City</option>';
                        updateClientAddress();

                        if (!selectedProvince) return;

                        fetch(`get_cities.php?province=${encodeURIComponent(selectedProvince)}`)
                            .then(res => res.json())
                            .then(cities => {
                                cities.forEach(cityName => {
                                    const option = document.createElement("option");
                                    option.value = cityName;
                                    option.textContent = cityName;
                                    city.appendChild(option);
                                });
                            });
                    });

                    city.addEventListener("change", () => {
                        suggestRDO();
                        updateClientAddress();
                    });
                }

                const scrollPos = sessionStorage.getItem("clientsScrollY");
                if (scrollPos !== null) {
                    window.scrollTo(0, parseInt(scrollPos));
                }
            });

            window.addEventListener("beforeunload", function () {
            sessionStorage.setItem("clientsScrollY", window.scrollY);
            });

            // Construct full client address string
            function updateClientAddress() {
                const floor = document.getElementById("floor_no").value.trim();
                const building = document.getElementById("building_no").value.trim();
                const street = document.getElementById("street").value.trim();
                const barangayEl = document.getElementById("barangay");
                const barangay = barangayEl.value.trim().replace(/\b\w/g, c => c.toUpperCase());
                barangayEl.value = barangay;

                const city = document.getElementById("city").value.trim();
                const province = document.getElementById("province").value.trim();
                const zip = document.getElementById("zip_code").value.trim();

                const parts = [];
                if (floor) parts.push(floor);
                if (building) parts.push(building);
                if (street) parts.push(street);
                if (barangay) parts.push("Brgy. " + barangay);
                if (city) parts.push(city);
                if (province) parts.push(province);
                if (zip) parts.push(zip);

                document.getElementById("client_address").value = parts.join(", ");
            }

            // Suggest RDO code based on city value
            function suggestRDO() {
                const city = document.getElementById("city").value.trim();
                const rdoInput = document.getElementById("rdo_code");

                const matchedCity = Object.keys(rdoMapping).find(key =>
                    city.toLowerCase().includes(key.toLowerCase())
                );

                if (matchedCity) {
                    rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
                }
            }

            const rdoMapping = {
                "Laoag City, Ilocos Norte": "001",
                "Vigan, Ilocos Sur": "002",
                "San Fernando, La Union": "003",
                "Calasiao, West Pangasinan": "004",
                "Alaminos, Pangasinan": "005",
                "Urdaneta, Pangasinan": "006",
                "Bangued, Abra": "007",
                "Baguio City": "008",
                "La Trinidad, Benguet": "009",
                "Bontoc, Mt. Province": "010",
                "Tabuk City, Kalinga": "011",
                "Lagawe, Ifugao": "012",
                "Tuguegarao, Cagayan": "013",
                "Bayombong, Nueva Vizcaya": "014",
                "Naguilian, Isabela": "015",
                "Cabarroguis, Quirino": "016",
                "Tarlac City, Tarlac": "17A",
                "Paniqui, Tarlac": "17B",
                "Olongapo City": "018",
                "Subic Bay Freeport Zone": "019",
                "Balanga, Bataan": "020",
                "North Pampanga": "21A",
                "South Pampanga": "21B",
                "Clark Freeport Zone": "21C",
                "Baler, Aurora": "022",
                "North Nueva Ecija": "23A",
                "South Nueva Ecija": "23B",
                "Valenzuela City": "024",
                "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
                "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
                "Malabon-Navotas": "026",
                "Caloocan City": "027",
                "Novaliches": "028",
                "Tondo – San Nicolas": "029",
                "Binondo": "030",
                "Sta. Cruz": "031",
                "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
                "Intramuros-Ermita-Malate": "033",
                "Paco-Pandacan-Sta. Ana-San Andres": "034",
                "Romblon": "035",
                "Puerto Princesa": "036",
                "San Jose, Occidental Mindoro": "037",
                "North Quezon City": "038",
                "South Quezon City": "039",
                "Cubao": "040",
                "Mandaluyong City": "041",
                "San Juan": "042",
                "Pasig": "043",
                "Taguig-Pateros": "044",
                "Marikina": "045",
                "Cainta-Taytay": "046",
                "East Makati": "047",
                "West Makati": "048",
                "North Makati": "049",
                "South Makati": "050",
                "Pasay City": "051",
                "Parañaque": "052",
                "Las Piñas City": "53A",
                "Muntinlupa City": "53B",
                "Trece Martirez City, East Cavite": "54A",
                "Kawit, West Cavite": "54B",
                "San Pablo City": "055",
                "Calamba, Laguna": "056",
                "Biñan, Laguna": "057",
                "Batangas City": "058",
                "Lipa City": "059",
                "Lucena City": "060",
                "Gumaca, Quezon": "061",
                "Boac, Marinduque": "062",
                "Calapan, Oriental Mindoro": "063",
                "Talisay, Camarines Norte": "064",
                "Naga City": "065",
                "Iriga City": "066",
                "Legazpi City, Albay": "067",
                "Sorsogon, Sorsogon": "068",
                "Virac, Catanduanes": "069",
                "Masbate, Masbate": "070",
                "Kalibo, Aklan": "071",
                "Roxas City": "072",
                "San Jose, Antique": "073",
                "Iloilo City": "074",
                "Zarraga, Iloilo City": "075",
                "Victorias City, Negros Occidental": "076",
                "Bacolod City": "077",
                "Binalbagan, Negros Occidental": "078",
                "Dumaguete City": "079",
                "Mandaue City": "080",
                "Cebu City North": "081",
                "Cebu City South": "082",
                "Talisay City, Cebu": "083",
                "Tagbilaran City": "084",
                "Catarman, Northern Samar": "085",
                "Borongan, Eastern Samar": "086",
                "Calbayog City, Samar": "087",
                "Tacloban City": "088",
                "Ormoc City": "089",
                "Maasin, Southern Leyte": "090",
                "Dipolog City": "091",
                "Pagadian City, Zamboanga del Sur": "092",
                "Zamboanga City, Zamboanga del Sur": "093A",
                "Ipil, Zamboanga Sibugay": "093B",
                "Isabela, Basilan": "094",
                "Jolo, Sulu": "095",
                "Bongao, Tawi-Tawi": "096",
                "Gingoog City": "097",
                "Cagayan de Oro City": "098",
                "Malaybalay City, Bukidnon": "099",
                "Ozamis City": "100",
                "Iligan City": "101",
                "Marawi City": "102",
                "Butuan City": "103",
                "Bayugan City, Agusan del Sur": "104",
                "Surigao City": "105",
                "Tandag, Surigao del Sur": "106",
                "Cotabato City": "107",
                "Kidapawan, North Cotabato": "108",
                "Tacurong, Sultan Kudarat": "109",
                "General Santos City": "110",
                "Koronadal City, South Cotabato": "111",
                "Tagum, Davao del Norte": "112",
                "West Davao City": "113A",
                "East Davao City": "113B",
                "Mati, Davao Oriental": "114",
                "Digos, Davao del Sur": "115"
            };
        </script>
    </div>
</body>

</html>