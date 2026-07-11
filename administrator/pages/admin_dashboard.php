<?php
session_start();
require '../../config_db.php';

// KAILANGAN LOGGED IN AS ADMIN PARA MAACCESS ANG ADMIN PAGES
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    session_destroy();
    header("Location: ../../auth/admin_login.php");
    exit();
}

// PAGCHECK NG SESSION TIMEOUT
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_destroy();
    header("Location: ../../auth/admin_login.php?error=Session+expired");
    exit();
}
// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
require_once '../includes/admin_auth_guard.php';

// PAG CHECK NG ACCOUNT STATUS (ACTIVE/SUSPENDED) ON EVERY PAGE LOAD 

$_active_check = $conn->prepare("SELECT is_active FROM administrators WHERE id = ? AND IFNULL(is_deleted, 0) = 0");
$_active_check->bind_param("i", $_SESSION['admin_id']);
$_active_check->execute();
$_active_row = $_active_check->get_result()->fetch_assoc();
$_active_check->close();

if (!$_active_row || (int)$_active_row['is_active'] === 0) {
    // PAG ANG ACCOUNT AY HINDI NA ACTIVE (SUSPENDED), AUTOMATIC NA LOGOUT AT I-REREDIRECT SA LOGIN PAGE NA MAY ERROR MESSAGE
    session_destroy();
    header("Location: ../../auth/admin_login.php?error=" . urlencode("Your account has been suspended. Contact the Super Admin."));
    exit();
}
unset($_active_check, $_active_row);

// STATISTICS FOR DASHBOARD  
$stats = [];
$res = $conn->query("SELECT COUNT(*) AS total FROM students");
$stats['total_students'] = $res ? $res->fetch_assoc()['total'] : 0;

$res = $conn->query("SELECT COUNT(DISTINCT student_id) AS total_voters FROM votes");
$stats['total_votes'] = $res ? $res->fetch_assoc()['total_voters'] : 0;

$res = $conn->query("SELECT COUNT(*) AS total FROM positions");
$stats['total_positions'] = $res ? $res->fetch_assoc()['total'] : 0;

$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM election_settings");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// PARA SA HEAD-TO-HEAD CANDIDATE DISPLAY, KAILANGAN MAKUHA LAHAT NG CANDIDATES WITH THEIR VOTE COUNTS, JOINED WITH THEIR PARTYLISTS (IF ANY) AT POSITIONS
$all_partylists = [];
$plRes = $conn->query("SELECT id, name FROM partylists ORDER BY name ASC");
while ($row = $plRes->fetch_assoc()) {
    $all_partylists[] = $row;
}
$partyNames = array_column($all_partylists, 'name');

// PAGDETERMINE NG LEFT/RIGHT PARTY NAMES BASED ON ORDER IN DATABASE (FIRST TWO PARTIES) 
$party_left  = $partyNames[0] ?? '';
$party_right = $partyNames[1] ?? '';

// PAGKUHA NG CANDIDATES WITH VOTE COUNTS, JOINED WITH THEIR PARTYLISTS (IF ANY) AT POSITIONS, GROUPED BY POSITION
$position_candidates = [];

$sql = "
    SELECT
        c.id            AS candidate_id,
        c.first_name,
        c.middle_name,
        c.photo_url,
        c.last_name,
        c.partylist_id,
        COALESCE(pl.name, 'Independent') AS partylist,
        p.id            AS position_id,
        p.position_name,
        p.position_type,
        p.display_order,
        COUNT(v.id)     AS votes
    FROM candidates c
    JOIN  positions p        ON c.position_id  = p.id
    LEFT JOIN partylists pl  ON c.partylist_id = pl.id
    LEFT JOIN votes v        ON v.candidate_id  = c.id
    GROUP BY c.id, c.first_name, c.middle_name, c.last_name,
             c.partylist_id, pl.name,
             p.id, p.position_name, p.position_type, p.display_order, c.photo_url
    ORDER BY p.display_order ASC, p.position_name ASC
";

$res = $conn->query($sql);
if (!$res) die("SQL Error: " . $conn->error);

while ($row = $res->fetch_assoc()) {
    $pid = $row['position_id'];

    if (!isset($position_candidates[$pid])) {
        $position_candidates[$pid] = [
            'position_name' => $row['position_name'],
            'position_type' => strtolower($row['position_type']),
            'display_order' => $row['display_order'],
            'candidates'    => [],
        ];
    }

    $votes     = (int) $row['votes'];
    $full_name = trim(
        ($row['last_name']  ?? '') . ', ' .
            ($row['first_name'] ?? '') . ' ' .
            (!empty($row['middle_name']) ? strtoupper($row['middle_name'][0]) . '.' : '')
    );

    $photo_url     = trim($row['photo_url'] ?? '');
    $seed          = urlencode(strtoupper($row['last_name']) . ' ' . strtoupper($row['first_name']));
    $dicebear      = 'https://api.dicebear.com/7.x/initials/svg?seed=' . $seed
        . '&backgroundColor=6366f1&fontFamily=Arial&fontSize=38&bold=true&fontColor=ffffff';
    $is_real_photo = !empty($photo_url)
        && strpos($photo_url, 'ui-avatars.com') === false
        && strpos($photo_url, 'ui-avatars.io')  === false;
    $photo_src     = $is_real_photo
        ? ((strpos($photo_url, 'http') === 0) ? $photo_url : '../../' . $photo_url)
        : $dicebear;

    $position_candidates[$pid]['candidates'][] = [
        'candidate_id' => (int) $row['candidate_id'],
        'name'         => $full_name,
        'first_name'   => $row['first_name'],
        'last_name'    => $row['last_name'],
        'votes'        => $votes,
        'percentage'   => 0,
        'partylist'    => $row['partylist'],
        'partylist_id' => (int) ($row['partylist_id'] ?? 0),
        'photo_src'    => $photo_src,
        'dicebear'     => $dicebear,
    ];
}

