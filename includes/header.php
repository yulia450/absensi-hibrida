<?php
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($pageTitle) ?> — Sistem Absensi SMK AL-MANSHURIYAH</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
<script>
  window.BASE_URL        = '<?= BASE_URL ?>';
  window.ABSENSI_API_URL = '<?= BASE_URL ?>api/absensi.php';
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="main-content">
    <header class="top-header">
      <button class="menu-toggle" id="menu-toggle" style="display:none">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
      <div class="header-right">
        <div class="header-time" id="live-clock">--:--:--</div>
      </div>
    </header>
    <div class="page-content">