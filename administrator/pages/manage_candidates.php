<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../config_db.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    session_destroy();
    header("Location: ../../auth/admin_login.php");
    exit();
}
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_destroy();
    header("Location: ../../auth/admin_login.php?error=Session+expired");
    exit();
}

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
require_once '../includes/admin_auth_guard.php';

// PAGKUHA NG LAHAT NG PARTYLISTS FOR CANDIDATE FORMS
$all_partylists = [];
$plRes = $conn->query("SELECT id, name FROM partylists ORDER BY name ASC");
while ($row = $plRes->fetch_assoc()) {
    $all_partylists[] = $row;
}

// PAG HANDLE NG AJAX REQUEST TO GET CANDIDATE DATA FOR EDITING
if (isset($_GET['action']) && $_GET['action'] === 'get_candidate' && isset($_GET['id'])) {
    $candidate_id = intval($_GET['id']);
    $stmt = $conn->prepare("
        SELECT c.*, pl.id AS partylist_id, pl.name AS partylist_name
        FROM candidates c
        LEFT JOIN partylists pl ON c.partylist_id = pl.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $candidate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    header('Content-Type: application/json');
    echo $result->num_rows > 0
        ? json_encode($result->fetch_assoc())
        : json_encode(['error' => 'Candidate not found']);
    $stmt->close();
    exit();
}

// MESSAGE HANDLING FOR TOAST NOTIFICATIONS 
$toast_msg  = '';
$toast_type = 'success';

if (isset($_GET['success'])) {
    $toast_type = 'success';
    switch ($_GET['success']) {
        case 'candidate_added':
            $toast_msg = 'Candidate added successfully!';
            break;
        case 'candidate_deleted':
            $toast_msg = 'Candidate deleted successfully!';
            break;
        case 'updated':
            $toast_msg = 'Candidate updated successfully!';
            break;
        case 'partylist_added':
            $toast_msg = 'Partylist added successfully!';
            break;
        case 'partylist_deleted':
            $toast_msg = 'Partylist deleted successfully!';
            break;
    }
} elseif (isset($_GET['error'])) {
    $toast_type = 'error';
    switch ($_GET['error']) {
        case 'invalid_student_id':
            $toast_msg = 'Invalid Student ID format. Use format like 12-3456';
            break;
        case 'invalid_department':
            $toast_msg = 'Department can only contain letters, numbers, spaces and hyphen (-)';
            break;
        case 'invalid_section':
            $toast_msg = 'Section can only contain letters, numbers, spaces and hyphen (-)';
            break;
        case 'empty_password':
            $toast_msg = 'Password is required.';
            break;
        case 'invalid_characters':
            $toast_msg = 'Special characters are not allowed except hyphen (-)';
            break;
        case 'student_exists':
            $toast_msg = 'Student ID already exists.';
            break;
        case 'insert_failed':
            $toast_msg = 'Failed to add student. Please try again.';
            break;
        case 'empty_fields':
            $toast_msg = 'All fields are required.';
            break;
        case 'db_error':
            $toast_msg = 'A database error occurred. Please try again.';
            break;
        case 'partylist_exists':
            $toast_msg = 'Partylist already exists or could not be added.';
            break;
        default:
            $toast_msg = htmlspecialchars($_GET['error']);
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates Management - SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/admin/manage_candidates.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>

<body>
    <?php include '../../logout_modal.php'; ?>

    <!-- Messages Modal -->
    <div id="messageModal" class="message-modal-overlay">
        <div class="modal-container">
            <h2 id="messageTitle"></h2>
            <p id="messageText"></p>
            <button onclick="closeMessageModal()" class="save">OK</button>
        </div>
    </div>

    <div class="header-main">
        <div class="header-container">
            <div class="header">
                <div class="logo-box">
                    <div class="logo-text">
                        <h1>Candidate Management</h1>
                        <p>Create, edit and manage candidates</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/sidebar_menu.php'; ?>

    <div class="main-wrapper">
        <div class="page-content">

            <div class="content-header" style="margin-top:40px;">
                <h2 class="header-title">Candidates</h2>
                <div style="display:flex; align-items:center; gap:20px;">
                    <button class="manage-partylist-btn" onclick="openPartylistModal()">
                        <i data-lucide="list" class="icon"></i> Manage Partylists
                    </button>
                    <button class="add-candidate-btn" onclick="openAddCandidateModal()">
                        <i data-lucide="plus" class="icon"></i> Add Candidate
                    </button>
                </div>
            </div>

            <!-- CANDIDATES BY POSITION -->
            <?php
            $positions = $conn->query("SELECT * FROM positions ORDER BY display_order");
            if ($positions && $positions->num_rows > 0):
                while ($position = $positions->fetch_assoc()):
                    $position_id         = $position['id'];
                    $candidates_per_page = 8;
                    $candidate_page_key  = 'cpage_' . $position_id;
                    $candidate_page      = isset($_GET[$candidate_page_key]) ? max(1, intval($_GET[$candidate_page_key])) : 1;
                    $candidate_offset    = ($candidate_page - 1) * $candidates_per_page;

                    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM candidates WHERE position_id = ?");
                    $count_stmt->bind_param("i", $position_id);
                    $count_stmt->execute();
                    $total_candidates = $count_stmt->get_result()->fetch_assoc()['total'];
                    $total_pages      = ceil($total_candidates / $candidates_per_page);
                    $count_stmt->close();
            ?>
                    <div class="position-section" data-position-id="<?= $position_id ?>">
                        <h3 class="position-title"><?= htmlspecialchars($position['position_name']) ?></h3>

                        <div class="candidates-grid">
                            <?php
                            // JOIN partylists so we get the name for display
                            $stmt = $conn->prepare("
                            SELECT c.*, 
                                   COALESCE(pl.name) AS partylist_name,
                                   pl.id AS partylist_id
                            FROM candidates c
                            LEFT JOIN partylists pl ON c.partylist_id = pl.id
                            WHERE c.position_id = ?
                            ORDER BY c.id DESC
                            LIMIT ? OFFSET ?
                        ");
                            $stmt->bind_param("iii", $position_id, $candidates_per_page, $candidate_offset);
                            $stmt->execute();
                            $candidates = $stmt->get_result();

                            if ($candidates->num_rows > 0):
                                while ($candidate = $candidates->fetch_assoc()):
                                    $display_name = $candidate['last_name'] . ', ' . $candidate['first_name'];
                                    if (!empty($candidate['middle_name'])) {
                                        $display_name .= ' ' . strtoupper(substr($candidate['middle_name'], 0, 1)) . '.';
                                    }
                                    $party_display = !empty($candidate['partylist_name']) ? $candidate['partylist_name'] : 'Independent';

                                    $photo_url     = trim($candidate['photo_url'] ?? '');
                                    $seed          = urlencode(strtoupper($candidate['last_name']) . ' ' . strtoupper($candidate['first_name']));
                                    $dicebear      = 'https://api.dicebear.com/7.x/initials/svg?seed=' . $seed
                                        . '&backgroundColor=6366f1&fontFamily=Arial&fontSize=38&bold=true&fontColor=ffffff';
                                    $is_real_photo = !empty($photo_url)
                                        && strpos($photo_url, 'ui-avatars.com') === false
                                        && strpos($photo_url, 'ui-avatars.io')  === false;
                                    $image_src     = $is_real_photo
                                        ? ((strpos($photo_url, 'http') === 0) ? $photo_url : '../../' . $photo_url)
                                        : $dicebear;
                            ?>
                                    <div class="candidate-card">
                                        <div class="candidate-info">
                                            <img src="<?= htmlspecialchars($image_src) ?>"
                                                alt="<?= htmlspecialchars($display_name) ?>"
                                                onerror="this.onerror=null; this.src='<?= htmlspecialchars($dicebear) ?>';">
                                            <div>
                                                <span class="candidate-name"><?= htmlspecialchars($display_name) ?></span>
                                                <span class="candidate-party"><?= htmlspecialchars($party_display) ?></span>
                                            </div>
                                        </div>
                                        <div class="candidate-actions">
                                            <button class="edit-btn"
                                                onclick="openEditCandidateModal(<?= $candidate['id'] ?>)"
                                                title="Edit candidate">
                                                <i data-lucide="edit" class="icon"></i>
                                            </button>
                                            <button class="delete-btn"
                                                onclick="openCandidateDeleteModal(<?= $candidate['id'] ?>, '<?= htmlspecialchars($display_name, ENT_QUOTES) ?>')"
                                                title="Delete candidate">
                                                <i data-lucide="trash-2" class="icon"></i>
                                            </button>
                                        </div>
                                    </div>
                            <?php
                                endwhile;
                            else:
                                echo "<p class='no-candidate'>No candidates yet for this position.</p>";
                            endif;
                            $stmt->close();
                            ?>
                        </div>

                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($candidate_page > 1): ?>
                                    <a href="?<?= $candidate_page_key ?>=<?= $candidate_page - 1 ?>" class="page-btn">Previous</a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?<?= $candidate_page_key ?>=<?= $i ?>"
                                        class="page-btn <?= $i == $candidate_page ? 'active' : '' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <?php if ($candidate_page < $total_pages): ?>
                                    <a href="?<?= $candidate_page_key ?>=<?= $candidate_page + 1 ?>" class="page-btn">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
            <?php endwhile;
            endif; ?>

        </div>
    </div>


    <!-- ADD CANDIDATE MODAL -->
    <div class="edit-modal-overlay" id="addCandidateModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add New Candidate</h2>
                <button onclick="closeAddModal()" class="modal-close">&times;</button>
            </div>

            <form method="POST" action="../actions/add_candidate.php"
                enctype="multipart/form-data" class="modal-form">

                <div class="input-container">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="input-container">
                    <label>Middle Initial</label>
                    <input type="text" name="middle_name" maxlength="1">
                </div>
                <div class="input-container">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="input-container">
                    <label>Position *</label>
                    <select name="position_id" required>
                        <option value="">Select Position</option>
                        <?php
                        $pq = $conn->query("SELECT * FROM positions ORDER BY display_order");
                        if ($pq) while ($pos = $pq->fetch_assoc()):
                        ?>
                            <option value="<?= $pos['id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="input-container" id="partylist-field"
                    <?= empty($all_partylists) ? 'style="display:none;"' : '' ?>>
                    <label>Partylist</label>
                    <select name="partylist_id" id="add_partylist_id">
                        <option value="">Select Partylist</option>
                        <?php foreach ($all_partylists as $pl): ?>
                            <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (empty($all_partylists)): ?>
                    <input type="hidden" name="partylist_id" value="">
                <?php endif; ?>
                <div class="input-container">
                    <label>Photo</label>
                    <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png">
                </div>

                <div class="form-btn">
                    <button type="submit" class="save" name="add_candidate">
                        <i data-lucide="save" class="icon"></i> Save
                    </button>
                    <button type="button" onclick="closeAddModal()" class="cancel">
                        <i data-lucide="x" class="icon"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- EDIT CANDIDATE MODAL -->
    <div class="edit-modal-overlay" id="editCandidateModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Edit Candidate</h2>
                <button onclick="closeEditModal()" class="modal-close">&times;</button>
            </div>

            <form method="POST" action="../actions/edit_candidate.php"
                class="modal-form" enctype="multipart/form-data">
                <input type="hidden" name="candidate_id" id="edit_candidate_id">
                <input type="hidden" name="edit_candidate" value="1">

                <div class="input-container">
                    <label>First Name *</label>
                    <input type="text" name="first_name" id="edit_first_name" required>
                </div>
                <div class="input-container">
                    <label>Middle Initial</label>
                    <input type="text" name="middle_name" id="edit_middle_name" maxlength="1">
                </div>
                <div class="input-container">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" id="edit_last_name" required>
                </div>
                <div class="input-container">
                    <label>Position *</label>
                    <select name="position_id" id="edit_position_id" required>
                        <option value="">Select Position</option>
                        <?php
                        $pq = $conn->query("SELECT * FROM positions ORDER BY display_order");
                        if ($pq) while ($pos = $pq->fetch_assoc()):
                        ?>
                            <option value="<?= $pos['id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="input-container" <?= empty($all_partylists) ? 'style="display:none;"' : '' ?>>
                    <label>Partylist *</label>
                    <select name="partylist_id" id="edit_partylist_id">
                        <option value="">Select Partylist</option>
                        <?php foreach ($all_partylists as $pl): ?>
                            <option value="<?= $pl['id'] ?>"><?= htmlspecialchars($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-container">
                    <label>Photo</label>
                    <input type="file" name="photo" id="edit_photo" accept="image/jpeg,image/jpg,image/png">
                </div>

                <div class="form-btn">
                    <button type="submit" class="save">
                        <i data-lucide="save" class="icon"></i> Update
                    </button>
                    <button type="button" onclick="closeEditModal()" class="cancel">
                        <i data-lucide="x" class="icon"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- DELETE CANDIDATE MODAL -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <h3>Delete Candidate</h3>
            <p id="deleteMessage"></p>
            <div class="modal-actions">
                <button class="cancel-delete" onclick="closeCandidateDeleteModal()">Cancel</button>
                <form method="GET" action="../actions/delete_candidate.php">
                    <input type="hidden" name="id" id="deleteCandidateId">
                    <button type="submit" class="confirm-delete">Delete</button>
                </form>
            </div>
        </div>
    </div>


    <!-- MANAGE PARTYLISTS MODAL -->
    <div class="edit-modal-overlay" id="partylistModal">
        <div class="modal-container" style="max-width: 520px;">
            <div class="modal-header">
                <h2>Manage Partylists</h2>
                <button onclick="closePartylistModal()" class="modal-close">&times;</button>
            </div>

            <div class="modal-form">
                <!-- ADD PARTYLIST FORM -->
                <form method="POST" action="../actions/add_partylist.php"
                    style="display:flex; gap:10px; align-items:flex-end;">
                    <div class="input-container" style="flex:1; margin-bottom:0;">
                        <label>New Partylist Name *</label>
                        <input type="text" name="partylist_name" placeholder="e.g. Alliance Party" required>
                    </div>
                    <button type="submit" class="save" style="height:40px; white-space:nowrap;">
                        <i data-lucide="plus" class="icon"></i> Add
                    </button>
                </form>

                <hr style="border-color:#e5e7eb; margin:14px 0 10px;">

                <!-- PARTYLIST LIST WITH EDIT COLOR BUTTON -->
                <div style="display:flex; flex-direction:column; gap:8px;" id="partylistListContainer">
                    <?php if (empty($all_partylists)): ?>
                        <p style="color:#6b7280; font-size:14px;">No partylists added yet.</p>
                    <?php else: ?>
                        <?php foreach ($all_partylists as $i => $pl): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center;
                    padding:10px 14px; background:#f4f6f8; border-radius:8px; gap:8px;">

                                <!-- PARTYLIST NAME -->
                                <span style="font-size:14px; font-weight:500; overflow:hidden;
                             text-overflow:ellipsis; white-space:nowrap; flex:1;">
                                    <?= htmlspecialchars($pl['name']) ?>
                                </span>

                                <!-- ACTION BUTTONS: COLOR + DELETE side by side -->
                                <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">

                                    <!-- COLOR BUTTON — always visible, shows current color -->
                                    <div style="position:relative;">
                                        <button id="plSwatch<?= $i ?>"
                                            onclick="document.getElementById('plPicker<?= $i ?>').click()"
                                            title="Change chart color"
                                            style="width:80px; height:32px; border-radius:6px; cursor:pointer;
                                   border:1.5px solid rgba(0,0,0,0.15); font-size:12px;
                                   font-weight:600; color:#fff; display:flex; align-items:center;
                                   justify-content:center; gap:4px; padding:0 8px;
                                   text-shadow:0 1px 2px rgba(0,0,0,0.4);
                                   background:#1e3a8a;">
                                             Edit Color
                                        </button>
                                        <input type="color" id="plPicker<?= $i ?>"
                                            data-index="<?= $i ?>"
                                            style="position:absolute; opacity:0; width:0; height:0; pointer-events:none;"
                                            oninput="onPlColorPick(<?= $i ?>, this.value)">
                                    </div>

                                    <!-- DELETE BUTTON -->
                                    <button onclick="openPartylistDeleteModal(<?= $pl['id'] ?>, '<?= htmlspecialchars($pl['name'], ENT_QUOTES) ?>')"
                                        style="color:#dc3545; font-size:13px; padding:4px 10px;
                               border-radius:6px; background:#fee2e2; border:1px solid #fecaca;
                               cursor:pointer; display:flex; align-items:center; gap:4px;
                               height:32px; white-space:nowrap;">
                                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- COLOR PALETTE PREVIEW STRIP -->
                <?php if (!empty($all_partylists)): ?>
                    <div style="margin-top:14px;">
                        <div style="font-size:11px; font-weight:600; color:#9ca3af; letter-spacing:.5px;
                            text-transform:uppercase; margin-bottom:6px;">Chart Color Preview</div>
                        <div id="plPreviewStrip"
                            style="display:flex; height:10px; border-radius:6px; overflow:hidden; gap:2px;">
                        </div>
                        <div style="display:flex; justify-content:flex-end; margin-top:8px;">
                            <button id="plResetColorsBtn"
                                style="font-size:12px; color:#6b7280; background:none; border:none;
                                   cursor:pointer; padding:0; text-decoration:underline;">
                                ↺ Reset to defaults
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- DELETE PARTYLIST MODAL -->
    <div class="modal-overlay" id="partylistDeleteModal">
        <div class="modal-box">
            <h3>Delete Partylist</h3>
            <p id="partylistDeleteMessage"></p>
            <div class="modal-actions">
                <button class="cancel-delete" onclick="closePartylistDeleteModal()">Cancel</button>
                <a id="confirmPartylistDeleteBtn" class="confirm-delete" style="text-decoration:none;">Delete</a>
            </div>
        </div>
    </div>


    <script>
        lucide.createIcons();

        // PARTYLIST COLOR EDITOR  
        const PL_COLOR_LS_KEY = 'softvote_global_colors';
        const PL_DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];
        const PL_COUNT = <?= count($all_partylists) ?>;

        function loadPlColors() {
            try {
                const s = localStorage.getItem(PL_COLOR_LS_KEY);
                if (s) {
                    const a = JSON.parse(s);
                    if (Array.isArray(a) && a.length) {
                        const out = [...a];
                        while (out.length < PL_COUNT) {
                            out.push(PL_DEFAULT_PALETTE[out.length % PL_DEFAULT_PALETTE.length]);
                        }
                        return out;
                    }
                }
            } catch (e) {}
            return Array.from({
                length: PL_COUNT
            }, (_, i) => PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length]);
        }

        function savePlColors() {
            try {
                localStorage.setItem(PL_COLOR_LS_KEY, JSON.stringify(plColors));
            } catch (e) {}
        }

        let plColors = loadPlColors();

        function syncPlSwatches() {
            for (let i = 0; i < PL_COUNT; i++) {
                const color = plColors[i] ?? PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length];
                const btn = document.getElementById('plSwatch' + i);
                const picker = document.getElementById('plPicker' + i);
                if (btn) btn.style.background = color;
                if (picker) picker.value = color;
            }
            syncPlPreviewStrip();
        }

        function syncPlPreviewStrip() {
            const strip = document.getElementById('plPreviewStrip');
            if (!strip) return;
            strip.innerHTML = '';
            for (let i = 0; i < PL_COUNT; i++) {
                const seg = document.createElement('div');
                seg.style.cssText = `flex:1; background:${plColors[i] ?? PL_DEFAULT_PALETTE[0]}; border-radius:3px; min-width:4px;`;
                strip.appendChild(seg);
            }
        }

        function onPlColorPick(index, value) {
            plColors[index] = value;
            const swatch = document.getElementById('plSwatch' + index);
            if (swatch) swatch.style.background = value;
            savePlColors();
            syncPlPreviewStrip();
        }

        // Reset button
        document.getElementById('plResetColorsBtn')?.addEventListener('click', () => {
            plColors = Array.from({
                length: PL_COUNT
            }, (_, i) => PL_DEFAULT_PALETTE[i % PL_DEFAULT_PALETTE.length]);
            savePlColors();
            syncPlSwatches();
        });

        function openPartylistModal() {
            document.getElementById('partylistModal').classList.add('active');
            lucide.createIcons();
            plColors = loadPlColors();
            requestAnimationFrame(() => syncPlSwatches());
        }

        const _urlParams = new URLSearchParams(window.location.search);
        const _scrollToId = _urlParams.get('scroll_to');

        <?php if ($toast_msg): ?>
                (function() {
                    showMessageModal(
                        <?= $toast_type === 'success' ? '"Success"' : '"Error"' ?>,
                        "<?= addslashes(htmlspecialchars_decode($toast_msg)) ?>",
                        <?= $toast_type === 'success' ? 'true' : 'false' ?>
                    );
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, 'manage_candidates.php');
                    }
                })();
        <?php endif; ?>

        if (_scrollToId) {
            const target = document.querySelector(`[data-position-id="${_scrollToId}"]`);
            if (target) {
                setTimeout(() => {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 300);
            }
        }
        // Toast on page load 
        <?php if ($toast_msg): ?>
                (function() {
                    showMessageModal(
                        <?= $toast_type === 'success' ? '"Success"' : '"Error"' ?>,
                        "<?= addslashes(htmlspecialchars_decode($toast_msg)) ?>",
                        <?= $toast_type === 'success' ? 'true' : 'false' ?>
                    );
                    if (window.history.replaceState) {
                        window.history.replaceState({}, document.title, 'manage_candidates.php');
                    }
                })();
        <?php endif; ?>

        function showMessageModal(title, message, isSuccess = true) {
            const modal = document.getElementById('messageModal');
            const titleEl = document.getElementById('messageTitle');
            const textEl = document.getElementById('messageText');
            titleEl.innerText = title;
            textEl.innerText = message;
            titleEl.style.color = isSuccess ? '#10b981' : '#ef4444';
            modal.classList.add('active');
            setTimeout(closeMessageModal, 2500);
        }

        function closeMessageModal() {
            document.getElementById('messageModal').classList.remove('active');
        }

        // Add Candidate Modal 
        function openAddCandidateModal() {
            document.getElementById('addCandidateModal').classList.add('active');
            lucide.createIcons();
        }

        function closeAddModal() {
            const modal = document.getElementById('addCandidateModal');
            modal.classList.remove('active');
            modal.querySelector('form').reset();
        }

        // Edit Candidate Modal 
        function openEditCandidateModal(candidateId) {
            fetch('manage_candidates.php?action=get_candidate&id=' + candidateId)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert('Error loading candidate data.');
                        return;
                    }

                    document.getElementById('edit_candidate_id').value = data.id;
                    document.getElementById('edit_first_name').value = data.first_name || '';
                    document.getElementById('edit_middle_name').value = data.middle_name || '';
                    document.getElementById('edit_last_name').value = data.last_name || '';
                    document.getElementById('edit_position_id').value = data.position_id || '';

                    const partySelect = document.getElementById('edit_partylist_id');
                    partySelect.value = data.partylist_id ?? '';

                    document.getElementById('editCandidateModal').classList.add('active');
                    lucide.createIcons();
                })
                .catch(() => alert('Error loading candidate data.'));
        }

        function closeEditModal() {
            const modal = document.getElementById('editCandidateModal');
            modal.classList.remove('active');
            modal.querySelector('form').reset();
        }

        // Delete Candidate Modal 
        function openCandidateDeleteModal(candidateId, candidateName) {
            document.getElementById('deleteCandidateId').value = candidateId;
            document.getElementById('deleteMessage').innerText = `Are you sure you want to delete ${candidateName}?`;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeCandidateDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        function closePartylistModal() {
            document.getElementById('partylistModal').classList.remove('active');
        }

        // Delete Partylist Modal 
        function openPartylistDeleteModal(id, name) {
            document.getElementById('partylistDeleteMessage').innerText = `Are you sure you want to delete '${name}'?`;
            document.getElementById('confirmPartylistDeleteBtn').href = `../actions/delete_partylist.php?id=${id}`;
            document.getElementById('partylistDeleteModal').classList.add('show');
        }

        function closePartylistDeleteModal() {
            document.getElementById('partylistDeleteModal').classList.remove('show');
        }

        // Outside-click closes modals 
        ['editCandidateModal', 'addCandidateModal', 'partylistModal', 'messageModal'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('active');
            });
        });
        ['deleteModal', 'partylistDeleteModal'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('show');
            });
        });

        // ESC key closes all modals 
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            closeEditModal();
            closeAddModal();
            closeCandidateDeleteModal();
            closePartylistDeleteModal();
            closePartylistModal();
            closeMessageModal();
        });
    </script>
</body>

</html>