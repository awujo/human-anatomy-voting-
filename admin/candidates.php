<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

$uploadDir    = __DIR__ . '/../uploads/candidates';
$publicPrefix = '/uploads/candidates';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id          = (int) ($_POST['id'] ?? 0);
        $positionId  = (int) ($_POST['position_id'] ?? 0);
        $matricNo    = trim($_POST['matric_no'] ?? '') ?: null;
        $fullName    = trim($_POST['full_name'] ?? '');
        $department  = trim($_POST['department'] ?? '') ?: null;
        $level       = trim($_POST['level'] ?? '') ?: null;
        $manifesto   = trim($_POST['manifesto'] ?? '') ?: null;

        if ($fullName === '' || $positionId <= 0) {
            flash_set('error', 'Full name and position are required.');
            redirect(BASE_URL . '/admin/candidates.php');
        }

        try {
            $photoPath = handle_photo_upload('photo', $uploadDir, $publicPrefix);
        } catch (RuntimeException $e) {
            flash_set('error', $e->getMessage());
            redirect(BASE_URL . '/admin/candidates.php');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO candidates (position_id, matric_no, full_name, photo, department, level, manifesto)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$positionId, $matricNo, $fullName, $photoPath, $department, $level, $manifesto]);
            log_admin_activity($pdo, $_SESSION['admin_id'], 'create_candidate', $fullName);
            flash_set('success', 'Candidate "' . $fullName . '" registered.');
        } else {
            if ($photoPath) {
                $stmt = $pdo->prepare(
                    'UPDATE candidates SET position_id=?, matric_no=?, full_name=?, photo=?, department=?, level=?, manifesto=? WHERE id=?'
                );
                $stmt->execute([$positionId, $matricNo, $fullName, $photoPath, $department, $level, $manifesto, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE candidates SET position_id=?, matric_no=?, full_name=?, department=?, level=?, manifesto=? WHERE id=?'
                );
                $stmt->execute([$positionId, $matricNo, $fullName, $department, $level, $manifesto, $id]);
            }
            log_admin_activity($pdo, $_SESSION['admin_id'], 'update_candidate', 'candidate #' . $id);
            flash_set('success', 'Candidate updated.');
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE candidates SET status = IF(status = "active", "disqualified", "active") WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'toggle_candidate_status', 'candidate #' . $id);
        flash_set('success', 'Candidate status updated.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM candidates WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'delete_candidate', 'candidate #' . $id);
        flash_set('success', 'Candidate deleted.');
    }

    redirect(BASE_URL . '/admin/candidates.php');
}

$positions = $pdo->query('SELECT * FROM positions ORDER BY display_order, id')->fetchAll();

$editCandidate = null;
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM candidates WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editCandidate = $stmt->fetch();
}

$filterPosition = (int) ($_GET['position_id'] ?? 0);
if ($filterPosition) {
    $stmt = $pdo->prepare(
        'SELECT c.*, p.title AS position_title FROM candidates c
         JOIN positions p ON p.id = c.position_id
         WHERE c.position_id = ? ORDER BY p.display_order, c.full_name'
    );
    $stmt->execute([$filterPosition]);
} else {
    $stmt = $pdo->query(
        'SELECT c.*, p.title AS position_title FROM candidates c
         JOIN positions p ON p.id = c.position_id
         ORDER BY p.display_order, c.full_name'
    );
}
$candidates = $stmt->fetchAll();

$pageTitle = 'Candidates';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-3">Candidates</h3>

<div class="card mb-4">
  <div class="card-header"><?php echo $editCandidate ? 'Edit Candidate' : 'Register a new candidate'; ?></div>
  <div class="card-body">
    <?php if (!$positions): ?>
      <div class="alert alert-warning mb-0">Create a position first before adding candidates.</div>
    <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="row g-2">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="<?php echo $editCandidate ? 'update' : 'create'; ?>">
      <?php if ($editCandidate): ?>
        <input type="hidden" name="id" value="<?php echo (int) $editCandidate['id']; ?>">
      <?php endif; ?>

      <div class="col-md-3">
        <label class="form-label">Position</label>
        <select name="position_id" class="form-select" required>
          <option value="">-- select --</option>
          <?php foreach ($positions as $p): ?>
            <option value="<?php echo (int) $p['id']; ?>"
              <?php echo ($editCandidate && $editCandidate['position_id'] == $p['id']) ? 'selected' : ''; ?>>
              <?php echo h($p['title']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Full name</label>
        <input type="text" name="full_name" class="form-control" required
               value="<?php echo h($editCandidate['full_name'] ?? ''); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Matric no (optional)</label>
        <input type="text" name="matric_no" class="form-control"
               value="<?php echo h($editCandidate['matric_no'] ?? ''); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Department</label>
        <input type="text" name="department" class="form-control"
               value="<?php echo h($editCandidate['department'] ?? ''); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Level</label>
        <input type="text" name="level" class="form-control" placeholder="e.g. 200L"
               value="<?php echo h($editCandidate['level'] ?? ''); ?>">
      </div>

      <div class="col-md-8">
        <label class="form-label">Manifesto (optional)</label>
        <textarea name="manifesto" class="form-control" rows="2"><?php echo h($editCandidate['manifesto'] ?? ''); ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Photo <?php echo $editCandidate ? '(leave empty to keep current)' : ''; ?></label>
        <input type="file" name="photo" class="form-control" accept="image/png,image/jpeg,image/webp">
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary"><?php echo $editCandidate ? 'Save Changes' : 'Add Candidate'; ?></button>
        <?php if ($editCandidate): ?>
          <a href="candidates.php" class="btn btn-outline-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<form method="get" class="row g-2 mb-3">
  <div class="col-md-3">
    <select name="position_id" class="form-select" onchange="this.form.submit()">
      <option value="0">All positions</option>
      <?php foreach ($positions as $p): ?>
        <option value="<?php echo (int) $p['id']; ?>" <?php echo $filterPosition === (int) $p['id'] ? 'selected' : ''; ?>>
          <?php echo h($p['title']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<div class="row g-3">
  <?php foreach ($candidates as $c): ?>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body d-flex gap-3">
          <img class="candidate-photo" src="<?php echo $c['photo'] ? h(BASE_URL . $c['photo']) : 'https://via.placeholder.com/96?text=No+Photo'; ?>" alt="">
          <div>
            <h6 class="mb-1"><?php echo h($c['full_name']); ?></h6>
            <div class="text-muted small mb-1"><?php echo h($c['position_title']); ?></div>
            <?php if ($c['matric_no']): ?><div class="small">Matric: <?php echo h($c['matric_no']); ?></div><?php endif; ?>
            <span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?>">
              <?php echo h($c['status']); ?>
            </span>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
          <a href="?edit=<?php echo (int) $c['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
          <form method="post" class="d-inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit">
              <?php echo $c['status'] === 'active' ? 'Disqualify' : 'Reactivate'; ?>
            </button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this candidate and all their votes?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$candidates): ?>
    <div class="col-12 text-center text-muted py-4">No candidates yet.</div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
