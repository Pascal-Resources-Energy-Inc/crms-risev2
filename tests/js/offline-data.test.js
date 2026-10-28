const assert = require('node:assert/strict');
const test = require('node:test');
const createData = require('../../public/offline/data.js');

const clone = value => value === undefined ? undefined : structuredClone(value);
const savedAt = '2026-10-28T00:00:00Z';
const firstKey = '00000000-0000-4000-8000-000000000001';
const secondKey = '00000000-0000-4000-8000-000000000002';

function snapshot(id = 42) {
    return {
        schema_version: 1, saved_at: savedAt,
        user: { id, role: 'mega_dealer', name: 'Example Account' }, profile: {},
        settings: { can_order: true, can_transact: true },
        customers: [{ id: 100, name: 'Assigned Customer' }],
        distributors: [{ id: 200, user_id: 900, name: 'Assigned AD' }],
        products: [
            { id: 10, name: 'Item A', price: 70, order_price: 50, dealer_stock: 8, ad_stock: 0, ad_id: 200, client_allowed: true },
            { id: 11, name: 'Item B', price: 90, order_price: 75, dealer_stock: 4, ad_stock: 1, ad_id: 200, client_allowed: true }
        ],
        transactions: [], orders: [], stoves: [], acknowledged_orders: []
    };
}
function order(overrides = {}) {
    return {
        user_id: 42, type: 'client', client_id: 100, ad_id: null,
        items: [{ product_id: 10, quantity: 2, unit_price: 70 }],
        payment_method: 'cash', delivery_type: 'pickup', snapshot_saved_at: savedAt,
        idempotency_key: firstKey, ...overrides
    };
}
function memoryStore(initial = [snapshot()]) {
    const snapshots = new Map(initial.map(value => [Number(value.user.id), clone(value)]));
    const rows = new Map();
    let tail = Promise.resolve();
    return {
        snapshots, rows, failPut: false, failAdd: false,
        async get(id) { return clone(snapshots.get(Number(id))); },
        async put(value) {
            if (this.failPut) throw new Error('Storage quota reached');
            snapshots.set(Number(value.user.id), clone(value));
        },
        async list(id) { return [...rows.values()].filter(row => Number(row.user_id) === Number(id)).map(clone); },
        async saveOrder(row, expected) {
            const current = rows.get(row.idempotency_key);
            if (expected && (!current || current.user_id !== expected.user_id || Number(current.revision || 0) !== Number(expected.revision || 0) ||
                (current.status === 'synced' && row.status !== 'synced'))) return clone(current);
            rows.set(row.idempotency_key, clone(row)); return clone(row);
        },
        addOrder(row, check, options = {}) {
            const operation = tail.then(() => {
                if (this.failAdd) throw new Error('Storage quota reached');
                const current = rows.get(row.idempotency_key);
                if (current) {
                    if (current.user_id !== row.user_id) throw new Error('Order reference belongs to another account.');
                    if (!options.replaceRejected || current.status !== 'needs_review') return clone(current);
                    if (Number(current.revision || 0) !== options.expectedRevision) throw new Error('Saved order changed in another tab.');
                } else if (options.replaceRejected) throw new Error('Original saved order is missing.');
                check(clone(snapshots.get(row.user_id)), [...rows.values()].filter(value => value.user_id === row.user_id).map(clone));
                rows.set(row.idempotency_key, clone(row)); return clone(row);
            });
            tail = operation.catch(() => {}); return operation;
        }
    };
}
function response(body, status = 200) {
    return { ok: status >= 200 && status < 300, status, redirected: false, json: async () => clone(body) };
}
function harness(store = memoryStore(), id = 42) {
    let user = { id, role: 'mega_dealer' }, fetcher;
    const calls = [], events = new Map(), local = new Map(), intervals = new Map();
    const storage = {
        getItem: key => local.has(key) ? local.get(key) : null,
        setItem: (key, value) => local.set(key, String(value)), removeItem: key => local.delete(key)
    };
    const worker = { state: 'activated', messages: [], postMessage(message, ports) {
        this.messages.push(message); queueMicrotask(() => ports[0].reply({ success: true, version: 'v7', updated: false }));
    } };
    const registration = { active: worker, async update() {} };
    const env = {
        getOfflineUser: () => user, localStorage: storage,
        location: { href: 'https://example.test/app/offline/products.html', pathname: '/app/offline/products.html' },
        document: { currentScript: { src: 'https://example.test/app/offline/data.js' }, visibilityState: 'visible', addEventListener() {}, removeEventListener() {} },
        navigator: { onLine: true, serviceWorker: { async register(url, options) { calls.push({ registration: url, options }); return registration; } } },
        AbortController, setTimeout, clearTimeout,
        setInterval(callback, delay) { const timer = intervals.size + 1; intervals.set(timer, { callback, delay }); return timer; },
        clearInterval: timer => intervals.delete(timer),
        addEventListener(name, callback) { if (!events.has(name)) events.set(name, new Set()); events.get(name).add(callback); },
        removeEventListener: (name, callback) => events.has(name) && events.get(name).delete(callback),
        CustomEvent: class { constructor(type) { this.type = type; } },
        dispatchEvent(event) { for (const callback of events.get(event.type) || []) callback(event); return true; },
        MessageChannel: class { constructor() { this.port1 = { close() {} }; this.port2 = { reply: data => this.port1.onmessage({ data }) }; } },
        async fetch(url, options) {
            calls.push({ url, ...options });
            if (fetcher) return fetcher(url, options);
            if (url.endsWith('/snapshot')) return response(snapshot(user.id));
            if (url.endsWith('/session')) return response({ user_id: user.id, csrf_token: 'synthetic-csrf-token' });
            if (url.endsWith('/orders')) {
                const row = JSON.parse(options.body);
                return response({ success: true, records: row.items.map(item => ({ id: 1000 + item.product_id, product_id: item.product_id, status: row.type === 'order' ? 'Pending' : 'Completed' })) });
            }
            throw new Error('Unexpected request');
        }
    };
    return { api: createData(env, store), store, calls, env, local, storage, worker, registration, intervals,
        setUser: value => { user = value; }, setFetch: value => { fetcher = value; } };
}
const posts = h => h.calls.filter(call => call.method === 'POST');

