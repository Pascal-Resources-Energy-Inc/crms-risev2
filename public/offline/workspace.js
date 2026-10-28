(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else { root.OfflineWorkspace = api; const start = () => api.mount(root); if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', start, { once: true }); else start(); }
})(typeof window !== 'undefined' ? window : this, function () {
    'use strict';
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value) || 0);
    const timestamp = value => { const parsed = Date.parse(value || ''); return Number.isFinite(parsed) ? parsed : 0; };
    function roleLabel(role) {
        const normalized = String(role || '').toLowerCase().replace(/[\s_-]+/g, '');
        return ({ dealer: 'Dealer', megadealer: 'Mega Dealer', centerchief: 'Center Chief', client: 'Client', customer: 'Client' })[normalized] || 'Account';
    }
    function serverRecords(snapshot, row) {
        if (row.status !== 'synced') return [];
        const saved = snapshot && (row.type === 'order' ? snapshot.orders : snapshot.transactions);
        const history = Array.isArray(saved) ? saved : [];
        const receipt = snapshot && snapshot.acknowledged_order_results && snapshot.acknowledged_order_results[row.idempotency_key];
        const local = row.result && Array.isArray(row.result.records) ? row.result.records : [];
        const records = Array.isArray(receipt) && receipt.length ? receipt : local;
        const resolved = records.filter(record => record && typeof record === 'object' &&
            (record.id !== null && record.id !== undefined && record.id !== '' || record.transaction_id)).map(record => {
            const current = history.find(entry => (record.id !== null && record.id !== undefined && record.id !== '' && String(entry.id) === String(record.id)) ||
                (record.transaction_id && entry.transaction_id === record.transaction_id));
            return current ? Object.assign({}, record, current, { transaction_id: current.transaction_id || record.transaction_id }) : record;
        });
        // Older server references embedded the retry key. Retain those links
        // without trying to infer an ID from product, price or creation time.
        if (!resolved.length && row.idempotency_key) return history.filter(record => record.transaction_id && String(record.transaction_id).includes(row.idempotency_key));
        return resolved;
    }
    function historyEntries(snapshot, queue) {
        const copy = Array.isArray(queue) ? queue : [], covered = new Set(), references = new Set();
        copy.filter(row => row.status === 'synced').forEach(row => {
            serverRecords(snapshot, row).forEach(record => {
                if (record.id !== null && record.id !== undefined) covered.add(row.type + ':' + record.id);
                if (record.transaction_id) references.add(row.type + ':' + record.transaction_id);
            });
        });
        const entries = copy.map(row => ({ ...row, source: 'device', sort: timestamp(row.created_at), key: 'local:' + row.idempotency_key }));
        if (snapshot) ['client', 'order'].forEach(type => {
            const rows = type === 'client' ? snapshot.transactions : snapshot.orders;
            (Array.isArray(rows) ? rows : []).forEach(row => {
                if (covered.has(type + ':' + row.id) || row.transaction_id && references.has(type + ':' + row.transaction_id)) return;
                entries.push({ ...row, source: 'server', type, sort: timestamp(row.created_at || row.date), key: type + ':' + row.id });
            });
        });
        return entries.sort((a, b) => b.sort - a.sort || a.key.localeCompare(b.key));
    }
    function mount(win) {
        const doc = win.document, api = win.OfflineData, byId = id => doc.getElementById(id);
        const page = doc.body.getAttribute('data-workspace');
        const node = (tag, className, text) => { const element = doc.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; };
        const notice = message => { byId('workspaceNotice').textContent = message || ''; byId('workspaceNotice').hidden = !message; };
        let saved = null, queue = [], generation = 0, busy = false, syncState = null;
        const viewer = api && api.identity();
        const syncButton = byId('workspaceSync'), refreshButton = byId('workspaceRefresh');
        if (!api || !viewer) {
            notice(api ? 'Sign in online to prepare your account for offline use.' : 'Offline account files are not ready. Connect and update the offline version.');
            byId('workspaceSavedAt').textContent = 'No saved account data is available.'; syncButton.disabled = refreshButton.disabled = true; return;
        }
        function savedAt() { byId('workspaceSavedAt').textContent = saved ? 'Data saved ' + new Date(saved.saved_at).toLocaleString() : 'No saved account update yet. Connect to download the latest data automatically.'; }
        function automaticBusy() { return syncState && ['checking', 'syncing', 'refreshing'].includes(syncState.phase); }
        function connection() {
            const fallback = win.navigator && win.navigator.onLine === false ? 'offline' : 'ready';
            const phase = syncState && syncState.phase || fallback;
            const phases = {
                idle: ['Auto sync on', 'Automatic sync is ready', 'Pending transactions sync and saved data refresh automatically when your connection returns.'],
                offline: ['Offline · Saved mode', 'Your work is safe on this device', 'Pending transactions sync and saved data refresh automatically when your connection returns.'],
                checking: ['Checking connection', 'Checking your connection', 'Your saved catalog and transactions are available while we reconnect.'],
                syncing: ['Syncing transactions', 'Sending your pending transactions', 'Keep working. Transactions stay saved until the server accepts them.'],
                refreshing: ['Updating saved data', 'Downloading your latest data', 'Products, customers, profile and order history are being refreshed.'],
                ready: ['Connected · Auto sync on', 'Your workspace is up to date', 'Pending transactions sync automatically. New data is downloaded when you reconnect.'],
                retrying: ['Waiting to reconnect', 'We will retry automatically', 'Your pending transactions are kept safely on this device.'],
                auth_required: ['Sign in to sync', 'Online sign-in is needed', 'Your pending transactions are kept. Sign in online to resume automatic sync.'],
                error: ['Sync needs attention', 'Your saved work is kept', 'Use Sync now to retry. Orders that need review remain in transaction history.']
            };
            const copy = phases[phase] || phases.ready;
            const badge = byId('workspaceConnection'), label = byId('workspaceSyncLabel'), detail = byId('workspaceSyncDetail'), count = byId('workspaceQueue');
            if (badge) { badge.textContent = copy[0]; badge.className = 'saved-mode state-' + (phases[phase] ? phase : 'ready'); }
            if (label) label.textContent = copy[1];
            if (detail) detail.textContent = syncState && syncState.message || copy[2];
            const pending = syncState && Number.isFinite(Number(syncState.pending)) ? Number(syncState.pending) : queue.filter(row => row.status === 'pending').length;
            const reviews = syncState && Number.isFinite(Number(syncState.needsReview)) ? Number(syncState.needsReview) : queue.filter(row => row.status === 'needs_review').length;
            if (count) count.textContent = pending + ' pending sync' + (reviews ? ' · ' + reviews + ' need review' : '');
        }
        function profile() {
            const holder = byId('profileDetails'); if (!holder) return;
            const info = saved && saved.profile || {}, user = saved && saved.user || viewer;
            holder.replaceChildren();
            const fields = [
                ['Account', roleLabel(user.role)], ['Reference', info.chief_reference || info.dealer_reference || info.partner_code || info.id || user.id],
                ['Name', info.name || user.name], ['Email', user.email], ['Contact', info.number],
                ['Sales territory', info.sales_territory || info.area], ['MFI', info.mfi],
                ['Address', info.address || info.street_address], ['Registered', info.created_at ? new Date(info.created_at).toLocaleDateString() : null]
            ];
            fields.forEach(([label, value]) => {
                if (value === null || value === undefined || value === '') return;
                const row = node('div'); row.append(node('dt', '', label), node('dd', '', String(value))); holder.append(row);
            });
            if (!saved) holder.append(node('p', 'muted', 'Profile details will appear after your first saved update.'));
        }
        function statusLabel(row) {
            if (row.source === 'device') {
                if (row.status === 'needs_review') return 'Needs review';
                if (row.status === 'synced') {
                    const states = [...new Set(serverRecords(saved, row).map(record => record.status).filter(Boolean))];
                    if (states.length === 1) return 'Synced · ' + (row.type === 'order' && String(states[0]).toLowerCase() === 'pending' ? 'Pending fulfilment' : states[0]);
                    return row.type === 'order' ? 'Synced · Check fulfilment online' : 'Synced';
                }
                return 'Pending sync';
            }
            return row.type === 'order' && String(row.status).toLowerCase() === 'pending' ? 'Pending fulfilment' : row.status || 'Saved';
        }
        function recipient(row) {
            if (row.recipient_name) return row.recipient_name;
            if (row.customer_name) return row.customer_name;
            const recipients = saved ? row.type === 'order' ? saved.distributors : saved.customers : [];
            const target = (recipients || []).find(item => String(item.id) === String(row.type === 'order' ? row.ad_id : row.client_id));
            return target ? target.display_name || target.name : row.type === 'order' ? 'Area Distributor' : 'Customer';
        }
        function transactionReference(row) {
            if (row.source !== 'device') return row.transaction_id ? 'Transaction ID: ' + row.transaction_id : 'Transaction ID is awaiting a server update.';
            const references = serverRecords(saved, row).map(record => record.transaction_id).filter(Boolean);
            if (references.length) return 'Transaction ID: ' + [...new Set(references)].join(', ');
            if (row.status === 'synced') return 'Synced · Transaction ID is awaiting a server update.';
            return 'Device reference: ' + row.idempotency_key + ' · Transaction ID is assigned when synced.';
        }
        async function review(row, button) {
            button.disabled = true;
            try {
                let result;
                try { result = await api.reviewOrder(row.idempotency_key); }
                catch (error) {
                    if (error.code !== 'CART_REPLACEMENT_REQUIRED') throw error;
                    if (!win.confirm('Reviewing this saved order replaces your current unsent cart. Pending saved transactions are kept. Continue?')) return;
                    result = await api.reviewOrder(row.idempotency_key, { replaceCart: true });
                }
                if (!result || !['cart.html', 'products.html'].includes(result.url)) throw new Error('The saved order could not be prepared. Refresh and try again.');
                win.location.href = result.url;
            } catch (error) { notice(error.message || 'Unable to prepare this order for review. Your saved order is kept.'); }
            finally { button.disabled = false; }
        }
        function activity() {
            const holder = byId('activityList'); if (!holder) return;
            holder.replaceChildren();
            const all = historyEntries(saved, queue), filter = byId('historyFilter') ? byId('historyFilter').value : 'all';
            let rows = all.filter(row => {
                if (filter === 'pending' || filter === 'needs_review') return row.source === 'device' && row.status === filter;
                return filter === 'all' || row.type === filter;
            });
            if (page === 'home') rows = rows.slice(0, 5);
            const summary = byId('historySummary');
            if (summary) summary.textContent = rows.length + ' saved activity record(s) shown. ' + queue.filter(row => row.status === 'pending').length + ' pending sync; ' + queue.filter(row => row.status === 'needs_review').length + ' need review.';
            rows.forEach(row => {
                const card = node('article', 'entry'), header = node('div', 'entry-header');
                const statusClass = row.source === 'device' ? row.status : String(row.status).toLowerCase() === 'pending' ? 'pending' : '';
                header.append(node('h3', '', (row.type === 'order' ? 'AD Order · ' : 'Client Transaction · ') + recipient(row)), node('span', 'status ' + statusClass, statusLabel(row)));
                card.append(header, node('p', 'muted', transactionReference(row)));
                if (row.sort) card.append(node('p', 'muted', new Date(row.sort).toLocaleString()));
                const items = row.source === 'device' ? row.items || [] : [{ name: row.item, quantity: row.qty, unit_price: row.price }];
                const list = node('ul'); let computed = 0;
                items.forEach(item => {
                    const quantity = Number(item.quantity || item.qty) || 0, price = Number(item.unit_price ?? item.price) || 0; computed += quantity * price;
                    list.append(node('li', '', (item.name || item.item || 'Saved product') + ' × ' + quantity + ' · ' + money(price * quantity)));
                });
                card.append(list, node('p', 'amount', money(row.total_amount === null || row.total_amount === undefined ? computed : row.total_amount)));
                if (row.last_error) card.append(node('p', '', row.last_error));
                if (row.source === 'device' && row.status === 'needs_review') {
                    const button = node('button', 'button', 'Review saved order'); button.type = 'button'; button.onclick = () => review(row, button); card.append(button);
                }
                holder.append(card);
            });
            if (!rows.length) holder.append(node('p', 'empty', filter === 'all' ? 'No saved transactions yet. New offline orders will appear here after saving.' : 'No saved transactions match this filter.'));
        }
        function render() {
            savedAt(); profile(); activity(); connection();
            syncButton.disabled = busy || automaticBusy() || !queue.some(row => row.status === 'pending'); refreshButton.disabled = busy || automaticBusy();
            if (page === 'home') {
                byId('workspaceTitle').textContent = 'Hello ' + (saved && saved.user.name || viewer.name || 'there') + '!';
                byId('pointsBalance').textContent = saved && saved.points !== null && saved.points !== '' && Number.isFinite(Number(saved.points)) ? Number(saved.points).toLocaleString(undefined, { maximumFractionDigits: 2 }) : 'Refresh to view';
                byId('pendingCount').textContent = String(queue.filter(row => row.status !== 'synced').length);
                byId('productCount').textContent = String(saved && Array.isArray(saved.products) ? saved.products.length : 0);
            }
            if (page === 'account') byId('profileSavedAt').textContent = saved ? 'Profile saved ' + new Date(saved.saved_at).toLocaleString() : 'Connect and update to save your profile.';
        }
        async function reload() {
            const current = ++generation;
            try {
                const results = await Promise.allSettled([api.getSnapshot(), api.listOrders()]); if (current !== generation) return;
                const candidate = results[0].status === 'fulfilled' ? results[0].value : saved;
                if (candidate && Number(candidate.user.id) !== Number(viewer.id)) throw new Error('The saved account changed. Sign in again before viewing this workspace.');
                saved = candidate;
                if (results[1].status === 'fulfilled') queue = results[1].value;
                queue = queue.filter(row => Number(row.user_id) === Number(viewer.id));
                render();
                const failure = results.find(result => result.status === 'rejected');
                if (failure) notice(failure.reason.message || 'Some saved data is unavailable. Your previous view and pending work are kept.');
                else if (!saved) notice('No account data has been saved yet. Connect and sign in online to download the latest data automatically.');
            } catch (error) { notice(error.message || 'Unable to read saved data. Your pending transactions are kept.'); }
        }
        refreshButton.onclick = async () => {
            if (busy || automaticBusy()) return; busy = true; render(); notice('Updating saved app files and account data…');
            try {
                const result = await api.updateAll();
                if (result && result.offline) notice('You are offline. Your saved data and pending transactions are kept. Connect to receive updates.');
                else if (result && (result.dataError || result.filesError)) notice([result.dataError, result.filesError].filter(Boolean).join(' '));
                else notice('Latest available account data has been saved on this device.');
                await reload();
            }
            catch (error) { notice(error.message); }
            finally { busy = false; render(); }
        };
        syncButton.onclick = async () => {
            if (busy || automaticBusy()) return; busy = true; render(); notice('Submitting pending transactions…');
            try {
                let result;
                if (typeof api.syncNow === 'function') result = await api.syncNow({ manual: true });
                else {
                    const count = await api.sync();
                    try { await api.refresh(); } catch (error) { await reload(); notice(count + ' transaction(s) synced. ' + error.message); return; }
                    result = { synced: count };
                }
                await reload();
                if (result && result.offline) notice('You are offline. Your pending transactions are kept and will sync automatically when your connection returns.');
                else notice((Number(result && result.synced) || 0) + ' transaction(s) synced. Orders that need review remain saved below.' + (result && result.filesError ? ' ' + result.filesError : ''));
            } catch (error) { await reload(); notice(error.message || 'Sync failed. Your pending transactions are kept.'); }
            finally { busy = false; render(); }
        };
        if (byId('historyFilter')) byId('historyFilter').onchange = activity;
        if (byId('onlineProfile')) byId('onlineProfile').href = typeof win.getOfflineAppUrl === 'function' ? win.getOfflineAppUrl('account') : new URL('../account', win.location.href).href;
        if (byId('logoutButton')) byId('logoutButton').onclick = () => { if (typeof win.logoutOfflineUser === 'function') win.logoutOfflineUser(); else notice('Open the online account page to sign out.'); };
        function acceptSyncState(state) {
            if (!state || state.user_id !== null && state.user_id !== undefined && Number(state.user_id) !== Number(viewer.id)) return;
            syncState = state; render();
        }
        if (typeof api.getSyncState === 'function') acceptSyncState(api.getSyncState());
        win.addEventListener('offline-sync-state', event => acceptSyncState(event.detail));
        win.addEventListener('offline-data-updated', reload); win.addEventListener('offline-outbox-updated', reload); reload();
        return { reload };
    }
    return { mount, historyEntries, roleLabel };
});
