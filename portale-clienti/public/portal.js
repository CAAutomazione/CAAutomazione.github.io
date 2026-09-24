(() => {
  document.querySelectorAll('input[type="password"]').forEach(input => {
    const wrapper = document.createElement('span');
    wrapper.className = 'password-control';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-visibility';
    button.setAttribute('aria-label', 'Mostra password');
    button.setAttribute('aria-pressed', 'false');
    button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 21 18-18"/></svg>';
    wrapper.appendChild(button);
    button.addEventListener('click', event => {
      event.preventDefault();
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.classList.toggle('is-visible', visible);
      button.setAttribute('aria-label', visible ? 'Nascondi password' : 'Mostra password');
      button.setAttribute('aria-pressed', String(visible));
      input.focus({preventScroll:true});
    });
  });
  document.querySelectorAll('.custom-title-toggle input').forEach(toggle => {
    const panel = toggle.closest('form').querySelector('.custom-title-field');
    const input = panel.querySelector('input');
    const update = () => { panel.hidden = !toggle.checked; input.disabled = !toggle.checked; input.required = toggle.checked; };
    toggle.addEventListener('change', update);
    update();
  });
  document.querySelectorAll('[data-upload-form]').forEach(form => form.addEventListener('submit', event => {
    const select = form.querySelector('.recipient-select');
    const parts = select.value.split(':');
    if (parts.length !== 2) { event.preventDefault(); select.focus(); return; }
    form.querySelector('.recipient-id').value = parts[0];
    form.querySelector('.plant-id').value = parts[1];
    const folder = form.querySelector('[name="files[]"]');
    if (folder) {
      if (!folder.files.length || folder.files.length > 100) { event.preventDefault(); alert('Seleziona una cartella con un massimo di 100 file.'); return; }
      form.querySelector('.expected-files').value = folder.files.length;
    }
    if (!confirm('Confermi la consegna a ' + select.selectedOptions[0].text + '?'))event.preventDefault();
  }));
  const resetToggle = document.querySelector('.reset-toggle');
  resetToggle?.addEventListener('click', () => {
    const panel = document.getElementById('reset-panel');
    panel.hidden = !panel.hidden;
    resetToggle.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) panel.querySelector('input[type=email]')?.focus();
  });
  const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('it').trim();
  const params = new URLSearchParams(location.search);
  const key = 'portal-view:' + (params.get('tab') || 'documents');
  const save = () => {
    try {
      sessionStorage.setItem(key, JSON.stringify({ y: scrollY, lists: [...document.querySelectorAll('.scroll,.account-list')].map(node => node.scrollTop), searches: [...document.querySelectorAll('.table-search,.account-search')].map(node => node.value) }));
    } catch (_) {}
  };
  const filters = [...document.querySelectorAll('.table-search,.account-search')];
  const update = input => {
    const card = input.closest('.card');
    const rows = input.classList.contains('account-search') ? [...card.querySelectorAll('.account-list > .account')] : [...card.querySelectorAll('tbody > tr')];
    const query = normalize(input.value);
    let count = 0;
    rows.forEach(row => {
      const matched = !query || normalize(input.classList.contains('account-search') ? row.querySelector('summary').textContent : row.textContent).includes(query);
      row.hidden = !matched;
      if (matched) count++;
    });
    const label = input.closest('.table-tools').querySelector('.search-count');
    if (label) label.textContent = query ? count + ' risultati' : '';
  };
  let previous;
  try { previous = JSON.parse(sessionStorage.getItem(key) || 'null'); } catch (_) {}
  filters.forEach((input, index) => {
    if (previous?.searches?.[index]) input.value = previous.searches[index];
    update(input);
    input.addEventListener('input', () => { update(input); save(); });
  });
  if (previous) {
    requestAnimationFrame(() => {
      document.querySelectorAll('.scroll,.account-list').forEach((node, index) => { node.scrollTop = previous.lists?.[index] || 0; });
      scrollTo(0, previous.y || 0);
    });
  }
  window.addEventListener('pagehide', save);
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', save));
  document.querySelectorAll('a[href*="action=download"]').forEach(link => link.addEventListener('click', save));
})();
