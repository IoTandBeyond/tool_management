<?php $active = 'history'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>History &amp; reports</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>History &amp; reports</h1>
    <p class="sub">Filter transactions and open analytics tabs.</p>

    <div class="tabs">
      <button type="button" class="tab active" data-tab="history">Transaction history</button>
      <button type="button" class="tab" data-tab="r1">Out per operator</button>
      <button type="button" class="tab" data-tab="r2">Overdue</button>
      <button type="button" class="tab" data-tab="r3">Long outstanding</button>
      <button type="button" class="tab" data-tab="r4">Missing tools</button>
      <button type="button" class="tab" data-tab="r5">Most used</button>
    </div>

    <div id="panel-history" class="card">
      <h2>Filters</h2>
      <div class="row-actions history-filters-actions">
        <button type="button" class="btn btn-primary" id="btn-apply">Apply filters</button>
        <button type="button" class="btn btn-secondary" id="btn-clear-filters">Clear filters</button>
      </div>
      <div class="history-filter-row">
        <div>
          <label for="flt-op">Operator</label>
          <select id="flt-op"><option value="">All</option></select>
        </div>
        <div>
          <label for="flt-tool">Tool</label>
          <select id="flt-tool"><option value="">All</option></select>
        </div>
        <div>
          <label for="flt-from">From</label>
          <input type="date" id="flt-from">
        </div>
        <div>
          <label for="flt-to">To</label>
          <input type="date" id="flt-to">
        </div>
      </div>
      <div style="overflow-x: auto; margin-top: 1rem;">
        <table>
          <thead>
            <tr>
              <th>Checkout</th>
              <th>Return</th>
              <th>Operator</th>
              <th>Tool</th>
              <th>Warehouse</th>
              <th>Expected return</th>
            </tr>
          </thead>
          <tbody id="hist-body"></tbody>
        </table>
      </div>
    </div>

    <div id="panel-r1" class="card" style="display: none;">
      <h2>Tools currently checked out per operator</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Operator</th><th>Employee ID</th><th>Warehouse</th><th>Open loans</th></tr></thead>
          <tbody id="rep-r1"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r2" class="card" style="display: none;">
      <h2>Overdue (past expected return, not checked in)</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Tool</th><th>Operator</th><th>Warehouse</th><th>Due</th></tr></thead>
          <tbody id="rep-r2"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r3" class="card" style="display: none;">
      <h2>Long outstanding checkouts</h2>
      <p class="sub" id="r3-note"></p>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Tool</th><th>Operator</th><th>Warehouse</th><th>Checkout</th></tr></thead>
          <tbody id="rep-r3"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r4" class="card" style="display: none;">
      <h2>Tools marked missing</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Name</th><th>Barcode</th><th>NFC</th><th>Description</th></tr></thead>
          <tbody id="rep-r4"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r5" class="card" style="display: none;">
      <h2>Most frequently borrowed (by checkout count)</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Tool</th><th>Barcode</th><th>Checkouts</th></tr></thead>
          <tbody id="rep-r5"></tbody>
        </table>
      </div>
    </div>
  </div>

  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      function ensureAuth() {
        return tmApi('me', {}, true).then(function (data) {
          if (!data.logged_in) {
            window.location.href = 'login.php';
            return false;
          }
          return true;
        });
      }

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      function fillFilters() {
        tmApi('operators_list', {}, true).then(function (d) {
          var s = document.getElementById('flt-op');
          s.innerHTML = '<option value="">All</option>';
          (d.operators || []).forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.name + ' (' + o.employee_id + ')';
            s.appendChild(opt);
          });
        });
        tmApi('tools_list', {}, true).then(function (d) {
          var s = document.getElementById('flt-tool');
          s.innerHTML = '<option value="">All</option>';
          (d.tools || []).forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name + ' (' + t.barcode + ')';
            s.appendChild(opt);
          });
        });
      }

      function clearFilters() {
        document.getElementById('flt-op').value = '';
        document.getElementById('flt-tool').value = '';
        document.getElementById('flt-from').value = '';
        document.getElementById('flt-to').value = '';
        loadHistory();
      }

      function loadHistory() {
        var payload = {
          operator_id: document.getElementById('flt-op').value || 0,
          tool_id: document.getElementById('flt-tool').value || 0,
          date_from: document.getElementById('flt-from').value || '',
          date_to: document.getElementById('flt-to').value || ''
        };
        if (!payload.operator_id) delete payload.operator_id;
        if (!payload.tool_id) delete payload.tool_id;
        tmApi('history', payload, true).then(function (data) {
          var tb = document.getElementById('hist-body');
          tb.innerHTML = '';
          (data.transactions || []).forEach(function (r) {
            var tr = document.createElement('tr');
            tr.innerHTML =
              '<td>' + tmFormatDt(r.checkout_at) + '</td>' +
              '<td>' + (r.checkin_at ? tmFormatDt(r.checkin_at) : '—') + '</td>' +
              '<td>' + esc(r.operator_name) + ' (' + esc(r.employee_id) + ')</td>' +
              '<td>' + esc(r.tool_name) + '</td>' +
              '<td>' + esc(r.warehouse_name || '—') + '</td>' +
              '<td>' + tmFormatDt(r.expected_return_at) + '</td>';
            tb.appendChild(tr);
          });
        });
      }

      function loadReports(which) {
        if (which === 'r1') {
          tmApi('report_checked_out_by_operator', {}, true).then(function (d) {
            var tb = document.getElementById('rep-r1');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(r.name) + '</td><td>' + esc(r.employee_id) + '</td><td>' + esc(r.warehouse_name || '—') + '</td><td>' + r.open_count + '</td>';
              tb.appendChild(tr);
            });
          });
        }
        if (which === 'r2') {
          tmApi('report_overdue', {}, true).then(function (d) {
            var tb = document.getElementById('rep-r2');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(r.tool_name) + '</td><td>' + esc(r.operator_name) + '</td><td>' + esc(r.warehouse_name || '—') + '</td><td>' + tmFormatDt(r.expected_return_at) + '</td>';
              tb.appendChild(tr);
            });
          });
        }
        if (which === 'r3') {
          tmApi('report_long_outstanding', {}, true).then(function (d) {
            document.getElementById('r3-note').textContent = 'Open loans older than ' + (d.days_threshold || 30) + ' days.';
            var tb = document.getElementById('rep-r3');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(r.tool_name) + '</td><td>' + esc(r.operator_name) + '</td><td>' + esc(r.warehouse_name || '—') + '</td><td>' + tmFormatDt(r.checkout_at) + '</td>';
              tb.appendChild(tr);
            });
          });
        }
        if (which === 'r4') {
          tmApi('report_missing_tools', {}, true).then(function (d) {
            var tb = document.getElementById('rep-r4');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(r.name) + '</td><td>' + esc(r.barcode) + '</td><td>' + esc(r.nfc_id || '—') + '</td><td>' + esc(r.description || '—') + '</td>';
              tb.appendChild(tr);
            });
          });
        }
        if (which === 'r5') {
          tmApi('report_most_used_tools', { limit: 25 }, true).then(function (d) {
            var tb = document.getElementById('rep-r5');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML = '<td>' + esc(r.name) + '</td><td>' + esc(r.barcode) + '</td><td>' + r.checkout_count + '</td>';
              tb.appendChild(tr);
            });
          });
        }
      }

      document.querySelectorAll('.tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
          document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('active'); });
          tab.classList.add('active');
          var id = tab.dataset.tab;
          ['history', 'r1', 'r2', 'r3', 'r4', 'r5'].forEach(function (p) {
            document.getElementById('panel-' + p).style.display = p === id ? 'block' : 'none';
          });
          if (id !== 'history') loadReports(id);
        });
      });

      document.getElementById('btn-apply').addEventListener('click', loadHistory);
      document.getElementById('btn-clear-filters').addEventListener('click', clearFilters);

      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });

      ensureAuth().then(function (ok) {
        if (!ok) return;
        fillFilters();
        loadHistory();
        loadReports('r1');
        loadReports('r2');
        loadReports('r3');
        loadReports('r4');
        loadReports('r5');
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
