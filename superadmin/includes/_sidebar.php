<?php
// Need to import sa bawat page ng superadmin
?>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="page-loader-spinner"></div>
    <span class="page-loader-text">Loading...</span>
</div>

<aside class="sidebar">

    <!-- Logo / Brand -->
    <div class="sidebar-logo">
        <div class="logo-container">
            <h3>Soft<span>Vote</span></h3>
        </div>
        <div class="logo-sub">Election Management System</div>
        <div class="sa-badge">Super Admin</div>
    </div>

    <hr class="sidebar-divider">

    <!-- Navigation -->
    <nav class="nav-section">
        <div class="nav-label">Main</div>
        <a href="dashboard.php"
           title="Dashboard"
           class="nav-item <?= ($active_nav === 'dashboard') ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard" class="icon"></i>
            <span>Dashboard</span>
        </a>
        <a href="manage_admins.php"
           title="Manage Admins"
           class="nav-item <?= ($active_nav === 'admins') ? 'active' : '' ?>">
            <i data-lucide="users" class="icon"></i>
            <span>Manage Admins</span>
        </a>
        <a href="activity_logs.php"
           title="Audit Logs"
           class="nav-item <?= ($active_nav === 'logs') ? 'active' : '' ?>">
            <i data-lucide="file-text" class="icon"></i>
            <span>Audit Logs</span>
        </a>
        <a href="sessions.php"
           title="Active Sessions"
           class="nav-item <?= ($active_nav === 'sessions') ? 'active' : '' ?>">
            <i data-lucide="activity" class="icon"></i>
            <span>Active Sessions</span>
        </a>
        <a href="statistics.php"
           title="Election Status"
           class="nav-item <?= ($active_nav === 'stats') ? 'active' : '' ?>">
            <i data-lucide="bar-chart-2" class="icon"></i>
            <span>Election Status</span>
        </a>

        <div class="nav-label" style="margin-top:8px">System</div>
        <a href="settings.php"
           title="System Settings"
           class="nav-item <?= ($active_nav === 'settings') ? 'active' : '' ?>">
            <i data-lucide="settings" class="icon"></i>
            <span>System Settings</span>
        </a>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sa-info">
            <div class="sa-avatar"><?= strtoupper(substr($sa_name, 0, 1)) ?></div>
            <div class="sa-details">
                <p class="sa-name"><?= htmlspecialchars($sa_name) ?></p>
                <span class="sa-role">Super Admin</span>
            </div>
        </div>

        <?php include __DIR__ . '/../../logout_modal.php'; ?>

        <button class="sidebar-logout" onclick="openLogoutModal('superadmin')">
            <i data-lucide="log-out"></i>
            <span>Logout</span>
        </button>

        <p class="footer-copyright">© 2026 SOFTVOTE System<br>Student Council Elections</p>
    </div>

</aside>

<script>
    lucide.createIcons();

    // Show page loader on nav clicks
    document.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (!href || href === '#' || e.ctrlKey || e.metaKey || e.shiftKey) return;
            if (this.classList.contains('active')) return;
            document.getElementById('pageLoader').classList.add('active');
        });
    });

    // Hide loader on back/forward navigation
    window.addEventListener('pageshow', () => {
        document.getElementById('pageLoader').classList.remove('active');
    });
</script>