<?php
session_start();

// AUTH PAG CHECK KUNG ALREADY LOGIN ANG STUDENTS
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    session_destroy();
    header("Location: ../auth/student_login.php");
    exit();
}

// BALIK LANG DIN SA DASHBOARD
if ($_SESSION['has_voted'] != 1) {
    header("Location: student_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vote Submitted - SOFTVOTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/student/voted_already.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <div class="modal-overlay">
        <div class="modal-container">

            <div style="margin-bottom:20px;">
                <img src="../images/voted-img.png" alt="Done Vote Illustration" class="vote-illustration">
            </div>

            <h2 class="modal-title">You Have Already Voted</h2>

            <p class="modal-subtitle">
                Thank you for participating in the election.
                Your vote has been successfully recorded.
            </p>

            <a href="../auth/student_login.php"
                class="logout-button">
                Logout
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>

</body>

</html>