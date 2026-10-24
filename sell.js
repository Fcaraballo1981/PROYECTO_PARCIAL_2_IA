(() => {
  'use strict';

  const MAX_FILES = 8;
  const MAX_FILE_BYTES = 4_000_000;
  const MAX_TOTAL_BYTES = 4_000_000;
  const VALID_IMAGE_TYPES = new Set(['image/jpeg', 'image/png']);

  const form = document.getElementById('watch-sale-form');
  if (!form) return;

  const steps = [...document.querySelectorAll('.form-step')];
  const progress = [...document.querySelectorAll('#progress li')];
  const photoInput = document.getElementById('watch-photos');
  const preview = document.getElementById('photo-preview');
  const dropZone = document.getElementById('drop-zone');
  const valuationLoading = document.getElementById('valuation-loading');
  const valuationResult = document.getElementById('valuation-result');
  const valuationSubmit = document.getElementById('valuation-submit');
  const bookingButton = document.getElementById('to-booking');
  const bookingDate = document.getElementById('booking-date');
  const slotGrid = document.getElementById('slot-grid');
  const confirmBooking = document.getElementById('confirm-booking');
  const bookingError = document.getElementById('booking-error');
  const fileError = document.getElementById('file-error');

  let files = [];
  let submission = null;
  let selectedSlot = '';
  let availability = [];
  let valuationBusy = false;
  let bookingBusy = false;
  let formDirty = false;
  let flowComplete = false;

  const escapeHtml = value => String(value ?? '').replace(
    /[&<>"']/g,
    character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])
  );

  function goToStep(number) {
    steps.forEach(step => {
      const active = Number(step.dataset.step) === number;
      step.hidden = !active;
      step.classList.toggle('active', active);
    });

    progress.forEach((item, index) => {
      item.classList.toggle('active', index === number - 1);
      item.classList.toggle('complete', index < number - 1);
      if (index === number - 1) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });

    document.getElementById('sale-process').scrollIntoView({
      behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
    });
    const heading = steps.find(step => Number(step.dataset.step) === number)?.querySelector('h3');
    if (heading) {
      heading.tabIndex = -1;
      heading.focus({ preventScroll: true });
    }
  }

  function showFileError(message = '') {
    fileError.textContent = message;
    fileError.hidden = !message;
  }

  function validateStepOne() {
    let valid = true;
    const requiredFields = form.querySelectorAll('[data-step="1"] [required]');

    requiredFields.forEach(field => {
      const fieldIsValid = field.type === 'checkbox' ? field.checked : field.checkValidity();
      field.setAttribute('aria-invalid', String(!fieldIsValid));
      if (!fieldIsValid) {
        valid = false;
        field.closest('.consent')?.setAttribute('aria-invalid', 'true');
      }
    });

    if (!valid) form.querySelector('[data-step="1"] [aria-invalid="true"]')?.focus();
    return valid;
  }

  function renderFiles() {
    preview.replaceChildren();
    files.forEach((file, index) => {
      const item = document.createElement('div');
      item.className = 'preview-item';

      const image = document.createElement('img');
      const objectUrl = URL.createObjectURL(file);
      image.src = objectUrl;
      image.alt = `Vista previa de ${file.name}`;
      image.width = 240;
      image.height = 240;
      image.onload = () => URL.revokeObjectURL(objectUrl);

      const name = document.createElement('small');
      name.textContent = file.name;

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.setAttribute('aria-label', `Quitar ${file.name}`);
      remove.textContent = '×';
      remove.addEventListener('click', () => {
        files.splice(index, 1);
        renderFiles();
      });

      item.append(image, name, remove);
      preview.append(item);
    });
  }

  function addFiles(fileList) {
    for (const file of fileList) {
      if (files.length >= MAX_FILES) {
        showFileError(`Podés subir como máximo ${MAX_FILES} fotografías.`);
        break;
      }
      if (!VALID_IMAGE_TYPES.has(file.type) || file.size < 1 || file.size > MAX_FILE_BYTES) {
        showFileError(`${file.name} no es una imagen JPG o PNG válida, o supera 4 MB.`);
        continue;
      }
      if (files.some(item => item.name === file.name && item.size === file.size && item.lastModified === file.lastModified)) continue;
      if (files.reduce((total, item) => total + item.size, 0) + file.size > MAX_TOTAL_BYTES) {
        showFileError('El conjunto de fotografías no puede superar 4 MB.');
        break;
      }
      files.push(file);
    }
    if (files.length) showFileError();
    renderFiles();
  }

  function renderList(title, items) {
    if (!Array.isArray(items) || !items.length) return '';
    return `<div class="valuation-panel"><h4>${escapeHtml(title)}</h4><ul>${items.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul></div>`;
  }

  function renderValuation(valuation = {}) {
    const value = valuation.result || {};
    const status = valuation.status === 'completed' ? 'Análisis visual completado' : 'Solicitud guardada';

    valuationResult.innerHTML = `
      <div class="valuation-summary">
        <h4>${status}</h4>
        <p>${escapeHtml(value.summary || 'Nuestro equipo revisará la información recibida.')}</p>
        ${value.identification ? `<p><b>Identificación posible:</b> ${escapeHtml(value.identification)}</p>` : ''}
      </div>
      <div class="valuation-columns">
        ${renderList('Factores observados', value.factors)}
        ${renderList('Información que ayudaría', value.missingInformation)}
        ${renderList('Advertencias', value.warnings)}
      </div>`;
    valuationResult.hidden = false;
  }

  async function submitValuation(event) {
    event.preventDefault();
    if (valuationBusy) return;
    if (!validateStepOne()) {
      goToStep(1);
      return;
    }
    if (!files.length) {
      showFileError('Subí al menos una fotografía del reloj para continuar.');
      goToStep(2);
      photoInput.focus();
      return;
    }

    valuationBusy = true;
    valuationSubmit.disabled = true;
    valuationSubmit.textContent = 'Analizando…';
    goToStep(3);
    valuationLoading.hidden = false;
    valuationResult.hidden = true;
    bookingButton.disabled = true;

    const data = new FormData(form);
    data.delete('photos[]');
    files.forEach(file => data.append('photos[]', file, file.name));
    if (submission) {
      data.append('submission_id', submission.id);
      data.append('submission_token', submission.token);
    }

    try {
      const response = await fetch('api/valuation.php', {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json' }
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'No pudimos guardar la solicitud.');
      submission = result.submission;
      renderValuation(result.valuation);
      bookingButton.disabled = false;
    } catch (error) {
      valuationResult.innerHTML = `<div class="valuation-summary"><h4>No se guardó la solicitud</h4><p>${escapeHtml(error.message)}</p></div>`;
      valuationResult.hidden = false;
    } finally {
      valuationBusy = false;
      valuationSubmit.disabled = false;
      valuationSubmit.textContent = 'Solicitar valoración preliminar';
      valuationLoading.hidden = true;
    }
  }

  async function loadAvailability() {
    bookingDate.disabled = true;
    bookingDate.innerHTML = '<option>Cargando fechas…</option>';
    slotGrid.textContent = '';
    confirmBooking.disabled = true;
    selectedSlot = '';

    try {
      const response = await fetch('api/availability.php', { headers: { Accept: 'application/json' } });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'No se pudo consultar la agenda.');
      availability = Array.isArray(result.days) ? result.days : [];
      bookingDate.innerHTML = availability.length
        ? availability.map(day => `<option value="${escapeHtml(day.date)}">${escapeHtml(day.label)}</option>`).join('')
        : '<option>Sin fechas disponibles</option>';
      bookingDate.disabled = !availability.length;
      renderSlots();
    } catch (error) {
      availability = [];
      bookingDate.innerHTML = '<option>No disponible</option>';
      slotGrid.textContent = error.message;
    }
  }

  function renderSlots() {
    const day = availability.find(item => item.date === bookingDate.value);
    selectedSlot = '';
    confirmBooking.disabled = true;
    slotGrid.replaceChildren();

    (day?.slots || []).forEach((slot, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'slot-button';
      button.setAttribute('role', 'radio');
      button.setAttribute('aria-checked', 'false');
      button.tabIndex = index === 0 ? 0 : -1;
      button.textContent = slot.label;
      button.addEventListener('click', () => {
        slotGrid.querySelectorAll('button').forEach(item => {
          item.setAttribute('aria-checked', 'false');
          item.tabIndex = -1;
        });
        button.setAttribute('aria-checked', 'true');
        button.tabIndex = 0;
        selectedSlot = slot.value;
        confirmBooking.disabled = false;
      });
      slotGrid.append(button);
    });

    if (!day?.slots?.length) slotGrid.textContent = 'No quedan horarios para esta fecha.';
  }

  async function saveBooking() {
    if (bookingBusy || !submission || !selectedSlot) return;
    bookingBusy = true;
    confirmBooking.disabled = true;
    confirmBooking.textContent = 'Guardando…';
    bookingError.textContent = '';

    try {
      const response = await fetch('api/book.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          submission_id: submission.id,
          token: submission.token,
          starts_at: selectedSlot
        })
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.error || 'No se pudo guardar la cita.');

      const when = new Intl.DateTimeFormat('es-UY', {
        dateStyle: 'full',
        timeStyle: 'short',
        timeZone: 'America/Montevideo'
      }).format(new Date(result.booking.starts_at));
      const confirmation = document.getElementById('booking-confirmation');
      confirmation.innerHTML = `<h4>Solicitud de cita guardada</h4><p>Te esperamos el <b>${escapeHtml(when)}</b> (hora de Montevideo).</p><span class="booking-code">${escapeHtml(result.booking.code)}</span><p>Llevá el reloj, caja, documentación, accesorios y un documento de identidad. La cita queda solicitada; el equipo podrá contactarte para confirmarla.</p>`;
      confirmation.hidden = false;
      flowComplete = true;
      document.querySelector('.booking-layout').hidden = true;
      confirmBooking.hidden = true;
    } catch (error) {
      bookingError.textContent = error.message;
      await loadAvailability();
    } finally {
      bookingBusy = false;
      if (!confirmBooking.hidden) {
        confirmBooking.disabled = !selectedSlot;
        confirmBooking.textContent = 'Confirmar solicitud de cita';
      }
    }
  }

  function startLiveWatch() {
    const watch = document.getElementById('sale-live-watch');
    if (!watch) return;

    const hourHand = watch.querySelector('.sell-hour-hand');
    const minuteHand = watch.querySelector('.sell-minute-hand');
    const secondHand = watch.querySelector('.sell-second-hand');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const formatter = new Intl.DateTimeFormat('es-UY', {
      timeZone: 'America/Montevideo',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hourCycle: 'h23'
    });

    const update = () => {
      const now = new Date();
      const parts = Object.fromEntries(formatter.formatToParts(now).map(part => [part.type, part.value]));
      const seconds = Number(parts.second) + (reducedMotion ? 0 : now.getMilliseconds() / 1000);
      const minutes = Number(parts.minute) + seconds / 60;
      const hours = Number(parts.hour) % 12 + minutes / 60;

      hourHand.style.transform = `translateX(-50%) rotate(${hours * 30}deg)`;
      minuteHand.style.transform = `translateX(-50%) rotate(${minutes * 6}deg)`;
      secondHand.style.transform = `translateX(-50%) rotate(${seconds * 6}deg)`;
      watch.setAttribute('aria-label', `Hora actual en Montevideo: ${parts.hour}:${parts.minute}:${parts.second}`);

      const interval = reducedMotion ? 1000 : 100;
      setTimeout(update, interval - (Date.now() % interval));
    };

    update();
  }

  document.querySelector('[data-next]').addEventListener('click', () => {
    if (validateStepOne()) goToStep(2);
  });
  document.querySelectorAll('[data-back]').forEach(button => button.addEventListener('click', () => {
    goToStep(Number(button.closest('[data-step]').dataset.step) - 1);
  }));
  document.querySelector('[data-edit]').addEventListener('click', () => goToStep(1));
  form.addEventListener('input', event => {
    formDirty = true;
    event.target.removeAttribute('aria-invalid');
    event.target.closest('.consent')?.removeAttribute('aria-invalid');
  });
  form.addEventListener('change', () => { formDirty = true; });
  form.addEventListener('submit', submitValuation);

  photoInput.addEventListener('change', () => {
    addFiles(photoInput.files);
    photoInput.value = '';
  });
  ['dragenter', 'dragover'].forEach(name => dropZone.addEventListener(name, event => {
    event.preventDefault();
    dropZone.classList.add('dragging');
  }));
  ['dragleave', 'drop'].forEach(name => dropZone.addEventListener(name, event => {
    event.preventDefault();
    dropZone.classList.remove('dragging');
  }));
  dropZone.addEventListener('drop', event => addFiles(event.dataTransfer.files));

  bookingButton.addEventListener('click', async () => {
    goToStep(4);
    await loadAvailability();
  });
  bookingDate.addEventListener('change', renderSlots);
  slotGrid.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    const buttons = [...slotGrid.querySelectorAll('button')];
    const current = buttons.indexOf(document.activeElement);
    if (current < 0 || !buttons.length) return;
    event.preventDefault();
    const backwards = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
    const next = buttons[(current + (backwards ? -1 : 1) + buttons.length) % buttons.length];
    next.click();
    next.focus();
  });
  confirmBooking.addEventListener('click', saveBooking);

  window.addEventListener('beforeunload', event => {
    if (!formDirty || flowComplete) return;
    event.preventDefault();
    event.returnValue = '';
  });

  const menu = document.getElementById('menu-toggle');
  menu?.addEventListener('click', () => {
    const open = menu.getAttribute('aria-expanded') === 'true';
    menu.setAttribute('aria-expanded', String(!open));
    menu.setAttribute('aria-label', open ? 'Abrir menú' : 'Cerrar menú');
    document.getElementById('main-nav')?.classList.toggle('is-open', !open);
  });
  document.querySelectorAll('#main-nav a').forEach(link => link.addEventListener('click', () => {
    menu?.setAttribute('aria-expanded', 'false');
    menu?.setAttribute('aria-label', 'Abrir menú');
    document.getElementById('main-nav')?.classList.remove('is-open');
  }));

  startLiveWatch();
})();
