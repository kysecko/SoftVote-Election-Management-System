<?php
session_start();
require_once '../config_db.php';

if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
  session_destroy();
  header("Location: ../auth/student_login.php");
  exit();
}

if ($_SESSION['has_voted'] != 1) {
  header("Location: student_dashboard.php");
  exit();
}

$election_title = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_title'")
  ->fetch_assoc()['setting_value'] ?? 'Student Council Election';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Vote Submitted - SOFTVOTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../styles/student/vote_success.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

  <div class="modal-overlay">
    <div class="modal-container">

      <div class="logo-container">
        <img src="../images/white lang.jpg" alt="School Logo" class="school-logo">
      </div>

      <h1 class="modal-title">Vote Already Submitted</h1>
      <p class="modal-subtitle">
        You have already cast your vote in this election.
      </p>

      <div class="success-box">
        <div class="success-icon">
          <i data-lucide="check-circle" class="icon"></i>
        </div>
        <div class="success-content">
          <h3>Thank you for voting!</h3>
          <p>
            Your vote has been successfully recorded. Each student can only vote once to ensure fair elections.
          </p>
        </div>
      </div>

      <div class="important-notice">
        <strong>Important:</strong>
        Once submitted, votes cannot be changed or withdrawn. This ensures the integrity of the electoral process.
      </div>

      <button class="logout-button" onclick="location.href='../auth/student_login.php'">
        <i data-lucide="log-out"></i>
        Logout
      </button>

    </div>
  </div>

  <script>
    lucide.createIcons();

    history.pushState(null, null, location.href);
    window.onpopstate = function() {
      history.go(1);
    };
  </script>

</body>

</html>