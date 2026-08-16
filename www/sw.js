// Service worker mínimo, solo para que la PWA sea instalable.
// Estrategia: siempre red primero. Nunca se cachean formularios ni datos
// (login, depósitos, etc.) porque este es un sistema financiero en vivo —
// mostrar una página vieja desde caché podría hacer que alguien crea que
// un depósito ya se guardó cuando en realidad no. Solo el "shell" estático
// (manifest + íconos) se guarda, por si se abre la app sin señal.
const CACHE_NAME = 'cetecpro-shell-v1';
const SHELL_ASSETS = [
  '/manifest.json',
  '/pwa-icons/icon-192.png',
  '/pwa-icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});
