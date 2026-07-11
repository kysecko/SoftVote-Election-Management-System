
## 1. **authenticateStudent()** 
**File:** `auth/login_auth.php` (Lines: ~130-170)

### Layunin
Nag-handle ng student login na may special logic para sa mga bagong users na kailangang magbago ng password sa unang login.

### Breakdown ng Complexity
- **Database Query:** Kumukuha ng student record na may password hash verification
- **Multi-stage Logic:** 
  - Nag-validate ng credentials gamit ang `password_verify()`
  - Nag-check kung bago ang user (`is_new_user = 1`)
  - Nagroute ng mga bagong users sa password change page vs. normal dashboard
  - Nag-handle ng voting status redirect (naboto na → `voted_already.php`)
- **Session Management:** 
  - Nag-regenerate ng session ID para sa security
  - Nagseset ng iba't ibang session variables based sa user type
  - Nagsisimula ng flags: `has_voted`, `user_type`, timestamps

### Pangunahing Challenges
- Nag-handle ng tatlong magkakaibang redirect paths based sa account state
- Dapat mag-verify ng password against bcrypt hash
- Nag-distinguish sa pagitan ng "bagong user na kailangan ng password" at "naboto na" states
- Kailangan ng session regeneration para sa security

### Code Snippet
```php
function authenticateStudent($student_id, $password, $conn)
{
    // PAGCHECK KUNG STUDENT AY NAKA-REGISTER AT VALID ANG PASSWORD
    if (!password_verify($password, $student['password'])) {
        redirectWithError("Invalid Student ID or Password", 'student', $student_id);
    }

    // BAGONG USER: KAILANGAN MAGSET NG SARILING PASSWORD
    if ((int)$student['is_new_user'] === 1) {
        $_SESSION['pending_student_id'] = $student['id'];
        header("Location: ../student/change_password.php");
        exit();
    }

    // NORMAL NA LOGIN: PAGCHECK NG VOTING STATUS
    header("Location: " . ($student['has_voted'] == 1
        ? "../student/voted_already.php"
        : "../student/student_dashboard.php"));
}
```

---

## 2. **authenticateSuperAdmin() & authenticateAdmin()**
**File:** `auth/login_auth.php` (Lines: ~31-120)

### Layunin
Dual authentication function para sa super admin at admin roles na may audit trail logging.

### Breakdown ng Complexity

#### authenticateSuperAdmin()
- **Account Status Check:** Nag-validate ng `is_active` flag bago hayaang mag-login
- **Audit Logging:** Tumatawag sa `writeAuditLog()` para sa lahat ng login attempts (success/failure)
- **Session Management:** 
  - Nag-clear ng lumang session data
  - Nag-regenerate ng session ID
  - Nagseset ng 6 session variables kasama ang login timestamp
- **Database Updates:** Nag-update ng `last_login` at `last_activity` timestamps
- **Failed Attempt Tracking:** Naglologue ng failed login attempts para sa security

#### authenticateAdmin()
- Similar sa super admin pero may slightly different validation
- Kasama ang audit log para sa admin login activities

### Pangunahing Challenges
- Dapat mag-handle ng successful at failed attempts na may logging
- Nangangailangan ng prepared statements para sa SQL injection prevention
- Multiple database interactions (SELECT, UPDATE, INSERT para sa audit log)
- Security-sensitive: nag-handle ng password verification at session regeneration

