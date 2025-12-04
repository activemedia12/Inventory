<?php
session_start();

// ADD CACHE CONTROL HEADERS FIRST
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if logout is confirmed
if (isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
    // COMPLETELY DESTROY THE SESSION
    session_unset();    // Remove all session variables
    session_destroy();  // Destroy the session
    session_write_close(); // Ensure session is closed
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // REDIRECT TO LOGIN WITH NO-CACHE HEADERS
    header("Location: ../website/sub-main.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- ADD THESE META TAGS FOR CACHE CONTROL -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Logout Confirmation</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Your existing CSS remains the same */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
            border-radius: 10px;
        }

        :root {
            --primary-color: #1c1c1c;
            --primary-dark: #1a51b0;
            --secondary-color: white;
            --accent-color: #e74c3c;
            --text-dark: #3b3b3b;
            --text-light: #7f8c8d;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --border-color: #e1e8ed;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 24px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s ease;
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logout-container {
            background: var(--card-bg);
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .logout-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .logout-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .logout-message {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 25px;
        }

        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: 2px solid var(--primary-color);
        }

        .btn-primary:hover {
            background-color: transparent;
            color: #1c1c1c;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: #1c1c1c;
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: transparent;
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
        }

        .btn i {
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .logout-container {
                scale: 0.8;
            }
        }

        @media (max-width: 480px) {
            .logout-container {
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h1 class="logout-title">Logout Confirmation</h1>
        <p class="logout-message">Are you sure you want to log out of your account?</p>

        <div class="btn-group">
            <a href="logout.php?confirm=true" class="btn btn-primary">
                <i class="fas fa-check"></i> Yes, Logout
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </div>
    
    <!-- ADD JAVASCRIPT FOR EXTRA PROTECTION -->
    <script>
        // Prevent caching
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>