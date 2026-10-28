const assert = require('node:assert/strict');
const test = require('node:test');
const createData = require('../../public/offline/data.js');

const clone = value => value === undefined ? undefined : structuredClone(value);
const key = '00000000-0000-4000-8000-000000000001';
function snapshot() {
    return {
        schema_version: 1, saved_at: '2026-10-28T00:00:00Z', user: { id: 42, role: 'mega_dealer' }, profile: {},
        settings: { can_order: true, can_transact: true }, customers: [],
        distributors: [{ id: 200, user_id: 900, name: 'Example AD' }],
        products: [{ id: 10, name: 'Example refill', price: 70, order_price: 50, dealer_stock: 8, ad_stock: 0, ad_id: 200, client_allowed: true }],
        transactions: [], orders: [], acknowledged_orders: []
    };
}
function order() {
    return { user_id: 42, type: 'order', client_id: null, ad_id: 200, items: [{ product_id: 10, quantity: 20, unit_price: 50 }],
        payment_method: 'cash', delivery_type: 'pickup', snapshot_saved_at: snapshot().saved_at, idempotency_key: key };
}
function memoryStore() {
    let saved = snapshot(); const rows = new Map();
    return {
        rows, async get() { return clone(saved); }, async put(value) { saved = clone(value); },
        async list(id) { return [...rows.values()].filter(row => row.user_id === id).map(clone); },
        async saveOrder(row) { rows.set(row.idempotency_key, clone(row)); return clone(row); },
        async addOrder(row, check) {
            if (rows.has(row.idempotency_key)) return clone(rows.get(row.idempotency_key));
            check(clone(saved), [...rows.values()].map(clone)); rows.set(row.idempotency_key, clone(row)); return clone(row);
        }
    };
}
function storage(values = new Map()) {
    return { values, getItem: name => values.has(name) ? values.get(name) : null,
        setItem: (name, value) => values.set(name, String(value)), removeItem: name => values.delete(name) };
}
function listeners(target = {}) {
    const handlers = new Map();
    return Object.assign(target, {
        addEventListener(name, handler) { if (!handlers.has(name)) handlers.set(name, new Set()); handlers.get(name).add(handler); },
        removeEventListener(name, handler) { if (handlers.has(name)) handlers.get(name).delete(handler); },
        dispatchEvent(event) { for (const handler of handlers.get(event.type) || []) handler(event); return !event.defaultPrevented; }
    });
}
function deferred() { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; }
const response = (body, status = 200) => ({ ok: status >= 200 && status < 300, status, redirected: false, json: async () => clone(body) });
async function settle() { for (let i = 0; i < 10; i++) await new Promise(resolve => setImmediate(resolve)); }
function harness(options = {}) {
    const local = storage(), session = options.session || storage(), timers = new Map(), intervals = new Map(), calls = [], store = memoryStore();
    let timerId = 0, fetcher, reloads = 0, guards = 0, assets = { success: true, version: 'v10', revision: 'revision-new', updated: true };
    const worker = { state: 'activated', postMessage(message, ports) {
        calls.push({ worker: message.type }); queueMicrotask(() => ports[0].reply(clone(assets)));
    } };
    const registration = { active: worker, async update() {} };
    const env = listeners({
        OfflineDataConfig: options.headless ? { headless: true } : {},
        getOfflineUser: () => options.anonymous ? null : { id: 42, role: 'mega_dealer' }, localStorage: local, sessionStorage: session,
        location: { href: 'https://example.test/app/' + (options.onlinePage ? 'products' : 'offline/home.html'),
            pathname: '/app/' + (options.onlinePage ? 'products' : 'offline/home.html'), reload() { reloads++; } },
        document: listeners({ currentScript: { src: 'https://example.test/app/offline/data.js' }, visibilityState: 'visible' }),
        navigator: { onLine: options.offline !== true, serviceWorker: { async register() { return registration; } } },
        AbortController,
        setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; }, clearTimeout(id) { timers.delete(id); },
        setInterval(callback, delay) { const id = ++timerId; intervals.set(id, { callback, delay }); return id; }, clearInterval(id) { intervals.delete(id); },
        MessageChannel: class { constructor() { this.port1 = { close() {} }; this.port2 = { reply: data => this.port1.onmessage({ data }) }; } },
        CustomEvent: class {
            constructor(type, init = {}) { this.type = type; this.detail = init.detail; this.cancelable = !!init.cancelable; this.defaultPrevented = false; }
            preventDefault() { if (this.cancelable) this.defaultPrevented = true; }
        },
        async fetch(url, init) { calls.push({ url, ...init }); return fetcher ? fetcher(url, init, normalFetch) : normalFetch(url, init); }
    });
    function normalFetch(url, init) {
        if (url.endsWith('/snapshot')) return response(snapshot());
        if (url.endsWith('/session')) return response({ user_id: 42, csrf_token: 'synthetic-csrf' });
        if (url.endsWith('/orders')) return response({ success: true, records: [{ id: 123, product_id: 10, transaction_id: 'PRORD-0123', status: 'Pending' }] });
        if (url.endsWith('/manifest.json')) return response({});
        throw new Error('Unexpected request ' + url);
    }
    env.addEventListener('offline-before-update', () => { guards++; });
    const api = createData(env, store);
    return { api, env, local, session, store, calls, timers, intervals,
        get reloads() { return reloads; }, get guards() { return guards; },
        assets(value) { assets = Object.assign({}, assets, value); }, fetch(value) { fetcher = value; },
        event(type) { env.dispatchEvent(new env.CustomEvent(type)); },
        async applyTimer() {
            await settle();
            const entry = [...timers.entries()].find(([, timer]) => timer.delay === 500 || timer.delay === 1000);
            if (entry) { timers.delete(entry[0]); await entry[1].callback(); await settle(); }
            return !!entry;
        }
    };
}