### Code Snippet
```php
function authenticateSuperAdmin($username, $password, $conn)
{
    // PAGKUHA NG SUPERADMIN GAMIT ANG PREPARED STATEMENT
    $stmt = $conn->prepare("SELECT id, username, password, name, is_active FROM superadmins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        logFailedAttempt($conn, 'superadmin', $username);
        redirectWithError("Invalid superadmin credentials", 'superadmin');
    }

    $sa = $result->fetch_assoc();

    // PAGCHECK KUNG ACCOUNT AY ACTIVE PA
    if (!$sa['is_active']) {
        redirectWithError("This superadmin account has been disabled", 'superadmin');
    }

    // VERIFICATION UNG BCRYPT PASSWORD
    if (!password_verify($password, $sa['password'])) {
        logFailedAttempt($conn, 'superadmin', $username);
        redirectWithError("Invalid superadmin credentials", 'superadmin');
    }

    // PAGUPDATED NG LOGIN TIMESTAMP
    $upd = $conn->prepare("UPDATE superadmins SET last_login = NOW(), last_activity = NOW() WHERE id = ?");
    $upd->bind_param("i", $sa['id']);
    $upd->execute();

    // PAGSULAT NG AUDIT LOG
    writeAuditLog($conn, 'superadmin', $sa['id'], $sa['name'], 'login', null, null, 'Superadmin logged in');

    // PAGERGENERATE NG SESSION AT PAGSESET NG VARIABLES
    session_regenerate_id(true);
    $_SESSION['superadmin_id'] = $sa['id'];
    $_SESSION['superadmin_name'] = $sa['name'];
    $_SESSION['user_type'] = 'superadmin';
    $_SESSION['login_time'] = time();
}
```

---

## 3. **fetch_results()** - Vote Aggregation Function
**File:** `administrator/actions/fetch_results.php` (Complete file)

### Layunin
Nagrereturns ng JSON-formatted vote results na may vote counts at percentages na naggupu ng position at party list.

### Breakdown ng Complexity
- **Complex SQL Query:** 
  - Nag-join ng candidates, positions, party lists, at votes tables
  - Gumagamit ng LEFT JOIN para sa optional party list data
  - Naggrupu ng multiple columns para sa accurate aggregation
  - Nagbabanto ng votes gamit ang `COUNT(v.id)`
- **Data Transformation:** 
  - Nag-restructure ng flat SQL results sa nested array structure
  - Nagbubuo ng buong pangalan mula sa separate first/middle/last name fields
  - Nag-handle ng null party lists (Independent candidates)
  - Dynamically namumuklot ng unique party names
- **Percentage Calculation:**
  - Naglolop sa bawat position at candidate
  - Nagkakalkula ng vote percentages: `(votes / total_votes) * 100`
  - Nag-handle ng edge case ng 0 total votes

### Pangunahing Challenges
- Malaking nested data structure na may multiple aggregation levels
- Dapat mag-handle ng NULL values para sa middle names at party lists
- Percentage calculation ay nangangailangan ng multi-pass algorithm (kumuha ng totals, pagkatapos kalkulahin ang percentages)
- GROUP BY ay nangangailangan ng multiple aggregate columns para sa database compatibility

### Code Snippet
```php
// KOMPLIKADONG JOIN NA MAY MULTIPLE LEFT JOINs PARA SA OPTIONAL FIELDS
$sql = "
    SELECT 
        c.id AS candidate_id,
        c.first_name,
        c.middle_name,
        c.last_name,
        pl.name AS partylist,
        p.id AS position_id,
        p.position_name,
        p.position_type,
        p.display_order,
        COUNT(v.id) AS votes
    FROM candidates c
    JOIN positions p ON c.position_id = p.id
    LEFT JOIN partylists pl ON pl.id = c.partylist_id
    LEFT JOIN votes v ON v.candidate_id = c.id
    GROUP BY c.id, c.first_name, c.middle_name, c.last_name, pl.name, p.id, p.position_name, p.position_type, p.display_order
    ORDER BY p.display_order ASC, p.position_name ASC
";

// PAG-TRANSFORM NG FLAT RESULTS SA NESTED STRUCTURE
while ($row = $res->fetch_assoc()) {
    $pid = $row['position_id'];
    
    // PAGINITIALIZE NG POSITION KUNG HINDI PA NAKIKITA
    if (!isset($position_candidates[$pid])) {
        $position_candidates[$pid] = [
            'position_name' => $row['position_name'],
            'position_type' => strtolower($row['position_type']),
            'candidates' => []
        ];
    }

    // PAGBUILD NG BUONG PANGALAN MULA SA MGA BAHAGI
    $full_name = trim(
        ($row['last_name'] ?? '') . ', ' .
        ($row['first_name'] ?? '') . ' ' .
        (!empty($row['middle_name']) ? strtoupper($row['middle_name'][0]) . '.' : '')
    );

    $position_candidates[$pid]['candidates'][] = [
        'candidate_id' => (int) $row['candidate_id'],
        'name' => $full_name,
        'votes' => (int) $row['votes'],
        'partylist' => trim($row['partylist'] ?? ''),
        'percentage' => 0,
    ];
}

// PAGCALCULATE NG PERCENTAGES (PANGALAWANG PASS REQUIRED)
foreach ($position_candidates as &$pos) {
    $total_votes = array_sum(array_column($pos['candidates'], 'votes'));
    foreach ($pos['candidates'] as &$c) {
        $c['percentage'] = $total_votes > 0
            ? round(($c['votes'] / $total_votes) * 100, 1)
            : 0;
    }
}
```

