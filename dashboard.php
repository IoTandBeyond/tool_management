<?php $active = 'dashboard'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="dashboard.title">Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <?php require __DIR__ . '/includes/nav.php'; ?>
  <div class="wrap">
    <h1 data-i18n="dashboard.heading">Dashboard</h1>
    <p class="sub" data-i18n="dashboard.sub">Live summary — refreshes every 5 minutess.</p>

    <div class="stats-grid" id="stats">
      <div class="stat"><div class="num" id="s-stock">—</div><div class="lbl" data-i18n="dashboard.in_stock">In stock</div></div>
      <div class="stat"><div class="num" id="s-out">—</div><div class="lbl" data-i18n="dashboard.checked_out">Checked out</div></div>
      <div class="stat"><div class="num" id="s-missing">—</div><div class="lbl" data-i18n="dashboard.missing">Missing</div></div>
      <div class="stat"><div class="num" id="s-overdue">—</div><div class="lbl" data-i18n="dashboard.overdue">Overdue loans</div></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <h2 data-i18n="dashboard.recent_activity">Recent activity</h2>
        <div id="feed"></div>
      </div>
      <div class="card dashboard-charts-card">
        <h2 data-i18n="dashboard.chart_category_title">Inventory by category</h2>
        <p class="sub" data-i18n="dashboard.chart_category_sub">Total stock units per category (active tools).</p>
        <div class="chart-wrap chart-wrap--pie">
          <canvas id="chart-category-pie" aria-label="Tools and equipment by category"></canvas>
        </div>
        <h2 class="dashboard-charts-heading" data-i18n="dashboard.chart_borrow_title">Checkouts this month</h2>
        <p class="sub" id="chart-month-label"></p>
        <div class="chart-wrap chart-wrap--line">
          <canvas id="chart-monthly-borrow" aria-label="Daily checkouts this month"></canvas>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      var pieChart = null;
      var lineChart = null;
      var PIE_COLORS = ['#3d9eff', '#22c55e', '#f59e0b', '#a78bfa', '#f472b6', '#14b8a6', '#ef4444', '#94a3b8'];

      function chartTheme() {
        var style = getComputedStyle(document.documentElement);
        return {
          text: (style.getPropertyValue('--text') || '#e8eaed').trim(),
          muted: (style.getPropertyValue('--muted') || '#9aa0a6').trim(),
          grid: (style.getPropertyValue('--hairline') || '#3c4043').trim(),
          accent: (style.getPropertyValue('--accent') || '#3d9eff').trim()
        };
      }

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

      function renderCharts(data) {
        if (!data.ok || typeof Chart === 'undefined') return;
        var theme = chartTheme();
        var pieEl = document.getElementById('chart-category-pie');
        var lineEl = document.getElementById('chart-monthly-borrow');
        var categories = data.by_category || [];
        var pieLabels = categories.map(function (r) { return r.label; });
        var pieValues = categories.map(function (r) { return Number(r.total) || 0; });
        var pieColors = pieLabels.map(function (_, i) { return PIE_COLORS[i % PIE_COLORS.length]; });

        if (pieChart) pieChart.destroy();
        if (!pieLabels.length) {
          pieLabels.push(typeof tmT === 'function' ? tmT('dashboard.chart_no_stock') : 'No stock');
          pieValues.push(1);
          pieColors = [theme.muted];
        }
        pieChart = new Chart(pieEl, {
          type: 'pie',
          data: {
            labels: pieLabels,
            datasets: [{
              data: pieValues,
              backgroundColor: pieColors,
              borderColor: 'transparent'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: { color: theme.text, boxWidth: 12, padding: 10 }
              },
              tooltip: {
                callbacks: {
                  label: function (ctx) {
                    var v = ctx.parsed || 0;
                    var sum = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                    var pct = sum ? Math.round((v / sum) * 100) : 0;
                    return ctx.label + ': ' + v + ' (' + pct + '%)';
                  }
                }
              }
            }
          }
        });

        var mb = data.monthly_borrow || {};
        document.getElementById('chart-month-label').textContent = mb.month_label || '';

        if (lineChart) lineChart.destroy();
        lineChart = new Chart(lineEl, {
          type: 'line',
          data: {
            labels: mb.labels || [],
            datasets: [
              {
                label: typeof tmT === 'function' ? tmT('dashboard.chart_tools_line') : 'Tools (consumables)',
                data: mb.consumable || [],
                borderColor: theme.accent,
                backgroundColor: theme.accent + '33',
                tension: 0.25,
                fill: false,
                pointRadius: 2
              },
              {
                label: typeof tmT === 'function' ? tmT('dashboard.chart_measurement_line') : 'Measurement equipment',
                data: mb.measurement || [],
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                tension: 0.25,
                fill: false,
                pointRadius: 2
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                title: { display: true, text: typeof tmT === 'function' ? tmT('dashboard.chart_day_axis') : 'Day of month', color: theme.muted },
                ticks: { color: theme.muted, maxTicksLimit: 16 },
                grid: { color: theme.grid }
              },
              y: {
                beginAtZero: true,
                title: { display: true, text: typeof tmT === 'function' ? tmT('dashboard.chart_checkouts_axis') : 'Checkouts', color: theme.muted },
                ticks: { color: theme.muted, precision: 0 },
                grid: { color: theme.grid }
              }
            },
            plugins: {
              legend: {
                labels: { color: theme.text }
              }
            }
          }
        });
      }

      function loadCharts() {
        tmApi('dashboard_charts', {}, true).then(renderCharts);
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
        loadCharts();
        setInterval(function () {
          loadStats();
          loadFeed();
          loadCharts();
        }, 300000);
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
