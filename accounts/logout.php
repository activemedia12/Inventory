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
    <title>Log Out - Active Media Designs &amp; Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body class="auth-page">
    <div class="auth-shell">
        <div class="auth-main">
            <div class="auth-grid auth-grid--simple">
                <div class="auth-bg-blobs" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="auth-topbar auth-simple-topbar">
                    <a href="../website/sub-main.php" class="auth-brandmark">
                        <img src="../assets/images/plainlogo.png" alt="Active Media Designs Logo">
                        AMDP Website
                    </a>
                </div>
                <div class="auth-form-panel">
                    <div class="auth-icon-badge auth-icon-badge--alert">
                        <i class="fas fa-arrow-right-from-bracket"></i>
                    </div>
                    <h1>Log Out</h1>
                    <p class="auth-subtitle">Are you sure you want to log out of your account?</p>

                    <div class="auth-btn-row">
                        <a href="logout.php?confirm=true" class="btn btn-primary">
                            <i class="fas fa-check"></i> Yes, Log Out
                        </a>
                        <a href="javascript:history.back()" class="btn btn-secondary">
                            <i class="fas fa-xmark"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

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