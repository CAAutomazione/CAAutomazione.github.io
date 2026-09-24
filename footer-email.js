(() => {
  const links = document.querySelectorAll('.site-footer a[href^="mailto:"]');
  if (!links.length) return;
  const status = document.createElement('div');
  status.className = 'email-feedback';
  status.setAttribute('role', 'status');
  status.setAttribute('aria-live', 'polite');
  document.body.append(status);
  let timer;
  links.forEach(link => link.addEventListener('click', () => {
    const address = decodeURIComponent(link.getAttribute('href').slice(7).split('?')[0]);
    clearTimeout(timer);
    status.textContent = `Apertura dell’app di posta… Se non si apre, puoi usare ${address}.`;
    status.classList.add('is-visible');
    timer = setTimeout(() => status.classList.remove('is-visible'), 7000);
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(address).then(() => {
        status.textContent = `Indirizzo copiato: ${address}. Se l’app di posta non si apre, incollalo nella tua email.`;
      }).catch(() => {});
    }
  }));
})();
