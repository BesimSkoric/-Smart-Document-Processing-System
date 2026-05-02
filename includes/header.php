<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
<div class="brand">
<div class="brand-mark"></div>
<div>
<h2>DocFlow</h2>
<p>Document Review</p>
</div>
</div>

<nav class="menu">
<a href="index.php" class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
Upload
</a>
<a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
Dashboard
</a>
<a href="admin.php" class="<?= $currentPage == 'admin.php' ? 'active' : '' ?>">
Admin panel
</a>
</nav>

<div class="side-note">
<strong>Workflow</strong>
<span>Uploaded → Review → Validated</span>
</div>
</aside>