test('AD orders can be queued above zero or insufficient AD stock', async () => {
    const h = harness();
    await h.api.queueOrder(order({ type: 'order', client_id: null, ad_id: 200, items: [{ product_id: 10, quantity: 500, unit_price: 50 }] }));
    const saved = (await h.api.listOrders())[0];
    assert.equal(saved.items[0].quantity, 500); assert.equal(saved.status, 'pending');
    assert.equal(posts(h).length, 0);
});

test('client transactions remain limited to the saved dealer stock', async () => {
    const h = harness();
    await assert.rejects(h.api.queueOrder(order({ items: [{ product_id: 10, quantity: 9, unit_price: 70 }] })), /stock|available/i);
    assert.equal((await h.api.listOrders()).length, 0);
});

test('client reservations are checked atomically across simultaneous saves', async () => {
    const h = harness();
    const results = await Promise.allSettled([
        h.api.queueOrder(order({ items: [{ product_id: 10, quantity: 5, unit_price: 70 }] })),
        h.api.queueOrder(order({ idempotency_key: secondKey, items: [{ product_id: 10, quantity: 5, unit_price: 70 }] }))
    ]);
    assert.equal(results.filter(result => result.status === 'fulfilled').length, 1);
    assert.equal((await h.api.listOrders()).length, 1);
    assert.equal((await h.api.getSnapshot()).products[0].dealer_stock, 3);
});

test('failed refresh keeps the previous saved copy and pending transactions', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => { throw new TypeError('Network unavailable'); });
    await assert.rejects(h.api.refresh(), /server|reach|network/i);
    assert.equal(h.store.snapshots.get(42).products[0].price, 70);
    assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('snapshot writes preserve the previous data when device storage fails', async () => {
    const h = harness(); h.store.failPut = true;
    const latest = snapshot(); latest.products[0].price = 90;
    h.setFetch(async () => response(latest));
    await assert.rejects(h.api.refresh(), /Storage quota/);
    assert.equal(h.store.snapshots.get(42).products[0].price, 70);
});

test('snapshots and pending orders are scoped to their original account', async () => {
    const store = memoryStore([snapshot(42), snapshot(43)]), a = harness(store, 42), b = harness(store, 43);
    await a.api.queueOrder(order()); await b.api.queueOrder(order({ user_id: 43, idempotency_key: secondKey }));
    assert.equal((await a.api.getSnapshot()).user.id, 42); assert.equal((await b.api.getSnapshot()).user.id, 43);
    assert.equal((await a.api.listOrders())[0].user_id, 42); assert.equal((await b.api.listOrders())[0].user_id, 43);
    a.setFetch(async () => response(snapshot(43)));
    await assert.rejects(a.api.refresh(), /account|incomplete/i);
    assert.equal(store.snapshots.get(42).user.id, 42);
});

