const preview = location.hostname.endsWith('github.io');
document.querySelectorAll('.form-panel').forEach(form => {
  form.querySelector('button[type=submit]').disabled = preview;
});
const form = document.querySelector('[data-cv-form]');
const fileInput = document.querySelector('[data-cv-file]');
const error = document.querySelector('[data-file-error]');
const allowedExtensions = /\.(pdf|doc|docx)$/i;
function validateFile() {
  const file = fileInput.files[0];
  let message = '';
  if (file && !allowedExtensions.test(file.name)) message = 'Seleziona un file PDF, DOC o DOCX.';
  else if (file && file.size > 5 * 1024 * 1024) message = 'Il CV supera il limite di 5 MB.';
  fileInput.setCustomValidity(message);
  error.textContent = message;
  error.hidden = !message;
  return !message;
}
fileInput?.addEventListener('change', validateFile);
form?.addEventListener('submit', event => {
  if (!validateFile()) { event.preventDefault(); fileInput.reportValidity(); }
});
const messages = {
  campi: 'Controlla i campi obbligatori e riprova.',
  file: 'Il curriculum non è valido o supera il limite consentito.',
  invio: 'Non è stato possibile inviare il messaggio. Riprova o scrivi direttamente via email.',
  limite: 'Troppi tentativi di invio. Riprova più tardi.',
  configurazione: 'L’invio dal sito non è ancora configurato. Scrivi direttamente via email.'
};
const code = new URLSearchParams(location.search).get('errore');
if (code && messages[code]) {
  const alert = document.createElement('p');
  alert.className = 'form-preview';
  alert.setAttribute('role', 'alert');
  alert.textContent = messages[code];
  document.querySelector('.contact-hero .container')?.append(alert);
}
