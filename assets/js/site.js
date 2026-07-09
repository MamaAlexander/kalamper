/* ═══════════════════════════════════════════
   KALAMPER — site.js
   ═══════════════════════════════════════════ */

(function () {
  'use strict';

  /* ── Header scroll ── */
  const header = document.getElementById('site-header');
  if (header) {
    const onScroll = () => {
      header.classList.toggle('scrolled', window.scrollY > 40);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Mobile menu ── */
  const menuBtn = document.getElementById('menu-btn');
  const siteNav = document.getElementById('site-nav');

  // Create overlay if not in HTML (subpages don't have it)
  let navOverlay = document.getElementById('nav-overlay');
  if (!navOverlay && menuBtn) {
    navOverlay = document.createElement('div');
    navOverlay.className = 'nav-overlay';
    navOverlay.id = 'nav-overlay';
    document.body.appendChild(navOverlay);
  }

  // iOS-compatible scroll lock
  let _scrollY = 0;
  function lockScroll() {
    _scrollY = window.scrollY;
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + _scrollY + 'px';
    document.body.style.width = '100%';
  }
  function unlockScroll() {
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    window.scrollTo(0, _scrollY);
  }

  function closeNav() {
    menuBtn.setAttribute('aria-expanded', 'false');
    menuBtn.classList.remove('is-open');
    siteNav.classList.remove('is-open');
    navOverlay.classList.remove('is-visible');
    unlockScroll();
  }

  if (menuBtn && siteNav) {
    menuBtn.addEventListener('click', () => {
      const open = menuBtn.getAttribute('aria-expanded') === 'true';
      menuBtn.setAttribute('aria-expanded', String(!open));
      menuBtn.classList.toggle('is-open', !open);
      siteNav.classList.toggle('is-open', !open);
      navOverlay.classList.toggle('is-visible', !open);
      open ? unlockScroll() : lockScroll();
    });
    siteNav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', closeNav);
    });
    navOverlay.addEventListener('click', closeNav);
  }

  /* ── Smooth scroll for anchor links ── */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const id = a.getAttribute('href').slice(1);
      const target = document.getElementById(id);
      if (target) {
        e.preventDefault();
        const top = target.getBoundingClientRect().top + window.scrollY - 70;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    });
  });

  /* ── Reveal on scroll ── */
  const revealEls = document.querySelectorAll('[data-reveal]');
  if (revealEls.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const delay = parseInt(el.dataset.revealDelay || '0', 10);
        setTimeout(() => el.classList.add('is-visible'), delay);
        observer.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    revealEls.forEach(el => observer.observe(el));
  } else {
    // Fallback: show all immediately
    revealEls.forEach(el => el.classList.add('is-visible'));
  }

  /* ── Counter animation ── */
  function animateCount(el, target, duration) {
    const startTime = performance.now();
    const startVal = 0;

    function tick(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(startVal + (target - startVal) * eased);
      el.textContent = current;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  const counters = document.querySelectorAll('.count[data-target]');
  if (counters.length && 'IntersectionObserver' in window) {
    const counterObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const target = parseInt(el.dataset.target, 10);
        const duration = target > 100 ? 1800 : target > 10 ? 1200 : 800;
        animateCount(el, target, duration);
        counterObserver.unobserve(el);
      });
    }, { threshold: 0.5 });

    counters.forEach(el => counterObserver.observe(el));
  } else {
    counters.forEach(el => {
      el.textContent = el.dataset.target;
    });
  }

  /* ── Terminal (polarity) tab switching ── */
  document.body.setAttribute('data-terminal', 'euro');

  const terminalTabs = document.querySelectorAll('.terminal-tab');
  terminalTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const type = tab.dataset.terminal;
      document.body.setAttribute('data-terminal', type);

      terminalTabs.forEach(t => {
        t.classList.toggle('is-active', t === tab);
        t.setAttribute('aria-selected', String(t === tab));
      });
    });
  });

  /* ── Clickable cards ── */
  // Tech cards → technology pages
  document.querySelectorAll('.tech-card').forEach(card => {
    const href = card.querySelector('.tech-card-link')?.getAttribute('href');
    if (!href) return;
    card.style.cursor = 'pointer';
    card.addEventListener('click', e => {
      if (e.target.closest('a, button')) return;
      window.location.href = href;
    });
  });

  // Product cards → catalog pages
  document.querySelectorAll('.product-card').forEach(card => {
    const href = card.querySelector('.product-name a')?.getAttribute('href');
    if (!href) return;
    card.style.cursor = 'pointer';
    card.addEventListener('click', e => {
      if (e.target.closest('a, button')) return;
      window.location.href = href;
    });
  });

  /* ── Active nav link on scroll ── */
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link[href^="#"]');

  if (sections.length && navLinks.length) {
    const sectionObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const id = entry.target.id;
          navLinks.forEach(link => {
            link.classList.toggle('is-active', link.getAttribute('href') === `#${id}`);
          });
        }
      });
    }, { threshold: 0.4 });

    sections.forEach(s => sectionObserver.observe(s));
  }

  /* ── Parallax on hero product image (subtle) ── */
  const heroProduct = document.querySelector('.hero-product-wrap');
  if (heroProduct && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    let ticking = false;
    window.addEventListener('scroll', () => {
      if (!ticking) {
        requestAnimationFrame(() => {
          const scrollY = window.scrollY;
          if (scrollY < window.innerHeight) {
            heroProduct.style.transform = `translateY(${scrollY * 0.06}px)`;
          }
          ticking = false;
        });
        ticking = true;
      }
    }, { passive: true });
  }

})();

