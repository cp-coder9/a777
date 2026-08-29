const CACHE = 'architex-community-v1';
const SHELL = ['/community-app','/community.webmanifest','/assets/icons/community.svg','/assets/css/architex-v1.css','/assets/css/community.css','/assets/js/api-client.js','/assets/js/project-datum.js','/assets/js/tool-registry.js','/assets/js/shell.js'];
self.addEventListener('install', event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL))));
self.addEventListener('activate', event => event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())));
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET' || new URL(event.request.url).pathname.startsWith('/api/')) return;
  event.respondWith(fetch(event.request).then(response => { const copy=response.clone(); caches.open(CACHE).then(cache=>cache.put(event.request,copy)); return response; }).catch(() => caches.match(event.request).then(cached => cached || caches.match('/community-app'))));
});
