<?php
require_once '../superadmin_auth_guard.php';
require_once '../../config_db.php';

$active_nav = 'settings';

// TOASTS FROM REDIRECT
$toast_success = htmlspecialchars($_GET['success'] ?? '');
$toast_error   = htmlspecialchars($_GET['error']   ?? '');

// CURRENT ELECTION STATUS
$statusRes     = $conn->query("SELECT setting_value FROM election_settings WHERE setting_key = 'election_status' LIMIT 1");
$currentStatus = $statusRes ? $statusRes->fetch_assoc()['setting_value'] : 'not_started';

$statusLabels = [
    'not_started' => ['label' => 'Not Started', 'class' => 'badge-gray'],
    'ongoing'     => ['label' => 'Ongoing',     'class' => 'badge-green'],
    'ended'       => ['label' => 'Ended',        'class' => 'badge-red'],
];
$badge = $statusLabels[$currentStatus] ?? $statusLabels['not_started'];

// PH TIMEZONE 
date_default_timezone_set('Asia/Manila');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>System Settings — SOFTVOTE</title>
    <link rel="stylesheet" href="../../styles/superadmin/dashboard.css">
    <link rel="stylesheet" href="../../styles/superadmin/settings.css">

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

    <style>
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
        }

        .countdown-icon svg {
            width: 26px;
            height: 26px;
        }

        .countdown-icon.restart {
            background: #fee2e2;
            color: #dc2626;
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

        .ring-wrap {
            position: relative;
            width: 110px;
            height: 110px;
            margin: 0 auto 22px;
        }

        .ring-wrap svg {
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
            animation: urgentPulse .4s ease infinite alternate;
        }

        .countdown-btns {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .cd-cancel {
            padding: 10px 20px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }

        .cd-cancel:hover {
            background: #f9fafb;
        }

        .cd-proceed {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }

        .cd-proceed.restart {
            background: #dc2626;
            color: #fff;
        }

        .cd-proceed.restart:hover {
            background: #b91c1c;
        }

        @keyframes urgentPulse {
            from {
                transform: scale(1);
            }

            to {
                transform: scale(1.08);
            }
        }
    </style>
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
                    <h1>System <span>Settings</span></h1>
                    <p>Database management, log control, and election cycle tools</p>
                </div>
            </div>

            <div class="settings-grid">

                <!-- Database Backup -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon amber"><i data-lucide="database" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">Database Backup</div>
                            <div class="setting-desc">Export a full snapshot of all election data, votes, candidates, and logs. Use before making major changes.</div>
                        </div>
                    </div>
                    <div class="setting-actions">
                        <a href="../superadmin_backup_sql.php" class="setting-btn amber">
                            <i data-lucide="download" class="icon"></i>Export SQL
                        </a>
                        <a href="../superadmin_backup_json.php" class="setting-btn">
                            <i data-lucide="file-json" class="icon"></i>Export JSON
                        </a>
                    </div>
                </div>

                <!-- Reset Log History -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon red"><i data-lucide="trash-2" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">Reset Log History</div>
                            <div class="setting-desc">Clear all audit log entries after a completed election cycle. Export a backup first — this cannot be undone.</div>
                        </div>
                    </div>
                    <div class="setting-actions">
                        <button class="setting-btn red" onclick="openClearLogsModal()">
                            <i data-lucide="alert-triangle" class="icon"></i>Clear All Logs
                        </button>
                    </div>
                </div>

                <!-- Election Status Control -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon green"><i data-lucide="toggle-right" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">
                                Election Status
                                <span class="status-badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
                            </div>
                            <div class="setting-desc">Control the election cycle. All connected admin accounts will reflect this change immediately.</div>
                        </div>
                    </div>
                    <div class="setting-actions">

                        <!-- Set Ongoing -->
                        <button class="setting-btn green"
                            <?= $currentStatus === 'ongoing' ? 'disabled' : '' ?>
                            onclick="openElectionStatusModal('ongoing')">
                            <i data-lucide="play-circle" class="icon"></i>Set Ongoing
                        </button>

                        <!-- End Election -->
                        <button class="setting-btn red"
                            <?= $currentStatus === 'ended' ? 'disabled' : '' ?>
                            onclick="openElectionStatusModal('ended')">
                            <i data-lucide="stop-circle" class="icon"></i>End Election
                        </button>

                        <!-- Restart / Reset -->
                        <button class="setting-btn"
                            <?= $currentStatus === 'not_started' ? 'disabled' : '' ?>
                            onclick="openElectionStatusModal('not_started')">
                            <i data-lucide="refresh-cw" class="icon"></i>Restart
                        </button>

                    </div>
                </div>

                <!-- Reset Election Cycle -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon red"><i data-lucide="refresh-cw" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">Reset Election Cycle</div>
                            <div class="setting-desc">Wipe all votes and reset the cycle to start fresh. Requires a backup first. This is irreversible.</div>
                        </div>
                    </div>
                    <div class="setting-actions">
                        <button class="setting-btn red" onclick="openResetCycleModal()">
                            <i data-lucide="alert-octagon" class="icon"></i>Reset Full Cycle
                        </button>
                    </div>
                </div>

                <!-- Change Superadmin Password -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon blue"><i data-lucide="lock" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">Change Password</div>
                            <div class="setting-desc">Update your own superadmin password. Use a strong, unique password — minimum 8 characters.</div>
                        </div>
                    </div>
                    <div class="setting-actions">
                        <button class="setting-btn" onclick="openSAPasswordModal()">
                            <i data-lucide="key-round" class="icon"></i>Change Password
                        </button>
                    </div>
                </div>

                <!-- System Information -->
                <div class="setting-card">
                    <div class="setting-card-top">
                        <div class="setting-icon purple"><i data-lucide="info" class="icon"></i></div>
                        <div class="setting-text">
                            <div class="setting-title">System Information</div>
                            <div class="setting-desc">Current runtime environment details.</div>
                        </div>
                    </div>
                    <div>
                        <div class="sysinfo-row">
                            <span class="key">PHP Version</span>
                            <span class="val"><?= phpversion() ?></span>
                        </div>
                        <div class="sysinfo-row">
                            <span class="key">Server Software</span>
                            <span class="val"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? '—') ?></span>
                        </div>
                        <div class="sysinfo-row">
                            <span class="key">Session Timeout</span>
                            <span class="val">30 minutes</span>
                        </div>
                        <div class="sysinfo-row">
                            <span class="key">Current Date & Time</span>
                            <?php function phTime($format = "M d, Y h:i:s A")
                            {
                                return (new DateTime("now", new DateTimeZone("Asia/Manila")))->format($format);
                            } ?>

                            <span class="val ok"><?= phTime() ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ELECTION STATUS MODAL -->
            <div class="modal-overlay" id="electionStatusModal">
                <div class="modal">
                    <div class="modal-icon-wrap" id="electionStatusIcon"></div>
                    <h3 id="electionStatusTitle"></h3>
                    <p id="electionStatusDesc"></p>
                    <div class="modal-actions">
                        <button class="btn-cancel" onclick="closeModal('electionStatusModal')">Cancel</button>
                        <form method="POST" action="../settings_actions.php" style="display:inline">
                            <input type="hidden" name="action" value="set_election_status">
                            <input type="hidden" name="status" id="electionStatusValue">
                            <button type="submit" class="btn-confirm" id="electionStatusConfirmBtn">Confirm</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CLEAR LOGS MODAL -->
            <div class="modal-overlay" id="clearLogsModal">
                <div class="modal">
                    <h3>Clear All Audit Logs?</h3>
                    <p>This will permanently delete all audit log records. Export a backup first. This cannot be undone.</p>
                    <div class="modal-actions">
                        <button class="btn-cancel" onclick="closeModal('clearLogsModal')">Cancel</button>
                        <form method="POST" action="../settings_actions.php" style="display:inline">
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn-confirm">Yes, Clear Logs</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RESET CYCLE MODAL -->
            <div class="modal-overlay" id="resetCycleModal">
                <div class="modal">
                    <h3>Reset Election Cycle?</h3>
                    <p>This will wipe ALL votes and reset the cycle to start fresh. Requires a backup first. This is irreversible.</p>
                    <div class="modal-actions">
                        <button class="btn-cancel" onclick="closeModal('resetCycleModal')">Cancel</button>
                        <button class="btn-confirm" onclick="
                closeModal('resetCycleModal');
                openCountdown();
            ">Yes, Reset Everything</button>
                    </div>
                </div>
            </div>

            <!-- RESET COUNTDOWN MODAL -->
            <div class="countdown-overlay" id="resetCountdownModal">
                <div class="countdown-box">
                    <div class="countdown-icon restart">
                        <i data-lucide="alert-octagon"></i>
                    </div>
                    <h2>Resetting Election Cycle…</h2>
                    <p class="cd-subtitle">All votes will be permanently deleted. You may cancel before the timer ends.</p>
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
                        <button class="cd-proceed restart" id="cdProceedBtn">Reset Now</button>
                    </div>
                </div>
            </div>

            <!-- CHANGE SUPERADMIN PASSWORD MODAL -->
            <div class="modal-overlay" id="saPasswordModal">
                <div class="modal">
                    <h3>Change My Password</h3>
                    <p>Set a new password for your superadmin account. Minimum 8 characters.</p>
                    <form method="POST" action="../settings_actions.php">
                        <input type="hidden" name="action" value="change_sa_password">
                        <div class="field"><label>New Password</label><input type="password" name="new_password" placeholder="••••••••" required minlength="8"></div>
                        <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" placeholder="••••••••" required minlength="8"></div>
                        <div class="modal-actions">
                            <button type="button" class="btn-cancel" onclick="closeModal('saPasswordModal')">Cancel</button>
                            <button type="submit" class="btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <form method="POST" action="../settings_actions.php" id="resetCycleForm" style="display:none">
                <input type="hidden" name="action" value="reset_cycle">
            </form>


            <script>
                lucide.createIcons();

                // RESET CYCLE COUNTDOWN
                const RESET_SECONDS = 10;
                const resetCircumference = 2 * Math.PI * 46;
                let resetTimer = null;
                let resetRemaining = RESET_SECONDS;

                const resetModal = document.getElementById('resetCountdownModal');
                const resetRing = document.getElementById('cdRing');
                const resetNumber = document.getElementById('cdNumber');

                function openCountdown() {
                    clearInterval(resetTimer);
                    resetRemaining = RESET_SECONDS;

                    resetNumber.textContent = resetRemaining;
                    resetNumber.classList.remove('urgent');

                    // Set ring inline — same pattern as admin dashboard
                    resetRing.removeAttribute('class');
                    resetRing.setAttribute('stroke', '#dc2626');
                    resetRing.setAttribute('stroke-width', '8');
                    resetRing.setAttribute('stroke-linecap', 'round');
                    resetRing.setAttribute('fill', 'none');
                    resetRing.style.transition = 'none';
                    resetRing.style.strokeDasharray = resetCircumference;
                    resetRing.style.strokeDashoffset = 0;

                    void resetRing.offsetWidth; // force reflow

                    resetRing.style.transition = 'stroke-dashoffset 1s linear';
                    resetModal.classList.add('active');

                    resetTimer = setInterval(() => {
                        resetRemaining--;
                        resetNumber.textContent = resetRemaining;
                        resetRing.style.strokeDashoffset =
                            resetCircumference * (1 - resetRemaining / RESET_SECONDS);

                        if (resetRemaining <= 2) resetNumber.classList.add('urgent');

                        if (resetRemaining <= 0) {
                            clearInterval(resetTimer);
                            document.getElementById('resetCycleForm').submit();
                        }
                    }, 1000);
                }

                document.getElementById('cdCancelBtn').addEventListener('click', () => {
                    clearInterval(resetTimer);
                    resetModal.classList.remove('active');
                    resetNumber.classList.remove('urgent');
                });

                document.getElementById('cdProceedBtn').addEventListener('click', () => {
                    clearInterval(resetTimer);
                    document.getElementById('resetCycleForm').submit();
                });

                resetModal.addEventListener('click', e => {
                    if (e.target === resetModal) {
                        clearInterval(resetTimer);
                        resetModal.classList.remove('active');
                        resetNumber.classList.remove('urgent');
                    }
                });

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

                document.querySelectorAll('.modal-overlay').forEach(m => {
                    m.addEventListener('click', e => {
                        if (e.target === m) m.classList.remove('open');
                    });
                });

                /* Election status modal — dynamically populated based on which button was clicked */
                const electionStatusConfig = {
                    ongoing: {
                        icon: 'play-circle',
                        color: 'green',
                        title: 'Start the Election?',
                        desc: 'This will set the election to <strong>Ongoing</strong>. Students will immediately be able to cast their votes.',
                        btnText: 'Yes, Set Ongoing',
                        btnClass: 'btn-confirm green',
                    },
                    ended: {
                        icon: 'stop-circle',
                        color: 'red',
                        title: 'End the Election?',
                        desc: 'This will set the election to <strong>Ended</strong>. No more votes will be accepted after this.',
                        btnText: 'Yes, End Election',
                        btnClass: 'btn-confirm red',
                    },
                    not_started: {
                        icon: 'refresh-cw',
                        color: '',
                        title: 'Restart the Election?',
                        desc: 'This will reset the status to <strong>Not Started</strong>. The election cycle will need to be restarted manually.',
                        btnText: 'Yes, Restart',
                        btnClass: 'btn-confirm',
                    },
                };

                function openElectionStatusModal(status) {
                    const cfg = electionStatusConfig[status];
                    if (!cfg) return;

                    document.getElementById('electionStatusTitle').textContent = cfg.title;
                    document.getElementById('electionStatusDesc').innerHTML = cfg.desc;
                    document.getElementById('electionStatusValue').value = status;

                    const btn = document.getElementById('electionStatusConfirmBtn');
                    btn.textContent = cfg.btnText;
                    btn.className = cfg.btnClass;

                    document.getElementById('electionStatusModal').classList.add('open');
                    lucide.createIcons();
                }

                /* Settings section modals */
                function openClearLogsModal() {
                    document.getElementById('clearLogsModal').classList.add('open');
                }

                function openResetCycleModal() {
                    document.getElementById('resetCycleModal').classList.add('open');
                }

                function openSAPasswordModal() {
                    document.getElementById('saPasswordModal').classList.add('open');
                }
            </script>
</body>

</html>