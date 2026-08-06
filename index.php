<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$settings = $pdo->query('SELECT * FROM election_settings ORDER BY id DESC LIMIT 1')->fetch();

$pageTitle = $settings['title'] ?? 'Departmental Election';
require __DIR__ . '/includes/site_header.php';
?>

<div class="text-center py-5">
  <h1 class="mb-3"><?php echo h($settings['title'] ?? 'Departmental Election'); ?></h1>
  <p class="text-muted mb-4">
    Status: <span class="badge bg-secondary text-uppercase"><?php echo h($settings['status'] ?? 'upcoming'); ?></span>
  </p>
  <div class="d-flex justify-content-center gap-3">
    <a href="<?php echo BASE_URL; ?>/vote/login.php" class="btn btn-primary btn-lg">Vote Now</a>
    <a href="<?php echo BASE_URL; ?>/results.php" class="btn btn-outline-primary btn-lg">View Live Results</a>
  </div>
  <p class="mt-5">
    <a class="text-muted small" href="<?php echo BASE_URL; ?>/admin/login.php">Admin Login</a>
  </p>
</div>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
