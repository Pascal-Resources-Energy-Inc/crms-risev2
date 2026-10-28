const assert = require('node:assert/strict');
const test = require('node:test');
const createData = require('../../public/offline/data.js');

const copy = value => value === undefined ? undefined : structuredClone(value);
const firstKey = '00000000-0000-4000-8000-000000000001';
const secondKey = '00000000-0000-4000-8000-000000000002';

function snapshot(id = 42) {
    return {
        schema_version: 1, saved_at: '2026-10-28T00:00:00Z',
        user: { id, role: 'mega_dealer', name: 'Example Account' }, profile: {},
        settings: { can_order: true, can_transact: true },
        customers: [{ id: 100, name: 'Assigned Customer' }],
        distributors: [{ id: 200, user_id: 900, name: 'Assigned AD' }],
        products: [{ id: 10, name: 'Item A', price: 70, order_price: 50, dealer_stock: 8, ad_stock: 0, ad_id: 200, client_allowed: true }],
        transactions: [], orders: [], acknowledged_orders: []
    };
}
function order(overrides = {}) {
    return {
        user_id: 42, type: 'order', client_id: null, ad_id: 200,
        items: [{ product_id: 10, quantity: 20, unit_price: 50 }],
        payment_method: 'cash', delivery_type: 'pickup', snapshot_saved_at: '2026-10-28T00:00:00Z',
        idempotency_key: firstKey, ...overrides
    };
}
function memoryStore() {
    const snapshots = new Map([[42, snapshot()], [43, snapshot(43)]]), rows = new Map();
    let tail = Promise.resolve();
    return {
        snapshots, rows,
        async get(id) { return copy(snapshots.get(Number(id))); },
        async put(value) { snapshots.set(Number(value.user.id), copy(value)); },
        async list(id) { return [...rows.values()].filter(row => Number(row.user_id) === Number(id)).map(copy); },
        async saveOrder(row, expected) {
            const current = rows.get(row.idempotency_key);
            if (!current || current.user_id !== expected.user_id || Number(current.revision || 0) !== Number(expected.revision || 0) ||
                !(current.status === 'pending' || (row.status === 'synced' && current.status === 'needs_review'))) return copy(current);
            rows.set(row.idempotency_key, copy(row)); return copy(row);
        },
        addOrder(row, validate, options = {}) {
            const operation = tail.then(() => {
                const current = rows.get(row.idempotency_key);
                if (current) {
                    if (current.user_id !== row.user_id) throw new Error('Reference belongs to another account.');
                    if (!options.replaceRejected || current.status !== 'needs_review') return copy(current);
                    if (Number(current.revision || 0) !== options.expectedRevision) throw new Error('Order changed in another tab.');
                } else if (options.replaceRejected) throw new Error('Saved order is missing.');
                validate(copy(snapshots.get(row.user_id)), [...rows.values()].filter(value => value.user_id === row.user_id).map(copy));
                rows.set(row.idempotency_key, copy(row)); return copy(row);
            });
            tail = operation.catch(() => {}); return operation;
        }
    };
}
function response(body, status = 200) {
    return { ok: status >= 200 && status < 300, status, redirected: false, json: async () => copy(body) };
}
function deferred() { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; }
function listeners(target = {}) {
    const handlers = new Map();
    return Object.assign(target, {
        addEventListener(name, callback) { if (!handlers.has(name)) handlers.set(name, new Set()); handlers.get(name).add(callback); },
        removeEventListener(name, callback) { if (handlers.has(name)) handlers.get(name).delete(callback); },
        dispatchEvent(event) { for (const callback of handlers.get(event.type) || []) callback(event); return true; }
    });
}
function harness() {
    let user = { id: 42, role: 'mega_dealer' }, fetcher, nextTimer = 1;
    const store = memoryStore(), calls = [], stateEvents = [], timers = new Map(), intervals = new Map(), local = new Map();
    const accepted = new Map();
    const worker = { state: 'activated', messages: [], postMessage(message, ports) {
        this.messages.push(message); queueMicrotask(() => ports[0].reply({ success: true, version: 'v8', updated: false }));
    } };
    const registration = { active: worker, async update() {} };
    const env = listeners({
        getOfflineUser: () => user,
        localStorage: { getItem: key => local.has(key) ? local.get(key) : null, setItem: (key, value) => local.set(key, String(value)), removeItem: key => local.delete(key) },
        location: { href: 'https://example.test/app/offline/home.html', pathname: '/app/offline/home.html' },
        document: listeners({ currentScript: { src: 'https://example.test/app/offline/data.js' }, visibilityState: 'visible' }),
        navigator: { onLine: true, serviceWorker: { async register(url, options) { calls.push({ registration: url, options }); return registration; } } },
        AbortController,
        setTimeout(callback, delay) { const id = nextTimer++; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        setInterval(callback, delay) { const id = nextTimer++; intervals.set(id, { callback, delay }); return id; },
        clearInterval(id) { intervals.delete(id); },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options && options.detail; } },
        MessageChannel: class { constructor() { this.port1 = { close() {} }; this.port2 = { reply: data => this.port1.onmessage({ data }) }; } },
        async fetch(url, options) {
            calls.push({ url, ...options });
            if (fetcher) return fetcher(url, options, defaultFetch);
            return defaultFetch(url, options);
        }
    });
    function defaultFetch(url, options) {
        if (url.endsWith('/snapshot')) {
            const data = snapshot(user.id);
            data.acknowledged_orders = [...accepted.keys()];
            data.orders = [...accepted.values()].flatMap(result => result.records);
            return response(data);
        }
        if (url.endsWith('/session')) return response({ user_id: user.id, csrf_token: 'fresh-example-csrf' });
        if (url.endsWith('/orders')) {
            const saved = JSON.parse(options.body);
            const result = accepted.get(saved.idempotency_key) || { success: true, records: [{ id: 1000 + accepted.size, product_id: 10, transaction_id: 'EXAMPLE-' + saved.idempotency_key, status: 'Pending' }] };
            accepted.set(saved.idempotency_key, result); return response(result);
        }
        throw new Error('Unexpected request: ' + url);
    }
    env.addEventListener('offline-sync-state', event => stateEvents.push(copy(event.detail)));
    const api = createData(env, store);
    return {
        api, env, store, calls, timers, intervals, accepted, stateEvents, worker, registration,
        setFetch(value) { fetcher = value; }, setUser(value) { user = value; },
        event(name) { env.dispatchEvent(new env.CustomEvent(name)); },
        retries() { return [...timers.entries()].filter(([, timer]) => [5000, 10000, 20000, 40000, 60000].includes(timer.delay)); },
        async fireRetry() {
            const entry = this.retries()[0]; assert.ok(entry, 'Expected a scheduled retry'); timers.delete(entry[0]); entry[1].callback(); await settle(); return entry[1].delay;
        }
    };
}
const posts = h => h.calls.filter(call => call.method === 'POST');
const requests = h => h.calls.filter(call => call.url);
async function settle() { for (let index = 0; index < 8; index++) await new Promise(resolve => setImmediate(resolve)); }
async function until(predicate, label) {
    for (let index = 0; index < 40; index++) { if (predicate()) return; await new Promise(resolve => setImmediate(resolve)); }
    assert.ok(predicate(), label);
}

