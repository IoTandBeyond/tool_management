<?php $active = 'tools'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Tools</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Tools</h1>
    <p class="sub">Company catalog and per-warehouse stock. Barcode is unique per company.</p>
    <div id="company-row" class="row-actions" style="margin-bottom: 1rem; display: none;">
      <label for="company-select">Company</label>
      <select id="company-select"></select>
      <button type="button" class="btn btn-secondary" id="btn-company-apply">Load</button>
    </div>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add">Add tool</button>
    </div>
    <div class="card" style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Barcode</th>
            <th>Warehouse</th>
            <th>Stock</th>
            <th>Category</th>
            <th class="col-catalog">Catalog</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
  </div>

  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto;">
      <h2 id="modal-title">Tool</h2>
      <input type="hidden" id="f-id">
      <label for="f-name">Name</label>
      <input type="text" id="f-name">
      <label for="f-barcode" style="margin-top: 0.75rem;">Barcode</label>
      <input type="text" id="f-barcode">
      <label for="f-nfc" style="margin-top: 0.75rem;">NFC ID (optional)</label>
      <input type="text" id="f-nfc">
      <label for="f-cat" style="margin-top: 0.75rem;">Category</label>
      <select id="f-cat"></select>
      <label for="f-desc" style="margin-top: 0.75rem;">Description</label>
      <textarea id="f-desc"></textarea>
      <fieldset class="form-fieldset-catalog">
        <legend>Catalog</legend>
        <label class="label-check" for="f-missing">
          <input type="checkbox" id="f-missing"> <span>Marked missing</span>
        </label>
        <label class="label-check" for="f-active">
          <input type="checkbox" id="f-active" checked> <span>Active in catalog</span>
        </label>
      </fieldset>
      <div id="stock-block">
        <label for="f-wh" style="margin-top: 0.75rem;">Warehouse (for stock)</label>
        <select id="f-wh"></select>
        <label for="f-stock" style="margin-top: 0.75rem;">Stock quantity</label>
        <input type="number" id="f-stock" min="0" step="1" value="0">
      </div>
      <label for="f-image-file" style="margin-top: 0.75rem;">Picture</label>
      <input type="hidden" id="f-image-path" value="">
      <input type="file" id="f-image-file" accept="image/jpeg,image/png,image/webp,image/gif">
      <p class="sub" id="f-image-status" style="margin-top: 0.35rem;"></p>
      <label class="label-check" for="f-remove-image">
        <input type="checkbox" id="f-remove-image">
        <span>Remove picture</span>
      </label>
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="f-save">Save</button>
        <button type="button" class="btn btn-secondary" id="f-close">Close</button>
      </div>
    </div>
  </div>

  <div id="img-lightbox" class="img-lightbox" aria-hidden="true">
    <img id="img-lightbox-img" src="" alt="">
  </div>

  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var me = { role: '', company_id: null, warehouse_id: null };
      var toolsCache = [];

      function companyId() {
        if (me.role === 'super_admin') {
          var v = parseInt(document.getElementById('company-select').value, 10);
          return v || 0;
        }
        return me.company_id || 0;
      }

      function catalogCell(t) {
        var st = t.catalog_status;
        if (!st) {
          var m = Number(t.missing_flag);
          var a = Number(t.is_active);
          if (m === 1) st = 'missing';
          else if (a !== 1) st = 'inactive';
          else st = 'ok';
        }
        if (st === 'missing') return '<td class="col-catalog"><span class="badge badge-missing">Missing</span></td>';
        if (st === 'inactive') return '<td class="col-catalog"><span class="badge badge-out">Inactive</span></td>';
        return '<td class="col-catalog"><span class="badge badge-available">OK</span></td>';
      }

      function warehouseCell(t) {
        if (t.stock_qty !== undefined && t.stock_qty !== null) {
          return '<td>' + esc(t.warehouse_name || '—') + '</td>';
        }
        var a = t.assignments || [];
        if (!a.length) return '<td>—</td>';
        return '<td>' + a.map(function (x) { return esc(x.warehouse_name); }).join('<br>') + '</td>';
      }

      function stockQtyCell(t) {
        if (t.stock_qty !== undefined && t.stock_qty !== null) {
          return '<td>' + esc(String(t.stock_qty)) + '</td>';
        }
        var a = t.assignments || [];
        if (!a.length) return '<td>—</td>';
        return '<td>' + a.map(function (x) { return esc(String(x.stock_qty)); }).join('<br>') + '</td>';
      }

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      function photoCell(t) {
        if (!t.image) return '<td class="tool-photo-cell"><span class="tool-no-photo">—</span></td>';
        var src = esc(t.image);
        return '<td class="tool-photo-cell"><button type="button" class="tool-thumb-btn" data-full="' + src + '">' +
          '<img src="' + src + '" alt="" class="tool-list-thumb" loading="lazy"></button></td>';
      }

      function loadCategories() {
        var cid = companyId();
        if (cid < 1) return Promise.resolve();
        return tmApi('categories_list', { company_id: cid }, true).then(function (data) {
          var sel = document.getElementById('f-cat');
          sel.innerHTML = '<option value="">— None —</option>';
          (data.categories || []).forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            sel.appendChild(o);
          });
        });
      }

      function loadWarehouses(cb) {
        var cid = companyId();
        if (cid < 1) return;
        tmApi('warehouses_list', { company_id: cid }, true).then(function (d) {
          var sel = document.getElementById('f-wh');
          sel.innerHTML = '<option value="">—</option>';
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

      function loadTools() {
        var cid = companyId();
        if (cid < 1) {
          document.getElementById('tbody').innerHTML = '';
          return;
        }
        tmApi('tools_list', { company_id: cid }, true).then(function (data) {
          toolsCache = data.tools || [];
          var tb = document.getElementById('tbody');
          tb.innerHTML = '';
          toolsCache.forEach(function (t) {
            var tr = document.createElement('tr');
            tr.innerHTML =
              photoCell(t) +
              '<td>' + esc(t.name) + '</td>' +
              '<td>' + esc(t.barcode) + '</td>' +
              warehouseCell(t) +
              stockQtyCell(t) +
              '<td>' + esc(t.category_name || '—') + '</td>' +
              catalogCell(t) +
              '<td><button type="button" class="btn btn-ghost btn-edit" data-id="' + t.id + '">Edit</button> ' +
              '<button type="button" class="btn btn-danger btn-del" data-id="' + t.id + '">Delete</button></td>';
            tb.appendChild(tr);
          });
          tb.querySelectorAll('.tool-thumb-btn').forEach(function (b) {
            b.addEventListener('click', function (e) {
              e.preventDefault();
              var src = b.getAttribute('data-full');
              if (!src) return;
              document.getElementById('img-lightbox-img').src = src;
              document.getElementById('img-lightbox').classList.add('is-open');
            });
          });
          tb.querySelectorAll('.btn-edit').forEach(function (b) {
            b.addEventListener('click', function () { openEdit(parseInt(b.dataset.id, 10)); });
          });
          tb.querySelectorAll('.btn-del').forEach(function (b) {
            b.addEventListener('click', function () {
              if (!confirm('Delete this tool?')) return;
              tmApi('tool_delete', { id: parseInt(b.dataset.id, 10) }, true).then(function (r) {
                if (r.ok) loadTools();
                else alert(r.error || 'Delete failed');
              });
            });
          });
        });
      }

      function resetImageFields() {
        document.getElementById('f-image-path').value = '';
        document.getElementById('f-image-file').value = '';
        document.getElementById('f-remove-image').checked = false;
        document.getElementById('f-image-status').textContent = '';
      }

      function openAdd() {
        document.getElementById('modal-title').textContent = 'Add tool';
        document.getElementById('f-id').value = '';
        document.getElementById('f-name').value = '';
        document.getElementById('f-barcode').value = '';
        document.getElementById('f-nfc').value = '';
        document.getElementById('f-desc').value = '';
        document.getElementById('f-missing').checked = false;
        document.getElementById('f-active').checked = true;
        document.getElementById('f-stock').value = '1';
        resetImageFields();
        loadCategories().then(function () {
          loadWarehouses(function () {
            document.getElementById('modal').style.display = 'flex';
          });
        });
      }

      function openEdit(id) {
        var toolId = Number(id);
        var t = toolsCache.find(function (x) { return Number(x.id) === toolId; });
        if (!t) {
          alert('Tool not found. Reload the list and try again.');
          return;
        }
        document.getElementById('modal-title').textContent = 'Edit tool';
        document.getElementById('f-id').value = String(t.id);
        document.getElementById('f-name').value = t.name || '';
        document.getElementById('f-barcode').value = t.barcode || '';
        document.getElementById('f-nfc').value = t.nfc_id || '';
        document.getElementById('f-desc').value = t.description || '';
        document.getElementById('f-missing').checked = Number(t.missing_flag) === 1;
        document.getElementById('f-active').checked = Number(t.is_active) !== 0;
        document.getElementById('f-image-path').value = t.image || '';
        document.getElementById('f-image-file').value = '';
        document.getElementById('f-remove-image').checked = false;
        document.getElementById('f-image-status').textContent = t.image ? 'Picture on file.' : '';
        document.getElementById('f-stock').value = t.stock_qty != null ? String(t.stock_qty) : '';
        loadCategories().then(function () {
          document.getElementById('f-cat').value = t.category_id ? String(t.category_id) : '';
          loadWarehouses(function () {
            if (t.stock_qty != null && me.warehouse_id) {
              document.getElementById('f-wh').value = String(me.warehouse_id);
            } else if (t.assignments && t.assignments.length === 1) {
              document.getElementById('f-wh').value = String(t.assignments[0].warehouse_id);
            } else if (t.assignments && t.assignments.length > 1) {
              document.getElementById('f-wh').value = String(t.assignments[0].warehouse_id);
            }
            document.getElementById('modal').style.display = 'flex';
          });
        });
      }

      document.getElementById('btn-add').addEventListener('click', openAdd);
      document.getElementById('btn-company-apply').addEventListener('click', function () {
        loadCategories().then(loadTools);
      });
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('f-image-file').addEventListener('change', function () {
        var f = document.getElementById('f-image-file').files[0];
        document.getElementById('f-image-status').textContent = '';
        if (!f) return;
        document.getElementById('f-remove-image').checked = false;
        tmUploadToolImage(f).then(function (r) {
          if (r.ok && r.path) {
            document.getElementById('f-image-path').value = r.path;
            document.getElementById('f-image-status').textContent = 'Uploaded — save to attach.';
          } else {
            alert(r.error || 'Upload failed');
          }
        }).catch(function () { alert('Upload failed'); });
      });

      document.getElementById('f-save').addEventListener('click', function () {
        var cid = companyId();
        var payload = {
          company_id: me.role === 'super_admin' ? cid : undefined,
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          name: document.getElementById('f-name').value.trim(),
          barcode: document.getElementById('f-barcode').value.trim(),
          nfc_id: document.getElementById('f-nfc').value.trim(),
          category_id: document.getElementById('f-cat').value || null,
          description: document.getElementById('f-desc').value.trim(),
          missing_flag: document.getElementById('f-missing').checked,
          is_active: document.getElementById('f-active').checked
        };
        if (me.role === 'super_admin') payload.company_id = cid;
        var wh = parseInt(document.getElementById('f-wh').value, 10) || 0;
        var sq = document.getElementById('f-stock').value.trim();
        if (sq !== '') {
          payload.warehouse_id = wh;
          payload.stock_qty = parseInt(sq, 10);
        }
        var removePic = document.getElementById('f-remove-image').checked;
        var path = document.getElementById('f-image-path').value.trim();
        if (removePic) payload.image = null;
        else if (path) payload.image = path;

        if (!payload.id && (!payload.warehouse_id || payload.stock_qty == null)) {
          alert('Choose warehouse and stock for a new tool.');
          return;
        }

        tmApi('tool_save', payload, true).then(function (r) {
          if (r.ok) {
            document.getElementById('modal').style.display = 'none';
            loadTools();
          } else {
            alert(r.error || 'Save failed');
          }
        });
      });

      document.getElementById('img-lightbox').addEventListener('click', function (e) {
        if (e.target === document.getElementById('img-lightbox-img')) return;
        document.getElementById('img-lightbox').classList.remove('is-open');
        document.getElementById('img-lightbox-img').src = '';
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
        if (data.role === 'super_admin') {
          document.getElementById('company-row').style.display = 'flex';
          tmApi('companies_list', {}, true).then(function (d) {
            var sel = document.getElementById('company-select');
            (d.companies || []).forEach(function (c) {
              var o = document.createElement('option');
              o.value = c.id;
              o.textContent = c.name;
              sel.appendChild(o);
            });
            if (sel.options.length) {
              sel.selectedIndex = 0;
              loadCategories().then(loadTools);
            }
          });
        } else {
          loadCategories().then(loadTools);
        }
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
