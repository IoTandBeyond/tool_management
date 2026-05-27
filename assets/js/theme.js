(function () {
  function syncIcons() {
    var mode = document.documentElement.getAttribute('data-theme') || 'dark';
    var btn = document.getElementById('theme-toggle');
    if (btn) {
      var titleKey = mode === 'light' ? 'theme.toggle_dark' : 'theme.toggle_light';
      var title = typeof tmT === 'function' ? tmT(titleKey) : (mode === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
      btn.title = title;
      btn.setAttribute('aria-label', title);
      btn.setAttribute('aria-pressed', mode === 'light');
    }
  }

  function toggle() {
    var cur = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = cur === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    try {
      localStorage.setItem('tm-theme', next);
    } catch (e) {}
    syncIcons();
  }

  function init() {
    var btn = document.getElementById('theme-toggle');
    if (!btn) return;
    btn.addEventListener('click', toggle);
    syncIcons();
    document.addEventListener('tm-i18n-ready', syncIcons);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