test('startup verifies saved server acknowledgements, syncs the pending order, and refreshes resulting data', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const watcher = h.api.startUpdates(); await settle();
    assert.deepEqual(requests(h).map(call => call.url.split('/').pop()), ['snapshot', 'session', 'orders', 'snapshot']);
    assert.equal(posts(h).length, 1);
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    assert.equal(h.store.snapshots.get(42).orders[0].status, 'Pending');
    assert.equal(h.api.getSyncState().pending, 0);
    assert.equal(h.api.getSyncState().phase, 'ready');
    assert.ok(h.stateEvents.some(state => state && state.phase === 'syncing'));
    watcher.stop();
});

test('actual server recovery synchronizes automatically while the network interface remains online', async () => {
    const h = harness(); await h.api.queueOrder(order()); h.env.navigator.onLine = false;
    const watcher = h.api.startUpdates(); await settle(); assert.equal(requests(h).length, 0);
    h.env.navigator.onLine = true; h.event('offline-connection-restored'); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'synced');
    h.event('online'); h.event('focus'); h.event('offline-connection-restored'); await settle();
    assert.equal(posts(h).length, 1, 'A second reconnect cannot duplicate the accepted order');
    watcher.stop();
});

test('the reachability probe discovers server recovery without a browser online event', async () => {
    const h = harness(); await h.api.queueOrder(order()); h.env.navigator.onLine = false;
    h.setFetch(async (url, options, normal) => url.endsWith('/manifest.json') ? response({}) : normal(url, options));
    const watcher = h.api.startUpdates(); h.event('offline'); await settle();
    assert.equal(posts(h).length, 0);
    h.env.navigator.onLine = true;
    const health = [...h.intervals.values()].find(timer => timer.delay === 15000);
    assert.ok(health, 'A lightweight server reachability check must be registered');
    await health.callback(); await settle();
    assert.ok(requests(h).some(call => call.url.endsWith('/manifest.json') && call.method === 'HEAD'));
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'synced');
    watcher.stop(); assert.equal(h.intervals.size, 0);
});

