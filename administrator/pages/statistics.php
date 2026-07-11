<?php
include '../../config_db.php';

// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
require_once '../includes/admin_auth_guard.php';

// PAGKUHA NG TOTAL VOTERS (DISTINCT STUDENT IDs IN VOTES TABLE)
$totalVotersRes = $conn->query("SELECT COUNT(DISTINCT student_id) AS total FROM votes");
$totalVoters = $totalVotersRes ? (int)$totalVotersRes->fetch_assoc()['total'] : 0;

// PAGKUHA NG LAHAT NG PARTYLISTS FOR CANDIDATE FORMS
$all_partylists = [];
$plRes = $conn->query("SELECT id, name FROM partylists ORDER BY name ASC");
while ($row = $plRes->fetch_assoc()) {
    $all_partylists[] = $row;
}
$partyNames = array_column($all_partylists, 'name');

// PAGKUHA NG VOTE COUNT PER PARTYLIST — LEFT JOIN to include those with zero votes
$partylistVotes = [];
$res = $conn->query("
    SELECT
        pl.name     AS partylist,
        COUNT(v.id) AS total
    FROM partylists pl
    LEFT JOIN candidates c ON c.partylist_id = pl.id
    LEFT JOIN votes v      ON v.candidate_id  = c.id
    GROUP BY pl.id, pl.name
    ORDER BY pl.name ASC
");
while ($row = $res->fetch_assoc()) {
    $partylistVotes[] = $row;
}

// PAGKUHA NG VOTE COUNT PER CANDIDATE, GROUPED BY POSITION — LEFT JOIN to include candidates with zero votes
$positionData = [];
$res = $conn->query("
    SELECT
        pos.id                                       AS pos_id,
        pos.position_name,
        c.id                                         AS cand_id,
        CONCAT(c.last_name, ', ', c.first_name)      AS cand_name,
        COALESCE(pl.name, 'Independent')             AS partylist,
        COUNT(v.id)                                  AS total
    FROM positions pos
    JOIN candidates c ON c.position_id = pos.id
    LEFT JOIN partylists pl ON c.partylist_id = pl.id
    LEFT JOIN votes v ON v.candidate_id = c.id
    GROUP BY pos.id, pos.position_name, c.id, c.last_name, c.first_name, pl.name
    ORDER BY pos.display_order ASC, pos.position_name ASC, 
             COUNT(v.id) DESC, c.last_name ASC
");
while ($row = $res->fetch_assoc()) {
    $pid = $row['pos_id'];
    if (!isset($positionData[$pid])) {
        $positionData[$pid] = [
            'name' => $row['position_name'],
            'candidates' => []
        ];
    }
    $positionData[$pid]['candidates'][] = [
        'name'      => $row['cand_name'],
        'partylist' => $row['partylist'],
        'votes'     => (int) $row['total'],
    ];
}

// PARA SA DEPARTMENT POPULATION CHART
$deptData = [];
$res = $conn->query("
    SELECT department, COUNT(*) AS total
    FROM students
    WHERE department IS NOT NULL AND department != ''
    GROUP BY department
    ORDER BY total DESC
");
while ($row = $res->fetch_assoc()) {
    $deptData[] = $row;
}

// PAG CHECK IF ELECTION HAS ENDED
$electionEnded = false;
$electionStatus = 'not_started';
$elRes = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_status' LIMIT 1");

if ($elRes && $row = $elRes->fetch_assoc()) {
    $electionStatus = strtolower(trim($row['setting_value']));
    $electionEnded = in_array($electionStatus, ['ended', 'closed', 'finish', 'completed', 'true', '1']);
}

// TOTAL VOTERS PER POSITION (for the report's "X students voted" line)
$positionVoterTotals = [];
$pvRes = $conn->query("
    SELECT 
        c.position_id AS pos_id,
        COUNT(DISTINCT v.student_id) AS total_voters
    FROM votes v
    JOIN candidates c ON v.candidate_id = c.id
    GROUP BY c.position_id
");
if ($pvRes) {
    while ($row = $pvRes->fetch_assoc()) {
        $positionVoterTotals[$row['pos_id']] = (int)$row['total_voters'];
    }
}

$totalVoters = 0;
$totalVotersRes = $conn->query("SELECT COUNT(DISTINCT student_id) AS total FROM votes");
if ($totalVotersRes) {
    $totalVoters = (int) $totalVotersRes->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Statistics Management - SOFTVOTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../styles/admin/statistics.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>


    <style>
        @page {
            margin: 0;
            size: auto;
        }

        @media print {
            body {
                margin: 15mm 15mm 15mm 15mm;
            }
        }

        /* Hide on screen */
        #print-winners-doc {
            display: none;
        }

        @media print {

            /* Force show print doc */
            #print-winners-doc {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
                opacity: 1 !important;
            }

            /* Hide everything else */
            .header-main,
            .main-wrapper,
            #globalColorCustomizer,
            #deptColorCustomizer,
            .tab-nav,
            .tab-panel,
            .color-customizer-widget {
                display: none !important;
                visibility: hidden !important;
            }

            body {
                margin: 15mm;
            }
        }
    </style>

</head>

<body>
    <?php include '../../logout_modal.php'; ?>

    <div class="header-main">
        <div class="header-container">
            <div class="header">
                <div class="logo-box">
                    <div class="logo-text">
                        <h2>Election Overview</h2>
                        <p>A summary of voting activity, participation rates, and election results.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/sidebar_menu.php'; ?>

    <div class="main-wrapper">

        <div class="tab-nav" id="tabNav">
            <button class="tab-btn active" data-tab="partylist">Partylist Votes</button>
            <button class="tab-btn" data-tab="candidates">Candidate Votes</button>
            <button class="tab-btn" data-tab="department">Department Population</button>
            <div class="tab-slider" id="tabSlider"></div>
        </div>

        <!-- PARTYLIST TAB -->
        <div class="tab-panel active" id="tab-partylist">
            <div class="tab-container">
                <h3>Partylist Vote Distribution</h3>
                <p>Total votes received by each partylist across all positions</p>
                <div class="analysis-grid">
                    <div class="chart-card">
                        <div class="pie-wrap"><canvas id="pieChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>Detailed Breakdown</h3>
                        <?php foreach ($partylistVotes as $pl): ?>
                            <div class="vote-box" id="vote-box-<?= htmlspecialchars($pl['partylist']) ?>">
                                <div class="vote-left">
                                    <span class="dot partylist-dot" data-party="<?= htmlspecialchars($pl['partylist']) ?>"></span>
                                    <?= htmlspecialchars(strtoupper($pl['partylist'])) ?>
                                </div>
                                <div class="vote-right">
                                    <strong class="partylist-vote-count" data-party="<?= htmlspecialchars($pl['partylist']) ?>"><?= $pl['total'] ?></strong>
                                    <small>votes</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- CANDIDATE VOTES TAB -->
        <div class="tab-panel" id="tab-candidates">
            <div class="tab-container">
                <h3>Candidate Vote Distribution</h3>
                <p>Votes received per candidate broken down by position</p>

                <div class="election-ended-banner">
                    <?php if ($electionEnded): ?>
                        <i data-lucide="lock" style="width: 20px; height: 20px;"></i>
                        Election has ended — final results are ready.
                    <?php elseif ($electionStatus === 'not_started'): ?>
                        <i data-lucide="clock" style="width: 20px; height: 20px;"></i>
                        Election has not started yet — no results available.
                    <?php else: ?>
                        <i data-lucide="bar-chart-2" style="width: 20px; height: 20px;"></i>
                        Election in progress — results shown may change.
                    <?php endif; ?>
                    <button class="print-report-btn" onclick="openPrintReport()">
                        <i data-lucide="printer" style="width: 20px; height: 20px;"></i> Print Official Results
                    </button>
                </div>

                <div class="candidates-grid">
                    <?php foreach ($positionData as $posId => $pos): ?>
                        <div class="chart-card position-card">
                            <div class="position-title">
                                <i data-lucide="award"></i>
                                <?= htmlspecialchars($pos['name']) ?>
                            </div>
                            <div class="pie-wrap"><canvas id="posChart<?= $posId ?>"></canvas></div>
                            <div class="position-legend">
                                <?php foreach ($pos['candidates'] as $c): ?>
                                    <span class="legend-item">
                                        <span class="dot-sm"></span>
                                        <?= htmlspecialchars($c['name']) ?> (<?= $c['votes'] ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- DEPARTMENT POPULATION TAB -->
        <div class="tab-panel" id="tab-department">
            <div class="tab-container">
                <h3>Department Population</h3>
                <p>Number of enrolled students per department</p>

                <div class="dept-layout">
                    <!-- Pie chart -->
                    <div class="chart-card dept-chart-card">
                        <div class="pie-wrap"><canvas id="deptChart"></canvas></div>
                    </div>

                    <!-- Department cards -->
                    <div class="dept-cards-grid">
                        <?php foreach ($deptData as $i => $dept):
                            $deptKey = 'dept_img_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($dept['department']));
                        ?>
                            <div class="dept-card">
                                <div class="dept-icon-wrap">
                                    <div class="dept-icon" id="icon-<?= htmlspecialchars($deptKey) ?>">
                                        <?= htmlspecialchars(strtoupper(substr($dept['department'], 0, 3))) ?>
                                    </div>
                                    <label class="dept-icon-edit-btn" title="Upload icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="17 8 12 3 7 8" />
                                            <line x1="12" y1="3" x2="12" y2="15" />
                                        </svg>
                                        <input type="file" accept="image/*" style="display:none"
                                            onchange="onDeptImageChange(this, '<?= htmlspecialchars($deptKey) ?>')">
                                    </label>
                                </div>
                                <div class="dept-name"><?= htmlspecialchars($dept['department']) ?></div>
                                <div class="dept-count"><?= number_format($dept['total']) ?></div>
                                <div class="dept-label">students</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- DEPARTMENT COLOR CUSTOMIZER (dept tab only) -->
        <div id="deptColorCustomizer" class="color-customizer-widget" style="display:none;">
            <div id="dccPanel" class="gcc-panel">
                <div class="gcc-head">
                    <div class="gcc-head-left">
                        <h4>Department Colors</h4>
                        <span class="gcc-badge dept-badge">DEPT TAB ONLY</span>
                    </div>
                    <p>Only affects department chart &amp; cards</p>
                </div>
                <div class="gcc-body">
                    <div class="gcc-section-label">Current Colors</div>
                    <div id="dccCustomRows"></div>
                    <div class="gcc-save-row">
                        <input class="gcc-preset-name-input" id="dccPresetNameInput"
                            type="text" placeholder="Name this palette…" maxlength="32">
                        <button class="gcc-save-btn dept-save-btn" id="dccSavePresetBtn">Save Preset</button>
                    </div>
                    <div class="gcc-section-label" style="margin-top:18px;">Saved Presets</div>
                    <div class="gcc-saved-list dept-preset-list" id="dccSavedList"></div>
                </div>
                <div class="gcc-footer">
                    <div class="gcc-preview-strip" id="dccPreviewStrip"></div>
                    <button class="gcc-reset-btn" id="dccResetBtn">↺ Reset</button>
                </div>
            </div>
            <button id="dccToggle" class="gcc-toggle-btn" title="Customize department chart colors">
                <i data-lucide="palette"></i>
            </button>
        </div>


    </div>
    <?php
    // Total enrolled students (always fetch, not just when election ended)
    $totalStudentsRes = $conn->query("SELECT COUNT(*) AS total FROM students");
    $totalStudents = $totalStudentsRes ? (int)$totalStudentsRes->fetch_assoc()['total'] : 0;
    ?>
    <div id="print-winners-doc">

        <!-- HEADER -->
        <div style="text-align:center; margin-bottom:24px; padding-bottom:16px; border-bottom:3px double #1e3a8a;">
            <h1 style="font-size:22px; font-weight:800; margin:0 0 4px; text-transform:uppercase; letter-spacing:1px; color:#1e3a8a;">
                Official Election Results
            </h1>
            <p style="font-size:13px; color:#444; margin:4px 0 2px;">Student Council Election 2026</p>
            <p style="font-size:11px; margin:4px 0 0; font-weight:600; color:<?= $electionEnded ? '#16a34a' : ($electionStatus === 'not_started' ? '#6b7280' : '#d97706') ?>;">
                <?php
                if ($electionEnded) {
                    echo '✔ FINAL RESULTS';
                } elseif ($electionStatus === 'not_started') {
                    echo '— Election has not started yet';
                } else {
                    echo '⚠ PRELIMINARY — Election still ongoing';
                }
                ?>
            </p>
        </div>

        <!-- PER-POSITION TALLY -->
        <?php foreach ($positionData as $posId => $pos):
            $posTotal     = array_sum(array_column($pos['candidates'], 'votes'));

            $votersForPos = $positionVoterTotals[$posId] ?? $posTotal;
            $didNotVote   = max(0, $totalStudents - $votersForPos);
            $sorted = $pos['candidates'];
            usort($sorted, fn($a, $b) => $b['votes'] - $a['votes']);
        ?>
            <div style="margin-bottom:28px; page-break-inside:avoid;">

                <!-- Position header -->
                <div style="background:#1e3a8a; color:#fff; padding:10px 16px;
                    display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; font-size:15px; text-transform:uppercase;">
                        <?= htmlspecialchars($pos['name']) ?>
                    </span>
                    <span style="font-size:12px; opacity:.85;">
                        <?= number_format($votersForPos) ?> of <?= number_format($totalStudents) ?> students voted
                    </span>
                </div>

                <!-- Candidate tally table -->
                <table style="width:100%; border-collapse:collapse; font-size:13px;
                      border:1px solid #cbd5e1; border-top:none;">
                    <thead>
                        <tr style="background:#f1f5f9;">
                            <th style="padding:9px 12px; text-align:left; border-bottom:1.5px solid #cbd5e1; width:30px;">#</th>
                            <th style="padding:9px 12px; text-align:left; border-bottom:1.5px solid #cbd5e1;">Candidate</th>
                            <th style="padding:9px 12px; text-align:left; border-bottom:1.5px solid #cbd5e1;">Partylist</th>
                            <th style="padding:9px 12px; text-align:center; border-bottom:1.5px solid #cbd5e1; width:80px;">Votes</th>
                            <th style="padding:9px 12px; text-align:center; border-bottom:1.5px solid #cbd5e1; width:80px;">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sorted as $rank => $c):
                            $isWinner = ($rank === 0 && $c['votes'] > 0);
                            $share    = $posTotal > 0 ? round($c['votes'] / $posTotal * 100, 1) : 0;
                            $rowBg    = $isWinner ? '#f0fdf4' : ($rank % 2 === 0 ? '#fff' : '#f8fafc');
                        ?>
                            <tr style="background:<?= $rowBg ?>;">
                                <td style="padding:10px 12px; border-bottom:1px solid #e2e8f0; text-align:center;
                                font-weight:700; color:#94a3b8; font-size:12px;">
                                    <?= $rank + 1 ?>
                                </td>

                                <td style="padding:10px 12px; border-bottom:1px solid #e2e8f0;">
                                    <div style="font-weight:<?= $isWinner ? '700' : '500' ?>; color:#0f172a; font-size:13px;">
                                        <?php if ($isWinner): ?>
                                            <span style="display:inline-block; background:#bbf7d0; color:#065f46;
                                             font-size:10px; font-weight:700; padding:1px 6px;
                                             border-radius:20px; margin-right:4px;">★ WINNER</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </div>
                                </td>

                                <td style="padding:10px 12px; border-bottom:1px solid #e2e8f0;
                                color:#64748b; font-size:12px;">
                                    <?= htmlspecialchars($c['partylist']) ?>
                                </td>

                                <td style="padding:10px 12px; border-bottom:1px solid #e2e8f0;
                                text-align:center; font-weight:800; font-size:15px;
                                color:<?= $isWinner ? '#16a34a' : '#1e40af' ?>;">
                                    <?= number_format($c['votes']) ?>
                                </td>

                                <td style="padding:10px 12px; border-bottom:1px solid #e2e8f0;
                                text-align:center; color:#64748b; font-size:12px;">
                                    <?= $share ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- SUBTOTAL -->
                        <tr style="background:#eff6ff;">
                            <td colspan="3" style="padding:10px 12px; font-weight:700; color:#1e3a8a;
                                           font-size:13px; border-top:1.5px solid #bfdbfe;">
                                TOTAL VOTES CAST — <?= htmlspecialchars(strtoupper($pos['name'])) ?>
                            </td>

                            <td style="padding:10px 12px; text-align:center; font-weight:800;
                                color:#1e3a8a; font-size:15px; border-top:1.5px solid #bfdbfe;">
                                <?= number_format($posTotal) ?>
                            </td>

                            <td style="padding:10px 12px; text-align:center; font-weight:700;
                                color:#1e3a8a; border-top:1.5px solid #bfdbfe;">
                                100%
                            </td>
                        </tr>

                        <!-- Cross-check -->
                        <tr style="background:#f0fdf4;">
                            <td colspan="3" style="padding:8px 12px; font-size:12px; color:#15803d; font-weight:600;">
                                TOTAL ENROLLED STUDENTS (for cross-check)
                            </td>

                            <td style="padding:8px 12px; text-align:center; font-weight:800;
                                font-size:14px; color:#15803d;">
                                <?= number_format($totalStudents) ?>
                            </td>

                            <td style="padding:8px 12px; font-size:11px; color:#64748b; text-align:center;">
                                <?= number_format($didNotVote) ?> did not vote
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <!-- FOOTER -->
        <div style="margin-top:32px; padding-top:12px; border-top:1px solid #cbd5e1;
                display:flex; justify-content:space-between; font-size:10px; color:#94a3b8;">
            <span>SOFTVOTE — Official Election System</span>
            <span>Results are final upon election closure • <?= date('Y') ?></span>
        </div>

    </div>


    <script>
        lucide.createIcons();

        function openPrintReport() {
            document.body.classList.add('printing-mode');
            setTimeout(() => window.print(), 150);
        }
        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-mode');
        });

        // SHARED CONSTANTS  
        const LS_COLORS = 'softvote_global_colors';
        const LS_DEPT_COLORS = 'softvote_dept_colors';
        const DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];
        const DEFAULT_DEPT_PALETTE = ['#065f46', '#047857', '#059669', '#10b981', '#34d399', '#6ee7b7', '#a7f3d0', '#d1fae5'];

        const partylistData = <?= json_encode($partylistVotes) ?>;
        const positionData = <?= json_encode(array_values($positionData)) ?>;
        const positionIds = <?= json_encode(array_keys($positionData)) ?>;
        const deptData = <?= json_encode($deptData) ?>;
        const partyNamesFromDB = <?= json_encode($partyNames) ?>;

        // LOAD COLORS FROM LOCALSTORAGE (set by manage_candidates color editor) ─
        function loadColors() {
            try {
                const s = localStorage.getItem(LS_COLORS);
                if (s) {
                    const a = JSON.parse(s);
                    if (Array.isArray(a) && a.length) return a;
                }
            } catch (e) {}
            return [...DEFAULT_PALETTE];
        }

        function loadDeptColors() {
            try {
                const s = localStorage.getItem(LS_DEPT_COLORS);
                if (s) {
                    const parsed = JSON.parse(s);
                    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                        return deptData.map((d, i) =>
                            parsed[d.department] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]
                        );
                    }
                    if (Array.isArray(parsed) && parsed.length) {
                        return deptData.map((_, i) =>
                            parsed[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]
                        );
                    }
                }
            } catch (e) {}
            return deptData.map((_, i) => DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]);
        }

        // COLOR HELPERS  
        let globalColors = loadColors();
        let deptColors = loadDeptColors();

        function colorsFor(n) {
            const out = [];
            for (let i = 0; i < n; i++) out.push(globalColors[i % globalColors.length]);
            return out;
        }

        function deptColorsFor(n) {
            const out = [];
            for (let i = 0; i < n; i++) out.push(deptColors[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]);
            return out;
        }

        // APPLY GLOBAL COLORS (partylist + candidate charts)  
        function applyColorsEverywhere() {
            const colors = colorsFor(partylistData.length);

            // Partylist pie chart
            if (pieChartInst) {
                pieChartInst.data.datasets[0].backgroundColor = colors;
                pieChartInst.update('none');
            }

            // Partylist breakdown dots
            partylistData.forEach((pl, i) => {
                const dot = document.querySelector(`.partylist-dot[data-party="${CSS.escape(pl.partylist)}"]`);
                if (dot) dot.style.background = colors[i] ?? DEFAULT_PALETTE[0];
            });

            // Build partylist → color map
            const partyColorMap = {};
            partyNamesFromDB.forEach((name, i) => {
                partyColorMap[name] = globalColors[i % globalColors.length];
            });
            partyColorMap['Independent'] = globalColors[partyNamesFromDB.length % globalColors.length];

            // Candidate charts
            positionIds.forEach((posId, idx) => {
                const pos = positionData[idx];
                if (!pos) return;
                const total = pos.candidates.reduce((sum, c) => sum + c.votes, 0);
                const hasVotes = total > 0;
                const chartColors = hasVotes ?
                    pos.candidates.map(c => partyColorMap[c.partylist] ?? globalColors[0]) : ['#e5e7eb'];

                const inst = candChartInsts[posId];
                if (inst) {
                    inst.data.datasets[0].backgroundColor = chartColors;
                    inst.update('none');
                }

                const card = document.getElementById('posChart' + posId)?.closest('.position-card');
                if (card) {
                    card.querySelectorAll('.dot-sm').forEach((d, i) => {
                        d.style.background = hasVotes ?
                            (partyColorMap[pos.candidates[i]?.partylist] ?? '#d1d5db') :
                            '#d1d5db';
                    });
                }
            });
        }

        // APPLY DEPT COLORS  
        function applyDeptColorsEverywhere() {
            if (deptChartInst) {
                deptChartInst.data.datasets[0].backgroundColor = deptColorsFor(deptData.length);
                deptChartInst.update('none');
            }
            document.querySelectorAll('#tab-department .dept-icon').forEach((icon, i) => {
                if (!icon.querySelector('img')) icon.style.background = deptColors[i % deptColors.length];
            });
        }

        // TAB NAVIGATION   
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');
        const slider = document.getElementById('tabSlider');
        const tabNav = document.getElementById('tabNav');

        function moveSlider(btn) {
            if (!slider || !btn || !tabNav) return;
            slider.style.left = btn.offsetLeft + 'px';
            slider.style.width = btn.offsetWidth + 'px';
        }

        function activateTab(btn) {
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
            moveSlider(btn);

            updateCustomizerVisibility(btn.dataset.tab);

            if (btn.dataset.tab === 'candidates' && !window.candidatesInited) {
                window.candidatesInited = true;
                initCandidateCharts();
                applyColorsEverywhere();
            } else if (btn.dataset.tab === 'candidates') {
                Object.values(candChartInsts).forEach(c => c?.resize());
            }

            if (btn.dataset.tab === 'department' && !window.deptInited) {
                window.deptInited = true;
                initDeptChart();
                applyDeptColorsEverywhere();
                buildDccPickerRows();
                buildDccPreviewStrip();
                renderDeptSavedPresets();
                lucide.createIcons();
            } else if (btn.dataset.tab === 'department') {
                deptChartInst?.resize();
                syncDccPickerUI();
            }
        }
        tabBtns.forEach(btn => btn.addEventListener('click', () => activateTab(btn)));

        function initSlider() {
            const active = tabNav.querySelector('.tab-btn.active');
            if (active) moveSlider(active);
        }
        requestAnimationFrame(() => requestAnimationFrame(initSlider));
        window.addEventListener('load', initSlider);
        window.addEventListener('resize', initSlider);
        new MutationObserver(() => setTimeout(initSlider, 350))
            .observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });

        // CHARTS  
        let pieChartInst = null;
        let deptChartInst = null;
        const candChartInsts = {};

        function initPartylistChart() {
            const labels = partylistData.map(p => p.partylist.toUpperCase());
            const data = partylistData.map(p => parseInt(p.total) || 0);
            const colors = colorsFor(partylistData.length);

            // Sync dots immediately
            partylistData.forEach((pl, i) => {
                const dot = document.querySelector(`.partylist-dot[data-party="${CSS.escape(pl.partylist)}"]`);
                if (dot) dot.style.background = colors[i] ?? DEFAULT_PALETTE[0];
            });

            pieChartInst = new Chart(document.getElementById('pieChart'), {
                type: 'pie',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    return ` ${ctx.label}: ${ctx.parsed} votes (${total ? Math.round(ctx.parsed / total * 100) : 0}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function initCandidateCharts() {
            const partyColorMap = {};
            partyNamesFromDB.forEach((name, i) => {
                partyColorMap[name] = globalColors[i % globalColors.length];
            });
            partyColorMap['Independent'] = globalColors[partyNamesFromDB.length % globalColors.length];

            positionData.forEach((pos, idx) => {
                const posId = positionIds[idx];
                const canvas = document.getElementById('posChart' + posId);
                if (!canvas) return;

                const rawData = pos.candidates.map(c => c.votes);
                const total = rawData.reduce((a, b) => a + b, 0);
                const hasVotes = total > 0;
                const card = canvas.closest('.position-card');

                const chartColors = hasVotes ?
                    pos.candidates.map(c => partyColorMap[c.partylist] ?? globalColors[0]) : ['#e5e7eb'];

                card?.querySelectorAll('.dot-sm').forEach((d, i) => {
                    d.style.background = hasVotes ?
                        (partyColorMap[pos.candidates[i]?.partylist] ?? '#d1d5db') :
                        '#d1d5db';
                });

                candChartInsts[posId] = new Chart(canvas, {
                    type: 'pie',
                    data: {
                        labels: hasVotes ? pos.candidates.map(c => c.name) : ['No votes yet'],
                        datasets: [{
                            data: hasVotes ? rawData : [1],
                            backgroundColor: chartColors,
                            borderWidth: hasVotes ? 2 : 0,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => {
                                        if (!hasVotes) return ' No votes yet';
                                        const pct = total > 0 ? Math.round(rawData[ctx.dataIndex] / total * 100) : 0;
                                        return ` ${ctx.label}: ${rawData[ctx.dataIndex]} votes (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            });
        }

        function initDeptChart() {
            const labels = deptData.map(d => d.department);
            const data = deptData.map(d => parseInt(d.total));
            const colors = deptColorsFor(deptData.length);

            document.querySelectorAll('#tab-department .dept-icon').forEach((icon, i) => {
                if (!icon.querySelector('img')) icon.style.background = colors[i] || DEFAULT_DEPT_PALETTE[0];
            });

            deptChartInst = new Chart(document.getElementById('deptChart'), {
                type: 'pie',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    return ` ${ctx.label}: ${ctx.parsed} (${total ? Math.round(ctx.parsed / total * 100) : 0}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // DEPARTMENT ICON UPLOAD  
        function restoreDeptImages() {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (!key.startsWith('dept_img_')) continue;
                const dataUrl = localStorage.getItem(key);
                if (dataUrl) applyDeptImage(key, dataUrl);
            }
        }

        function onDeptImageChange(input, deptKey) {
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => {
                try {
                    localStorage.setItem(deptKey, e.target.result);
                } catch (err) {}
                applyDeptImage(deptKey, e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }

        function applyDeptImage(deptKey, dataUrl) {
            const icon = document.getElementById('icon-' + deptKey);
            if (icon) icon.innerHTML = `<img src="${dataUrl}" alt="dept icon">`;
        }

        // DEPT COLOR CUSTOMIZER UI  
        const LS_DEPT_PRESETS = 'softvote_dept_saved_presets';
        const MAX_DEPT_SLOTS = Math.max(deptData.length, 1);

        function saveDeptColors() {
            try {
                const colorMap = {};
                deptData.forEach((d, i) => {
                    colorMap[d.department] = deptColors[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length];
                });
                localStorage.setItem(LS_DEPT_COLORS, JSON.stringify(colorMap));
            } catch (e) {}
        }

        function loadDeptPresets() {
            try {
                const s = localStorage.getItem(LS_DEPT_PRESETS);
                if (s) {
                    const a = JSON.parse(s);
                    if (Array.isArray(a)) return a;
                }
            } catch (e) {}
            return [];
        }

        function saveDeptPresetsToDisk() {
            try {
                localStorage.setItem(LS_DEPT_PRESETS, JSON.stringify(deptSavedPresets));
            } catch (e) {}
        }

        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        let deptSavedPresets = loadDeptPresets();
        let deptActivePreset = null;

        function buildDccPickerRows() {
            const container = document.getElementById('dccCustomRows');
            if (!container) return;
            container.innerHTML = '';
            const rowCount = Math.min(MAX_DEPT_SLOTS, 8);
            for (let i = 0; i < rowCount; i++) {
                const color = deptColors[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length];
                const label = deptData[i] ? deptData[i].department.toUpperCase() : `Slot ${i + 1}`;
                const row = document.createElement('div');
                row.className = 'gcc-custom-row';
                row.innerHTML = `
                    <div class="gcc-custom-label">${escHtml(label)}</div>
                    <div class="gcc-swatch-btn" id="dccSwatchBtn${i}"
                         style="background:${color}"
                         onclick="document.getElementById('dccPicker${i}').click()"></div>
                    <input class="gcc-hex" id="dccHex${i}" type="text" value="${color}"
                           oninput="onDccHex(${i}, this.value)">
                    <input type="color" id="dccPicker${i}" value="${color}"
                           style="display:none" oninput="onDccPick(${i}, this.value)">`;
                container.appendChild(row);
            }
        }

        function buildDccPreviewStrip() {
            const strip = document.getElementById('dccPreviewStrip');
            if (!strip) return;
            strip.innerHTML = '';
            const n = Math.min(deptColors.length, MAX_DEPT_SLOTS, 8);
            for (let i = 0; i < n; i++) {
                const seg = document.createElement('div');
                seg.className = 'gcc-preview-seg';
                seg.id = 'dccPreviewSeg' + i;
                seg.style.background = deptColors[i];
                strip.appendChild(seg);
            }
        }

        function syncDccPickerUI() {
            const rowCount = Math.min(MAX_DEPT_SLOTS, 8);
            for (let i = 0; i < rowCount; i++) {
                const color = deptColors[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length];
                const swatch = document.getElementById('dccSwatchBtn' + i);
                const hex = document.getElementById('dccHex' + i);
                const picker = document.getElementById('dccPicker' + i);
                const seg = document.getElementById('dccPreviewSeg' + i);
                if (swatch) swatch.style.background = color;
                if (hex) hex.value = color;
                if (picker) picker.value = color;
                if (seg) seg.style.background = color;
            }
            document.querySelectorAll('#dccSavedList .gcc-saved-item').forEach(el => {
                el.classList.toggle('active', el.dataset.presetName === deptActivePreset);
            });
        }

        function renderDeptSavedPresets() {
            const list = document.getElementById('dccSavedList');
            if (!list) return;
            list.innerHTML = '';
            if (!deptSavedPresets.length) {
                list.innerHTML = `<div class="gcc-empty-presets">No saved presets yet.<br>Pick your colors above, then hit <strong>Save Preset</strong>.</div>`;
                return;
            }
            deptSavedPresets.forEach((preset, idx) => {
                const item = document.createElement('div');
                item.className = 'gcc-saved-item' + (preset.name === deptActivePreset ? ' active' : '');
                item.dataset.presetName = preset.name;
                const colors = Array.isArray(preset.colors) ?
                    preset.colors :
                    Object.values(preset.colors);
                const swatches = colors.slice(0, 6).map(c => `<div class="gcc-saved-swatch" style="background:${c}"></div>`).join('');
                item.innerHTML = `
                    <div class="gcc-saved-swatches">${swatches}</div>
                    <div class="gcc-saved-name">${escHtml(preset.name)}</div>
                    <div class="gcc-saved-actions">
                        <button class="gcc-saved-apply">Apply</button>
                        <button class="gcc-saved-delete" title="Delete">×</button>
                    </div>`;
                item.querySelector('.gcc-saved-apply').addEventListener('click', e => {
                    e.stopPropagation();
                    applyDeptPreset(idx);
                });
                item.querySelector('.gcc-saved-delete').addEventListener('click', e => {
                    e.stopPropagation();
                    deleteDeptPreset(idx);
                });
                item.addEventListener('click', () => applyDeptPreset(idx));
                list.appendChild(item);
            });
        }

        function applyDeptPreset(idx) {
            const preset = deptSavedPresets[idx];
            if (!preset) return;
            deptActivePreset = preset.name;
            const isNameKeyed = preset.colors && !Array.isArray(preset.colors);
            if (isNameKeyed) {
                deptColors = deptData.map((d, i) =>
                    preset.colors[d.department] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]);
            } else {
                const arr = Array.isArray(preset.colors) ? preset.colors : [];
                deptColors = deptData.map((d, i) => arr[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]);
            }
            applyDeptColorsEverywhere();
            saveDeptColors();
            syncDccPickerUI();
            renderDeptSavedPresets();
        }

        function deleteDeptPreset(idx) {
            const deletedName = deptSavedPresets[idx]?.name;
            deptSavedPresets.splice(idx, 1);
            if (deptActivePreset === deletedName) deptActivePreset = null;
            saveDeptPresetsToDisk();
            renderDeptSavedPresets();
            syncDccPickerUI();
        }

        function onDccHex(i, val) {
            if (!/^#[0-9a-fA-F]{6}$/.test(val)) return;
            deptColors[i] = val;
            deptActivePreset = null;
            applyDeptColorsEverywhere();
            saveDeptColors();
            syncDccPickerUI();
        }

        function onDccPick(i, val) {
            deptColors[i] = val;
            deptActivePreset = null;
            applyDeptColorsEverywhere();
            saveDeptColors();
            syncDccPickerUI();
        }

        document.getElementById('dccToggle')?.addEventListener('click', () => {
            document.getElementById('dccPanel').classList.toggle('open');
        });

        document.getElementById('dccResetBtn')?.addEventListener('click', () => {
            deptColors = deptData.map((_, i) => DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length]);
            deptActivePreset = null;
            applyDeptColorsEverywhere();
            saveDeptColors();
            syncDccPickerUI();
            renderDeptSavedPresets();
        });

        document.getElementById('dccSavePresetBtn')?.addEventListener('click', () => {
            const input = document.getElementById('dccPresetNameInput');
            const name = input.value.trim();
            if (!name) {
                input.classList.add('error');
                input.focus();
                setTimeout(() => input.classList.remove('error'), 1200);
                return;
            }
            const colorMap = {};
            deptData.forEach((d, i) => {
                colorMap[d.department] = deptColors[i] ?? DEFAULT_DEPT_PALETTE[i % DEFAULT_DEPT_PALETTE.length];
            });
            const existing = deptSavedPresets.findIndex(p => p.name === name);
            const entry = {
                name,
                colors: colorMap
            };
            if (existing >= 0) deptSavedPresets[existing] = entry;
            else deptSavedPresets.push(entry);
            deptActivePreset = name;
            saveDeptPresetsToDisk();
            renderDeptSavedPresets();
            syncDccPickerUI();
            input.value = '';
        });

        document.getElementById('dccPresetNameInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('dccSavePresetBtn').click();
        });

        document.addEventListener('click', e => {
            const deptWidget = document.getElementById('deptColorCustomizer');
            if (deptWidget && !deptWidget.contains(e.target)) {
                document.getElementById('dccPanel')?.classList.remove('open');
            }
        });

        function updateCustomizerVisibility(tab) {
            const deptWidget = document.getElementById('deptColorCustomizer');
            if (!deptWidget) return;
            if (tab === 'department') {
                deptWidget.style.display = 'block';
                deptWidget.classList.add('visible');
            } else {
                deptWidget.style.display = 'none';
                deptWidget.classList.remove('visible');
                document.getElementById('dccPanel')?.classList.remove('open');
            }
        }

        // LISTEN FOR COLOR CHANGES FROM OTHER TABS (storage event)  
        // When manage_candidates saves to localStorage, this page auto-updates
        window.addEventListener('storage', e => {
            if (e.key === LS_COLORS) {
                globalColors = loadColors();
                applyColorsEverywhere();
            }
            if (e.key === LS_DEPT_COLORS) {
                deptColors = loadDeptColors();
                applyDeptColorsEverywhere();
            }
        });


        // INITIALIZATION 
        restoreDeptImages();
        window.addEventListener('DOMContentLoaded', () => {
            initPartylistChart();
            applyColorsEverywhere();

            // Detect whichever tab is active on load, not hardcoded 'partylist'
            const activeBtn = tabNav.querySelector('.tab-btn.active');
            const activeTab = activeBtn?.dataset.tab ?? 'partylist';
            updateCustomizerVisibility(activeTab);

            // If dept tab is active on load, initialize it too
            if (activeTab === 'department' && !window.deptInited) {
                window.deptInited = true;
                initDeptChart();
                applyDeptColorsEverywhere();
                buildDccPickerRows();
                buildDccPreviewStrip();
                renderDeptSavedPresets();
                lucide.createIcons();
            }

            if (activeBtn) moveSlider(activeBtn);
        });
    </script>
</body>

</html>