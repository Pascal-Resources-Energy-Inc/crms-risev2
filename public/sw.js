const CACHE_VERSION = 'v10';
const BASE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const CACHE_PREFIX = 'gazlite-crms-' + encodeURIComponent(BASE_PATH || 'root') + '-';
const CACHE_NAME = CACHE_PREFIX + CACHE_VERSION;
const CORE_PATHS = [
  '/pwa-launcher.html', '/offline/login.html', '/offline/home.html', '/offline/products.html',
  '/offline/cart.html', '/offline/confirm_order.html', '/offline/transaction.html', '/offline/account.html',
  '/offline/offline.js', '/offline/network_status.js', '/offline/auth.js', '/offline/data.js', '/offline/data.css',
  '/offline/catalog.js', '/offline/checkout.js', '/offline/workspace.js', '/offline/style.css', '/offline/ui.css',
  '/inside_css/assets/css/bootstrap.min.css', '/inside_css/assets/css/icons.min.css',
  '/inside_css/assets/css/app.min.css', '/inside_css/assets/css/custom.min.css',
  '/inside_css/assets/libs/bootstrap/js/bootstrap.bundle.min.js',
  '/inside_css/assets/libs/sweetalert2/sweetalert2.all.min.js',
  '/inside_css/assets/libs/sweetalert2/sweetalert2.min.css'
];
const OPTIONAL_PATHS = [
  '/manifest.json', '/images/logo_sa_labas.PNG', '/images/human.PNG', '/images/context.PNG',
  '/images/logo_nya.png', '/images/aaa.png', '/images/background.png',
  '/images/icons/logo_nya_192.png', '/images/icons/logo_512.png'
];
const OPTIONAL_EXTERNAL_URLS = [
  'https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
  'https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js',
  'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
];
const OFFLINE_PAGE_MAP = {
  '/': 'home.html', '/home': 'home.html', '/dashboard': 'home.html', '/login': 'login.html',
  '/products': 'products.html', '/cart': 'cart.html', '/place_order': 'confirm_order.html',
  '/confirm_order': 'confirm_order.html', '/transaction': 'transaction.html',
  '/transactions': 'transaction.html', '/history': 'transaction.html',
  '/account': 'account.html', '/user-profile': 'account.html'
};
const appUrl = path => new URL(BASE_PATH + path, self.registration.scope).href;
const CORE_RECORD = appUrl('/offline/__core-cache');
const VERSION_RECORD = appUrl('/offline/__bundle-version');
const pins = new Map();
let refreshing, warming, lastRefresh = 0, lastWarm = 0;

