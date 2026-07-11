<?php
session_start();
require 'config_db.php';

$res = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_status' LIMIT 1");
$real_status = $res ? $res->fetch_assoc()['setting_value'] : 'not_started';

if ($real_status === 'ongoing') {
    header("Location: auth/student_login.php");
    exit();
}

$status = $real_status;

$status = $real_status;

$config = [
    'not_started' => [
        'icon'     => 'clock',
        'color'    => '#f59e0b',
        'bg'       => '#fffbeb',
        'border'   => '#fde68a',
        'title'    => 'Election Has Not Started Yet',
        'message'  => 'The voting period has not been opened. Please check back later or contact your administrator.',
        'badge'    => 'Not Started',
        'badge_bg' => '#fef3c7',
        'badge_fg' => '#92400e',
    ],
    'ended' => [
        'icon'     => 'flag',
        'color'    => '#6b7280',
        'bg'       => '#f9fafb',
        'border'   => '#e5e7eb',
        'title'    => 'The Election Has Ended',
        'message'  => 'Voting is now closed. Thank you for your participation. Results are being finalized.',
        'badge'    => 'Election Ended',
        'badge_bg' => '#f3f4f6',
        'badge_fg' => '#374151',
    ],
];

$c = $config[$status] ?? $config['not_started'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Election Status – SOFTVOTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <style>
        body {
            background-color: #f0f2f5 !important;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }

        .status-card {
            max-width: 460px;
            margin: 80px auto;
            background: <?= $c['bg'] ?>;
            border: 1px solid <?= $c['border'] ?>;
            border-radius: 16px;
            padding: 40px 36px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .status-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: <?= $c['border'] ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .status-icon-wrap svg {
            width: 32px;
            height: 32px;
            color: <?= $c['color'] ?>;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
            background: <?= $c['badge_bg'] ?>;
            color: <?= $c['badge_fg'] ?>;
            margin-bottom: 16px;
        }

        .status-card h2 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }

        .status-card p {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: background .2s;
        }

        .btn-back:hover {
            background: #1d4ed8;
        }

        .btn-back svg {
            width: 15px;
            height: 15px;
        }
    </style>

</head>

<body>
    <div class="status-card">
        <div class="status-icon-wrap">
            <i data-lucide="<?= $c['icon'] ?>"></i>
        </div>
        <div class="status-badge"><?= $c['badge'] ?></div>
        <h2><?= $c['title'] ?></h2>
        <p><?= $c['message'] ?></p>
        <a href="auth/student_login.php" class="btn-back">
            <i data-lucide="arrow-left"></i> Back to Login
        </a>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>