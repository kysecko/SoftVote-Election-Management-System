<?php
session_start();
require '../../config_db.php';

// AUTH CHECKING
if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    exit();
}

header('Content-Type: application/json');

$position_candidates = [];
$all_partylists      = [];

// QUERY PARA SA PAGKUHA NG MGA KANDIDATO, KANILANG POSISYON, AT BILANG NG MGA BOTONG NATANGGAP
$sql = "
    SELECT 
        c.id           AS candidate_id,
        c.first_name,
        c.middle_name,
        c.last_name,
        pl.name        AS partylist,
        p.id           AS position_id,
        p.position_name,
        p.position_type,
        p.display_order,
        COUNT(v.id)    AS votes
    FROM candidates c
    JOIN positions p        ON c.position_id  = p.id
    LEFT JOIN partylists pl ON pl.id          = c.partylist_id
    LEFT JOIN votes v       ON v.candidate_id = c.id
    GROUP BY 
        c.id,
        c.first_name,
        c.middle_name,
        c.last_name,
        pl.name,
        p.id,
        p.position_name,
        p.position_type,
        p.display_order
    ORDER BY p.display_order ASC, p.position_name ASC
";

$res = $conn->query($sql);

if (!$res) {
    echo json_encode(['error' => $conn->error]);
    exit();
}

while ($row = $res->fetch_assoc()) {
    $pid       = $row['position_id'];
    $partylist = trim($row['partylist'] ?? '');

    if (!isset($position_candidates[$pid])) {
        $position_candidates[$pid] = [
            'position_name' => $row['position_name'],
            'position_type' => strtolower($row['position_type']),
            'display_order' => $row['display_order'],
            'candidates'    => []
        ];
    }

    $full_name = trim(
        ($row['last_name']  ?? '') . ', ' .
        ($row['first_name'] ?? '') . ' ' .
        (!empty($row['middle_name']) ? strtoupper($row['middle_name'][0]) . '.' : '')
    );

    $position_candidates[$pid]['candidates'][] = [
        'candidate_id' => (int) $row['candidate_id'],
        'name'         => $full_name,
        'votes'        => (int) $row['votes'],
        'partylist'    => $partylist,
        'percentage'   => 0,
    ];

    if ($partylist !== '' && !in_array($partylist, $all_partylists)) {
        $all_partylists[] = $partylist;
    }
}

foreach ($position_candidates as &$pos) {
    $total_votes = array_sum(array_column($pos['candidates'], 'votes'));
    foreach ($pos['candidates'] as &$c) {
        $c['percentage'] = $total_votes > 0
            ? round(($c['votes'] / $total_votes) * 100, 1)
            : 0;
    }
}
unset($pos, $c);

echo json_encode([
    'positions'  => $position_candidates,
    'partylists' => $all_partylists,
]);