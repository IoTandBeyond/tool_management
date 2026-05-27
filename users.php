<?php $active = 'users'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="users.title">Users</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1 data-i18n="users.heading">Staff users</h1>
    <p class="sub">Admins and warehouse managers (not kiosk operators — use Operators page).</p>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add">Add user</button>
    </div>
    <div id="filter-wrap" class="row-actions" style="margin-bottom: 1rem; display: none;">
      <label for="filter-co">Company</label>
      <select id="filter-co"><option value="">All</option></select>
      <button type="button" class="btn btn-secondary" id="btn-filter">Apply</button>
    </div>
    <div class="card" style="overflow-x: auto;">
      <table>
        <thead><tr><th>Email</th><th>Name</th><th>Role</th><th>Company</th><th>Warehouse</th></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>
  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 440px; width: 100%;">
      <h2 id="modal-title">User</h2>
      <input type="hidden" id="f-id">
      <label for="f-email">Email</label>
      <input type="email" id="f-email">
      <label for="f-name" style="margin-top: 0.75rem;">Name</label>
      <input type="text" id="f-name">
      <label for="f-role" style="margin-top: 0.75rem;">Role</label>
      <select id="f-role">
        <option value="admin">Company admin</option>
        <option value="manager">Warehouse manager</option>
      </select>
      <div id="f-co-wrap" style="display: none;">
        <label for="f-co" style="margin-top: 0.75rem;">Company</label>
        <select id="f-co"></select>
      </div>
      <div id="f-wh-wrap" style="display: none;">
        <label for="f-wh" style="margin-top: 0.75rem;">Warehouse</label>
        <select id="f-wh"></select>
      </div>
      <label for="f-pass" style="margin-top: 0.75rem;">Password <span class="sub">(required for new)</span></label>
      <input type="password" id="f-pass" autocomplete="new-password">
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="f-save">Save</button>
        <button type="button" class="btn btn-secondary" id="f-close">Close</button>
      </div>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var session = { role: '', company_id: null };
      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }
      function loadWarehousesForCompany(cid, cb) {
        tmApi('warehouses_list', { company_id: cid }, true).then(function (d) {
          var sel = document.getElementById('f-wh');
          sel.innerHTML = '<option value="">—</option>';
          (d.warehouses || []).forEach(function (w) {
            var o = document.createElement('option');
            o.value = w.id;
            o.textContent = w.warehouse_name;
            sel.appendChild(o);
          });
          if (cb) cb();
        });
      }
      function load() {
        var payload = {};
        var fc = document.getElementById('filter-co');
        if (session.role === 'super_admin' && fc && fc.value) payload.company_id = parseInt(fc.value, 10);
        tmApi('users_list', payload, true).then(function (d) {
          if (!d.ok) { window.location.href = 'dashboard.php'; return; }
          var tb = document.getElementById('tbody');
          tb.innerHTML = '';
          (d.users || []).forEach(function (u) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>' + esc(u.email) + '</td><td>' + esc(u.name) + '</td><td>' + esc(u.role) + '</td><td>' + esc(u.company_name || '—') + '</td><td>' + esc(u.warehouse_name || '—') + '</td>';
            tb.appendChild(tr);
          });
        });
      }
      function syncRoleUi() {
        var role = document.getElementById('f-role').value;
        document.getElementById('f-wh-wrap').style.display = role === 'manager' ? 'block' : 'none';
        if (session.role === 'super_admin') {
          document.getElementById('f-co-wrap').style.display = 'block';
          var cid = parseInt(document.getElementById('f-co').value, 10);
          if (role === 'manager' && cid) loadWarehousesForCompany(cid);
        }
      }
      document.getElementById('f-role').addEventListener('change', syncRoleUi);
      document.getElementById('f-co').addEventListener('change', function () {
        var cid = parseInt(document.getElementById('f-co').value, 10);
        if (document.getElementById('f-role').value === 'manager') loadWarehousesForCompany(cid);
      });
      document.getElementById('btn-add').addEventListener('click', function () {
        document.getElementById('modal-title').textContent = 'Add user';
        document.getElementById('f-id').value = '';
        document.getElementById('f-email').value = '';
        document.getElementById('f-name').value = '';
        document.getElementById('f-pass').value = '';
        document.getElementById('f-role').value = 'manager';
        if (session.role === 'admin') {
          document.getElementById('f-co-wrap').style.display = 'none';
          loadWarehousesForCompany(session.company_id);
          document.getElementById('modal').style.display = 'flex';
          syncRoleUi();
        } else {
          document.getElementById('f-co-wrap').style.display = 'block';
          tmApi('companies_list', {}, true).then(function (d) {
            var sel = document.getElementById('f-co');
            sel.innerHTML = '';
            (d.companies || []).forEach(function (c) {
              var o = document.createElement('option');
              o.value = c.id;
              o.textContent = c.name;
              sel.appendChild(o);
            });
            document.getElementById('modal').style.display = 'flex';
            syncRoleUi();
          });
        }
      });
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('f-save').addEventListener('click', function () {
        var payload = {
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          email: document.getElementById('f-email').value.trim(),
          name: document.getElementById('f-name').value.trim(),
          role: document.getElementById('f-role').value,
          password: document.getElementById('f-pass').value
        };
        if (session.role === 'super_admin') {
          payload.company_id = document.getElementById('f-co').value ? parseInt(document.getElementById('f-co').value, 10) : null;
          payload.warehouse_id = document.getElementById('f-wh').value ? parseInt(document.getElementById('f-wh').value, 10) : null;
        } else {
          payload.company_id = session.company_id;
          payload.warehouse_id = document.getElementById('f-wh').value ? parseInt(document.getElementById('f-wh').value, 10) : null;
        }
        if (payload.role === 'admin') payload.warehouse_id = null;
        tmApi('user_save', payload, true).then(function (r) {
          if (r.ok) { document.getElementById('modal').style.display = 'none'; load(); }
          else alert(r.error || 'Save failed');
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
        if (m.role === 'super_admin') {
          document.getElementById('filter-wrap').style.display = 'flex';
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
        }
        if (m.role === 'admin') {
          loadWarehousesForCompany(m.company_id);
        }
        load();
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