test('a complete new offline bundle applies automatically and preserves the saved cart and pending queue', async () => {
    const h = harness(); h.local.setItem('dealerCartData', '[{"id":10,"quantity":2}]'); await h.api.queueOrder(order());
    await h.api.refreshAssets(); await h.applyTimer();
    assert.equal(h.reloads, 1); assert.equal(h.guards, 1);
    assert.equal(h.local.getItem('dealerCartData'), '[{"id":10,"quantity":2}]');
    assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('unchanged offline functions never reload the current page', async () => {
    const h = harness(); h.assets({ updated: false }); await h.api.refreshAssets(); await h.applyTimer();
    assert.equal(h.reloads, 0); assert.equal(h.guards, 0);
});

test('a failed or incomplete file download never replaces the current offline page', async () => {
    const h = harness(); h.assets({ success: false, message: 'Incomplete offline release' });
    await assert.rejects(h.api.refreshAssets(), /Incomplete/); await h.applyTimer(); assert.equal(h.reloads, 0);
});

test('public asset preparation never reloads online pages or the headless login preparation flow', async () => {
    for (const options of [{ onlinePage: true }, { headless: true }]) {
        const h = harness(options); await h.api.refreshAssets(); await h.applyTimer(); assert.equal(h.reloads, 0);
    }
});

test('a downloaded UI waits for the in-flight order and final data refresh before applying', async () => {
    const h = harness(), gate = deferred(); await h.api.queueOrder(order()); let posting = false;
    h.fetch(async (url, init, normal) => {
        if (url.endsWith('/orders')) { posting = true; await gate.promise; }
        return normal(url, init);
    });
    const sync = h.api.syncNow(); await settle(); assert.equal(posting, true);
    await h.applyTimer(); assert.equal(h.reloads, 0, 'A reload must not interrupt the server POST');
    gate.resolve(); await sync; await h.applyTimer();
    assert.equal((await h.api.listOrders())[0].status, 'synced'); assert.equal(h.reloads, 1);
    assert.equal(h.calls.filter(call => call.method === 'POST').length, 1);
});

test('a downloaded UI waits for the shared data and file update to finish', async () => {
    const h = harness(), gate = deferred(); let reading = false;
    h.fetch(async (url, init, normal) => { if (url.endsWith('/snapshot')) { reading = true; await gate.promise; } return normal(url, init); });
    const update = h.api.updateAll(); await settle(); assert.equal(reading, true);
    await h.applyTimer(); assert.equal(h.reloads, 0);
    gate.resolve(); await update; await h.applyTimer(); assert.equal(h.reloads, 1);
});

test('an accepted order acknowledgement is followed by a complete snapshot refresh before automatic reload', async () => {
    const h = harness(), gate = deferred(); await h.api.queueOrder(order()); let snapshots = 0, finalRefresh = false;
    h.fetch(async (url, init, normal) => {
        if (url.endsWith('/snapshot') && ++snapshots === 2) { finalRefresh = true; await gate.promise; }
        return normal(url, init);
    });
    const sync = h.api.syncNow(); await settle(); assert.equal(finalRefresh, true);
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    await h.applyTimer(); assert.equal(h.reloads, 0, 'A completed POST does not make the final data refresh safe to interrupt');
    gate.resolve(); await sync; await h.applyTimer(); assert.equal(h.reloads, 1);
});

test('an unsaved form may defer automatic application, then it resumes without a button click', async () => {
    const h = harness(); let editing = true;
    h.env.addEventListener('offline-before-update', event => { if (editing) event.preventDefault(); });
    await h.api.refreshAssets(); await h.applyTimer(); assert.equal(h.reloads, 0);
    editing = false; await h.applyTimer(); assert.equal(h.reloads, 1); assert.equal(h.guards, 2);
});

test('repeat notifications for the same saved release request only one reload', async () => {
    const h = harness(); await h.api.refreshAssets(); await h.applyTimer(); assert.equal(h.reloads, 1);
    await h.api.refreshAssets(); await h.applyTimer(); await h.applyTimer(); assert.equal(h.reloads, 1);
});

test('reconnection checks for changed functions even shortly after a previous asset check', async () => {
    const h = harness(); h.assets({ updated: false }); const watcher = h.api.startUpdates(); await settle();
    const before = h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length; assert.ok(before > 0);
    h.env.navigator.onLine = false; h.event('offline'); h.assets({ updated: true });
    h.env.navigator.onLine = true; h.event('offline-connection-restored'); await settle(); await h.applyTimer();
    assert.ok(h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length > before);
    assert.equal(h.reloads, 1); watcher.stop();
});

test('anonymous offline login also receives changed functions when the connection returns', async () => {
    const h = harness({ anonymous: true, offline: true }), watcher = h.api.startUpdates(); await settle();
    h.env.navigator.onLine = true; h.event('online'); await settle(); await h.applyTimer();
    assert.equal(h.reloads, 1); assert.equal(h.calls.some(call => call.method === 'POST'), false); watcher.stop();
});

test('page exit cancels a scheduled automatic reload', async () => {
    const h = harness(); const watcher = h.api.startUpdates(); await settle(); h.event('pagehide');
    await h.applyTimer(); assert.equal(h.reloads, 0); watcher.stop();
});

test('public offline functions still update on recovery after the data session requires sign-in', async () => {
    const h = harness(); h.assets({ updated: false });
    h.fetch(async (url, init, normal) => url.endsWith('/snapshot') ? response({ message: 'Sign in again.' }, 401) : normal(url, init));
    const watcher = h.api.startUpdates(); await settle(); assert.equal(h.api.getSyncState().phase, 'auth_required');
    const before = h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length;
    h.env.navigator.onLine = false; h.event('offline'); h.assets({ updated: true });
    h.env.navigator.onLine = true; h.event('online'); await settle(); await h.applyTimer();
    assert.ok(h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length > before);
    assert.equal(h.reloads, 1); assert.equal(h.calls.some(call => call.method === 'POST'), false); watcher.stop();
});

test('the HEAD probe forces changed functions on server recovery inside the five-minute asset interval', async () => {
    const h = harness(); let serverAvailable = true; h.assets({ updated: false });
    h.fetch(async (url, init, normal) => { if (!serverAvailable) throw new TypeError('Example server disconnected'); return normal(url, init); });
    const watcher = h.api.startUpdates(); await settle();
    const before = h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length;
    const probe = [...h.intervals.values()].find(timer => timer.delay === 15000); assert.ok(probe);
    serverAvailable = false; await probe.callback(); await settle(); assert.equal(h.api.getSyncState().phase, 'retrying');
    serverAvailable = true; h.assets({ updated: true }); await probe.callback(); await settle(); await h.applyTimer();
    assert.equal(h.env.navigator.onLine, true, 'The browser network interface never changed');
    assert.ok(h.calls.filter(call => call.worker === 'REFRESH_OFFLINE_ASSETS').length > before);
    assert.equal(h.reloads, 1); watcher.stop();
});

test('hidden documents apply their downloaded release only after becoming visible', async () => {
    const h = harness(); h.env.document.visibilityState = 'hidden'; await h.api.refreshAssets(); await h.applyTimer(); assert.equal(h.reloads, 0);
    h.env.document.visibilityState = 'visible'; h.env.document.dispatchEvent(new h.env.CustomEvent('visibilitychange'));
    await h.applyTimer(); assert.equal(h.reloads, 1);
});

test('focused entry fields postpone automatic application until editing finishes', async () => {
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
        const h = harness(); h.env.document.activeElement = { tagName };
        await h.api.refreshAssets(); await h.applyTimer(); assert.equal(h.reloads, 0, tagName + ' is still being edited');
        h.env.document.activeElement = null; await h.applyTimer(); assert.equal(h.reloads, 1);
    }
});

test('a completed confirmation can choose saved history for its automatic update destination', async () => {
    const h = harness(), replaced = [];
    h.env.location.pathname = '/app/offline/confirm_order.html'; h.env.location.href = 'https://example.test/app/offline/confirm_order.html';
    h.env.location.replace = url => replaced.push(url);
    h.env.addEventListener('offline-before-update', event => { event.detail.url = 'transaction.html'; });
    await h.api.refreshAssets(); await h.applyTimer();
    assert.deepEqual(replaced, ['https://example.test/app/offline/transaction.html']); assert.equal(h.reloads, 0);
});

test('an update destination outside this app offline documents is ignored', async () => {
    for (const url of ['https://other.example/offline/transaction.html', '/app/account', '/other/offline/home.html', 'javascript:alert(1)']) {
        const h = harness(), replaced = []; h.env.location.replace = value => replaced.push(value);
        h.env.addEventListener('offline-before-update', event => { event.detail.url = url; });
        await h.api.refreshAssets(); await h.applyTimer(); assert.deepEqual(replaced, []); assert.equal(h.reloads, 1);
    }
});
