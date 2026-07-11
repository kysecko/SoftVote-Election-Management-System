<?php
session_start();

if (!isset($_SESSION['otp_student_id']) || !isset($_SESSION['otp_email'])) {
    header('Location: ../auth/student_login.php');
    exit;
}

require_once '../config_db.php';

$error      = '';
$student_id = $_SESSION['otp_student_id'];
$email      = $_SESSION['otp_email'];

$masked_email = $email
    ? preg_replace('/(?<=.).(?=[^@]*?.@)/', '*', $email)
    : 'your registered email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp_code'] ?? '');

    if (strlen($otp) !== 6 || !ctype_digit($otp)) {
        $error = 'Please enter the complete 6-digit OTP.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM otp_tokens 
            WHERE student_id = ? AND token = ? AND used = 0 AND expires_at > NOW() 
            ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ss", $student_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = 'Invalid or expired OTP. Please request a new one.';
        } else {
            $row      = $result->fetch_assoc();
            $token_id = $row['id'];

            $upd = $conn->prepare("UPDATE otp_tokens SET used = 1 WHERE id = ?");
            $upd->bind_param("i", $token_id);
            $upd->execute();

            $_SESSION['otp_verified'] = true;
            header('Location: change_password.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/lucide@latest"></script>
    <title>Verify Email - SoftVote</title>
    <style>
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
            text-align: left;
            margin: 0 0 8px;
        }

        .subtitle {
            color: #6b7280;
            text-align: left;
            margin: 0 0 24px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-error   { background: #fee2e2; color: #991b1b; }
        .alert-success { background: #d1fae5; color: #065f46; }

        .otp-group {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            border-radius: 8px;
            border: 1px solid #9ca3af;
            font-size: 1.4rem;
            font-weight: 600;
            color: #111827;
        }

        .otp-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        .timer-row {
            text-align: center;
            margin-bottom: 20px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        #countdown {
            color: #1d4ed8;
            font-weight: bold;
        }

        .timer-row.expired #countdown {
            color: #dc2626;
        }

        button.verify-btn {
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

        button.verify-btn:hover    { background: #1d4ed8; }
        button.verify-btn:disabled { background: #9ca3af; cursor: not-allowed; }

        .resend-row {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            color: #6b7280;
            gap: 4px;
            font-size: 0.9rem;
        }

        #resendLink {
            text-decoration: none;
            color: #2563eb;
            background: none;
            border: none;
            padding: 0;
            width: auto;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
        }

        #resendLink:hover:not(:disabled) { text-decoration: underline; }
        #resendLink:disabled { color: #9ca3af; cursor: not-allowed; }

        #resendCooldown { color: #9ca3af; font-size: 0.85rem; }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        .spinner-dark {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #2563eb;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            margin-right: 4px;
            vertical-align: middle;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="card">
    <h1>Verify Your Email</h1>
    <p class="subtitle">
        Enter the 6-digit code sent to <strong><?= htmlspecialchars($masked_email) ?></strong>.<br>
        The code expires in 3 minutes.
    </p>

    <div id="resendAlert" class="alert" style="display:none;"></div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="otpForm">
        <div class="otp-group" id="otpBoxes">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="1">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="2">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="3">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="4">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="5">
            <input class="otp-input" type="text" inputmode="numeric" maxlength="1" tabindex="6">
        </div>
        <input type="hidden" name="otp_code" id="otp_code">

        <div class="timer-row" id="timerEl">
            Code expires in <span id="countdown">03:00</span>
        </div>

        <button type="submit" class="verify-btn" id="verifyBtn">Verify OTP</button>
    </form>

    <div class="resend-row">
        Didn't receive it?
        <button type="button" id="resendLink">Resend OTP</button>
        <span id="resendCooldown"></span>
    </div>
</div>

<script>
    lucide.createIcons();

    const boxes     = document.querySelectorAll('.otp-input');
    const hidden    = document.getElementById('otp_code');
    const verifyBtn = document.getElementById('verifyBtn');
    const timerEl   = document.getElementById('timerEl');
    const countEl   = document.getElementById('countdown');

    // OTP box behavior
    boxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            if (box.value && i < boxes.length - 1) boxes[i + 1].focus();
            syncHidden();
        });

        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && i > 0) {
                boxes[i - 1].value = '';
                boxes[i - 1].focus();
                syncHidden();
            }
        });

        box.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            [...pasted].slice(0, 6).forEach((ch, idx) => {
                if (boxes[idx]) boxes[idx].value = ch;
            });
            boxes[Math.min(pasted.length, 5)].focus();
            syncHidden();
        });
    });

    function syncHidden() {
        hidden.value = [...boxes].map(b => b.value).join('');
    }

    boxes[0].focus();

    // Countdown timer
    let seconds = 180;
    let timerInterval = startTimer();

    function startTimer() {
        return setInterval(() => {
            seconds--;
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            countEl.textContent = `${m}:${s}`;
            if (seconds <= 0) {
                clearInterval(timerInterval);
                countEl.textContent = 'Expired';
                timerEl.classList.add('expired');
                verifyBtn.disabled = true;
            }
        }, 1000);
    }

    // Submit spinner
    document.getElementById('otpForm').addEventListener('submit', function () {
        if (hidden.value.length === 6) {
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<span class="spinner"></span>Verifying...';
        }
    });

    // Resend OTP
    const resendLink    = document.getElementById('resendLink');
    const resendAlert   = document.getElementById('resendAlert');
    const resendCooldown = document.getElementById('resendCooldown');

    function showResendAlert(message, type) {
        resendAlert.textContent  = message;
        resendAlert.className    = 'alert alert-' + type;
        resendAlert.style.display = 'flex';
        setTimeout(() => { resendAlert.style.display = 'none'; }, 5000);
    }

    function startResendCooldown() {
        let cooldown = 60;
        resendLink.disabled = true;
        resendCooldown.textContent = ` (${cooldown}s)`;
        const cd = setInterval(() => {
            cooldown--;
            resendCooldown.textContent = ` (${cooldown}s)`;
            if (cooldown <= 0) {
                clearInterval(cd);
                resendLink.disabled = false;
                resendCooldown.textContent = '';
            }
        }, 1000);
    }

    function resetOtpBoxes() {
        boxes.forEach(box => { box.value = ''; });
        hidden.value = '';
        boxes[0].focus();
    }

    function resetCountdown() {
        clearInterval(timerInterval);
        seconds = 180;
        countEl.textContent = '03:00';
        timerEl.classList.remove('expired');
        verifyBtn.disabled = false;
        timerInterval = startTimer();
    }

    resendLink.addEventListener('click', async () => {
        resendLink.disabled = true;
        resendLink.innerHTML = '<span class="spinner-dark"></span>Sending...';

        try {
            const response = await fetch('resend_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();

            if (data.success) {
                showResendAlert(data.message, 'success');
                resetOtpBoxes();
                resetCountdown();
                startResendCooldown();
            } else {
                showResendAlert(data.message, 'error');
                resendLink.disabled = false;
            }
        } catch (err) {
            showResendAlert('Something went wrong. Please try again.', 'error');
            resendLink.disabled = false;
        }

        resendLink.innerHTML = 'Resend OTP';
    });
</script>
</body>
</html>