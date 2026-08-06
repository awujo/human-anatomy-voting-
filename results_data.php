<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json');

$settings = $pdo->query('SELECT * FROM election_settings ORDER BY id DESC LIMIT 1')->fetch();

if ($settings && !$settings['show_live_results']) {
    echo json_encode(['visible' => false]);
    exit;
}

$rows = $pdo->query(
    'SELECT * FROM election_results ORDER BY position_title, total_votes DESC, candidate_name'
)->fetchAll();

$positions = [];
foreach ($rows as $r) {
    $positions[$r['position_title']][] = [
        'candidate_id'   => (int) $r['candidate_id'],
        'candidate_name' => $r['candidate_name'],
        'candidate_photo'=> $r['candidate_photo'],
        'total_votes'    => (int) $r['total_votes'],
    ];
}

echo json_encode([
    'visible'    => true,
    'title'      => $settings['title'] ?? 'Election Results',
    'status'     => $settings['status'] ?? 'ongoing',
    'positions'  => $positions,
    'updated_at' => date('Y-m-d H:i:s'),
]);
