<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';

$active_nav = 'stats';

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

// FETCH VOTE TALLIES PER POSITION
$position_tallies = [];
$r = $conn->query("
    SELECT 
        p.id, 
        p.position_name AS position_name,   /* ← was p.name */
        c.id AS candidate_id, 
        CONCAT(c.last_name, ', ', c.first_name) AS candidate_name,  /* ← candidates have no 'name' column */
        COUNT(v.id) AS vote_count
    FROM positions p
    LEFT JOIN candidates c ON c.position_id = p.id
    LEFT JOIN votes v ON v.candidate_id = c.id
    GROUP BY p.id, c.id
    ORDER BY p.id, vote_count DESC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $pid = $row['id'];
        if (!isset($position_tallies[$pid])) {
            $position_tallies[$pid] = [
                'name' => $row['candidate_name'],
                'candidates' => [],
                'total'      => 0,
            ];
        }
        if ($row['candidate_id']) {
            $position_tallies[$pid]['candidates'][] = [
                'name'  => $row['candidate_name'],
                'votes' => (int)$row['vote_count'],
            ];
            $position_tallies[$pid]['total'] += (int)$row['vote_count'];
        }
    }
}

$turnout   = $total_students > 0 ? round(($voted_count / $total_students) * 100, 1) : 0;
$not_voted = $total_students - $voted_count;

$status_map   = [
    'ongoing' => ['Open',    '#3ecf8e', '#3ecf8e'],
    'ended'   => ['Ended',   '#f25c5c', '#f25c5c'],
    'pending' => ['Pending', '#f5a623', '#f5a623'],
];
$status_label = $status_map[$election_status] ?? ['Unknown', '#999', '#999'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Election Stats — SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/superadmin/dashboard.css">
    <link rel="stylesheet" href="../../styles/superadmin/statistics.css">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>

    <div class="shell">

        <?php require_once '../includes/_sidebar.php'; ?>

        <!-- MAIN -->
        <div class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <h1>Election <span>Statistics</span></h1>
                    <p>View-only · live vote tally per position</p>
                </div>
                <div class="election-pill"
                    style="color:<?= $status_label[1] ?>;border-color:<?= $status_label[2] ?>44;background:<?= $status_label[2] ?>11">
                    <?= htmlspecialchars($election_title) ?> · <?= $status_label[0] ?>
                </div>
            </div>

            <!-- Overall turnout summary -->
            <div class="stats-grid" style="margin-bottom:24px">
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Votes Cast</span>
                        <div class="stat-icon green"><i data-lucide="check-square"></i></div>
                    </div>
                    <div class="stat-num"><?= $voted_count ?></div>
                    <div class="stat-desc"><b><?= $turnout ?>%</b> turnout</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Positions</span>
                        <div class="stat-icon orange"><i data-lucide="award"></i></div>
                    </div>
                    <div class="stat-num"><?= $positions ?></div>
                    <div class="stat-desc">Contested seats</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Candidates</span>
                        <div class="stat-icon blue"><i data-lucide="user-check"></i></div>
                    </div>
                    <div class="stat-num"><?= $candidates ?></div>
                    <div class="stat-desc">Total running</div>
                </div>
                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Not Yet Voted</span>
                        <div class="stat-icon red"><i data-lucide="clock"></i></div>
                    </div>
                    <div class="stat-num"><?= $not_voted ?></div>
                    <div class="stat-desc">Pending ballots</div>
                </div>
            </div>

            <!-- Per-position tally -->
            <?php if (empty($position_tallies)): ?>
                <div class="empty-state">No election data available yet.</div>
            <?php else: ?>
                <div class="tally-wrap" id="tallyWrap">
                    <?php foreach ($position_tallies as $pos): ?>
                        <div class="tally-position">
                            <div class="tally-position-header">
                                <div class="tally-position-name">
                                    <i data-lucide="award"></i>
                                    <?= htmlspecialchars($pos['name']) ?>
                                </div>
                                <span class="tally-total"><?= $pos['total'] ?> votes cast</span>
                            </div>
                            <div class="tally-rows">
                                <?php if (empty($pos['candidates'])): ?>
                                    <div class="tally-empty">No candidates for this position.</div>
                                    <?php else:
                                    $rank_classes = ['first', 'second', 'third'];
                                    $rank = 0;
                                    foreach ($pos['candidates'] as $c):
                                        $rank++;
                                        $pct = $pos['total'] > 0
                                            ? round(($c['votes'] / $pos['total']) * 100, 1)
                                            : 0;
                                        $rank_class = $rank_classes[$rank - 1] ?? '';
                                    ?>
                                        <div class="tally-row">
                                            <div class="tally-rank <?= $rank_class ?>"><?= $rank ?></div>
                                            <div class="tally-candidate">
                                                <div class="tally-candidate-name"><?= htmlspecialchars($c['name']) ?></div>
                                                <div class="tally-bar-wrap">
                                                    <div class="tally-bar-track">
                                                        <div class="tally-bar-fill"
                                                            style="width:0%"
                                                            data-target="<?= $pct ?>%"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="tally-votes"><?= $c['votes'] ?></div>
                                            <div class="tally-pct"><?= $pct ?>%</div>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        lucide.createIcons();

        /* Animate tally bar fills on page load */
        window.addEventListener('load', () => {
            document.querySelectorAll('.tally-bar-fill').forEach(bar => {
                bar.style.width = bar.dataset.target;
            });
        });
    </script>
</body>

</html>