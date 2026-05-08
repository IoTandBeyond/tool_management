<?php $active = 'companies'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Companies</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Companies</h1>
    <p class="sub">Create and edit companies (super admin only).</p>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add">Add company</button>
    </div>
    <div class="card" style="overflow-x: auto;">
      <table class="companies-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Address</th>
            <th>Telephone</th>
            <th>Contact</th>
            <th>Contact phone</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>
  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto;">
      <h2 id="modal-title">Company</h2>
      <input type="hidden" id="f-id">
      <label for="f-name">Name</label>
      <input type="text" id="f-name">
      <label for="f-address" style="margin-top: 0.75rem;">Address</label>
      <textarea id="f-address" rows="2" placeholder="Street, city, region"></textarea>
      <label for="f-telephone" style="margin-top: 0.75rem;">Telephone</label>
      <input type="text" id="f-telephone" autocomplete="tel" placeholder="Main company line">
      <label for="f-contact-name" style="margin-top: 0.75rem;">Contact name</label>
      <input type="text" id="f-contact-name" autocomplete="name" placeholder="Primary contact person">
      <label for="f-contact-phone" style="margin-top: 0.75rem;">Contact phone</label>
      <input type="text" id="f-contact-phone" autocomplete="tel" placeholder="Direct / mobile">
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="f-save">Save</button>
        <button type="button" class="btn btn-secondary" id="f-close">Close</button>
      </div>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var lastCompanies = [];
      function cell(s) {
        return escapeHtml(s || '—');
      }
      function load() {
        tmApi('companies_list', {}, true).then(function (d) {
          if (!d.ok) { window.location.href = 'dashboard.php'; return; }
          lastCompanies = d.companies || [];
          var tb = document.getElementById('tbody');
          tb.innerHTML = '';
          lastCompanies.forEach(function (c) {
            var tr = document.createElement('tr');
            tr.innerHTML =
              '<td>' + cell(c.name) + '</td>' +
              '<td class="companies-cell-address">' + cell(c.address) + '</td>' +
              '<td>' + cell(c.telephone) + '</td>' +
              '<td>' + cell(c.contact_name) + '</td>' +
              '<td>' + cell(c.contact_phone) + '</td>' +
              '<td><button type="button" class="btn btn-ghost btn-edit" data-id="' + c.id + '">Edit</button></td>';
            tb.appendChild(tr);
          });
          tb.querySelectorAll('.btn-edit').forEach(function (b) {
            b.addEventListener('click', function () {
              var row = lastCompanies.find(function (x) { return x.id === parseInt(b.dataset.id, 10); });
              if (!row) return;
              document.getElementById('modal-title').textContent = 'Edit company';
              document.getElementById('f-id').value = row.id;
              document.getElementById('f-name').value = row.name || '';
              document.getElementById('f-address').value = row.address || '';
              document.getElementById('f-telephone').value = row.telephone || '';
              document.getElementById('f-contact-name').value = row.contact_name || '';
              document.getElementById('f-contact-phone').value = row.contact_phone || '';
              document.getElementById('modal').style.display = 'flex';
            });
          });
        });
      }
      function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
      }
      function clearForm() {
        document.getElementById('f-id').value = '';
        document.getElementById('f-name').value = '';
        document.getElementById('f-address').value = '';
        document.getElementById('f-telephone').value = '';
        document.getElementById('f-contact-name').value = '';
        document.getElementById('f-contact-phone').value = '';
      }
      document.getElementById('btn-add').addEventListener('click', function () {
        document.getElementById('modal-title').textContent = 'Add company';
        clearForm();
        document.getElementById('modal').style.display = 'flex';
      });
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('f-save').addEventListener('click', function () {
        var payload = {
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          name: document.getElementById('f-name').value.trim(),
          address: document.getElementById('f-address').value.trim(),
          telephone: document.getElementById('f-telephone').value.trim(),
          contact_name: document.getElementById('f-contact-name').value.trim(),
          contact_phone: document.getElementById('f-contact-phone').value.trim()
        };
        tmApi('company_save', payload, true).then(function (r) {
          if (r.ok) { document.getElementById('modal').style.display = 'none'; load(); }
          else alert(r.error || 'Save failed');
        });
      });
      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });
      tmApi('me', {}, true).then(function (m) {
        if (!m.logged_in || m.role !== 'super_admin') { window.location.href = 'dashboard.php'; return; }
        load();
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
