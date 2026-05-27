<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="kiosk.title">Tool Management — Warehouse</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="theme-toggle-float">
    <?php require __DIR__ . '/includes/lang_switcher.php'; ?>
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
  </div>
  <div class="wrap">
    <div class="kiosk-title">
      <h1 data-i18n="kiosk.heading">Warehouse tools</h1>
      <p class="sub" id="kiosk-wh-hint" data-i18n="kiosk.hint_scan">Scan your badge — the reader sends Enter after the ID.</p>
    </div>
    <div id="warehouse-setup" class="card" style="max-width: 480px; margin: 0 auto 1rem; display: none;">
      <h2 style="margin: 0 0 0.5rem; font-size: 1.05rem;" data-i18n="kiosk.setup_title">Warehouse setup</h2>
      <p class="sub" style="margin-bottom: 0.75rem;" data-i18n="kiosk.setup_sub">Enter this kiosk’s warehouse number once (from your administrator). It is saved on this device.</p>
      <label for="warehouse-setup-id" data-i18n="kiosk.warehouse_id">Warehouse ID</label>
      <input type="number" id="warehouse-setup-id" min="1" step="1" data-i18n-placeholder="kiosk.warehouse_id_placeholder">
      <div class="row-actions" style="margin-top: 0.75rem;">
        <button type="button" class="btn btn-primary" id="warehouse-setup-save" data-i18n="kiosk.save_warehouse">Save warehouse</button>
      </div>
    </div>
    <div class="card" style="max-width: 480px; margin: 0 auto;">
      <label for="employee_id" data-i18n="kiosk.employee_id">Employee ID</label>
      <input type="text" id="employee_id" name="employee_id" autocomplete="off" data-i18n-placeholder="kiosk.employee_placeholder">
      <div id="id-error" class="msg msg-error" style="display: none; margin-top: 1rem;"></div>
    </div>
    <div class="kiosk-footer">
      <a href="login.php" data-i18n="kiosk.supervisor_login">Supervisor login</a>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var STORAGE_KEY = 'tm_kiosk_warehouse_id';
      var input = document.getElementById('employee_id');
      var err = document.getElementById('id-error');
      var setupPanel = document.getElementById('warehouse-setup');
      var setupInput = document.getElementById('warehouse-setup-id');
      var hint = document.getElementById('kiosk-wh-hint');

      function readWarehouseIdFromUrl() {
        var params = new URLSearchParams(window.location.search);
        return parseInt(params.get('warehouse_id') || '0', 10);
      }

      function getWarehouseId() {
        var fromUrl = readWarehouseIdFromUrl();
        if (fromUrl > 0) {
          try { localStorage.setItem(STORAGE_KEY, String(fromUrl)); } catch (e) {}
          return fromUrl;
        }
        try {
          var stored = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
          return stored > 0 ? stored : 0;
        } catch (e) {
          return 0;
        }
      }

      function refreshWarehouseUi() {
        var wid = getWarehouseId();
        if (wid > 0) {
          setupPanel.style.display = 'none';
          hint.textContent = typeof tmT === 'function'
            ? tmT('kiosk.hint_warehouse', { id: wid })
            : 'Warehouse #' + wid + ' — scan your badge; the reader sends Enter after the ID.';
          input.focus();
        } else {
          setupPanel.style.display = 'block';
          hint.textContent = typeof tmT === 'function' ? tmT('kiosk.hint_no_warehouse') : hint.textContent;
          setupInput.focus();
        }
      }

      function saveWarehouseSetup() {
        err.style.display = 'none';
        var n = parseInt(setupInput.value || '0', 10);
        if (n < 1) {
          err.textContent = typeof tmT === 'function' ? tmT('kiosk.err_warehouse_id') : 'Enter a valid warehouse ID.';
          err.style.display = 'block';
          return;
        }
        try { localStorage.setItem(STORAGE_KEY, String(n)); } catch (e) {}
        refreshWarehouseUi();
        input.focus();
      }

      document.getElementById('warehouse-setup-save').addEventListener('click', saveWarehouseSetup);
      setupInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          saveWarehouseSetup();
        }
      });

      document.addEventListener('tm-i18n-ready', refreshWarehouseUi);
      refreshWarehouseUi();

      function goToCheckout() {
        err.style.display = 'none';
        var warehouseId = getWarehouseId();
        if (!warehouseId) {
          err.textContent = typeof tmT === 'function' ? tmT('kiosk.err_pick_warehouse') : 'Choose a warehouse above.';
          err.style.display = 'block';
          return;
        }
        var id = (input.value || '').trim();
        if (!id) {
          err.textContent = typeof tmT === 'function' ? tmT('kiosk.err_employee_id') : 'Scan or enter your employee ID first.';
          err.style.display = 'block';
          input.focus();
          return;
        }
        tmApi('validate_operator', { employee_id: id, warehouse_id: warehouseId }).then(function (data) {
          if (!data.ok) {
            err.textContent = data.error || (typeof tmT === 'function' ? tmT('common.validation_failed') : 'Validation failed');
            err.style.display = 'block';
            return;
          }
          var q = 'operator_id=' + encodeURIComponent(data.operator.id) +
            '&name=' + encodeURIComponent(data.operator.name) +
            '&warehouse_id=' + encodeURIComponent(warehouseId);
          window.location.href = 'checkout.php?' + q;
        }).catch(function () {
          err.textContent = typeof tmT === 'function' ? tmT('common.network_error_retry') : 'Network error — try again.';
          err.style.display = 'block';
        });
      }

      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          goToCheckout();
        }
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
