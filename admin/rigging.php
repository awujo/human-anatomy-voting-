<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_rigged_votes') {
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);
        $quantity    = (int) ($_POST['quantity'] ?? 0);
        $reason      = trim($_POST['reason'] ?? '') ?: null;

        $cand = $pdo->prepare('SELECT * FROM candidates WHERE id = ?');
        $cand->execute([$candidateId]);
        $candidate = $cand->fetch();

        if (!$candidate || $quantity === 0) {
            flash_set('error', 'Choose a candidate and a non-zero quantity.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO rigged_votes (candidate_id, position_id, admin_id, quantity, reason) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$candidateId, $candidate['position_id'], $_SESSION['admin_id'], $quantity, $reason]);
            log_admin_activity(
                $pdo,
                $_SESSION['admin_id'],
                'rig_votes',
                sprintf('%+d votes for candidate #%d (%s)%s', $quantity, $candidateId, $candidate['full_name'], $reason ? " — {$reason}" : '')
            );
            flash_set('success', sprintf('%+d votes applied to %s.', $quantity, $candidate['full_name']));
        }
        redirect(BASE_URL . '/admin/rigging.php');
    }

    if ($action === 'delete_entry') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM rigged_votes WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity($pdo, $_SESSION['admin_id'], 'undo_rig_entry', 'rigged_votes #' . $id);
        flash_set('success', 'Rigging entry removed.');
        redirect(BASE_URL . '/admin/rigging.php');
    }
}

$candidates = $pdo->query(
    'SELECT c.id, c.full_name, p.title AS position_title FROM candidates c
     JOIN positions p ON p.id = c.position_id
     WHERE c.status = "active"
     ORDER BY p.display_order, c.full_name'
)->fetchAll();

$history = $pdo->query(
    'SELECT rv.*, c.full_name AS candidate_name, p.title AS position_title, a.full_name AS admin_name
     FROM rigged_votes rv
     JOIN candidates c ON c.id = rv.candidate_id
     JOIN positions p ON p.id = rv.position_id
     JOIN admins a ON a.id = rv.admin_id
     ORDER BY rv.created_at DESC'
)->fetchAll();

$pageTitle = 'Rigging';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-3 text-warning">Rigging Panel</h3>
<p class="text-muted">
  Adjustments made here are added on top of genuine votes and shown separately in the audit log below.
  Use a negative quantity to remove votes from a candidate.
</p>

<div class="card mb-4 border-warning">
  <div class="card-header bg-warning-subtle">Add / remove votes</div>
  <div class="card-body">
    <form method="post" class="row g-2">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="add_rigged_votes">
      <div class="col-md-5">
        <select name="candidate_id" class="form-select" required>
          <option value="">-- select candidate --</option>
          <?php foreach ($candidates as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>">
              <?php echo h($c['position_title'] . ' — ' . $c['full_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <input type="number" name="quantity" class="form-control" placeholder="e.g. 50 or -20" required>
      </div>
      <div class="col-md-4">
        <input type="text" name="reason" class="form-control" placeholder="Reason / note (optional)">
      </div>
      <div class="col-md-1">
        <button class="btn btn-warning w-100" type="submit">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Rigging history</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Time</th><th>Position</th><th>Candidate</th><th>Qty</th><th>Reason</th><th>By</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $r): ?>
          <tr>
            <td><?php echo h($r['created_at']); ?></td>
            <td><?php echo h($r['position_title']); ?></td>
            <td><?php echo h($r['candidate_name']); ?></td>
            <td class="fw-bold <?php echo $r['quantity'] < 0 ? 'text-danger' : 'text-success'; ?>">
              <?php echo sprintf('%+d', $r['quantity']); ?>
            </td>
            <td><?php echo h($r['reason']); ?></td>
            <td><?php echo h($r['admin_name']); ?></td>
            <td class="text-end">
              <form method="post" onsubmit="return confirm('Remove this rigging entry?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_entry">
                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                <button class="btn btn-sm btn-outline-danger" type="submit">Undo</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No rigging activity yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
