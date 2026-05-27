/**
 * Bilingual UI (en / es). Locale embedded by PHP or loaded from lang/*.json.
 */
(function (global) {
  'use strict';

  function tmDetectLang() {
    try {
      var stored = localStorage.getItem('tm-lang');
      if (stored === 'en' || stored === 'es') return stored;
    } catch (e) {}
    var nav = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
    return nav.indexOf('es') === 0 ? 'es' : 'en';
  }

  function tmGetLang() {
    return global.__TM_LANG || tmDetectLang();
  }

  function tmGetLocale() {
    return global.__TM_LOCALE || {};
  }

  function tmT(key, vars) {
    var parts = String(key).split('.');
    var v = tmGetLocale();
    for (var i = 0; i < parts.length; i++) {
      if (!v || typeof v !== 'object') return key;
      v = v[parts[i]];
    }
    if (typeof v !== 'string') return key;
    if (vars) {
      Object.keys(vars).forEach(function (k) {
        v = v.replace(new RegExp('\\{' + k + '\\}', 'g'), String(vars[k]));
      });
    }
    return v;
  }

  function tmSetLang(lang) {
    if (lang !== 'en' && lang !== 'es') return;
    try {
      localStorage.setItem('tm-lang', lang);
    } catch (e) {}
    document.cookie = 'tm-lang=' + lang + ';path=/;max-age=31536000;SameSite=Lax';
    global.location.reload();
  }

  function tmApplyI18n(root) {
    var scope = root || document;
    scope.querySelectorAll('[data-i18n]').forEach(function (el) {
      var key = el.getAttribute('data-i18n');
      if (!key) return;
      var text = tmT(key);
      if (el.tagName === 'TITLE') {
        document.title = text;
      } else if (el.getAttribute('data-i18n-html') === '1') {
        el.innerHTML = text;
      } else {
        el.textContent = text;
      }
    });
    scope.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
      el.placeholder = tmT(el.getAttribute('data-i18n-placeholder'));
    });
    scope.querySelectorAll('[data-i18n-title]').forEach(function (el) {
      el.title = tmT(el.getAttribute('data-i18n-title'));
    });
    scope.querySelectorAll('[data-i18n-aria]').forEach(function (el) {
      el.setAttribute('aria-label', tmT(el.getAttribute('data-i18n-aria')));
    });
    document.documentElement.lang = tmGetLang();
    tmSyncLangSwitcher();
  }

  function tmSyncLangSwitcher() {
    var lang = tmGetLang();
    document.querySelectorAll('.lang-switcher [data-lang]').forEach(function (btn) {
      var active = btn.getAttribute('data-lang') === lang;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function tmWireLangSwitcher() {
    document.querySelectorAll('.lang-switcher [data-lang]').forEach(function (btn) {
      if (btn.dataset.tmLangWired) return;
      btn.dataset.tmLangWired = '1';
      btn.addEventListener('click', function () {
        tmSetLang(btn.getAttribute('data-lang'));
      });
    });
  }

  function tmI18nInit() {
    if (!global.__TM_LOCALE || !global.__TM_LANG) {
      var lang = tmDetectLang();
      return fetch('lang/' + lang + '.json', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          global.__TM_LANG = lang;
          global.__TM_LOCALE = data;
          tmApplyI18n();
          tmWireLangSwitcher();
        })
        .catch(function () {
          global.__TM_LANG = 'en';
          global.__TM_LOCALE = {};
          tmApplyI18n();
          tmWireLangSwitcher();
        });
    }
    tmApplyI18n();
    tmWireLangSwitcher();
    return Promise.resolve();
  }

  global.tmT = tmT;
  global.tmGetLang = tmGetLang;
  global.tmSetLang = tmSetLang;
  global.tmApplyI18n = tmApplyI18n;
  global.tmI18nInit = tmI18nInit;
})(typeof window !== 'undefined' ? window : this);
