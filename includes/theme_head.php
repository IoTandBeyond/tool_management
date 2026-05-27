<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';
tm_i18n_boot_script();
?>
<script>
(function () {
  try {
    var t = localStorage.getItem('tm-theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
  var lang = window.__TM_LANG;
  if (!lang) {
    try {
      lang = localStorage.getItem('tm-lang');
    } catch (e) {}
    if (lang !== 'en' && lang !== 'es') {
      var nav = (navigator.language || 'en').toLowerCase();
      lang = nav.indexOf('es') === 0 ? 'es' : 'en';
    }
  }
  if (lang === 'en' || lang === 'es') {
    document.documentElement.setAttribute('lang', lang);
    try {
      var ls = localStorage.getItem('tm-lang');
      if (ls === 'en' || ls === 'es') {
        document.cookie = 'tm-lang=' + ls + ';path=/;max-age=31536000;SameSite=Lax';
      }
    } catch (e) {}
  }
})();
</script>