---

## 4. **admin_dashboard.php** - Dashboard Data Processing
**File:** `administrator/pages/admin_dashboard.php` (Lines: 1-100)

### Layunin
Nag-load at nag-process ng lahat ng dashboard data para sa admin kasama ang statistics, candidates na may vote counts, at photo URL generation.

### Breakdown ng Complexity

#### Part 1: Security & Status Checks
- Nag-validate ng admin session ay nag-exist at active
- Nag-check ng `is_active` flag bawat page load (nakakaiwas sa paggamit ng suspended accounts)
- Nag-implement ng session timeout (1 hour)
- Auto-regenerate session ID bawat 5 minuto

#### Part 2: Statistics Aggregation
- Tumatakbo ng 3+ separate queries upang makalkula:
  - Total na bilang ng students
  - Total voters (distinct)
  - Total na posisyon
- Nag-load ng election settings sa associative array

#### Part 3: Complex Candidate Data Fetching
- **SQL na may Multiple Joins:**
  ```sql
  SELECT candidates c
  JOIN positions p ON c.position_id = p.id
  LEFT JOIN partylists pl ON c.partylist_id = pl.id
  LEFT JOIN votes v ON v.candidate_id = c.id
  ```
- **Image URL Generation:**
  - Nag-detect ng real photos vs. placeholder avatars
  - Nagbubuo ng fallback DiceBear avatar URLs gamit ang initials
  - Nag-handle ng URL protocol (http vs. relative paths)
  
- **Vote Percentage Calculation:**
  - Naggrupo ng candidates by position
  - Nagkakalkula ng total votes per position
  - Nagkompyut ng percentage para sa bawat candidate

#### Part 4: Winner Determination
- Nagsosort ng candidates by votes para sa bawat position
- Nag-identify ng top vote-getter bilang winner
- Nag-handle ng tied scenarios implicitly (pumipili ng first sa sort)

### Pangunahing Challenges
- Multiple database queries (maaaring ma-optimize gamit ang single query)
- Image URL validation at fallback generation
- Percentage calculation na may null-handling
- Session state validation sa bawat page load
- Malaking data structure manipulation na may references (`&$pos`, `&$c`)

### Code Snippet
```php
// PAGGENERATE NG PHOTO URL NA MAY FALLBACK SA DICEBEAR
$photo_url = trim($row['photo_url'] ?? '');
$seed = urlencode(strtoupper($row['last_name']) . ' ' . strtoupper($row['first_name']));
$dicebear = 'https://api.dicebear.com/7.x/initials/svg?seed=' . $seed
    . '&backgroundColor=6366f1&fontFamily=Arial&fontSize=38&bold=true&fontColor=ffffff';

// PAGCHECK KUNG REAL ANG PHOTO O PLACEHOLDER LANG
$is_real_photo = !empty($photo_url)
    && strpos($photo_url, 'ui-avatars.com') === false
    && strpos($photo_url, 'ui-avatars.io') === false;

$photo_src = $is_real_photo
    ? ((strpos($photo_url, 'http') === 0) ? $photo_url : '../../' . $photo_url)
    : $dicebear;

// PAGCALCULATE NG PERCENTAGES BAWAT POSITION
foreach ($position_candidates as &$pos) {
    $total_votes = array_sum(array_column($pos['candidates'], 'votes'));
    foreach ($pos['candidates'] as &$c) {
        $c['percentage'] = $total_votes > 0 
            ? round(($c['votes'] / $total_votes) * 100, 1) 
            : 0;
    }
}
```

