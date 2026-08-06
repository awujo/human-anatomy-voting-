<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['voter_id'])) {
    redirect(BASE_URL . '/vote/ballot.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $matricNo = trim($_POST['matric_no'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM voters WHERE matric_no = ? LIMIT 1');
    $stmt->execute([$matricNo]);
    $voter = $stmt->fetch();

    if (!$voter) {
        flash_set('error', 'That matric number is not registered to vote. Contact the election admin.');
        redirect(BASE_URL . '/vote/login.php');
    }

    if ($voter['status'] !== 'eligible') {
        flash_set('error', 'This voter account has been blocked. Contact the election admin.');
        redirect(BASE_URL . '/vote/login.php');
    }

    session_regenerate_id(true);
    $_SESSION['voter_id']     = $voter['id'];
    $_SESSION['voter_matric'] = $voter['matric_no'];
    $_SESSION['voter_name']   = $voter['full_name'];
    redirect(BASE_URL . '/vote/ballot.php');
}

$pageTitle = 'Voter Login';
require __DIR__ . '/../includes/site_header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Voter Login</h4>
        <p class="text-muted">Enter your matric number to vote.</p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label">Matric Number</label>
            <input type="text" name="matric_no" class="form-control" placeholder="e.g. 22/0313" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary w-100">Log In &amp; Vote</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/site_footer.php'; ?>
