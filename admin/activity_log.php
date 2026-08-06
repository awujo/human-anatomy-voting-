<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

$logs = $pdo->query(
    'SELECT l.*, a.full_name AS admin_name FROM admin_activity_log l
     LEFT JOIN admins a ON a.id = l.admin_id
     ORDER BY l.created_at DESC LIMIT 200'
)->fetchAll();

$pageTitle = 'Activity Log';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-3">Activity Log</h3>
<p class="text-muted">Most recent 200 admin actions, including rigging entries.</p>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Time</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td><?php echo h($l['created_at']); ?></td>
            <td><?php echo h($l['admin_name'] ?? 'system'); ?></td>
            <td><span class="badge bg-secondary"><?php echo h($l['action']); ?></span></td>
            <td><?php echo h($l['details']); ?></td>
            <td class="text-muted small"><?php echo h($l['ip_address']); ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No activity yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
