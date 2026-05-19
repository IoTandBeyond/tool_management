<?php $active = 'import'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Import tools</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Import tools</h1>
    <p class="sub">Super admin only — bulk import consumable tools or measurement equipment from CSV or Excel (.xlsx).</p>

    <div class="card" style="max-width: 640px;">
      <label for="import-type">Import type</label>
      <select id="import-type">
        <option value="consumable">Consumable tools (PPE, supplies)</option>
        <option value="measurement">Measurement equipment</option>
      </select>

      <label for="import-company" style="margin-top: 0.75rem;">Company</label>
      <select id="import-company"></select>

      <label for="import-file" style="margin-top: 0.75rem;">Spreadsheet file (.csv or .xlsx)</label>
      <input type="file" id="import-file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">

      <div class="row-actions" style="margin-top: 1rem; flex-wrap: wrap;">
        <button type="button" class="btn btn-primary" id="btn-import">Import</button>
        <button type="button" class="btn btn-secondary" id="btn-template">Download template</button>
      </div>

      <p class="sub" style="margin-top: 1rem;">
        Required columns: <strong>name</strong>, <strong>barcode</strong>, <strong>warehouse</strong> (must match an existing warehouse name).
        Optional: stock_qty (default 1), nfc_id, category, description, missing, active.
        Measurement rows may also include uom, range, brand_model, condition, location, last_maintenance.
      </p>
    </div>

    <div id="result-card" class="card" style="display: none; margin-top: 1rem; max-width: 720px;">
      <h2>Import result</h2>
      <p id="result-summary" class="sub"></p>
      <div id="result-errors-wrap" style="display: none; overflow-x: auto; margin-top: 0.75rem;">
        <table>
          <thead><tr><th>Row</th><th>Issue</th></tr></thead>
          <tbody id="result-errors"></tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var measurementCoId = 2;

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
      }

      document.getElementById('btn-template').addEventListener('click', function () {
        var cid = document.getElementById('import-company').value;
        var type = document.getElementById('import-type').value;
        if (!cid) {
          alert('Select a company first');
          return;
        }
        window.location.href = 'api.php?action=tools_import_template&asset_type=' +
          encodeURIComponent(type);
      });

      document.getElementById('btn-import').addEventListener('click', function () {
        var file = document.getElementById('import-file').files[0];
        var cid = parseInt(document.getElementById('import-company').value, 10);
        var type = document.getElementById('import-type').value;
        if (!file) {
          alert('Choose a CSV or XLSX file');
          return;
        }
        if (!cid) {
          alert('Select a company');
          return;
        }
        if (type === 'measurement' && cid !== measurementCoId) {
          alert('Measurement equipment can only be imported for company id ' + measurementCoId);
          return;
        }
        document.getElementById('btn-import').disabled = true;
        tmUploadToolsImport(file, cid, type).then(function (r) {
          document.getElementById('btn-import').disabled = false;
          if (!r.ok) {
            alert(r.error || 'Import failed');
            return;
          }
          var card = document.getElementById('result-card');
          card.style.display = 'block';
          document.getElementById('result-summary').textContent =
            'Imported ' + (r.imported || 0) + ' row(s). Skipped ' + (r.skipped || 0) + '.';
          var errs = r.errors || [];
          var wrap = document.getElementById('result-errors-wrap');
          var tb = document.getElementById('result-errors');
          tb.innerHTML = '';
          if (errs.length) {
            wrap.style.display = 'block';
            errs.forEach(function (e) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(e.row) + '</td><td>' + esc(e.message) + '</td>';
              tb.appendChild(tr);
            });
          } else {
            wrap.style.display = 'none';
          }
        }).catch(function () {
          document.getElementById('btn-import').disabled = false;
          alert('Import failed');
        });
      });

      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });

      tmApi('me', {}, true).then(function (m) {
        if (!m.logged_in || m.role !== 'super_admin') {
          window.location.href = 'dashboard.php';
          return;
        }
        measurementCoId = m.measurement_company_id || 2;
        tmApi('companies_list', {}, true).then(function (d) {
          var sel = document.getElementById('import-company');
          (d.companies || []).forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            sel.appendChild(o);
          });
          if (sel.options.length) sel.selectedIndex = 0;
        });
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
