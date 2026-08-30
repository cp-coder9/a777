const CACHE='architex-static-v1';
const STATIC=['/assets/css/architex-v1.css','/assets/css/community.css','/assets/css/demo-sandbox.css','/assets/js/api-client.js','/assets/js/shell.js','/assets/icons/community.svg','/manifest.webmanifest'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(STATIC))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key))))));
self.addEventListener('fetch',event=>{
  const request=event.request;
  if(request.method!=='GET')return;
  if(request.url.includes('/api/')){event.respondWith((async()=>{return fetch(request);})());return;}
  const url=new URL(request.url);
  if(url.origin!==self.location.origin||request.mode==='navigate')return;
  event.respondWith(fetch(request).then(response=>{if(response.ok){const copy=response.clone();caches.open(CACHE).then(cache=>cache.put(request,copy));}return response;}).catch(()=>caches.match(request)));
});