---

## 5. **student_dashboard.php** - Voting Interface Rendering
**File:** `student/student_dashboard.php` (Lines: 1-80)

### Layunin
Nag-render ng voting ballot interface na may lahat ng positions at candidates na hindi-organisado ng party list.

### Breakdown ng Complexity

#### Part 1: Election Status & Session Validation
- Nag-check kung ang election ay tunay na ongoing (hindi ended/hindi started)
- Nag-validate ng student session at voting eligibility
- Nag-implement ng session timeout protection

#### Part 2: Dynamic Candidate Organization
- Kumukuha ng candidates at partylists na may complex JOIN
- **Nested Data Structure:**
  - Positions → Candidates → Organized by Party List
- Nagseseparate ng "board" positions mula sa regular positions gamit ang string matching:
  ```php
  stripos($position['position_name'], 'board') !== false
  ```

#### Part 3: Party Name Detection
- Kumumuklot ng unique party list names mula sa candidates
- Nagdadagdag ng "Independent" sa party list kung may independent candidates
- Gumagamit ng `in_array()` upang maiwasan ang duplicates

#### Part 4: Error Handling
- Nakuha ang stored validation errors mula sa session
- Nagpapakita ng errors kung ang vote validation ay nabigo sa nakaraang submission

### Pangunahing Challenges
- Dapat mag-validate ng election status bago mag-render ng form
- Complex multi-level array transformations (position → candidate → party)
- String-based position type detection (walang dedicated position_type column sa student context)
- Session state ay dapat i-check sa multiple points (status, auth, timeout)
- Error recovery mula sa previous failed votes

### Code Snippet
```php
// PAGSPLIT NG POSITIONS SA BOARD AT REGULAR
$boardPositions = [];
$regularPositions = [];

foreach ($positions as $positionId => $position) {
    if (
        stripos($position['position_name'], 'board') !== false ||
        stripos($position['position_name'], 'representative') !== false
    ) {
        $boardPositions[$positionId] = $position;
    } else {
        $regularPositions[$positionId] = $position;
    }
}

// DYNAMIC NA PARTY FILTERING
$hasIndependent = false;
foreach ($positions as $pos) {
    foreach ($pos['candidates'] as $c) {
        if ($c['partylist'] === 'Independent') {
            $hasIndependent = true;
            break 2;
        }
    }
}
if ($hasIndependent && !in_array('Independent', $partyNames)) {
    $partyNames[] = 'Independent';
}
```

---

## 6. **review_votes.php** - Vote Validation & Transaction
**File:** `student/review_votes.php` (Lines: 1-50 + Final Submission)

### Layunin
Nag-validate ng lahat ng vote selections, nagpapakita ng review page, at nag-commit ng votes sa database gamit ang transactions.

### Breakdown ng Complexity

#### Part 1: Vote Validation
- **Naglolop sa lahat ng positions** upang masiguro na bawat position ay may candidate na napili
- **Nag-validate ng bawat selection** laban sa database:
  - Nag-check kung ang candidate ay nag-exist
  - Nag-verify na ang candidate ay nabibilang sa correct position
  - Kumukuha ng candidate details para sa display
- Formats names with proper capitalization and initials
- Builds avatar initials from first and last name

