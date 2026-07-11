<?php
session_start();


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';
require_once '../config_db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $email      = strtolower(trim($_POST['email'] ?? ''));

    if (empty($student_id) || empty($email)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if student exists AND email matches
        $stmt = $conn->prepare("SELECT student_id, email FROM students WHERE student_id = ? LIMIT 1");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows === 0) {
            $error = 'No account found with that Student ID.';
        } else {
            $student = $result->fetch_assoc();

            // SECURITY: Email must match the one registered in the database
            if (strtolower($student['email']) !== $email) {
                $error = 'Email does not match our records for this Student ID.';
            } else {
                // Delete old unused OTPs
                $del = $conn->prepare("DELETE FROM otp_tokens WHERE student_id = ? AND used = 0");
                $del->bind_param("s", $student_id);
                $del->execute();
                $del->close();

                // Generate OTP
                $otp_plain = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // Save OTP to database
                $ins = $conn->prepare("INSERT INTO otp_tokens (student_id, token, expires_at) 
                                       VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
                $ins->bind_param("ss", $student_id, $otp_plain);

                if ($ins->execute()) {
                    $ins->close();

                    // Send OTP via Email
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'sitci.softvote@gmail.com';
                        $mail->Password   = 'yhqy xjvz nwyt vbzg';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('sitci.softvote@gmail.com', 'SoftVote Election System');
                        $mail->addAddress($email);
                        $mail->isHTML(true);
                        $mail->Subject = 'SoftVote - Password Reset OTP';
                        $mail->Body    = "
                            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; border: 1px solid #e5e7eb; padding: 20px; border-radius: 10px;'>
                                <h2 style='color: #2563eb;'>Reset Your Password</h2>
                                <p>Use the code below to reset your password. It will expire in <strong>3 minutes</strong>.</p>
                                <div style='font-size: 32px; font-weight: bold; background: #f3f4f6; padding: 15px; text-align: center; letter-spacing: 5px; color: #1e40af;'>
                                    {$otp_plain}
                                </div>
                                <p style='font-size: 12px; color: #6b7280; margin-top: 20px;'>If you didn't request this, you can safely ignore this email.</p>
                            </div>";

                        if ($mail->send()) {
                            $_SESSION['otp_student_id'] = $student_id;
                            $_SESSION['otp_email']      = $email;
                            session_regenerate_id(true);

                            header('Location: verify_otp.php');
                            exit();

                        } else {
                            $error = 'Failed to send email. Please try again.';
                        }
                    } catch (Exception $e) {
                        $error = 'Failed to send OTP email. Please try again later.';
                        error_log("PHPMailer Error: " . $e->getMessage());
                    }
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SoftVote</title>

    <style>
        /* Your existing styles (unchanged) */
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

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 32px;
            max-width: 420px;
            width: 100%;
        }

        h1 {
            font-size: 1.75rem;
            color: #111827;
            margin: 0 0 8px;
        }

        .subtitle {
            color: #6b7280;
            margin: 0 0 24px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

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

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="card">
        <h1>Forgot your Password?</h1>
        <p class="subtitle">Enter your Student ID and registered email.<br>We'll send a 6-digit OTP.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="forgotForm">
            <div class="field">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id"
                    placeholder="e.g. 2026-001"
                    value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                    required autocomplete="off">
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    placeholder="your.email@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autocomplete="off">
            </div>

            <button type="submit" id="submitBtn">Send OTP</button>
        </form>

        <a href="../auth/student_login.php" class="back-link">← Back to Login</a>
    </div>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Sending...';
        });
    </script>

</body>

</html>