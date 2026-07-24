// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then((reg) => {
                console.log('[Service Worker] Registered successfully:', reg.scope);
            })
            .catch((err) => {
                console.error('[Service Worker] Registration failed:', err);
            });
    });
}

// Handle PWA Installation Prompts
let deferredPrompt;
const installBtn = document.getElementById('btn-install-pwa');
const installContainer = document.getElementById('pwa-install-banner');

window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent Chrome 67 and earlier from automatically showing the prompt
    e.preventDefault();
    // Stash the event so it can be triggered later.
    deferredPrompt = e;
    
    // Show our custom installation banner/button
    if (installContainer) {
        installContainer.classList.remove('hidden');
    }
    if (installBtn) {
        installBtn.classList.remove('hidden');
    }
    console.log('[PWA] beforeinstallprompt event fired and captured.');
});

if (installBtn) {
    installBtn.addEventListener('click', (e) => {
        if (!deferredPrompt) {
            alert('Oyalo is already installed or your browser doesn\'t support automatic installation. You can add it manually using your browser menu (e.g. \"Add to Home Screen\").');
            return;
        }
        // Hide our install UI
        if (installContainer) {
            installContainer.classList.add('hidden');
        }
        // Show the prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('[PWA] User accepted the install prompt');
            } else {
                console.log('[PWA] User dismissed the install prompt');
            }
            deferredPrompt = null;
        });
    });
}

// Detect when successfully installed
window.addEventListener('appinstalled', (evt) => {
    console.log('[PWA] Oyalo Cloud WiFi was installed successfully!');
    if (installContainer) {
        installContainer.classList.add('hidden');
    }
    alert('Thank you for installing Oyalo! You can now access it directly from your device home screen or desktop apps list.');
});

// Detect display mode (Standalone / Mobile app context)
function isRunningInStandalone() {
    return (window.matchMedia('(display-mode: standalone)').matches) || (window.navigator.standalone) || document.referrer.includes('android-app://');
}

window.addEventListener('DOMContentLoaded', () => {
    if (isRunningInStandalone()) {
        console.log('[PWA] Running inside Standalone App Mode!');
        // We can hide header navigation or show specific TV app optimizations here
        const backBtn = document.getElementById('pwa-back-button');
        if (backBtn) {
            backBtn.classList.remove('hidden');
        }
    }
});
