const CACHE_VERSION = 'v1';
const CACHE_NAME = `gazlite-pwa-${CACHE_VERSION}`;
const BASE_PATH = self.registration.scope.includes('/crms-risev2/public') ? '/crms-risev2/public' : '';

const OFFLINE_URLS = [
  `${BASE_PATH}/pwa-launcher.html`,
  `${BASE_PATH}/offline/login.html`,
  `${BASE_PATH}/offline/home.html`,
  `${BASE_PATH}/offline/products.html`,
  `${BASE_PATH}/offline/cart.html`,
  `${BASE_PATH}/offline/confirm_order.html`,
  `${BASE_PATH}/offline/transaction.html`,
  `${BASE_PATH}/offline/account.html`,
  `${BASE_PATH}/offline/offline.js`,
  `${BASE_PATH}/offline/network_status.js`,
  `${BASE_PATH}/offline/style.css`,
  
  `${BASE_PATH}/images/logo_sa_labas.png`,
  `${BASE_PATH}/images/human.png`,
  `${BASE_PATH}/images/context.png`,
  `${BASE_PATH}/images/logo_nya.png`,
  `${BASE_PATH}/images/aaa.png`,
  `${BASE_PATH}/images/background.png`,
  `${BASE_PATH}/images/icons/icon-192x192.png`,
  `${BASE_PATH}/images/icons/icon-512x512.png`,
  `${BASE_PATH}/images/icons/logo_nya_192.png`,
  `${BASE_PATH}/images/icons/logo_nya_512.png`,
  `${BASE_PATH}/uploads/products/refill_230.png`,
  `${BASE_PATH}/uploads/products/refill_300.png`,
  
  "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
  "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js",
  "https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css",
  "https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap",
  "https://cdn.jsdelivr.net/npm/sweetalert2@11",
  "https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css",
  
  "https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js",
  "https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js",
  
  `${BASE_PATH}/inside_css/assets/js/layout.js`,
  `${BASE_PATH}/inside_css/assets/css/bootstrap.min.css`,
  `${BASE_PATH}/inside_css/assets/css/icons.min.css`,
  `${BASE_PATH}/inside_css/assets/css/app.min.css`,
  `${BASE_PATH}/inside_css/assets/css/custom.min.css`,
  `${BASE_PATH}/inside_css/assets/libs/bootstrap/js/bootstrap.bundle.min.js`,
  `${BASE_PATH}/inside_css/assets/libs/simplebar/simplebar.min.js`,
  `${BASE_PATH}/inside_css/assets/libs/node-waves/waves.min.js`,
  `${BASE_PATH}/inside_css/assets/libs/feather-icons/feather.min.js`,
  `${BASE_PATH}/inside_css/assets/js/pages/plugins/lord-icon-2.1.0.js`,
  `${BASE_PATH}/inside_css/assets/js/plugins.js`,
  `${BASE_PATH}/inside_css/assets/libs/particles.js/particles.js`,
  `${BASE_PATH}/inside_css/assets/js/pages/particles.app.js`,
  `${BASE_PATH}/inside_css/assets/js/pages/password-addon.init.js`
];

const ONLINE_ONLY_ROUTES = ['/home', '/login', '/products', '/cart', '/transaction', '/account', '/dashboard', '/dealer', '/client'];
const SKIP_PATHS = ['/vendor/', '/node_modules/', '/api/', '/_debugbar/', '/livewire/'];
const NETWORK_TIMEOUT = 2000;

const OFFLINE_PAGE_MAP = {
  '/home': 'home.html',
  '/products': 'products.html',
  '/cart': 'cart.html',
  '/confirm_order': 'confirm_order.html',
  '/transaction': 'transaction.html',
  '/account': 'account.html'
};

console.log('[SW] Service Worker Starting - Cache:', CACHE_NAME, '| Base:', BASE_PATH);

self.addEventListener('install', event => {
  console.log('[SW] Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME).then(async cache => {
      let success = 0, failed = 0;
      
      for (const url of OFFLINE_URLS) {
        try {
          const response = await fetch(url, { cache: 'reload', credentials: 'same-origin' });
          
          if (response?.ok) {
            await cache.put(url, response);
            success++;
          } else {
            failed++;
            console.warn(`[SW] Failed (${response.status}):`, url);
          }
        } catch (error) {
          failed++;
          console.error('[SW] Error caching:', url, error.message);
        }
      }
      
      console.log(`[SW] Cached: ${success} succeeded, ${failed} failed`);
    }).catch(error => console.error('[SW] Install failed:', error))
  );
  
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  console.log('[SW] Activating...');
  
  event.waitUntil(
    caches.keys().then(cacheNames => 
      Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName.startsWith('gazlite-pwa-') && cacheName !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      )
    ).then(() => {
      console.log('[SW] Service Worker active');
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  if (!url.protocol.startsWith('http') || 
      url.protocol === 'chrome-extension:' ||
      SKIP_PATHS.some(path => url.pathname.includes(path)) ||
      request.method !== 'GET') {
    return;
  }
  
  event.respondWith(handleFetch(request));
});

async function handleFetch(request) {
  const url = new URL(request.url);
  const isNavigation = request.mode === 'navigate' || 
                       (request.headers.get('accept')?.includes('text/html'));
  
  return isNavigation ? handleNavigation(request, url) : handleAsset(request, url);
}

async function handleNavigation(request, url) {
  const pathname = url.pathname;
  const isOnlineRoute = ONLINE_ONLY_ROUTES.some(route => pathname.includes(route));
  
  try {
    const networkResponse = await Promise.race([
      fetch(request),
      new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), NETWORK_TIMEOUT))
    ]);
    
    if (!isOnlineRoute && networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
    
  } catch (error) {
    console.log('[SW] Offline - serving cached page');
    
    let offlineFile = 'login.html';
    for (const [route, file] of Object.entries(OFFLINE_PAGE_MAP)) {
      if (pathname.includes(route) || (route === '/home' && (pathname === '/' || pathname === BASE_PATH + '/'))) {
        offlineFile = file;
        break;
      }
    }
    
    const offlineUrl = `${BASE_PATH}/offline/${offlineFile}`;
    const cachedResponse = await caches.match(offlineUrl) || await caches.match(request);
    
    return cachedResponse || new Response('Offline - Page not cached', {
      status: 503,
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

async function handleAsset(request, url) {
  try {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      fetch(request).then(response => {
        if (response?.ok) {
          caches.open(CACHE_NAME).then(cache => cache.put(request, response));
        }
      }).catch(() => {});
      
      return cachedResponse;
    }
    
    const networkResponse = await fetch(request);
    
    if (networkResponse?.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
    
  } catch (error) {
    console.log('[SW] Asset unavailable:', url.pathname);
    return new Response('Offline', { status: 503 });
  }
}

self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

console.log('[SW] Service Worker Loaded');