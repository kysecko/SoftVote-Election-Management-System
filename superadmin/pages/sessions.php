<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';

$conn->query("SET time_zone = '+00:00'");

function phTime($datetime, $format = 'M d, Y h:i A')
{
    if (!$datetime) return 'Never';
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));  
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));       
    return $dt->format($format);
}

function phTimestamp($datetime)
{
    if (!$datetime) return 0;
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    return $dt->getTimestamp();                             
}

$active_nav = 'sessions';

// FETCH ACTIVE SESSIONS
$active_sessions = [];
$r = $conn->query("
    SELECT id, name, username,
        IFNULL(last_login, NULL)    AS last_login,
        IFNULL(last_activity, NULL) AS last_activity,
        'superadmin' AS role
    FROM superadmins
    WHERE last_login IS NOT NULL
      AND last_activity IS NOT NULL

    UNION ALL

    SELECT id, name, username,
        IFNULL(last_login, NULL)    AS last_login,
        IFNULL(last_activity, NULL) AS last_activity,
        'admin' AS role
    FROM administrators
    WHERE IFNULL(is_deleted, 0) = 0
      AND IFNULL(is_active, 1) = 1
      AND last_login IS NOT NULL
      AND last_activity IS NOT NULL

    ORDER BY last_activity DESC
");
if ($r) while ($row = $r->fetch_assoc()) $active_sessions[] = $row;

// COUNT ONLINE SESSIONS (active within last 5 minutes)
$online_count = 0;
foreach ($active_sessions as $s) {
    $idle = time() - phTimestamp($s['last_activity']);
    if ($idle < 300) $online_count++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Active Sessions — SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/superadmin/dashboard.css">
    <link rel="stylesheet" href="../../styles/superadmin/sessions.css">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <div class="shell">

        <?php require_once '../includes/_sidebar.php'; ?>

        <!-- MAIN -->
        <div class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h1>Active <span>Sessions</span></h1>
                    <p>Real-time monitor · see who is currently using the system</p>
                </div>
            </div>

            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Online Now</span>
                        <div class="stat-icon green"><i data-lucide="wifi"></i></div>
                    </div>
                    <div class="stat-num" style="color:var(--green)"><?= $online_count ?></div>
                    <div class="stat-desc">Active within 5 minutes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Admins</span>
                        <div class="stat-icon orange"><i data-lucide="users"></i></div>
                    </div>
                    <div class="stat-num"><?= count($active_sessions) ?></div>
                    <div class="stat-desc">Non-deleted accounts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Session Timeout</span>
                        <div class="stat-icon blue"><i data-lucide="timer"></i></div>
                    </div>
                    <div class="stat-num">30<span style="font-size:16px;color:var(--text2)">m</span></div>
                    <div class="stat-desc">Auto-logout after idle</div>
                </div>
            </div>

            <?php if (empty($active_sessions)): ?>
                <div class="empty-state">No admin sessions found.</div>
            <?php else: ?>
                <div class="sessions-grid">
                    <?php foreach ($active_sessions as $s):
                        $idle_sec = time() - phTimestamp($s['last_activity']);

                        // Classify session state
                        if ($idle_sec < 300) {
                            $state_class = 'badge-online';
                            $state_label = 'Online';
                            $show_dot    = true;
                        } elseif ($idle_sec < 1800) {
                            $state_class = 'badge-idle';
                            $state_label = 'Idle';
                            $show_dot    = false;
                        } else {
                            $state_class = 'badge-offline';
                            $state_label = 'Inactive';
                            $show_dot    = false;
                        }

                        $last_active = phTime($s['last_activity']);

                        $last_login = $s['last_login']
                            ? phTime($s['last_login'])
                            : 'Never';

                        // Format idle time as human-readable
                        if ($idle_sec < 60)        $idle_label = 'Just now';
                        elseif ($idle_sec < 3600)  $idle_label = floor($idle_sec / 60) . 'm ago';
                        elseif ($idle_sec < 86400) $idle_label = floor($idle_sec / 3600) . 'h ago';
                        else                       $idle_label = floor($idle_sec / 86400) . 'd ago';
                    ?>
                        <div class="session-card">
                            <div class="session-card-top">
                                <div class="session-avatar">
                                    <?= strtoupper(substr($s['name'], 0, 1)) ?>
                                    <?php if ($show_dot): ?>
                                        <div class="online-dot"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="session-meta">
                                    <div class="session-name"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="session-role">
                                        @<?= htmlspecialchars($s['username']) ?> ·
                                        <span style="color: <?= $s['role'] === 'superadmin' ? 'var(--amber)' : 'var(--text3)' ?>">
                                            <?= $s['role'] === 'superadmin' ? 'Super Admin' : 'Admin' ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="badge <?= $state_class ?>"><?= $state_label ?></span>
                            </div>
                            <div class="session-info">
                                <div class="session-row">
                                    <span class="key">Last Active</span>
                                    <span class="val"><?= $idle_label ?></span>
                                </div>
                                <div class="session-row">
                                    <span class="key">Last Login</span>
                                    <span class="val"><?= $last_login ?></span>
                                </div>
                                <div class="session-row">
                                    <span class="key">Timestamp</span>
                                    <span class="val"><?= $last_active ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>

</html>