/* Account-scoped saved data and durable, idempotent offline transactions. */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) module.exports = factory;
    else root.OfflineData = factory(root);
})(typeof window !== 'undefined' ? window : this, function (env, adapter) {
    'use strict';
    const config = env.OfflineDataConfig || {};
    const script = env.document && env.document.currentScript;
    const base = new URL('../', script && script.src || new URL('offline/data.js', env.location.href));
    const store = adapter || browserStore();
    let refreshTask, syncTask, assetsTask, updateTask, cycleTask, watcher, workEpoch = 0, lastAssetsAttempt = 0;
    let pendingUpdate, updateTimer = null, updateApplying = false, updateStopped = false, updateHooks = false;
    let statusNode, syncButton, updateNote, titleNode, metaNode, statusIndicator;
    let syncState = { user_id: null, phase: 'idle', pending: 0, needsReview: 0, lastSyncedAt: null, lastUpdatedAt: null, retryAt: null, message: '', automatic: true };

    function identity() { return config.user || (typeof env.getOfflineUser === 'function' ? env.getOfflineUser() : null); }
    function userId() {
        const id = Number((identity() || {}).id);
        if (!Number.isSafeInteger(id) || id < 1) throw new Error('Sign in online before preparing your offline account.');
        return id;
    }
    function sameAccount(id) {
        if (userId() !== id) throw failure('Account changed. Reopen the page for the current account. Your saved work is kept.', 403);
    }
    function emit(type, detail) { if (env.dispatchEvent && env.CustomEvent) env.dispatchEvent(new env.CustomEvent(type, { detail })); }
    function failure(message, status) { const error = new Error(message); error.httpStatus = status; return error; }
    function browserStore() {
        function open() {
            return new Promise((resolve, reject) => {
                const request = env.indexedDB.open('GazLiteOffline', 1);
                const timer = env.setTimeout(() => reject(new Error('Device storage is unavailable. Your saved work is kept.')), 5000);
                request.onupgradeneeded = () => {
                    request.result.createObjectStore('snapshots', { keyPath: 'user_id' });
                    request.result.createObjectStore('outbox', { keyPath: 'idempotency_key' });
                };
                request.onerror = request.onblocked = () => { env.clearTimeout(timer); reject(new Error('Unable to open saved offline data.')); };
                request.onsuccess = () => { env.clearTimeout(timer); resolve(request.result); };
            });
        }
        async function run(names, mode, action) {
            const db = await open();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(names, mode);
                let result, error;
                tx.oncomplete = () => { db.close(); resolve(result); };
                tx.onabort = tx.onerror = () => { db.close(); reject(error || tx.error || new Error('Offline work could not be saved.')); };
                const fail = value => { error = value; tx.abort(); };
                try { action(tx, value => { result = value; }, fail); } catch (value) { fail(value); }
            });
        }
        return {
            get: id => run(['snapshots'], 'readonly', (tx, done) => {
                tx.objectStore('snapshots').get(id).onsuccess = event => done(event.target.result && event.target.result.data);
            }),
            put: data => run(['snapshots'], 'readwrite', tx => tx.objectStore('snapshots').put({ user_id: Number(data.user.id), data })),
            list: id => run(['outbox'], 'readonly', (tx, done) => {
                tx.objectStore('outbox').getAll().onsuccess = event => done(event.target.result.filter(row => Number(row.user_id) === id));
            }),
            saveOrder: (row, expected) => run(['outbox'], 'readwrite', (tx, done) => {
                const outbox = tx.objectStore('outbox');
                outbox.get(row.idempotency_key).onsuccess = event => {
                    const current = event.target.result;
                    if (!current || current.user_id !== expected.user_id || Number(current.revision || 0) !== Number(expected.revision || 0) ||
                        !(current.status === 'pending' || (row.status === 'synced' && current.status === 'needs_review'))) return done(current);
                    outbox.put(row); done(row);
                };
            }),
            addOrder: (row, check, options) => run(['outbox', 'snapshots'], 'readwrite', (tx, done, fail) => {
                const outbox = tx.objectStore('outbox');
                outbox.get(row.idempotency_key).onsuccess = event => {
                    const current = event.target.result;
                    if (current) {
                        if (current.user_id !== row.user_id) return fail(new Error('This reference belongs to another account.'));
                        if (!options.replaceRejected || current.status !== 'needs_review') return done(current);
                        if (Number(current.revision || 0) !== options.expectedRevision) return fail(new Error('This order changed in another tab. Review its current version in History.'));
                    } else if (options.replaceRejected) return fail(new Error('The saved order is no longer available.'));
                    tx.objectStore('snapshots').get(row.user_id).onsuccess = snapshotEvent => {
                        outbox.getAll().onsuccess = rowsEvent => {
                            try {
                                check(snapshotEvent.target.result && snapshotEvent.target.result.data,
                                    rowsEvent.target.result.filter(entry => entry.user_id === row.user_id));
                                if (current) outbox.put(row); else outbox.add(row);
                                done(row);
                            } catch (error) { fail(error); }
                        };
                    };
                };
            })
        };
    }
    async function request(path, options) {
        const controller = new env.AbortController();
        const timer = env.setTimeout(() => controller.abort(), 20000);
        try {
            const response = await env.fetch(new URL(path, base).href, Object.assign({
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: { Accept: 'application/json' }
            }, options));
            if (response.redirected || [401, 419].includes(response.status)) throw failure('Sign in online with this account, then update or sync again. Your saved work is kept.', response.redirected ? 401 : response.status);
            const json = await response.json().catch(() => null);
            if (!response.ok || !json) {
                const errors = json && json.errors ? Object.values(json.errors).flat().join(' ') : '';
                throw failure(errors || (json && json.message) || 'The app server is unavailable. Your saved work is kept.', response.status);
            }
            return json;
        } catch (error) {
            if (error.name === 'AbortError' || error instanceof TypeError) throw failure('Cannot reach the app server. Using your saved offline data.', 0);
            throw error;
        } finally { env.clearTimeout(timer); }
    }
    function validateSnapshot(data, id) {
        if (data && data.user && Number(data.user.id) !== id) throw failure('Sign in online with the same account to sync its saved transactions.', 403);
        if (!data || data.schema_version !== 1 || Number((data.user || {}).id) !== id || !Number.isFinite(Date.parse(data.saved_at)) ||
            !data.settings || !['products', 'customers', 'distributors', 'transactions'].every(key => Array.isArray(data[key]))) {
            const error = new Error('The offline update is incomplete. Your previous saved copy is kept.');
            error.code = 'INVALID_SNAPSHOT'; throw error;
        }
        return data;
    }
    async function reconcileAccepted(data, id) {
        const accepted = new Set(data.acknowledged_orders || []);
        for (const row of await store.list(id)) {
            if (row.status === 'synced' || !accepted.has(row.idempotency_key)) continue;
            const records = data.acknowledged_order_results && data.acknowledged_order_results[row.idempotency_key];
            await store.saveOrder(Object.assign({}, row, {
                status: 'synced', synced_at: data.saved_at, last_error: null,
                result: { success: true, acknowledged: true, records: Array.isArray(records) ? records : [], message: 'Already saved by the server.' }
            }), row);
        }
    }
    async function refresh() {
        if (refreshTask) return refreshTask;
        refreshTask = (async () => {
            const id = userId();
            const data = validateSnapshot(await request('api/offline/snapshot'), id);
            if (userId() !== id) throw new Error('Account changed while updating. Please update again.');
            await store.put(data);
            if (env.OfflineAuth && Object.prototype.hasOwnProperty.call(data, 'offline_login')) await env.OfflineAuth.reconcile(data.offline_login, id);
            await reconcileAccepted(data, id);
            emit('offline-data-updated'); emit('offline-outbox-updated');
            setSyncState({ lastUpdatedAt: data.saved_at });
            return data;
        })();
        try { return await refreshTask; } finally { refreshTask = null; }
    }
    function withReservations(data, rows) {
        const copy = JSON.parse(JSON.stringify(data)), accepted = new Set(data.acknowledged_orders || []);
        rows.filter(row => row.type === 'client' && row.status !== 'needs_review' && !accepted.has(row.idempotency_key)).forEach(row => row.items.forEach(item => {
            if (row.status === 'synced') {
                const records = row.result && Array.isArray(row.result.records) ? row.result.records : [];
                const record = records.find(entry => String(entry.product_id) === String(item.product_id));
                if (record && data.transactions.some(entry => (record.id != null && String(entry.id) === String(record.id)) ||
                    (record.transaction_id && entry.transaction_id === record.transaction_id))) return;
            }
            const product = copy.products.find(entry => Number(entry.id) === Number(item.product_id));
            if (product) product.dealer_stock = Math.max(0, Number(product.dealer_stock) - Number(item.quantity));
        }));
        return copy;
    }
    async function getSnapshot() {
        const id = userId(), data = await store.get(id);
        if (!data) { sameAccount(id); return null; }
        const rows = await store.list(id);
        sameAccount(id);
        return withReservations(validateSnapshot(data, id), rows);
    }
    function validateOrder(snapshot, context, items) {
        if (!snapshot || !context || Number(context.user_id) !== Number(snapshot.user.id)) throw new Error('Open Products to start a cart for this account.');
        if (!['client', 'order'].includes(context.type)) throw new Error('Choose the transaction type in Products.');
        if (!snapshot.settings[context.type === 'order' ? 'can_order' : 'can_transact']) throw new Error('This account cannot create that transaction.');
        const recipient = (context.type === 'order' ? snapshot.distributors : snapshot.customers)
            .find(entry => String(entry.id) === String(context.type === 'order' ? context.ad_id : context.client_id));
        if (!recipient) throw new Error('Choose an eligible customer or Area Distributor in Products.');
        if (!Array.isArray(items) || !items.length || items.length > 100) throw new Error('Your cart must contain between 1 and 100 products.');
        const seen = new Set();
        const checked = items.map(item => {
            const id = Number(item.product_id || item.id), quantity = Number(item.quantity);
            const product = snapshot.products.find(entry => Number(entry.id) === id);
            if (!product || seen.has(id)) throw new Error('A cart product is unavailable. Review Products.');
            seen.add(id);
            if (context.type === 'order' && String(product.ad_id) !== String(context.ad_id)) throw new Error('A product does not belong to the selected Area Distributor.');
            if (context.type === 'client' && !product.client_allowed) throw new Error(product.name + ' is unavailable for client transactions.');
            if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > 1000000) throw new Error(product.name + ': enter a whole quantity from 1 to 1,000,000.');
            if (context.type === 'client' && (!Number.isFinite(Number(product.dealer_stock)) || quantity > Number(product.dealer_stock))) throw new Error(product.name + ': quantity exceeds your available saved stock.');
            const rawPrice = context.type === 'order' ? product.order_price : product.price;
            const supplied = item.unit_price ?? item.price, price = Number(rawPrice);
            if (rawPrice == null || rawPrice === '' || supplied == null || supplied === '' || !Number.isFinite(price) || price < 0 ||
                !Number.isFinite(Number(supplied)) || Math.abs(Number(supplied) - price) > 0.005) throw new Error(product.name + ': price changed. Return to Products and review your cart.');
            return { product_id: id, quantity, unit_price: price, name: product.name };
        });
        return { recipient, items: checked, total: checked.reduce((sum, item) => sum + item.quantity * item.unit_price, 0) };
    }
    async function queueOrder(order) {
        const id = userId();
        if (!/^[a-f0-9-]{36}$/i.test(order.idempotency_key || '')) throw new Error('Missing order reference. Review your cart again.');
        if (!['cash', 'gcash', 'bank_transfer', 'credit'].includes(order.payment_method) || !['pickup', 'delivery'].includes(order.delivery_type)) throw new Error('Choose a valid payment and fulfilment method.');
        const existing = (await store.list(id)).find(row => row.idempotency_key === order.idempotency_key);
        sameAccount(id);
        if (existing && existing.status !== 'needs_review') return existing;
        if (existing && order.retry_key !== order.idempotency_key) throw new Error('Review this saved order in History before changing it.');
        const checked = validateOrder(await getSnapshot(), order, order.items);
        const row = Object.assign({}, order, {
            user_id: id, items: checked.items, total_amount: checked.total,
            recipient_name: checked.recipient.display_name || checked.recipient.name,
            status: 'pending', revision: existing ? Number(existing.revision || 0) + 1 : 0,
            created_at: existing ? existing.created_at : new Date().toISOString(), last_error: null
        });
        delete row.result; delete row.synced_at; delete row.rejected_at;
        const saved = await store.addOrder(row, (raw, rows) => {
            sameAccount(id);
            validateOrder(withReservations(validateSnapshot(raw, id), rows), order, order.items);
        }, { replaceRejected: !!existing, expectedRevision: existing ? Number(existing.revision || 0) : null });
        emit('offline-outbox-updated'); emit('offline-order-queued'); return saved;
    }
    async function listOrders() { return store.list(userId()); }
    async function sync(options) {
        if (syncTask) return syncTask;
        syncTask = (async () => {
            const id = userId(), data = await store.get(id);
            if (data) await reconcileAccepted(validateSnapshot(data, id), id);
            const pending = (await store.list(id)).filter(row => row.status === 'pending');
            if (!pending.length) return 0;
            const session = await request('api/offline/session');
            if (Number(session.user_id) !== id) throw failure('Sign in online with the same account to sync your saved transactions.', 403);
            if (!session.csrf_token) throw failure('Your online session needs to be renewed before transactions can sync.', 419);
            let count = 0;
            for (const row of pending) {
                if (options && options.shouldContinue && !options.shouldContinue()) throw cancelled();
                if (userId() !== id) throw new Error('Account changed. Remaining transactions are kept for their original account.');
                const current = (await store.list(id)).find(entry => entry.idempotency_key === row.idempotency_key);
                if (!current || current.status !== 'pending' || Number(current.revision || 0) !== Number(row.revision || 0)) continue;
                try {
                    const result = await request('api/offline/orders', {
                        method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': session.csrf_token }, body: JSON.stringify(row)
                    });
                    if (!result.success) throw new Error(result.message || 'The server could not accept this transaction.');
                    await store.saveOrder(Object.assign({}, row, { status: 'synced', synced_at: new Date().toISOString(), result, last_error: null }), row); count++;
                } catch (error) {
                    if ([409, 422].includes(error.httpStatus)) {
                        await store.saveOrder(Object.assign({}, row, { status: 'needs_review', rejected_at: new Date().toISOString(), last_error: error.message, last_http_status: error.httpStatus }), row);
                        continue;
                    }
                    await store.saveOrder(Object.assign({}, row, { last_error: error.message }), row); throw error;
                }
            }
            return count;
        })();
        try { return await syncTask; } finally { syncTask = null; emit('offline-outbox-updated'); }
    }
    async function reviewOrder(key, options) {
        const id = userId(), row = (await store.list(id)).find(entry => entry.idempotency_key === key);
        if (!row || row.status !== 'needs_review') throw new Error('Only rejected orders can be revised. Pending orders retain their saved reference until the server responds.');
        if (row.last_http_status === 409) throw new Error('This order reference conflicts with a server record. Check your online transaction history before making another order.');
        const snapshot = await getSnapshot();
        if (!snapshot) throw new Error('Update offline data before reviewing this order.');
        const issues = [], recipients = row.type === 'order' ? snapshot.distributors : snapshot.customers;
        const recipient = recipients.find(entry => String(entry.id) === String(row.type === 'order' ? row.ad_id : row.client_id));
        if (!recipient) issues.push('Choose an eligible recipient in Products. The original recipient is no longer available.');
        const context = {
            user_id: id, type: row.type, ad_id: row.type === 'order' && recipient ? recipient.id : null,
            client_id: row.type === 'client' && recipient ? recipient.id : null, retry_key: key,
            payment_method: row.payment_method, delivery_type: row.delivery_type, snapshot_saved_at: snapshot.saved_at, review_issues: issues
        };
        const items = row.items.map(item => {
            const product = snapshot.products.find(entry => String(entry.id) === String(item.product_id));
            if (!product) { issues.push(item.name + ' is no longer available. Remove it from your cart.'); return Object.assign({}, item, { id: item.product_id, price: item.unit_price, unavailable: true }); }
            if (row.type === 'client' && Number(item.quantity) > Number(product.dealer_stock)) issues.push(product.name + ': reduce quantity to match your saved stock.');
            const price = row.type === 'order' ? product.order_price : product.price;
            return Object.assign({}, item, { id: product.id, price, unit_price: price, name: product.name, description: product.description, image: product.image });
        });
        const storage = env.localStorage;
        let cart;
        try { cart = JSON.parse(storage.getItem('dealerCartData') || '[]'); } catch (_) { cart = ['invalid']; }
        if (!(options && options.replaceCart) && ((Array.isArray(cart) ? cart.length : cart) || storage.getItem('dealerOrderData'))) {
            const error = new Error('Reviewing this order replaces the current unsent cart. Confirm replacement to continue.'); error.code = 'CART_REPLACEMENT_REQUIRED'; throw error;
        }
        const current = (await store.list(id)).find(entry => entry.idempotency_key === key);
        if (userId() !== id || !current || current.status !== 'needs_review' || Number(current.revision || 0) !== Number(row.revision || 0)) throw new Error('The account or order changed. Open History and review again.');
        const changes = { dealerCartData: JSON.stringify(items), dealerCartItems: String(items.reduce((sum, item) => sum + Number(item.quantity), 0)), offlineCartContext: JSON.stringify(context), dealerOrderData: null };
        const before = {};
        try {
            Object.keys(changes).forEach(name => { before[name] = storage.getItem(name); });
            Object.entries(changes).forEach(([name, value]) => value === null ? storage.removeItem(name) : storage.setItem(name, value));
        } catch (error) {
            Object.entries(before).forEach(([name, value]) => { try { value === null ? storage.removeItem(name) : storage.setItem(name, value); } catch (_) {} });
            throw new Error('Unable to prepare the review cart. Your saved order is kept.');
        }
        return { url: recipient ? 'cart.html' : 'products.html', issues, context, items };
    }
    function activeWorker(registration) {
        const worker = registration.installing || registration.waiting || registration.active;
        if (!worker) return Promise.reject(new Error('Offline pages are still installing. Try again while connected.'));
        if (worker.state === 'activated') return Promise.resolve(worker);
        return new Promise((resolve, reject) => {
            const finish = error => { env.clearTimeout(timer); worker.removeEventListener('statechange', changed); error ? reject(error) : resolve(worker); };
            const changed = () => { if (worker.state === 'activated') finish(); else if (worker.state === 'redundant') finish(new Error('The new offline version could not be installed. Your saved copy is kept.')); };
            const timer = env.setTimeout(() => finish(new Error('Offline installation is still pending. Try again while connected.')), 20000);
            worker.addEventListener('statechange', changed); changed();
        });
    }
    function scheduleApply(delay = 500) {
        if (!pendingUpdate || updateApplying || updateStopped || updateTimer !== null) return;
        updateTimer = env.setTimeout(applyUpdate, delay);
    }
    function applyUpdate() {
        updateTimer = null;
        if (!pendingUpdate || updateApplying || updateStopped) return;
        if (env.document.visibilityState === 'hidden') return;
        const focused = env.document.activeElement;
        const editing = focused && /^(?:INPUT|TEXTAREA|SELECT)$/.test(focused.tagName);
        if (cycleTask || updateTask || syncTask || refreshTask || assetsTask || editing) { scheduleApply(1000); return; }
        const detail = { automatic: true, revision: pendingUpdate.revision, url: env.location.href };
        if (!env.dispatchEvent(new env.CustomEvent('offline-before-update', { cancelable: true, detail }))) {
            if (updateNote) updateNote.textContent = 'Your offline update is ready and will apply automatically after you finish this form. Your saved work is kept.';
            scheduleApply(1000); return;
        }
        // The page may choose another saved screen after an order is completed.
        // Updates never navigate outside this application's offline documents.
        let target = new URL(env.location.href);
        try {
            const requested = new URL(detail.url, env.location.href);
            if (requested.origin === base.origin && requested.pathname.startsWith(base.pathname + 'offline/') &&
                /^\/[a-z_]+\.html$/.test(requested.pathname.slice((base.pathname + 'offline').length))) target = requested;
        } catch (_) {}
        updateApplying = true;
        if (updateNote) updateNote.textContent = 'Applying the latest offline UI and functions. Your saved work is kept.';
        try {
            if (target.href !== env.location.href) env.location.replace(target.href);
            else env.location.reload();
        } catch (_) { updateApplying = false; scheduleApply(1000); }
    }
    function noteUpdate(result) {
        const offlinePage = env.location.pathname && env.location.pathname.startsWith(base.pathname + 'offline/');
        if (config.headless || !offlinePage || !result || !result.success || result.updated !== true ||
            typeof result.revision !== 'string' || !result.revision || updateApplying) return;
        pendingUpdate = result;
        if (!updateHooks) {
            updateHooks = true;
            env.addEventListener('pagehide', () => {
                updateStopped = true;
                if (updateTimer !== null) env.clearTimeout(updateTimer);
                updateTimer = null;
            });
            env.addEventListener('pageshow', () => { updateStopped = false; scheduleApply(); });
            env.document.addEventListener('visibilitychange', () => { if (env.document.visibilityState !== 'hidden') scheduleApply(); });
        }
        if (updateNote) {
            updateNote.hidden = false;
            updateNote.textContent = 'The latest offline UI and functions are downloaded. The update will apply automatically once syncing and editing are finished.';
        }
        scheduleApply();
    }
    async function refreshAssets() {
        if (assetsTask) return assetsTask;
        assetsTask = (async () => {
            if (!env.navigator || !env.navigator.serviceWorker) throw new Error('Offline access needs a supported browser and a secure connection.');
            let registration;
            try {
                registration = await env.navigator.serviceWorker.register(new URL('sw.js', base).href, { scope: base.href, updateViaCache: 'none' }); await registration.update();
            } catch (_) { throw new Error('Cannot reach the app server to update offline files. Your saved version is kept.'); }
            const worker = await activeWorker(registration);
            const result = await new Promise((resolve, reject) => {
                const channel = new env.MessageChannel();
                const timer = env.setTimeout(() => { channel.port1.close(); reject(new Error('The offline update is still pending. Your saved copy is kept.')); }, 25000);
                channel.port1.onmessage = event => {
                    env.clearTimeout(timer); channel.port1.close();
                    event.data && event.data.success ? resolve(event.data) : reject(new Error((event.data || {}).message || 'Offline files could not be updated.'));
                };
                try { worker.postMessage({ type: 'REFRESH_OFFLINE_ASSETS' }, [channel.port2]); }
                catch (error) { env.clearTimeout(timer); channel.port1.close(); reject(error); }
            });
            noteUpdate(result); return result;
        })();
        try { return await assetsTask; } finally { assetsTask = null; }
    }
    function getSyncState() {
        const id = Number((identity() || {}).id) || null;
        if (syncState.user_id !== id) {
            let lastSyncedAt = null;
            try { lastSyncedAt = env.localStorage.getItem('offlineLastSync:' + base.href + ':' + id); } catch (_) {}
            syncState = { user_id: id, phase: id ? 'idle' : 'auth_required', pending: 0, needsReview: 0, lastSyncedAt,
                lastUpdatedAt: null, retryAt: null, message: id ? '' : 'Sign in to prepare your saved workspace.', automatic: true };
        }
        return Object.assign({}, syncState);
    }
    function renderSyncState() {
        if (!statusNode) return;
        const state = getSyncState();
        const titles = { idle: 'Automatic sync is on', offline: 'Working offline', checking: 'Checking your saved work', syncing: 'Syncing transactions',
            refreshing: 'Getting the latest data', ready: 'Your workspace is up to date', retrying: 'Waiting to reconnect', auth_required: 'Online sign-in needed', error: 'Sync needs attention' };
        titleNode.textContent = titles[state.phase] || titles.idle;
        statusNode.textContent = state.message || (state.phase === 'ready' ? 'Transactions and account data update automatically when connected.' : 'Your saved transactions stay on this device until the server accepts them.');
        const details = [state.pending + ' pending', state.needsReview + ' need review'];
        if (state.lastUpdatedAt) details.push('Data updated ' + new Date(state.lastUpdatedAt).toLocaleString());
        if (state.lastSyncedAt) details.push('Last synced ' + new Date(state.lastSyncedAt).toLocaleTimeString());
        if (state.retryAt) details.push('Retry at ' + new Date(state.retryAt).toLocaleTimeString());
        metaNode.textContent = details.join(' · ');
        statusIndicator.className = 'offline-sync-indicator ' + state.phase;
        const busy = !!cycleTask || !!updateTask;
        syncButton.hidden = !state.user_id || (!state.pending && state.phase !== 'auth_required' && state.phase !== 'error');
        syncButton.disabled = busy;
        statusNode.closest && statusNode.closest('.offline-data-bar').setAttribute('data-sync-phase', state.phase);
    }
    function setSyncState(patch) {
        getSyncState();
        syncState = Object.assign({}, syncState, patch);
        renderSyncState(); emit('offline-sync-state', getSyncState());
    }
    let statusGeneration = 0;
    async function status(message) {
        const generation = ++statusGeneration;
        if (!identity()) { setSyncState({ phase: 'auth_required', pending: 0, needsReview: 0, message: message || 'Sign in to prepare your saved workspace.' }); return; }
        try {
            const id = userId(), results = await Promise.all([store.get(id), store.list(id)]);
            if (generation !== statusGeneration) return;
            sameAccount(id);
            const patch = { pending: results[1].filter(row => row.status === 'pending').length,
                needsReview: results[1].filter(row => row.status === 'needs_review').length };
            if (results[0]) patch.lastUpdatedAt = results[0].saved_at;
            if (message) patch.message = message;
            setSyncState(patch);
        } catch (error) { setSyncState({ message: error.message }); }
    }
    function cancelled() { const error = new Error('Sync paused. Your pending transactions are kept.'); error.code = 'CANCELLED'; return error; }
    function transient(error) { return error.code !== 'CANCELLED' && error.code !== 'INVALID_SNAPSHOT' &&
        (error.httpStatus === 0 || error.httpStatus >= 500 || [408, 429].includes(error.httpStatus)); }
    async function syncNow(options = {}) {
        if (options.manual !== false && watcher) watcher.reset();
        if (cycleTask) return cycleTask;
        const epoch = workEpoch;
        cycleTask = (async () => {
            if (env.navigator && env.navigator.onLine === false) {
                await status(); setSyncState({ phase: 'offline', retryAt: null, message: 'Your work is saved. Transactions will sync automatically when the connection returns.' });
                return { offline: true, synced: 0 };
            }
            let id;
            try { id = userId(); } catch (error) {
                error.httpStatus = 401;
                setSyncState({ phase: 'auth_required', retryAt: null, message: error.message });
                throw error;
            }
            const canContinue = () => epoch === workEpoch && Number((identity() || {}).id) === id &&
                (!options.shouldContinue || options.shouldContinue());
            const check = () => { if (!canContinue()) throw cancelled(); sameAccount(id); };
            let files = null, filesError = null;
            // File updates run beside transactions. A failed or slow file download
            // never blocks queued transactions or replaces a complete saved bundle.
            if (options.assets !== false && (options.assets === true || options.manual !== false || Date.now() - lastAssetsAttempt >= 300000)) {
                lastAssetsAttempt = Date.now();
                refreshAssets().then(result => { files = result; }, error => { filesError = error.message; });
            }
            const before = (await store.list(id)).filter(row => row.status === 'pending').length;
            check(); await status();
            setSyncState({ phase: 'checking', retryAt: null, message: 'Connection available. Checking pending transactions and the latest account data.' });
            try {
                await refresh(); check();
                const pending = (await store.list(id)).filter(row => row.status === 'pending').length;
                check();
                let synced = 0;
                if (pending) {
                    setSyncState({ phase: 'syncing', pending, message: 'Sending ' + pending + ' saved transaction(s). Each reference is protected against duplicates.' });
                    synced = await sync({ shouldContinue: canContinue });
                    check();
                    setSyncState({ phase: 'refreshing', message: 'Transactions checked. Updating products, stock and transaction history.' });
                    // A data request started before the POST must not become the
                    // final copy after it. Drain it, then request a fresh snapshot.
                    if (refreshTask) await refreshTask.catch(() => {});
                    check(); await refresh(); check();
                }
                await status(); check();
                const state = getSyncState();
                if (synced || (before > state.pending && state.pending === 0 && !state.needsReview)) {
                    const lastSyncedAt = new Date().toISOString();
                    try { env.localStorage.setItem('offlineLastSync:' + base.href + ':' + id, lastSyncedAt); } catch (_) {}
                    setSyncState({ lastSyncedAt });
                }
                setSyncState({ phase: 'ready', retryAt: null, message: state.pending
                    ? 'Your latest data is saved. ' + state.pending + ' new transaction(s) are waiting for the next automatic sync.'
                    : state.needsReview
                        ? 'Your latest data is saved. ' + state.needsReview + ' transaction(s) need review in History; the rest can sync automatically.'
                        : 'All available transactions are synced and your latest data is saved.' });
                return { synced, data: await getSnapshot(), files, filesError };
            } catch (error) {
                if (error.code !== 'CANCELLED') {
                    await status();
                    setSyncState({ phase: [401, 403, 419].includes(error.httpStatus) ? 'auth_required' : transient(error) ? 'retrying' : 'error',
                        message: error.message, retryAt: null });
                }
                throw error;
            }
        })();
        try { return await cycleTask; } finally { cycleTask = null; renderSyncState(); }
    }
    async function updateAll() {
        if (updateTask) return updateTask;
        updateTask = (async () => {
            if (env.navigator && env.navigator.onLine === false) { await status('Offline · Using your saved data and functions. Updates resume when connected.'); return { offline: true }; }
            try {
                await status('Updating offline functions and saved data…');
                const [data, files] = await Promise.allSettled([identity() ? refresh() : Promise.resolve(null), refreshAssets()]);
                const result = { data: data.status === 'fulfilled' ? data.value : null, files: files.status === 'fulfilled' ? files.value : null,
                    dataError: data.status === 'rejected' ? data.reason.message : null, filesError: files.status === 'rejected' ? files.reason.message : null };
                const errors = [result.dataError, result.filesError].filter(Boolean); await status(errors.length ? errors.join(' ') : null); return result;
            } finally { renderSyncState(); }
        })();
        try { return await updateTask; } finally { updateTask = null; renderSyncState(); scheduleApply(); }
    }
    function startUpdates() {
        if (watcher) return watcher;
        let stopped = false, blocked = false, retry = null, running = null, again = false, failures = 0, lastCheck = 0, probing = false, reachable = null;
        const startingAccount = Number((identity() || {}).id) || null;
        const clearRetry = () => { if (retry !== null) env.clearTimeout(retry); retry = null; };
        function schedule(delay) {
            clearRetry();
            if (stopped || blocked || env.navigator.onLine === false) return;
            setSyncState({ retryAt: new Date(Date.now() + delay).toISOString() });
            retry = env.setTimeout(() => { retry = null; return trigger(); }, delay);
        }
        function trigger(options = {}) {
            if (options.manual) { blocked = false; failures = 0; clearRetry(); }
            if (stopped || blocked) return Promise.resolve({ paused: true });
            if ((Number((identity() || {}).id) || null) !== startingAccount) {
                blocked = true; clearRetry();
                setSyncState({ phase: 'auth_required', retryAt: null, message: 'The account changed. Open the current account workspace before syncing.' });
                return Promise.resolve({ paused: true });
            }
            if (!startingAccount) return updateAll();
            if (env.navigator.onLine === false) {
                clearRetry(); setSyncState({ phase: 'offline', retryAt: null, message: 'Your saved work will sync automatically when connected.' });
                return Promise.resolve({ offline: true });
            }
            if (running) { if (options.queued) again = true; return running; }
            // A manual cycle can own cycleTask while this watcher is idle.
            // Join it, then drain work saved after that cycle read its queue.
            if (options.queued && cycleTask) again = true;
            clearRetry(); lastCheck = Date.now();
            running = syncNow({ manual: false, assets: options.assets, shouldContinue: () => !stopped }).then(result => {
                failures = 0; reachable = true; return result;
            }).catch(error => {
                if (stopped) return { error };
                if (error.code === 'CANCELLED') { if (Number((identity() || {}).id) === startingAccount) schedule(1000); }
                else if (transient(error)) { reachable = false; schedule(Math.min(60000, 5000 * Math.pow(2, Math.min(failures++, 4)))); }
                else { blocked = true; clearRetry(); }
                return { error };
            }).finally(() => {
                running = null;
                if (again && retry === null && !stopped && !blocked) { again = false; trigger(); } else again = false;
            });
            return running;
        }
        const restored = () => {
            // Restore public functions even if the data session needs a new sign-in
            // or an earlier sync is still waiting for a network request to settle.
            if (!stopped && env.navigator.onLine !== false) {
                lastAssetsAttempt = Date.now(); refreshAssets().catch(() => {});
            }
            return trigger({ assets: true });
        };
        const queued = () => trigger({ queued: true });
        const visible = () => { if (env.document.visibilityState !== 'hidden' && Date.now() - lastCheck >= 60000) return trigger(); };
        const offline = () => { clearRetry(); reachable = false; setSyncState({ phase: 'offline', retryAt: null, message: 'Working offline. Your saved transactions will sync when the connection returns.' }); };
        const authenticated = () => trigger({ manual: true });
        const storage = event => { if (event.key === 'offlineUser' || event.key === 'current_user_id') authenticated(); };
        const handlers = [['online', restored], ['offline', offline], ['offline-connection-restored', restored], ['offline-order-queued', queued],
            ['focus', visible], ['storage', storage], ['offline-authenticated', authenticated]];
        handlers.forEach(([name, handler]) => env.addEventListener(name, handler));
        env.document.addEventListener('visibilitychange', visible);
        async function probe() {
            if (stopped || probing || running || env.navigator.onLine === false) return;
            probing = true;
            const controller = new env.AbortController(), timeout = env.setTimeout(() => controller.abort(), 3000);
            try {
                const response = await env.fetch(new URL('manifest.json', base).href, { method: 'HEAD', cache: 'no-store', credentials: 'same-origin', signal: controller.signal });
                if (stopped) return;
                const wasReachable = reachable; reachable = response.ok;
                if (reachable && wasReachable === false) restored();
                if (!reachable && wasReachable !== false && retry === null) trigger();
            } catch (_) { if (!stopped && reachable !== false && retry === null) { reachable = false; trigger(); } }
            finally { env.clearTimeout(timeout); probing = false; }
        }
        const periodic = env.setInterval(visible, 300000), health = env.setInterval(probe, 15000);
        const stop = () => { if (watcher) watcher.stop(); };
        watcher = { trigger, reset() { blocked = false; failures = 0; clearRetry(); }, stop() {
            stopped = true; workEpoch++; clearRetry();
            env.clearInterval(periodic); env.clearInterval(health);
            handlers.forEach(([name, handler]) => env.removeEventListener(name, handler));
            env.document.removeEventListener('visibilitychange', visible); env.removeEventListener('pagehide', stop); watcher = null;
        } };
        env.addEventListener('pagehide', stop); trigger(); return watcher;
    }
    function mount() {
        if (config.user) {
            const user = { id: config.user.id, role: config.user.role, name: config.user.name, email: config.user.email, loginTime: new Date().toISOString() };
            try { env.localStorage.setItem('offlineUser', JSON.stringify(user)); ['id', 'role', 'name', 'email'].forEach(key => env.localStorage.setItem('current_user_' + key, user[key] || '')); } catch (_) {}
        }
        if (config.headless) return;
        const bar = env.document.createElement('aside'); bar.className = 'offline-data-bar'; bar.setAttribute('aria-label', 'Automatic synchronization');
        statusIndicator = env.document.createElement('span'); statusIndicator.className = 'offline-sync-indicator'; statusIndicator.setAttribute('aria-hidden', 'true');
        const summary = env.document.createElement('div'); summary.className = 'offline-sync-summary'; summary.setAttribute('role', 'status'); summary.setAttribute('aria-live', 'polite');
        titleNode = env.document.createElement('strong'); titleNode.className = 'offline-sync-title';
        statusNode = env.document.createElement('span'); statusNode.className = 'offline-sync-description';
        metaNode = env.document.createElement('span'); metaNode.className = 'offline-sync-meta'; summary.append(titleNode, statusNode, metaNode);
        const actions = env.document.createElement('div'); actions.className = 'offline-sync-actions';
        syncButton = env.document.createElement('button'); syncButton.type = 'button'; syncButton.textContent = 'Sync now'; syncButton.hidden = true;
        updateNote = env.document.createElement('span'); updateNote.className = 'offline-update-note'; updateNote.hidden = true;
        const signIn = env.document.createElement('a'); signIn.href = new URL('login', base).href; signIn.textContent = 'Sign in online'; signIn.hidden = true;
        actions.append(syncButton, signIn); bar.append(statusIndicator, summary, actions, updateNote); env.document.body.appendChild(bar);
        syncButton.onclick = async () => {
            syncButton.disabled = true;
            try { await syncNow(); }
            catch (error) { await status(error.message); } finally { syncButton.disabled = false; }
        };
        env.addEventListener('offline-sync-state', event => { signIn.hidden = !event.detail || event.detail.phase !== 'auth_required'; });
        if (env.navigator.serviceWorker) env.navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'OFFLINE_ASSETS_UPDATED') noteUpdate(event.data);
        });
        env.addEventListener('offline-outbox-updated', () => status()); env.addEventListener('pageshow', () => startUpdates()); status(); startUpdates();
    }
    if (!adapter && env.document) {
        if (env.document.readyState === 'loading') env.document.addEventListener('DOMContentLoaded', mount, { once: true }); else mount();
    }
    return { identity, getSnapshot, validateSnapshot, validateOrder, refresh, refreshAssets, updateAll, startUpdates, syncNow, getSyncState, queueOrder, listOrders, sync, reviewOrder };
});