function localPath(url) {
  if (url.origin !== new URL(self.registration.scope).origin) return null;
  if (BASE_PATH && url.pathname !== BASE_PATH && !url.pathname.startsWith(BASE_PATH + '/')) return null;
  return url.pathname.slice(BASE_PATH.length) || '/';
}
async function fetchTimed(url, milliseconds = 8000, options = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), milliseconds);
  try { return await fetch(url, { ...options, signal: controller.signal }); }
  finally { clearTimeout(timer); }
}
function validCore(path, response) {
  if (!response || !response.ok || response.redirected) return false;
  const type = (response.headers.get('content-type') || '').split(';')[0].trim().toLowerCase();
  if (path.endsWith('.html')) return ['text/html', 'application/xhtml+xml'].includes(type);
  if (path.endsWith('.css')) return type === 'text/css';
  return /^(?:text|application)\/(?:x-)?(?:javascript|ecmascript)$/.test(type);
}
async function complete(name) {
  try {
    const cache = await caches.open(name);
    const files = await Promise.all(CORE_PATHS.map(path => cache.match(appUrl(path))));
    return files.every((file, index) => validCore(CORE_PATHS[index], file));
  } catch (_) { return false; }
}
async function revision(name) {
  try {
    const record = await (await caches.open(name)).match(VERSION_RECORD);
    return record ? (await record.json()).revision : null;
  } catch (_) { return null; }
}
async function recover(metadata, names) {
  const candidates = names.filter(name => name.startsWith(CACHE_NAME + '-assets-')).reverse();
  if (names.includes(CACHE_NAME)) candidates.push(CACHE_NAME);
  for (const name of candidates) {
    if (!await complete(name)) continue;
    try { await metadata.put(CORE_RECORD, new Response(JSON.stringify({ name }), { headers: { 'Content-Type': 'application/json' } })); }
    catch (_) { /* A full device can still read its saved bundle. */ }
    return name;
  }
  return CACHE_NAME;
}
async function coreName(clientId) {
  const metadata = await caches.open(CACHE_NAME), names = await caches.keys();
  if (clientId) {
    if (pins.has(clientId) && names.includes(pins.get(clientId))) return pins.get(clientId);
    try {
      const record = await metadata.match(appUrl('/offline/__client/' + encodeURIComponent(clientId)));
      const saved = record && await record.json();
      if (saved && saved.name.startsWith(CACHE_PREFIX) && names.includes(saved.name)) return saved.name;
    } catch (_) {}
  }
  try {
    const record = await metadata.match(CORE_RECORD), value = record && await record.json();
    if (value && value.name.startsWith(CACHE_NAME + '-assets-') && names.includes(value.name)) return value.name;
  } catch (_) {}
  return recover(metadata, names);
}
async function pin(clientId, name) {
  if (!clientId) return;
  pins.set(clientId, name);
  try {
    await (await caches.open(CACHE_NAME)).put(appUrl('/offline/__client/' + encodeURIComponent(clientId)),
      new Response(JSON.stringify({ name }), { headers: { 'Content-Type': 'application/json' } }));
  } catch (_) {}
}
async function fingerprint(files) {
  const hashes = await Promise.all(files.map(async ({ path, response }) => {
    const bytes = await crypto.subtle.digest('SHA-256', await response.clone().arrayBuffer());
    return path + ':' + Array.from(new Uint8Array(bytes), byte => byte.toString(16).padStart(2, '0')).join('');
  }));
  const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(hashes.join('\n')));
  return Array.from(new Uint8Array(hash), byte => byte.toString(16).padStart(2, '0')).join('');
}
async function notifyClients(result) {
  const used = [];
  if (!self.clients.matchAll) return used;
  const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  await Promise.all(windows.map(async client => {
    if (localPath(new URL(client.url)) === null) return;
    const name = await coreName(client.id); used.push(name);
    try { client.postMessage({ type: 'OFFLINE_ASSETS_UPDATED', ...result, updated: await revision(name) !== result.revision }); }
    catch (_) {}
  }));
  return used;
}
async function refreshOfflineAssets(clientId) {
  const before = await revision(await coreName(clientId));
  if (!refreshing) refreshing = (async () => {
    const stagedName = CACHE_NAME + '-assets-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    let committed = false;
    try {
      const files = await Promise.all(CORE_PATHS.map(async path => {
        const response = await fetchTimed(appUrl(path), 8000, { credentials: 'same-origin', cache: 'no-store' });
        if (!validCore(path, response)) throw new Error('Could not download all offline files. Your saved version is kept.');
        return { path, response };
      }));
      const nextRevision = await fingerprint(files), currentName = await coreName();
      const result = { success: true, version: CACHE_VERSION, revision: nextRevision };
      if (await revision(currentName) !== nextRevision || !await complete(currentName)) {
        const staged = await caches.open(stagedName);
        await Promise.all(files.map(file => staged.put(appUrl(file.path), file.response)));
        await staged.put(VERSION_RECORD, new Response(JSON.stringify({ revision: nextRevision }), { headers: { 'Content-Type': 'application/json' } }));
        // The only commit is the pointer to a fully downloaded bundle.
        await (await caches.open(CACHE_NAME)).put(CORE_RECORD, new Response(JSON.stringify({ name: stagedName }), { headers: { 'Content-Type': 'application/json' } }));
        committed = true;
        const used = await notifyClients(result).catch(() => null), names = await caches.keys();
        await Promise.all(names.filter(name => name.startsWith(CACHE_NAME + '-assets-') && name !== stagedName && name !== currentName && used && !used.includes(name)).map(name => caches.delete(name).catch(() => {})));
      } else await notifyClients(result).catch(() => {});
      return result;
    } catch (error) {
      if (!committed) await caches.delete(stagedName).catch(() => {});
      return { success: false, message: error.message || 'Offline files could not be updated.' };
    }
  })().finally(() => { refreshing = null; });
  const result = await refreshing;
  return { ...result, updated: result.success ? before !== result.revision : false };
}
function warmOptional() {
  if (warming) return warming;
  if (Date.now() - lastWarm < 60000 || (self.navigator && self.navigator.onLine === false)) return Promise.resolve();
  lastWarm = Date.now();
  warming = (async () => {
    const cache = await caches.open(CACHE_NAME);
    await Promise.all(OPTIONAL_PATHS.map(appUrl).concat(OPTIONAL_EXTERNAL_URLS).map(async url => {
      try {
        const response = await fetchTimed(url, 5000, { cache: 'reload', credentials: 'same-origin' });
        if (response.ok || response.type === 'opaque') await cache.put(url, response);
      } catch (_) {}
    }));
  })().catch(() => {}).finally(() => { warming = null; });
  return warming;
}
async function offlineStatus() {
  try {
    let name = await coreName(), ready = await complete(name);
    if (!ready) { name = await recover(await caches.open(CACHE_NAME), await caches.keys()); ready = await complete(name); }
    return { ready, version: CACHE_VERSION, revision: ready ? await revision(name) : null,
      message: ready ? 'Offline pages are ready on this device.' : 'Connect and finish offline setup before disconnecting.' };
  } catch (_) { return { ready: false, version: CACHE_VERSION, revision: null, message: 'Offline storage is unavailable. Saved transactions have not been removed.' }; }
}
self.addEventListener('install', event => {
  event.waitUntil((async () => {
    const result = await refreshOfflineAssets();
    if (!result.success) throw new Error(result.message);
    await self.skipWaiting();
  })());
});
self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    const names = await caches.keys(), used = new Set();
    const older = names.filter(name => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME && !name.includes('-assets-')).reverse();
    const windows = self.clients.matchAll ? await self.clients.matchAll({ type: 'window', includeUncontrolled: true }) : [];
    for (const client of windows) {
      if (localPath(new URL(client.url)) === null) continue;
      for (const old of older) {
        const cache = await caches.open(old);
        const record = await cache.match(appUrl('/offline/__client/' + encodeURIComponent(client.id))) || await cache.match(CORE_RECORD);
        let name = old;
        try { if (record) name = (await record.json()).name; } catch (_) {}
        if (!name || !names.includes(name)) continue;
        await pin(client.id, name); used.add(name); break;
      }
    }
    await Promise.all(names.filter(name => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME && !name.startsWith(CACHE_NAME + '-assets-') && !used.has(name)).map(name => caches.delete(name)));
    await self.clients.claim();
  })());
});
function scheduleRefresh(event) {
  if (!event || !event.waitUntil || Date.now() - lastRefresh < 60000 || (self.navigator && self.navigator.onLine === false)) return;
  lastRefresh = Date.now(); event.waitUntil(Promise.all([refreshOfflineAssets(), warmOptional()]));
}
function unavailable() {
  return new Response('<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gaz Lite offline setup</title><body style="font:16px system-ui;max-width:32rem;margin:15vh auto;padding:24px;line-height:1.6"><h1>Offline setup is not ready</h1><p>Connect and open Gaz Lite once to save the offline pages. Your pending transactions have not been removed.</p><a href="' + appUrl('/pwa-launcher.html') + '">Try again</a></body></html>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
async function handleNavigation(request, url, path = localPath(url), event) {
  let name = await coreName(), cache = await caches.open(name);
  const offlineDocument = path === '/pwa-launcher.html' || /^\/offline\/[^/]+\.html$/.test(path);
  if (offlineDocument) {
    let saved = await cache.match(appUrl(path));
    if (!saved) {
      const recovered = await recover(await caches.open(CACHE_NAME), await caches.keys());
      if (recovered !== name) { name = recovered; cache = await caches.open(name); saved = await cache.match(appUrl(path)); }
    }
    if (saved) { await pin(event && (event.resultingClientId || event.clientId), name); scheduleRefresh(event); return saved; }
    try {
      const response = await fetchTimed(request, 5000, { cache: 'no-store' });
      if (validCore(path, response)) { scheduleRefresh(event); return response; }
    } catch (_) {}
    return unavailable();
  }
  try {
    const response = await fetchTimed(request, 5000);
    if (response.status < 500) return response;
  } catch (_) {}
  const target = appUrl('/offline/' + (OFFLINE_PAGE_MAP[path.replace(/\/+$/, '') || '/'] || 'login.html'));
  if (await cache.match(target)) return Response.redirect(target + url.search, 302);
  return unavailable();
}
async function handleAsset(event, request) {
  const path = localPath(new URL(request.url));
  const cache = await caches.open(CORE_PATHS.includes(path) ? await coreName(event.clientId) : CACHE_NAME);
  const saved = await cache.match(CORE_PATHS.includes(path) ? appUrl(path) : request);
  if (saved) return saved;
  try {
    const response = await fetchTimed(request, 5000, { cache: 'no-store' });
    if (!CORE_PATHS.includes(path) && (response.ok || response.type === 'opaque')) {
      try { await cache.put(request, response.clone()); } catch (_) {}
    }
    if (CORE_PATHS.includes(path) && !validCore(path, response)) return new Response('Offline file unavailable.', { status: 503 });
    return response;
  } catch (_) { return new Response('This file is unavailable offline.', { status: 503 }); }
}
self.addEventListener('fetch', event => {
  const request = event.request, url = new URL(request.url), path = localPath(url);
  if (!/^https?:$/.test(url.protocol) || request.method !== 'GET' || (path && /^\/(?:api|_debugbar|livewire|vendor|node_modules)(?:\/|$)/.test(path))) return;
  if (request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html')) {
    if (path !== null) event.respondWith(handleNavigation(request, url, path, event));
  } else event.respondWith(handleAsset(event, request));
});
self.addEventListener('message', event => {
  if (!event.data) return;
  if (event.data.type === 'SKIP_WAITING') self.skipWaiting();
  const operation = event.data.type === 'GET_OFFLINE_STATUS' ? offlineStatus()
    : event.data.type === 'REFRESH_OFFLINE_ASSETS' ? refreshOfflineAssets(event.source && event.source.id) : null;
  if (!operation) return;
  event.waitUntil(Promise.all([operation.then(result => { if (event.ports && event.ports[0]) event.ports[0].postMessage(result); }), warmOptional()]));
});
