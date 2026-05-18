<?php
/** @var string $active */
$active = $active ?? '';
?>
<nav class="topnav">
  <div>
    <span class="brand">Tool Management</span>
  </div>
  <div>
    <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="companies.php" id="nav-companies" class="<?= $active === 'companies' ? 'active' : '' ?>" style="display:none">Companies</a>
    <a href="warehouses.php" id="nav-warehouses" class="<?= $active === 'warehouses' ? 'active' : '' ?>" style="display:none">Warehouses</a>
    <a href="users.php" id="nav-users" class="<?= $active === 'users' ? 'active' : '' ?>" style="display:none">Users</a>
    <a href="tools.php" class="<?= $active === 'tools' ? 'active' : '' ?>">Tools</a>
    <a href="measurement_tools.php" id="nav-measurement" class="<?= $active === 'measurement' ? 'active' : '' ?>" style="display:none">Measurement equipment</a>
    <a href="operators.php" class="<?= $active === 'operators' ? 'active' : '' ?>">Operators</a>
    <a href="history.php" class="<?= $active === 'history' ? 'active' : '' ?>">History &amp; reports</a>
    <?php require __DIR__ . '/theme_toggle.php'; ?>
    <a href="#" id="nav-logout">Logout</a>
  </div>
</nav>
<script src="assets/js/nav.js"></script>
<script>document.addEventListener('DOMContentLoaded', tmInitNav);</script>