#### Part 2: Database Transaction (Final Submission)
```php
$conn->begin_transaction();
try {
    foreach ($selected_votes as $vote) {
        // PAGINSERT NG INDIVIDUAL VOTE RECORD
        $stmt = $conn->prepare("INSERT INTO votes (student_id, position_id, candidate_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $student_id, $vote['position_id'], $vote['candidate_id']);
        $stmt->execute();
    }
    // PAGMARK NG STUDENT BILANG NABOTAHAN NA
    $update = $conn->prepare("UPDATE students SET has_voted = 1 WHERE student_id = ?");
    $update->bind_param("s", $student_id);
    $update->execute();
    
    $_SESSION['has_voted'] = 1;
    $conn->commit();
    header("Location: vote_success.php");
} catch (Exception $e) {
    $conn->rollback();
    die("Error processing votes: " . $e->getMessage());
}
```

#### Part 3: Vote Grouping for Display
- Separates votes into "leaders" and "board members"
- Uses string matching to detect board positions
- Renders different review sections based on position type

### Key Challenges
- **ACID Compliance:** All votes must commit together or none at all
- **Validation at Review Time:** Database validation required for each vote to prevent race conditions
- **Formatting:** Multiple formatting steps para sa name display
- **Error Recovery:** Kung ang transaction ay nabigo, dapat mag-rollback ng lahat ng inserts
- **Session Consistency:** Dapat mag-update ng session variable AT database together

### Code Snippet
```php
// ACID TRANSACTION PARA SA MULTIPLE INSERTS
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
```

---

## 7. **superadmin_actions.php** - Admin Management
**File:** `superadmin/superadmin_actions.php` (Lines: 1-100+)

### Layunin
Nag-handle ng CRUD operations para sa admin accounts na may comprehensive validation at audit logging.

### Breakdown ng Complexity

#### Case 1: Create Admin
- **Validation:** 
  - Non-empty checks para sa name, username, password
  - Regex validation: `^[a-zA-Z0-9_]+$` (alphanumeric + underscore lang)
- **Duplicate Check:** Prepared statement query upang mag-verify ng username uniqueness
- **Password Hashing:** Gumagamit ng `PASSWORD_BCRYPT` para sa secure storage
- **Audit Trail:** Naglologue ng creation kasama ang admin name at new admin ID

#### Case 2: Edit Admin  
- **Duplicate Check:** Dapat mag-exclude ng current admin mula sa uniqueness check:
  ```sql
  SELECT id FROM administrators WHERE username = ? AND id != ?
  ```
- **Validation:** Parehong regex at non-empty checks
- **Audit Logging:** Nagrerecord ng kung ano ang fields na nabago

#### Case 3: Reset Password
- Nag-validate ng dalawang password fields ay tugma
- Nag-force ng strong password kung kailangan
- Nag-update ng password hash at nag-log ng action

### Pangunahing Challenges
- **Username Uniqueness:** Dapat mag-exclude ng self kapag nag-check ng duplicates during edit
- **Regex Validation:** Nagsisiguro ng safe username format
- **Audit Trail:** Bawat action ay dapat na ma-log kasama ang actor info
- **Prepared Statements:** Lahat ng inputs ay dapat na ma-parameterize upang maiwasan ang SQL injection

### Code Snippet
```php
case 'create_admin':
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // VALIDATION
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        redirect_error("Username can only contain letters, numbers, and underscores");
    }

    // PAGCHECK NG DUPLICATE
    $check = $conn->prepare("SELECT id FROM administrators WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        redirect_error("Username already exists");
    }

    // PAG-HASH AT PAG-INSERT
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO administrators (name, username, password, is_active, is_deleted) VALUES (?, ?, ?, 1, 0)");
    $stmt->bind_param("sss", $name, $username, $hashed);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        writeAuditLog($conn, 'superadmin', $sa_id, $sa_name, 'admin_created', 'admin', $new_id, "Created admin account: $username ($name)");
        redirect_success("Admin '$name' created successfully");
    }
    break;
```

---

## 8. **restart_election.php** - Database Transaction Reset
**File:** `administrator/actions/restart_election.php` (Complete file)

