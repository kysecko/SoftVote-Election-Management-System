<?php
session_start();
require_once '../../config_db.php';

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

// PAGREGENERATE NG SESSION ID EVERY 5 MINUTES TO PREVENT SESSION FIXATION
require_once '../includes/admin_auth_guard.php';
    

$filter_type  = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filter_dept  = isset($_GET['department']) ? trim($_GET['department']) : '';
$filter_sect  = isset($_GET['section'])    ? trim($_GET['section'])    : '';

// LAHAT NG DEPARTMENTS PARA SA FILTER DROPDOWN 
$departments_query = $conn->query(
    "SELECT DISTINCT department FROM students
     WHERE department IS NOT NULL AND department != ''
     ORDER BY department"
);
$departments_array = [];
if ($departments_query) {
    while ($d = $departments_query->fetch_assoc()) {
        $departments_array[] = $d['department'];
    }
}

// LAHAT NG SECTIONS PARA SA FILTER DROPDOWN (OPTIONAL DEPENDE SA DEPARTMENT FILTER)
$sections_array = [];
if (!empty($filter_dept)) {
    $sect_stmt = $conn->prepare(
        "SELECT DISTINCT section FROM students
         WHERE department = ? AND section IS NOT NULL AND section != ''
         ORDER BY section"
    );
    $sect_stmt->bind_param("s", $filter_dept);
    $sect_stmt->execute();
    $sect_result = $sect_stmt->get_result();
    while ($s = $sect_result->fetch_assoc()) {
        $sections_array[] = $s['section'];
    }
    $sect_stmt->close();
} else {
    $all_sects = $conn->query(
        "SELECT DISTINCT section FROM students
         WHERE section IS NOT NULL AND section != ''
         ORDER BY section"
    );
    if ($all_sects) {
        while ($s = $all_sects->fetch_assoc()) {
            $sections_array[] = $s['section'];
        }
    }
}

// PAGKUHA NG STUDENTS BASED SA FILTERS
$query  = "SELECT id, student_id, department, section, has_voted, created_at FROM students";
$params = [];
$types  = "";
$where  = [];

if ($filter_type === 'department' && !empty($filter_dept)) {
    $where[]  = "department = ?";
    $params[] = $filter_dept;
    $types   .= "s";

    // OPTIONAL LANG 
    if (!empty($filter_sect)) {
        $where[]  = "section = ?";
        $params[] = $filter_sect;
        $types   .= "s";
    }
} elseif ($filter_type === 'section' && !empty($filter_sect)) {
    $where[]  = "section = ?";
    $params[] = $filter_sect;
    $types   .= "s";
}

if (!empty($where)) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY department, section, student_id";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

$total_students  = $students->num_rows;
$voted_count     = 0;
$not_voted_count = 0;
$students_array  = [];

