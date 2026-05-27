<?php $active = 'operators'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="operators.title">Operators</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1 data-i18n="operators.heading">Operators</h1>
    <p class="sub" data-i18n="operators.sub">Kiosk users: employee ID must be unique per company. Operators are tied to one warehouse.</p>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add" data-i18n="operators.add">Add operator</button>
    </div>
    <div class="card" style="overflow-x: auto;">
      <table>
        <thead>
          <tr><th data-i18n="common.name">Name</th><th data-i18n="operators.employee_id">Employee ID</th><th data-i18n="common.company">Company</th><th data-i18n="common.warehouse">Warehouse</th><th data-i18n="operators.department">Department</th><th></th></tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>

  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 440px; width: 100%;">
      <h2 id="modal-title">Operator</h2>
      <input type="hidden" id="f-id">
      <div id="f-co-wrap" style="display: none;">
        <label for="f-co">Company</label>
        <select id="f-co"></select>
      </div>
      <div id="f-wh-wrap">
        <label for="f-wh" style="margin-top: 0.75rem;">Warehouse</label>
        <select id="f-wh"></select>
      </div>
      <label for="f-name" style="margin-top: 0.75rem;">Name</label>
      <input type="text" id="f-name">
      <label for="f-emp" style="margin-top: 0.75rem;">Employee ID</label>
      <input type="text" id="f-emp">
      <label for="f-dept" style="margin-top: 0.75rem;">Department</label>
      <input type="text" id="f-dept">
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="f-save">Save</button>
        <button type="button" class="btn btn-secondary" id="f-close">Close</button>
      </div>
    </div>
  </div>

  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var me = { role: '', company_id: null, warehouse_id: null };

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      function loadWarehouses(cid, cb) {
        tmApi('warehouses_list', { company_id: cid }, true).then(function (d) {
          var sel = document.getElementById('f-wh');
          sel.innerHTML = '';
          (d.warehouses || []).forEach(function (w) {
            var o = document.createElement('option');
            o.value = w.id;
            o.textContent = w.warehouse_name;
            sel.appendChild(o);
          });
          if (me.role === 'manager' && me.warehouse_id) {
            sel.value = String(me.warehouse_id);
            sel.disabled = true;
          } else {
            sel.disabled = false;
          }
          if (cb) cb();
        });
      }

      function load() {
        tmApi('operators_list', {}, true).then(function (data) {
          var tb = document.getElementById('tbody');
          tb.innerHTML = '';
          (data.operators || []).forEach(function (o) {
            var tr = document.createElement('tr');
            tr.innerHTML =
              '<td>' + esc(o.name) + '</td>' +
              '<td>' + esc(o.employee_id) + '</td>' +
              '<td>' + esc(o.company_name || '—') + '</td>' +
              '<td>' + esc(o.warehouse_name || '—') + '</td>' +
              '<td>' + esc(o.department || '—') + '</td>' +
              '<td><button type="button" class="btn btn-ghost btn-edit" data-id="' + o.id + '">Edit</button> ' +
              '<button type="button" class="btn btn-danger btn-del" data-id="' + o.id + '">Delete</button></td>';
            tb.appendChild(tr);
          });
          tb.querySelectorAll('.btn-edit').forEach(function (b) {
            b.addEventListener('click', function () { openEdit(parseInt(b.dataset.id, 10)); });
          });
          tb.querySelectorAll('.btn-del').forEach(function (b) {
            b.addEventListener('click', function () {
              if (!confirm('Delete this operator?')) return;
              tmApi('operator_delete', { id: parseInt(b.dataset.id, 10) }, true).then(function (r) {
                if (r.ok) load();
                else alert(r.error || 'Delete failed');
              });
            });
          });
        });
      }

      function openAdd() {
        document.getElementById('modal-title').textContent = 'Add operator';
        document.getElementById('f-id').value = '';
        document.getElementById('f-name').value = '';
        document.getElementById('f-emp').value = '';
        document.getElementById('f-dept').value = '';
        if (me.role === 'super_admin') {
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
            var cid = parseInt(sel.value, 10);
            loadWarehouses(cid, function () {
              document.getElementById('modal').style.display = 'flex';
            });
          });
        } else {
          document.getElementById('f-co-wrap').style.display = 'none';
          loadWarehouses(me.company_id, function () {
            document.getElementById('modal').style.display = 'flex';
          });
        }
      }

      function openEdit(id) {
        tmApi('operators_list', {}, true).then(function (data) {
          var o = (data.operators || []).find(function (x) { return x.id === id; });
          if (!o) return;
          document.getElementById('modal-title').textContent = 'Edit operator';
          document.getElementById('f-id').value = o.id;
          document.getElementById('f-name').value = o.name;
          document.getElementById('f-emp').value = o.employee_id;
          document.getElementById('f-dept').value = o.department || '';
          if (me.role === 'super_admin') {
            document.getElementById('f-co-wrap').style.display = 'block';
            tmApi('companies_list', {}, true).then(function (d) {
              var sel = document.getElementById('f-co');
              sel.innerHTML = '';
              (d.companies || []).forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
              });
              sel.value = String(o.company_id);
              loadWarehouses(o.company_id, function () {
                document.getElementById('f-wh').value = String(o.warehouse_id);
                document.getElementById('modal').style.display = 'flex';
              });
            });
          } else {
            document.getElementById('f-co-wrap').style.display = 'none';
            loadWarehouses(me.company_id, function () {
              document.getElementById('f-wh').value = String(o.warehouse_id);
              document.getElementById('modal').style.display = 'flex';
            });
          }
        });
      }

      document.getElementById('f-co').addEventListener('change', function () {
        loadWarehouses(parseInt(document.getElementById('f-co').value, 10));
      });

      document.getElementById('btn-add').addEventListener('click', openAdd);
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('f-save').addEventListener('click', function () {
        var payload = {
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          name: document.getElementById('f-name').value.trim(),
          employee_id: document.getElementById('f-emp').value.trim(),
          department: document.getElementById('f-dept').value.trim(),
          warehouse_id: parseInt(document.getElementById('f-wh').value, 10)
        };
        if (me.role === 'super_admin') {
          payload.company_id = parseInt(document.getElementById('f-co').value, 10);
        }
        tmApi('operator_save', payload, true).then(function (r) {
          if (r.ok) {
            document.getElementById('modal').style.display = 'none';
            load();
          } else {
            alert(r.error || 'Save failed');
          }
        });
      });

      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });

      tmApi('me', {}, true).then(function (data) {
        if (!data.logged_in) {
          window.location.href = 'login.php';
          return;
        }
        me.role = data.role;
        me.company_id = data.company_id;
        me.warehouse_id = data.warehouse_id;
        load();
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