### Layunin
Atomically nag-reset ng lahat ng election data habang pinapanatili ang database integrity gamit ang transactions.

### Breakdown ng Complexity
- **Multi-table Transaction:** 
  - Tanggalin ang lahat ng boto
  - I-reset ang voting flags sa lahat ng students
  - I-clear ang winner flags sa candidates
  - I-update ang election status sa 'not_started'
- **ACID Principle:** Lahat ng operations ay dapat magtagumpay o lahat ay dapat mabigo
- **Error Handling:** Kung ang anumang query ay nabigo sa transaction, buong transaction ay nagba-rollback

### Pangunahing Challenges
- **Data Loss Risk:** Mga boto ay permanenteng tatanggalin (walang soft delete)
- **State Consistency:** Dapat mag-reset ng related flags sa multiple tables
- **Election Restart:** Nagbabalik ng system sa "not started" state

### Code Snippet
```php
try {
    $conn->begin_transaction();

    $conn->query("DELETE FROM votes");
    $conn->query("UPDATE students SET has_voted = 0");
    $conn->query("UPDATE candidates SET is_winner = 0");
    $conn->query("UPDATE election_settings SET setting_value = 'not_started' WHERE setting_key = 'election_status'");

    $conn->commit();

    header("Location: ../pages/admin_dashboard.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../pages/admin_dashboard.php");
    exit();
}
```

---

## 9. **statistics.php** (Superadmin) - Vote Tally Aggregation
**File:** `superadmin/pages/statistics.php` (Lines: 1-60)

### Layunin
Nagpapakita ng comprehensive election statistics kasama ang voter turnout, position tallies, at vote counts.

### Breakdown ng Complexity

#### Part 1: Statistics Calculation
- **Voter Turnout Calculation:**
  ```php
  $turnout = $total_students > 0 
    ? round(($voted_count / $total_students) * 100, 1) 
    : 0;
  ```
  Nag-handle ng division by zero case

#### Part 2: Vote Tally Aggregation
- **Complex Query:** Naggrupo ng votes by position at candidate
- **Nested Data Structure:**
  - Position → Candidates → Vote Counts
  - Nagtitipid ng track ng total votes per position
- Dapat magkalkula ng running totals habang ginagawa ang results

#### Part 3: Status Mapping
- Nagma-map ng election status string sa display labels, colors, at CSS classes
- Gumagamit ng color codes para sa visual indication

### Pangunahing Challenges
- **Null Handling:** LEFT JOINs ay maaaring magbalik ng null candidates para sa empty positions
- **Vote Totaling:** Dapat mag-accumulate ng per-position at per-candidate counts
- **Edge Cases:** Empty positions o zero votes scenarios

### Code Snippet
```php
// TALLY NG BOTO NA MAY RUNNING TOTALS
$r = $conn->query("
    SELECT 
        p.id, 
        p.position_name,
        c.id AS candidate_id, 
        CONCAT(c.last_name, ', ', c.first_name) AS candidate_name,
        COUNT(v.id) AS vote_count
    FROM positions p
    LEFT JOIN candidates c ON c.position_id = p.id
    LEFT JOIN votes v ON v.candidate_id = c.id
    GROUP BY p.id, c.id
    ORDER BY p.id, vote_count DESC
");

while ($row = $r->fetch_assoc()) {
    $pid = $row['id'];
    // PAGINITIALIZE NG POSITION TALLY KUNG HINDI PA
    if (!isset($position_tallies[$pid])) {
        $position_tallies[$pid] = [
            'name' => $row['candidate_name'],
            'candidates' => [],
            'total' => 0,
        ];
    }
    // PAGADD NG CANDIDATE VOTES
    if ($row['candidate_id']) {
        $position_tallies[$pid]['candidates'][] = [
            'name' => $row['candidate_name'],
            'votes' => (int)$row['vote_count'],
        ];
        $position_tallies[$pid]['total'] += (int)$row['vote_count'];
    }
}
```

---

## Summary Table

