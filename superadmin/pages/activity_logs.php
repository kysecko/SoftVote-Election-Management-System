<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';
$conn->query("SET time_zone = '+08:00'");

$active_nav = 'logs';

function phTime($datetime, $format = 'M d, Y h:i A')
{
    if (!$datetime) return 'Never';
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}

function phTimestamp($datetime)
{
    if (!$datetime) return 0;
    $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    return $dt->getTimestamp();
}

$per_page     = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$total_logs   = (int)$conn->query("SELECT COUNT(*) AS c FROM audit_logs")->fetch_assoc()['c'];
$total_pages  = max(1, (int)ceil($total_logs / $per_page));

$audit_logs = [];
$r = $conn->query("SELECT actor_name, actor_type, action, detail, ip_address, created_at 
                   FROM audit_logs 
                   ORDER BY created_at DESC 
                   LIMIT $per_page OFFSET $offset");

if ($r) while ($row = $r->fetch_assoc()) $audit_logs[] = $row;

$toast_success = htmlspecialchars($_GET['success'] ?? '');
$toast_error   = htmlspecialchars($_GET['error'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Audit Logs — SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/superadmin/dashboard.css">
    <link rel="stylesheet" href="../../styles/superadmin/activity_logs.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>


</head>

<body>

    <div class="toast-container" id="toastContainer"></div>

    <div class="shell">
        <?php require_once '../includes/_sidebar.php'; ?>

        <div class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h1>Audit <span>Logs</span></h1>
                    <p>Full system activity trail · superadmin-only view</p>
                </div>
            </div>

            <div class="table-wrap">
                <div class="table-header">
                    <div class="table-title"><i data-lucide="scroll-text"></i>All Activity</div>
                    <span class="panel-badge"><?= $total_logs ?> records</span>
                </div>

                <div class="table-toolbar">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="logSearch" placeholder="Search by actor, action, or detail…" oninput="filterLogs()">
                    </div>
                    <select class="filter-select" id="logTypeFilter" onchange="filterLogs()">
                        <option value="">All Types</option>
                        <option value="superadmin">Super Admin</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="table-container">
                    <?php if (empty($audit_logs)): ?>
                        <div class="empty-state">No audit logs recorded yet.</div>
                    <?php else: ?>
                        <table id="logTable">
                            <thead>
                                <tr>
                                    <th>Actor</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                    <th>Detail</th>
                                    <th>IP Address</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_logs as $log): ?>
                                    <tr data-actor="<?= strtolower(htmlspecialchars($log['actor_name'])) ?>"
                                        data-action="<?= strtolower(htmlspecialchars($log['action'])) ?>"
                                        data-detail="<?= strtolower(htmlspecialchars($log['detail'] ?? '')) ?>"
                                        data-type="<?= htmlspecialchars($log['actor_type']) ?>">
                                        <td><span class="td-name"><?= htmlspecialchars($log['actor_name']) ?></span></td>
                                        <td>
                                            <span class="badge <?= $log['actor_type'] === 'superadmin' ? 'badge-super' : 'badge-admin' ?>">
                                                <?= $log['actor_type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $ac  = $log['action'];
                                            $cls = str_contains($ac, 'delete') ? 'badge-delete'
                                                : (str_contains($ac, 'create') ? 'badge-action' : 'badge-admin');
                                            ?>
                                            <span class="badge <?= $cls ?>"><?= htmlspecialchars($ac) ?></span>
                                        </td>
                                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text2)">
                                            <?= htmlspecialchars($log['detail'] ?? '—') ?>
                                        </td>
                                        <td class="td-mono"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                                        <td class="td-date"><?= phTime($log['created_at'], 'M d, Y h:i A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <?php
                    $from = $offset + 1;
                    $to   = min($offset + $per_page, $total_logs);
                    $params = $_GET;
                    unset($params['page']);
                    $base = '?' . http_build_query($params);
                    $base = $base === '?' ? '?' : $base . '&';

                    $is_first = $current_page === 1;
                    $is_last  = $current_page === $total_pages;

                    $window_start = max(1, $current_page - 2);
                    $window_end   = min($total_pages, $current_page + 2);
                    ?>

                    <div class="pagination">
                        <span class="pagination-info">
                            Showing <strong><?= $from ?>–<?= $to ?></strong> of <?= $total_logs ?> records
                        </span>

                        <div class="pagination-controls">

                            <!-- First -->
                            <a href="<?= $base ?>page=1" class="page-btn <?= $is_first ? 'disabled' : '' ?>" title="First page">
                                <i data-lucide="chevrons-left"></i>
                            </a>

                            <!-- Prev -->
                            <a href="<?= $base ?>page=<?= $current_page - 1 ?>"
                                class="page-btn-prev <?= $is_first ? 'disabled' : '' ?>">
                                <i data-lucide="chevron-left"></i> Prev
                            </a>

                            <!-- Numbered pages -->
                            <?php if ($window_start > 1): ?>
                                <a href="<?= $base ?>page=1" class="page-btn">1</a>
                                <?php if ($window_start > 2): ?>
                                    <span style="padding: 0 8px; color: var(--text3);">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($p = $window_start; $p <= $window_end; $p++): ?>
                                <a href="<?= $base ?>page=<?= $p ?>"
                                    class="page-btn <?= $p === $current_page ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($window_end < $total_pages): ?>
                                <?php if ($window_end < $total_pages - 1): ?>
                                    <span style="padding: 0 8px; color: var(--text3);">…</span>
                                <?php endif; ?>
                                <a href="<?= $base ?>page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a>
                            <?php endif; ?>

                            <!-- Next - Solid Blue -->
                            <a href="<?= $base ?>page=<?= $current_page + 1 ?>"
                                class="page-btn-next <?= $is_last ? 'disabled' : '' ?>">
                                Next <i data-lucide="chevron-right"></i>
                            </a>

                            <!-- Last -->
                            <a href="<?= $base ?>page=<?= $total_pages ?>"
                                class="page-btn <?= $is_last ? 'disabled' : '' ?>" title="Last page">
                                <i data-lucide="chevrons-right"></i>
                            </a>

                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function showToast(type, msg) {
            const icon = type === 'success' ? 'check-circle' : 'alert-circle';
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.innerHTML = `<i data-lucide="${icon}"></i><span>${msg}</span>`;
            document.getElementById('toastContainer').appendChild(el);
            lucide.createIcons();
            setTimeout(() => el.remove(), 4000);
        }

        window.addEventListener('load', () => {
            <?php if ($toast_success): ?>showToast('success', <?= json_encode($toast_success) ?>);
        <?php endif; ?>
        <?php if ($toast_error): ?>showToast('error', <?= json_encode($toast_error) ?>);
        <?php endif; ?>
        });

        function filterLogs() {
            const q = document.getElementById('logSearch').value.toLowerCase();
            const type = document.getElementById('logTypeFilter').value;
            document.querySelectorAll('#logTable tbody tr').forEach(row => {
                const textMatch = row.dataset.actor.includes(q) ||
                    row.dataset.action.includes(q) ||
                    row.dataset.detail.includes(q);
                const typeMatch = !type || row.dataset.type === type;
                row.style.display = (textMatch && typeMatch) ? '' : 'none';
            });
        }
    </script>
</body>

</html>