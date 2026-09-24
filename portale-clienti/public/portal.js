(() => {
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
