<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';

date_default_timezone_set('Asia/Manila');
$active_nav = 'admins';
function phTime($datetime, $format = 'M d, H:i')
{
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format($format);
}



// FETCH ADMINS
$admins = [];
$r = $conn->query("SELECT id, username, name, created_at,
    COALESCE(is_active, 1) AS is_active,
    last_login
    FROM administrators
    WHERE (is_deleted = 0 OR is_deleted IS NULL)
    ORDER BY created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $admins[] = $row;

// TOASTS FROM REDIRECT
$toast_success = htmlspecialchars($_GET['success'] ?? '');
$toast_error   = htmlspecialchars($_GET['error']   ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Admins — SOFTVOTE</title>
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
                    <h1>Manage <span>Admins</span></h1>
                    <p>Create, edit, suspend, or remove administrator accounts</p>
                </div>
                <button class="topbar-btn primary" onclick="openAddModal()">
                    <i data-lucide="plus"></i>Add Admin
                </button>
            </div>

            <div class="table-wrap">
                <div class="table-header">
                    <div class="table-title"><i data-lucide="shield"></i>Administrators</div>
                    <span class="panel-badge"><?= count($admins) ?> total</span>
                </div>

                <!-- Search and Filter toolbar -->
                <div class="table-toolbar">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" id="adminSearch" placeholder="Search by name or username…" oninput="filterAdmins()">
                    </div>
                    <select class="filter-select" id="adminStatusFilter" onchange="filterAdmins()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="table-container">
                    <?php if (empty($admins)): ?>
                        <div class="empty-state">No administrator accounts found.</div>
                    <?php else: ?>
                        <table id="adminTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin):
                                    $isActive    = (int)$admin['is_active'];
                                    $statusLabel = $isActive ? 'Active' : 'Inactive';
                                    $statusClass = $isActive ? 'badge-active' : 'badge-inactive';
                                    $lastLogin = $admin['last_login']
                                        ? phTime($admin['last_login'])
                                        : 'Never';
                                    $toggleLabel = $isActive ? 'Suspend' : 'Activate';
                                    $toggleIcon  = $isActive ? 'pause-circle' : 'play-circle';
                                ?>
                                    <tr data-name="<?= strtolower(htmlspecialchars($admin['name'])) ?>"
                                        data-username="<?= strtolower(htmlspecialchars($admin['username'])) ?>"
                                        data-status="<?= $isActive ? 'active' : 'inactive' ?>">
                                        <td><span class="td-name"><?= htmlspecialchars($admin['name']) ?></span></td>
                                        <td><span class="td-mono">@<?= htmlspecialchars($admin['username']) ?></span></td>
                                        <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                        <td class="td-date td-last-login"
                                            data-utc="<?= $admin['last_login'] ? htmlspecialchars($admin['last_login']) : '' ?>">
                                            <?= $lastLogin ?>
                                        </td>
                                        <td class="td-date"><?= date('M d, Y', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <div class="action-group">
                                                <!-- Edit -->
                                                <button class="action-btn edit-btn"
                                                    onclick="openEditModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['name'])) ?>', '<?= htmlspecialchars(addslashes($admin['username'])) ?>')">
                                                    <i data-lucide="pencil" class="icon"></i>Edit
                                                </button>
                                                <!-- Reset Password -->
                                                <button class="action-btn reset-btn"
                                                    onclick="openResetModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['name'])) ?>')">
                                                    <i data-lucide="key-round" class="icon"></i>Reset PW
                                                </button>
                                                <!-- Toggle Active/Suspend -->
                                                <button class="action-btn toggle-btn"
                                                    onclick="openToggleModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['name'])) ?>', <?= $isActive ?>)">
                                                    <i data-lucide="<?= $toggleIcon ?>" class="icon"></i><?= $toggleLabel ?>
                                                </button>
                                                <!-- Delete — protect self -->
                                                <?php if ($admin['id'] !== $sa_id): ?>
                                                    <button class="action-btn"
                                                        onclick="openDeleteModal(<?= $admin['id'] ?>, '<?= htmlspecialchars(addslashes($admin['name'])) ?>')">
                                                        <i data-lucide="trash-2" class="icon"></i>Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE ADMIN MODAL -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Delete Admin?</h3>
            <p id="deleteMsg">This will permanently remove the admin account. This cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('deleteModal')">Cancel</button>
                <form method="POST" action="../superadmin_actions.php" style="display:inline">
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" name="admin_id" id="deleteAdminId">
                    <button type="submit" class="btn-confirm">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- ADD ADMIN MODAL -->
    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <h3>Add Administrator</h3>
            <p>Create a new admin account with access to the election management panel.</p>
            <form method="POST" action="../superadmin_actions.php">
                <input type="hidden" name="action" value="create_admin">
                <div class="field"><label>Full Name</label><input type="text" name="name" placeholder="Juan Dela Cruz" required></div>
                <div class="field"><label>Username</label><input type="text" name="username" placeholder="jdelacruz" required></div>
                <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT ADMIN MODAL -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3>Edit Administrator</h3>
            <p>Update the admin's name or username.</p>
            <form method="POST" action="../superadmin_actions.php">
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="admin_id" id="editAdminId">
                <div class="field"><label>Full Name</label><input type="text" name="name" id="editName" placeholder="Juan Dela Cruz" required></div>
                <div class="field"><label>Username</label><input type="text" name="username" id="editUsername" placeholder="jdelacruz" required></div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-blue">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RESET PASSWORD MODAL -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal">
            <h3>Reset Password</h3>
            <p id="resetMsg">Set a new password for this admin account.</p>
            <form method="POST" action="../superadmin_actions.php">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="admin_id" id="resetAdminId">
                <div class="field"><label>New Password</label><input type="password" name="new_password" placeholder="••••••••" required minlength="6"></div>
                <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" placeholder="••••••••" required minlength="6"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('resetModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOGGLE STATUS MODAL -->
    <div class="modal-overlay" id="toggleModal">
        <div class="modal">
            <h3 id="toggleTitle">Suspend Admin?</h3>
            <p id="toggleMsg">This admin will no longer be able to log in.</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal('toggleModal')">Cancel</button>
                <form method="POST" action="../superadmin_actions.php" style="display:inline">
                    <input type="hidden" name="action" value="toggle_admin_status">
                    <input type="hidden" name="admin_id" id="toggleAdminId">
                    <input type="hidden" name="new_status" id="toggleNewStatus">
                    <button type="submit" class="btn-confirm" id="toggleConfirmBtn">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

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

        window.addEventListener('load', () => {
            <?php if ($toast_success): ?>
                showToast('success', <?= json_encode($toast_success) ?>);
            <?php endif; ?>
            <?php if ($toast_error): ?>
                showToast('error', <?= json_encode($toast_error) ?>);
            <?php endif; ?>
        });

        /* Modal helpers */
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function openDeleteModal(id, name) {
            document.getElementById('deleteAdminId').value = id;
            document.getElementById('deleteMsg').textContent =
                `This will permanently remove "${name}". This cannot be undone.`;
            document.getElementById('deleteModal').classList.add('open');
        }

        function openAddModal() {
            document.getElementById('addModal').classList.add('open');
        }

        function openEditModal(id, name, username) {
            document.getElementById('editAdminId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editUsername').value = username;
            document.getElementById('editModal').classList.add('open');
        }

        function openResetModal(id, name) {
            document.getElementById('resetAdminId').value = id;
            document.getElementById('resetMsg').textContent = `Set a new password for "${name}".`;
            document.getElementById('resetModal').classList.add('open');
        }

        function openToggleModal(id, name, isActive) {
            document.getElementById('toggleAdminId').value = id;
            document.getElementById('toggleNewStatus').value = isActive ? 0 : 1;
            if (isActive) {
                document.getElementById('toggleTitle').textContent = `Suspend "${name}"?`;
                document.getElementById('toggleMsg').textContent = 'This admin will not be able to log in until reactivated.';
                document.getElementById('toggleConfirmBtn').textContent = 'Yes, Suspend';
            } else {
                document.getElementById('toggleTitle').textContent = `Activate "${name}"?`;
                document.getElementById('toggleMsg').textContent = 'This admin will regain access to the election panel.';
                document.getElementById('toggleConfirmBtn').textContent = 'Yes, Activate';
            }
            document.getElementById('toggleModal').classList.add('open');
        }

        /* Fixed — only close when clicking the dark overlay itself, not children */
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('mousedown', e => {
                if (e.target === overlay) overlay.classList.remove('open');
            });
        });

        /* prevent Enter key from closing modals while typing in inputs */
        document.querySelectorAll('.modal input, .modal textarea, .modal select').forEach(input => {
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') e.preventDefault();
            });
        });

        /* Admin table search and filtering */
        function filterAdmins() {
            const q = document.getElementById('adminSearch').value.toLowerCase();
            const status = document.getElementById('adminStatusFilter').value;
            document.querySelectorAll('#adminTable tbody tr').forEach(row => {
                const nameMatch = row.dataset.name.includes(q) || row.dataset.username.includes(q);
                const statusMatch = !status || row.dataset.status === status;
                row.style.display = (nameMatch && statusMatch) ? '' : 'none';
            });
        }

        /* Real-time relative last login */
        function timeAgo(utcDateStr) {
            if (!utcDateStr) return 'Never';

            // Parse as UTC (append Z if not present)
            const date = new Date(utcDateStr.trim().replace(' ', 'T') + 'Z');
            if (isNaN(date)) return 'Never';

            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
            if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
            if (seconds < 2592000) return `${Math.floor(seconds / 86400)}d ago`;
            if (seconds < 31536000) return `${Math.floor(seconds / 2592000)}mo ago`;
            return `${Math.floor(seconds / 31536000)}y ago`;
        }

        function updateLastLogins() {
            document.querySelectorAll('.td-last-login').forEach(cell => {
                const utc = cell.dataset.utc;
                cell.textContent = timeAgo(utc);
            });
        }

        // Run immediately and refresh every 30 seconds
        updateLastLogins();
        setInterval(updateLastLogins, 30000);
    </script>
</body>

</html>