| Function | File | Complexity Level | Key Technique |
|----------|------|------------------|----------------|
| `authenticateStudent()` | `auth/login_auth.php` | [4/5] | Multi-path auth flow + session management |
| `authenticateSuperAdmin()` | `auth/login_auth.php` | [5/5] | Audit logging + security checks + multi-step |
| `fetch_results()` | `administrator/actions/fetch_results.php` | [5/5] | Complex SQL joins + nested data transformation |
| Admin Dashboard logic | `administrator/pages/admin_dashboard.php` | [5/5] | Multi-query aggregation + image handling |
| Vote organization logic | `student/student_dashboard.php` | [4/5] | Dynamic position grouping + state validation |
| Vote validation transaction | `student/review_votes.php` | [5/5] | ACID transactions + validation loop |
| Admin CRUD operations | `superadmin/superadmin_actions.php` | [4/5] | Validation + SQL injection prevention |
| Election reset transaction | `administrator/actions/restart_election.php` | [3/5] | Multi-table atomic operations |
| Statistics aggregation | `superadmin/pages/statistics.php` | [4/5] | Nested data grouping + calculations |

---

## Common Patterns Used Across Complex Functions

### 1. **Prepared Statements**
Lahat ng database queries ay gumagamit ng prepared statements na may `bind_param()` upang maiwasan ang SQL injection:
```php
$stmt = $conn->prepare("SELECT id FROM administrators WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
```

### 2. **Database Transactions**
Mahahalagang operasyon ay gumagamit ng `begin_transaction()`, `commit()`, at `rollback()`:
```php
$conn->begin_transaction();
// ... maraming operasyon ...
$conn->commit(); // o $conn->rollback() kung may error
```

### 3. **Audit Logging**
Gawain ng admin ay nakalogue gamit ang `writeAuditLog()` helper:
```php
writeAuditLog($conn, $actor_type, $actor_id, $actor_name, $action, $target_type, $target_id, $detail);
```

### 4. **Reference-based Loop Modification**
Ginamit para sa in-place array modifications:
```php
foreach ($data as &$item) {
    $item['calculated_field'] = calculateValue($item);
}
unset($item); // MAHALAGANG GAWIN UPANG MAIWASAN ANG REFERENCE LEAK
```

### 5. **Null Coalescing**
Silong na pag-handle ng null:
```php
$value = $row['optional_field'] ?? 'default';
COALESCE(pl.name, 'Independent') AS partylist
```

### 6. **Session Regeneration**
Mahusay na kasanayan sa security:
```php
session_regenerate_id(true); // true = tanggalin ang lumang session
```

---

## Performance Considerations

### Posibleng Optimizations

1. **Admin Dashboard:** Maraming queries ay maaaring magsama sa single query na may subqueries
2. **Vote Aggregation:** Isaalang-alang ang database-level calculations sa halip ng PHP loops
3. **Candidate Queries:** GROUP BY queries ay maaaring makabuti mula sa indexes sa `position_id`, `candidate_id`
4. **Statistics Page:** Position tally query ay tumatakbo O(n) iterations sa results

### Inirerekomendang Indexes
```sql
CREATE INDEX idx_candidates_position ON candidates(position_id);
CREATE INDEX idx_votes_candidate ON votes(candidate_id);
CREATE INDEX idx_votes_student ON votes(student_id);
CREATE INDEX idx_students_voted ON students(has_voted);
CREATE INDEX idx_admin_username ON administrators(username);
```

---

## Security Notes

Secured:
- Lahat ng database queries ay gumagamit ng prepared statements
- Passwords ay hash na may bcrypt (`PASSWORD_BCRYPT`)
- Session regeneration sa login
- Audit logging ng administrative actions
- Account status checks sa bawat page load

Monitoring Points:
- Siguraduhing ang `error_reporting` ay off sa production
- Ma-verify ang session timeout enforcement (kasalukuyang 1 hour)
- I-monitor ang audit logs para sa suspicious activities
- Mag-implement ng rate limiting sa login endpoints

