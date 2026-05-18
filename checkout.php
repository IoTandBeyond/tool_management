<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Borrow tools</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-home="index.php">
  <div class="theme-toggle-float">
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
  </div>
  <div class="wrap">
    <h1>Borrow tools</h1>
    <p class="sub" id="operator-line"></p>

    <div id="borrowed-block" class="card" style="margin-bottom: 1rem;">
      <h2>Currently on loan to you</h2>
      <p class="sub" id="borrowed-hint" style="display: none;">Tap <strong>Return</strong> on a row, then scan that tool’s barcode or NFC to check it in.</p>
      <div id="borrowed-empty" class="sub" style="display: none;">No open loans — scan a tool below to borrow.</div>
      <table id="borrowed-table" style="display: none;">
        <thead><tr><th class="tool-icon-cell" aria-label="Photo"></th><th>Tool</th><th>Barcode</th><th>Checked out</th><th>Due back</th><th></th></tr></thead>
        <tbody id="borrowed-body"></tbody>
      </table>
      <div id="return-panel" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.08);">
        <h3 style="margin: 0 0 0.5rem; font-size: 1rem;">Confirm return</h3>
        <p class="sub" id="return-hint"></p>
        <label for="return-scan">Scan barcode / NFC (must match this tool)</label>
        <input type="text" id="return-scan" autocomplete="off" placeholder="Scan to match">
        <div id="return-msg" style="margin-top: 0.75rem;"></div>
        <div class="row-actions" style="margin-top: 0.75rem;">
          <button type="button" class="btn btn-secondary" id="cancel-return">Cancel</button>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Scan tools to borrow</h2>
      <p class="sub">Scans are added to the list below. Nothing is checked out until you tap <strong>Confirm borrow</strong>. Use <strong>Exit</strong> to leave without borrowing.</p>
      <label for="scan">Barcode / NFC</label>
      <input type="text" id="scan" autocomplete="off" placeholder="Scan or enter code">
      <div id="scan-msg" style="margin-top: 0.75rem;"></div>
    </div>

    <div id="queue-wrap" class="card checkout-queue" style="display: none; margin-top: 1rem;">
      <h2>Ready to borrow</h2>
      <p class="sub">Review the list, then confirm. Stock is checked when you confirm.</p>
      <div style="overflow-x: auto;">
        <table>
          <thead><tr><th>Tool</th><th>Barcode</th><th></th></tr></thead>
          <tbody id="queue-body"></tbody>
        </table>
      </div>
    </div>

    <div class="row-actions" style="margin-top: 1.5rem;">
      <button type="button" class="btn btn-primary" id="btn-confirm-borrow">Confirm borrow</button>
      <a class="btn btn-secondary" id="kiosk-cancel" href="index.php">Exit</a>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script src="assets/js/kiosk-idle.js"></script>
  <script>
    (function () {
      var KIOSK_WH_KEY = 'tm_kiosk_warehouse_id';
      var params = new URLSearchParams(window.location.search);
      var operatorId = parseInt(params.get('operator_id') || '0', 10);
      var warehouseId = parseInt(params.get('warehouse_id') || '0', 10);
      if (!warehouseId) {
        try {
          warehouseId = parseInt(localStorage.getItem(KIOSK_WH_KEY) || '0', 10);
        } catch (e) {}
      }
      var name = params.get('name') || 'Operator';
      if (!operatorId || !warehouseId) {
        window.location.href = 'index.php' + (warehouseId ? '?warehouse_id=' + encodeURIComponent(warehouseId) : '');
        return;
      }
      try {
        localStorage.setItem(KIOSK_WH_KEY, String(warehouseId));
      } catch (e) {}
      var homeQs = '?warehouse_id=' + encodeURIComponent(warehouseId);
      document.getElementById('kiosk-cancel').href = 'index.php' + homeQs;

      document.getElementById('operator-line').textContent = name + ' — add scans to your borrow list, then confirm. Return open loans from the table above.';

      var scanInput = document.getElementById('scan');
      var scanMsg = document.getElementById('scan-msg');
      var queueWrap = document.getElementById('queue-wrap');
      var queueBody = document.getElementById('queue-body');
      var borrowQueue = [];
      var returnPanel = document.getElementById('return-panel');
      var returnScan = document.getElementById('return-scan');
      var returnMsg = document.getElementById('return-msg');
      var pendingReturnToolId = null;

      function showMsg(text, ok) {
        scanMsg.innerHTML = '';
        if (!text) return;
        var d = document.createElement('div');
        d.className = 'msg ' + (ok ? 'msg-success' : 'msg-error');
        d.textContent = text;
        scanMsg.appendChild(d);
      }

      function showReturnMsg(text, ok) {
        returnMsg.innerHTML = '';
        if (!text) return;
        var d = document.createElement('div');
        d.className = 'msg ' + (ok ? 'msg-success' : 'msg-error');
        d.textContent = text;
        returnMsg.appendChild(d);
      }

      function openReturn(toolId, toolName, barcode) {
        pendingReturnToolId = toolId;
        document.getElementById('return-hint').textContent = 'Returning: ' + toolName + ' (expected barcode: ' + barcode + ')';
        showReturnMsg('', true);
        returnScan.value = '';
        returnPanel.style.display = 'block';
        document.getElementById('borrowed-hint').style.display = 'block';
        scanInput.disabled = true;
        returnScan.focus();
      }

      function closeReturn() {
        pendingReturnToolId = null;
        returnPanel.style.display = 'none';
        showReturnMsg('', true);
        scanInput.disabled = false;
        scanInput.focus();
      }

      function loadBorrowed() {
        tmApi('operator_borrowed', { operator_id: operatorId }).then(function (data) {
          if (!data.ok) return;
          var rows = data.borrowed || [];
          var empty = document.getElementById('borrowed-empty');
          var table = document.getElementById('borrowed-table');
          var hint = document.getElementById('borrowed-hint');
          var body = document.getElementById('borrowed-body');
          body.innerHTML = '';
          if (!rows.length) {
            empty.style.display = 'block';
            table.style.display = 'none';
            hint.style.display = 'none';
            closeReturn();
            return;
          }
          empty.style.display = 'none';
          table.style.display = 'table';
          hint.style.display = 'block';
          rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var tdIcon = document.createElement('td');
            tdIcon.className = 'tool-icon-cell';
            if (r.tool_image) {
              var im = document.createElement('img');
              im.src = r.tool_image;
              im.alt = '';
              im.className = 'checkout-tool-icon';
            } else {
              var im = document.createElement('div');
              im.className = 'checkout-tool-icon checkout-tool-icon--empty';
              im.setAttribute('aria-hidden', 'true');
            }
            tdIcon.appendChild(im);
            var td0 = document.createElement('td');
            td0.textContent = r.tool_name;
            var td1 = document.createElement('td');
            td1.textContent = r.barcode;
            var td2 = document.createElement('td');
            td2.textContent = tmFormatDt(r.checkout_at);
            var td3 = document.createElement('td');
            var returnRequired = Number(r.return_required) === 1 || r.asset_type === 'measurement';
            td3.textContent = returnRequired ? tmFormatDt(r.expected_return_at) : '—';
            var td4 = document.createElement('td');
            if (returnRequired) {
              var btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'btn btn-primary';
              btn.textContent = 'Return';
              btn.addEventListener('click', function () {
                openReturn(r.tool_id, r.tool_name, r.barcode);
              });
              td4.appendChild(btn);
            } else {
              td4.textContent = 'Consumable';
            }
            tr.appendChild(tdIcon);
            tr.appendChild(td0);
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tr.appendChild(td4);
            body.appendChild(tr);
          });
        });
      }

      function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      function countInQueueForTool(toolId) {
        return borrowQueue.filter(function (q) { return q.toolId === toolId; }).length;
      }

      function renderQueue() {
        queueBody.innerHTML = '';
        borrowQueue.forEach(function (item, idx) {
          var tr = document.createElement('tr');
          tr.innerHTML =
            '<td>' + escapeHtml(item.name) + '</td>' +
            '<td>' + escapeHtml(item.barcode) + '</td>' +
            '<td><button type="button" class="btn btn-ghost queue-remove" data-idx="' + idx + '">Remove</button></td>';
          queueBody.appendChild(tr);
        });
        queueWrap.style.display = borrowQueue.length ? 'block' : 'none';
        queueBody.querySelectorAll('.queue-remove').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var i = parseInt(btn.getAttribute('data-idx'), 10);
            borrowQueue.splice(i, 1);
            renderQueue();
            scanInput.focus();
          });
        });
      }

      function addScanToQueue() {
        var code = (scanInput.value || '').trim();
        if (!code) return;
        showMsg('', true);
        tmApi('kiosk_tool_lookup', { operator_id: operatorId, warehouse_id: warehouseId, code: code }).then(function (data) {
          scanInput.value = '';
          scanInput.focus();
          if (!data.ok) {
            showMsg(data.error || 'Lookup failed', false);
            return;
          }
          var tid = data.tool.id;
          var already = countInQueueForTool(tid);
          if (already + 1 > data.stock_qty) {
            showMsg('Only ' + data.stock_qty + ' available at this warehouse for ' + data.tool.name + ' (including items already in your list).', false);
            return;
          }
          borrowQueue.push({
            code: code,
            toolId: tid,
            name: data.tool.name,
            barcode: data.tool.barcode
          });
          renderQueue();
          showMsg('Added: ' + data.tool.name + ' — ' + (borrowQueue.length) + ' line(s) in list.', true);
        }).catch(function () {
          showMsg('Network error', false);
        });
      }

      scanInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          addScanToQueue();
        }
      });

      document.getElementById('cancel-return').addEventListener('click', closeReturn);

      returnScan.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var code = (returnScan.value || '').trim();
        if (!code || !pendingReturnToolId) return;
        tmApi('return_tool', {
          operator_id: operatorId,
          tool_id: pendingReturnToolId,
          code: code
        }).then(function (data) {
          returnScan.value = '';
          if (data.ok) {
            showReturnMsg('Returned: ' + (data.tool && data.tool.name ? data.tool.name : 'tool'), true);
            closeReturn();
            loadBorrowed();
          } else {
            showReturnMsg(data.error || 'Return failed', false);
            returnScan.focus();
          }
        }).catch(function () {
          showReturnMsg('Network error', false);
        });
      });

      document.getElementById('btn-confirm-borrow').addEventListener('click', function () {
        if (!borrowQueue.length) {
          window.location.href = 'index.php' + homeQs;
          return;
        }
        var codes = borrowQueue.map(function (q) { return q.code; });
        showMsg('Processing…', true);
        document.getElementById('btn-confirm-borrow').disabled = true;
        tmApi('checkout_batch', { operator_id: operatorId, warehouse_id: warehouseId, codes: codes }).then(function (data) {
          document.getElementById('btn-confirm-borrow').disabled = false;
          if (!data.ok) {
            showMsg(data.error || 'Borrow failed', false);
            return;
          }
          borrowQueue = [];
          renderQueue();
          showMsg('Checked out ' + (data.count || codes.length) + ' item(s). Due back: ' + tmFormatDt(data.expected_return_at), true);
          loadBorrowed();
          scanInput.focus();
        }).catch(function () {
          document.getElementById('btn-confirm-borrow').disabled = false;
          showMsg('Network error', false);
        });
      });

      loadBorrowed();
      scanInput.focus();
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
