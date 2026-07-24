(() => {
  if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  let promptEvent;
  const banner = document.getElementById('pwa-install-banner');
  const button = document.getElementById('btn-install-pwa');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); promptEvent = event; banner?.classList.remove('hidden'); });
  button?.addEventListener('click', async () => {
    if (!promptEvent) { alert('To install Oyalo, use your browser menu and choose “Install app” or “Add to Home Screen”. On a smart TV, open this page in its browser and add it to favorites or the home screen.'); return; }
    promptEvent.prompt(); await promptEvent.userChoice; promptEvent = null; banner?.classList.add('hidden');
  });
  window.addEventListener('appinstalled', () => banner?.classList.add('hidden'));
  window.addEventListener('DOMContentLoaded', () => { if (standalone) document.getElementById('pwa-back-button')?.classList.remove('hidden'); });
})();