test('overlapping reconnect events coalesce one transaction cycle', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred(); let first = true;
    h.setFetch(async (url, options, normal) => { if (url.endsWith('/snapshot') && first) { first = false; await gate.promise; } return normal(url, options); });
    const watcher = h.api.startUpdates();
    await until(() => requests(h).length === 1, 'First snapshot started');
    h.event('online'); h.event('focus'); h.event('offline-connection-restored'); h.env.document.dispatchEvent(new h.env.CustomEvent('visibilitychange'));
    assert.equal(posts(h).length, 0);
    gate.resolve(); await settle();
    assert.equal(posts(h).length, 1); assert.equal(h.accepted.size, 1);
    watcher.stop();
});

test('a manual synchronization overlapping automatic recovery cannot duplicate the transaction', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred(); let first = true;
    h.setFetch(async (url, options, normal) => {
        if (url.endsWith('/snapshot') && first) { first = false; await gate.promise; }
        return normal(url, options);
    });
    const watcher = h.api.startUpdates(); await until(() => requests(h).length === 1, 'Automatic refresh started');
    const manual = h.api.syncNow(); h.event('offline-connection-restored');
    gate.resolve(); await manual; await settle();
    assert.equal(posts(h).length, 1); assert.equal(h.accepted.size, 1);
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    watcher.stop();
});

test('a transaction queued while connected is automatically submitted without waiting for another reconnect', async () => {
    const h = harness(); const watcher = h.api.startUpdates(); await settle();
    await h.api.queueOrder(order()); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'synced');
    watcher.stop();
});

test('a new order arriving during an in-flight submission is drained in a follow-up cycle', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred(); let blocked = true;
    h.setFetch(async (url, options, normal) => {
        if (url.endsWith('/orders') && blocked) { blocked = false; await gate.promise; }
        return normal(url, options);
    });
    const watcher = h.api.startUpdates(); await until(() => posts(h).length === 1, 'Initial order submission started');
    await h.api.queueOrder(order({ idempotency_key: secondKey, items: [{ product_id: 10, quantity: 50, unit_price: 50 }] }));
    gate.resolve(); await settle();
    assert.deepEqual(posts(h).map(call => JSON.parse(call.body).idempotency_key).sort(), [firstKey, secondKey]);
    assert.ok((await h.api.listOrders()).every(row => row.status === 'synced'));
    watcher.stop();
});

test('an order queued during a manually owned sync is drained after the watcher joins that cycle', async () => {
    const h = harness(); const watcher = h.api.startUpdates(); await settle();
    h.env.navigator.onLine = false; await h.api.queueOrder(order()); await settle();
    h.env.navigator.onLine = true;
    const gate = deferred(); let first = true;
    h.setFetch(async (url, options, normal) => {
        if (url.endsWith('/orders') && first) { first = false; await gate.promise; }
        return normal(url, options);
    });
    const manual = h.api.syncNow(); await until(() => posts(h).length === 1, 'Manual synchronization captured its pending rows');
    await h.api.queueOrder(order({ idempotency_key: secondKey }));
    gate.resolve(); await manual; await settle();
    assert.deepEqual(posts(h).map(call => JSON.parse(call.body).idempotency_key).sort(), [firstKey, secondKey]);
    assert.ok((await h.api.listOrders()).every(row => row.status === 'synced'));
    assert.equal(h.api.getSyncState().pending, 0);
    watcher.stop();
});

test('a lost response retries the same reference and accepts the server acknowledgement without another POST', async () => {
    const h = harness(); await h.api.queueOrder(order()); let lost = true;
    h.setFetch(async (url, options, normal) => {
        const result = normal(url, options);
        if (url.endsWith('/orders') && lost) { lost = false; throw new TypeError('Response lost after commit'); }
        return result;
    });
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(h.api.getSyncState().phase, 'retrying');
    assert.equal((await h.api.listOrders())[0].status, 'pending');
    assert.equal(await h.fireRetry(), 5000);
    assert.equal(posts(h).length, 1, 'Acknowledged orders must not be submitted again');
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    watcher.stop();
});

