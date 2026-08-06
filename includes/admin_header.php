<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$navItems = [
    'index.php'         => 'Dashboard',
    'positions.php'      => 'Positions',
    'candidates.php'      => 'Candidates',
    'voters.php'          => 'Voters',
    'rigging.php'         => 'Rigging',
    'activity_log.php'    => 'Activity Log',
    'settings.php'        => 'Settings',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? h($pageTitle) . ' — Admin' : 'Admin'; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/admin/index.php">Election Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <?php foreach ($navItems as $file => $label): ?>
          <li class="nav-item">
            <a class="nav-link <?php echo $currentPage === $file ? 'active fw-bold' : ''; ?>"
               href="<?php echo BASE_URL; ?>/admin/<?php echo $file; ?>"><?php echo h($label); ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item">
          <span class="nav-link text-white-50"><?php echo h($_SESSION['admin_name'] ?? ''); ?></span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo BASE_URL; ?>/admin/logout.php">Logout</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">
<?php render_flashes(); ?>
