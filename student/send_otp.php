<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';
require_once '../config_db.php';

/**
 * Send OTP for First Login or Forgot Password
 */
function sendOTP($student_id, $email, $type = 'first_login')
{
    global $conn;

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // Delete old unused OTPs
    $del = $conn->prepare("DELETE FROM otp_tokens WHERE student_id = ? AND used = 0");
    $del->bind_param("s", $student_id);
    $del->execute();

    // Generate 6-digit OTP
    $otp_plain = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Insert new OTP (valid for 3 minutes)
    $ins = $conn->prepare("INSERT INTO otp_tokens (student_id, token, expires_at) 
                           VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))");
    $ins->bind_param("ss", $student_id, $otp_plain);
    $ins->execute();

    // Send Email
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
        $mail->Subject = 'SoftVote - Login Verification OTP';

        $purpose = ($type === 'first_login') 
            ? 'Complete your first login' 
            : 'Reset your password';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 10px;'>
                <h2 style='color: #2563eb;'>{$purpose}</h2>
                <p>Use this code to continue:</p>
                <div style='font-size: 36px; font-weight: bold; text-align: center; letter-spacing: 8px; background: #f3f4f6; padding: 20px; border-radius: 8px; color: #1e40af;'>
                    {$otp_plain}
                </div>
                <p style='font-size: 13px; color: #6b7280; margin-top: 20px;'>
                    This code expires in <strong>3 minutes</strong>.<br>
                    If you didn't request this, ignore this email.
                </p>
            </div>";

        return $mail->send();

    } catch (Exception $e) {
        error_log("OTP Email Error: " . $e->getMessage());
        return false;
    }
}