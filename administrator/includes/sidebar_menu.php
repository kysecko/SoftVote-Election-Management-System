<?php
$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
    ['href' => 'admin_dashboard.php',   'icon' => 'layout-dashboard', 'label' => 'Admin Dashboard'],
    ['href' => 'manage_candidates.php', 'icon' => 'user-plus',        'label' => 'Manage Candidates'],
    ['href' => 'manage_students.php',   'icon' => 'users',            'label' => 'Manage Students'],
    ['href' => 'students_list.php',     'icon' => 'book-user',        'label' => 'Student Directory'],
    ['href' => 'statistics.php',     'icon' => 'chart-column',        'label' => 'Statistics'],
];
?>

<style>
    .sidebar-nav {
        background: #0D2B6B;
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 270px;
        min-height: 100vh;
        padding: 20px 16px 24px;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        transition: width 0.3s ease;
    }

    body.sidebar-collapsed .sidebar-nav {
        width: 80px;
    }

    .sidebar-toggle {
        position: absolute;
        top: 250px;
        right: -14px;
        width: 28px;
        height: 28px;
        background: #1d4ed8;
        border: none;
        border-radius: 50%;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        z-index: 200;
        flex-shrink: 0;
    }

    .sidebar-toggle svg {
        width: 16px;
        height: 16px;
    }

    .sidebar-logo {
        width: 96px;
        height: 96px;
        object-fit: contain;
        border-radius: 12px;
        margin-bottom: 6px;
        transition: width 0.3s ease, height 0.3s ease;
        flex-shrink: 0;
    }

    body.sidebar-collapsed .sidebar-logo {
        width: 48px;
        height: 48px;
    }

    .sidebar-brand {
        text-align: center;
        margin-bottom: 8px;
        white-space: nowrap;
    }

    body.sidebar-collapsed .sidebar-brand {
        display: none;
    }

    .sidebar-brand p {
        color: #9ca3af;
        font-size: 11px;
        margin: 2px 0 0;
    }

    .sidebar-divider {
        border: none;
        border-top: 1px solid #374151;
        width: 100%;
        margin: 12px 0 18px;
    }

    body.sidebar-collapsed .sidebar-divider {
        display: none;
    }

    .sidebar-links {
        display: flex;
        flex-direction: column;
        gap: 6px;
        width: 100%;
        flex: 1;
    }

    body.sidebar-collapsed .sidebar-links {
        margin-top: 30px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: #d1d5db;
        font-size: 14.5px;
        font-weight: 500;
        padding: 11px 14px;
        border-radius: 8px;
        transition: background 0.18s, color 0.18s;
        position: relative;
        white-space: nowrap;
    }

    .sidebar-link:hover {
        background: #2949a0;
        color: #ffffff;
    }

    .sidebar-link.active {
        background: #1d4ed8;
        color: #ffffff;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(29, 78, 216, 0.45);
    }

    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 8px;
        bottom: 8px;
        background: #1A6BF5;
        border-radius: 0 3px 3px 0;
    }

    .sidebar-link svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    body.sidebar-collapsed .sidebar-link span,
    body.sidebar-collapsed .sidebar-logout span,
    body.sidebar-collapsed hr,
    body.sidebar-collapsed .footer {
        display: none;
    }

    body.sidebar-collapsed .sidebar-link {
        justify-content: center;
        padding: 11px;
    }

    body.sidebar-collapsed .sidebar-logout {
        justify-content: center;
        padding: 11px;
    }

    .sidebar-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        width: 100%;
        padding: 11px 14px;
        border-radius: 8px;
        border: none;
        background: #d32929;
        color: #fff;
        font-size: 14.5px;
        font-weight: 500;
        cursor: pointer;
        font-family: inherit;
        transition: background 0.18s, color 0.18s;
        margin-top: 8px;
        white-space: nowrap;
    }

    .sidebar-logout:hover {
        background: #e74747;
    }

    .sidebar-logout svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .footer {
        padding: 0 30px;
        text-align: center;
        font-size: 12px;
        color: #ffffff;
    }

    /* Loading animation when tapping other pages */
    .page-loader {
        position: fixed;
        inset: 0;
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(2px);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 12px;
    }

    .page-loader.active {
        display: flex;
    }

    .page-loader-spinner {
        width: 44px;
        height: 44px;
        border: 4px solid #e5e7eb;
        border-top-color: #2563eb;
        border-radius: 50%;
        animation: spin 0.75s linear infinite;
    }

    .page-loader-text {
        font-size: 0.9rem;
        color: #374151;
        font-weight: 500;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>


<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="page-loader-spinner"></div>
    <span class="page-loader-text">Loading...</span>
</div>

<div class="sidebar-nav no-print">

    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i data-lucide="chevron-left" id="toggle-icon"></i>
    </button>

    <img src="../../images/white lang.jpg" alt="SOFTVOTE logo" class="sidebar-logo">
    <div class="sidebar-brand">
        <p>Admin Panel</p>
    </div>


    <hr class="sidebar-divider">

    <nav class="sidebar-links">
        <?php foreach ($nav_items as $item): ?>
            <?php $is_active = ($current_page === $item['href']); ?>
            <a href="<?= htmlspecialchars($item['href']) ?>"
                title="<?= htmlspecialchars($item['label']) ?>"
                class="sidebar-link<?= $is_active ? ' active' : '' ?>">
                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <hr style=" width:100%;border:1px solid #1A3A7A; margin: 9px;">

    <?php include '../../logout_modal.php'; ?>
    <button class="sidebar-logout" onclick="openLogoutModal('admin')">
        <i data-lucide="log-out"></i>
        <span>Logout</span>
    </button>

    <hr style=" width:100%;border:1px solid #1A3A7A; margin: 9px;">
    <p class="footer">
        © 2026 SOFTVOTE System
        Student Council Elections </p>
</div>


<script>
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    lucide.createIcons();

    function toggleSidebar() {
        const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
        const toggleIcon = document.getElementById('toggle-icon');
        toggleIcon.setAttribute('data-lucide', isCollapsed ? 'chevron-right' : 'chevron-left');
        lucide.createIcons();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const icon = document.getElementById('toggle-icon');
        if (document.body.classList.contains('sidebar-collapsed')) {
            icon.setAttribute('data-lucide', 'chevron-right');
            lucide.createIcons();
        }
    });

    // Show loader on nav link clicks
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');

            // Skip if same page, external, or modifier key held
            if (!href || href === '#' || e.ctrlKey || e.metaKey || e.shiftKey) return;
            if (this.classList.contains('active')) return;

            document.getElementById('pageLoader').classList.add('active');
        });
    });

    // Hide loader when page finishes loading (back/forward navigation)
    window.addEventListener('pageshow', () => {
        document.getElementById('pageLoader').classList.remove('active');
    });
</script>