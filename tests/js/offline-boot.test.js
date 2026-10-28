const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

const workerSource = fs.readFileSync(path.join(__dirname, '../../public/sw.js'), 'utf8');
function resource(url, body = 'saved file', options = {}) {
    const type = String(url).endsWith('.html') ? 'text/html' : String(url).endsWith('.css') ? 'text/css' : 'application/javascript';
    return new Response(body, { ...options, headers: { 'Content-Type': type, ...options.headers } });
}
function worker(scope = 'https://example.test/app/', fetcher = async () => { throw new Error('disconnected'); }) {
    const handlers = {}, stores = new Map(), windows = new Map(), deleted = [];
    let skipped = false, failure;
    const key = request => new URL(typeof request === 'string' ? request : request.url, scope).href;
    const caches = {
        async open(name) {
            if (!stores.has(name)) stores.set(name, new Map());
            const saved = stores.get(name);
            return {
                async put(url, response) { if (failure && failure(name, key(url))) throw new Error('device full'); saved.set(key(url), response.clone()); },
                async match(url) { const response = saved.get(key(url)); return response && response.clone(); }
            };
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { deleted.push(name); return stores.delete(name); }
    };
    const context = vm.createContext({ URL, Response, Request, AbortController, setTimeout, clearTimeout, TextEncoder, crypto: webcrypto, caches, fetch: fetcher,
        self: { registration: { scope }, navigator: { onLine: false }, addEventListener: (type, handler) => { handlers[type] = handler; },
            skipWaiting: async () => { skipped = true; }, clients: { claim: async () => {}, matchAll: async () => [...windows.values()] } } });
    vm.runInContext(workerSource, context);
    const cacheName = vm.runInContext('CACHE_NAME', context), coreRecord = vm.runInContext('CORE_RECORD', context);
    return { context, handlers, stores, windows, deleted, cacheName, coreRecord, skipped: () => skipped,
        failWrites(fn) { failure = fn; },
        async seed(url, body) { await (await caches.open(cacheName)).put(url, resource(url, body)); },
        async navigate(url) { const target = new URL(url, scope); return context.handleNavigation(new Request(target, { headers: { Accept: 'text/html' } }), target); }
    };
}

test('offline routes redirect to real offline documents under the application scope', async () => {
    const app = worker('https://example.test/team/portal/');
    await app.seed('/team/portal/offline/home.html', 'home');
    await app.seed('/team/portal/offline/products.html', 'products');
    assert.equal((await app.navigate('/team/portal/dashboard')).headers.get('location'), 'https://example.test/team/portal/offline/home.html');
    assert.equal((await app.navigate('/team/portal/products?type=order')).headers.get('location'), 'https://example.test/team/portal/offline/products.html?type=order');
    assert.equal(await (await app.navigate('/team/portal/offline/home.html')).text(), 'home');
});

test('an unprepared offline launch has readable recovery instead of a blank response', async () => {
    const app = worker();
    const response = await app.navigate('/app/home');
    assert.equal(response.status, 503);
    assert.match(await response.text(), /Offline setup is not ready/);
});

test('an incomplete core download never activates the new worker', async () => {
    const app = worker(undefined, async url => resource(url, 'file', { status: String(url).endsWith('/offline/checkout.js') ? 404 : 200 }));
    let installation; app.handlers.install({ waitUntil: promise => { installation = promise; } });
    await assert.rejects(installation, /all offline files/);
    assert.equal(app.skipped(), false);
    assert.equal((await app.context.offlineStatus()).ready, false);
});

test('optional downloads do not hold activation behind slow external resources', async () => {
    const requests = [];
    const app = worker(undefined, async url => { requests.push(String(url)); return resource(url); });
    let installation; app.handlers.install({ waitUntil: promise => { installation = promise; } });
    await installation;
    assert.equal(app.skipped(), true);
    assert.ok(requests.every(url => url.startsWith('https://example.test/app/')));
    assert.equal((await app.context.offlineStatus()).ready, true);
});

test('a failed update preserves the prior complete bundle', async () => {
    let broken = false;
    const app = worker(undefined, async url => resource(url, broken ? 'bad release' : 'working release', { status: broken && String(url).endsWith('/offline/data.js') ? 503 : 200 }));
    assert.equal((await app.context.refreshOfflineAssets()).success, true);
    broken = true;
    assert.equal((await app.context.refreshOfflineAssets()).success, false);
    assert.equal(await (await app.navigate('/app/offline/home.html')).text(), 'working release');
    assert.equal((await app.context.offlineStatus()).ready, true);
});

test('HTML error pages cannot replace a core script or stylesheet', async () => {
    const app = worker(undefined, async url => resource(url, 'error', { headers: String(url).endsWith('/offline/catalog.js') ? { 'Content-Type': 'text/html' } : {} }));
    assert.equal((await app.context.refreshOfflineAssets()).success, false);
    assert.equal((await app.context.offlineStatus()).ready, false);
});

test('a failed storage write does not publish a partial release', async () => {
    const app = worker(undefined, async url => resource(url));
    app.failWrites((name, url) => name.includes('-assets-') && url.endsWith('/offline/data.js'));
    assert.equal((await app.context.refreshOfflineAssets()).success, false);
    assert.equal((await app.context.offlineStatus()).ready, false);
});

test('missing or corrupt cache metadata recovers an intact saved bundle', async () => {
    const app = worker(undefined, async url => resource(url, 'saved release'));
    await app.context.refreshOfflineAssets();
    app.stores.get(app.cacheName).delete(app.coreRecord);
    assert.equal((await app.context.offlineStatus()).ready, true);
    assert.equal(await (await app.navigate('/app/offline/home.html')).text(), 'saved release');
    app.stores.get(app.cacheName).set(app.coreRecord, new Response('{invalid'));
    assert.equal((await app.context.offlineStatus()).ready, true);
});

test('readiness verifies a complete bundle rather than any cache entries', async () => {
    const app = worker();
    for (let i = 0; i < 10; i++) await app.seed('/app/images/example-' + i, 'image');
    const status = await app.context.offlineStatus();
    assert.equal(status.ready, false);
    assert.equal(status.version, 'v10');
});

test('unchanged functions do not create another bundle or report a new version', async () => {
    const app = worker(undefined, async url => resource(url));
    await app.context.refreshOfflineAssets();
    const count = app.stores.size;
    const result = await app.context.refreshOfflineAssets();
    assert.equal(result.updated, false);
    assert.equal(app.stores.size, count);
});

test('an open document keeps its own scripts until its next navigation', async () => {
    let release = 'old functions';
    const app = worker(undefined, async url => resource(url, release));
    await app.context.refreshOfflineAssets();
    app.windows.set('cart', { id: 'cart', url: 'https://example.test/app/offline/cart.html', postMessage() {} });
    const target = new URL('https://example.test/app/offline/cart.html');
    await app.context.handleNavigation(new Request(target), target, '/offline/cart.html', { resultingClientId: 'cart' });
    release = 'new functions';
    assert.equal((await app.context.refreshOfflineAssets('cart')).updated, true);
    const script = new Request('https://example.test/app/offline/checkout.js');
    assert.equal(await (await app.context.handleAsset({ clientId: 'cart' }, script)).text(), 'old functions');
    await app.context.handleNavigation(new Request(target), target, '/offline/cart.html', { resultingClientId: 'cart' });
    assert.equal(await (await app.context.handleAsset({ clientId: 'cart' }, script)).text(), 'new functions');
});

test('server reachability checks, writes and private APIs bypass saved responses', () => {
    const app = worker();
    for (const request of [new Request('https://example.test/app/manifest.json', { method: 'HEAD' }), new Request('https://example.test/app/api/offline/snapshot'), new Request('https://example.test/app/api/offline/orders', { method: 'POST' })]) {
        let intercepted = false; app.handlers.fetch({ request, respondWith() { intercepted = true; } });
        assert.equal(intercepted, false);
    }
});

test('authenticated server HTML is passed through without being cached', async () => {
    const app = worker(undefined, async () => new Response('private page', { status: 200 }));
    assert.equal(await (await app.navigate('/app/home')).text(), 'private page');
    assert.equal(app.stores.get(app.cacheName).size, 0);
});

test('worker activation retires only this application scope caches', async () => {
    const app = worker();
    const old = app.cacheName.replace(/v10$/, 'v9');
    app.stores.set(old, new Map()); app.stores.set('dms-pwa-v1', new Map()); app.stores.set('gazlite-pwa-v1', new Map());
    let activated; app.handlers.activate({ waitUntil: promise => { activated = promise; } }); await activated;
    assert.deepEqual(app.deleted, [old]);
});
