<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';

$active_nav = 'dashboard';
$sa_name = $_SESSION['sa_name'] ?? 'Superadmin';

$total_admins = $conn->query("SELECT COUNT(*) AS c FROM administrators WHERE is_deleted = 0")->fetch_assoc()['c'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'] ?? 0;
$voted_count    = $conn->query("SELECT COUNT(*) AS c FROM students WHERE has_voted = 1")->fetch_assoc()['c'] ?? 0;
$positions      = $conn->query("SELECT COUNT(*) AS c FROM positions")->fetch_assoc()['c'] ?? 0;
$candidates     = $conn->query("SELECT COUNT(*) AS c FROM candidates")->fetch_assoc()['c'] ?? 0;

$election_status = 'unknown';
$election_title  = 'Election';
$r = $conn->query("SELECT setting_key, setting_value FROM election_settings WHERE setting_key IN ('election_status','election_title')");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['setting_key'] === 'election_status') $election_status = $row['setting_value'];
        if ($row['setting_key'] === 'election_title')  $election_title  = $row['setting_value'];
    }
}

// RENAME THIS TABLE TO audit_logs LATER
$audit_logs = [];
$r = $conn->query("SELECT actor_name, actor_type, action, detail, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $audit_logs[] = $row;

$turnout   = $total_students > 0 ? round(($voted_count / $total_students) * 100, 1) : 0;
$not_voted = $total_students - $voted_count;

$status_map = [
    'ongoing' => ['Open',    '#3ecf8e', '#3ecf8e'],
    'ended'   => ['Ended',   '#f25c5c', '#f25c5c'],
    'pending' => ['Pending', '#f5a623', '#f5a623'],
];
$status_label = $status_map[$election_status] ?? ['Unknown', '#999', '#999'];

// TOASTS FROM REDIRECT
$toast_success = htmlspecialchars($_GET['success'] ?? '');
$toast_error   = htmlspecialchars($_GET['error']   ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard — SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/superadmin/dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="shell">

        <?php require_once '../includes/_sidebar.php'; ?>

        <!-- MAIN -->
        <div class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h1>Dashboard <span>Overview</span></h1>
                    <p>Welcome back, <?= htmlspecialchars($sa_name) ?></p>
                </div>
                <div class="election-pill"
                    style="color:<?= $status_label[1] ?>;border-color:<?= $status_label[2] ?>44;background:<?= $status_label[2] ?>11">
                    <?= htmlspecialchars($election_title) ?> · <?= $status_label[0] ?>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Administrators</span>
                        <div class="stat-icon orange"><i data-lucide="users"></i></div>
                    </div>
                    <div class="stat-num" id="cnt-admins">0</div>
                    <div class="stat-desc">Active admin accounts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Registered Students</span>
                        <div class="stat-icon blue"><i data-lucide="user"></i></div>
                    </div>
                    <div class="stat-num" id="cnt-students">0</div>
                    <div class="stat-desc">Total eligible voters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Votes Cast</span>
                        <div class="stat-icon green"><i data-lucide="check-square"></i></div>
                    </div>
                    <div class="stat-num" id="cnt-voted">0</div>
                    <div class="stat-desc"><b><?= $turnout ?>%</b> turnout rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Not Yet Voted</span>
                        <div class="stat-icon red"><i data-lucide="clock"></i></div>
                    </div>
                    <div class="stat-num" id="cnt-remaining">0</div>
                    <div class="stat-desc">Pending ballots</div>
                </div>
            </div>

            <div class="panel-row">
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title"><i data-lucide="bar-chart-2"></i>Voter Turnout</div>
                        <span class="panel-badge">Live</span>
                    </div>
                    <div class="panel-body">
                        <div class="progress-meta">
                            <span>Students voted</span>
                            <b><?= $voted_count ?> / <?= $total_students ?></b>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" id="turnout-bar"></div>
                        </div>
                        <div class="progress-note"><?= $turnout ?>% participation · <?= $not_voted ?> students have not voted</div>
                        <div class="mini-stats">
                            <div class="mini-stat">
                                <div class="num"><?= $positions ?></div>
                                <div class="lbl">Positions</div>
                            </div>
                            <div class="mini-stat">
                                <div class="num"><?= $candidates ?></div>
                                <div class="lbl">Candidates</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title"><i data-lucide="activity"></i>Recent Activity</div>
                        <span class="panel-badge"><?= count($audit_logs) ?> entries</span>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($audit_logs)): ?>
                            <div class="empty-state">No audit logs yet.</div>
                        <?php else: ?>
                            <div class="log-list">
                                <?php foreach ($audit_logs as $log):
                                    $dot  = str_contains($log['action'], 'login')  ? 'login'
                                        : (str_contains($log['action'], 'delete') ? 'delete'
                                            : (str_contains($log['action'], 'create') ? 'create' : 'default'));
                                    $time = date('h:i A', strtotime($log['created_at']));
                                ?>
                                    <div class="log-item">
                                        <div class="log-dot <?= $dot ?>"></div>
                                        <div>
                                            <div class="log-action"><?= htmlspecialchars($log['actor_name']) ?></div>
                                            <div class="log-meta"><?= htmlspecialchars($log['action']) ?> · <?= htmlspecialchars($log['ip_address'] ?? '—') ?></div>
                                        </div>
                                        <div class="log-time"><?= $time ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        /* Animated counters */
        function animateCount(id, target) {
            const el = document.getElementById(id);
            if (!el) return;
            let cur = 0;
            const step = Math.max(1, Math.ceil(target / 40));
            const t = setInterval(() => {
                cur = Math.min(cur + step, target);
                el.textContent = cur.toLocaleString();
                if (cur >= target) clearInterval(t);
            }, 28);
        }

        window.addEventListener('load', () => {
            animateCount('cnt-admins', <?= (int)$total_admins ?>);
            animateCount('cnt-students', <?= (int)$total_students ?>);
            animateCount('cnt-voted', <?= (int)$voted_count ?>);
            animateCount('cnt-remaining', <?= (int)$not_voted ?>);

            setTimeout(() => {
                const bar = document.getElementById('turnout-bar');
                if (bar) bar.style.width = '<?= $turnout ?>%';
            }, 350);

            // Show toasts from redirect
            <?php if ($toast_success): ?>
                showToast('success', <?= json_encode($toast_success) ?>);
            <?php endif; ?>
            <?php if ($toast_error): ?>
                showToast('error', <?= json_encode($toast_error) ?>);
            <?php endif; ?>
        });

        /* Toast */
        function showToast(type, msg) {
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.innerHTML = `<i data-lucide="${icon}"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(el);
            lucide.createIcons();
            setTimeout(() => el.remove(), 4000);
        }
    </script>
</body>

</html>