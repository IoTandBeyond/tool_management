<?php
declare(strict_types=1);
?>
<script src="assets/js/i18n.js"></script>
<script src="assets/js/theme.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof tmI18nInit === 'function') {
    tmI18nInit().then(function () {
      document.dispatchEvent(new CustomEvent('tm-i18n-ready'));
    });
  }
});
</script>