// ═══ DEALERS + MAP ═══
let dealersMap = null;
let dealersMarkers = [];

// Lazy-load Yandex Maps — script is NOT in <head>, loaded on demand
let _ymapsCbs = [];
let _ymapsReady = false;
let _ymapsLoading = false;

function onYmapsReady(cb) {
  if (_ymapsReady) { cb(); return; }
  _ymapsCbs.push(cb);
  if (!_ymapsLoading) {
    _ymapsLoading = true;
    const s = document.createElement('script');
    s.src = 'https://api-maps.yandex.ru/2.1/?apikey=651452c9-a3c4-4c89-aa09-a644e03979a6&lang=ru_RU';
    s.async = true;
    s.onload = () => ymaps.ready(() => {
      _ymapsReady = true;
      _ymapsCbs.forEach(fn => fn());
      _ymapsCbs = [];
    });
    document.head.appendChild(s);
  }
}

// Preload when dealers section is 400px away
const _dealersSec = document.getElementById('dealers');
if (_dealersSec && 'IntersectionObserver' in window) {
  const _preloadObs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) { onYmapsReady(() => {}); _preloadObs.disconnect(); }
  }, { rootMargin: '400px' });
  _preloadObs.observe(_dealersSec);
}

// Static fallback data (used when PHP API is unavailable, e.g. on Vercel)
const DEALERS_FALLBACK = {
  cities: ['Алматы','Астана','Шымкент','Атырау','Москва','Санкт-Петербург','Краснодар','Екатеринбург','Новосибирск'],
  byCity: {
    'Алматы': [
      {name:'АКБ-Центр Алматы',address:'пр. Достык, 5/1',phone:'+7 (727) 300-10-01',hours:'Ежедневно 09:00-20:00',lat:43.2565,lng:76.9286},
      {name:'АвтоАккумуляторы',address:'ул. Розыбакиева, 247а',phone:'+7 (727) 300-20-02',hours:'Пн-Сб 09:00-19:00',lat:43.2310,lng:76.8870}
    ],
    'Астана': [
      {name:'АКБ Плюс',address:'пр. Туран, 21',phone:'+7 (7172) 28-10-01',hours:'Ежедневно 09:00-20:00',lat:51.1280,lng:71.4305},
      {name:'АвтоЭнерго',address:'ул. Кенесары, 40',phone:'+7 (7172) 50-20-05',hours:'Пн-Пт 09:00-18:00',lat:51.1801,lng:71.4460}
    ],
    'Шымкент': [
      {name:'АКБ-Маркет',address:'пр. Республики, 33',phone:'+7 (7252) 53-10-01',hours:'Ежедневно 08:00-19:00',lat:42.3417,lng:69.5901}
    ],
    'Атырау': [
      {name:'АвтоБатарея',address:'ул. Азаттык, 55',phone:'+7 (7122) 35-10-02',hours:'Пн-Сб 09:00-18:00',lat:47.1167,lng:51.8833}
    ],
    'Москва': [
      {name:'АвтоМаг на Варшавке',address:'Варшавское ш., 87',phone:'8 (800) 222-07-70',hours:'Ежедневно 08:00-22:00',lat:55.6602,lng:37.6247},
      {name:'АКБ Сервис МКАД',address:'МКАД 39-й км, 1с2',phone:'8 (495) 777-44-55',hours:'Пн-Вс 09:00-21:00',lat:55.6171,lng:37.4640}
    ],
    'Санкт-Петербург': [
      {name:'Северная АКБ',address:'Московский пр., 100',phone:'8 (812) 600-77-01',hours:'Пн-Сб 09:00-20:00',lat:59.8764,lng:30.3242},
      {name:'АккумуляторСПб',address:'пр. Энгельса, 150',phone:'8 (812) 600-77-02',hours:'Ежедневно 09:00-21:00',lat:60.0264,lng:30.3417}
    ],
    'Краснодар': [
      {name:'АвтоАккум Юг',address:'ул. Ставропольская, 78',phone:'8 (861) 201-92-01',hours:'Пн-Пт 09:00-19:00',lat:45.0355,lng:38.9753}
    ],
    'Екатеринбург': [
      {name:'УралАКБ',address:'ул. Малышева, 51',phone:'8 (343) 300-10-01',hours:'Пн-Сб 09:00-20:00',lat:56.8379,lng:60.5975}
    ],
    'Новосибирск': [
      {name:'СибАКБ',address:'Красный пр., 220',phone:'8 (383) 200-10-01',hours:'Ежедневно 09:00-19:00',lat:55.0302,lng:82.9265}
    ]
  }
};

