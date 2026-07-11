<?php
session_start();
include '../config_db.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Student Login · SoftVote</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap">
  <link rel="stylesheet" href="../styles/student_login.css">
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
      <p class="brand-subtitle">Student Login</p>
      <p class="copyright">© 2026 SoftVote · Election Management System</p>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
      <div class="login-card">

        <h2>Student Council Election</h2>
        <p>Voting Management</p>

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
            <i data-lucide="alert-circle" class="icon"></i>
            <?= htmlspecialchars($_GET['error']) ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
          <div class="success-message">
            <i data-lucide="check-circle" class="icon"></i>
            Successfully logged out
          </div>
        <?php endif; ?>

        <form action="login_auth.php" method="POST" id="studentLoginForm">
          <input type="hidden" name="user_type" value="student">

          <div class="field">
            <label>Student ID</label>
            <div class="input-wrapper">
              <span class="input-icon">
                <i data-lucide="user"></i>
              </span>
              <input type="text" name="username" placeholder="Enter your Student ID" required
                value="<?= htmlspecialchars($_GET['username'] ?? '') ?>">
            </div>
          </div>

          <div class="field">
            <label>Password</label>
            <div class="input-wrapper">
              <span class="input-icon">
                <i data-lucide="lock"></i>
              </span>
              <input
                type="password"
                name="password"
                id="password-input"
                placeholder="Enter your password"
                autocomplete="current-password"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                required>
              <button type="button" class="toggle-btn" id="toggle-password">
                <i data-lucide="eye"></i>
              </button>
            </div>
          </div>

          <!-- Forgot Password -->
          <div class="forgot-row">
            <a href="../student/forgot_password.php" class="forgot-link">Forgot password?</a>
          </div>

          <button type="submit" class="btn-login">
            Login to Vote
          </button>
        </form>

      </div>
    </div>

  </div>


  <script>
    lucide.createIcons();

    const passwordInput = document.getElementById('password-input');
    const showPasswordBtn = document.getElementById('toggle-password');

    let passwordTimer = null;

    showPasswordBtn.addEventListener('click', () => {

      const icon = showPasswordBtn.querySelector('i');

      // SHOW PASSWORD
      if (passwordInput.type === 'password') {

        passwordInput.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');

        // CLEAR OLD TIMER
        clearTimeout(passwordTimer);

        // AUTO HIDE AFTER 3 SECONDS
        passwordTimer = setTimeout(() => {
          passwordInput.type = 'password';
          icon.setAttribute('data-lucide', 'eye');
          lucide.createIcons();
        }, 3000);

      } else {

        // MANUAL HIDE
        passwordInput.type = 'password';
        icon.setAttribute('data-lucide', 'eye');

        clearTimeout(passwordTimer);
      }

    });
  </script>

</body>

</html>