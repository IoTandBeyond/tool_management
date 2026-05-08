<?php
declare(strict_types=1);
?>
<script>
(function () {
  try {
    var t = localStorage.getItem('tm-theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  } catch (e) {}
})();
</script>
