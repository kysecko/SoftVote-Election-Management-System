<?php
// Detect path depth to always point correctly to /auth/logout.php
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base = '/softvote/auth/logout.php';
?>

<div id="logoutModal" class="logout-modal">
    <div class="modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div class="modal-buttons">
            <button onclick="closeLogoutModal()" class="cancel-btn">Cancel</button>
            <a href="#" id="logoutConfirmBtn" class="confirm-btn">Confirm</a>
        </div>
    </div>
</div>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        background-color: #f4f6f8;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
    }

    .logout-modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }

    .modal-content {
        background: #fff;
        width: 350px;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .modal-buttons {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        gap: 5px;
    }

    .confirm-btn {
        background: #dc3545;
        color: white;
        padding: 8px 15px;
        text-decoration: none;
        border-radius: 5px;
    }

    .confirm-btn:hover {
        background: #e94e5d;
    }

    .cancel-btn {
        background: #6c757d;
        color: white;
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .cancel-btn:hover {
        background: #8c9297;
    }
</style>

<script>
    const LOGOUT_BASE = "<?= $base ?>";

    function openLogoutModal(type = 'student') {
        document.getElementById("logoutModal").style.display = "block";
        document.getElementById("logoutConfirmBtn").href = LOGOUT_BASE + "?type=" + type;
    }

    function closeLogoutModal() {
        document.getElementById("logoutModal").style.display = "none";
    }

    window.onclick = function(event) {
        let modal = document.getElementById("logoutModal");
        if (event.target == modal) modal.style.display = "none";
    }
</script>