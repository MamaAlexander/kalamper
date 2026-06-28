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
  if (menuBtn && siteNav) {
    menuBtn.addEventListener('click', () => {
      const open = menuBtn.getAttribute('aria-expanded') === 'true';
      menuBtn.setAttribute('aria-expanded', String(!open));
      menuBtn.classList.toggle('is-open', !open);
      siteNav.classList.toggle('is-open', !open);
      document.body.style.overflow = open ? '' : 'hidden';
    });

    // Close on nav link click
    siteNav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.classList.remove('is-open');
        siteNav.classList.remove('is-open');
        document.body.style.overflow = '';
      });
    });

    // Close on outside click
    document.addEventListener('click', e => {
      if (!header.contains(e.target) && siteNav.classList.contains('is-open')) {
        menuBtn.setAttribute('aria-expanded', 'false');
        menuBtn.classList.remove('is-open');
        siteNav.classList.remove('is-open');
        document.body.style.overflow = '';
      }
    });
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

const PIN_ICON = () => L.divIcon({
  className: '',
  html: `<svg width="28" height="36" viewBox="0 0 28 36" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M14 0C6.27 0 0 6.27 0 14C0 24.5 14 36 14 36C14 36 28 24.5 28 14C28 6.27 21.73 0 14 0Z" fill="#FFB800"/>
    <circle cx="14" cy="14" r="6" fill="#111"/>
  </svg>`,
  iconSize: [28, 36],
  iconAnchor: [14, 36],
  popupAnchor: [0, -38]
});

function initMap() {
  if (dealersMap || !document.getElementById('dealers-map')) return;
  dealersMap = L.map('dealers-map', { zoomControl: true, scrollWheelZoom: false });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 18
  }).addTo(dealersMap);
}

async function loadCities() {
  const container = document.getElementById('dealers-cities');
  if (!container) return;
  try {
    const res = await fetch('./api/dealers.php');
    const data = await res.json();
    if (!data.ok || !data.cities.length) {
      container.innerHTML = '<p style="color:var(--text-muted);text-align:center">Информация о дилерах обновляется</p>';
      return;
    }
    container.innerHTML = data.cities.map(city =>
      `<button class="dealer-city-btn" onclick="selectCity('${city.replace(/'/g,"\\'")}', this)">${city}</button>`
    ).join('');
  } catch(e) { console.error(e); }
}

async function selectCity(city, btn) {
  // Mark active button
  document.querySelectorAll('.dealer-city-btn').forEach(b => b.classList.remove('is-active'));
  btn.classList.add('is-active');

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

  try {
    const res = await fetch(`./api/dealers.php?city=${encodeURIComponent(city)}`);
    const data = await res.json();
    if (!data.ok || !data.dealers.length) {
      listEl.innerHTML = '<div style="padding:20px;color:var(--text-muted)">Дилеры не найдены</div>';
      return;
    }

    // Clear old markers
    dealersMarkers.forEach(m => m.remove());
    dealersMarkers = [];

    const bounds = [];

    listEl.innerHTML = data.dealers.map((d, i) => `
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

    // Add markers
    data.dealers.forEach((d, i) => {
      if (!d.lat || !d.lng) return;
      const popup = L.popup({ className: 'dealer-popup' }).setContent(
        `<b style="color:#FFB800">${d.name}</b><br>${d.address}<br><a href="tel:${d.phone.replace(/\D/g,'')}" style="color:#FFB800">${d.phone}</a>`
      );
      const marker = L.marker([d.lat, d.lng], { icon: PIN_ICON() })
        .addTo(dealersMap)
        .bindPopup(popup)
        .on('click', () => {
          document.querySelectorAll('.dealer-card').forEach(c => c.classList.remove('is-active'));
          document.getElementById(`dc-${i}`)?.classList.add('is-active');
          document.getElementById(`dc-${i}`)?.scrollIntoView({ block: 'nearest' });
        });
      dealersMarkers.push(marker);
      bounds.push([d.lat, d.lng]);
    });

    // Fit map to markers
    setTimeout(() => {
      dealersMap.invalidateSize();
      if (bounds.length > 1) dealersMap.fitBounds(bounds, { padding: [40, 40] });
      else if (bounds.length === 1) dealersMap.setView(bounds[0], 13);
    }, 100);

  } catch(e) { listEl.innerHTML = '<div style="padding:20px;color:var(--text-muted)">Ошибка загрузки</div>'; }
}

function focusDealer(i) {
  document.querySelectorAll('.dealer-card').forEach(c => c.classList.remove('is-active'));
  document.getElementById(`dc-${i}`)?.classList.add('is-active');
  if (dealersMarkers[i]) {
    dealersMap.setView(dealersMarkers[i].getLatLng(), 15, { animate: true });
    dealersMarkers[i].openPopup();
  }
}

loadCities();

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