test('a partial queue failure retries only the unaccepted transaction', async () => {
    const h = harness(); await h.api.queueOrder(order()); await h.api.queueOrder(order({ idempotency_key: secondKey }));
    let failSecond = true;
    h.setFetch(async (url, options, normal) => {
        if (url.endsWith('/orders') && JSON.parse(options.body).idempotency_key === secondKey && failSecond) {
            failSecond = false; return response({ message: 'Temporary save failure' }, 503);
        }
        return normal(url, options);
    });
    const watcher = h.api.startUpdates(); await settle();
    assert.equal((await h.api.listOrders()).find(row => row.idempotency_key === firstKey).status, 'synced');
    assert.equal((await h.api.listOrders()).find(row => row.idempotency_key === secondKey).status, 'pending');
    await h.fireRetry();
    assert.deepEqual(posts(h).map(call => JSON.parse(call.body).idempotency_key), [firstKey, secondKey, secondKey]);
    assert.equal(h.accepted.size, 2); assert.ok((await h.api.listOrders()).every(row => row.status === 'synced'));
    watcher.stop();
});

test('temporary server failures use bounded exponential backoff and preserve all pending work', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => response({ message: 'Temporary outage' }, 503));
    const watcher = h.api.startUpdates(); await settle();
    const delays = [];
    for (let index = 0; index < 6; index++) delays.push(await h.fireRetry());
    assert.deepEqual(delays, [5000, 10000, 20000, 40000, 60000, 60000]);
    assert.equal(posts(h).length, 0); assert.equal((await h.api.listOrders())[0].status, 'pending');
    watcher.stop(); assert.equal(h.retries().length, 0);
});

test('going offline cancels retries, and returning online recovers the original queue', async () => {
    const h = harness(); await h.api.queueOrder(order()); let reachable = false;
    h.setFetch(async (url, options, normal) => { if (!reachable) throw new TypeError('Disconnected'); return normal(url, options); });
    const watcher = h.api.startUpdates(); await settle(); assert.ok(h.retries().length);
    h.env.navigator.onLine = false; h.event('offline'); await settle();
    assert.equal(h.retries().length, 0); assert.equal(h.api.getSyncState().phase, 'offline');
    reachable = true; h.env.navigator.onLine = true; h.event('online'); await settle();
    assert.equal((await h.api.listOrders())[0].status, 'synced'); assert.equal(posts(h).length, 1);
    watcher.stop();
});

for (const status of [401, 403, 419]) test('HTTP ' + status + ' pauses automatic submission until the account is signed in again', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => response({ message: 'Sign in with the same account' }, status));
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(posts(h).length, 0); assert.equal((await h.api.listOrders())[0].status, 'pending');
    assert.equal(h.api.getSyncState().phase, 'auth_required'); assert.equal(h.retries().length, 0);
    h.event('online'); h.event('focus'); h.event('offline-connection-restored'); await settle();
    assert.equal(posts(h).length, 0); assert.equal(h.retries().length, 0);
    watcher.stop();
});

test('a CSRF expiry during submission pauses the pending queue instead of resubmitting new work', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async (url, options, normal) => url.endsWith('/orders') ? response({ message: 'Session expired' }, 419) : normal(url, options));
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'pending');
    assert.equal(h.api.getSyncState().phase, 'auth_required'); assert.equal(h.retries().length, 0);
    await h.api.queueOrder(order({ idempotency_key: secondKey })); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders()).length, 2);
    watcher.stop();
});

test('an explicit retry after signing in resumes synchronization and future automatic submissions', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => response({ message: 'Session expired' }, 401));
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(h.api.getSyncState().phase, 'auth_required');
    h.setFetch(undefined);
    const result = await h.api.syncNow(); await settle();
    assert.equal(result.synced, 1); assert.equal(h.api.getSyncState().phase, 'ready');
    await h.api.queueOrder(order({ idempotency_key: secondKey })); await settle();
    assert.equal(posts(h).length, 2);
    assert.ok((await h.api.listOrders()).every(row => row.status === 'synced'));
    watcher.stop();
});

test('another server account cannot receive the saved account transactions and does not trigger retry loops', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async (url, options, normal) => url.endsWith('/session') ? response({ user_id: 43, csrf_token: 'wrong-account-token' }) : normal(url, options));
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(posts(h).length, 0); assert.equal(h.api.getSyncState().phase, 'auth_required');
    assert.equal(h.retries().length, 0); assert.equal((await h.api.listOrders())[0].status, 'pending');
    watcher.stop();
});

