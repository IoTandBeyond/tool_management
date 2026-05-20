<?php $active = 'measurement'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Measurement equipment</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Measurement equipment</h1>
    <p class="sub">Calibrated tools and gauges — must be returned by operators. Send units to external maintenance providers when needed.</p>
    <div class="row-actions" style="margin-bottom: 1rem;">
      <button type="button" class="btn btn-primary" id="btn-add">Add equipment</button>
    </div>
    <div class="tools-list-toolbar">
      <div class="tools-list-search">
        <label for="list-search">Search</label>
        <input type="search" id="list-search" placeholder="Name, barcode, NFC, brand, location…" autocomplete="off">
      </div>
      <div>
        <label for="list-category">Category</label>
        <select id="list-category"><option value="">All categories</option></select>
      </div>
      <div>
        <label for="list-page-size">Rows per page</label>
        <select id="list-page-size">
          <option value="15" selected>15</option>
          <option value="30">30</option>
          <option value="50">50</option>
        </select>
      </div>
    </div>
    <p class="sub" id="list-meta" style="margin: 0 0 0.75rem;"></p>
    <div class="card" style="overflow-x: auto;">
      <table>
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Barcode</th>
            <th>UoM</th>
            <th>Range</th>
            <th>Brand / model</th>
            <th>Location</th>
            <th>Stock</th>
            <th class="col-catalog">Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div class="tools-list-pager">
      <p class="sub" id="list-page-info" style="margin: 0;"></p>
      <div class="row-actions">
        <button type="button" class="btn btn-secondary" id="list-prev">Previous</button>
        <button type="button" class="btn btn-secondary" id="list-next">Next</button>
      </div>
    </div>
  </div>

  <div id="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 20; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto;">
      <h2 id="modal-title">Equipment</h2>
      <input type="hidden" id="f-id">
      <label for="f-name">Name</label>
      <input type="text" id="f-name">
      <label for="f-barcode" style="margin-top: 0.75rem;">Barcode</label>
      <input type="text" id="f-barcode">
      <label for="f-nfc" style="margin-top: 0.75rem;">NFC ID (optional)</label>
      <input type="text" id="f-nfc">
      <label for="f-uom" style="margin-top: 0.75rem;">UoM</label>
      <input type="text" id="f-uom" placeholder="e.g. each, set">
      <label for="f-range" style="margin-top: 0.75rem;">Range</label>
      <input type="text" id="f-range">
      <label for="f-brand" style="margin-top: 0.75rem;">Brand / model</label>
      <input type="text" id="f-brand">
      <label for="f-condition" style="margin-top: 0.75rem;">Condition</label>
      <input type="text" id="f-condition" placeholder="e.g. Good, Fair">
      <label for="f-location" style="margin-top: 0.75rem;">Location</label>
      <input type="text" id="f-location">
      <label for="f-last-maint" style="margin-top: 0.75rem;">Last maintenance</label>
      <input type="date" id="f-last-maint">
      <fieldset class="form-fieldset-catalog">
        <legend>Catalog</legend>
        <label class="label-check" for="f-missing">
          <input type="checkbox" id="f-missing"> <span>Marked missing</span>
        </label>
        <label class="label-check" for="f-active">
          <input type="checkbox" id="f-active" checked> <span>Active in catalog</span>
        </label>
      </fieldset>
      <label for="f-wh" style="margin-top: 0.75rem;">Warehouse</label>
      <select id="f-wh"></select>
      <label for="f-stock" style="margin-top: 0.75rem;">Stock quantity</label>
      <input type="number" id="f-stock" min="0" step="1" value="1">
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

  <div id="maint-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 25; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 440px; width: 100%;">
      <h2 id="maint-title">Send to maintenance</h2>
      <input type="hidden" id="maint-tool-id">
      <label for="maint-provider">Maintenance provider (company)</label>
      <select id="maint-provider"></select>
      <label for="maint-notes" style="margin-top: 0.75rem;">Notes (optional)</label>
      <textarea id="maint-notes"></textarea>
      <div class="row-actions" style="margin-top: 1rem;">
        <button type="button" class="btn btn-primary" id="maint-save">Confirm</button>
        <button type="button" class="btn btn-secondary" id="maint-close">Close</button>
      </div>
    </div>
  </div>

  <div id="img-lightbox" class="img-lightbox" aria-hidden="true">
    <img id="img-lightbox-img" src="" alt="">
  </div>

  <script src="assets/js/api.js"></script>
  <script src="assets/js/tools-list-ui.js"></script>
  <script>
    (function () {
      var me = { role: '', company_id: null, warehouse_id: null, measurement_company_id: 2, show_measurement_nav: false };
      var toolsCache = [];
      var categoriesCache = [];
      var editingTool = null;

      function companyId() {
        return me.measurement_company_id;
      }

      function canAccess() {
        return !!me.show_measurement_nav;
      }

      function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
      }

      function catalogCell(t) {
        var st = t.catalog_status;
        if (st === 'maintenance') {
          var prov = t.maintenance_provider_name ? ' (' + t.maintenance_provider_name + ')' : '';
          return '<td class="col-catalog"><span class="badge badge-maintenance">Maintenance</span><br><span class="sub">' + esc(prov) + '</span></td>';
        }
        if (st === 'missing') return '<td class="col-catalog"><span class="badge badge-missing">Missing</span></td>';
        if (st === 'inactive') return '<td class="col-catalog"><span class="badge badge-out">Inactive</span></td>';
        return '<td class="col-catalog"><span class="badge badge-available">OK</span></td>';
      }

      function photoCell(t) {
        if (!t.image) return '<td class="tool-photo-cell"><span class="tool-no-photo">—</span></td>';
        var src = esc(t.image);
        return '<td class="tool-photo-cell"><button type="button" class="tool-thumb-btn" data-full="' + src + '">' +
          '<img src="' + src + '" alt="" class="tool-list-thumb" loading="lazy"></button></td>';
      }

      function stockCell(t) {
        if (t.stock_qty != null) return '<td>' + esc(String(t.stock_qty)) + '</td>';
        var a = t.assignments || [];
        if (!a.length) return '<td>—</td>';
        return '<td>' + a.map(function (x) { return esc(String(x.stock_qty)); }).join('<br>') + '</td>';
      }

      function actionsCell(t) {
        var id = t.id;
        var html = '<button type="button" class="btn btn-ghost btn-edit" data-id="' + id + '">Edit</button> ';
        if (t.maintenance_status === 'in_maintenance') {
          html += '<button type="button" class="btn btn-primary btn-maint-in" data-id="' + id + '">Return from maintenance</button>';
        } else {
          html += '<button type="button" class="btn btn-secondary btn-maint-out" data-id="' + id + '">Send to maintenance</button> ';
          html += '<button type="button" class="btn btn-danger btn-del" data-id="' + id + '">Delete</button>';
        }
        return '<td>' + html + '</td>';
      }

      function loadWarehouses(cb) {
        tmApi('warehouses_list', { company_id: companyId() }, true).then(function (d) {
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

      function loadProviders(cb) {
        tmApi('maintenance_providers_list', { company_id: companyId() }, true).then(function (d) {
          var sel = document.getElementById('maint-provider');
          sel.innerHTML = '<option value="">Select provider…</option>';
          (d.providers || []).forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            sel.appendChild(o);
          });
          if (cb) cb();
        });
      }

      function loadCategories() {
        return tmApi('categories_list', { company_id: companyId() }, true).then(function (data) {
          categoriesCache = data.categories || [];
          listView.populateCategories(categoriesCache);
        });
      }

      var listView = tmToolsListView({
        getItems: function () { return toolsCache; },
        emptyColSpan: 10,
        elements: {
          tbody: document.getElementById('tbody'),
          search: document.getElementById('list-search'),
          category: document.getElementById('list-category'),
          pageSize: document.getElementById('list-page-size'),
          btnPrev: document.getElementById('list-prev'),
          btnNext: document.getElementById('list-next'),
          meta: document.getElementById('list-meta'),
          pageInfo: document.getElementById('list-page-info')
        },
        renderRows: function (items, tbody) {
          items.forEach(function (t) {
            var tr = document.createElement('tr');
            tr.innerHTML =
              photoCell(t) +
              '<td>' + esc(t.name) + '</td>' +
              '<td>' + esc(t.barcode) + '</td>' +
              '<td>' + esc(t.uom || '—') + '</td>' +
              '<td>' + esc(t.range_spec || '—') + '</td>' +
              '<td>' + esc(t.brand_model || '—') + '</td>' +
              '<td>' + esc(t.location || '—') + '</td>' +
              stockCell(t) +
              catalogCell(t) +
              actionsCell(t);
            tbody.appendChild(tr);
          });
        },
        afterRender: function () { bindTableActions(); }
      });
      listView.wire();

      function loadTools() {
        tmApi('tools_list', { company_id: companyId(), asset_type: 'measurement' }, true).then(function (data) {
          toolsCache = data.tools || [];
          listView.resetPage();
          listView.render();
        });
      }

      function bindTableActions() {
        var tb = document.getElementById('tbody');
        tb.querySelectorAll('.tool-thumb-btn').forEach(function (b) {
          b.addEventListener('click', function () {
            document.getElementById('img-lightbox-img').src = b.getAttribute('data-full');
            document.getElementById('img-lightbox').classList.add('is-open');
          });
        });
        tb.querySelectorAll('.btn-edit').forEach(function (b) {
          b.addEventListener('click', function () { openEdit(parseInt(b.dataset.id, 10)); });
        });
        tb.querySelectorAll('.btn-del').forEach(function (b) {
          b.addEventListener('click', function () {
            if (!confirm('Delete this equipment?')) return;
            tmApi('tool_delete', { id: parseInt(b.dataset.id, 10) }, true).then(function (r) {
              if (r.ok) loadTools();
              else alert(r.error || 'Delete failed');
            });
          });
        });
        tb.querySelectorAll('.btn-maint-out').forEach(function (b) {
          b.addEventListener('click', function () {
            document.getElementById('maint-title').textContent = 'Send to maintenance';
            document.getElementById('maint-tool-id').value = b.dataset.id;
            document.getElementById('maint-notes').value = '';
            loadProviders(function () {
              document.getElementById('maint-modal').style.display = 'flex';
            });
          });
        });
        tb.querySelectorAll('.btn-maint-in').forEach(function (b) {
          b.addEventListener('click', function () {
            if (!confirm('Mark this equipment as returned from maintenance?')) return;
            tmApi('tool_return_maintenance', { tool_id: parseInt(b.dataset.id, 10) }, true).then(function (r) {
              if (r.ok) loadTools();
              else alert(r.error || 'Failed');
            });
          });
        });
      }

      function openAdd() {
        editingTool = null;
        document.getElementById('modal-title').textContent = 'Add equipment';
        document.getElementById('f-id').value = '';
        ['f-name', 'f-barcode', 'f-nfc', 'f-uom', 'f-range', 'f-brand', 'f-condition', 'f-location', 'f-last-maint'].forEach(function (id) {
          document.getElementById(id).value = '';
        });
        document.getElementById('f-missing').checked = false;
        document.getElementById('f-active').checked = true;
        document.getElementById('f-stock').value = '1';
        document.getElementById('f-image-path').value = '';
        document.getElementById('f-image-file').value = '';
        document.getElementById('f-remove-image').checked = false;
        document.getElementById('f-image-status').textContent = '';
        loadWarehouses(function () {
          document.getElementById('modal').style.display = 'flex';
        });
      }

      function openEdit(id) {
        var t = toolsCache.find(function (x) { return Number(x.id) === Number(id); });
        if (!t) { alert('Not found'); return; }
        editingTool = t;
        document.getElementById('modal-title').textContent = 'Edit equipment';
        document.getElementById('f-id').value = String(t.id);
        document.getElementById('f-name').value = t.name || '';
        document.getElementById('f-barcode').value = t.barcode || '';
        document.getElementById('f-nfc').value = t.nfc_id || '';
        document.getElementById('f-uom').value = t.uom || '';
        document.getElementById('f-range').value = t.range_spec || '';
        document.getElementById('f-brand').value = t.brand_model || '';
        document.getElementById('f-condition').value = t.tool_condition || '';
        document.getElementById('f-location').value = t.location || '';
        document.getElementById('f-last-maint').value = t.last_maintenance ? String(t.last_maintenance).slice(0, 10) : '';
        document.getElementById('f-missing').checked = Number(t.missing_flag) === 1;
        document.getElementById('f-active').checked = Number(t.is_active) !== 0;
        document.getElementById('f-image-path').value = t.image || '';
        document.getElementById('f-image-status').textContent = t.image ? 'Picture on file.' : '';
        loadWarehouses(function () {
          var whSel = document.getElementById('f-wh');
          var defaultWh = tmDefaultEditWarehouseId(t, me.warehouse_id);
          if (defaultWh) whSel.value = defaultWh;
          tmApplyEditStockFields(t, whSel, document.getElementById('f-stock'));
          document.getElementById('modal').style.display = 'flex';
        });
      }

      function buildPayload() {
        var payload = {
          asset_type: 'measurement',
          company_id: companyId(),
          id: document.getElementById('f-id').value ? parseInt(document.getElementById('f-id').value, 10) : undefined,
          name: document.getElementById('f-name').value.trim(),
          barcode: document.getElementById('f-barcode').value.trim(),
          nfc_id: document.getElementById('f-nfc').value.trim(),
          uom: document.getElementById('f-uom').value.trim(),
          range_spec: document.getElementById('f-range').value.trim(),
          brand_model: document.getElementById('f-brand').value.trim(),
          tool_condition: document.getElementById('f-condition').value.trim(),
          location: document.getElementById('f-location').value.trim(),
          last_maintenance: document.getElementById('f-last-maint').value || null,
          missing_flag: document.getElementById('f-missing').checked,
          is_active: document.getElementById('f-active').checked,
          warehouse_id: parseInt(document.getElementById('f-wh').value, 10) || 0,
          stock_qty: parseInt(document.getElementById('f-stock').value, 10)
        };
        var path = document.getElementById('f-image-path').value.trim();
        if (document.getElementById('f-remove-image').checked) payload.image = null;
        else if (path) payload.image = path;
        return payload;
      }

      document.getElementById('f-wh').addEventListener('change', function () {
        if (editingTool) {
          tmApplyEditStockFields(editingTool, document.getElementById('f-wh'), document.getElementById('f-stock'));
        }
      });
      document.getElementById('btn-add').addEventListener('click', openAdd);
      document.getElementById('f-close').addEventListener('click', function () {
        document.getElementById('modal').style.display = 'none';
      });
      document.getElementById('maint-close').addEventListener('click', function () {
        document.getElementById('maint-modal').style.display = 'none';
      });
      document.getElementById('maint-save').addEventListener('click', function () {
        var tid = parseInt(document.getElementById('maint-tool-id').value, 10);
        var pid = parseInt(document.getElementById('maint-provider').value, 10);
        if (!pid) { alert('Select a maintenance provider'); return; }
        tmApi('tool_send_maintenance', {
          tool_id: tid,
          provider_company_id: pid,
          notes: document.getElementById('maint-notes').value.trim()
        }, true).then(function (r) {
          if (r.ok) {
            document.getElementById('maint-modal').style.display = 'none';
            loadTools();
          } else alert(r.error || 'Failed');
        });
      });
      document.getElementById('f-save').addEventListener('click', function () {
        var payload = buildPayload();
        if (!payload.name || !payload.barcode) { alert('Name and barcode required'); return; }
        if (!payload.id && (!payload.warehouse_id || payload.stock_qty == null)) {
          alert('Warehouse and stock required'); return;
        }
        tmApi('tool_save', payload, true).then(function (r) {
          if (r.ok) {
            document.getElementById('modal').style.display = 'none';
            loadTools();
          } else alert(r.error || 'Save failed');
        });
      });
      document.getElementById('f-image-file').addEventListener('change', function () {
        var f = document.getElementById('f-image-file').files[0];
        if (!f) return;
        tmUploadToolImage(f).then(function (r) {
          if (r.ok && r.path) {
            document.getElementById('f-image-path').value = r.path;
            document.getElementById('f-image-status').textContent = 'Uploaded — save to attach.';
          } else alert(r.error || 'Upload failed');
        });
      });
      document.getElementById('img-lightbox').addEventListener('click', function (e) {
        if (e.target.id === 'img-lightbox-img') return;
        document.getElementById('img-lightbox').classList.remove('is-open');
      });
      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () { window.location.href = 'login.php'; });
      });

      tmApi('me', {}, true).then(function (data) {
        if (!data.logged_in) { window.location.href = 'login.php'; return; }
        me.role = data.role;
        me.company_id = data.company_id;
        me.warehouse_id = data.warehouse_id;
        me.measurement_company_id = data.measurement_company_id || 2;
        me.show_measurement_nav = !!data.show_measurement_nav;
        if (!canAccess()) {
          window.location.href = 'dashboard.php';
          return;
        }
        loadCategories().then(loadTools);
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
