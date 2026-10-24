(() => {
  'use strict';

  const assistantRoot = document.getElementById('watch-assistant');
  if (!assistantRoot) return;
  const assistantContext = assistantRoot.dataset.context === 'sale' ? 'sale' : 'general';
  const storeKey = `gabinete-relojero-conversaciones-v1-${assistantContext}`;
  const maxConversations = 12;
  const maxMessages = 24;
  const $ = (id) => document.getElementById(id);
  const panel = $('assistant-panel');
  const launcher = $('assistant-open');
  const messagesNode = $('assistant-messages');
  const form = $('assistant-form');
  const input = $('assistant-input');
  const sendButton = $('assistant-send');
  const historyPanel = $('assistant-history');
  const historyList = $('assistant-history-list');

  const makeConversation = () => ({
    id: crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    title: 'Nueva consulta',
    updatedAt: Date.now(),
    messages: []
  });

  function trimMessages(messages) {
    while (messages.length > maxMessages) {
      messages.shift();
      if (messages[0]?.role === 'model') messages.shift();
    }
    if (messages[0]?.role === 'model') messages.shift();
    return messages;
  }

  function readStore() {
    try {
      const saved = JSON.parse(localStorage.getItem(storeKey) || 'null');
      if (saved && Array.isArray(saved.conversations)) {
        const conversations = saved.conversations.filter((item) => item && typeof item.id === 'string' && Array.isArray(item.messages))
          .slice(0, maxConversations).map((item) => ({
            id: item.id,
            title: String(item.title || 'Consulta').slice(0, 90),
            updatedAt: Number(item.updatedAt) || Date.now(),
            messages: trimMessages(item.messages.filter((message) => message && ['user', 'model'].includes(message.role) && typeof message.text === 'string')
              .map((message) => ({ role: message.role, text: message.text.slice(0, 4000) })))
          }));
        return { currentId: saved.currentId, conversations };
      }
    } catch { /* El chat puede seguir funcionando si el navegador bloquea el almacenamiento local. */ }
    return { currentId: null, conversations: [] };
  }

  let state = readStore();
  let busy = false;
  let activeRequest = null;
  if (!state.conversations.some((conversation) => conversation.id === state.currentId)) {
    const fresh = makeConversation();
    state.conversations.unshift(fresh);
    state.currentId = fresh.id;
  }

  const current = () => state.conversations.find((conversation) => conversation.id === state.currentId);

  function persist() {
    try {
      state.conversations.sort((a, b) => b.updatedAt - a.updatedAt);
      state.conversations = state.conversations.slice(0, maxConversations);
      localStorage.setItem(storeKey, JSON.stringify(state));
    } catch { /* La sesión actual se conserva en memoria aunque no haya espacio local. */ }
  }

  function addBubble(role, text, error = false, animate = true) {
    const bubble = document.createElement('div');
    bubble.className = `assistant-bubble ${role === 'user' ? 'assistant-user' : 'assistant-bot'}${error ? ' assistant-error' : ''}`;
    bubble.textContent = text;
    messagesNode.append(bubble);
    messagesNode.scrollTop = messagesNode.scrollHeight;
    if (role === 'model' && !error && animate) {
      panel.classList.remove('is-answering');
      requestAnimationFrame(() => panel.classList.add('is-answering'));
      window.setTimeout(() => panel.classList.remove('is-answering'), 1100);
    }
  }

  function renderMessages() {
    messagesNode.replaceChildren();
    const conversation = current();
    if (!conversation || !conversation.messages.length) {
      const welcome = document.createElement('div');
      welcome.className = 'assistant-welcome';
      welcome.innerHTML = assistantContext === 'sale'
        ? '<b>Te acompañamos a ofrecer tu reloj.</b><br>Podemos orientarte sobre los datos y fotografías necesarios. Para enviar la pieza y agendar una visita, usá el formulario de esta página.'
        : '<b>Bienvenido al Gabinete.</b><br>Te orientamos sobre relojes, cuidados y servicios de relojería. ¿Qué pieza o consulta tenés en mente?';
      messagesNode.append(welcome);
    } else {
      conversation.messages.forEach((message) => addBubble(message.role, message.text, false, false));
    }
    messagesNode.scrollTop = messagesNode.scrollHeight;
  }

  function formatDate(timestamp) {
    try { return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(timestamp)); }
    catch { return ''; }
  }

  function renderHistory() {
    historyList.replaceChildren();
    const oldConversations = state.conversations.filter((conversation) => conversation.id !== state.currentId && conversation.messages.length);
    if (!oldConversations.length) {
      const empty = document.createElement('p');
      empty.className = 'assistant-history-empty';
      empty.textContent = 'Tus conversaciones anteriores aparecerán acá.';
      historyList.append(empty);
      return;
    }
    oldConversations.forEach((conversation) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'assistant-history-item';
      button.textContent = conversation.title;
      const date = document.createElement('small');
      date.textContent = formatDate(conversation.updatedAt);
      button.append(date);
      button.addEventListener('click', () => {
        state.currentId = conversation.id;
        historyPanel.hidden = true;
        persist();
        renderMessages();
        input.focus();
      });
      historyList.append(button);
    });
  }

  function setBusy(value) {
    busy = value;
    sendButton.disabled = value;
    input.disabled = value;
    panel.classList.toggle('is-busy', value);
    if (value) {
      const typing = document.createElement('div');
      typing.className = 'assistant-typing';
      typing.id = 'assistant-typing';
      typing.textContent = 'El relojero está preparando una respuesta';
      typing.setAttribute('role', 'status');
      messagesNode.append(typing);
      messagesNode.scrollTop = messagesNode.scrollHeight;
    } else {
      $('assistant-typing')?.remove();
    }
  }

  function openPanel() {
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    launcher.hidden = true;
    renderMessages();
    input.focus();
  }

  function closePanel() {
    panel.hidden = true;
    launcher.hidden = false;
    launcher.setAttribute('aria-expanded', 'false');
    historyPanel.hidden = true;
    $('assistant-history-toggle').setAttribute('aria-expanded', 'false');
    launcher.focus();
  }

  launcher.addEventListener('click', openPanel);
  $('assistant-close').addEventListener('click', closePanel);
  $('assistant-history-toggle').addEventListener('click', () => {
    renderHistory();
    historyPanel.hidden = !historyPanel.hidden;
    $('assistant-history-toggle').setAttribute('aria-expanded', String(!historyPanel.hidden));
  });
  $('assistant-new').addEventListener('click', () => {
    if (busy) {
      activeRequest?.abort();
      setBusy(false);
    }
    const fresh = makeConversation();
    state.conversations.unshift(fresh);
    state.currentId = fresh.id;
    historyPanel.hidden = true;
    $('assistant-history-toggle').setAttribute('aria-expanded', 'false');
    persist();
    renderMessages();
    input.focus();
  });
  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      closePanel();
    }
  });

  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 104)}px`;
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy) return;
    const text = input.value.trim();
    if (!text) return;
    const conversationId = state.currentId;
    const conversation = current();
    conversation.messages.push({ role: 'user', text });
    if (conversation.title === 'Nueva consulta') conversation.title = text.slice(0, 72);
    conversation.updatedAt = Date.now();
    trimMessages(conversation.messages);
    persist();
    addBubble('user', text);
    input.value = '';
    input.style.height = 'auto';
    setBusy(true);
    activeRequest = new AbortController();

    try {
      const response = await fetch('api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ messages: conversation.messages, context: assistantContext }),
        signal: activeRequest.signal
      });
      if (!response.headers.get('content-type')?.includes('application/json')) {
        const error = new Error('No se encontró el backend PHP del asistente.');
        error.code = 'server';
        throw error;
      }
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        const error = new Error(result.error || 'No se pudo completar la consulta.');
        error.code = result.code;
        throw error;
      }
      if (state.currentId !== conversationId) return;
      const answer = String(result.reply || '').trim();
      if (!answer) throw new Error('La respuesta llegó vacía. Probá nuevamente en un momento.');
      conversation.messages.push({ role: 'model', text: answer });
      conversation.updatedAt = Date.now();
      trimMessages(conversation.messages);
      persist();
      addBubble('model', answer);
    } catch (error) {
      if (error.name === 'AbortError') return;
      if (state.currentId === conversationId && conversation.messages.at(-1)?.role === 'user' && conversation.messages.at(-1)?.text === text) {
        conversation.messages.pop();
        persist();
      }
      const failureCode = error.code || (error instanceof TypeError ? 'server' : '');
      const fallback = failureCode === 'configuration'
          ? 'Falta configurar GEMINI_API_KEY en el servidor. Agregala en las variables de entorno y volvé a desplegar.'
        : failureCode === 'server'
          ? 'No encontramos el backend PHP. Revisá la configuración del despliegue o iniciá el servidor local antes de abrir el sitio.'
          : failureCode === 'credentials'
            ? 'Gemini rechazó la clave configurada. Revisá que GEMINI_API_KEY sea una clave válida de Google AI Studio.'
            : failureCode === 'quota'
              ? 'La API de Gemini alcanzó su límite de uso o cuota. Revisá el crédito y los límites en Google AI Studio.'
        : failureCode === 'rate_limit'
          ? 'Recibimos varias consultas seguidas. Esperá un minuto y volvé a escribirnos.'
          : failureCode === 'unavailable'
            ? 'Ahora no podemos conectar con el asistente. Revisá tu conexión y probá nuevamente.'
            : 'No pudimos completar la respuesta en este momento. Probá nuevamente en unos instantes.';
      if (state.currentId === conversationId) addBubble('model', fallback, true);
    } finally {
      activeRequest = null;
      setBusy(false);
    }
  });

  persist();
})();
