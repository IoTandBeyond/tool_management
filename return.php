<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Return tools</title>
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
    <h1>Return tools</h1>
    <p class="sub" id="operator-line"></p>

    <div class="card">
      <h2>Your open loans</h2>
      <p id="none-msg" class="sub" style="display: none;">You have nothing to return. <a href="index.php">Back to home</a></p>
      <table id="loans-table" style="display: none;">
        <thead>
          <tr><th>Tool</th><th>Barcode</th><th>Checked out</th><th></th></tr>
        </thead>
        <tbody id="loans-body"></tbody>
      </table>
    </div>

    <div class="card" id="scan-panel" style="display: none; margin-top: 1rem;">
      <h2 id="scan-title">Confirm return</h2>
      <p class="sub" id="scan-hint"></p>
      <label for="scan">Scan same tool barcode / NFC</label>
      <input type="text" id="scan" autocomplete="off" placeholder="Scan to match">
      <div id="scan-msg" style="margin-top: 0.75rem;"></div>
      <div class="row-actions">
        <button type="button" class="btn btn-secondary" id="cancel-scan">Cancel</button>
      </div>
    </div>

    <div class="row-actions" style="margin-top: 1.5rem;">
      <a class="btn btn-primary" href="index.php">Done</a>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script src="assets/js/kiosk-idle.js"></script>
  <script>
    (function () {
      var params = new URLSearchParams(window.location.search);
      var operatorId = parseInt(params.get('operator_id') || '0', 10);
      var name = params.get('name') || 'Operator';
      if (!operatorId) {
        window.location.href = 'index.php';
        return;
      }

      document.getElementById('operator-line').textContent = name + ' — tap Return, then scan the tool.';

      var pendingToolId = null;
      var pendingName = '';
      var scanPanel = document.getElementById('scan-panel');
      var scanInput = document.getElementById('scan');
      var scanMsg = document.getElementById('scan-msg');

      function showMsg(text, ok) {
        scanMsg.innerHTML = '';
        if (!text) return;
        var d = document.createElement('div');
        d.className = 'msg ' + (ok ? 'msg-success' : 'msg-error');
        d.textContent = text;
        scanMsg.appendChild(d);
      }

      function loadLoans() {
        tmApi('operator_borrowed', { operator_id: operatorId }).then(function (data) {
          if (!data.ok) return;
          var rows = data.borrowed || [];
          var none = document.getElementById('none-msg');
          var table = document.getElementById('loans-table');
          var body = document.getElementById('loans-body');
          body.innerHTML = '';
          if (!rows.length) {
            none.style.display = 'block';
            table.style.display = 'none';
            return;
          }
          none.style.display = 'none';
          table.style.display = 'table';
          rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var btn = document.createElement('button');
            btn.className = 'btn btn-primary';
            btn.type = 'button';
            btn.textContent = 'Return';
            btn.addEventListener('click', function () {
              pendingToolId = r.tool_id;
              pendingName = r.tool_name;
              scanPanel.style.display = 'block';
              document.getElementById('scan-hint').textContent = 'Returning: ' + r.tool_name + ' (barcode ' + r.barcode + ')';
              scanMsg.innerHTML = '';
              scanInput.value = '';
              scanInput.focus();
            });
            var td0 = document.createElement('td');
            td0.textContent = r.tool_name;
            var td1 = document.createElement('td');
            td1.textContent = r.barcode;
            var td2 = document.createElement('td');
            td2.textContent = tmFormatDt(r.checkout_at);
            var td3 = document.createElement('td');
            td3.appendChild(btn);
            tr.appendChild(td0);
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            body.appendChild(tr);
          });
        });
      }

      function cancelScan() {
        pendingToolId = null;
        pendingName = '';
        scanPanel.style.display = 'none';
        scanMsg.innerHTML = '';
      }

      document.getElementById('cancel-scan').addEventListener('click', cancelScan);

      scanInput.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var code = (scanInput.value || '').trim();
        if (!code || !pendingToolId) return;
        tmApi('return_tool', {
          operator_id: operatorId,
          tool_id: pendingToolId,
          code: code
        }).then(function (data) {
          scanInput.value = '';
          if (data.ok) {
            showMsg('Returned: ' + (data.tool && data.tool.name ? data.tool.name : pendingName), true);
            cancelScan();
            loadLoans();
          } else {
            showMsg(data.error || 'Return failed', false);
            scanInput.focus();
          }
        }).catch(function () {
          showMsg('Network error', false);
        });
      });

      loadLoans();
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
