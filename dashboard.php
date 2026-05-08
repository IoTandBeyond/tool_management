<?php $active = 'dashboard'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title>Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1>Dashboard</h1>
    <p class="sub">Live summary — refreshes every 20 seconds.</p>

    <div class="stats-grid" id="stats">
      <div class="stat"><div class="num" id="s-stock">—</div><div class="lbl">In stock</div></div>
      <div class="stat"><div class="num" id="s-out">—</div><div class="lbl">Checked out</div></div>
      <div class="stat"><div class="num" id="s-missing">—</div><div class="lbl">Missing</div></div>
      <div class="stat"><div class="num" id="s-overdue">—</div><div class="lbl">Overdue loans</div></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <h2>Recent activity</h2>
        <div id="feed"></div>
      </div>
      <div class="card">
        <h2>Quick links</h2>
        <p class="sub"><a href="tools.php">Manage tools</a> · <a href="operators.php">Manage operators</a> · <a href="history.php">History &amp; reports</a></p>
        <p class="sub">Kiosk: <a href="index.php" target="_blank" rel="noopener">Open operator screen</a></p>
      </div>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      function checkAuth() {
        return tmApi('me', {}, true).then(function (data) {
          if (!data.logged_in) {
            window.location.href = 'login.php';
            return false;
          }
          return true;
        });
      }

      function loadStats() {
        tmApi('dashboard_stats', {}, true).then(function (data) {
          if (!data.ok) return;
          var s = data.stats;
          document.getElementById('s-stock').textContent = s.in_stock;
          document.getElementById('s-out').textContent = s.checked_out;
          document.getElementById('s-missing').textContent = s.missing;
          document.getElementById('s-overdue').textContent = s.overdue_transactions;
        });
      }

      function loadFeed() {
        tmApi('activity_feed', { limit: 15 }, true).then(function (data) {
          if (!data.ok) return;
          var el = document.getElementById('feed');
          el.innerHTML = '';
          (data.items || []).forEach(function (it) {
            var d = document.createElement('div');
            d.className = 'feed-item';
            var who = (it.meta && it.meta.operator_name) ? escapeHtml(it.meta.operator_name) : '';
            var main = who
              ? '<div><strong>' + who + '</strong> — ' + escapeHtml(it.message) + '</div>'
              : '<div>' + escapeHtml(it.message) + '</div>';
            d.innerHTML = main + '<div class="feed-meta">' +
              escapeHtml(it.event_type) + ' · ' + tmFormatDt(it.created_at) + '</div>';
            el.appendChild(d);
          });
        });
      }

      function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
      }

      document.getElementById('nav-logout').addEventListener('click', function (e) {
        e.preventDefault();
        tmApi('logout', {}, true).then(function () {
          window.location.href = 'login.php';
        });
      });

      checkAuth().then(function (ok) {
        if (!ok) return;
        loadStats();
        loadFeed();
        setInterval(function () {
          loadStats();
          loadFeed();
        }, 20000);
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
