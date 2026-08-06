<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

$stats = [
    'positions'   => $pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn(),
    'candidates'  => $pdo->query('SELECT COUNT(*) FROM candidates')->fetchColumn(),
    'voters'      => $pdo->query('SELECT COUNT(*) FROM voters')->fetchColumn(),
    'real_votes'  => $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
    'rigged'      => $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM rigged_votes')->fetchColumn(),
];

$settings = $pdo->query('SELECT * FROM election_settings ORDER BY id DESC LIMIT 1')->fetch();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-4">Dashboard</h3>

<?php if ($settings): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center">
  <div>
    <strong><?php echo h($settings['title']); ?></strong> —
    status: <span class="badge bg-secondary text-uppercase"><?php echo h($settings['status']); ?></span>,
    live results: <?php echo $settings['show_live_results'] ? 'ON' : 'OFF'; ?>
  </div>
  <a href="settings.php" class="btn btn-sm btn-outline-primary">Edit Settings</a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-2 col-6">
    <div class="card text-center"><div class="card-body">
      <div class="display-6"><?php echo (int) $stats['positions']; ?></div>
      <div class="text-muted small">Positions</div>
    </div></div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card text-center"><div class="card-body">
      <div class="display-6"><?php echo (int) $stats['candidates']; ?></div>
      <div class="text-muted small">Candidates</div>
    </div></div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card text-center"><div class="card-body">
      <div class="display-6"><?php echo (int) $stats['voters']; ?></div>
      <div class="text-muted small">Registered Voters</div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card text-center"><div class="card-body">
      <div class="display-6"><?php echo (int) $stats['real_votes']; ?></div>
      <div class="text-muted small">Real Ballots Cast</div>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card text-center border-warning"><div class="card-body">
      <div class="display-6 text-warning"><?php echo (int) $stats['rigged']; ?></div>
      <div class="text-muted small">Rigged Adjustment (net)</div>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <a href="positions.php" class="btn btn-outline-dark w-100 py-3">Manage Positions</a>
  </div>
  <div class="col-md-3">
    <a href="candidates.php" class="btn btn-outline-dark w-100 py-3">Manage Candidates</a>
  </div>
  <div class="col-md-3">
    <a href="voters.php" class="btn btn-outline-dark w-100 py-3">Manage Voters</a>
  </div>
  <div class="col-md-3">
    <a href="rigging.php" class="btn btn-outline-warning w-100 py-3">Rigging Panel</a>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
