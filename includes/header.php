<?php
require_once __DIR__ . '/helpers.php';
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= BASE_URL ?>/assets/css/app.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"></script>
</head><body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar"><div class="container-fluid px-4">
<a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard.php">🌿 LAMAS</a>
<div class="ms-auto d-flex align-items-center gap-3 text-white">
<?php if ($user): ?><span class="small"><?= e($user['full_name']) ?> · <?= e(ucfirst($user['role'])) ?></span><a class="btn btn-sm btn-outline-light" href="<?= BASE_URL ?>/logout.php">Logout</a><?php endif; ?>
</div></div></nav>
<div class="app-shell">
<?php if ($user): ?><aside class="sidebar">
<div class="sidebar-label">MAIN MENU</div>
<a href="<?= BASE_URL ?>/dashboard.php">▦ Dashboard</a>
<a href="<?= BASE_URL ?>/attendance/index.php">◷ Attendance</a>
<a href="<?= BASE_URL ?>/persons/index.php">♙ Students & Faculty</a>
<a href="<?= BASE_URL ?>/reports/index.php">▥ Reports & Analytics</a>
</aside><?php endif; ?>
<main class="main-content">
