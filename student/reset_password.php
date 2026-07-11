<?php
session_start();

// KAILANGAN NG OTP VERIFICATION BAGO MAAACCESS ANG PAGE NA TO
if (empty($_SESSION['otp_verified']) || empty($_SESSION['otp_student_id'])) {
    header('Location: forgot_password.php');
    exit;
}

require_once '../config_db.php';

$error      = '';
$success    = false;
$student_id = $_SESSION['otp_student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass     = $_POST['new_password']     ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE students SET password = ?, is_new_user = 0 WHERE student_id = ?");
        $stmt->bind_param("ss", $hashed, $student_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Clean up session
            unset($_SESSION['otp_verified'], $_SESSION['otp_student_id'], $_SESSION['otp_email']);
            $success = true;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <title>Reset Password — SoftVote</title>

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
            justify-content: center;
            gap: 10px;
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

        .field {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .field label {
            display: block;
            font-weight: 500;
            color: #374151;
        }

        .input-wrap {
            display: flex;
            align-items: center;
            width: 100%;
            height: 50px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-sizing: border-box;
            overflow: hidden;
            background-color: #fff;
        }

        .input-wrap:focus-within {
            outline: none;
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

        .toggle-eye {
            background-color: transparent;
            padding: 0 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
        }

        .eye {
            color: #b2b4b8;
        }

        .strength-bar {
            display: flex;
            gap: 4px;
            margin-top: 2px;
        }

        .strength-bar span {
            flex: 1;
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 4px;
        }

        .strength-label {
            margin-bottom: 12px;
            margin-top: -5px;
        }

        .submit {
            margin-top: 20px;
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
            align-items: center;
        }

        .submit:hover {
            background: #1d4ed8;
        }

        .button:disabled {
            background: #9ca3af;
            text-align: center;
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

        /* Success modal */
        .success-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .success-state p{
            text-align: center;
        }

        .success-state a {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .success-state a:hover {
            text-decoration: underline;
        }
    </style>

</head>

<body>

    <div class="card">

        <?php if ($success): ?>
            <div class="success-state">
                <h2>Password Updated!</h2>
                <p>Your password has been reset successfully. You can now log in with your new password.</p>
                <a href="../index.php" class="login-btn">Go to Login →</a>
            </div>

        <?php else: ?>

            <h1>New Password</h1>
            <p class="subtitle">Update your password to proceed voting. Make sure its easy to remember!</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="resetForm">
                <div class="field">
                    <label for="new_password">New Password</label>
                    <div class="input-wrap">
                        <input type="password" id="new_password" name="new_password"
                            placeholder="Minimum of 8 characters" required autocomplete="new-password">
                        <button type="button" class="toggle-eye" onclick="togglePass('new_password', this)">
                            <?= eyeIcon() ?>
                        </button>
                    </div>
                    <div class="strength-bar">
                        <span id="s1"></span><span id="s2"></span>
                        <span id="s3"></span><span id="s4"></span>
                    </div>
                    <div class="strength-label" id="strength-label"></div>
                </div>

                <div class="field">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Repeat your new password" required autocomplete="new-password">
                        <button type="button" class="toggle-eye" onclick="togglePass('confirm_password', this)">
                            <?= eyeIcon() ?>
                        </button>
                    </div>
                </div>

                <button class="submit" type="submit" id="resetBtn">Reset Password</button>
            </form>

        <?php endif; ?>
    </div>

    <?php
    function eyeIcon()
    {
        return '<i data-lucide="eye" class="eye"></i> ';
    }
    ?>

    <script>
        lucide.createIcons();

        function togglePass(id, btn) {
            const inp = document.getElementById(id);
            inp.type = inp.type === 'password' ? 'text' : 'password';
        }

        // Password strength indicator
        const pwdInput = document.getElementById('new_password');
        const bars = [document.getElementById('s1'), document.getElementById('s2'),
            document.getElementById('s3'), document.getElementById('s4')
        ];
        const label = document.getElementById('strength-label');
        const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['Too short', 'Weak', 'Moderate', 'Strong'];

        pwdInput?.addEventListener('input', () => {
            const val = pwdInput.value;
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            bars.forEach((b, i) => {
                b.style.background = i < score ? colors[score - 1] : 'var(--border)';
            });

            label.textContent = val.length ? labels[score - 1] || 'Too short' : '';
            label.style.color = val.length ? colors[score - 1] : 'var(--muted)';
        });

        // Submit spinner
        document.getElementById('resetForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('resetBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Updating...';
        });
    </script>
</body>

</html>