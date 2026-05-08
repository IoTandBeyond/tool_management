<?php $active = 'warehouses'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Warehouses</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Warehouses</h1>
    <p class="sub" id="sub-line"></p>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add">Add warehouse</button>
    </div>
    <div class="card" style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Company</th>
            <th>Name</th>
            <th>Address</th>
            <th>Manager</th>
            <th id="th-actions"></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>
  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 480px; width: 100%;">
      <h2 id="modal-title">Warehouse</h2>
      <input type="hidden" id="f-id">
      <div id="f-co-wrap" style="display: none;">
        <label for="f-co">Company</label>
        <select id="f-co"></select>
      </div>
      <label for="f-name">Name</label>
      <input type="text" id="f-name">
      <label for="f-addr" style="margin-top: 0.75rem;">Address</label>
      <textarea id="f-addr"></textarea>
      <label for="f-manager" style="margin-top: 0.75rem;">Manager</label>
      <select id="f-manager">
        <option value="">Unassigned</option>
      </select>
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="f-save">Save</button>
        <button type="button" class="btn btn-secondary" id="f-close">Close</button>
      </div>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var session = { role: '', company_id: null, warehouse_id: null };
      function canAddWarehouse() {
        return session.role === 'super_admin' || session.role === 'admin';
      }
      function canEditWarehouse() {
        return session.role === 'super_admin' || session.role === 'admin';
      }
      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
      }
      function loadCompanies(cb) {
        tmApi('companies_list', {}, true).then(function (d) {
          var sel = document.getElementById('f-co');
          sel.innerHTML = '';
          (d.companies || []).forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            sel.appendChild(o);
          });
          if (cb) cb();
        });
      }
      function loadManagers(companyId, selectedManagerId, cb) {
        var sel = document.getElementById('f-manager');
        sel.innerHTML = '<option value="">Unassigned</option>';
        if (!companyId) {
          if (cb) cb();
          return;
        }
        tmApi('company_managers_list', { company_id: companyId }, true).then(function (d) {
          (d.managers || []).forEach(function (m) {
            var o = document.createElement('option');
            o.value = m.id;
            o.textContent = m.name || m.email || ('Manager #' + m.id);
            sel.appendChild(o);
          });
          if (selectedManagerId) sel.value = String(selectedManagerId);
          if (cb) cb();
        });
      }
      function load() {
        var payload = {};
        var fc = document.getElementById('filter-co');
        if (session.role === 'super_admin' && fc && fc.value) {
          payload.company_id = parseInt(fc.value, 10);
        }
        tmApi('warehouses_list', payload, true).then(function (d) {
          var tb = document.getElementById('tbody');
          tb.innerHTML = '';
          var list = d.warehouses || [];
          var showEdit = canEditWarehouse();
          document.getElementById('th-actions').textContent = showEdit ? 'Actions' : '';
          list.forEach(function (w) {
            var tr = document.createElement('tr');
            var mgr = w.manager_display != null && String(w.manager_display).trim() !== ''
              ? String(w.manager_display).trim()
              : '—';
            var actionsCell = showEdit
              ? '<td><button type="button" class="btn btn-ghost btn-edit" data-id="' + w.id + '">Edit</button></td>'
              : '<td>—</td>';
            tr.innerHTML =
              '<td>' + esc(w.company_name || '—') + '</td>' +
              '<td>' + esc(w.warehouse_name) + '</td>' +
              '<td>' + esc(w.warehouse_address || '—') + '</td>' +
              '<td>' + esc(mgr) + '</td>' +
              actionsCell;
            tb.appendChild(tr);
          });
          if (!showEdit) return;
          tb.querySelectorAll('.btn-edit').forEach(function (b) {
            b.addEventListener('click', function () {
              var row = list.find(function (x) { return Number(x.id) === parseInt(b.dataset.id, 10); });
              if (!row) return;
              document.getElementById('modal-title').textContent = 'Edit warehouse';
              document.getElementById('f-id').value = row.id;
              document.getElementById('f-name').value = row.warehouse_name || '';
              document.getElementById('f-addr').value = row.warehouse_address || '';
              if (session.role === 'super_admin') document.getElementById('f-co').value = String(row.company_id);
              loadManagers(row.company_id, row.manager_user_id || null, function () {
                document.getElementById('modal').style.display = 'flex';
              });
            });
          });
        });
      }
      document.getElementById('btn-add').addEventListener('click', function () {
        document.getElementById('modal-title').textContent = 'Add warehouse';
        document.getElementById('f-id').value = '';
        document.getElementById('f-name').value = '';
        document.getElementById('f-addr').value = '';
        if (session.role === 'admin') document.getElementById('f-co').value = String(session.company_id);
        var cid = session.role === 'super_admin'
          ? parseInt(document.getElementById('f-co').value, 10)
          : session.company_id;
        loadManagers(cid, null, function () {
          document.getElementById('modal').style.display = 'flex';
        });
      });
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('f-save').addEventListener('click', function () {
        var cid = session.role === 'super_admin'
          ? parseInt(document.getElementById('f-co').value, 10)
          : session.company_id;
        var payload = {
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          warehouse_name: document.getElementById('f-name').value.trim(),
          warehouse_address: document.getElementById('f-addr').value.trim(),
          manager_user_id: document.getElementById('f-manager').value
            ? parseInt(document.getElementById('f-manager').value, 10)
            : null
        };
        if (session.role === 'super_admin' || session.role === 'admin') {
          payload.company_id = cid;
        }
        tmApi('warehouse_save', payload, true).then(function (r) {
          if (r.ok) {
            document.getElementById('modal').style.display = 'none';
            load();
          } else alert(r.error || 'Save failed');
        });
      });
      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });
      tmApi('me', {}, true).then(function (m) {
        if (!m.logged_in || (m.role !== 'super_admin' && m.role !== 'admin')) {
          window.location.href = 'dashboard.php';
          return;
        }
        session.role = m.role;
        session.company_id = m.company_id;
        session.warehouse_id = m.warehouse_id;
        document.getElementById('btn-add').style.display = canAddWarehouse() ? '' : 'none';
        if (m.role === 'super_admin') {
          document.getElementById('sub-line').textContent = 'Filter by company or add warehouses for any company.';
          document.getElementById('f-co-wrap').style.display = 'block';
          var filter = document.createElement('div');
          filter.className = 'row-actions';
          filter.style.marginBottom = '1rem';
          filter.innerHTML = '<label for="filter-co">Company filter</label> <select id="filter-co"><option value="">All</option></select> <button type="button" class="btn btn-secondary" id="btn-filter">Apply</button>';
          document.querySelector('h1').after(filter);
          tmApi('companies_list', {}, true).then(function (d) {
            var sel = document.getElementById('filter-co');
            (d.companies || []).forEach(function (c) {
              var o = document.createElement('option');
              o.value = c.id;
              o.textContent = c.name;
              sel.appendChild(o);
            });
          });
          document.getElementById('btn-filter').addEventListener('click', load);
          document.getElementById('f-co').addEventListener('change', function () {
            var cid = parseInt(document.getElementById('f-co').value, 10);
            loadManagers(cid, null);
          });
          loadCompanies(function () {
            var initialCid = parseInt(document.getElementById('f-co').value, 10);
            loadManagers(initialCid, null, function () { load(); });
          });
          return;
        }
        if (m.role === 'admin') {
          document.getElementById('sub-line').textContent = 'Warehouses for your company — add or edit.';
          document.getElementById('f-co-wrap').style.display = 'none';
          loadManagers(m.company_id, null, function () { load(); });
          return;
        }
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
