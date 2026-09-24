// Conteggio interno del sito su Tophost: nessun cookie o identificativo nel browser.
if (!location.hostname.endsWith('github.io') && document.visibilityState !== 'prerender') {
  const data = new URLSearchParams({ p: location.pathname });
  const endpoint = '/pannello-di-controllo/visita.php';
  if (navigator.sendBeacon) navigator.sendBeacon(endpoint, data);
  else fetch(endpoint, { method: 'POST', body: data, keepalive: true, credentials: 'omit' }).catch(() => {});
}