while ($row = $students->fetch_assoc()) {
    $students_array[] = $row;
    if ($row['has_voted'] == 1) $voted_count++;
    else                        $not_voted_count++;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Directory - SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/admin/student_directory.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <style>
        /* Filter step rows */
        .filter-step {
            display: none;
        }

        .filter-step.visible {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Modal */
        .modal-filter-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .4);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-filter-overlay.show {
            display: flex;
        }

        .modal-filter-overlay .modal-content {
            background: #fff;
            padding: 24px 28px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
            text-align: center;
            max-width: 320px;
            width: 90%;
        }

        .modal-filter-overlay .modal-content p {
            font-size: 15px;
            color: red;
            margin-bottom: 16px;
        }

        .modal-filter-overlay .modal-content button {
            padding: 8px 24px;
            background: #0b5ed7;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }

        .modal-filter-overlay .modal-content button:hover {
            background: #0a58ca;
        }
    </style>
</head>

<body>
    <?php include '../../logout_modal.php'; ?>

    <!-- HEADER -->
    <div class="header-main">
        <div class="header-container">
            <div class="header">
                <div class="logo-box">
                    <div class="logo-text">
                        <h1>Students Directory</h1>
                        <p>View and filter registered students</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/sidebar_menu.php'; ?>

    <!-- MAIN CONTENTS -->
    <div class="main-wrapper">
        <div class="page-content">

            <!-- FILTER SECTION -->
            <div class="no-print filter-section">
                <div class="filter-header">
                    <h2>Filter Students</h2>
                    <button class="print-btn" onclick="window.print()">
                        <i data-lucide="printer" class="icon"></i>Print Directory
                    </button>
                </div>

                <div class="filter-controls" style="flex-wrap:wrap; gap:14px;">

                    <!-- unang process : filtering by -->
                    <div class="filter-group">
                        <label style="padding-bottom: 4px;">Filter By:</label>
                        <select id="filterType" onchange="onFilterTypeChange()">
                            <option value="all" <?= $filter_type === 'all'        ? 'selected' : '' ?>>All Students</option>
                            <option value="department" <?= $filter_type === 'department' ? 'selected' : '' ?>>Department</option>
                        </select>
                    </div>

                    <!-- pangalawang process : after choosing department mag-select specific dpt. -->
                    <div class="filter-group filter-step" id="stepDept">
                        <label style="align-self: start; margin-bottom: -4px;">Department:</label>
                        <select id="deptSelect" onchange="onDeptChange()">
                            <option value="">Choose Department </option>
                            <?php foreach ($departments_array as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"
                                    <?= $filter_dept === $dept ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- last process : after mag choose nang department -->
                    <div class="filter-group filter-step" id="stepSect">
                        <label style="align-self: start; margin-bottom: -4px;">Section:</label>
                        <select id="sectSelect">
                            <option value="">All Sections </option>
                            <?php foreach ($sections_array as $sect): ?>
                                <option value="<?= htmlspecialchars($sect) ?>"
                                    <?= $filter_sect === $sect ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sect) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- APPLY -->
                    <button class="apply-btn" onclick="applyFilter()">
                        <i data-lucide="filter" class="icon"></i>Apply Filter
                    </button>

                    <!-- CLEAR -->
                    <?php if ($filter_type !== 'all'): ?>
                        <button class="clear-btn" onclick="clearFilter()">
                            <i data-lucide="x" class="icon"></i>Clear
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- END FILTER -->

            <!-- STATS -->
            <div class="stats-section no-print">
                <div class="stat-card">
                    <div class="stat-icon blue"><i data-lucide="users" class="icon"></i></div>
                    <div class="stat-content">
                        <p class="stat-label">Total Students</p>
                        <p class="stat-value"><?= $total_students ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i data-lucide="check-circle" class="icon"></i></div>
                    <div class="stat-content">
                        <p class="stat-label">Voted</p>
                        <p class="stat-value"><?= $voted_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i data-lucide="x-circle" class="icon"></i></div>
                    <div class="stat-content">
                        <p class="stat-label">Not Voted</p>
                        <p class="stat-value"><?= $not_voted_count ?></p>
                    </div>
                </div>
            </div>

        </div>

        <!-- PRINT HEADER -->
        <div class="print-header">
            <div class="print-logo">
                <img src="../../images/white lang.jpg" alt="SOFTNET Logo">

                <div class="print-always">
                    <h2 style="margin:8px 0 4px; font-size:16px; color:#111;">
                        Student Directory
                    </h2>

                    <?php if ($filter_type !== 'all' && !empty($filter_dept)): ?>
                        <p style="font-size:13px; color:#555; margin:0;">
                            Filtered by Department:
                            <strong><?= htmlspecialchars($filter_dept) ?></strong>
                            <?php if (!empty($filter_sect)): ?>
                                &nbsp;/ Section: <strong><?= htmlspecialchars($filter_sect) ?></strong>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <p style="font-size:13px; color:#555; margin:0;">
                            Showing: <strong>All Students</strong>
                        </p>
                    <?php endif; ?>

                </div>

            </div>
        </div>

        <!-- TABLE -->
        <div class="directory-section">
            <?php if ($total_students > 0): ?>
                <div class="table-wrapper">
                    <table class="directory-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Department</th>
                                <th>Section</th>
                                <th class="no-print">Voting Status</th>
                                <th class="no-print">Registered Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1;
                            foreach ($students_array as $student): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td class="student-id"><?= htmlspecialchars($student['student_id']) ?></td>
                                    <td><?= htmlspecialchars($student['department'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($student['section'] ?? 'Not Assigned') ?></td>
                                    <td class="no-print">
                                        <?php if ($student['has_voted'] == 1): ?>
                                            <span class="status-badge voted">Voted</span>
                                        <?php else: ?>
                                            <span class="status-badge not-voted">Not Voted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print"><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="print-summary">
                    <div class="summary-grid">
                        <div class="summary-item"><strong>Total Students:</strong> <?= $total_students ?></div>
                        <div class="summary-item"><strong>Voted:</strong> <?= $voted_count ?></div>
                        <div class="summary-item"><strong>Not Voted:</strong> <?= $not_voted_count ?></div>
                        <div class="summary-item">
                            <strong>Voter Turnout:</strong>
                            <?= $total_students > 0 ? round(($voted_count / $total_students) * 100, 1) : 0 ?>%
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="no-data">
                    <i data-lucide="users-x" class="icon"></i>
                    <p>No students found</p>
                    <?php if ($filter_type !== 'all'): ?>
                        <p class="hint">Try changing your filter criteria</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ERROR MODAL -->
        <div id="filter-modal" class="modal-filter-overlay">
            <div class="modal-content">
                <p id="filterMessage"></p>
                <button onclick="closeFilterModal()">Close</button>
            </div>
        </div>

    </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        lucide.createIcons();


        const allDepartments = <?= json_encode($departments_array) ?>;
        const currentFilterType = '<?= $filter_type ?>';
        const currentDept = '<?= addslashes($filter_dept) ?>';
        const currentSect = '<?= addslashes($filter_sect) ?>';

        // on page load retrive all the selected  
        document.addEventListener('DOMContentLoaded', () => {
            if (currentFilterType === 'department') {
                showStep('stepDept');
                if (currentDept) {
                    showStep('stepSect');
                }
            }
        });

        // step visibility helpers 
        function showStep(id) {
            document.getElementById(id).classList.add('visible');
        }

        function hideStep(id) {
            document.getElementById(id).classList.remove('visible');
        }

        // unang step [ changed ]
        function onFilterTypeChange() {
            const type = document.getElementById('filterType').value;
            if (type === 'all') {
                hideStep('stepDept');
                hideStep('stepSect');
            } else {
                showStep('stepDept');
                hideStep('stepSect');
                document.getElementById('deptSelect').value = '';
                document.getElementById('sectSelect').innerHTML = '<option value="">All Sections </option>';
            }
        }

        // sunod na step [ pag fetch ng section by selecting specific department]
        function onDeptChange() {
            const dept = document.getElementById('deptSelect').value;
            const sectSelect = document.getElementById('sectSelect');

            if (!dept) {
                hideStep('stepSect');
                sectSelect.innerHTML = '<option value="">All Sections</option>';
                return;
            }

            // feching data for the specific department
            fetch(`../actions/get_sections.php?department=${encodeURIComponent(dept)}`)
                .then(r => r.json())
                .then(sections => {
                    sectSelect.innerHTML = '<option value="">All Sections </option>';
                    sections.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s;
                        opt.textContent = s;
                        sectSelect.appendChild(opt);
                    });
                    showStep('stepSect');
                })
                .catch(() => {
                    sectSelect.innerHTML = '<option value="">All Sections</option>';
                    showStep('stepSect');
                });
        }

        // filter apply 
        function applyFilter() {
            const type = document.getElementById('filterType').value;

            if (type === 'all') {
                window.location.href = 'students_list.php';
                return;
            }

            const dept = document.getElementById('deptSelect').value;
            if (!dept) {
                showFilterModal("Please select a department.");
                return;
            }

            const sect = document.getElementById('sectSelect').value;
            let url = `students_list.php?filter=department&department=${encodeURIComponent(dept)}`;
            if (sect) url += `&section=${encodeURIComponent(sect)}`;
            window.location.href = url;
        }

        // clear apply filter 
        function clearFilter() {
            window.location.href = 'students_list.php';
        }

        // modal helpers
        function showFilterModal(msg) {
            document.getElementById('filterMessage').textContent = msg;
            const modal = document.getElementById('filter-modal');
            modal.classList.add('show');
            setTimeout(() => modal.classList.remove('show'), 3000);
        }

        function closeFilterModal() {
            document.getElementById('filter-modal').classList.remove('show');
        }

        document.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) {
                const id = document.activeElement?.id;
                if (['filterType', 'deptSelect', 'sectSelect'].includes(id)) {
                    e.preventDefault();
                    applyFilter();
                }
            }
        });
    </script>
</body>

</html>