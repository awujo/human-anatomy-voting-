<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

$importResults = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_single') {
        $matricNo   = trim($_POST['matric_no'] ?? '');
        $fullName   = trim($_POST['full_name'] ?? '');
        $department = trim($_POST['department'] ?? '') ?: null;
        $level      = trim($_POST['level'] ?? '') ?: null;
        $email      = trim($_POST['email'] ?? '') ?: null;
        $phone      = trim($_POST['phone'] ?? '') ?: null;

        if ($matricNo === '' || $fullName === '') {
            flash_set('error', 'Matric number and full name are required.');
        } else {
            $pin = generate_pin();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO voters (matric_no, full_name, department, level, email, phone, pin_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$matricNo, $fullName, $department, $level, $email, $phone, password_hash($pin, PASSWORD_BCRYPT)]);
                log_admin_activity($pdo, $_SESSION['admin_id'], 'add_voter', $matricNo);
                flash_set('success', "Voter {$matricNo} added. PIN: {$pin} — record this now, it will not be shown again.");
            } catch (PDOException $e) {
                flash_set('error', 'Could not add voter (matric number may already exist).');
            }
        }
        redirect(BASE_URL . '/admin/voters.php');
    }

    if ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE voters SET status = IF(status = "eligible", "blocked", "eligible") WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'toggle_voter_status', 'voter #' . $id);
        flash_set('success', 'Voter status updated.');
        redirect(BASE_URL . '/admin/voters.php');
    }

    if ($action === 'reset_pin') {
        $id  = (int) ($_POST['id'] ?? 0);
        $pin = generate_pin();
        $stmt = $pdo->prepare('UPDATE voters SET pin_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($pin, PASSWORD_BCRYPT), $id]);
        $matric = $pdo->prepare('SELECT matric_no FROM voters WHERE id = ?');
        $matric->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'reset_voter_pin', 'voter #' . $id);
        flash_set('success', 'New PIN for ' . $matric->fetchColumn() . ": {$pin} — record this now, it will not be shown again.");
        redirect(BASE_URL . '/admin/voters.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM voters WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'delete_voter', 'voter #' . $id);
        flash_set('success', 'Voter deleted.');
        redirect(BASE_URL . '/admin/voters.php');
    }

    if ($action === 'import_csv') {
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash_set('error', 'Please choose a CSV file to upload.');
            redirect(BASE_URL . '/admin/voters.php');
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $importResults = [];
        $rowNum = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($rowNum === 1 && stripos($row[0] ?? '', 'matric') !== false) {
                continue; // skip header row
            }
            $matricNo   = trim($row[0] ?? '');
            $fullName   = trim($row[1] ?? '');
            $department = trim($row[2] ?? '') ?: null;
            $level      = trim($row[3] ?? '') ?: null;

            if ($matricNo === '' || $fullName === '') {
                continue;
            }

            $pin = generate_pin();
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO voters (matric_no, full_name, department, level, pin_hash) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$matricNo, $fullName, $department, $level, password_hash($pin, PASSWORD_BCRYPT)]);
                $importResults[] = ['matric_no' => $matricNo, 'full_name' => $fullName, 'pin' => $pin, 'status' => 'added'];
            } catch (PDOException $e) {
                $importResults[] = ['matric_no' => $matricNo, 'full_name' => $fullName, 'pin' => '-', 'status' => 'skipped (duplicate)'];
            }
        }
        fclose($handle);

        log_admin_activity($pdo, $_SESSION['admin_id'], 'import_voters', count($importResults) . ' rows processed');
        flash_set('success', 'Import finished. PINs are shown below — record them now, they will not be shown again.');
    }
}

$voters = $pdo->query('SELECT * FROM voters ORDER BY id DESC')->fetchAll();

$pageTitle = 'Voters';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-3">Voters</h3>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">Add a single voter</div>
      <div class="card-body">
        <form method="post" class="row g-2">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add_single">
          <div class="col-6"><input type="text" name="matric_no" class="form-control" placeholder="Matric No (e.g. 22/0313)" required></div>
          <div class="col-6"><input type="text" name="full_name" class="form-control" placeholder="Full name" required></div>
          <div class="col-6"><input type="text" name="department" class="form-control" placeholder="Department"></div>
          <div class="col-6"><input type="text" name="level" class="form-control" placeholder="Level e.g. 200L"></div>
          <div class="col-6"><input type="email" name="email" class="form-control" placeholder="Email (optional)"></div>
          <div class="col-6"><input type="text" name="phone" class="form-control" placeholder="Phone (optional)"></div>
          <div class="col-12"><button class="btn btn-primary" type="submit">Add Voter &amp; Generate PIN</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">Bulk import (CSV)</div>
      <div class="card-body">
        <p class="text-muted small">Columns: <code>matric_no, full_name, department, level</code>. First row may be a header.</p>
        <form method="post" enctype="multipart/form-data" class="row g-2">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="import_csv">
          <div class="col-12"><input type="file" name="csv_file" accept=".csv" class="form-control" required></div>
          <div class="col-12"><button class="btn btn-outline-primary" type="submit">Import</button></div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($importResults): ?>
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning-subtle">Import results — save these PINs now</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th>Matric No</th><th>Name</th><th>PIN</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($importResults as $r): ?>
          <tr>
            <td><?php echo h($r['matric_no']); ?></td>
            <td><?php echo h($r['full_name']); ?></td>
            <td class="pin-code"><?php echo h($r['pin']); ?></td>
            <td><?php echo h($r['status']); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Matric No</th><th>Name</th><th>Dept</th><th>Level</th><th>Status</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($voters as $v): ?>
          <tr>
            <td><?php echo h($v['matric_no']); ?></td>
            <td><?php echo h($v['full_name']); ?></td>
            <td><?php echo h($v['department']); ?></td>
            <td><?php echo h($v['level']); ?></td>
            <td><span class="badge bg-<?php echo $v['status'] === 'eligible' ? 'success' : 'secondary'; ?>"><?php echo h($v['status']); ?></span></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_pin">
                <input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit" onclick="return confirm('Generate a new PIN for this voter?');">Reset PIN</button>
              </form>
              <form method="post" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">
                  <?php echo $v['status'] === 'eligible' ? 'Block' : 'Unblock'; ?>
                </button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this voter and their votes?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$voters): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No voters yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
