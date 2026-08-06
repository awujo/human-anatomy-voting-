<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_admin.php';

$settings = $pdo->query('SELECT * FROM election_settings ORDER BY id DESC LIMIT 1')->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title            = trim($_POST['title'] ?? '');
    $startDatetime    = $_POST['start_datetime'] ?: null;
    $endDatetime      = $_POST['end_datetime'] ?: null;
    $status           = $_POST['status'] ?? 'upcoming';
    $showLiveResults  = isset($_POST['show_live_results']) ? 1 : 0;

    if ($settings) {
        $stmt = $pdo->prepare(
            'UPDATE election_settings SET title=?, start_datetime=?, end_datetime=?, status=?, show_live_results=? WHERE id=?'
        );
        $stmt->execute([$title, $startDatetime, $endDatetime, $status, $showLiveResults, $settings['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO election_settings (title, start_datetime, end_datetime, status, show_live_results) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $startDatetime, $endDatetime, $status, $showLiveResults]);
    }

    log_admin_activity($pdo, $_SESSION['admin_id'], 'update_settings', $title);
    flash_set('success', 'Election settings saved.');
    redirect(BASE_URL . '/admin/settings.php');
}

$pageTitle = 'Settings';
require __DIR__ . '/../includes/admin_header.php';
?>

<h3 class="mb-3">Election Settings</h3>

<div class="card" style="max-width:600px;">
  <div class="card-body">
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="mb-3">
        <label class="form-label">Election title</label>
        <input type="text" name="title" class="form-control" required
               value="<?php echo h($settings['title'] ?? 'Departmental Election'); ?>">
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Start</label>
          <input type="datetime-local" name="start_datetime" class="form-control"
                 value="<?php echo $settings && $settings['start_datetime'] ? h(str_replace(' ', 'T', $settings['start_datetime'])) : ''; ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">End</label>
          <input type="datetime-local" name="end_datetime" class="form-control"
                 value="<?php echo $settings && $settings['end_datetime'] ? h(str_replace(' ', 'T', $settings['end_datetime'])) : ''; ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['upcoming', 'ongoing', 'ended'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo ($settings['status'] ?? '') === $s ? 'selected' : ''; ?>>
              <?php echo ucfirst($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="show_live_results" id="liveResults"
               <?php echo !$settings || $settings['show_live_results'] ? 'checked' : ''; ?>>
        <label class="form-check-label" for="liveResults">Show live results publicly</label>
      </div>
      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
