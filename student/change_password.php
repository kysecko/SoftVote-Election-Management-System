<?php
session_start();

$is_forgot = isset($_SESSION['otp_verified']) && isset($_SESSION['otp_student_id']);
$is_first  = isset($_SESSION['otp_verified']) && isset($_SESSION['pending_student_id']);
$is_success = isset($_GET['success']);

if (!$is_forgot && !$is_first && !$is_success) {
  header("Location: ../auth/student_login.php");
  exit();
}

$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://unpkg.com/lucide@latest"></script>
  <title>Create Password - SoftVote</title>

  <style>
    /* Page base */
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f3f4f6;
      margin: 0;
      padding: 20px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Card container */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      padding: 32px;
      max-width: 420px;
      width: 100%;
    }

    h2 {
      font-size: 1.75rem;
      color: #111827;
      margin: 0 0 8px;
      text-align: left;
    }

    p.subtitle {
      color: #6b7280;
      text-align: left;
      margin: 0 0 24px;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    /* Alerts */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .alert-error {
      background: #fee2e2;
      color: #991b1b;
    }

    .alert-success {
      background: #d1fae5;
      color: #065f46;
    }

    /* Form fields */
    .field {
      margin-bottom: 20px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    label {
      font-weight: 500;
      color: #374151;
    }

    .input-wrap {
      display: flex;
      align-items: center;
      width: 100%;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      box-sizing: border-box;
      overflow: hidden;
      background-color: #fff;
      height: 50px;
    }

    .input-wrap:focus-within {
      border-color: #3b82f6;
    }

    .input-wrap input {
      flex: 1;
      padding: 12px;
      border: none;
      background: transparent;
      font-size: 14px;
      outline: none;
    }

    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }

    input[type="password"]::-webkit-textfield-decoration-container {
      display: none;
    }

    /* Toggle password eye button */
    .toggle-pw {
      background-color: transparent;
      padding: 0 12px;
      cursor: pointer;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .toggle-pw .eye {
      width: 18px;
      height: 18px;
      color: #6b7280;
    }

    /* Submit button */
    button.submit {
      width: 100%;
      padding: 12px;
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.2s;
    }

    button.submit:hover {
      background: #1d4ed8;
    }

    button.submit:disabled {
      background: #9ca3af;
      cursor: not-allowed;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 20px;
      color: #2563eb;
      text-decoration: none;
      font-size: 0.9rem;
    }

    .back-link:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="card">
    <h2>Set Your Permanent Password</h2>
    <p class="subtitle">This will be your password for future logins.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="process_change_password.php">
      <div class="field">
        <label for="new_password">New Password</label>
        <div class="input-wrap">
          <input type="password" id="new_password" name="new_password" placeholder="New Password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('new_password')"><i class="eye" data-lucide="eye"></i></button>
        </div>
      </div>

      <div class="field">
        <label for="confirm_password">Re-enter Password</label>
        <div class="input-wrap">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
          <button type="button" class="toggle-pw" onclick="togglePw('confirm_password')"><i class="eye" data-lucide="eye"></i></button>
        </div>
      </div>

      <button type="submit" class="submit">Save Password & Continue</button>
    </form>
  </div>

<?php if (isset($_GET['success'])): ?>
<div id="successModal" onclick="goToLogin()" style="
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999; cursor: pointer;">
    <div onclick="event.stopPropagation()" style="
        background: white; border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        padding: 40px 32px; max-width: 380px;
        width: 90%; text-align: center; cursor: default;">

        <div style="display:flex; justify-content:center; margin-bottom:16px;">
            <i data-lucide="circle-check-big" style="width:64px; height:64px; color:#16a34a;"></i>
        </div>

        <h3 style="margin: 0 0 8px; color:#111827; font-size:1.4rem;">Password Changed!</h3>
        <p style="color:#6b7280; margin: 0 0 6px; font-size:0.95rem; line-height:1.5;">
            Your password has been updated successfully.<br>
            <?php
            $redirect_target = $_GET['redirect'] ?? '../auth/student_login.php';
            $is_dashboard    = strpos($redirect_target, 'dashboard') !== false
                            || strpos($redirect_target, 'voted_already') !== false;
            echo $is_dashboard
                ? 'You will be redirected to the voting page shortly.'
                : 'You will be redirected to the login page shortly.';
            ?>
        </p>
        <p style="color:#9ca3af; font-size:0.85rem; margin-bottom:24px;">
            Redirecting in <strong id="countdown">5</strong> seconds...
        </p>

        <!-- Button uses JS redirect so it respects the dynamic destination -->
        <button onclick="goToLogin()" style="
            display: inline-block; background: #2563eb; color: white;
            padding: 10px 28px; border-radius: 6px; border: none;
            font-weight: 500; font-size: 0.95rem; cursor: pointer;">
            <?= $is_dashboard ? 'Go to Dashboard' : 'Go to Login' ?>
        </button>
    </div>
</div>
<?php endif; ?>

<script>
    lucide.createIcons();

    function togglePw(id) {
        const input = document.getElementById(id);
        input.type = input.type === 'password' ? 'text' : 'password';
        lucide.createIcons();
    }

    // Read redirect destination from URL param, fallback to login
    const urlParams  = new URLSearchParams(window.location.search);
    const redirectTo = urlParams.get('redirect') || '../auth/student_login.php';

    function goToLogin() {
        clearInterval(window._cdTimer);
        window.location.href = redirectTo;
    }

    const countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        let seconds = 5;
        window._cdTimer = setInterval(() => {
            seconds--;
            countdownEl.textContent = seconds;
            if (seconds <= 0) goToLogin();
        }, 1000);
    }
</script>
</body>

</html>