function initMapInner() {
  if (dealersMap || !document.getElementById('dealers-map')) return;
  dealersMap = new ymaps.Map('dealers-map', {
    center: [55, 60], zoom: 4, controls: ['zoomControl'],
  }, { suppressMapOpenBlock: true, yandexMapAutoSwitch: false });
  dealersMap.behaviors.disable('scrollZoom');
}

function initMap() {
  onYmapsReady(initMapInner);
}

function closeDropdown(dropdown, trigger) {
  dropdown.classList.remove('is-open');
  trigger.setAttribute('aria-expanded', 'false');
}

async function loadCities() {
  const dropdown = document.getElementById('city-dropdown');
  const trigger = document.getElementById('city-dropdown-trigger');
  const label = document.getElementById('city-dropdown-label');
  const list = document.getElementById('city-dropdown-list');
  if (!dropdown) return;

  let cities;
  try {
    const ac = new AbortController();
    const timer = setTimeout(() => ac.abort(), 3000);
    const res = await fetch('./api/dealers.php', { signal: ac.signal });
    clearTimeout(timer);
    if (!res.ok) throw new Error('no php');
    const data = await res.json();
    if (!data.ok || !data.cities.length) throw new Error('empty');
    cities = data.cities;
  } catch(e) {
    cities = DEALERS_FALLBACK.cities;
  }

  list.innerHTML = cities.map(city =>
    `<div class="city-dropdown-item" role="option" data-city="${city}">${city}</div>`
  ).join('');

  // Toggle open on trigger click
  trigger.addEventListener('click', e => {
    e.stopPropagation();
    const open = dropdown.classList.toggle('is-open');
    trigger.setAttribute('aria-expanded', String(open));
  });

  // Select city from list
  list.addEventListener('click', e => {
    const item = e.target.closest('.city-dropdown-item');
    if (!item) return;
    const city = item.dataset.city;
    label.textContent = city;
    trigger.classList.add('has-value');
    list.querySelectorAll('.city-dropdown-item').forEach(i => i.classList.remove('is-selected'));
    item.classList.add('is-selected');
    closeDropdown(dropdown, trigger);
    selectCity(city);
  });

  // Close on outside click/tap (bubble phase, compatible with iOS Safari)
  document.addEventListener('click', e => {
    if (!dropdown.contains(e.target)) {
      closeDropdown(dropdown, trigger);
    }
  });
  document.addEventListener('touchstart', e => {
    if (!dropdown.contains(e.target)) {
      closeDropdown(dropdown, trigger);
    }
  }, { passive: true });
}

