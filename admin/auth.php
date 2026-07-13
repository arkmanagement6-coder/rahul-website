<?php
// admin/auth.php
session_start();

// Admin Authentication Password
define('ADMIN_PASSKEY', 'admin@smmpaynow'); // cPanel users will update this password

// Handle Login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $passkey = $_POST['passkey'] ?? '';
    if ($passkey === ADMIN_PASSKEY) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit(0);
    } else {
        $login_error = "Invalid administrator passkey!";
    }
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: index.php');
    exit(0);
}

// If not logged in, render the login page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SMMPayNow - Admin Portal Login</title>
        <!-- RemixIcons -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.5.0/remixicon.min.css" rel="stylesheet">
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
            body {
                background: radial-gradient(circle at 10% 20%, rgb(18, 20, 29) 0%, rgb(11, 12, 16) 90%);
                color: #f3f4f6;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-card {
                background: rgba(30, 32, 45, 0.45);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 24px;
                padding: 40px 30px;
                width: 100%;
                max-width: 440px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
                text-align: center;
            }
            .logo-icon {
                width: 60px;
                height: 60px;
                border-radius: 16px;
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 20px;
                box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
            }
            .logo-icon i {
                font-size: 30px;
                color: #ffffff;
            }
            h1 {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 8px;
                background: linear-gradient(135deg, #ffffff 0%, #a5b4fc 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            p {
                font-size: 14px;
                color: #9ca3af;
                margin-bottom: 30px;
            }
            .input-group {
                position: relative;
                margin-bottom: 24px;
                text-align: left;
            }
            .input-group label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                color: #a5b4fc;
                margin-bottom: 8px;
                letter-spacing: 0.5px;
            }
            .input-wrapper {
                position: relative;
            }
            .input-wrapper i {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #6b7280;
                font-size: 18px;
            }
            .input-wrapper input {
                width: 100%;
                background: rgba(17, 19, 28, 0.8);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 12px;
                padding: 14px 16px 14px 46px;
                color: #ffffff;
                font-size: 15px;
                outline: none;
                transition: all 0.3s;
            }
            .input-wrapper input:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            }
            .login-btn {
                width: 100%;
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                border: none;
                border-radius: 12px;
                color: #ffffff;
                font-size: 15px;
                font-weight: 600;
                padding: 14px;
                cursor: pointer;
                box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            .login-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 12px 24px rgba(99, 102, 241, 0.3);
            }
            .error-message {
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid rgba(239, 68, 68, 0.2);
                color: #f87171;
                border-radius: 12px;
                padding: 12px;
                font-size: 13px;
                margin-bottom: 24px;
                text-align: left;
                display: flex;
                align-items: center;
                gap: 8px;
            }
        </style>
    </head>
    <body>
        <div class="login-card">
            <div class="logo-icon">
                <i class="ri-shield-keyhole-line"></i>
            </div>
            <h1>Admin Control Panel</h1>
            <p>Enter your administrator passkey to continue</p>
            
            <?php if (isset($login_error)): ?>
                <div class="error-message">
                    <i class="ri-error-warning-line"></i>
                    <span><?php echo $login_error; ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="input-group">
                    <label for="passkey">Secure Passkey</label>
                    <div class="input-wrapper">
                        <i class="ri-lock-password-line"></i>
                        <input type="password" id="passkey" name="passkey" placeholder="••••••••••••" required autocomplete="current-password">
                    </div>
                </div>
                <button type="submit" class="login-btn">
                    <span>Unlock Portal</span>
                    <i class="ri-arrow-right-line"></i>
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit(0);
}
?>
