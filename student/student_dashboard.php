<?php
session_start();
require_once '../config_db.php';

// PAG CHECK NG ELECTION STATUS MUNA BAGO MA-ACCESS ANG DASHBOARD
$res = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_status' LIMIT 1");
$real_status = $res ? $res->fetch_assoc()['setting_value'] : 'not_started';

if ($real_status === 'not_started' || $real_status === 'ended') {
    session_destroy();
    header("Location: ../election_status.php?status=" . $real_status);
    exit();
}

// PAG CHECK NG SESSION PARA SA STUDENT DASHBOARD ACCESS
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: ../auth/student_login.php");
    exit();
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_destroy();
    header("Location: ../auth/student_login.php?error=Session+expired");
    exit();
}

$voted_check = $conn->prepare("SELECT has_voted FROM students WHERE student_id = ?");
$voted_check->bind_param("s", $_SESSION['student_id']);
$voted_check->execute();
$voted_row = $voted_check->get_result()->fetch_assoc();
$voted_check->close();

if ($voted_row && $voted_row['has_voted'] == 1) {
    $_SESSION['has_voted'] = 1;
    header("Location: voted_already.php");
    exit();
} else {
    $_SESSION['has_voted'] = 0;
}

// PAG FETCH NG MGA POSITION AT CANDIDATES PARA SA IDISPLAY SA DASHBOARD
$partyNames = [];
$plRes = $conn->query("SELECT id, name FROM partylists ORDER BY name ASC");
while ($row = $plRes->fetch_assoc()) {
    $partyNames[] = $row['name'];
}

// PAG JOIN NG POSITIONS, CANDIDATES, AT PARTYLISTS PARA SA MAS EFFICIENT NA PAGFETCH NG DATA
$positionsQuery = $conn->query("
    SELECT
        p.id            AS position_id,
        p.position_name,
        p.display_order,
        c.id            AS candidate_id,
        CONCAT(
            c.last_name, ', ',
            c.first_name,
            IF(c.middle_name IS NOT NULL AND c.middle_name != '',
               CONCAT(' ', LEFT(c.middle_name, 1), '.'), '')
        )               AS name,
        c.photo_url,
        COALESCE(pl.name, 'Independent') AS partylist
    FROM positions p
    LEFT JOIN candidates c   ON p.id = c.position_id
    LEFT JOIN partylists pl  ON c.partylist_id = pl.id
    ORDER BY p.display_order ASC, pl.name ASC, c.id ASC
");

$positions = [];

while ($row = $positionsQuery->fetch_assoc()) {
    if (empty($row['candidate_id'])) continue;

    $positionId = $row['position_id'];
    if (!isset($positions[$positionId])) {
        $positions[$positionId] = [
            'position_name' => $row['position_name'],
            'display_order' => $row['display_order'],
            'candidates'    => [],
        ];
    }
    $positions[$positionId]['candidates'][] = $row;
}

//I CHECK KUNG MAY INDEPENDENT CANDIDATES PARA MA-ADD ANG INDEPENDENT SA PARTY NAMES KUNG WALA PA
$hasIndependent = false;
foreach ($positions as $pos) {
    foreach ($pos['candidates'] as $c) {
        if ($c['partylist'] === 'Independent') {
            $hasIndependent = true;
            break 2;
        }
    }
}
if ($hasIndependent && !in_array('Independent', $partyNames)) {
    $partyNames[] = 'Independent';
}

$total_positions = $conn->query("SELECT COUNT(*) AS count FROM positions")->fetch_assoc()['count'];
$election_title  = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_title'")->fetch_assoc()['setting_value'] ?? 'Student Council Election';

$errors = [];
if (isset($_SESSION['vote_errors'])) {
    $errors = $_SESSION['vote_errors'];
    unset($_SESSION['vote_errors']);
}

$partyColorMap = [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Voting Dashboard - SOFTVOTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../styles/student/dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <script>
        (function() {
            const PL_COLOR_LS_KEY = 'softvote_global_colors';
            const PL_DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];
            const partylistNames = <?= json_encode($partyNames) ?>;

            let colorList;
            try {
                const saved = localStorage.getItem(PL_COLOR_LS_KEY);
                const arr = saved ? JSON.parse(saved) : null;
                colorList = (Array.isArray(arr) && arr.length) ?
                    arr : partylistNames.map((_, i) => PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length]);
                while (colorList.length < partylistNames.length)
                    colorList.push(PL_DEFAULT_PALETTE[colorList.length % PL_DEFAULT_PALETTE.length]);
            } catch (e) {
                colorList = partylistNames.map((_, i) => PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length]);
            }

            window.__partyColors = {};
            partylistNames.forEach((name, i) => {
                window.__partyColors[name] = colorList[i];
            });
        })();
    </script>
