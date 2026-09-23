const form = document.querySelector('[data-cv-form]');
const fileInput = document.querySelector('[data-cv-file]');
const error = document.querySelector('[data-file-error]');
const allowedExtensions = /\.(pdf|doc|docx)$/i;
function validateFile() {
  const file = fileInput.files[0];
  let message = '';
  if (file && !allowedExtensions.test(file.name)) message = 'Seleziona un file PDF, DOC o DOCX.';
  else if (file && file.size > 10 * 1024 * 1024) message = 'Il CV supera il limite di 10 MB.';
  fileInput.setCustomValidity(message);
  error.textContent = message;
  error.hidden = !message;
  return !message;
}
fileInput?.addEventListener('change', validateFile);
form?.addEventListener('submit', event => {
  if (!validateFile()) { event.preventDefault(); fileInput.reportValidity(); }
});
