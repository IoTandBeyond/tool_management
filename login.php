<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php require __DIR__ . '/includes/theme_head.php'; ?>
  <title data-i18n="login.title">Supervisor login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="theme-toggle-float">
    <?php require __DIR__ . '/includes/lang_switcher.php'; ?>
    <?php require __DIR__ . '/includes/theme_toggle.php'; ?>
  </div>
  <div class="wrap" style="max-width: 420px; margin-top: 3rem;">
    <h1 data-i18n="login.heading">Staff login</h1>
    <p class="sub" data-i18n="login.sub">Super admin, company admin, or warehouse manager. Kiosk does not use this screen.</p>
    <div class="card">
      <label for="email" data-i18n="common.email">Email</label>
      <input type="email" id="email" autocomplete="username" autofocus data-i18n-placeholder="login.email_placeholder">
      <label for="password" style="margin-top: 1rem;" data-i18n="common.password">Password</label>
      <input type="password" id="password" autocomplete="current-password">
      <div id="err" class="msg msg-error" style="display: none; margin-top: 1rem;"></div>
      <div class="row-actions" style="margin-top: 1.25rem;">
        <button type="button" class="btn btn-primary" id="submit" data-i18n="login.sign_in">Sign in</button>
        <a class="btn btn-secondary" href="index.php" data-i18n="login.kiosk_home">Kiosk home</a>
      </div>
    </div>
  </div>
  <script src="assets/js/api.js"></script>
  <script>
    (function () {
      function go() {
        var err = document.getElementById('err');
        err.style.display = 'none';
        var u = document.getElementById('email').value.trim();
        var p = document.getElementById('password').value;
        tmApi('login', { email: u, password: p }, true).then(function (data) {
          if (data.ok) {
            window.location.href = 'dashboard.php';
          } else {
            err.textContent = data.error || (typeof tmT === 'function' ? tmT('common.login_failed') : 'Login failed');
            err.style.display = 'block';
          }
        }).catch(function () {
          err.textContent = typeof tmT === 'function' ? tmT('common.network_error') : 'Network error';
          err.style.display = 'block';
        });
      }
      document.getElementById('submit').addEventListener('click', go);
      document.getElementById('password').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') go();
      });
    })();
  </script>
  <?php require __DIR__ . '/includes/theme_footer.php'; ?>
</body>
</html>