test('validation failures remain for explicit review while other saved orders synchronize', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.store.snapshots.get(42).products[0].order_price = 55;
    await h.api.queueOrder(order({ idempotency_key: secondKey, items: [{ product_id: 10, quantity: 20, unit_price: 55 }] }));
    h.setFetch(async (url, options, normal) => {
        if (url.endsWith('/snapshot')) { const latest = normal(url, options); const data = await latest.json(); data.products[0].order_price = 55; return response(data); }
        if (url.endsWith('/orders') && JSON.parse(options.body).idempotency_key === firstKey) return response({ message: 'Price changed. Review the saved order.' }, 422);
        return normal(url, options);
    });
    const watcher = h.api.startUpdates(); await settle();
    const rows = await h.api.listOrders();
    assert.equal(rows.find(row => row.idempotency_key === firstKey).status, 'needs_review');
    assert.equal(rows.find(row => row.idempotency_key === secondKey).status, 'synced');
    assert.equal(h.api.getSyncState().needsReview, 1); assert.equal(h.api.getSyncState().pending, 0);
    h.event('offline-connection-restored'); await settle();
    assert.equal(posts(h).filter(call => JSON.parse(call.body).idempotency_key === firstKey).length, 1);
    watcher.stop();
});

test('an idempotency conflict stops resubmission and asks for online review of that reference', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async (url, options, normal) => url.endsWith('/orders') ? response({ message: 'Saved reference already belongs to another payload.' }, 409) : normal(url, options));
    const watcher = h.api.startUpdates(); await settle();
    const saved = (await h.api.listOrders())[0];
    assert.equal(saved.status, 'needs_review'); assert.equal(saved.last_http_status, 409);
    h.event('offline-connection-restored'); await settle();
    assert.equal(posts(h).length, 1); assert.equal(h.retries().length, 0);
    await assert.rejects(h.api.reviewOrder(firstKey), /online|reference|saved/i);
    watcher.stop();
});

test('failure to update optional app files never prevents accepted transaction syncing or latest data', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.registration.update = async () => { throw new Error('Files unavailable'); };
    const watcher = h.api.startUpdates(); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'synced');
    assert.equal(h.store.snapshots.get(42).orders.length, 1);
    assert.notEqual(h.api.getSyncState().phase, 'retrying');
    watcher.stop();
});

test('account changes during the initial refresh do not submit or alter the original account queue', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred();
    h.setFetch(async url => { if (url.endsWith('/snapshot')) { await gate.promise; return response(snapshot(42)); } throw new Error('No other request expected'); });
    const watcher = h.api.startUpdates(); await until(() => requests(h).length === 1, 'Refresh started');
    h.setUser({ id: 43, role: 'dealer' }); gate.resolve(); await settle();
    assert.equal(posts(h).length, 0); assert.equal((await h.store.list(42))[0].status, 'pending');
    assert.equal((await h.store.list(43)).length, 0); assert.equal(h.retries().length, 0);
    watcher.stop();
});

test('page exit removes automatic retries and listeners without discarding queued work', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => { throw new TypeError('Disconnected'); });
    h.api.startUpdates(); await settle(); assert.ok(h.retries().length);
    h.event('pagehide'); await settle();
    assert.equal(h.retries().length, 0); assert.equal(h.intervals.size, 0);
    const count = requests(h).length; h.event('online'); h.event('offline-connection-restored'); await settle();
    assert.equal(requests(h).length, count); assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('leaving the page during its preliminary refresh prevents a later automatic POST', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred();
    h.setFetch(async (url, options, normal) => { if (url.endsWith('/snapshot')) await gate.promise; return normal(url, options); });
    h.api.startUpdates(); await until(() => requests(h).length === 1, 'Refresh started before page exit');
    h.event('pagehide'); gate.resolve(); await settle();
    assert.equal(posts(h).length, 0); assert.equal(h.retries().length, 0);
    assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('leaving after a server commit still preserves its acknowledgement without starting another cycle', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const gate = deferred();
    h.setFetch(async (url, options, normal) => {
        const result = normal(url, options);
        if (url.endsWith('/orders')) await gate.promise;
        return result;
    });
    h.api.startUpdates(); await until(() => h.accepted.size === 1, 'Server committed before page exit');
    h.event('pagehide'); gate.resolve(); await settle();
    assert.equal(posts(h).length, 1); assert.equal((await h.api.listOrders())[0].status, 'synced');
    assert.equal(h.retries().length, 0); assert.equal(h.intervals.size, 0);
    assert.equal(requests(h).filter(call => call.url.endsWith('/snapshot')).length, 1);
});
