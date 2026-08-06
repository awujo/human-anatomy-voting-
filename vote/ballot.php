<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_voter.php';

$voterId = (int) $_SESSION['voter_id'];

$settings = $pdo->query('SELECT * FROM election_settings ORDER BY id DESC LIMIT 1')->fetch();

if ($settings && $settings['status'] !== 'ongoing') {
    $pageTitle = 'Voting Closed';
    require __DIR__ . '/../includes/site_header.php';
    echo '<div class="alert alert-warning">Voting is currently <strong>' . h($settings['status']) . '</strong>. Please check back when the election is open.</div>';
    require __DIR__ . '/../includes/site_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $choices = $_POST['vote'] ?? []; // [position_id => candidate_id]
    $cast = 0;

    $pdo->beginTransaction();
    try {
        foreach ($choices as $positionId => $candidateId) {
            $positionId  = (int) $positionId;
            $candidateId = (int) $candidateId;
            if ($positionId <= 0 || $candidateId <= 0) {
                continue;
            }

            // Candidate must belong to the position and be active; position must be open.
            $check = $pdo->prepare(
                'SELECT c.id FROM candidates c
                 JOIN positions p ON p.id = c.position_id
                 WHERE c.id = ? AND c.position_id = ? AND c.status = "active" AND p.status = "open"'
            );
            $check->execute([$candidateId, $positionId]);
            if (!$check->fetch()) {
                continue;
            }

            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO votes (voter_id, position_id, candidate_id, ip_address) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$voterId, $positionId, $candidateId, $_SERVER['REMOTE_ADDR'] ?? null]);
            if ($stmt->rowCount() > 0) {
                $cast++;
            }
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash_set('error', 'Something went wrong while recording your vote. Please try again.');
        redirect(BASE_URL . '/vote/ballot.php');
    }

    if ($cast > 0) {
        flash_set('success', 'Your vote' . ($cast > 1 ? 's have' : ' has') . ' been recorded. Thank you!');
    } else {
        flash_set('error', 'No new votes were recorded — you may have already voted for the selected position(s).');
    }
    redirect(BASE_URL . '/vote/ballot.php');
}

// Positions still open that this voter has not yet voted in.
$stmt = $pdo->prepare(
    'SELECT p.* FROM positions p
     WHERE p.status = "open"
       AND p.id NOT IN (SELECT position_id FROM votes WHERE voter_id = ?)
     ORDER BY p.display_order, p.id'
);
$stmt->execute([$voterId]);
$openPositions = $stmt->fetchAll();

$candidatesByPosition = [];
foreach ($openPositions as $p) {
    $c = $pdo->prepare('SELECT * FROM candidates WHERE position_id = ? AND status = "active" ORDER BY full_name');
    $c->execute([$p['id']]);
    $candidatesByPosition[$p['id']] = $c->fetchAll();
}

$votedCount = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE voter_id = ?');
$votedCount->execute([$voterId]);
$votedCount = (int) $votedCount->fetchColumn();

$pageTitle = 'Cast Your Vote';
require __DIR__ . '/../includes/site_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Welcome, <?php echo h($_SESSION['voter_name']); ?> (<?php echo h($_SESSION['voter_matric']); ?>)</h4>
  <a href="<?php echo BASE_URL; ?>/vote/logout.php" class="btn btn-sm btn-outline-secondary">Logout</a>
</div>

<?php if (!$openPositions): ?>
  <div class="alert alert-success">
    You have cast your vote for every available position. Thank you for participating!
  </div>
  <a href="<?php echo BASE_URL; ?>/results.php" class="btn btn-primary">View Live Results</a>
<?php else: ?>
  <form method="post">
    <?php echo csrf_field(); ?>
    <?php foreach ($openPositions as $p): ?>
      <div class="card mb-4">
        <div class="card-header"><strong><?php echo h($p['title']); ?></strong>
          <?php if ($p['description']): ?><div class="small text-muted"><?php echo h($p['description']); ?></div><?php endif; ?>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($candidatesByPosition[$p['id']] as $c): ?>
              <?php $radioId = 'cand_' . $c['id']; ?>
              <div class="col-md-4">
                <input type="radio" class="btn-check" name="vote[<?php echo (int) $p['id']; ?>]"
                       id="<?php echo $radioId; ?>" value="<?php echo (int) $c['id']; ?>" required>
                <label class="w-100 candidate-card" for="<?php echo $radioId; ?>">
                  <div class="card candidate-card-inner h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                      <img class="candidate-photo" src="<?php echo $c['photo'] ? h(BASE_URL . $c['photo']) : 'https://via.placeholder.com/96?text=No+Photo'; ?>" alt="">
                      <div>
                        <div class="fw-semibold"><?php echo h($c['full_name']); ?></div>
                        <?php if ($c['department']): ?><div class="small text-muted"><?php echo h($c['department']); ?></div><?php endif; ?>
                      </div>
                    </div>
                  </div>
                </label>
              </div>
            <?php endforeach; ?>
            <?php if (!$candidatesByPosition[$p['id']]): ?>
              <div class="text-muted">No candidates registered for this position yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary btn-lg">Submit My Vote</button>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../includes/site_footer.php'; ?>
