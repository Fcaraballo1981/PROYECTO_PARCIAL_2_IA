(() => {
  'use strict';

  const products = [
    { id: 'GR-01', name: 'Submariner 16618', brand: 'Rolex', category: 'Colección', price: 32250, description: 'Oro amarillo · Automático · Bisel azul', reference: 'Ref. 16618 · Sultan dial', condition: 'Consultar stock', image: 'assets/rolex-submariner.jpg', alt: 'Rolex Submariner de oro amarillo con bisel azul', credit: 'EMore98 · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:Rolex_Submariner_ref._16618_Sultan_Dial.jpg' },
    { id: 'GR-02', name: 'Seamaster 300M Chronograph', brand: 'Omega', category: 'Deportivo', price: 7450, description: 'Acero · Automático · Cronógrafo', reference: 'Ref. 2225.80.00 · 300 m', condition: 'Consultar stock', image: 'assets/omega-seamaster.jpg', alt: 'Omega Seamaster 300M Chronograph de acero con bisel azul', credit: 'Rama · CC BY-SA 2.0 FR', source: 'https://commons.wikimedia.org/wiki/File:Omega-Seamaster-p1020458.jpg' },
    { id: 'GR-03', name: 'Santos', brand: 'Cartier', category: 'Clásico', price: 11125, description: 'Acero · Automático · Caja cuadrada', reference: 'Brazalete integrado · Fecha', condition: 'Consultar stock', image: 'assets/cartier-santos.jpg', alt: 'Cartier Santos de acero con esfera blanca y números romanos', credit: 'Pittigrilli · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:Cartier_Santos_wristwatch.jpg' },
    { id: 'GR-04', name: 'Monaco', brand: 'TAG Heuer', category: 'Cronógrafo', price: 9125, description: 'Acero · Automático · Esfera azul', reference: 'Cronógrafo · Correa de cuero', condition: 'Consultar stock', image: 'assets/tag-heuer-monaco.jpg', alt: 'TAG Heuer Monaco automático con esfera azul y correa de cuero', credit: 'EMore98 · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:TAG_Heuer_Monaco,_2020.jpg' },
    { id: 'GR-05', name: 'SKX007', brand: 'Seiko', category: 'Deportivo', price: 725, description: 'Acero · Automático · Diver 200 m', reference: 'Calibre 7S26 · Día y fecha', condition: 'Consultar stock', image: 'assets/seiko-skx007.jpg', alt: 'Seiko SKX007 automático con esfera y bisel negros', credit: 'Ashley Pomeroy · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:Seiko_SKX007.jpg', featured: true },
    { id: 'GR-06', name: '5 Sports GMT SSK003', brand: 'Seiko', category: 'Deportivo', price: 1050, description: 'Acero · Automático GMT · Bisel bicolor', reference: 'Calibre 4R34 · 100 m', condition: 'Consultar stock', image: 'assets/seiko-5-ssk003.png', alt: 'Seiko 5 Sports GMT SSK003 de acero con esfera azul', credit: 'Padgriffin · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:Seiko_5_SSK003.png', featured: true },
    { id: 'GR-07', name: 'Aqualand Promaster JP1010', brand: 'Citizen', category: 'Deportivo', price: 675, description: 'Acero · Cuarzo · Profundímetro', reference: 'Diver · Indicador digital', condition: 'Consultar stock', image: 'assets/citizen-promaster.jpg', alt: 'Citizen Aqualand Promaster JP1010 de buceo', credit: 'Pittigrilli · CC BY-SA 4.0', source: 'https://commons.wikimedia.org/wiki/File:Citizen_Aqualand_Promaster_JP1010_diving_watch_with_depth_gauge.jpg', featured: true },
    { id: 'GR-08', name: 'Eco-Drive CB0021', brand: 'Citizen', category: 'Clásico', price: 825, description: 'Acero · Solar · Hora mundial', reference: 'Calibre H144 · Radiocontrolado', condition: 'Consultar stock', image: 'assets/citizen-ecodrive.jpg', alt: 'Citizen Eco-Drive CB0021 con esfera negra y correa de cuero', credit: 'Ocrho · CC0', source: 'https://commons.wikimedia.org/wiki/File:Citizen_CB0021-06E,_H144.jpg', featured: true },
    { id: 'GR-09', name: 'Bambino ER24005W', brand: 'Orient', category: 'Clásico', price: 600, description: 'Acero · Automático · Cristal abombado', reference: 'Esfera crema · Fecha', condition: 'Consultar stock', image: 'assets/orient-bambino.jpg', alt: 'Orient Bambino ER24005W clásico con esfera crema', credit: 'MIKI Yoshihito · CC BY 2.0', source: 'https://commons.wikimedia.org/wiki/File:Orient_Watch_-_Bambino_(ER24005W)_(29121883374).jpg', featured: true },
    { id: 'GR-10', name: 'F-91W', brand: 'Casio', category: 'Colección', price: 75, description: 'Resina · Cuarzo digital · Cronógrafo', reference: 'Alarma · Luz · Calendario', condition: 'Consultar stock', image: 'assets/casio-f91w.jpg', alt: 'Casio F-91W digital negro', credit: 'Ashley Pomeroy · CC BY-SA 3.0', source: 'https://commons.wikimedia.org/wiki/File:Casio_F-91W_5051.jpg', featured: true },
    { id: 'GR-11', name: 'G-Shock DW-5600E', brand: 'Casio', category: 'Deportivo', price: 225, description: 'Resina · Cuarzo digital · 200 m', reference: 'Resistente a impactos · Alarma', condition: 'Consultar stock', image: 'assets/casio-dw5600.jpg', alt: 'Casio G-Shock DW-5600E digital negro', credit: 'Pittigrilli · Dominio público', source: 'https://commons.wikimedia.org/wiki/File:Casio_G-Shock_DW-5600E_wristwatch.jpg', featured: true }
  ];
  products.forEach(product => {
    const detailImage = product.image.replace(/\.(?:jpg|png)$/i, '-detail.jpg');
    product.images = [
      { src: product.image, alt: product.alt, label: 'Vista completa' },
      { src: detailImage, alt: `Detalle de la esfera del ${product.brand} ${product.name}`, label: 'Detalle de la esfera' }
    ];
  });
  const validFilters = ['Todos', 'Clásico', 'Deportivo', 'Colección', 'Cronógrafo'];
  const grid = document.getElementById('product-grid');
  const money = value => new Intl.NumberFormat('es-UY', { style: 'currency', currency: 'USD', currencyDisplay: 'code', maximumFractionDigits: 0 }).format(value);
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

  function renderProducts(filter = 'Todos') {
    const visible = products.filter(product => filter === 'Todos' || product.category === filter);
    document.getElementById('product-count').textContent = String(products.length).padStart(2, '0');
    grid.innerHTML = visible.map(product => `
      <article class="product-card">
        <div class="product-visual">
          <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.alt)}" width="900" height="1080" loading="lazy">
          <span class="product-tag">${escapeHtml(product.category)}</span>
          <span class="product-condition">${escapeHtml(product.condition)}</span>
          <a class="photo-credit" href="${escapeHtml(product.source)}" target="_blank" rel="noopener noreferrer" title="Ver autoría y licencia de la fotografía">Foto: ${escapeHtml(product.credit)}</a>
        </div>
        <div class="product-details">
          <span class="product-category">${escapeHtml(product.brand)}</span>
          <div class="product-meta"><span class="product-name">${escapeHtml(product.name)}</span><span class="product-code">${escapeHtml(product.id)}</span></div>
          <p class="product-description">${escapeHtml(product.description)}</p>
          <p class="product-reference">${escapeHtml(product.reference)}</p>
          <div class="product-bottom"><span><small>Precio de referencia</small><b class="price">${money(product.price)}</b></span><button class="add-sale" type="button" data-buy="${product.id}">Consultar esta pieza <span aria-hidden="true">→</span></button></div>
        </div>
      </article>`).join('');
  }

  function selectFilter(filter, { updateUrl = false } = {}) {
    const selected = validFilters.includes(filter) ? filter : 'Todos';
    document.querySelectorAll('.filter').forEach(button => {
      const active = button.dataset.filter === selected;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    renderProducts(selected);
    if (updateUrl) {
      const url = new URL(location.href);
      selected === 'Todos' ? url.searchParams.delete('estilo') : url.searchParams.set('estilo', selected);
      history.pushState({}, '', url);
    }
  }

  function openPurchase(product) {
    const backdrop = document.createElement('div');
    const trigger = document.activeElement;
    backdrop.className = 'modal-backdrop purchase-backdrop';
    backdrop.innerHTML = `
      <form class="modal purchase-modal" aria-labelledby="purchase-title">
        <button type="button" class="modal-close" aria-label="Cerrar">×</button>
        <p class="eyebrow">SOLICITUD DE COMPRA</p>
        <h2 id="purchase-title">${escapeHtml(product.brand)} ${escapeHtml(product.name)}</h2>
        <p class="purchase-summary">${escapeHtml(product.description)} <b>${money(product.price)}</b></p>
        <p>Dejanos tus datos. Confirmaremos disponibilidad y coordinaremos los próximos pasos antes de cualquier pago.</p>
        <div class="form-grid">
          <div class="field wide"><label>Nombre completo<input name="full_name" autocomplete="name" maxlength="120" required></label></div>
          <div class="field"><label>Correo electrónico<input name="email" type="email" autocomplete="email" required></label></div>
          <div class="field"><label>Teléfono<input name="phone" type="tel" autocomplete="tel" maxlength="40" required></label></div>
          <div class="field wide"><label>¿Cómo preferís que te contactemos?<select name="preferred_contact" required><option value="email">Correo electrónico</option><option value="telefono">Teléfono</option><option value="whatsapp">WhatsApp</option></select></label></div>
        </div>
        <label class="purchase-consent"><input name="consent" type="checkbox" required> Acepto que mis datos se usen para responder esta solicitud.</label>
        <label class="purchase-honeypot" aria-hidden="true">Sitio web<input name="website" tabindex="-1" autocomplete="off"></label>
        <p class="purchase-error" role="alert" hidden></p>
        <button class="modal-submit" type="submit">Enviar solicitud</button>
      </form>`;
    document.body.append(backdrop);
    document.body.classList.add('modal-open');
    const form = backdrop.querySelector('form');
    const close = () => {
      backdrop.remove();
      document.body.classList.remove('modal-open');
      trigger?.focus();
    };
    backdrop.querySelector('.modal-close').addEventListener('click', close);
    backdrop.addEventListener('click', event => { if (event.target === backdrop) close(); });
    backdrop.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); close(); return; }
      if (event.key !== 'Tab') return;
      const focusable = [...backdrop.querySelectorAll('button,input,select')].filter(element => !element.disabled && element.tabIndex !== -1);
      const first = focusable[0];
      const last = focusable.at(-1);
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    form.querySelector('[name="full_name"]').focus();
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const submit = form.querySelector('[type="submit"]');
      const errorNode = form.querySelector('.purchase-error');
      const data = Object.fromEntries(new FormData(form));
      submit.disabled = true;
      submit.textContent = 'Guardando…';
      errorNode.hidden = true;
      try {
        const response = await fetch('api/purchase.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({
            product_id: product.id,
            full_name: data.full_name,
            email: data.email,
            phone: data.phone,
            preferred_contact: data.preferred_contact,
            consent: data.consent === 'on',
            website: data.website || ''
          })
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.error || 'No pudimos guardar la solicitud.');
        form.innerHTML = `<div class="purchase-confirmation" role="status"><span aria-hidden="true">✓</span><p class="eyebrow">SOLICITUD RECIBIDA</p><h2>Reservamos tu consulta.</h2><p>Recibimos tu interés por <b>${escapeHtml(result.inquiry.product)}</b>. Conservá este código:</p><strong>${escapeHtml(result.inquiry.code)}</strong><p>Te contactaremos por el medio elegido para confirmar disponibilidad.</p><button class="modal-submit" type="button">Cerrar</button></div>`;
        form.querySelector('button').addEventListener('click', close);
        form.querySelector('button').focus();
      } catch (error) {
        errorNode.textContent = error.message;
        errorNode.hidden = false;
        submit.disabled = false;
        submit.textContent = 'Enviar solicitud';
      }
    });
  }

  const showcaseTrack = document.getElementById('japan-showcase-track');
  const showcaseStatus = document.getElementById('japan-showcase-status');
  const featuredProducts = products;
  let showcaseIndex = 0;

  function renderShowcase() {
    if (!showcaseTrack) return;
    showcaseTrack.innerHTML = featuredProducts.map((product, index) => `
      <article class="showcase-card" aria-label="${index + 1} de ${featuredProducts.length}: ${escapeHtml(product.brand)} ${escapeHtml(product.name)}">
        <div class="showcase-image" data-product-gallery="${escapeHtml(product.id)}">
          <img class="showcase-main-image" src="${escapeHtml(product.images[0].src)}" alt="${escapeHtml(product.images[0].alt)}" width="960" height="900" loading="lazy">
          <a href="${escapeHtml(product.source)}" target="_blank" rel="noopener noreferrer">Foto: ${escapeHtml(product.credit)}</a>
          <div class="showcase-thumbnails" role="group" aria-label="Vistas de ${escapeHtml(product.brand)} ${escapeHtml(product.name)}">
            ${product.images.map((image, imageIndex) => `<button type="button" data-showcase-image="${imageIndex}" aria-label="${escapeHtml(image.label)}" aria-pressed="${imageIndex === 0}"><img src="${escapeHtml(image.src)}" alt="" width="90" height="90" loading="lazy"></button>`).join('')}
          </div>
        </div>
        <div class="showcase-copy">
          <span class="showcase-number">${String(index + 1).padStart(2, '0')} / ${String(featuredProducts.length).padStart(2, '0')}</span>
          <p>${escapeHtml(product.brand)}</p>
          <h3>${escapeHtml(product.name)}</h3>
          <div class="showcase-rule" aria-hidden="true"></div>
          <p class="showcase-description">${escapeHtml(product.description)}. ${escapeHtml(product.reference)}.</p>
          <strong>${money(product.price)}</strong>
          <button type="button" data-buy="${escapeHtml(product.id)}">Consultar esta pieza</button>
        </div>
      </article>`).join('');
    showcaseStatus.textContent = `Pieza 1 de ${featuredProducts.length}`;
  }

  function goToShowcase(index) {
    if (!showcaseTrack) return;
    showcaseIndex = (index + featuredProducts.length) % featuredProducts.length;
    showcaseTrack.scrollTo({
      left: showcaseTrack.clientWidth * showcaseIndex,
      behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
    });
    showcaseStatus.textContent = `Pieza ${showcaseIndex + 1} de ${featuredProducts.length}`;
  }

  renderShowcase();
  document.getElementById('japan-showcase-prev')?.addEventListener('click', () => goToShowcase(showcaseIndex - 1));
  document.getElementById('japan-showcase-next')?.addEventListener('click', () => goToShowcase(showcaseIndex + 1));
  showcaseTrack?.addEventListener('scroll', () => {
    const nextIndex = Math.round(showcaseTrack.scrollLeft / Math.max(showcaseTrack.clientWidth, 1));
    if (nextIndex !== showcaseIndex && featuredProducts[nextIndex]) {
      showcaseIndex = nextIndex;
      showcaseStatus.textContent = `Pieza ${showcaseIndex + 1} de ${featuredProducts.length}`;
    }
  }, { passive: true });
  showcaseTrack?.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
      event.preventDefault();
      goToShowcase(showcaseIndex + (event.key === 'ArrowRight' ? 1 : -1));
    }
  });
  showcaseTrack?.addEventListener('click', event => {
    const imageButton = event.target.closest('[data-showcase-image]');
    if (imageButton) {
      const gallery = imageButton.closest('[data-product-gallery]');
      const product = products.find(item => item.id === gallery?.dataset.productGallery);
      const image = product?.images[Number(imageButton.dataset.showcaseImage)];
      const mainImage = gallery?.querySelector('.showcase-main-image');
      if (!image || !mainImage) return;
      mainImage.src = image.src;
      mainImage.alt = image.alt;
      gallery.querySelectorAll('[data-showcase-image]').forEach(button => button.setAttribute('aria-pressed', String(button === imageButton)));
      return;
    }
    const button = event.target.closest('[data-buy]');
    if (!button) return;
    const product = products.find(item => item.id === button.dataset.buy);
    if (product) openPurchase(product);
  });

  document.getElementById('filters').addEventListener('click', event => {
    const button = event.target.closest('[data-filter]');
    if (button) selectFilter(button.dataset.filter, { updateUrl: true });
  });
  grid.addEventListener('click', event => {
    const button = event.target.closest('[data-buy]');
    if (!button) return;
    const product = products.find(item => item.id === button.dataset.buy);
    if (product) openPurchase(product);
  });

  const menuToggle = document.getElementById('menu-toggle');
  menuToggle.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') === 'true';
    menuToggle.setAttribute('aria-expanded', String(!open));
    menuToggle.setAttribute('aria-label', open ? 'Abrir menú' : 'Cerrar menú');
    document.getElementById('main-nav').classList.toggle('is-open', !open);
  });
  document.querySelectorAll('#main-nav a').forEach(link => link.addEventListener('click', () => {
    menuToggle.setAttribute('aria-expanded', 'false');
    menuToggle.setAttribute('aria-label', 'Abrir menú');
    document.getElementById('main-nav').classList.remove('is-open');
  }));
  window.addEventListener('popstate', () => selectFilter(new URL(location.href).searchParams.get('estilo') || 'Todos'));
  selectFilter(new URL(location.href).searchParams.get('estilo') || 'Todos');

  const liveWatch = document.getElementById('live-watch');
  if (liveWatch) {
    const hourHand = liveWatch.querySelector('.hour-hand');
    const minuteHand = liveWatch.querySelector('.minute-hand');
    const secondHand = liveWatch.querySelector('.second-hand');
    const formatter = new Intl.DateTimeFormat('es-UY', {
      timeZone: 'America/Montevideo',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23'
    });
    const updateWatch = () => {
      const now = new Date();
      const parts = Object.fromEntries(formatter.formatToParts(now).map(part => [part.type, part.value]));
      const hours = Number(parts.hour);
      const minutes = Number(parts.minute);
      const seconds = Number(parts.second);
      hourHand.style.transform = `translateX(-50%) rotate(${(hours % 12) * 30 + minutes * .5}deg)`;
      minuteHand.style.transform = `translateX(-50%) rotate(${minutes * 6 + seconds * .1}deg)`;
      secondHand.style.transform = `translateX(-50%) rotate(${seconds * 6}deg)`;
      liveWatch.setAttribute('aria-label', `Hora actual en Montevideo: ${parts.hour}:${parts.minute}:${parts.second}`);
      setTimeout(updateWatch, Math.max(50, 1000 - now.getMilliseconds()));
    };
    updateWatch();
  }
})();