async function selectCity(city) {

  // Show map panel
  const placeholder = document.getElementById('dealers-map-placeholder');
  const mapEl = document.getElementById('dealers-map');
  const listPanel = document.getElementById('dealers-list-panel');
  const listEl = document.getElementById('dealers-list');

  placeholder.style.display = 'none';
  mapEl.style.display = 'block';
  listPanel.style.display = 'flex';
  listEl.innerHTML = '<div style="padding:20px;color:var(--text-muted);text-align:center">Загрузка...</div>';

  // Init map once
  initMap();

  // Try live API, fall back to static data (Vercel has no PHP)
  let dealers;
  try {
    const res = await fetch(`./api/dealers.php?city=${encodeURIComponent(city)}`);
    if (!res.ok) throw new Error('no php');
    const data = await res.json();
    if (!data.ok || !data.dealers.length) throw new Error('empty');
    dealers = data.dealers;
  } catch(e) {
    dealers = DEALERS_FALLBACK.byCity[city] || [];
  }

  if (!dealers.length) {
    listEl.innerHTML = '<div style="padding:20px;color:var(--text-muted)">Дилеры не найдены</div>';
    return;
  }

  listEl.innerHTML = dealers.map((d, i) => `
    <div class="dealer-card" id="dc-${i}" onclick="focusDealer(${i})">
      <div class="dealer-name">${d.name}</div>
      <div class="dealer-detail">
        <svg viewBox="0 0 16 16" fill="none"><path d="M8 1C5.24 1 3 3.24 3 6c0 3.75 5 9 5 9s5-5.25 5-9c0-2.76-2.24-5-5-5zm0 6.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" fill="currentColor"/></svg>
        ${d.address}
      </div>
      <div class="dealer-detail">
        <svg viewBox="0 0 16 16" fill="none"><path d="M2 3a1 1 0 011-1h2.5a1 1 0 011 1v1.5a1 1 0 01-.8.98l-.7.14a10 10 0 004.38 4.38l.14-.7A1 1 0 0111.5 9.5H13a1 1 0 011 1V13a1 1 0 01-1 1h-1C6.37 14 2 9.63 2 4V3z" fill="currentColor"/></svg>
        <a class="dealer-phone-link" href="tel:${d.phone.replace(/\D/g,'')}">${d.phone}</a>
      </div>
      ${d.hours ? `<div class="dealer-detail"><svg viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3l2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>${d.hours}</div>` : ''}
    </div>`).join('');

  const addMarkers = () => {
    // Clear old markers
    dealersMarkers.forEach(m => dealersMap.geoObjects.remove(m));
    dealersMarkers = [];

    const withCoords = dealers.filter(d => d.lat && d.lng);
    withCoords.forEach((d, i) => {
      const pm = new ymaps.Placemark([d.lat, d.lng], {
        balloonContentBody: `<b>${d.name}</b><br>${d.address}<br><a href="tel:${d.phone.replace(/\D/g,'')}">${d.phone}</a>`,
        hintContent: d.name,
      }, {
        preset: 'islands#yellowDotIcon',
        balloonPanelMaxMapArea: 0,
      });
      pm.events.add('click', () => {
        document.querySelectorAll('.dealer-card').forEach(c => c.classList.remove('is-active'));
        document.getElementById(`dc-${i}`)?.classList.add('is-active');
        document.getElementById(`dc-${i}`)?.scrollIntoView({ block: 'nearest' });
      });
      dealersMap.geoObjects.add(pm);
      dealersMarkers.push(pm);
    });

    if (dealersMarkers.length === 1) {
      dealersMap.setCenter([withCoords[0].lat, withCoords[0].lng], 14, { duration: 400 });
    } else if (dealersMarkers.length > 1) {
      dealersMap.setBounds(dealersMap.geoObjects.getBounds(), { checkZoomRange: true, zoomMargin: 60, duration: 400 });
    }
  };

  onYmapsReady(() => {
    initMapInner();
    addMarkers();
  });
}

function focusDealer(i) {
  document.querySelectorAll('.dealer-card').forEach(c => c.classList.remove('is-active'));
  document.getElementById(`dc-${i}`)?.classList.add('is-active');
  if (dealersMarkers[i] && dealersMap) {
    const coords = dealersMarkers[i].geometry.getCoordinates();
    dealersMap.setCenter(coords, 15, { duration: 400 });
    dealersMarkers[i].balloon.open();
  }
}

// ═══ GLOBAL PHONE UPDATE ═══
const _apiBase = (() => {
  const p = window.location.pathname;
  return (p.includes('/catalog/') || p.includes('/technologies/')) ? '../' : './';
})();