test('another signed-in server account blocks synchronization before any transaction POST', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => response({ user_id: 43, csrf_token: 'another-account-token' }));
    await assert.rejects(h.api.sync(), /same account|account changed|account/i);
    assert.equal(posts(h).length, 0); assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('missing fresh CSRF token cannot submit or discard a pending transaction', async () => {
    const h = harness(); await h.api.queueOrder(order());
    h.setFetch(async () => response({ user_id: 42, csrf_token: '' }));
    await assert.rejects(h.api.sync(), error => error.httpStatus === 419 && /session|renew/i.test(error.message));
    assert.equal(posts(h).length, 0); assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('an expired local identity cannot submit or mutate the original account queue', async () => {
    const h = harness(); await h.api.queueOrder(order()); h.setUser(null);
    await assert.rejects(h.api.sync(), /sign in|account/i);
    assert.equal(posts(h).length, 0); assert.equal((await h.store.list(42))[0].status, 'pending');
});

test('an account change before the atomic queue commit keeps the original queue unchanged', async () => {
    const h = harness();
    const add = h.store.addOrder.bind(h.store);
    h.store.addOrder = (row, check, options) => {
        h.setUser({ id: 43, role: 'dealer' });
        return add(row, check, options);
    };
    await assert.rejects(h.api.queueOrder(order()), /account|sign in/i);
    assert.equal((await h.store.list(42)).length, 0);
    assert.equal((await h.store.list(43)).length, 0);
});

test('a definitive price validation failure marks only that order for review and continues other pending work', async () => {
    const h = harness(); await h.api.queueOrder(order()); await h.api.queueOrder(order({ idempotency_key: secondKey, items: [{ product_id: 11, quantity: 1, unit_price: 90 }] }));
    h.setFetch(async (url, options) => {
        if (url.endsWith('/session')) return response({ user_id: 42, csrf_token: 'fresh-token' });
        const row = JSON.parse(options.body);
        return row.idempotency_key === firstKey ? response({ message: 'Item A price changed. Refresh and review.' }, 422) : response({ success: true, records: [] });
    });
    await h.api.sync();
    const rows = await h.api.listOrders();
    assert.equal(rows.find(row => row.idempotency_key === firstKey).status, 'needs_review');
    assert.equal(rows.find(row => row.idempotency_key === secondKey).status, 'synced');
    assert.equal(posts(h).length, 2);
});

test('a lost response keeps the same reference for a safe server retry', async () => {
    const h = harness(); await h.api.queueOrder(order()); let lost = true;
    h.setFetch(async (url, options) => {
        if (url.endsWith('/session')) return response({ user_id: 42, csrf_token: 'fresh-token' });
        if (lost) { lost = false; throw new TypeError('Response lost after server commit'); }
        return response({ success: true, records: [] });
    });
    await assert.rejects(h.api.sync());
    assert.equal((await h.api.listOrders())[0].status, 'pending');
    await h.api.sync();
    assert.deepEqual(posts(h).map(call => JSON.parse(call.body).idempotency_key), [firstKey, firstKey]);
    assert.equal((await h.api.listOrders())[0].status, 'synced');
});

test('reviewing a rejected order keeps its reference and creates one revised pending row', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const current = h.store.rows.get(firstKey); h.store.rows.set(firstKey, { ...current, status: 'needs_review', last_error: 'Price changed' });
    const latest = snapshot(); latest.products[0].price = 75; h.store.snapshots.set(42, latest);
    const reviewed = await h.api.reviewOrder(firstKey, { replaceCart: true });
    const context = reviewed.context || JSON.parse(h.local.get('offlineCartContext'));
    const items = reviewed.items || JSON.parse(h.local.get('dealerCartData'));
    const revised = await h.api.queueOrder(order({ ...context, idempotency_key: firstKey, retry_key: firstKey, items }));
    assert.equal(revised.idempotency_key, firstKey); assert.equal(revised.revision, 1); assert.equal(revised.status, 'pending');
    assert.equal(revised.items[0].unit_price, 75); assert.equal((await h.api.listOrders()).length, 1);
});

test('acknowledged server references reconcile a lost response without another POST or double reservation', async () => {
    const h = harness(); await h.api.queueOrder(order());
    const accepted = snapshot(); accepted.acknowledged_orders = [firstKey]; accepted.products[0].dealer_stock = 6;
    h.setFetch(async url => { assert.ok(url.endsWith('/snapshot')); return response(accepted); });
    await h.api.refresh();
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    assert.equal((await h.api.getSnapshot()).products[0].dealer_stock, 6);
    assert.equal(await h.api.sync(), 0); assert.equal(posts(h).length, 0);
});

test('a lost AD acknowledgement recovers the authoritative sequential transaction ID', async () => {
    const h = harness(); await h.api.queueOrder(order({ type: 'order', ad_id: 200, client_id: null,
        items: [{ product_id: 10, quantity: 20, unit_price: 50 }] }));
    const accepted = snapshot(); accepted.acknowledged_orders = [firstKey];
    accepted.acknowledged_order_results = { [firstKey]: [{ id: 123, product_id: 10, transaction_id: 'PRORD-0123', status: 'Pending' }] };
    h.setFetch(async () => response(accepted)); await h.api.refresh();
    const row = (await h.api.listOrders())[0];
    assert.equal(row.status, 'synced'); assert.equal(row.result.records[0].transaction_id, 'PRORD-0123');
    assert.equal(await h.api.sync(), 0); assert.equal(posts(h).length, 0);
});

test('server history IDs prevent a synchronized client stock movement from being counted twice', async () => {
    const h = harness(); await h.api.queueOrder(order()); await h.api.sync();
    const result = (await h.api.listOrders())[0].result;
    const accepted = snapshot(); accepted.products[0].dealer_stock = 6; accepted.transactions = result.records;
    h.setFetch(async () => response(accepted)); await h.api.refresh();
    assert.equal((await h.api.getSnapshot()).products[0].dealer_stock, 6);
});

test('data and app-file checks share one in-flight update and never submit pending work', async () => {
    const h = harness(); await h.api.queueOrder(order());
    await Promise.all([h.api.updateAll(), h.api.updateAll(), h.api.updateAll()]);
    assert.equal(h.calls.filter(call => call.url && call.url.endsWith('/snapshot')).length, 1);
    assert.equal(h.calls.filter(call => call.registration).length, 1);
    assert.equal(h.worker.messages.length, 1); assert.equal(posts(h).length, 0);
    assert.equal((await h.api.listOrders())[0].status, 'pending');
});

test('an expired data session does not block public app-file updates', async () => {
    const h = harness(); h.setFetch(async () => response({ message: 'Unauthenticated' }, 401));
    const result = await h.api.updateAll();
    assert.ok(result.dataError); assert.equal(result.files.success, true); assert.equal(h.worker.messages.length, 1);
});

test('a failed app-file download does not block refreshed account data', async () => {
    const h = harness(); h.registration.update = async () => { throw new Error('App download failed'); };
    const latest = snapshot(); latest.products[0].price = 80; h.setFetch(async () => response(latest));
    const result = await h.api.updateAll();
    assert.ok(result.filesError); assert.equal(h.store.snapshots.get(42).products[0].price, 80);
});

test('reconnecting automatically synchronizes pending transactions and refreshes the resulting saved data', async () => {
    const h = harness(); await h.api.queueOrder(order()); h.env.navigator.onLine = false;
    const watcher = h.api.startUpdates();
    assert.equal(h.calls.length, 0);
    h.env.navigator.onLine = true;
    h.env.dispatchEvent(new h.env.CustomEvent('offline-connection-restored'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.calls.filter(call => call.url && call.url.endsWith('/snapshot')).length, 2);
    assert.equal(h.worker.messages.length, 1); assert.equal(posts(h).length, 1);
    assert.equal((await h.api.listOrders())[0].status, 'synced');
    h.env.dispatchEvent(new h.env.CustomEvent('focus'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.calls.filter(call => call.url && call.url.endsWith('/snapshot')).length, 2);
    watcher.stop(); assert.equal(h.intervals.size, 0);
    h.env.dispatchEvent(new h.env.CustomEvent('offline-connection-restored'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(h.calls.filter(call => call.url && call.url.endsWith('/snapshot')).length, 2);
});
