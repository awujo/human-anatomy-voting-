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
    $pin      = trim($_POST['pin'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM voters WHERE matric_no = ? LIMIT 1');
    $stmt->execute([$matricNo]);
    $voter = $stmt->fetch();

    if (!$voter || !$voter['pin_hash'] || !password_verify($pin, $voter['pin_hash'])) {
        flash_set('error', 'Invalid matric number or PIN.');
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
        <p class="text-muted">Log in with your matric number and the PIN given to you by the election committee.</p>
        <form method="post">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label">Matric Number</label>
            <input type="text" name="matric_no" class="form-control" placeholder="e.g. 22/0313" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">PIN</label>
            <input type="password" name="pin" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary w-100">Log In &amp; Vote</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/site_footer.php'; ?>
