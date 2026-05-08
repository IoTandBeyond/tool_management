/**
 * Returns to home after IDLE_MS with no user activity (mousemove, key, touch, scroll).
 */
(function () {
  const IDLE_MS = 5 * 60 * 1000;
  const home = document.body.dataset.home || 'index.php';
  let timer = null;

  function reset() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      window.location.href = home;
    }, IDLE_MS);
  }

  ['mousemove', 'keydown', 'touchstart', 'scroll', 'click'].forEach(function (ev) {
    window.addEventListener(ev, reset, { passive: true });
  });
  reset();
})();
