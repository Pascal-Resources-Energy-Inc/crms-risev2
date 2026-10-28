const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
const app = fs.readFileSync(path.join(root, 'resources/views/layouts/app.blade.php'), 'utf8');
const launcher = fs.readFileSync(path.join(root, 'public/pwa-launcher.html'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const setup = [...app.matchAll(/<script\b(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]).find(source => source.includes('Offline startup verifies'))
    .replace("@json(asset('sw.js'))", JSON.stringify('https://example.test/team/sw.js'));

function harness(source, { online = true, ready = true, present = true, failRegister = false, installing = false, signedIn = true } = {}) {
    const nodes = new Map(), events = new Map(), documentEvents = new Map(), calls = [], redirects = [], workerEvents = new Map();
    const worker = {
        state: installing ? 'installing' : 'activated',
        addEventListener: (name, callback) => workerEvents.set(name, callback),
        removeEventListener: name => workerEvents.delete(name),
        activate() { this.state = 'activated'; workerEvents.get('statechange')(); },
        postMessage(message, ports) {
            calls.push({ message: message.type });
            queueMicrotask(() => ports[0].reply(message.type === 'REFRESH_OFFLINE_ASSETS' ? { success: ready, message: 'Incomplete download.' } : { ready, message: 'Offline files are incomplete.' }));
        }
    };
    const registration = { active: worker, installing: installing ? worker : null, async update() {} };
    const navigator = { onLine: online, serviceWorker: {
        async register(url, options) { calls.push({ registration: url, options }); if (failRegister) throw new Error('Core files could not be downloaded.'); return registration; },
        async getRegistration() { return present ? registration : null; }
    } };
    const saved = new Map(signedIn ? [['offlineUser', JSON.stringify({ id: 42, role: 'Dealer', loginTime: new Date().toISOString() })]] : []);
    saved.set('dealerCartData', 'kept'); saved.set('pwa-first-load-complete', 'true');
    const context = vm.createContext({ URL, Date, AbortController, setTimeout, clearTimeout, navigator,
        MessageChannel: class { constructor() { this.port1 = { close() {} }; this.port2 = { reply: data => this.port1.onmessage({ data }) }; } },
        localStorage: { getItem: key => saved.get(key) || null, removeItem: key => saved.delete(key) },
        document: { readyState: 'loading', addEventListener: (name, callback) => documentEvents.set(name, callback), getElementById(id) {
            if (!nodes.has(id)) nodes.set(id, { textContent: '', hidden: true, disabled: false }); return nodes.get(id);
        } },
        async fetch(url, options) { calls.push({ url, ...options }); return { ok: navigator.onLine }; },
        window: { location: { href: 'https://example.test/team/login', pathname: '/team/pwa-launcher.html', replace: url => redirects.push(url) }, addEventListener: (name, callback) => events.set(name, callback) }
    });
    vm.runInContext(source, context);
    return { context, nodes, events, documentEvents, calls, redirects, worker, saved };
}
const flush = () => new Promise(resolve => setImmediate(resolve));

test('online login verifies offline readiness at DOMContentLoaded using its actual asset scope', async () => {
    const h = harness(setup); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.calls.find(call => call.registration).registration, 'https://example.test/team/sw.js');
    assert.equal(h.calls.find(call => call.registration).options.scope, 'https://example.test/team/');
    assert.match(h.nodes.get('offlineSetupStatus').textContent, /are ready on this device/);
    assert.equal(h.nodes.get('offlineSetupRetry').hidden, true);
    assert.equal(h.events.has('load'), false);
});

test('an old first-load flag cannot make missing core files appear ready', async () => {
    const h = harness(setup, { failRegister: true }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.nodes.get('offlineSetupRetry').hidden, false);
    assert.match(h.nodes.get('offlineSetupStatus').textContent, /could not be downloaded/);
    assert.equal(h.saved.get('dealerCartData'), 'kept');
});

test('failed refresh never marks a partial offline bundle ready', async () => {
    const h = harness(setup, { ready: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.deepEqual(h.calls.filter(call => call.message).map(call => call.message), ['GET_OFFLINE_STATUS', 'REFRESH_OFFLINE_ASSETS']);
    assert.equal(h.nodes.get('offlineSetupRetry').hidden, false);
    assert.match(h.nodes.get('offlineSetupStatus').textContent, /Incomplete download/);
});

test('setup waits for a newly installing worker rather than declaring the registration ready', async () => {
    const h = harness(setup, { installing: true }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.calls.filter(call => call.message).length, 0);
    h.worker.activate(); await flush();
    assert.match(h.nodes.get('offlineSetupStatus').textContent, /are ready/);
});

test('offline setup confirms the existing saved copy without requesting network registration', async () => {
    const h = harness(setup, { online: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.calls.some(call => call.registration), false);
    assert.match(h.nodes.get('offlineSetupStatus').textContent, /are ready/);
});

test('launcher opens a prepared saved app without contacting a server', async () => {
    const h = harness(launcher, { online: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.deepEqual(h.redirects, ['/team/offline/home.html']);
    assert.equal(h.calls.some(call => call.url), false);
    assert.equal(h.saved.get('dealerCartData'), 'kept');
});

test('launcher shows readable recovery when no offline install exists', async () => {
    const h = harness(launcher, { online: false, present: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.redirects.length, 0);
    assert.equal(h.nodes.get('launchRecovery').hidden, false);
    assert.equal(h.nodes.get('launchSpinner').hidden, true);
});

test('launcher verifies a saved bundle instead of navigating to a missing cached page', async () => {
    const h = harness(launcher, { online: false, ready: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.redirects.length, 0);
    assert.equal(h.nodes.get('launchRecovery').hidden, false);
});

test('online launcher probes its own server and preserves subdirectory navigation', async () => {
    const h = harness(launcher, { signedIn: false }); h.documentEvents.get('DOMContentLoaded')(); await flush();
    assert.equal(h.calls.find(call => call.url).url, '/team/manifest.json');
    assert.equal(h.calls.find(call => call.url).method, 'HEAD');
    assert.deepEqual(h.redirects, ['/team/login']);
});