// PAGCALCULATE NG VOTE PERCENTAGE PARA SA PROGRESS BAR DISPLAY (BASED SA TOTAL VOTES PER POSITION)
foreach ($position_candidates as &$pos) {
    $total_votes = array_sum(array_column($pos['candidates'], 'votes'));
    foreach ($pos['candidates'] as &$c) {
        $c['percentage'] = $total_votes > 0 ? round(($c['votes'] / $total_votes) * 100, 1) : 0;
    }
}
unset($pos, $c);

$council_positions = array_filter($position_candidates, fn($p) => $p['position_type'] === 'council');
$board_positions   = array_filter($position_candidates, fn($p) => $p['position_type'] === 'board');

// PAGDETERMINE NG WINNERS PER POSITION (HIGHEST VOTES)
$winners_by_position = [];
foreach ($position_candidates as $pid => $pos) {
    if (empty($pos['candidates'])) continue;

    $sorted = $pos['candidates'];
    usort($sorted, fn($a, $b) => $b['votes'] <=> $a['votes']);
    $winner = $sorted[0]; // top candidate (may have 0 votes)

    $winners_by_position[] = [
        'position_name' => $pos['position_name'],
        'position_type' => $pos['position_type'],
        'display_order' => $pos['display_order'],
        'winner'        => $winner,
    ];
}

$council_winners = array_filter($winners_by_position, fn($w) => $w['position_type'] === 'council');
$board_winners   = array_filter($winners_by_position, fn($w) => $w['position_type'] === 'board');

// HELPER FUNCTIONS FOR RENDERING CANDIDATE BLOCKS AND POSITION CARDS IN THE LIVE RESULTS TAB 
function getCandidatesByParty(array $candidates, string $partylist): array
{
    return array_values(array_filter($candidates, fn($c) => $c['partylist'] === $partylist));
}

function renderCandidateBlock(array $c, string $side_class, string $partylist, string $tag_class): void
{ ?>
    <div class="candidate-side <?= $side_class ?>"
        data-partylist="<?= htmlspecialchars($partylist) ?>"
        data-candidate-id="<?= $c['candidate_id'] ?>">
        <div class="card-inner">
            <div class="candidate-image">
                <img src="<?= htmlspecialchars($c['photo_src']) ?>"
                    alt="<?= htmlspecialchars($c['name']) ?>"
                    onerror="this.onerror=null; this.src='<?= htmlspecialchars($c['dicebear']) ?>';">
            </div>
            <div class="card-details">
                <div class="party-tag <?= $tag_class ?>"><?= htmlspecialchars($partylist) ?></div>
                <h4 class="cand-name"><?= htmlspecialchars($c['name']) ?></h4>
                <p class="vote-label"><?= $c['votes'] ?> vote<?= $c['votes'] != 1 ? 's' : '' ?></p>
                <div class="progress-bar">
                    <div class="progress-fill <?= $side_class === 'left' ? 'fill-left' : 'fill-right' ?>"
                        style="width:<?= $c['percentage'] ?>%"></div>
                </div>
                <small class="pct"><?= $c['percentage'] ?>%</small>
            </div>
        </div>
    </div>
    <?php }

function renderPositionCard(array $position, int $position_id, string $party_left, string $party_right): void
{
    // PAG NO PARTYLISTS, IPAPALABAS LANG LAHAT NG CANDIDATES AS INDEPENDENT SA LEFT SIDE  
    $has_partylists = !empty($party_left);

    if (!$has_partylists) {
        $all_candidates = $position['candidates'];
        $row_count      = max(count($all_candidates), 1); ?>
        <div class="position-card" data-position-id="<?= $position_id ?>">
            <div class="position-header">
                <h3><?= htmlspecialchars($position['position_name']) ?></h3>
            </div>
            <?php foreach ($all_candidates as $c): ?>
                <div class="head-to-head-row">
                    <?php renderCandidateBlock($c, 'left', 'Independent', 'tag-a'); ?>
                    <div class="candidate-side right empty-side"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php return;
    }

    $left_candidates  = getCandidatesByParty($position['candidates'], $party_left);
    $right_candidates = getCandidatesByParty($position['candidates'], $party_right);

    // FOR INDEPENDENT CANDIDATES, IPAPALABAS LANG SA LEFT SIDE WITH "Independent" TAG
    $independent_candidates = getCandidatesByParty($position['candidates'], 'Independent');

    $row_count = max(count($left_candidates), count($right_candidates), count($independent_candidates), 1); ?>

    <div class="position-card" data-position-id="<?= $position_id ?>">
        <div class="position-header">
            <h3><?= htmlspecialchars($position['position_name']) ?></h3>
        </div>
        <?php for ($i = 0; $i < max(count($left_candidates), count($right_candidates)); $i++): ?>
            <div class="head-to-head-row">
                <?php if (isset($left_candidates[$i])): ?>
                    <?php renderCandidateBlock($left_candidates[$i], 'left', $party_left, 'tag-a'); ?>
                <?php else: ?>
                    <div class="candidate-side left empty-side">
                        <p class="no-candidate">No Candidate</p>
                    </div>
                <?php endif; ?>
                <?php if (isset($right_candidates[$i])): ?>
                    <?php renderCandidateBlock($right_candidates[$i], 'right', $party_right, 'tag-b'); ?>
                <?php else: ?>
                    <div class="candidate-side right empty-side">
                        <p class="no-candidate">No Candidate</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
        <?php foreach ($independent_candidates as $c): ?>
            <div class="head-to-head-row">
                <?php renderCandidateBlock($c, 'left', 'Independent', 'tag-a'); ?>
                <div class="candidate-side right empty-side"></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php }

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SOFTVOTE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../styles/admin/dashboard.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --party-a-color: #2563eb;
            --party-b-color: #dc2626;
            --party-a-light: #dbeafe;
            --party-b-light: #fee2e2;
            --party-a-text: #1d4ed8;
            --party-b-text: #b91c1c;
        }

        .fill-left {
            background: #16a34a !important;
        }

        .fill-right {
            background: #16a34a !important;
        }

        .tag-a {
            background: var(--party-a-light) !important;
            color: var(--party-a-text) !important;
        }

        .tag-b {
            background: var(--party-b-light) !important;
            color: var(--party-b-text) !important;
        }

        .tab-panel.active {
            overflow: hidden;
        }

        .page-content {
            overflow-x: hidden;
        }

        /* COUNTDOWN MODAL — EXACT MATCH SA MANAGE_STUDENTS */
        .countdown-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            /* OVERRIDE NG dashboard.css na may backdrop-filter */
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }

        .countdown-overlay.active {
            display: flex;
        }

        .countdown-box {
            background: #fff;
            border-radius: 20px;
            padding: 40px 36px 32px;
            width: 380px;
            max-width: 95vw;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .22);
            text-align: center;
            /* OVERRIDE NG dashboard.css na may animation at malaking padding */
            animation: none;
        }

        .countdown-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
        }

        .countdown-icon svg {
            width: 26px;
            height: 26px;
        }

        .countdown-icon.start {
            background: #dbeafe;
            color: #2563eb;
        }

        .countdown-icon.end {
            background: #fef3c7;
            color: #ef4444;
        }

        .countdown-icon.restart {
            background: #fee2e2;
            color: #ef4444;
        }

        .countdown-box h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px;
        }

        .countdown-box .cd-subtitle {
            font-size: 0.83rem;
            color: #6b7280;
            margin: 0 0 22px;
            line-height: 1.5;
        }

        /* RING TIMER - EXACT MATCH SA MANAGE_STUDENTS */
        .ring-wrap {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 22px;
        }

        .ring-wrap svg {
            transform: rotate(-90deg);
            width: 110px;
            height: 110px;
        }

        .ring-bg {
            fill: none; 
            stroke: #e5e7eb;
            stroke-width: 8;
        }

        .ring-fill {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            /* NO transition — JS controls it entirely */
        }

        .ring-fill.start-ring {
            stroke: #059669;
        }

        .ring-fill.end-ring {
            stroke: #ef4444;
        }

        .ring-fill.restart-ring {
            stroke: #dc2626;
        }

        .ring-number {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
        }

        .ring-number.urgent {
            color: #dc2626;
            animation: pulse-num .4s ease infinite alternate;
        }
    </style>
