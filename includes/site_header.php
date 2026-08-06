<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($pageTitle) ? h($pageTitle) : 'Departmental Election'; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>/index.php">Departmental Election</a>
    <div>
      <a class="btn btn-outline-light btn-sm" href="<?php echo BASE_URL; ?>/results.php">Live Results</a>
    </div>
  </div>
</nav>
<div class="container py-4">
<?php if (function_exists('render_flashes')) render_flashes(); ?>
