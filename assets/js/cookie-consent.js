(function () {
  var KEY = 'kalamper_cookie_consent';
  var MID = 110620127;

  function loadMetrika() {
    if (typeof ym === 'function') return;
    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
      m[i].l = 1 * new Date();
      for (var j = 0; j < document.scripts.length; j++) {
        if (document.scripts[j].src === r) return;
      }
      k = e.createElement(t); a = e.getElementsByTagName(t)[0];
      k.async = 1; k.src = r; a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js?id=' + MID, 'ym');
    ym(MID, 'init', {
      ssr: true, webvisor: true, clickmap: true,
      ecommerce: 'dataLayer', accurateTrackBounce: true, trackLinks: true
    });
  }

  function removeBanner() {
    var b = document.getElementById('cookie-banner');
    if (b) b.remove();
  }

  function showBanner() {
    if (document.getElementById('cookie-banner')) return;
    var el = document.createElement('div');
    el.id = 'cookie-banner';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-label', 'Уведомление об использовании cookie');
    el.innerHTML =
      '<div class="cookie-inner">' +
        '<p class="cookie-text">Мы используем <strong>cookie</strong> для аналитики (Яндекс.Метрика) и корректной работы карты дилеров. ' +
        'Подробнее — в <a href="/privacy.html">Политике конфиденциальности</a>.</p>' +
        '<div class="cookie-actions">' +
          '<button id="cookie-accept" class="cookie-btn cookie-btn--primary">Принять все</button>' +
          '<button id="cookie-decline" class="cookie-btn cookie-btn--outline">Только необходимые</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(el);

    document.getElementById('cookie-accept').addEventListener('click', function () {
      localStorage.setItem(KEY, 'accepted');
      removeBanner();
      loadMetrika();
    });
    document.getElementById('cookie-decline').addEventListener('click', function () {
      localStorage.setItem(KEY, 'declined');
      removeBanner();
    });
  }

  var consent = localStorage.getItem(KEY);
  if (consent === 'accepted') {
    loadMetrika();
  } else if (!consent) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', showBanner);
    } else {
      showBanner();
    }
  }
})();
