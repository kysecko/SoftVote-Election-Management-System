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

$students_per_page = 8;
$student_page = isset($_GET['student_page']) ? max(1, intval($_GET['student_page'])) : 1;
$student_offset = ($student_page - 1) * $students_per_page;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management - SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/admin/manage_students.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        /* BULK TOOLBAR - NAGLALAMAN NG MGA FILTER AT BULK ACTION BUTTONS */
        .bulk-toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            background: #fff;
            color: #374151;
            cursor: pointer;
            transition: border-color .2s;
            min-width: 150px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .bulk-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background .2s, opacity .2s;
        }

        .bulk-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .bulk-btn-edit {
            background: #3b82f6;
            color: #fff;
        }

        .bulk-btn-edit:hover:not(:disabled) {
            background: #2563eb;
        }

        .bulk-btn-delete {
            background: #ef4444;
            color: #fff;
        }

        .bulk-btn-delete:hover:not(:disabled) {
            background: #dc2626;
        }

        .selection-count {
            font-size: 0.8rem;
            color: #6b7280;
            margin-left: auto;
            white-space: nowrap;
        }

        /* CHECKBOX STYLING - PARA SA BAWAT ROW AT SELECT ALL HEADER */
        .cb-cell {
            width: 40px;
            text-align: center;
        }

        input[type="checkbox"].row-cb,
        input[type="checkbox"]#selectAllCb {
            width: 16px;
            height: 16px;
            accent-color: #2563eb;
            cursor: pointer;
        }

        /* BULK EDIT MODAL - PARA SA PAG-EDIT NG MARAMING ESTUDYANTE */
        .bulk-edit-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .bulk-edit-modal-overlay.active {
            display: flex;
        }

        .bulk-edit-box {
            background: #fff;
            border-radius: 14px;
            padding: 32px 28px;
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
        }

        .bulk-edit-box h2 {
            margin: 0 0 6px;
            font-size: 1.2rem;
            color: #111827;
        }

        .bulk-edit-box p.sub {
            margin: 0 0 20px;
            font-size: 0.82rem;
            color: #6b7280;
        }

        /* BULK DELETE CONFIRM MODAL - PARA SA KUMPIRMASYON NG PAGBUBURA */
        .bulk-confirm-box {
            background: #fff;
            border-radius: 14px;
            padding: 32px 28px;
            width: 420px;
            max-width: 95vw;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .18);
            text-align: center;
        }

        .bulk-confirm-box h3 {
            color: #ef4444;
            margin: 0 0 10px;
        }

        .bulk-confirm-box p {
            color: #374151;
            margin: 0 0 24px;
            font-size: 0.9rem;
        }

        .bulk-confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        /* COUNTDOWN MODAL - KATULAD NG SA DASHBOARD PARA SA BULK DELETE CONFIRMATION */
        .countdown-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
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
        }

        .countdown-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            background: #fee2e2;
            color: #ef4444;
        }

        .countdown-icon svg {
            width: 26px;
            height: 26px;
        }

        .countdown-box h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px;
        }

        .cd-subtitle {
            font-size: 0.83rem;
            color: #6b7280;
            margin: 0 0 22px;
            line-height: 1.5;
        }

        /* RING TIMER - SVG CIRCULAR PROGRESS */
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
            stroke: #fee2e2;
            stroke-width: 8;
        }

        .ring-fill {
            fill: none;
            stroke: #ef4444;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .ring-number {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 800;
            color: #ef4444;
        }

        .ring-number.urgent {
            color: #dc2626;
            animation: pulse-num .4s ease infinite alternate;
        }

        @keyframes pulse-num {
            from {
                transform: scale(1);
            }

            to {
                transform: scale(1.15);
            }
        }

        /* COUNTDOWN BUTTONS */
        .countdown-btns {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .cd-cancel {
            padding: 9px 20px;
            border-radius: 8px;
            border: 1.5px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .cd-cancel:hover {
            background: #f3f4f6;
        }

        .cd-delete-now {
            padding: 9px 20px;
            border-radius: 8px;
            border: none;
            background: #ef4444;
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }

        .cd-delete-now:hover {
            background: #dc2626;
        }
    </style>
</head>

<body>
    <?php include '../../logout_modal.php'; ?>

    <div id="messageModal" class="edit-modal-overlay">
        <div class="modal-container" style="text-align:center; max-width:400px; display: flex;flex-direction: column; justify-content: center; align-items: center;">
            <h2 id="messageTitle" style="margin-bottom:10px;"></h2>
            <p id="messageText" style="margin-bottom:20px;"></p>
            <button onclick="closeMessageModal()" class="save" style="align-self:center">
                OK
            </button>
        </div>
    </div>

    <div class="header-main">
        <div class="header-container">
            <div class="header">

                <div class="logo-box">
                    <div class="logo-text">
                        <h1>Student Management</h1>
                        <p>Create, edit and manage student accounts</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php include '../includes/sidebar_menu.php'; ?>


    <div class="main-wrapper">

        <div class="page-content">
            <div class="content-header" style="margin-top: 40px;">
                <h2 class="header-title">Add New Student Account</h2>
                <button class="add-candidate-btn" onclick="openAddStudentModal()">
                    <i data-lucide="plus" class="icon"></i>Add Account
                </button>
            </div>

            <!-- ADD STUDENT MODAL -->
            <div class="edit-modal-overlay" id="addStudentModal">
                <div class="modal-container">
                    <div class="modal-header">
                        <h2>Add New Student</h2>
                        <button class="modal-close" onclick="closeAddStudentModal()">&times;</button>
                    </div>

                    <form method="POST" action="../actions/add_student.php"
                        id="studentForm" class="modal-form">

                        <!-- ERROR MESSAGE INSIDE MODAL -->
                        <div id="addStudentError"
                            style="
        display:none;
        background:#fee2e2;
        color:#b91c1c;
        border:1px solid #fecaca;
        padding:10px 12px;
        border-radius:8px;
        margin-bottom:15px;
        font-size:0.875rem;
    ">
                        </div>

                        <div class="input-container">
                            <label>Student ID</label>
                            <input type="text" name="student_id" placeholder="e.g. 2026-001" required>
                        </div>

                        <div class="input-container">
                            <label>Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="add-password" placeholder="Create password" required>
                                <button type="button" class="toggle-btn" id="toggle-add-password">
                                    <i data-lucide="eye" id="add-eye-icon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="input-container">
                            <label>Email</label>
                            <input type="email" name="email" placeholder="juandelacruz@gmail.com" required>
                        </div>

                        <div class="input-container">
                            <label>Department</label>
                            <input type="text" name="department" placeholder="Computer Science" required>
                        </div>

                        <div class="input-container">
                            <label>Section</label>
                            <input type="text" name="section" placeholder="BSIT 2A" required>
                        </div>

                        <div class="form-btn">
                            <button class="save" type="submit" name="add_student">
                                <i data-lucide="save" class="icon"></i> Save
                            </button>

                            <label class="upload-btn" for="csv_file">
                                <i data-lucide="upload" class="icon"></i> Upload CSV
                            </label>

                            <input type="file" id="csv_file" name="csv_file" accept=".csv" hidden>

                            <button class="cancel" type="button" onclick="closeAddStudentModal()">
                                <i data-lucide="x" class="icon"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- UPLOAD PROGRESS MODAL -->
            <div id="uploadingModal" class="upload-modal-overlay" style="display:none;">
                <div class="modal-box">
                    <div class="spinner" id="uploadSpinner"></div>
                    <h3>Uploading CSV...</h3>
                    <p id="uploadStageLabel" style="font-size:13px; color:#6b7280; margin:0 0 12px;">
                        Sending file to server...
                    </p>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>
                    <small id="progressText">0%</small>
                </div>
            </div>

            <!-- RESULT MODAL -->
            <div id="resultModal" class="upload-modal-overlay" style="display:none;">
                <div class="modal-box">
                    <div id="resultIcon"></div>
                    <h3 id="resultTitle"></h3>
                    <p id="resultMessage"></p>
                    <button class="save" onclick="closeResultModal()" style="margin-left: 40%;">OK</button>
                </div>
            </div>

            <div class="search-container">
                <div class="search-input-wrapper">
                    <i data-lucide="search" class="search-icon"></i>
                    <input type="text" id="search"
                        placeholder="Search for Student ID, Department or Section...">
                </div>
            </div>

            <!-- BULK TOOLBAR - MGA FILTER AT BULK ACTION BUTTONS -->
            <div class="bulk-toolbar">
                <!-- DEPARTMENT FILTER DROPDOWN -->
                <select id="filterDept" class="filter-select">
                    <option value="">All Departments</option>
                </select>

                <!-- SECTION FILTER DROPDOWN -->
                <select id="filterSection" class="filter-select">
                    <option value="">All Sections</option>
                </select>

                <!-- BULK ACTION BUTTONS - NAKA-DISABLE HANGGANG MAY NAPILI -->
                <button class="bulk-btn bulk-btn-edit" id="bulkEditBtn" disabled onclick="openBulkEditModal()">
                    <i data-lucide="pencil" class="icon"></i> Edit Selected
                </button>
                <button class="bulk-btn bulk-btn-delete" id="bulkDeleteBtn" disabled onclick="openBulkDeleteModal()">
                    <i data-lucide="trash-2" class="icon"></i> Delete Selected
                </button>

                <span class="selection-count" id="selectionCount"></span>
            </div>

            <!-- DISPLAY STUDENTS THROUGH AJAX RESULTS -->
            <div id="studentsSection" class="position-section">
                <div class="table-wrapper">
                    <table class="table">
                        <thead class="table-header">
                            <tr class="label-header">
                                <th class="cb-cell title">
                                    <input type="checkbox" id="selectAllCb" title="Select all visible">
                                </th>
                                <th class="title">Student ID</th>
                                <th class="title">Department</th>
                                <th class="title">Section</th>
                                <th class="title">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <!-- Dynamic Putting ng JAVASCRIPT -->
                        </tbody>
                    </table>
                </div>
                <div id="paginationContainer"></div>
            </div>
            <div id="noStudentsMsg" style="display:none; padding:20px; color:#6b7280;">
                No student accounts found.
            </div>

            <!-- EDIT STUDENT MODAL -->
            <div class="edit-modal-overlay" id="editStudentModal">
                <div class="modal-container">
                    <div class="modal-header">
                        <h2>Edit Student</h2>
                        <button class="modal-close" onclick="closeEditStudentModal()">&times;</button>
                    </div>

                    <form id="editStudentForm" method="POST" action="../actions/edit_student.php" class="modal-form">
                        <input type="hidden" name="id" id="editStudentId">

                        <div id="editStudentError"
                            style="
                    display:none;
                    background:#fee2e2;
                    color:#b91c1c;
                    border:1px solid #fecaca;
                    padding:10px 12px;
                    border-radius:8px;
                    margin-bottom:15px;
                    font-size:0.875rem;
                ">
                        </div>

                        <div class="input-container">
                            <label>Student ID</label>
                            <input type="text" name="student_id" id="editStudentIdField" required>
                        </div>

                        <div class="input-container">
                            <label for="editEmail">Email</label>
                            <input type="email" id="editEmail" name="email" required>
                        </div>

                        <div class="input-container">
                            <label for="editDepartment">Department</label>
                            <input type="text" id="editDepartment" name="department" required>
                        </div>

                        <div class="input-container">
                            <label for="editSection">Section</label>
                            <input type="text" id="editSection" name="section" required>
                        </div>

                        <div class="form-btn">
                            <button type="submit" class="save">
                                <i data-lucide="save" class="icon"></i> Update
                            </button>
                            <button type="button" class="cancel" onclick="closeEditStudentModal()">
                                <i data-lucide="x" class="icon"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- DELETE STUDENT MODAL -->
            <div class="modal-overlay" id="deleteModal">
                <div class="modal-box">
                    <input type="hidden" id="deleteStudentId">

                    <h3>Delete Student</h3>
                    <p id="deleteMessage"></p>

                    <div class="modal-actions">
                        <button class="cancel-delete" onclick="closeDeleteModal()">Cancel</button>
                        <button class="confirm-delete" onclick="confirmDelete()">Delete</button>
                    </div>
                </div>
            </div>

            <!-- BULK EDIT MODAL -->
            <div class="bulk-edit-modal-overlay" id="bulkEditModal">
                <div class="bulk-edit-box">
                    <div class="modal-header">
                        <h2>Edit Selected Students</h2>
                        <button class="modal-close" onclick="closeBulkEditModal()">&times;</button>
                    </div>
                    <p class="sub" id="bulkEditSubtitle"></p>

                    <div class="input-container">
                        <label for="bulkDeptInput">New Department <span style="color:#9ca3af;font-weight:400;">(leave blank to keep current)</span></label>
                        <input type="text" id="bulkDeptInput" placeholder="e.g. IT">
                    </div>

                    <div class="input-container">
                        <label for="bulkSectionInput">New Section <span style="color:#9ca3af;font-weight:400;">(leave blank to keep current)</span></label>
                        <input type="text" id="bulkSectionInput" placeholder="e.g. 301">
                    </div>

                    <div class="form-btn" style="margin-top:20px;">
                        <button class="save" onclick="submitBulkEdit()">
                            <i data-lucide="save" class="icon"></i> Apply Changes
                        </button>
                        <button class="cancel" onclick="closeBulkEditModal()">
                            <i data-lucide="x" class="icon"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- BULK DELETE CONFIRM MODAL -->
            <div class="bulk-edit-modal-overlay" id="bulkDeleteModal">
                <div class="bulk-confirm-box">
                    <h3>Delete Selected Students</h3>
                    <p id="bulkDeleteMessage"></p>
                    <div class="bulk-confirm-actions">
                        <button class="cancel-delete" onclick="closeBulkDeleteModal()">Cancel</button>
                        <button class="confirm-delete" onclick="confirmBulkDelete()">Confirm Delete</button>
                    </div>
                </div>
            </div>

            <!-- BULK DELETE COUNTDOWN MODAL - KATULAD NG SA DASHBOARD PARA SA RESTART ELECTION -->
            <div class="countdown-overlay" id="bulkDeleteCountdownModal">
                <div class="countdown-box">
                    <div class="countdown-icon">
                        <i data-lucide="trash-2"></i>
                    </div>
                    <h2>Delete Selected Students</h2>
                    <p class="cd-subtitle">Deleting will proceed automatically. You may cancel before the timer ends.</p>

                    <!-- RING TIMER - SVG CIRCULAR PROGRESS COUNTDOWN -->
                    <div class="ring-wrap">
                        <svg viewBox="0 0 110 110" xmlns="http://www.w3.org/2000/svg">
                            <circle class="ring-bg" cx="55" cy="55" r="46" />
                            <circle class="ring-fill" id="bdRing" cx="55" cy="55" r="46" />
                        </svg>
                        <div class="ring-number" id="bdNumber">10</div>
                    </div>

                    <div class="countdown-btns">
                        <button class="cd-cancel" id="bdCancelBtn">
                            <i data-lucide="x" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;"></i>
                            Cancel
                        </button>
                        <button class="cd-delete-now" id="bdProceedBtn">Delete Now</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        lucide.createIcons();

        // Add Student inline error helpers  
        function showAddStudentError(msg) {
            const el = document.getElementById('addStudentError');
            if (!el) return;
            el.textContent = msg;
            el.style.display = 'block';
            // Scroll the error into view inside the modal
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideAddStudentError() {
            const el = document.getElementById('addStudentError');
            if (!el) return;
            el.textContent = '';
            el.style.display = 'none';
        }

        // Password toggle for ADD STUDENT modal
        const addPasswordInput = document.getElementById('add-password');
        const addToggleButton = document.getElementById('toggle-add-password');
        const addEyeIcon = document.getElementById('add-eye-icon');

        if (addPasswordInput && addToggleButton && addEyeIcon) {
            addToggleButton.addEventListener('click', () => {
                const isPassword = addPasswordInput.type === 'password';

                addPasswordInput.type = isPassword ? 'text' : 'password';

                addEyeIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');

                lucide.createIcons();
            });
        }

        // SUCCESS MESSAGES 
        <?php if (isset($_GET['success'])): ?>
            <?php
            $messages = [
                'student_added'   => 'Student account added successfully!',
                'student_deleted' => 'Student account deleted successfully!',
                'student_updated' => 'Student updated successfully!'
            ];
            if (isset($messages[$_GET['success']])):
            ?>
                showMessageModal("Success", "<?= $messages[$_GET['success']] ?>", true);
            <?php endif; ?>
        <?php endif; ?>

        // ERROR MESSAGES
        <?php if (isset($_GET['error'])): ?>
            const errorMessage = "<?= htmlspecialchars($_GET['error']); ?>";
            const errorMessages = {
                'email_exists': 'Email already exists.',
                'empty_fields': 'All fields are required.',
                'student_exists': 'Student ID already exists.',
                'db_error': 'Database error occurred.',
                'invalid_student_id': 'Invalid Student ID format. Use XX-XXXX (e.g. 23-1234).',
                'invalid_department': 'Invalid department. Only letters, numbers, spaces, and hyphens are allowed.',
                'invalid_section': 'Invalid section. Only letters, numbers, spaces, and hyphens are allowed.',
                'empty_password': 'Password cannot be empty.',
                'invalid_characters': 'Input contains invalid characters. Avoid # @ % . , < > \' ; \\ / * & ! ? = +',
                'insert_failed': 'Failed to save student. Please try again.',
            };
            const msg = errorMessages[errorMessage] || 'An unexpected error occurred.';
            showMessageModal("Error", msg, false);
        <?php endif; ?>

        function showMessageModal(title, message, isSuccess = true) {
            const modal = document.getElementById('messageModal');
            const titleEl = document.getElementById('messageTitle');
            const textEl = document.getElementById('messageText');

            titleEl.innerText = title;
            textEl.innerText = message;
            titleEl.style.color = isSuccess ? "#10b981" : "#ef4444";

            modal.classList.add('active');
            setTimeout(closeMessageModal, 2500);
        }

        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            modal.classList.remove('active');

            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, "manage_students.php");
            }
        }

        //  REAL-TIME AJAX SEARCH 
        let searchTimeout = null;
        let currentPage = 1;
        let currentSearch = '';
        let currentDept = '';
        let currentSection = '';

        // MGA ID NG MGA NAPILING ESTUDYANTE
        let selectedIds = new Set();

        const searchInput = document.getElementById('search');

        // AUTOMATIKONG NAGSE-SEARCH HABANG NAGTA-TYPE ANG ADMIN
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            currentPage = 1;
            currentSearch = this.value.trim();
            selectedIds.clear();

            // DEBOUCE - HINTAYIN MUNA BAGO MAG-FETCH (300ms)
            searchTimeout = setTimeout(() => {
                fetchStudents(currentSearch, currentPage);
            }, 300);
        });

        // GLOBAL NA STORAGE NG DEPT→SECTIONS MAPPING
        let deptSectionsMap = {};

        // PAGKUHA NG MGA NATATANGING DEPARTMENT AT SECTION PARA SA MGA FILTER DROPDOWN
        async function populateFilters() {
            try {
                const res = await fetch('../actions/get_students.php?meta=1');
                const data = await res.json();

                const deptSel = document.getElementById('filterDept');
                const sectionSel = document.getElementById('filterSection');

                // I-SAVE ANG KASALUKUYANG HALAGA BAGO I-REFRESH ANG MGA OPSYON
                const prevDept = deptSel.value;
                const prevSection = sectionSel.value;

                // I-STORE ANG DEPT→SECTIONS MAP KUNG AVAILABLE SA RESPONSE
                // KUNG HINDI, GAGAWIN NATING FLAT (LAHAT NG SECTIONS SA LAHAT NG DEPT)
                deptSectionsMap = data.dept_sections || {};

                // PALITAN ANG MGA OPSYON SA DEPARTAMENTO
                deptSel.innerHTML = '<option value="">All Departments</option>';
                (data.departments || []).forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d;
                    deptSel.appendChild(opt);
                });

                // I-RESTORE ANG DATI NILANG HALAGA
                deptSel.value = prevDept;

                // I-UPDATE ANG MGA SECTION BATAY SA NA-RESTORE NA DEPT
                updateSectionDropdown(prevDept, prevSection);

            } catch (e) {
                console.error('populateFilters error', e);
            }
        }

        // I-UPDATE ANG SECTION DROPDOWN BATAY SA NAPILING DEPARTMENT
        function updateSectionDropdown(selectedDept, restoreSection = '') {
            const sectionSel = document.getElementById('filterSection');
            sectionSel.innerHTML = '<option value="">All Sections</option>';

            let sectionsToShow = [];

            if (!selectedDept) {
                // WALANG NAPILING DEPT — IPAKITA LAHAT NG SECTIONS
                const allSections = new Set();
                Object.values(deptSectionsMap).forEach(sections => {
                    sections.forEach(s => allSections.add(s));
                });
                sectionsToShow = Array.from(allSections).sort();
            } else if (deptSectionsMap[selectedDept]) {
                // MAY NAPILING DEPT — IPAKITA LANG ANG SECTIONS NITO
                sectionsToShow = [...deptSectionsMap[selectedDept]].sort();
            }

            sectionsToShow.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                sectionSel.appendChild(opt);
            });

            // I-RESTORE ANG DATI NILANG HALAGA KUNG AVAILABLE PA RIN
            if (restoreSection && sectionsToShow.includes(restoreSection)) {
                sectionSel.value = restoreSection;
            } else {
                sectionSel.value = '';
                currentSection = '';
            }
        }

        // PAG-FILTER AYON SA DEPARTMENT
        document.getElementById('filterDept').addEventListener('change', function() {
            currentDept = this.value;
            currentSection = '';
            currentPage = 1;
            selectedIds.clear();

            // I-UPDATE ANG SECTIONS PARA SA NAPILING DEPARTMENT
            updateSectionDropdown(currentDept);

            fetchStudents(currentSearch, currentPage);
        });

        // PAG-FILTER AYON SA SECTION
        document.getElementById('filterSection').addEventListener('change', function() {
            currentSection = this.value;
            currentPage = 1;
            selectedIds.clear();
            fetchStudents(currentSearch, currentPage);
        });

        // PAG-FILTER AYON SA DEPARTAMENTO
        document.getElementById('filterDept').addEventListener('change', function() {
            currentDept = this.value;
            currentPage = 1;
            selectedIds.clear();
            fetchStudents(currentSearch, currentPage);
        });

        // PAG-FILTER AYON SA SEKSIYON
        document.getElementById('filterSection').addEventListener('change', function() {
            currentSection = this.value;
            currentPage = 1;
            selectedIds.clear();
            fetchStudents(currentSearch, currentPage);
        });

        // PAGKUHA NG MGA ESTUDYANTE MULA SA SERVER VIA AJAX
        function fetchStudents(search, page) {
            const params = new URLSearchParams({
                search,
                page,
                dept: currentDept,
                section: currentSection
            });

            fetch('../actions/get_students.php?' + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (data.error) return;
                    renderStudentTable(data.students);
                    renderPagination(data.total_pages, data.page, search);
                    syncSelectAll();
                    updateBulkButtons();
                })
                .catch(err => console.error('fetchStudents error:', err));
        }

        // PAG-RENDER NG MGA RESULTA SA TALAAN
        function renderStudentTable(students) {
            const tbody = document.getElementById('studentTableBody');
            const section = document.getElementById('studentsSection');
            const noMsg = document.getElementById('noStudentsMsg');

            if (!students || students.length === 0) {
                tbody.innerHTML = '';
                section.style.display = 'none';
                noMsg.style.display = 'block';
                return;
            }

            section.style.display = '';
            noMsg.style.display = 'none';

            // GUMAWA NG TABLE ROWS PARA SA BAWAT ESTUDYANTE
            tbody.innerHTML = students.map(s => {
                const checked = selectedIds.has(String(s.id)) ? 'checked' : '';
                return `
                <tr id="row-${s.id}">
                    <td class="cb-cell">
                        <input type="checkbox" class="row-cb" data-id="${s.id}"
                            data-dept="${escapeHtml(s.department)}"
                            data-section="${escapeHtml(s.section || '')}"
                            ${checked}
                            onchange="onRowCheck(this)">
                    </td>
                    <td>${escapeHtml(s.student_id)}</td>
                    <td>${escapeHtml(s.department)}</td>
                    <td>${escapeHtml(s.section || 'Not Assigned')}</td>
                    <td>
                        <button class="edit-btn" onclick="openEditStudentModal(${s.id})" title="Edit student">
                            <i data-lucide="edit" class="icon"></i>
                        </button>
                        <button class="delete-btn"
                            onclick="openDeleteModal(${s.id}, '${escapeHtml(s.student_id)}'); return false;"
                            title="Delete student account">
                            <i data-lucide="trash-2" class="icon"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');

            // I-REINITIALIZE ANG LUCIDE ICONS SA BAGONG ROWS
            lucide.createIcons();
        }

        // PAG-RENDER NG PAGINATION BUTTONS
        function renderPagination(totalPages, activePage, search) {
            const container = document.getElementById('paginationContainer');
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';

            if (activePage > 1) {
                html += `<button class="page-btn" onclick="goToPage(${activePage - 1})">Previous</button>`;
            }

            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="page-btn ${i === activePage ? 'active' : ''}" 
                    onclick="goToPage(${i})">${i}</button>`;
            }

            if (activePage < totalPages) {
                html += `<button class="page-btn" onclick="goToPage(${activePage + 1})">Next</button>`;
            }

            container.innerHTML = html;
        }

        // PAG-NAVIGATE SA IBANG PAGE
        function goToPage(page) {
            currentPage = page;
            fetchStudents(currentSearch, currentPage);
        }

        // HELPER - IWASAN ANG XSS SA DYNAMICALLY RENDERED HTML
        function escapeHtml(str) {
            if (str == null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // PAG-SYNC NG SELECT ALL CHECKBOX BATAY SA MGA NAPILING ROW
        function syncSelectAll() {
            const cbs = document.querySelectorAll('.row-cb');
            const checked = document.querySelectorAll('.row-cb:checked');
            const allCb = document.getElementById('selectAllCb');

            if (!cbs.length) {
                allCb.checked = false;
                allCb.indeterminate = false;
                return;
            }

            if (checked.length === 0) {
                allCb.checked = false;
                allCb.indeterminate = false;
            } else if (checked.length === cbs.length) {
                allCb.checked = true;
                allCb.indeterminate = false;
            } else {
                allCb.checked = false;
                allCb.indeterminate = true;
            }
        }

        // PAG-UPDATE NG BULK BUTTONS BATAY SA BILANG NG MGA NAPILI
        function updateBulkButtons() {
            const count = selectedIds.size;
            document.getElementById('bulkEditBtn').disabled = count === 0;
            document.getElementById('bulkDeleteBtn').disabled = count === 0;
            document.getElementById('selectionCount').textContent =
                count > 0 ? `${count} student${count > 1 ? 's' : ''} selected` : '';
        }

        // PAG-CHECK NG ISANG ROW - IDADAGDAG O AALISIN SA NAPILING MGA ID
        function onRowCheck(cb) {
            if (cb.checked) selectedIds.add(cb.dataset.id);
            else selectedIds.delete(cb.dataset.id);
            syncSelectAll();
            updateBulkButtons();
        }

        // SELECT ALL CHECKBOX - PIPILIIN O AALISAN NG LAHAT NG VISIBLE NA ROWS
        document.getElementById('selectAllCb').addEventListener('change', function() {
            const cbs = document.querySelectorAll('.row-cb');
            cbs.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) selectedIds.add(cb.dataset.id);
                else selectedIds.delete(cb.dataset.id);
            });
            updateBulkButtons();
        });

        // PAG LOAD NG MGA Students for first opening 
        populateFilters();
        fetchStudents('', 1);

        // ADD STUDENT MODAL 
        function openAddStudentModal() {
            const modal = document.getElementById('addStudentModal');
            if (!modal) return;
            hideAddStudentError();
            modal.classList.add('active');
            lucide.createIcons();
        }

        function closeAddStudentModal() {
            const modal = document.getElementById('addStudentModal');
            if (!modal) return;
            hideAddStudentError();
            modal.classList.remove('active');
            modal.querySelector('form').reset();
        }

        // EDIT STUDENT MODAL 
        function openEditStudentModal(studentId) {
            fetch('../actions/get_students.php?id=' + studentId)
                .then(res => res.json())
                .then(data => {
                    if (!data.error) {
                        const modal = document.getElementById('editStudentModal');
                        document.getElementById('editStudentId').value = data.id;
                        document.getElementById('editStudentIdField').value = data.student_id || '';
                        document.getElementById('editDepartment').value = data.department || '';
                        document.getElementById('editEmail').value = data.email || '';
                        document.getElementById('editSection').value = data.section || '';

                        modal.classList.add('active');
                        lucide.createIcons();
                    } else {
                        showMessageModal('Something went wrong', 'Error loading student data: ' + data.error, false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    showMessageModal('Something went wrong', 'Error loading student data', false);
                });
        }

        function closeEditStudentModal() {
            const modal = document.getElementById('editStudentModal');
            modal.classList.remove('active');
            document.getElementById('editStudentForm').reset();
        }

        document.getElementById('editStudentModal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeEditStudentModal();
        });


        document.getElementById('studentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideAddStudentError();

            const form = this;
            const formData = new FormData(form);
            formData.append('add_student', '1'); // ← IDAGDAG ITO

            fetch('../actions/add_student.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeAddStudentModal();
                        showMessageModal('Success', 'Student account added successfully!', true);
                        setTimeout(() => {
                            fetchStudents(currentSearch, currentPage);
                            populateFilters();
                        }, 300);
                    } else {
                        // Error stays INSIDE the open modal
                        showAddStudentError(data.message || 'Something went wrong.');
                    }
                })
                .catch(() => {
                    showAddStudentError('Connection error. Please try again.');
                });
        });

        // Edit Student error helpers (same pattern as Add Student)
        function showEditStudentError(msg) {
            const el = document.getElementById('editStudentError');
            if (!el) return;
            el.textContent = msg;
            el.style.display = 'block';
            el.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function hideEditStudentError() {
            const el = document.getElementById('editStudentError');
            if (!el) return;
            el.textContent = '';
            el.style.display = 'none';
        }

        // Password toggle for EDIT STUDENT modal
        const editPasswordInput = document.getElementById('edit-password');
        const editToggleButton = document.getElementById('toggle-edit-password');
        const editEyeIcon = document.getElementById('edit-eye-icon');

        if (editPasswordInput && editToggleButton && editEyeIcon) {
            editToggleButton.addEventListener('click', () => {
                const isPassword = editPasswordInput.type === 'password';
                editPasswordInput.type = isPassword ? 'text' : 'password';
                editEyeIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                lucide.createIcons();
            });
        }

        // Open / close (add hideEditStudentError to close)
        function closeEditStudentModal() {
            const modal = document.getElementById('editStudentModal');
            modal.classList.remove('active');
            hideEditStudentError();
            document.getElementById('editStudentForm').reset();
        }

        // Submit — errors stay INSIDE the open modal
        document.getElementById('editStudentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideEditStudentError();

            const formData = new FormData(this);

            fetch('../actions/edit_student.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeEditStudentModal();
                        showMessageModal('Success', 'Student updated successfully!', true);
                        setTimeout(() => {
                            fetchStudents(currentSearch, currentPage);
                            populateFilters();
                        }, 300);
                    } else {
                        showEditStudentError(data.message || 'Something went wrong.');
                    }
                })
                .catch(() => {
                    showEditStudentError('Connection error. Please try again.');
                });
        });
        
        // DELETE STUDENT MODAL 
        function openDeleteModal(studentId, studentName) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('deleteStudentId').value = studentId;
            document.getElementById('deleteMessage').innerHTML =
                `Are you sure you want to delete student ID: <span style="font-weight:bold;">${studentName}</span>?`;
            modal.classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.getElementById('deleteStudentId').value = '';
            document.getElementById('deleteMessage').innerText = '';
        }

        function confirmDelete() {
            const studentId = document.getElementById('deleteStudentId').value;
            if (!studentId) return;

            // Save scroll position before the action
            const scrollY = window.scrollY;

            closeDeleteModal();

            fetch('../actions/delete_student.php?id=' + studentId)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessageModal('Deleted', 'Student deleted successfully!', true);
                        setTimeout(() => {
                            fetchStudents(currentSearch, currentPage);
                            // Restore scroll position after table re-renders
                            setTimeout(() => window.scrollTo({
                                top: scrollY,
                                behavior: 'smooth'
                            }), 150);
                        }, 300);
                    } else {
                        showMessageModal('Error', data.message || 'Failed to delete student.', false);
                    }
                })
                .catch(() => showMessageModal('Error', 'Something went wrong.', false));
        }


        // BULK EDIT MODAL - PARA SA PAG-EDIT NG MARAMIHANG ESTUDYANTE
        function openBulkEditModal() {
            if (selectedIds.size === 0) {
                showMessageModal('No Students Selected', 'Please select students before editing.', false);
                return;
            }

            document.getElementById('bulkEditSubtitle').textContent =
                `You are about to edit ${selectedIds.size} students. Only the Department and Section can be changed.`;
            document.getElementById('bulkDeptInput').value = '';
            document.getElementById('bulkSectionInput').value = '';
            document.getElementById('bulkEditModal').classList.add('active');
            lucide.createIcons();
        }

        function closeBulkEditModal() {
            document.getElementById('bulkEditModal').classList.remove('active');
        }

        // BULK DELETE MODAL - PARA SA PAGBUBURA NG MARAMIHANG ESTUDYANTE
        function openBulkDeleteModal() {
            if (selectedIds.size === 0) {
                showMessageModal('No Students Selected', 'Please select students before deleting.', false);
                return;
            }

            document.getElementById('bulkDeleteMessage').textContent =
                `You are about to permanently delete ${selectedIds.size} student account${selectedIds.size > 1 ? 's' : ''}. This action cannot be undone.`;
            document.getElementById('bulkDeleteModal').classList.add('active');
        }

        function closeBulkDeleteModal() {
            document.getElementById('bulkDeleteModal').classList.remove('active');
        }
        // PAG-SUBMIT NG BULK EDIT - IPAPADALA SA SERVER VIA AJAX
        function submitBulkEdit() {
            const newDept = document.getElementById('bulkDeptInput').value.trim();
            const newSection = document.getElementById('bulkSectionInput').value.trim();

            // KAILANGAN NG HINDI BABABA SA ISANG FIELD NA PUPUNUIN
            if (!newDept && !newSection) {
                showMessageModal('No Changes', 'Please enter a new Department, Section, or both.', false);
                return;
            }

            const ids = Array.from(selectedIds);
            fetch('../actions/bulk_edit_students.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids,
                        department: newDept,
                        section: newSection
                    })
                })
                .then(res => res.json())
                .then(data => {
                    closeBulkEditModal();
                    if (data.success) {
                        showMessageModal('Success', data.message || 'Students updated successfully!', true);
                        selectedIds.clear();
                        // I-REFRESH ANG FILTERS AT TABLE PAGKATAPOS NG UPDATE
                        setTimeout(() => {
                            populateFilters();
                            fetchStudents(currentSearch, currentPage);
                        }, 300);
                    } else {
                        showMessageModal('Error', data.message || 'Failed to update students.', false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    closeBulkEditModal();
                    showMessageModal('Something went wrong', 'Something went wrong. Please try again.', false);
                });
        }

        // PAGKUMPIRMA NG BULK DELETE - ISASARA ANG UNANG MODAL AT BUBUKSAN ANG COUNTDOWN
        function confirmBulkDelete() {
            closeBulkDeleteModal();
            openBulkDeleteCountdown();
        }

        // COUNTDOWN VARIABLES PARA SA BULK DELETE
        const BD_COUNTDOWN_SECONDS = 10;
        const bdCircumference = 2 * Math.PI * 46;
        let bdTimer = null;
        let bdRemaining = BD_COUNTDOWN_SECONDS;

        const bdCountdownModal = document.getElementById('bulkDeleteCountdownModal');
        const bdRing = document.getElementById('bdRing');
        const bdNumber = document.getElementById('bdNumber');
        const bdCancel = document.getElementById('bdCancelBtn');
        const bdProceed = document.getElementById('bdProceedBtn');

        // BUKSAN ANG COUNTDOWN MODAL AT SIMULAN ANG TIMER
        function openBulkDeleteCountdown() {
            clearInterval(bdTimer);
            bdRemaining = BD_COUNTDOWN_SECONDS;

            // NUMBER RESET
            bdNumber.textContent = bdRemaining;
            bdNumber.classList.remove('urgent');

            // STEP 1: I-SET ANG STROKE DIRECTLY SA SVG ELEMENT — BYPASS ANG CSS
            bdRing.setAttribute('stroke', '#ef4444');
            bdRing.setAttribute('stroke-width', '8');
            bdRing.setAttribute('stroke-linecap', 'round');
            bdRing.setAttribute('fill', 'none');

            // STEP 2: I-RESET SA FULL CIRCLE — WALANG TRANSITION
            bdRing.style.transition = 'none';
            bdRing.style.strokeDasharray = bdCircumference;
            bdRing.style.strokeDashoffset = 0;

            // STEP 3: IPAKITA ANG MODAL
            bdCountdownModal.classList.add('active');
            lucide.createIcons();

            // STEP 4: FORCE REFLOW — TINITIYAK NA NA-PAINT ANG FULL CIRCLE
            void bdRing.offsetWidth;

            // STEP 5: I-ENABLE ANG TRANSITION — HANDA NA, MAGSISIMULA SA FULL
            bdRing.style.transition = 'stroke-dashoffset 1s linear';

            // STEP 6: INTERVAL — UNANG TICK PAGKATAPOS NG EKSAKTONG 1 SEGUNDO
            bdTimer = setInterval(() => {
                bdRemaining--;
                bdNumber.textContent = bdRemaining;
                bdRing.style.strokeDashoffset = bdCircumference * (1 - bdRemaining / BD_COUNTDOWN_SECONDS);

                if (bdRemaining <= 2) bdNumber.classList.add('urgent');

                if (bdRemaining <= 0) {
                    clearInterval(bdTimer);
                    submitBulkDelete();
                }
            }, 1000);
        }

        function closeBulkDeleteCountdownModal() {
            // I-CANCEL ANG TIMER AT ISARA ANG COUNTDOWN MODAL
            clearInterval(bdTimer);
            bdCountdownModal.classList.remove('active');
            bdNumber.classList.remove('urgent');
        }

        // PAG-SUBMIT NG BULK DELETE - IPAPADALA SA SERVER VIA AJAX
        function submitBulkDelete() {
            clearInterval(bdTimer);
            bdCountdownModal.classList.remove('active');

            const ids = Array.from(selectedIds);
            fetch('../actions/bulk_delete_students.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessageModal('Deleted', data.message || 'Students deleted successfully!', true);
                        selectedIds.clear();
                        // I-REFRESH ANG FILTERS AT TABLE PAGKATAPOS NG PAGBUBURA
                        setTimeout(() => {
                            populateFilters();
                            fetchStudents(currentSearch, currentPage);
                        }, 300);
                    } else {
                        showMessageModal('Error', data.message || 'Failed to delete students.', false);
                    }
                })
                .catch(err => {
                    console.error(err);
                    showMessageModal('Something went wrong', 'Something went wrong. Please try again.', false);
                });
        }

        // BUTTON EVENTS PARA SA COUNTDOWN MODAL
        bdCancel.addEventListener('click', closeBulkDeleteCountdownModal);
        bdProceed.addEventListener('click', () => {
            clearInterval(bdTimer);
            submitBulkDelete();
        });
        bdCountdownModal.addEventListener('click', e => {
            if (e.target === bdCountdownModal) closeBulkDeleteCountdownModal();
        });

        // GLOBAL KEY EVENTS 
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeAddStudentModal();
                closeEditStudentModal();
                closeDeleteModal();
                closeBulkEditModal();
                closeBulkDeleteModal();
                closeBulkDeleteCountdownModal(); // KINAKANSELA RIN ANG COUNTDOWN TIMER
            }
        });


        // CSV FILE UPLOAD FUNCTIONALITY
        const csvInput = document.getElementById('csv_file');

        // Show selected filename and auto-upload
        csvInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadCSV(this.files[0]);
            }
        });

        function uploadCSV(file) {
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('ajax', '1');

            // UI ELEMENTS  
            const uploadingModal = document.getElementById('uploadingModal');
            const bar = document.getElementById('progressBar');
            const text = document.getElementById('progressText');

            // Idagdag ang dalawang element na ito sa iyong uploadingModal HTML (tingnan sa ibaba)
            const stageEl = document.getElementById('uploadStageLabel');
            const spinner = document.getElementById('uploadSpinner');

            // RESET STATE
            bar.style.width = '0%';
            bar.className = 'progress-bar';
            text.textContent = '0%';
            if (stageEl) stageEl.textContent = 'Sending file to server...';
            uploadingModal.style.display = 'flex';

            // SIMULATED PROGRESS - gumagalaw agad kahit maliit ang file
            const stages = [{
                    pct: 15,
                    label: 'Sending file to server...',
                    delay: 150
                },
                {
                    pct: 40,
                    label: 'File received. Processing rows...',
                    delay: 500
                },
                {
                    pct: 65,
                    label: 'Validating student records...',
                    delay: 900
                },
                {
                    pct: 82,
                    label: 'Inserting into database...',
                    delay: 1400
                },
                {
                    pct: 92,
                    label: 'Finalizing...',
                    delay: 1900
                },
            ];
            const timers = [];
            stages.forEach(s => {
                const t = setTimeout(() => {
                    bar.style.width = s.pct + '%';
                    text.textContent = s.pct + '%';
                    if (stageEl) stageEl.textContent = s.label;
                }, s.delay);
                timers.push(t);
            });

            function clearTimers() {
                timers.forEach(clearTimeout);
            }

            // REAL XHR UPLOAD PROGRESS (0–70% range, blends with simulated)
            const xhr = new XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    // Real upload progress mapped to 0-70 so server-side has room
                    const realPct = Math.round((e.loaded / e.total) * 70);
                    if (realPct > parseInt(bar.style.width || '0')) {
                        bar.style.width = realPct + '%';
                        text.textContent = realPct + '%';
                    }
                }
            });

            xhr.addEventListener('load', function() {
                clearTimers();
                bar.style.width = '100%';
                text.textContent = '100%';
                if (stageEl) stageEl.textContent = 'Done.';
                setTimeout(() => {
                    uploadingModal.style.display = 'none';
                    let response;
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        showResultModal('error', 'Upload Failed', 'The server returned an unexpected response. Please try again.');
                        csvInput.value = '';
                        return;
                    }
                    if (response.success) {
                        closeAddStudentModal();
                        const skippedNote = response.skipped > 0 ?
                            ` ${response.skipped} row(s) were skipped due to invalid format, duplicate IDs, or missing fields.` :
                            '';
                        showResultModal(
                            response.skipped > 0 ? 'warn' : 'success',
                            response.skipped > 0 ? 'Upload Complete with Warnings' : 'Upload Successful',
                            `${response.inserted} student${response.inserted !== 1 ? 's' : ''} added successfully.` + skippedNote
                        );
                    } else {
                        showResultModal('error', 'Upload Failed', response.message || 'Something went wrong. Please try again.');
                    }
                    csvInput.value = '';
                    populateFilters();
                    fetchStudents(currentSearch, currentPage);
                }, 400);
            });

            xhr.addEventListener('error', function() {
                clearTimers();
                uploadingModal.style.display = 'none';
                showResultModal('error', 'Network error',
                    'Could not reach the server. Check your connection and try again.');
                csvInput.value = '';
            });

            xhr.open('POST', '../actions/add_student.php');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        }

        // Result modal functions
        function showResultModal(type, title, message) {
            const modal = document.getElementById('resultModal');
            document.getElementById('resultTitle').textContent = title;
            document.getElementById('resultMessage').textContent = message;
            const icon = document.getElementById('resultIcon');

            if (type === 'success') {
                icon.className = 'result-icon-success';
                icon.textContent = '✔';
            } else if (type === 'warn') {
                icon.className = '';
                icon.textContent = '⚠';
                icon.style.color = '#f59e0b';
                icon.style.fontSize = '2rem';
            } else {
                icon.className = 'result-icon-error';
                icon.textContent = '✖';
            }

            modal.style.display = 'flex';
        }

        function closeResultModal() {
            document.getElementById('resultModal').style.display = 'none';
        }
    </script>

</body>

</html>