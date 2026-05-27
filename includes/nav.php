<?php
/** @var string $active */
$active = $active ?? '';
?>
<nav class="topnav">
  <div>
    <span class="brand" data-i18n="app.brand">Tool Management</span>
  </div>
  <div>
    <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>" data-i18n="nav.dashboard">Dashboard</a>
    <a href="companies.php" id="nav-companies" class="<?= $active === 'companies' ? 'active' : '' ?>" style="display:none" data-i18n="nav.companies">Companies</a>
    <a href="import_tools.php" id="nav-import" class="<?= $active === 'import' ? 'active' : '' ?>" style="display:none" data-i18n="nav.import">Import tools</a>
    <a href="warehouses.php" id="nav-warehouses" class="<?= $active === 'warehouses' ? 'active' : '' ?>" style="display:none" data-i18n="nav.warehouses">Warehouses</a>
    <a href="users.php" id="nav-users" class="<?= $active === 'users' ? 'active' : '' ?>" style="display:none" data-i18n="nav.users">Users</a>
    <a href="tools.php" class="<?= $active === 'tools' ? 'active' : '' ?>" data-i18n="nav.tools">Tools</a>
    <a href="measurement_tools.php" id="nav-measurement" class="<?= $active === 'measurement' ? 'active' : '' ?>" style="display:none" data-i18n="nav.measurement">Measurement equipment</a>
    <a href="operators.php" class="<?= $active === 'operators' ? 'active' : '' ?>" data-i18n="nav.operators">Operators</a>
    <a href="history.php" class="<?= $active === 'history' ? 'active' : '' ?>" data-i18n="nav.history">History &amp; reports</a>
    <?php require __DIR__ . '/lang_switcher.php'; ?>
    <?php require __DIR__ . '/theme_toggle.php'; ?>
    <a href="#" id="nav-logout" data-i18n="nav.logout">Logout</a>
  </div>
</nav>
<script src="assets/js/nav.js"></script>
<script>document.addEventListener('DOMContentLoaded', tmInitNav);</script>
