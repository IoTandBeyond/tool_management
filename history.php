<?php $active = 'history'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="history.title">History &amp; reports</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1 data-i18n="history.heading">History &amp; reports</h1>
    <p class="sub" data-i18n="history.sub">Filter transactions and open analytics tabs.</p>
    <div class="tabs">
      <button type="button" class="tab active" data-tab="history" data-i18n="history.tab_history">Transaction history</button>
      <button type="button" class="tab" data-tab="r1" data-i18n="history.tab_r1">Out per operator</button>
      <button type="button" class="tab" data-tab="r2" data-i18n="history.tab_r2">Overdue</button>
      <button type="button" class="tab" data-tab="r3" data-i18n="history.tab_r3">Long outstanding</button>
      <button type="button" class="tab" data-tab="r4" data-i18n="history.tab_r4">Missing tools</button>
      <button type="button" class="tab" data-tab="r5" data-i18n="history.tab_r5">Most used</button>
      <button type="button" class="tab" data-tab="r6" id="tab-r6" style="display:none" data-i18n="history.tab_r6">In maintenance</button>
    </div>

    <div id="panel-history" class="card">
      <h2 data-i18n="history.filters">Filters</h2>
      
      <div class="history-filter-row">
        <div>
          <label for="flt-op" data-i18n="history.operator">Operator</label>
          <select id="flt-op"><option value="" data-i18n="common.all">All</option></select>
        </div>
        <div>
          <label for="flt-tool" data-i18n="history.tool">Tool</label>
          <select id="flt-tool"><option value="" data-i18n="common.all">All</option></select>
        </div>
        <div>
          <label for="flt-category" data-i18n="history.category_filter">Category</label>
          <select id="flt-category">
            <option value="" data-i18n="common.all">All</option>
            <option value="epp" data-i18n="history.cat_epp">EPP</option>
            <option value="insumos" data-i18n="history.cat_insumos">Insumos</option>
            <option value="stqmk" data-i18n="history.cat_stqmk">STQMK</option>
            <option value="measurement" data-i18n="common.measurement_equipment">Measurement equipment</option>
          </select>
        </div>
        <div>
          <label for="flt-from" data-i18n="history.from">From</label>
          <input type="date" id="flt-from">
        </div>
        <div>
          <label for="flt-to" data-i18n="history.to">To</label>
          <input type="date" id="flt-to">
        </div>
      </div>
      <br>
      <div class="row-actions history-filters-actions">
        <button type="button" class="btn btn-primary" id="btn-apply" data-i18n="common.apply">Apply filters</button>
        <button type="button" class="btn btn-secondary" id="btn-clear-filters" data-i18n="common.clear_filters">Clear filters</button>
      </div>
      <br>
      <div style="overflow-x: auto; margin-top: 1rem;">
        <table>
          <thead>
            <tr>
              <th data-i18n="history.col_checkout">Checkout</th>
              <th data-i18n="history.col_return">Return</th>
              <th data-i18n="history.col_operator">Operator</th>
              <th data-i18n="history.col_tool">Tool</th>
              <th data-i18n="history.col_category">Category</th>
              <th data-i18n="history.col_warehouse">Warehouse</th>
              <th data-i18n="history.col_expected">Expected return</th>
            </tr>
          </thead>
          <tbody id="hist-body"></tbody>
        </table>
      </div>
    </div>

    <div id="panel-r1" class="card" style="display: none;">
      <h2 data-i18n="history.r1_title">Tools currently checked out per operator</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="history.col_operator">Operator</th><th data-i18n="operators.employee_id">Employee ID</th><th data-i18n="history.col_warehouse">Warehouse</th><th data-i18n="history.r1_open">Open loans</th></tr></thead>
          <tbody id="rep-r1"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r2" class="card" style="display: none;">
      <h2 data-i18n="history.r2_title">Overdue (measurement equipment only)</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="history.col_tool">Tool</th><th data-i18n="history.col_operator">Operator</th><th data-i18n="history.col_warehouse">Warehouse</th><th data-i18n="history.r2_due">Due</th></tr></thead>
          <tbody id="rep-r2"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r3" class="card" style="display: none;">
      <h2 data-i18n="history.r3_title">Long outstanding (measurement equipment only)</h2>
      <p class="sub" id="r3-note"></p>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="history.col_tool">Tool</th><th data-i18n="history.col_operator">Operator</th><th data-i18n="history.col_warehouse">Warehouse</th><th data-i18n="history.col_checkout">Checkout</th></tr></thead>
          <tbody id="rep-r3"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r4" class="card" style="display: none;">
      <h2 data-i18n="history.r4_title">Tools marked missing</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="common.name">Name</th><th data-i18n="common.barcode">Barcode</th><th>NFC</th><th data-i18n="history.r4_desc">Description</th></tr></thead>
          <tbody id="rep-r4"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r5" class="card" style="display: none;">
      <h2 data-i18n="history.r5_title">Most frequently borrowed (by checkout count)</h2>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="history.col_tool">Tool</th><th data-i18n="common.barcode">Barcode</th><th data-i18n="history.r5_checkouts">Checkouts</th></tr></thead>
          <tbody id="rep-r5"></tbody>
        </table>
      </div>
    </div>
    <div id="panel-r6" class="card" style="display: none;">
      <h2 data-i18n="history.r6_title">Equipment in maintenance</h2>
      <p class="sub" data-i18n="history.r6_sub">Tools currently at an external maintenance provider.</p>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th data-i18n="history.col_tool">Tool</th><th data-i18n="common.barcode">Barcode</th><th data-i18n="history.r6_location">Location</th><th data-i18n="history.r6_provider">Provider</th><th data-i18n="history.r6_sent">Sent</th><th data-i18n="history.r6_notes">Notes</th></tr></thead>
          <tbody id="rep-r6"></tbody>
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

      function categoryLabel(r) {
        if (r.asset_type === 'measurement') {
          return typeof tmT === 'function' ? tmT('common.measurement_equipment') : 'Measurement equipment';
        }
        return r.category_name || (typeof tmT === 'function' ? tmT('common.none_dash') : '—');
      }

      function fillFilters() {
        tmApi('operators_list', {}, true).then(function (d) {
          var s = document.getElementById('flt-op');
          s.innerHTML = '<option value="">' + (typeof tmT === 'function' ? tmT('common.all') : 'All') + '</option>';
          (d.operators || []).forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.name + ' (' + o.employee_id + ')';
            s.appendChild(opt);
          });
        });
        tmApi('tools_list', {}, true).then(function (d) {
          var s = document.getElementById('flt-tool');
          s.innerHTML = '<option value="">' + (typeof tmT === 'function' ? tmT('common.all') : 'All') + '</option>';
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
        document.getElementById('flt-category').value = '';
        document.getElementById('flt-from').value = '';
        document.getElementById('flt-to').value = '';
        loadHistory();
      }

      function loadHistory() {
        var payload = {
          operator_id: document.getElementById('flt-op').value || 0,
          tool_id: document.getElementById('flt-tool').value || 0,
          category_filter: document.getElementById('flt-category').value || '',
          date_from: document.getElementById('flt-from').value || '',
          date_to: document.getElementById('flt-to').value || ''
        };
        if (!payload.operator_id) delete payload.operator_id;
        if (!payload.tool_id) delete payload.tool_id;
        if (!payload.category_filter) delete payload.category_filter;
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
              '<td>' + esc(categoryLabel(r)) + '</td>' +
              '<td>' + esc(r.warehouse_name || '—') + '</td>' +
              '<td>' + (r.asset_type === 'measurement' ? tmFormatDt(r.expected_return_at) : '—') + '</td>';
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
            document.getElementById('r3-note').textContent = typeof tmT === 'function'
              ? tmT('history.r3_note', { days: d.days_threshold || 30 })
              : 'Open loans older than ' + (d.days_threshold || 30) + ' days.';
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
        if (which === 'r6') {
          tmApi('report_maintenance', {}, true).then(function (d) {
            var tb = document.getElementById('rep-r6');
            tb.innerHTML = '';
            (d.rows || []).forEach(function (r) {
              var tr = document.createElement('tr');
              tr.innerHTML =
                '<td>' + esc(r.tool_name) + '</td><td>' + esc(r.barcode) + '</td><td>' + esc(r.location || '—') + '</td>' +
                '<td>' + esc(r.provider_name) + '</td><td>' + tmFormatDt(r.sent_at) + '</td><td>' + esc(r.notes || '—') + '</td>';
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
          ['history', 'r1', 'r2', 'r3', 'r4', 'r5', 'r6'].forEach(function (p) {
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
        tmApi('me', {}, true).then(function (m) {
          if (m.show_measurement_nav) {
            document.getElementById('tab-r6').style.display = '';
          }
        });
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