</head>

<body>
    <!-- HEADER -->
    <header class="main-header">
        <div class="header-inner">

            <!-- Left: Logo + Text -->
            <div class="logo-container">
                <img src="../images/white lang.jpg" alt="School Logo" class="logo-img">
                <div class="brand-text">
                    <h1>Student Council Voting</h1>
                    <p>SOFTVOTE: Vote Wisely!</p>
                </div>
            </div>

            <!-- Right: Logout -->
            <div class="header-right">
                <?php include '../logout_modal.php'; ?>

                <button class="logout-btn" onclick="openLogoutModal('student')">
                    <i data-lucide="log-out"></i>
                    <span class="logout-text">Logout</span>
                </button>
            </div>

        </div>
    </header>

    <div class="main-wrapper">

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PROGRESS BAR -->
        <div class="voting-progress-bar">
            <div class="progress-header">
                <h2>Voting Progress</h2>
                <span class="progress-text" id="progressText">0 of <?= $total_positions ?> positions selected</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width:0%;"></div>
            </div>
        </div>

        <!-- VOTING FORM -->
        <form action="review_votes.php" method="POST" id="votingForm">
            <input type="hidden" name="review_votes" value="1">

            <div class="ballot-wrapper">
                <?php
                // Split into council and board — same logic as original
                $boardPositions   = [];
                $regularPositions = [];

                foreach ($positions as $positionId => $position) {
                    if (
                        stripos($position['position_name'], 'board')          !== false ||
                        stripos($position['position_name'], 'representative') !== false
                    ) {
                        $boardPositions[$positionId]   = $position;
                    } else {
                        $regularPositions[$positionId] = $position;
                    }
                }

                function renderPositions(array $positionsList, array $partyNames): void
                {
                    foreach ($positionsList as $positionId => $position): ?>

                        <div class="position-block">
                            <h3 class="position-title">
                                <?= strtoupper(htmlspecialchars($position['position_name'])) ?>
                            </h3>

                            <div class="party-row">
                                <?php
                                $isFirst = true;
                                foreach ($partyNames as $partyName):
                                    $partyCandidates = array_values(array_filter(
                                        $position['candidates'],
                                        fn($c) => $c['partylist'] === $partyName
                                    ));
                                ?>
                                    <?php if (!$isFirst): ?>
                                        <div class="party-divider"></div>
                                    <?php endif; ?>
                                    <?php $isFirst = false; ?>

                                    <div class="party-column" data-party="<?= htmlspecialchars($partyName) ?>">

                                        <?php if (!empty($partyCandidates)): ?>
                                            <?php foreach ($partyCandidates as $candidate): ?>

                                                <label class="ballot-card" onclick="selectCandidate(this)"
                                                    style="--party-color: <?= htmlspecialchars($partyColors[$candidate['partylist']] ?? '#7C6FD0') ?>;">
                                                    <input type="radio"
                                                        name="position_<?= $positionId ?>"
                                                        value="<?= $candidate['candidate_id'] ?>"
                                                        hidden required>

                                                    <div class="ballot-photo">
                                                        <?php
                                                        $photo   = $candidate['photo_url'];
                                                        $imgSrc  = (!empty($photo) && strpos($photo, 'http') === 0)
                                                            ? $photo
                                                            : '../' . $photo;

                                                        $nameParts  = explode(',', $candidate['name']);
                                                        $lastName   = trim($nameParts[0] ?? '');
                                                        $firstPart  = trim($nameParts[1] ?? '');
                                                        $firstWords = explode(' ', $firstPart);
                                                        $initials   = strtoupper(
                                                            substr($firstWords[0] ?? '', 0, 1) .
                                                                substr($firstWords[1] ?? '', 0, 1)
                                                        );
                                                        if (empty(trim($initials))) {
                                                            $initials = strtoupper(substr($lastName, 0, 2));
                                                        }

                                                        $colors     = ['#7C6FD0', '#5B8FD0', '#D07C6F', '#6FB88F', '#D0B06F', '#8F6FB8'];
                                                        $bgColor    = $colors[abs(crc32($candidate['name']) % count($colors))];
                                                        ?>
                                                        <div class="ballot-photo-avatar"
                                                            style="background-color:<?= $bgColor ?>; display:none;"
                                                            data-initials="<?= htmlspecialchars($initials) ?>">
                                                            <?= htmlspecialchars($initials) ?>
                                                        </div>
                                                        <img src="<?= htmlspecialchars($imgSrc) ?>"
                                                            alt="<?= htmlspecialchars($candidate['name']) ?>"
                                                            alt="<?= htmlspecialchars($candidate['name']) ?>"
                                                            onerror="this.style.display='none';
                                                                      this.previousElementSibling.style.display='flex';">
                                                    </div>

                                                    <div class="ballot-info">
                                                        <div class="ballot-name">
                                                            <?= strtoupper(htmlspecialchars($candidate['name'])) ?>
                                                        </div>
                                                        <div class="ballot-selected-badge" style="display:none;">
                                                            <i data-lucide="circle-check" class="check-icon"></i>
                                                            <span>Selected</span>
                                                        </div>
                                                    </div>
                                                </label>

                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="ballot-empty">No Candidate</div>
                                        <?php endif; ?>

                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                <?php endforeach;
                }
                ?>

                <!-- COUNCIL POSITIONS -->
                <h2 class="section-title" style="align-self:center;">COUNCIL POSITIONS</h2>

                <!-- Party header row  -->
                <div class="party-header-row">
                    <?php foreach ($partyNames as $pname): ?>
                        <div class="party-header-cell"><?= htmlspecialchars($pname) ?></div>
                    <?php endforeach; ?>
                </div>

                <?php renderPositions($regularPositions, $partyNames); ?>

                <!-- BOARD MEMBERS -->
                <?php if (!empty($boardPositions)): ?>
                    <h2 class="section-title board-title">BOARD MEMBERS</h2>

                    <div class="party-header-row">
                        <?php foreach ($partyNames as $pname): ?>
                            <div class="party-header-cell"><?= htmlspecialchars($pname) ?></div>
                        <?php endforeach; ?>
                    </div>

                    <?php renderPositions($boardPositions, $partyNames); ?>
                <?php endif; ?>

            </div>

            <div class="vote-btn-container">
                <button type="submit" class="vote-btn" id="submitBtn" disabled>
                    <i data-lucide="circle-check"></i> Review My Votes
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();

            // i-restore ang mga dating piniling kandidato kung nagbalik mula sa review page
            const saved = sessionStorage.getItem('savedVotes');
            if (saved) {
                const selections = JSON.parse(saved);

                // hanapin ang bawat radio input at i-trigger ang selectCandidate para ma-restore ang visual state
                Object.entries(selections).forEach(([posId, candidateId]) => {
                    const radio = document.querySelector(`input[name="position_${posId}"][value="${candidateId}"]`);
                    if (radio) {
                        const card = radio.closest('.ballot-card');
                        if (card) selectCandidate(card);
                    }
                });

                // i-clear ang savedVotes pagkatapos ma-restore para hindi na maulit
                sessionStorage.removeItem('savedVotes');
            }

            updateProgress();
        });

        // piliin ang kandidato at i-update ang progress
        function selectCandidate(card) {
            const radio = card.querySelector('input[type="radio"]');
            const positionName = radio.name;

            document.querySelectorAll(`input[name="${positionName}"]`).forEach(r => {
                const c = r.closest('.ballot-card');
                c.classList.remove('selected');
                // Reset badge via inline style so CSS can control it
                const badge = c.querySelector('.ballot-selected-badge');
                if (badge) badge.style.display = 'none';
            });

            card.classList.add('selected');
            const badge = card.querySelector('.ballot-selected-badge');
            if (badge) {
                badge.style.removeProperty('display'); // let CSS .selected rule take over
                lucide.createIcons();
            }
            radio.checked = true;
            updateProgress();
        }
        // i-update ang progress bar at ang state ng submit button
        function updateProgress() {
            const allRadios = document.querySelectorAll('input[type="radio"]');
            const positions = new Set();
            allRadios.forEach(r => positions.add(r.name));

            let selectedCount = 0;
            positions.forEach(pos => {
                if (document.querySelector(`input[name="${pos}"]:checked`)) selectedCount++;
            });

            const totalPositions = positions.size;
            const percentage = totalPositions > 0 ? (selectedCount / totalPositions) * 100 : 0;

            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressText').textContent = `${selectedCount} of ${totalPositions} positions selected`;

            // i-enable ang submit button kung lahat ng posisyon ay napili na
            const submitBtn = document.getElementById('submitBtn');
            const allDone = selectedCount === totalPositions && totalPositions > 0;
            submitBtn.disabled = !allDone;
            document.getElementById('progressFill').classList.toggle('complete', allDone);
            document.getElementById('progressText').classList.toggle('complete', allDone);
        }

        // pagpigil sa form submission kung hindi pa lahat ng posisyon ay napili
        document.getElementById('votingForm').addEventListener('submit', function(e) {
            const allRadios = document.querySelectorAll('input[type="radio"]');
            const positions = new Set();
            allRadios.forEach(r => positions.add(r.name));
            let allSelected = true;
            positions.forEach(pos => {
                if (!document.querySelector(`input[name="${pos}"]:checked`)) allSelected = false;
            });
            if (!allSelected) {
                e.preventDefault();
                openModal('incompleteModal');
            }
        });

        // buksan ang incomplete selection modal
        function openModal(id) {
            document.getElementById(id)?.classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id)?.classList.remove('active');
        }

        // animating the main wrapper after iload ang page
        window.addEventListener('load', function() {
            document.querySelector('.main-wrapper').style.animation = 'fadeIn 0.5s ease-in';
        });


        const PL_COLOR_LS_KEY = 'softvote_global_colors';
        const PL_DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];

        // Partylist names in the same order PHP used when saving (alphabetical, then Independent last)
        const partylistNames = <?= json_encode($partyNames) ?>;

        function loadPartyColors() {
            try {
                const saved = localStorage.getItem(PL_COLOR_LS_KEY);
                if (saved) {
                    const arr = JSON.parse(saved);
                    if (Array.isArray(arr) && arr.length) {
                        const out = [...arr];
                        while (out.length < partylistNames.length)
                            out.push(PL_DEFAULT_PALETTE[out.length % PL_DEFAULT_PALETTE.length]);
                        return out;
                    }
                }
            } catch (e) {}
            return partylistNames.map((_, i) => PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length]);
        }

        const partyColors = {};
        const colorList = loadPartyColors();
        partylistNames.forEach((name, i) => {
            partyColors[name] = colorList[i];
        });

        document.querySelectorAll('.party-column').forEach(col => {
            const partyName = col.dataset.party;
            const color = partyColors[partyName] || PL_DEFAULT_PALETTE[0];
            col.querySelectorAll('.ballot-card').forEach(card => {
                card.style.setProperty('--party-color', color);
            });
        });

        document.querySelectorAll('.party-header-row').forEach(row => {
            row.querySelectorAll('.party-header-cell').forEach((cell, i) => {
                const name = partylistNames[i];
                if (name && partyColors[name]) {
                    cell.style.setProperty('--party-color', partyColors[name]);
                    cell.style.color = partyColors[name];
                }
            });
        });

        // Re-apply colors whenever admin changes them in another tab/window
        window.addEventListener('storage', function(e) {
            if (e.key !== 'softvote_global_colors') return;

            // Rebuild the color map from the new value
            try {
                const arr = e.newValue ? JSON.parse(e.newValue) : null;
                if (Array.isArray(arr) && arr.length) {
                    partylistNames.forEach((name, i) => {
                        const color = arr[i] ?? PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length];
                        window.__partyColors[name] = color;
                        partyColors[name] = color;
                    });
                }
            } catch (err) {
                return;
            }

            // Re-inject --party-color on all ballot cards (dashboard only)
            document.querySelectorAll('.party-column').forEach(col => {
                const name = col.dataset.party;
                const color = partyColors[name] || PL_DEFAULT_PALETTE[0];
                col.querySelectorAll('.ballot-card').forEach(card => {
                    card.style.setProperty('--party-color', color);
                });
            });

            // Re-color party header cells (dashboard only)
            document.querySelectorAll('.party-header-row').forEach(row => {
                row.querySelectorAll('.party-header-cell').forEach((cell, i) => {
                    const name = partylistNames[i];
                    const color = partyColors[name];
                    if (color) {
                        cell.style.setProperty('--party-color', color);
                        cell.style.color = color;
                    }
                });
            });

            // Re-apply badge colors (review_votes only — safe to call on dashboard too, just does nothing)
            if (typeof applyBadgeColors === 'function') applyBadgeColors();
        });
    </script>
</body>

</html>