</head>

<body>

    <!-- EXPORT POSTER -->
    <div id="export-poster" style="display:none; font-family: Arial, sans-serif; background: #fff; width: 900px; box-sizing: border-box; padding: 40px 48px 32px;">

        <!-- LOGO -->
        <div style="text-align:center; margin-bottom:10px;">
            <img src="../../images/white lang.jpg" style="width:80px; height:80px; object-fit:contain; display:inline-block;">
        </div>

        <!-- TITLE -->
        <div style="text-align:center; margin-bottom:20px;">
            <h1 style="font-size:36px; font-weight:900; color:#1a1a8c; text-transform:uppercase; margin:0 0 4px; letter-spacing:3px; line-height:1.1;">
                Supreme Junior Student Government
            </h1>
            <p style="font-size:16px; font-weight:700; color:#333; margin:0; letter-spacing:1px;">
                AY 2025-2026
            </p>
        </div>

        <?php
        $council_winners_filtered = array_values($council_winners);
        $board_winners_filtered   = array_values($board_winners);
        $has_votes = ($stats['total_votes'] ?? 0) > 0;
        ?>

        <!-- COUNCIL POSITIONS -->
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom: 20px;">
            <div style="flex:1; height:3px; background:#f5c518; border-radius:2px;"></div>
            <h2 style="font-size:22px; font-weight:900; color:#f5c518; text-transform:uppercase; margin:0; letter-spacing:3px; white-space:nowrap;">
                Council Positions
            </h2>
            <div style="flex:1; height:3px; background:#f5c518; border-radius:2px;"></div>
        </div>

        <div style="margin-bottom:24px;">
            <?php
            $council_arr = $council_winners_filtered;
            $row1 = array_slice($council_arr, 0, 4);
            $row2 = array_slice($council_arr, 4, 3);
            $row3 = array_slice($council_arr, 7);
            ?>

            <<div style="display:flex; justify-content:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; width:100%; max-width:900px; margin-left:auto; margin-right:auto;">
                <?php foreach ($row1 as $entry):
                    $w = $entry['winner']; ?>
                    <div style="text-align:center; width:160px;">
                        <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:140px; height:155px; margin:0 auto 8px; background:#3b82f6;">
                            <?php if ($has_votes): ?>
                                <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                    onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                    style="width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                        <?php if ($has_votes): ?>
                            <p style="font-size:12px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                            </p>
                            <p style="font-size:10px; font-weight:600; color:#555; text-transform:uppercase; margin:0; letter-spacing:1px;">
                                <?= htmlspecialchars($entry['position_name']) ?>
                            </p>
                        <?php else: ?>
                            <p style="font-size:11px; color:#666; margin:4px 0 0; font-weight:600;">
                                <?= htmlspecialchars($entry['position_name']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Repeat similar logic for row2 and row3 (same pattern) -->
            <?php if (!empty($row2)): ?>
                <div style="display:flex; justify-content:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; width:100%; max-width:900px; margin-left:auto; margin-right:auto;">
                    <?php foreach ($row2 as $entry):
                        $w = $entry['winner']; ?>
                        <div style="text-align:center; width:160px;">
                            <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:140px; height:155px; margin:0 auto 8px; background:#3b82f6;">
                                <?php if ($has_votes): ?>
                                    <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                        onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                        style="width:100%; height:100%; object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <?php if ($has_votes): ?>
                                <p style="font-size:12px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                    <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                                </p>
                                <p style="font-size:10px; font-weight:600; color:#555; text-transform:uppercase; margin:0; letter-spacing:1px;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php else: ?>
                                <p style="font-size:11px; color:#666; margin:4px 0 0; font-weight:600;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($row3)): ?>
                <div style="display:flex; justify-content:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; width:100%; max-width:900px; margin-left:auto; margin-right:auto;">
                    <?php foreach ($row3 as $entry):
                        $w = $entry['winner']; ?>
                        <div style="text-align:center; width:160px;">
                            <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:140px; height:155px; margin:0 auto 8px; background:#3b82f6;">
                                <?php if ($has_votes): ?>
                                    <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                        onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                        style="width:100%; height:100%; object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <?php if ($has_votes): ?>
                                <p style="font-size:12px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                    <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                                </p>
                                <p style="font-size:10px; font-weight:600; color:#555; text-transform:uppercase; margin:0; letter-spacing:1px;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php else: ?>
                                <p style="font-size:11px; color:#666; margin:4px 0 0; font-weight:600;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- BOARD OF DIRECTORS -->
        <div style="display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:20px;">
            <div style="flex:1; height:3px; background:#f5c518; border-radius:2px;"></div>
            <h2 style="font-size:22px; font-weight:900; color:#f5c518; text-transform:uppercase; margin:0; letter-spacing:3px; white-space:nowrap;">
                Board of Directors
            </h2>
            <div style="flex:1; height:3px; background:#f5c518; border-radius:2px;"></div>
        </div>

        <?php if (!empty($board_winners_filtered)): ?>
            <?php
            $board_arr = $board_winners_filtered;
            $brow1 = array_slice($board_arr, 0, 4);
            $brow2 = array_slice($board_arr, 4, 4);
            $brow3 = array_slice($board_arr, 8);
            ?>

            <div style="display:flex; justify-content:center; gap:14px; margin-bottom:16px; flex-wrap:wrap;">
                <?php foreach ($brow1 as $entry):
                    $w = $entry['winner']; ?>
                    <div style="text-align:center; width:130px;">
                        <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:140px; height:155px; margin:0 auto 6px; background:#3b82f6;">
                            <?php if ($has_votes): ?>
                                <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                    onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                    style="width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                        <?php if ($has_votes): ?>
                            <p style="font-size:11px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                            </p>
                            <p style="font-size:9px; font-weight:600; color:#666; text-transform:uppercase; margin:0; letter-spacing:0.5px;">
                                <?= htmlspecialchars($entry['position_name']) ?>
                            </p>
                        <?php else: ?>
                            <p style="font-size:10px; color:#555; margin:4px 0 0; font-weight:600;">
                                <?= htmlspecialchars($entry['position_name']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($brow2)): ?>
                <div style="display:flex; justify-content:center; gap:14px; margin-bottom:16px; flex-wrap:wrap;">
                    <?php foreach ($brow2 as $entry):
                        $w = $entry['winner']; ?>
                        <div style="text-align:center; width:130px;">
                            <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:140px; height:155px; margin:0 auto 6px; background:#3b82f6;">
                                <?php if ($has_votes): ?>
                                    <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                        onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                        style="width:100%; height:100%; object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <?php if ($has_votes): ?>
                                <p style="font-size:11px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                    <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                                </p>
                                <p style="font-size:9px; font-weight:600; color:#666; text-transform:uppercase; margin:0; letter-spacing:0.5px;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php else: ?>
                                <p style="font-size:10px; color:#555; margin:4px 0 0; font-weight:600;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($brow3)): ?>
                <div style="display:flex; justify-content:center; gap:14px; margin-bottom:16px; flex-wrap:wrap;">
                    <?php foreach ($brow3 as $entry):
                        $w = $entry['winner']; ?>
                        <div style="text-align:center; width:130px;">
                            <div style="border:3px solid #f5c518; border-radius:8px; overflow:hidden; width:110px; height:120px; margin:0 auto 6px; background:#3b82f6;">
                                <?php if ($has_votes): ?>
                                    <img src="<?= htmlspecialchars($w['photo_src']) ?>"
                                        onerror="this.onerror=null;this.src='<?= htmlspecialchars($w['dicebear']) ?>'"
                                        style="width:100%; height:100%; object-fit:cover;">
                                <?php endif; ?>
                            </div>
                            <?php if ($has_votes): ?>
                                <p style="font-size:11px; font-weight:700; color:#111; text-transform:uppercase; margin:0 0 2px; line-height:1.3;">
                                    <?= htmlspecialchars($w['first_name'] . ' ' . $w['last_name']) ?>
                                </p>
                                <p style="font-size:9px; font-weight:600; color:#666; text-transform:uppercase; margin:0; letter-spacing:0.5px;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php else: ?>
                                <p style="font-size:10px; color:#555; margin:4px 0 0; font-weight:600;">
                                    <?= htmlspecialchars($entry['position_name']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <!-- FOOTER -->
        <div style="border-top:3px solid #f5c518; margin-top:28px; padding-top:12px; text-align:center;">
            <p style="font-size:11px; color:#9ca3af; margin:0; letter-spacing:1px;">
                SOFTVOTE &nbsp;&middot;&nbsp; Official Election Results &nbsp;&middot;&nbsp; <?= date('F d, Y') ?>
            </p>
        </div>
    </div>

    <!-- PRINT WINNERS DOCUMENT - WITH BOARD POSITIONS -->
    <div id="print-winners-doc">

        <!-- HEADER -->
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="../../images/white lang.jpg"
                alt="SOFTVOTE Logo"
                style="width: 85px; height: 85px; display: block; margin: 0 auto 12px; object-fit: contain;">
        </div>

        <h2 style="text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 35px;">
            OFFICIAL STUDENT COUNCIL ELECTION 2026
        </h2>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 65px; max-width: 960px; margin: 0 auto;">

            <!-- LEFT COLUMN: COUNCIL POSITIONS -->
            <div>
                <?php foreach ($council_winners as $entry):
                    $w     = $entry['winner'];
                    $party = ($w['partylist'] !== 'Independent')
                        ? '(' . htmlspecialchars($w['partylist']) . ' Partylist)'
                        : '(Independent)';
                ?>
                    <div style="margin-bottom:22px;">
                        <div style="font-weight:700; font-size:13px; text-transform:uppercase; color:#333; letter-spacing:.5px;">
                            <?= htmlspecialchars($entry['position_name']) ?>:
                        </div>
                        <?php if ($w['votes'] > 0): ?>
                            <div style="font-size:15px; font-weight:600; margin:4px 0 2px; text-transform:uppercase; color:#0f172a;">
                                <?= htmlspecialchars(strtoupper($w['name'])) ?>
                            </div>
                            <div style="font-size:12px; color:#64748b; text-transform:uppercase;">
                                <?= $party ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:6px; height:18px; background:#f1f5f9;
                            border-bottom:1.5px solid #cbd5e1; width:100%;"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- RIGHT COLUMN: BOARD OF DIRECTORS -->
            <div>
                <?php
                $board_winners_print = array_filter($winners_by_position, fn($w) => $w['position_type'] === 'board');

                // Also include positions that had NO votes (currently excluded from $winners
                $board_positions_all = array_filter($position_candidates, fn($p) => $p['position_type'] === 'board');

                // Build a lookup of positions already in $board_winners_print
                $covered_positions = array_column(iterator_to_array((function () use ($board_winners_print) {
                    foreach ($board_winners_print as $e) yield $e['position_name'] => true;
                })()), null, 0);
                ?>

                <?php foreach ($board_winners_print as $entry):
                    $w     = $entry['winner'];
                    $party = ($w['partylist'] !== 'Independent')
                        ? '(' . htmlspecialchars($w['partylist']) . ' Partylist)'
                        : '(Independent)';
                ?>
                    <div style="margin-bottom:22px;">
                        <div style="font-weight:700; font-size:13px; text-transform:uppercase; color:#333; letter-spacing:.5px;">
                            <?= htmlspecialchars($entry['position_name']) ?>:
                        </div>
                        <?php if ($w['votes'] > 0): ?>
                            <div style="font-size:15px; font-weight:600; margin:4px 0 2px; text-transform:uppercase; color:#0f172a;">
                                <?= htmlspecialchars(strtoupper($w['name'])) ?>
                            </div>
                            <div style="font-size:12px; color:#64748b; text-transform:uppercase;">
                                <?= $party ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-top:6px; height:18px; background:#f1f5f9;
                            border-bottom:1.5px solid #cbd5e1; width:100%;"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>

    <?php include '../../logout_modal.php'; ?>

    <div class="header-main">
        <div class="header-container">
            <div class="header">
                <div class="logo-box">
                    <div class="logo-text">
                        <h1>Admin Dashboard</h1>
                        <p>Election Monitoring System</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/sidebar_menu.php'; ?>
    <div class="main-wrapper">

        <!-- START ELECTION MODAL -->
        <div id="startElectionModal" class="start-election-overlay">
            <div class="start-election-content">
                <h2>Are you sure to start the election?</h2>
                <p>Students can now select their chosen candidates.</p>
                <div class="btn-container">
                    <button class="cancel" data-modal-close="startElectionModal">Cancel</button>
                    <button class="confirm" id="startProceedBtn">Proceed</button>
                </div>
            </div>
        </div>

        <!-- END VOTING MODAL -->
        <div id="endVotingModal" class="start-election-overlay">
            <div class="start-election-content">
                <h2>Are you sure to end the election?</h2>
                <p>Vote casting will close and final results will be tallied.</p>
                <div class="btn-container">
                    <button class="cancel" data-modal-close="endVotingModal">Cancel</button>
                    <button class="confirm" id="endProceedBtn">Proceed</button>
                </div>
            </div>
        </div>

        <!-- RESTART ELECTION MODAL -->
        <div id="restartElectionModal" class="start-election-overlay">
            <div class="start-election-content">
                <h2>Are you sure to restart the election?</h2>
                <div class="warning-list">
                    <h3>This will:</h3>
                    <ul>
                        <li>Delete all votes from all students</li>
                        <li>Reset all the winners</li>
                        <li>Allow students to vote again</li>
                    </ul>
                </div>
                <div class="btn-container">
                    <button class="cancel" data-modal-close="restartElectionModal">Cancel</button>
                    <button class="confirm" id="restartProceedBtn">Proceed</button>
                </div>
            </div>
        </div>

        <!-- COUNTDOWN MODAL -->
        <div id="countdownModal" class="countdown-overlay">
            <div class="countdown-box">
                <div class="countdown-icon" id="cdIcon"><i data-lucide="play"></i></div>
                <h2 id="cdTitle">Starting Election…</h2>
                <p class="cd-subtitle" id="cdSubtitle">
                    The election will begin automatically. You may cancel before the timer ends.
                </p>
                <div class="ring-wrap">
                    <svg viewBox="0 0 110 110">
                        <circle class="ring-bg" cx="55" cy="55" r="46" />
                        <circle class="ring-fill" id="cdRing" cx="55" cy="55" r="46" />
                    </svg>
                    <div class="ring-number" id="cdNumber">10</div>
                </div>
                <div class="countdown-btns">
                    <button class="cd-cancel" id="cdCancelBtn">
                        <i data-lucide="x" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px;"></i>
                        Cancel
                    </button>
                    <button class="cd-proceed" id="cdProceedBtn">Proceed Now</button>
                </div>
            </div>
        </div>

        <div class="page-content">

            <!-- STATUS BAR -->
            <div class="stats-bar">
                <div class="stat-card">
                    <div class="stat-info">
                        <p>Total Students</p>
                        <h3><?= $stats['total_students'] ?? 0 ?></h3>
                    </div>
                    <div class="stat-icon blue"><i data-lucide="users"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <p>Voted Students</p>
                        <h3 class="green" id="stat-voted"><?= $stats['total_votes'] ?? 0 ?></h3>
                    </div>
                    <div class="stat-icon green"><i data-lucide="trending-up"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <p>Voter Turnout</p>
                        <h3 id="stat-turnout">
                            <?php
                            $total = $stats['total_students'] ?? 0;
                            $voted = $stats['total_votes']    ?? 0;
                            echo $total > 0 ? round(($voted / $total) * 100, 1) . '%' : '0%';
                            ?>
                        </h3>
                    </div>
                    <div class="stat-icon amber"><i data-lucide="bar-chart-3"></i></div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <p>Total Votes Cast</p>
                        <h3 id="stat-total-cast">
                            <?php
                            $res = $conn->query("SELECT COUNT(*) AS c FROM votes");
                            echo $res ? $res->fetch_assoc()['c'] : 0;
                            ?>
                        </h3>
                    </div>
                    <div class="stat-icon purple"><i data-lucide="award"></i></div>
                </div>
            </div>

            <!-- TAB NAV -->
            <div class="tab-nav" id="tabNav">
                <button class="tab-btn active" data-tab="live">Live Results</button>
                <button class="tab-btn" data-tab="partylist">Partylist Analysis</button>
                <button class="tab-btn" data-tab="voters">Voter Status</button>
                <div class="tab-slider" id="tabSlider"></div>
            </div>

            <!-- LIVE RESULTS TAB -->
            <div class="tab-panel active" id="tab-live">
                <div class="btn-container" style="margin-bottom:24px;">
                    <?php $status = $settings['election_status'] ?? 'not_started'; ?>
                    <?php if ($status === 'not_started'): ?>
                        <button class="end-voting active" data-modal-target="startElectionModal">
                            <i data-lucide="play" class="icon"></i> Start Election
                        </button>
                    <?php elseif ($status === 'ongoing'): ?>
                        <button class="end-voting active" data-modal-target="endVotingModal">
                            <i data-lucide="trophy" class="icon"></i> End Voting &amp; Calculate Winners
                        </button>
                    <?php elseif ($status === 'ended'): ?>
                        <button class="end-voting" onclick="window.print()">
                            <i data-lucide="printer" class="icon"></i> Print Results
                        </button>
                        <button class="end-voting" style="background:#dc2626;" data-modal-target="restartElectionModal">
                            <i data-lucide="rotate-ccw" class="icon"></i> Restart Election
                        </button>
                        <div class="dropdown">
                            <button onclick="myFunction()" class="dropbtn">
                                <i data-lucide="image" class="icon"></i> Save Image
                            </button>
                            <div id="myDropdown" class="dropdown-content">
                                <button onclick="saveAsImage('png')"><i data-lucide="download" class="icon"></i> Save as PNG</button>
                                <button onclick="saveAsImage('jpeg')"><i data-lucide="download" class="icon"></i> Save as JPEG</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="print-container">
                    <div id="save-result">
                        <?php if (!empty($council_positions)): ?>
                            <div class="section-title">
                                <h2>Council Positions</h2>
                            </div>
                            <div class="positions-list">
                                <?php foreach ($council_positions as $pid => $position): ?>
                                    <?php renderPositionCard($position, $pid, $party_left, $party_right); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($board_positions)): ?>
                            <div class="section-title" style="margin-top:48px;">
                                <h2>Board of Directors</h2>
                            </div>
                            <div class="positions-list">
                                <?php foreach ($board_positions as $pid => $position): ?>
                                    <?php renderPositionCard($position, $pid, $party_left, $party_right); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PARTYLIST ANALYSIS TAB -->
            <div class="tab-panel" id="tab-partylist">
                <div class="analysis-grid">
                    <div class="chart-card">
                        <h3>Partylist Distribution</h3>
                        <div class="pie-wrap"><canvas id="pieChart"></canvas></div>
                    </div>
                    <div class="chart-card">
                        <h3>Partylist Comparison</h3>
                        <div class="bar-wrap"><canvas id="barChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- VOTER STATUS TAB -->
            <div class="tab-panel" id="tab-voters">
                <div class="table-controls">
                    <input type="text" id="voterSearch" placeholder="Search by name or ID…" oninput="filterVoterTable()">
                    <select id="voterFilter" onchange="filterVoterTable()">
                        <option value="all">All Students</option>
                        <option value="voted">Voted</option>
                        <option value="not-voted">Not Voted</option>
                    </select>
                </div>
                <div class="voter-table-card">
                    <h3>Student Voter Status</h3>
                    <table class="voter-table" id="voterTable">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Department</th>
                                <th>Section</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res_voters = $conn->query("
                                SELECT s.student_id, s.department, s.section,
                                       IF(s.has_voted=1,'Voted','Not Voted') AS vote_status
                                FROM students s ORDER BY s.student_id ASC
                            ");
                            if ($res_voters): while ($sv = $res_voters->fetch_assoc()):
                                    $badgeClass = $sv['vote_status'] === 'Voted' ? 'voted' : 'not-voted';
                            ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sv['student_id']) ?></td>
                                        <td><span class="dept-badge"><?= htmlspecialchars($sv['department']) ?></span></td>
                                        <td><?= htmlspecialchars($sv['section']) ?></td>
                                        <td><span class="status-badge <?= $badgeClass ?>"><?= $sv['vote_status'] ?></span></td>
                                    </tr>
                            <?php endwhile;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- ALl JS FUNCTIONALITY -->

    <script>
        lucide.createIcons();

        // Load saved global colors (set by statistics page) 
        const LS_KEY = 'softvote_global_colors';
        const DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];

        function loadGlobalColors() {
            try {
                const saved = localStorage.getItem(LS_KEY);
                if (saved) {
                    const arr = JSON.parse(saved);
                    if (Array.isArray(arr) && arr.length >= 2) return arr;
                }
            } catch (e) {}
            return [...DEFAULT_PALETTE];
        }

        const globalColors = loadGlobalColors();
        const colorA = globalColors[0] || DEFAULT_PALETTE[0];
        const colorB = globalColors[1] || DEFAULT_PALETTE[1];

        function hexToRgb(hex) {
            return {
                r: parseInt(hex.slice(1, 3), 16),
                g: parseInt(hex.slice(3, 5), 16),
                b: parseInt(hex.slice(5, 7), 16)
            };
        }

        function lighten(hex, a = 0.85) {
            const {
                r,
                g,
                b
            } = hexToRgb(hex);
            return `rgb(${Math.round(r+(255-r)*a)},${Math.round(g+(255-g)*a)},${Math.round(b+(255-b)*a)})`;
        }

        function darken(hex, a = 0.15) {
            const {
                r,
                g,
                b
            } = hexToRgb(hex);
            return `rgb(${Math.round(r*(1-a))},${Math.round(g*(1-a))},${Math.round(b*(1-a))})`;
        }

        // Apply party colors to CSS vars — driven by partylist table order
        (function seedCssVars() {
            const root = document.documentElement;
            root.style.setProperty('--party-a-color', colorA);
            root.style.setProperty('--party-b-color', colorB);
            root.style.setProperty('--party-a-light', lighten(colorA, 0.85));
            root.style.setProperty('--party-b-light', lighten(colorB, 0.85));
            root.style.setProperty('--party-a-text', darken(colorA, 0.15));
            root.style.setProperty('--party-b-text', darken(colorB, 0.15));
        })();

        //  TAB NAV (pill slider) 

        (function() {
            const nav = document.getElementById('tabNav');
            const slider = document.getElementById('tabSlider');
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabPanels = document.querySelectorAll('.tab-panel');

            function moveSlider(btn) {
                if (!slider || !btn || !nav) return;
                // offsetLeft/offsetWidth = relative to parent, works before viewport paint
                slider.style.left = btn.offsetLeft + 'px';
                slider.style.width = btn.offsetWidth + 'px';
            }

            function activateTab(btn) {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabPanels.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const panel = document.getElementById('tab-' + btn.dataset.tab);
                if (panel) panel.classList.add('active');
                moveSlider(btn);
                if (btn.dataset.tab === 'partylist' && !chartsInitialized) {
                    initCharts();
                    chartsInitialized = true;
                }
            }

            tabBtns.forEach(btn => btn.addEventListener('click', () => activateTab(btn)));

            function initSlider() {
                const active = nav.querySelector('.tab-btn.active');
                if (active) moveSlider(active);
            }

            // Double-rAF: first frame queues layout, second reads it — guaranteed to have real dimensions
            requestAnimationFrame(() => requestAnimationFrame(initSlider));
            window.addEventListener('load', initSlider);

            // Sidebar collapse toggles 'sidebar-collapsed' on <body> — watch that
            new MutationObserver(() => setTimeout(initSlider, 350))
                .observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });

            window.addEventListener('resize', initSlider);
        })();

        // Function to handle sidebar collapse/expand
        function handleSidebarToggle() {
            // Just trigger a reflow - no slider needed
            document.body.style.display = 'none';
            document.body.offsetHeight; // Force reflow
            document.body.style.display = '';
        }

        // Listen for sidebar changes
        const sidebar = document.querySelector('.sidebar'); // Adjust this selector to match your sidebar
        if (sidebar) {
            // Watch for class changes on sidebar
            const observer = new MutationObserver(() => {
                // Small delay to let layout settle
                setTimeout(() => {
                    // No slider movement needed, just ensure tabs are still visible
                    const activeTab = document.querySelector('.tab-btn.active');
                    if (activeTab) {
                        // Optional: scroll active tab into view if needed
                        activeTab.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'nearest'
                        });
                    }
                }, 100);
            });

            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class', 'style']
            });
        }

        // Modal system 
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.start-election-overlay').forEach(modal => {
                modal.addEventListener('click', e => {
                    if (e.target === modal) modal.classList.remove('active');
                });
            });
            document.querySelectorAll('[data-modal-target]').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById(btn.getAttribute('data-modal-target'))?.classList.add('active');
                });
            });
            document.querySelectorAll('[data-modal-close]').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById(btn.getAttribute('data-modal-close'))?.classList.remove('active');
                });
            });

            document.getElementById('startProceedBtn')?.addEventListener('click', () => {
                document.getElementById('startElectionModal').classList.remove('active');
                openCountdown('start', 'Starting Election…', 'The election will begin automatically. You may cancel before the timer ends.', '../actions/start_election.php');
            });
            document.getElementById('endProceedBtn')?.addEventListener('click', () => {
                document.getElementById('endVotingModal').classList.remove('active');
                openCountdown('end', 'Ending Election…', 'Winners will be calculated automatically. You may cancel before the timer ends.', '../actions/end_voting.php');
            });
            document.getElementById('restartProceedBtn')?.addEventListener('click', () => {
                document.getElementById('restartElectionModal').classList.remove('active');
                openCountdown('restart', 'Restarting Election…', 'All votes will be deleted and the election will reset. You may cancel before the timer ends.', '../actions/restart_election.php');
            });
        });

        // Countdown modal 
        const COUNTDOWN_SECONDS = 10;
        const circumference = 2 * Math.PI * 46;
        let cdTimer = null,
            cdTarget = '',
            cdRemaining = COUNTDOWN_SECONDS;

        const cdModal = document.getElementById('countdownModal');
        const cdIcon = document.getElementById('cdIcon');
        const cdTitle = document.getElementById('cdTitle');
        const cdSubtitle = document.getElementById('cdSubtitle');
        const cdRing = document.getElementById('cdRing');
        const cdNumber = document.getElementById('cdNumber');
        const cdProceed = document.getElementById('cdProceedBtn');
        const cdCancel = document.getElementById('cdCancelBtn');

        cdRing.style.strokeDasharray = circumference;
        cdRing.style.strokeDashoffset = 0;

        function openCountdown(type, title, subtitle, url) {
            // ISARA ANG IBANG MGA MODAL
            document.querySelectorAll('.start-election-overlay')
                .forEach(m => m.classList.remove('active'));

            clearInterval(cdTimer);
            cdRemaining = COUNTDOWN_SECONDS;
            cdTarget = url;

            const iconMap = {
                start: 'play',
                restart: 'rotate-ccw',
                end: 'trophy'
            };

            // ICON SETUP
            cdIcon.className = 'countdown-icon ' + type;
            cdIcon.innerHTML = `<i data-lucide="${iconMap[type] || 'play'}"></i>`;
            lucide.createIcons({
                nodes: [cdIcon]
            });

            // PROCEED BUTTON SETUP
            cdProceed.className = 'cd-proceed ' + type;
            cdProceed.textContent =
                type === 'start' ? 'Start Now' :
                type === 'restart' ? 'Restart Now' : 'End Now';

            // TEXT SETUP
            cdTitle.textContent = title;
            cdSubtitle.textContent = subtitle;

            // NUMBER RESET
            cdNumber.textContent = cdRemaining;
            cdNumber.classList.remove('urgent');

            // RING COLOR — SET INLINE STROKE DIRECTLY, NO RELYING ON CSS CLASS
            const strokeColor =
                type === 'start' ? '#059669' :
                type === 'restart' ? '#dc2626' : '#ef4444';

            const localCircumference = 2 * Math.PI * 46;

            // FULLY RESET RING INLINE — BYPASS ALL CSS
            cdRing.removeAttribute('class');
            cdRing.setAttribute('stroke', strokeColor);
            cdRing.setAttribute('stroke-width', '8');
            cdRing.setAttribute('stroke-linecap', 'round');
            cdRing.setAttribute('fill', 'none');
            cdRing.style.transition = 'none';
            cdRing.style.strokeDasharray = localCircumference;
            cdRing.style.strokeDashoffset = 0;

            // IPAKITA ANG MODAL
            cdModal.classList.add('active');

            // FORCE REFLOW — TINITIYAK NA NA-PAINTED ANG FULL CIRCLE
            void cdRing.offsetWidth;

            // I-ENABLE ANG TRANSITION — MAGSISIMULA SA FULL AT DAHAN-DAHANG BABABA
            cdRing.style.transition = 'stroke-dashoffset 1s linear';

            // SIMULAN ANG INTERVAL — ANG UNANG TICK AY PAGKATAPOS NG 1 SEGUNDO
            cdTimer = setInterval(() => {
                cdRemaining--;
                cdNumber.textContent = cdRemaining;
                cdRing.style.strokeDashoffset = localCircumference * (1 - cdRemaining / COUNTDOWN_SECONDS);

                if (cdRemaining <= 2) cdNumber.classList.add('urgent');

                if (cdRemaining <= 0) {
                    clearInterval(cdTimer);
                    location.href = cdTarget;
                }
            }, 1000);
        }

        function closeCountdown() {
            clearInterval(cdTimer);
            cdModal.classList.remove('active');
            cdNumber.classList.remove('urgent');
            // RESTORE RING CLASS PARA SA SUSUNOD NA PAGBUBUKAS
            cdRing.className = 'ring-fill';
        }

        cdCancel.addEventListener('click', closeCountdown);
        cdProceed.addEventListener('click', () => {
            clearInterval(cdTimer);
            location.href = cdTarget;
        });
        cdModal.addEventListener('click', e => {
            if (e.target === cdModal) closeCountdown();
        });

        // Partylist charts 
        let chartsInitialized = false;

        // Votes summed per partylist / keyed by partylist name from partylists table
        const partyData = <?php
                            $partyTotals = [];
                            // Build totals keyed by partylist name using the same JOIN approach
                            $ptRes = $conn->query("
                SELECT pl.name AS partylist, COUNT(v.id) AS total
                FROM partylists pl
                LEFT JOIN candidates c ON c.partylist_id = pl.id
                LEFT JOIN votes v      ON v.candidate_id  = c.id
                GROUP BY pl.id, pl.name
                ORDER BY pl.name ASC
            ");
                            while ($ptRow = $ptRes->fetch_assoc()) {
                                $partyTotals[$ptRow['partylist']] = (int) $ptRow['total'];
                            }
                            echo json_encode($partyTotals);
                            ?>;

        const partyLabels = Object.keys(partyData);
        const partyValues = Object.values(partyData);

        function initCharts() {
            const colors = partyLabels.map((_, i) => globalColors[i % globalColors.length]);

            new Chart(document.getElementById('pieChart'), {
                type: 'pie',
                data: {
                    labels: partyLabels,
                    datasets: [{
                        data: partyValues,
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
                            position: 'bottom',
                            labels: {
                                padding: 14,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ` ${ctx.label}: ${ctx.parsed} votes`
                            }
                        }
                    }
                }
            });

            new Chart(document.getElementById('barChart'), {
                type: 'bar',
                data: {
                    labels: partyLabels,
                    datasets: [{
                        label: 'Total Votes',
                        data: partyValues,
                        backgroundColor: colors,
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Voter table filter 
        function filterVoterTable() {
            const q = document.getElementById('voterSearch').value.toLowerCase();
            const filter = document.getElementById('voterFilter').value;
            document.querySelectorAll('#voterTable tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                const badge = row.querySelector('.status-badge');
                const status = badge ? (badge.classList.contains('voted') ? 'voted' : 'not-voted') : '';
                row.style.display = (text.includes(q) && (filter === 'all' || status === filter)) ? '' : 'none';
            });
        }

        // Real-time results polling 
        async function updateResults() {
            try {
                const res = await fetch('../actions/fetch_results.php');
                if (!res.ok) return;
                const json = await res.json();
                if (json.error) return;

                for (const posId in json.positions) {
                    const posCard = document.querySelector(`.position-card[data-position-id="${posId}"]`);
                    if (!posCard) continue;
                    json.positions[posId].candidates.forEach(c => {
                        const sideEl = posCard.querySelector(`.candidate-side[data-candidate-id="${c.candidate_id}"]`);
                        if (!sideEl) return;
                        const votesEl = sideEl.querySelector('.vote-label');
                        const pctEl = sideEl.querySelector('.pct');
                        const barEl = sideEl.querySelector('.progress-fill');
                        if (votesEl) votesEl.textContent = `${c.votes} vote${c.votes != 1 ? 's' : ''}`;
                        if (pctEl) pctEl.textContent = `${c.percentage}%`;
                        if (barEl) barEl.style.width = `${c.percentage}%`;
                    });
                }
            } catch (e) {
                console.error('updateResults error:', e);
            }
        }
        setInterval(updateResults, 3000);

        // Save as image 
        function myFunction() {
            document.getElementById('myDropdown').classList.toggle('show');
        }
        window.onclick = e => {
            if (!e.target.matches('.dropbtn'))
                document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
        };

        function saveAsImage(format) {
            const target = document.getElementById('export-poster');

            // Temporarily show it for capture
            target.style.display = 'block';
            target.style.position = 'absolute';
            target.style.top = '-9999px';
            target.style.left = '-9999px';

            html2canvas(target, {
                scale: 3,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                width: 900,
                logging: false,
            }).then(canvas => {
                const link = document.createElement('a');
                const mimeType = format === 'jpeg' ? 'image/jpeg' : 'image/png';
                link.href = canvas.toDataURL(mimeType, 1.0);
                link.download = 'election_results_' + Date.now() + '.' + format;
                link.click();

                // Hide again after capture
                target.style.display = 'none';
                target.style.position = '';
                target.style.top = '';
                target.style.left = '';
            });
        }
    </script>
</body>

</html>