async function updateSitePhones() {
  const hasPhone = document.querySelector('[data-site-phone],[data-site-phone-href]');
  const hasEmail = document.querySelector('[data-site-email],[data-site-email-href]');
  if (!hasPhone && !hasEmail) return;
  try {
    const ac = new AbortController();
    const timer = setTimeout(() => ac.abort(), 3000);
    const res = await fetch(_apiBase + 'api/dealers.php', { signal: ac.signal });
    clearTimeout(timer);
    if (!res.ok) return;
    const data = await res.json();
    if (!data.ok) return;
    if (data.phone) {
      const phoneHref = 'tel:' + data.phone.replace(/\D/g, '');
      document.querySelectorAll('[data-site-phone]').forEach(el => {
        el.href = phoneHref;
        el.textContent = data.phone;
      });
      document.querySelectorAll('[data-site-phone-href]').forEach(el => {
        el.href = phoneHref;
      });
    }
    if (data.email) {
      const emailHref = 'mailto:' + data.email;
      document.querySelectorAll('[data-site-email]').forEach(el => {
        el.href = emailHref;
        el.textContent = data.email;
      });
      document.querySelectorAll('[data-site-email-href]').forEach(el => {
        el.href = emailHref;
      });
    }
  } catch(e) {}
}

updateSitePhones();
loadCities();

// ═══ FOOTER YEAR ═══
const fyEl = document.getElementById('footer-year');
if (fyEl) fyEl.textContent = new Date().getFullYear();

// ═══ REVIEWS ═══
async function loadReviews() {
  const grid = document.getElementById('reviews-grid');
  if (!grid) return;
  try {
    const res = await fetch('./api/reviews.php');
    const data = await res.json();
    if (!data.ok || !data.reviews.length) {
      grid.innerHTML = '<div class="reviews-loading">Отзывов пока нет. Будьте первым!</div>';
      return;
    }
    grid.innerHTML = data.reviews.map(r => `
      <div class="review-card">
        <div class="review-stars">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</div>
        <p class="review-text">${r.review.replace(/</g,'&lt;')}</p>
        <div class="review-meta">
          <span class="review-author">${r.name.replace(/</g,'&lt;')}</span>
          <span class="review-date">${new Date(r.created_at).toLocaleDateString('ru-RU',{day:'2-digit',month:'long',year:'numeric'})}</span>
        </div>
      </div>`).join('');
  } catch(e) { grid.innerHTML = '<div class="reviews-loading">Ошибка загрузки отзывов</div>'; }
}

function openReviewModal() {
  document.getElementById('review-modal').hidden = false;
  document.body.style.overflow = 'hidden';
}
function closeReviewModal() {
  document.getElementById('review-modal').hidden = true;
  document.body.style.overflow = '';
}

document.getElementById('review-add-btn')?.addEventListener('click', openReviewModal);
document.getElementById('review-modal-close')?.addEventListener('click', closeReviewModal);
document.getElementById('review-modal-overlay')?.addEventListener('click', closeReviewModal);

// Star rating
let selectedRating = 0;
document.querySelectorAll('.star').forEach(btn => {
  btn.addEventListener('click', () => {
    selectedRating = parseInt(btn.dataset.rating);
    document.getElementById('review-rating').value = selectedRating;
    document.querySelectorAll('.star').forEach((s,i) => s.classList.toggle('active', i < selectedRating));
  });
  btn.addEventListener('mouseenter', () => {
    const r = parseInt(btn.dataset.rating);
    document.querySelectorAll('.star').forEach((s,i) => s.classList.toggle('active', i < r));
  });
});
document.getElementById('rating-stars')?.addEventListener('mouseleave', () => {
  document.querySelectorAll('.star').forEach((s,i) => s.classList.toggle('active', i < selectedRating));
});

document.getElementById('review-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('review-form-status');
  const submit = document.getElementById('review-submit');
  const rating = parseInt(document.getElementById('review-rating').value);
  if (!rating) { status.textContent = 'Пожалуйста, выберите оценку'; status.className='form-status error'; return; }
  submit.disabled = true; submit.textContent = 'Отправка...';
  status.textContent = ''; status.className = 'form-status';
  try {
    const res = await fetch('./api/reviews.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        name: document.getElementById('review-name').value,
        email: document.getElementById('review-email').value,
        rating,
        review: document.getElementById('review-text').value
      })
    });
    const data = await res.json();
    if (data.ok) {
      status.textContent = data.message;
      status.className = 'form-status';
      document.getElementById('review-form').reset();
      selectedRating = 0;
      document.querySelectorAll('.star').forEach(s => s.classList.remove('active'));
      setTimeout(() => { closeReviewModal(); loadReviews(); }, 1500);
    } else {
      status.textContent = data.error || 'Ошибка'; status.className='form-status error';
    }
  } catch(e) { status.textContent = 'Ошибка сети'; status.className='form-status error'; }
  finally { submit.disabled=false; submit.textContent='Отправить отзыв'; }
});

loadReviews();
