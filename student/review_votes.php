<?php
session_start();
require_once '../config_db.php';

// Fetch partylist names in same order as dashboard (alphabetical)
$partyNames = [];
$plRes = $conn->query("SELECT name FROM partylists ORDER BY name ASC");
while ($row = $plRes->fetch_assoc()) {
  $partyNames[] = $row['name'];
}
if (!in_array('Independent', $partyNames)) {
  $partyNames[] = 'Independent';
}


// AUTHENTICATION 
if (!isset($_SESSION['student_id']) || $_SESSION['user_type'] !== 'student') {
  session_destroy();
  header("Location: ../auth/student_login.php");
  exit();
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
  session_destroy();
  header("Location: ../auth/student_login.php?error=Session+expired");
  exit();
}

if ($_SESSION['has_voted'] == 1) {
  header("Location: vote_success.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['review_votes'])) {
  header("Location: student_dashboard.php");
  exit();
}

$student_id = $_SESSION['student_sid'];
$errors = [];
$selected_votes = [];

// PAG VALIDATE NG MGA BOTONG PINILI AT PAGFETCH NG DETAILS NG CANDIDATE PARA SA REVIEW
$positions = $conn->query("SELECT DISTINCT p.id, p.position_name FROM positions p JOIN candidates c ON p.id = c.position_id ORDER BY p.display_order");
if (!$positions) die("Error fetching positions: " . $conn->error);

while ($position = $positions->fetch_assoc()) {
  $pos_id = $position['id'];
  $candidate_id = $_POST['position_' . $pos_id] ?? null;

  if (!$candidate_id) {
    $errors[] = "Please select a candidate for all positions.";
    break;
  }

  $stmt = $conn->prepare("
    SELECT 
        c.id,
        c.first_name,
        c.last_name,
        c.middle_name,
        c.photo_url,
        c.position_id,
        COALESCE(pl.name) AS partylist
    FROM candidates c
        LEFT JOIN partylists pl ON c.partylist_id = pl.id
    WHERE c.id = ? AND c.position_id = ?
");
  if (!$stmt) die("Prepare failed: " . $conn->error);

  $stmt->bind_param("ii", $candidate_id, $pos_id);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $errors[] = "Invalid candidate selection.";
    break;
  }

  $candidate = $result->fetch_assoc();

  $middle = !empty($candidate['middle_name'])
    ? strtoupper(substr($candidate['middle_name'], 0, 1)) . '.'
    : '';
  $formatted_name = strtoupper($candidate['last_name']) . ', ' .
    ucwords(strtoupper($candidate['first_name'])) .
    (!empty($middle) ? ' ' . $middle : '');

  $initials = strtoupper(substr($candidate['first_name'], 0, 1)) .
    strtoupper(substr($candidate['last_name'], 0, 1));

  $selected_votes[] = [
    'position_id'    => $pos_id,
    'position_name'  => $position['position_name'],
    'candidate_id'   => $candidate_id,
    'photo_url'      => $candidate['photo_url'] ?? null,
    'candidate_name' => trim($formatted_name),
    'initials'       => $initials ?: '?',
    'party_name'     => $candidate['partylist'] ?? 'Independent',
  ];
}

if (!empty($errors)) {
  $_SESSION['vote_errors'] = $errors;
  header("Location: student_dashboard.php");
  exit();
}

// pag-fetch ng lahat ng kandidato para sa edit modal
$allCandidatesQuery = $conn->query("
  SELECT 
    c.id AS candidate_id,
    c.first_name,
    c.last_name, 
    c.middle_name, 
    c.photo_url,
    p.id AS position_id,
    p.position_name,
    COALESCE(pl.name) AS party_name
  FROM candidates c
  JOIN positions p ON c.position_id = p.id
  LEFT JOIN partylists pl ON c.partylist_id = pl.id
  ORDER BY p.display_order, pl.name, c.last_name
");

$allCandidatesByPosition = [];
while ($row = $allCandidatesQuery->fetch_assoc()) {
  $mid = !empty($row['middle_name']) ? strtoupper(substr($row['middle_name'], 0, 1)) . '.' : '';
  $row['formatted_name'] = strtoupper($row['last_name']) . ', ' .
    ucwords(strtoupper($row['first_name'])) .
    (!empty($mid) ? ' ' . $mid : '');
  $row['initials'] = strtoupper(substr($row['first_name'], 0, 1)) .
    strtoupper(substr($row['last_name'], 0, 1));
  $allCandidatesByPosition[$row['position_id']][] = $row;
}

// FINAL SUBMISSION
if (isset($_POST['confirm_vote'])) {
  $conn->begin_transaction();
  try {
    foreach ($selected_votes as $vote) {
      $stmt = $conn->prepare("INSERT INTO votes (student_id, position_id, candidate_id) VALUES (?, ?, ?)");
      $stmt->bind_param("sii", $student_id, $vote['position_id'], $vote['candidate_id']);
      $stmt->execute();
    }
    $update = $conn->prepare("UPDATE students SET has_voted = 1 WHERE student_id = ?");
    $update->bind_param("s", $student_id);
    $update->execute();
    $_SESSION['has_voted'] = 1;
    $conn->commit();
    header("Location: vote_success.php");
    exit();
  } catch (Exception $e) {
    $conn->rollback();
    die("Error processing votes: " . $e->getMessage());
  }
}

// Group votes
$leaders = [];
$board_members = [];
foreach ($selected_votes as $vote) {
  if (stripos($vote['position_name'], 'board') !== false || stripos($vote['position_name'], 'representative') !== false) {
    $board_members[] = $vote;
  } else {
    $leaders[] = $vote;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Review Your Votes - SOFTVOTE</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles/student/review.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script>
    // Run synchronously in <head> so colors are ready before first paint
    (function() {
      const PL_COLOR_LS_KEY = 'softvote_global_colors';
      const PL_DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];
      const partylistNames = <?= json_encode(array_values($partyNames ?? [])) ?>;

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

      // Build a CSS class per party name and inject into <head> immediately
      const rules = partylistNames.map((name, i) => {
        const color = colorList[i];
        const safeName = CSS.escape(name);
        return `.party-badge[data-party="${safeName}"] {
        color: ${color} !important;
        border-color: ${color} !important;
        background: ${color}18 !important;
      }`;
      }).join('\n');

      const style = document.createElement('style');
      style.textContent = rules;
      document.head.appendChild(style);

      // Store globally so the bottom script can reuse it
      window.__partyColors = {};
      partylistNames.forEach((name, i) => {
        window.__partyColors[name] = colorList[i];
      });
    })();
  </script>
</head>

<body>

  <div class="main-wrapper">
    <form method="POST" action="" id="mainForm">

      <?php foreach ($selected_votes as $vote): ?>
        <input type="hidden"
          name="position_<?php echo $vote['position_id']; ?>"
          value="<?php echo $vote['candidate_id']; ?>"
          id="hidden_pos_<?php echo $vote['position_id']; ?>">
      <?php endforeach; ?>
      <input type="hidden" name="confirm_vote" value="1">
      <input type="hidden" name="review_votes" value="1">

      <!-- COUNCIL POSITIONS -->
      <?php if (!empty($leaders)): ?>
        <div class="review-section">
          <!-- HEADER -->
          <div class="review-header">
            <div class="logo-wrapper">
              <img src="../images/white lang.jpg" alt="logo">
            </div>
            <div>
              <h1 style="text-align: center;">Review Your Votes</h1>
              <p>Please review your votes before submitting</p>
            </div>
          </div>
          <div class="section-label">
            <span class="section-bar"></span>
            Council Positions
          </div>

          <?php foreach ($leaders as $i => $vote): ?>
            <div class="review-card" style="animation-delay: <?php echo $i * 0.06; ?>s">
              <div class="card-photo">
                <?php
                $photo = $vote['photo_url'];
                $isExternal = !empty($photo) && strpos($photo, 'http') === 0;
                $imgSrc = $isExternal ? $photo : (!empty($photo) ? '../' . $photo : '');
                ?>
                <?php if (!empty($imgSrc)): ?>
                  <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                    alt="<?php echo htmlspecialchars($vote['candidate_name']); ?>"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                  <div class="initials-fallback" style="display:none">
                    <?php echo htmlspecialchars($vote['initials']); ?>
                  </div>
                <?php else: ?>
                  <div class="initials-fallback">
                    <?php echo htmlspecialchars($vote['initials']); ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="card-info">
                <div class="position-tag"><?php echo htmlspecialchars($vote['position_name']); ?></div>
                <div class="candidate-name"><?php echo htmlspecialchars($vote['candidate_name']); ?></div>
                <div class="party-badge" data-party="<?php echo htmlspecialchars($vote['party_name']); ?>">
                  <?php echo htmlspecialchars($vote['party_name']); ?>
                </div>
              </div>

              <div class="card-actions">
                <button type="button"
                  class="edit-btn"
                  title="Change candidate"
                  onclick="openEditModal(<?php echo $vote['position_id']; ?>, '<?php echo addslashes($vote['position_name']); ?>', <?php echo $vote['candidate_id']; ?>)">
                  <i data-lucide="pencil"></i>
                </button>
                <div class="check-badge">
                  <i data-lucide="circle-check"></i>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- BOARD OF DIRECTORS -->
      <?php if (!empty($board_members)): ?>
        <div class="review-section">
          <div class="section-label">
            <span class="section-bar" style="background:#7c3aed"></span>
            Board of Directors
          </div>

          <?php foreach ($board_members as $i => $vote): ?>
            <div class="review-card" style="animation-delay: <?php echo ($i + count($leaders)) * 0.06; ?>s">
              <div class="card-photo">
                <?php
                $photo = $vote['photo_url'];
                $isExternal = !empty($photo) && strpos($photo, 'http') === 0;
                $imgSrc = $isExternal ? $photo : (!empty($photo) ? '../' . $photo : '');
                ?>
                <?php if (!empty($imgSrc)): ?>
                  <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                    alt="<?php echo htmlspecialchars($vote['candidate_name']); ?>"
                    onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                  <div class="initials-fallback" style="display:none">
                    <?php echo htmlspecialchars($vote['initials']); ?>
                  </div>
                <?php else: ?>
                  <div class="initials-fallback" style="background: linear-gradient(135deg,#10b981,#059669)">
                    <?php echo htmlspecialchars($vote['initials']); ?>
                  </div>
                <?php endif; ?>
              </div>

              <div class="card-info">
                <div class="position-tag"><?php echo htmlspecialchars($vote['position_name']); ?></div>
                <div class="candidate-name"><?php echo htmlspecialchars($vote['candidate_name']); ?></div>
                <div class="party-badge" data-party="<?php echo htmlspecialchars($vote['party_name']); ?>">
                  <?php echo htmlspecialchars($vote['party_name']); ?>
                </div>
              </div>

              <div class="card-actions">
                <button type="button"
                  class="edit-btn"
                  title="Change candidate"
                  onclick="openEditModal(<?php echo $vote['position_id']; ?>, '<?php echo addslashes($vote['position_name']); ?>', <?php echo $vote['candidate_id']; ?>)">
                  <i data-lucide="pencil"></i>
                </button>
                <div class="check-badge check-badge--green">
                  <i data-lucide="circle-check"></i>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- SUMMARY BAR -->
      <div class="summary-bar">
        <div class="summary-icon"><i data-lucide="circle-check"></i></div>
        <div class="summary-text">
          Total Votes: <strong><?php echo count($selected_votes); ?></strong>
          <p>You have voted for all required positions.
            Once submit, you will not be able to change your votes.
          </p>
        </div>
      </div>

      <!-- ACTION BUTTONS -->
      <div class="action-row">

        <button type="button" class="back-btn" onclick="goBackWithSelected()">
          <i data-lucide="arrow-left"></i>
          Back to Voting
        </button>

        <button type="button" class="submit-btn" onclick="confirmSubmit()">
          <i data-lucide="send"></i>
          Submit My Votes
        </button>
      </div>

    </form>
  </div>

  <!-- EDIT MODAL -->
  <div class="modal-overlay" id="editModal" onclick="closeModalOutside(event)">
    <div class="modal-box">

      <div class="modal-header">
        <div class="modal-title-wrap">
          <div>
            <h3 class="modal-title">Change Candidate</h3>
            <p class="modal-subtitle" id="modalPositionLabel">Select a new candidate for this position</p>
          </div>
        </div>
        <button class="modal-close" onclick="closeEditModal()">
          <i data-lucide="x"></i>
        </button>
      </div>

      <div class="modal-body" id="modalCandidateList">
        <!-- dito i fetch ang mga candidates -->
      </div>

      <div class="modal-footer">
        <button class="modal-cancel-btn" onclick="closeEditModal()">Cancel</button>
        <button class="modal-confirm-btn" id="modalConfirmBtn" onclick="confirmChange()" disabled>
          Confirm Change
        </button>
      </div>

    </div>
  </div>

  <!-- CONFIRM SUBMIT MODAL -->
  <div id="confirmModal" class="modal-overlay" onclick="closeConfirmModalOutside(event)">
    <div class="modal-box confirm-modal-box">
      <i class="confirm-modal-icon" data-lucide="circle-check-big"></i>
      <h2 class="confirm-modal-title">Confirm Your Vote</h2>
      <p class="confirm-modal-text">
        Once submitted, your vote cannot be changed. Do you want to continue?
      </p>
      <div class="confirm-modal-actions">
        <button class="confirm-cancel-btn" onclick="closeConfirmModal()">Cancel</button>
        <button class="confirm-submit-btn" onclick="submitVote()">Confirm</button>
      </div>
    </div>
  </div>

  <script>
    const allCandidates = <?php echo json_encode($allCandidatesByPosition); ?>;

    let currentPositionId = null;
    let currentlySelected = null;
    let pendingCandidateId = null;

    // para mabuksan ang edit modal at ipakita ang mga candidates based sa posisyon
    function openEditModal(positionId, positionName, currentCandidateId) {
      currentPositionId = positionId;
      currentlySelected = currentCandidateId;
      pendingCandidateId = null;

      document.getElementById('modalPositionLabel').textContent = positionName;
      document.getElementById('modalConfirmBtn').disabled = true;

      const list = document.getElementById('modalCandidateList');
      list.innerHTML = '';

      const candidates = allCandidates[positionId] || [];

      candidates.forEach(c => {
        const isSelected = c.candidate_id == currentCandidateId;

        const photo = c.photo_url || '';
        const isExternal = photo.startsWith('http');
        const imgSrc = isExternal ? photo : (photo ? '../' + photo : '');

        const photoHtml = imgSrc ?
          `<img src="${imgSrc}" alt="${c.formatted_name}"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
         <div class="modal-initials" style="display:none">${c.initials}</div>` :
          `<div class="modal-initials">${c.initials}</div>`;

        const card = document.createElement('div');
        card.className = 'modal-candidate';
        card.dataset.candidateId = c.candidate_id;

        card.innerHTML = `
          <div class="modal-candidate-photo">${photoHtml}</div>
          <div class="modal-candidate-info">
            <div class="modal-candidate-name">${c.formatted_name}</div>
            <div class="modal-candidate-party">${c.party_name || 'Independent'}</div>
          </div>
          <div class="modal-radio">
            <div class="radio-dot ${isSelected ? 'radio-dot--active' : ''}"></div>
          </div>
        `;

        // ALL candidates clickable
        card.onclick = () => selectModalCandidate(card, c.candidate_id);

        list.appendChild(card);
      });

      document.getElementById('editModal').classList.add('modal--open');
      document.body.style.overflow = 'hidden';

      lucide.createIcons();
    }

    // para pumili ng bagong kandidato sa modal
    function selectModalCandidate(card, candidateId) {
      document.querySelectorAll('.modal-candidate').forEach(c => {
        c.classList.remove('modal-candidate--selected');
        c.querySelector('.radio-dot')?.classList.remove('radio-dot--active');
      });

      card.classList.add('modal-candidate--selected');
      card.querySelector('.radio-dot').classList.add('radio-dot--active');

      pendingCandidateId = candidateId;

      document.getElementById('modalConfirmBtn').disabled = false;
    }

    // para iconfirm ang pagbabago ng kandidato
    function confirmChange() {
      if (!pendingCandidateId || !currentPositionId) return;

      // iupdate ang nakatagong input sa pangunahing form
      const hiddenInput = document.getElementById('hidden_pos_' + currentPositionId);
      if (hiddenInput) hiddenInput.value = pendingCandidateId;

      // iretrieve ang impormasyon ng bagong napiling kandidato
      const selectedCard = document.querySelector('.modal-candidate--selected');
      const newName = selectedCard.querySelector('.modal-candidate-name').textContent;
      const newParty = selectedCard.querySelector('.modal-candidate-party').textContent;
      const newPhoto = selectedCard.querySelector('img');
      const newInitials = selectedCard.querySelector('.modal-initials')?.textContent || '';

      // i-update ang review card sa pahina
      updateReviewCard(currentPositionId, newName, newParty, newPhoto ? newPhoto.src : null, newInitials, pendingCandidateId);

      closeEditModal();
    }

    // i-update ang review card pagkatapos magpalit ng kandidato
    function updateReviewCard(positionId, name, party, photoSrc, initials, newCandidateId) {
      const allEditBtns = document.querySelectorAll('.edit-btn');

      allEditBtns.forEach(btn => {
        const onclickAttr = btn.getAttribute('onclick');
        if (!onclickAttr || !onclickAttr.includes('openEditModal(' + positionId + ',')) return;

        const card = btn.closest('.review-card');
        if (!card) return;

        // i-update ang pangalan ng candidates
        const nameEl = card.querySelector('.candidate-name');
        if (nameEl) nameEl.textContent = name;

        // i-update ang pangalan ng party
        const partyEl = card.querySelector('.party-badge');
        if (partyEl) partyEl.textContent = party;
        partyEl.dataset.party = party;

        // i-update ang image o initials ng candidates
        const photoWrap = card.querySelector('.card-photo');
        if (photoWrap) {
          photoWrap.innerHTML = photoSrc ?
            `<img src="${photoSrc}" alt="${name}"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
             <div class="initials-fallback" style="display:none">${initials}</div>` :
            `<div class="initials-fallback">${initials}</div>`;
        }

        // i-update ang onclick para tama ang pagbukas sa susunod na pag-edit
        const positionTag = card.querySelector('.position-tag').textContent;
        btn.setAttribute('onclick', `openEditModal(${positionId}, '${positionTag}', ${newCandidateId})`);
      });
      applyBadgeColors();
    }

    // para isara ang edit modal
    function closeEditModal() {
      document.getElementById('editModal').classList.remove('modal--open');
      document.body.style.overflow = '';
      pendingCandidateId = null;
    }

    // isara ang edit modal kapag na-click ang labas nito
    function closeModalOutside(e) {
      if (e.target.id === 'editModal') closeEditModal();
    }

    // para buksan ang confirm submit modal bago i-submit ang mga boto
    function confirmSubmit() {
      document.getElementById('confirmModal').classList.add('modal--open');
      document.body.style.overflow = 'hidden';
    }

    // isara ang confirm modal
    function closeConfirmModal() {
      document.getElementById('confirmModal').classList.remove('modal--open');
      document.body.style.overflow = '';
    }

    // isara ang confirm modal kapag na-click ang labas nito
    function closeConfirmModalOutside(e) {
      if (e.target.id === 'confirmModal') closeConfirmModal();
    }

    // i-submit ang form pagkatapos ma-confirm ng estudyante ang kanyang mga boto
    function submitVote() {
      closeConfirmModal();
      document.getElementById('mainForm').submit();
    }

    // para bumalik sa voting page na may mga boto pa rin na naka-select
    function goBackWithSelected() {
      const selections = {};

      // kolektahin ang lahat ng kasalukuyang pinili gamit ang hidden inputs
      document.querySelectorAll('input[type="hidden"][id^="hidden_pos_"]').forEach(input => {
        const posId = input.id.replace('hidden_pos_', '');
        selections[posId] = input.value;
      });

      // i-save sa sessionStorage bago mag-navigate pabalik
      sessionStorage.setItem('savedVotes', JSON.stringify(selections));
      window.location.href = 'student_dashboard.php';
    }

    // pagii-initialize ang lucide icons pagkatapos maload ang page
    document.addEventListener('DOMContentLoaded', () => {
      lucide.createIcons();
    });

    // Carry partylist colors from admin localStorage into review page badges
    const PL_COLOR_LS_KEY = 'softvote_global_colors';
    const PL_DEFAULT_PALETTE = ['#1e3a8a', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#bfdbfe', '#a78bfa', '#34d399'];
    const partylistNames = <?= json_encode(array_values($partyNames ?? [])) ?>;

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

    const colorList = loadPartyColors();
    const partyColors = {};
    partylistNames.forEach((name, i) => {
      partyColors[name] = colorList[i];
    });

    // Colors already injected via <head> script — just reapply on dynamic badge updates
    function applyBadgeColors() {
      document.querySelectorAll('.party-badge').forEach(badge => {
        const partyName = badge.dataset.party || badge.textContent.trim();
        const color = window.__partyColors?.[partyName];
        if (color) {
          badge.style.color = color;
          badge.style.borderColor = color;
          badge.style.background = color + '18';
        }
      });
    }
    applyBadgeColors();

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