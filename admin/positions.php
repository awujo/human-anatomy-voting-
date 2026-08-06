<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $order       = (int) ($_POST['display_order'] ?? 0);

        if ($title === '') {
            flash_set('error', 'Position title is required.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO positions (title, description, display_order) VALUES (?, ?, ?)');
            $stmt->execute([$title, $description ?: null, $order]);
            log_admin_activity($pdo, $_SESSION['admin_id'], 'create_position', $title);
            flash_set('success', 'Position "' . $title . '" created.');
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE positions SET status = IF(status = "open", "closed", "open") WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'toggle_position_status', 'position #' . $id);
        flash_set('success', 'Position status updated.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM positions WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'delete_position', 'position #' . $id);
        flash_set('success', 'Position deleted.');
    }

    redirect(BASE_URL . '/admin/positions.php');
}

$positions = $pdo->query('SELECT * FROM positions ORDER BY display_order, id')->fetchAll();

$pageTitle = 'Positions';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Positions</h3>
</div>

<div class="card mb-4">
  <div class="card-header">Create a new position</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="create">
      <div class="col-md-4">
        <input type="text" name="title" class="form-control" placeholder="e.g. President" required>
      </div>
      <div class="col-md-5">
        <input type="text" name="description" class="form-control" placeholder="Description (optional)">
      </div>
      <div class="col-md-2">
        <input type="number" name="display_order" class="form-control" placeholder="Order" value="0">
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary w-100" type="submit">Add</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Title</th>
          <th>Description</th>
          <th>Order</th>
          <th>Status</th>
          <th>Candidates</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($positions as $p): ?>
          <?php
            $count = $pdo->prepare('SELECT COUNT(*) FROM candidates WHERE position_id = ?');
            $count->execute([$p['id']]);
            $candidateCount = $count->fetchColumn();
          ?>
          <tr>
            <td><?php echo (int) $p['id']; ?></td>
            <td><?php echo h($p['title']); ?></td>
            <td><?php echo h($p['description']); ?></td>
            <td><?php echo (int) $p['display_order']; ?></td>
            <td>
              <span class="badge bg-<?php echo $p['status'] === 'open' ? 'success' : 'secondary'; ?>">
                <?php echo h($p['status']); ?>
              </span>
            </td>
            <td><?php echo (int) $candidateCount; ?></td>
            <td class="text-end">
              <form method="post" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                <button class="btn btn-sm btn-outline-secondary" type="submit">
                  <?php echo $p['status'] === 'open' ? 'Close' : 'Reopen'; ?>
                </button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this position and all its candidates/votes?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$positions): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No positions yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
