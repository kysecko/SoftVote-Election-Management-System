<?php
session_start();

if (isset($_SESSION['superadmin_id'])) {
    if (!isset($_GET['logout'])) {
        header("Location: ../superadmin/pages/dashboard.php");
        exit();
    }
}

unset($_SESSION['superadmin_id']);
unset($_SESSION['superadmin_name']);
unset($_SESSION['superadmin_username']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Login · SoftVote</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="../styles/superadmin_login.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <div class="container">

        <!-- LEFT PANEL -->
        <div class="left-panel">
            <div class="logo-box">
                <img src="../images/white lang.jpg" alt="SoftVote Logo">
            </div>
            <h1 class="brand-title">SOFTVOTE</h1>
            <p class="brand-subtitle">Election Management System</p>
            <p class="copyright">© 2026 SoftVote · Election Management System</p>
        </div>

        <!-- RIGHT PANEL -->
        <div class="right-panel">
            <div class="login-card">

                <h2>Super Admin Login</h2>
                <p>Authenticate to access core system controls.</p>

                <!-- Role Tab Switcher -->
                <div class="role-tabs">
                    <a href="student_login.php" class="tab-btn <?= basename($_SERVER['PHP_SELF']) === 'student_login.php' ? 'active' : '' ?>">
                        <i data-lucide="graduation-cap"></i> Student
                    </a>
                    <a href="admin_login.php" class="tab-btn <?= basename($_SERVER['PHP_SELF']) === 'admin_login.php' ? 'active' : '' ?>">
                        <i data-lucide="user-cog"></i> Admin
                    </a>
                    <a href="superadmin_login.php" class="tab-btn <?= basename($_SERVER['PHP_SELF']) === 'superadmin_login.php' ? 'active' : '' ?>">
                        <i data-lucide="shield"></i> Superadmin
                    </a>
                </div>

                <?php if (!empty($_GET['error'])): ?>
                    <div class="error-message">
                        <i data-lucide="alert-circle"></i>
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout'])): ?>
                    <div class="success-message">
                        <i data-lucide="check-circle"></i>
                        Successfully logged out
                    </div>
                <?php endif; ?>

                <form action="login_auth.php" method="POST" id="saLoginForm">
                    <input type="hidden" name="user_type" value="superadmin">

                    <div class="field">
                        <label>Username</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i data-lucide="user"></i>
                            </span>
                            <input type="text" name="username" placeholder="Enter superadmin username" required
                                value="<?= htmlspecialchars($_GET['username'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="field">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">
                                <i data-lucide="lock"></i>
                            </span>
                            <input type="password" name="password" id="sa-password-input"
                                placeholder="Enter superadmin password" required>
                            <button type="button" class="toggle-btn" id="toggle-sa-password">
                                <i data-lucide="eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i data-lucide="shield-check"></i>
                        Authenticate
                    </button>
                </form>

            </div>
        </div>

    </div>

    <script>
        lucide.createIcons();

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        const passwordInput = document.getElementById('sa-password-input');
        const showPasswordBtn = document.getElementById('toggle-sa-password');
        let hideTimer = null;

        showPasswordBtn.addEventListener('click', () => {
            passwordInput.type = 'text';
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                passwordInput.type = 'password';
            }, 300);
        });
    </script>

</body>

</html>