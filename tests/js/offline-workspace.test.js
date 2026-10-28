const assert = require('node:assert/strict');
const test = require('node:test');
const workspace = require('../../public/offline/workspace.js');
const { harness, snapshot, settle, context, item } = require('./offline-ui-helpers');

function setup(page = 'history', initialSnapshot = snapshot(), rows = []) {
    const ids = 'workspaceNotice workspaceSavedAt workspaceTitle workspaceRefresh workspaceSync workspaceConnection workspaceSyncLabel workspaceSyncDetail workspaceQueue'.split(' ');
    if (page === 'home') ids.push('profileDetails', 'pointsBalance', 'pendingCount', 'productCount', 'activityList');
    if (page === 'account') ids.push('profileDetails', 'onlineProfile', 'logoutButton', 'profileSavedAt');
    if (page === 'history') ids.push('historyFilter', 'historySummary', 'activityList');
    const h = harness(ids, {}, initialSnapshot);
    h.win.document.body = { getAttribute: name => name === 'data-workspace' ? page : null };
    if (h.nodes.has('historyFilter')) h.node('historyFilter').value = 'all';
    let queue = rows;
    h.api.listOrders = async () => structuredClone(queue);
    h.api.updateAll = async () => ({ data: initialSnapshot });
    h.api.sync = async () => { queue = queue.map(row => ({ ...row, status: 'synced' })); return queue.length; };
    h.setQueue = value => { queue = value; };
    return h;
}
const queued = overrides => ({ ...context(), idempotency_key: '00000000-0000-4000-8000-000000000001', recipient_name: 'Assigned AD', created_at: '2026-10-28T01:00:00Z', status: 'pending', items: [item()], ...overrides });

test('workspace follows automatic sync progress without allowing duplicate manual requests', async () => {
    const h = setup('home', snapshot(), [queued()]);
    h.api.getSyncState = () => ({ phase: 'syncing', pending: 1, needsReview: 0, user_id: 42 });
    workspace.mount(h.win); await settle();
    assert.equal(h.node('workspaceConnection').textContent, 'Syncing transactions');
    assert.equal(h.node('workspaceSync').disabled, true);
    assert.equal(h.node('workspaceRefresh').disabled, true);
    await h.event('offline-sync-state', { detail: { phase: 'refreshing', pending: 0, needsReview: 0, user_id: 42 } });
    assert.match(h.node('workspaceSyncLabel').textContent, /Downloading/);
    h.setQueue([]); await h.event('offline-outbox-updated');
    await h.event('offline-sync-state', { detail: { phase: 'ready', pending: 0, needsReview: 0, user_id: 42 } });
    assert.match(h.node('workspaceConnection').textContent, /Connected/);
    assert.equal(h.node('workspaceRefresh').disabled, false);
    assert.equal(h.node('workspaceSync').disabled, true);
});

test('offline and rejected transaction status clearly keeps saved work for automatic retry or review', async () => {
    const h = setup('home', snapshot(), [queued(), queued({ status: 'needs_review' })]);
    h.win.navigator = { onLine: false };
    workspace.mount(h.win); await settle();
    assert.match(h.node('workspaceConnection').textContent, /Offline/);
    assert.match(h.node('workspaceSyncDetail').textContent, /automatically/);
    assert.equal(h.node('workspaceQueue').textContent, '1 pending sync · 1 need review');
    await h.event('offline-sync-state', { detail: { phase: 'auth_required', pending: 1, needsReview: 1, user_id: 42 } });
    assert.match(h.node('workspaceConnection').textContent, /Sign in/);
    assert.match(h.node('workspaceSyncDetail').textContent, /pending transactions are kept/);
});

test('another account cannot overwrite the visible automatic sync state', async () => {
    const h = setup('home'); h.api.getSyncState = () => ({ phase: 'ready', pending: 0, user_id: 42 });
    workspace.mount(h.win); await settle();
    await h.event('offline-sync-state', { detail: { phase: 'auth_required', pending: 99, needsReview: 3, message: 'Other account', user_id: 99 } });
    assert.match(h.node('workspaceConnection').textContent, /Connected/);
    assert.doesNotMatch(h.node('workspaceSyncDetail').textContent, /Other account/);
    assert.equal(h.node('workspaceQueue').textContent, '0 pending sync');
});

test('history keeps unsynced device rows distinct from saved server history and removes acknowledged duplicates', () => {
    const saved = snapshot(); saved.orders = [{ id: 123, transaction_id: 'saved-order', item: 'Refill' }, { id: 124, item: 'Other refill' }];
    const rows = [queued({ status: 'synced', result: { records: [{ id: 123 }] } })];
    const result = workspace.historyEntries(saved, rows);
    assert.equal(result.length, 2);
    assert.equal(result.filter(row => row.source === 'server')[0].id, 124);
});

test('acknowledgement deduplication also works when only the stable offline key is saved', () => {
    const saved = snapshot(), row = queued({ status: 'synced' });
    saved.orders = [{ id: 123, transaction_id: 'PRORD-OFF-' + row.idempotency_key + '-1' }];
    assert.equal(workspace.historyEntries(saved, [row]).length, 1);
});

test('synced AD orders show the fulfilment status from the latest server snapshot', async () => {
    const saved = snapshot(); saved.orders = [{ id: 123, status: 'Completed', item: 'Refill' }];
    const h = setup('history', saved, [queued({ status: 'synced', result: { records: [{ id: 123 }] } })]);
    workspace.mount(h.win); await settle();
    assert.match(h.node('activityList').textContent, /Synced · Completed/);
    assert.equal(h.node('activityList').children.length, 1);
});

test('synced orders show authoritative sequential IDs once without exposing a UUID as the transaction ID', async () => {
    const saved = snapshot(); saved.orders = [{ id: 123, transaction_id: 'PRORD-0123', status: 'Pending', item: 'Refill' },
        { id: 124, transaction_id: 'PRORD-0124', status: 'Pending', item: 'Other refill' }];
    const row = queued({ status: 'synced', result: { acknowledged: true, records: [
        { id: 123, transaction_id: 'PRORD-0123' }, { id: 124, transaction_id: 'PRORD-0124' }
    ] } });
    const h = setup('history', saved, [row]); workspace.mount(h.win); await settle();
    assert.equal(h.node('activityList').children.length, 1);
    assert.match(h.node('activityList').textContent, /Transaction ID: PRORD-0123, PRORD-0124/);
    assert.doesNotMatch(h.node('activityList').textContent, /Device reference|PRORD-OFF-|00000000-0000/);
});

test('old synced rows recover PRORD and TRX references from typed receipt history without duplicates', async () => {
    const saved = snapshot(), order = queued({ status: 'synced' });
    const sale = queued({ type: 'client', status: 'synced', idempotency_key: '00000000-0000-4000-8000-000000000002', client_id: 100 });
    saved.orders = [{ id: 123, transaction_id: 'PRORD-0123', status: 'Pending', item: 'Refill' }];
    saved.transactions = [{ id: 123, transaction_id: 'TRX-1793151000-4567', status: 'Completed', item: 'Refill' }];
    saved.acknowledged_order_results = { [order.idempotency_key]: [{ id: 123 }], [sale.idempotency_key]: [{ id: 123 }] };
    const h = setup('history', saved, [order, sale]); workspace.mount(h.win); await settle();
    assert.equal(h.node('activityList').children.length, 2);
    assert.match(h.node('activityList').textContent, /Transaction ID: PRORD-0123/);
    assert.match(h.node('activityList').textContent, /Transaction ID: TRX-1793151000-4567/);
    assert.match(h.node('activityList').textContent, /Synced · Pending fulfilment/);
    assert.match(h.node('activityList').textContent, /Synced · Completed/);
    assert.doesNotMatch(h.node('activityList').textContent, /Device reference|00000000-0000/);
});

test('a client acknowledgement immediately replaces its device reference before server history refreshes', async () => {
    const row = queued({ type: 'client', client_id: 100 }), h = setup('history', snapshot(), [row]);
    workspace.mount(h.win); await settle(); assert.match(h.node('activityList').textContent, /Device reference/);
    h.setQueue([{ ...row, status: 'synced', result: { records: [{ id: 50, transaction_id: 'TRX-1793151001-2345', status: 'Completed' }] } }]);
    await h.event('offline-outbox-updated');
    assert.equal(h.node('activityList').children.length, 1);
    assert.match(h.node('activityList').textContent, /Transaction ID: TRX-1793151001-2345/);
    assert.match(h.node('activityList').textContent, /Synced · Completed/);
    assert.doesNotMatch(h.node('activityList').textContent, /Device reference/);
});

test('saved receipt references remain visible when history is reopened while offline', async () => {
    const saved = snapshot(), row = queued({ type: 'client', status: 'synced', client_id: 100 });
    saved.transactions = [];
    saved.acknowledged_order_results = { [row.idempotency_key]: [{ id: 50, transaction_id: 'TRX-1793151002-6789', status: 'Completed' }] };
    for (let opening = 0; opening < 2; opening++) {
        const h = setup('history', structuredClone(saved), [structuredClone(row)]); h.win.navigator = { onLine: false };
        h.api.refresh = h.api.sync = h.api.updateAll = async () => { throw new Error('Offline history must use saved data'); };
        workspace.mount(h.win); await settle();
        assert.match(h.node('activityList').textContent, /Transaction ID: TRX-1793151002-6789/);
        assert.equal(h.node('activityList').children.length, 1);
    }
});

test('server transaction references deduplicate records without a numeric acknowledgement ID', () => {
    const saved = snapshot(); saved.orders = [{ id: 123, transaction_id: 'PRORD-0123' }];
    saved.transactions = [{ id: 123, transaction_id: 'TRX-1793151003-1234' }];
    const rows = [queued({ status: 'synced', result: { records: [{ transaction_id: 'PRORD-0123' }] } })];
    const entries = workspace.historyEntries(saved, rows);
    assert.equal(entries.length, 2);
    assert.equal(entries.filter(row => row.source === 'server')[0].type, 'client');
});

test('the latest server reference takes precedence over a stale acknowledgement for the same record', async () => {
    const saved = snapshot(); saved.orders = [{ id: 123, transaction_id: 'PRORD-0123', status: 'Pending' }];
    const row = queued({ status: 'synced', result: { records: [{ id: 123, transaction_id: 'STALE-REFERENCE', status: 'Completed' }] } });
    const h = setup('history', saved, [row]); workspace.mount(h.win); await settle();
    assert.match(h.node('activityList').textContent, /Transaction ID: PRORD-0123/);
    assert.doesNotMatch(h.node('activityList').textContent, /STALE-REFERENCE/);
    assert.match(h.node('activityList').textContent, /Synced · Pending fulfilment/);
});

test('an incomplete history row does not erase the actual client reference saved in its receipt', async () => {
    for (const reference of [null, '']) {
        const saved = snapshot(), row = queued({ type: 'client', status: 'synced', client_id: 100 });
        saved.transactions = [{ id: 50, transaction_id: reference, status: 'Completed', item: 'Refill' }];
        saved.acknowledged_order_results = { [row.idempotency_key]: [{ id: 50, transaction_id: 'TRX-1793151004-5678', status: 'Completed' }] };
        const h = setup('history', saved, [row]); workspace.mount(h.win); await settle();
        assert.equal(h.node('activityList').children.length, 1);
        assert.match(h.node('activityList').textContent, /Transaction ID: TRX-1793151004-5678/);
        assert.doesNotMatch(h.node('activityList').textContent, /Device reference|awaiting a server update/);
    }
});

test('a synced row without an authoritative reference waits for a server update without presenting its UUID as an ID', async () => {
    const h = setup('history', snapshot(), [queued({ status: 'synced' })]); workspace.mount(h.win); await settle();
    assert.match(h.node('activityList').textContent, /Transaction ID is awaiting a server update/);
    assert.doesNotMatch(h.node('activityList').textContent, /Device reference|00000000-0000/);
});

test('home recovers when the first account snapshot arrives and shows server points including zero', async () => {
    const h = setup('home', null, [queued()]); workspace.mount(h.win); await settle();
    assert.equal(h.node('pointsBalance').textContent, 'Refresh to view');
    const saved = snapshot(); saved.points = 0; saved.profile = { partner_code: 'PRMD00001', name: 'Saved Mega Dealer', sales_territory: 'Example territory' };
    h.setSnapshot(saved); await h.event('offline-data-updated');
    assert.equal(h.node('pointsBalance').textContent, '0');
    assert.equal(h.node('pendingCount').textContent, '1');
    assert.match(h.node('profileDetails').textContent, /Saved Mega Dealer/);
    assert.match(h.node('profileDetails').textContent, /PRMD00001/);
    assert.equal(h.node('productCount').textContent, '2');
});

test('history filters pending sync and rejected rows without mixing other accounts', async () => {
    const h = setup('history', snapshot(), [queued(), queued({ idempotency_key: 'review-key', status: 'needs_review', last_error: 'Price changed' }), queued({ idempotency_key: 'foreign-key', user_id: 99 })]);
    workspace.mount(h.win); await settle();
    assert.match(h.node('historySummary').textContent, /2 saved activity/);
    assert.doesNotMatch(h.node('activityList').textContent, /foreign-key/);
    h.node('historyFilter').value = 'needs_review'; h.node('historyFilter').onchange();
    assert.match(h.node('activityList').textContent, /Price changed/);
    assert.equal(h.node('activityList').children.length, 1);
});

test('review asks before replacing an unsent cart and uses the same rejected order reference', async () => {
    const h = setup('history', snapshot(), [queued({ status: 'needs_review' })]);
    const calls = [];
    h.api.reviewOrder = async (key, options) => {
        calls.push({ key, options });
        if (!options) { const error = new Error('Replace cart?'); error.code = 'CART_REPLACEMENT_REQUIRED'; throw error; }
        return { url: 'cart.html' };
    };
    workspace.mount(h.win); await settle();
    const button = h.node('activityList').children[0].children.at(-1);
    await button.onclick();
    assert.equal(calls.length, 2); assert.equal(calls[1].options.replaceCart, true);
    assert.equal(calls[0].key, calls[1].key); assert.equal(h.win.location.href, 'cart.html');
});

test('cancelled review leaves the current cart and rejected transaction untouched', async () => {
    const h = setup('history', snapshot(), [queued({ status: 'needs_review' })]);
    h.win.confirm = () => false; let calls = 0;
    h.api.reviewOrder = async () => { calls++; const error = new Error('Replace?'); error.code = 'CART_REPLACEMENT_REQUIRED'; throw error; };
    workspace.mount(h.win); await settle(); await h.node('activityList').children[0].children.at(-1).onclick();
    assert.equal(calls, 1); assert.notEqual(h.win.location.href, 'cart.html');
});

test('failed refresh retains the saved profile and does not report new data saved', async () => {
    const saved = snapshot(); saved.profile = { name: 'Kept profile' };
    const h = setup('account', saved); workspace.mount(h.win); await settle();
    h.api.updateAll = async () => ({ dataError: 'Session expired', files: { success: true } });
    await h.node('workspaceRefresh').onclick();
    assert.match(h.node('profileDetails').textContent, /Kept profile/);
    assert.match(h.node('workspaceNotice').textContent, /Session expired/);
});

test('manual sync refreshes accepted history and updates the pending count', async () => {
    const h = setup('home', snapshot(), [queued()]); workspace.mount(h.win); await settle();
    await h.node('workspaceSync').onclick();
    assert.equal(h.node('pendingCount').textContent, '0'); assert.equal(h.node('workspaceSync').disabled, true);
    assert.match(h.node('workspaceNotice').textContent, /1 transaction\(s\) synced/);
});

test('manual retry after authentication was required uses syncNow and its manual reset instead of direct sync', async () => {
    const h = setup('home', snapshot(), [queued()]); const calls = [];
    h.api.getSyncState = () => ({ phase: 'auth_required', pending: 1, needsReview: 0, user_id: 42 });
    h.api.sync = h.api.refresh = async () => { throw new Error('Direct sync or refresh must not run'); };
    h.api.syncNow = async options => {
        calls.push(options); h.setQueue([queued({ status: 'synced' })]);
        await h.event('offline-sync-state', { detail: { phase: 'ready', pending: 0, needsReview: 0, user_id: 42 } });
        return { synced: 1 };
    };
    workspace.mount(h.win); await settle();
    assert.equal(h.node('workspaceSync').disabled, false);
    await h.node('workspaceSync').onclick();
    assert.deepEqual(calls, [{ manual: true }]);
    assert.equal(h.node('pendingCount').textContent, '0');
    assert.match(h.node('workspaceNotice').textContent, /1 transaction\(s\) synced/);
});

test('syncNow reporting an offline connection retains pending work without claiming it synced', async () => {
    const h = setup('home', snapshot(), [queued()]);
    h.api.syncNow = async () => ({ offline: true, synced: 0 });
    h.api.sync = h.api.refresh = async () => { throw new Error('Direct sync or refresh must not run'); };
    workspace.mount(h.win); await settle(); await h.node('workspaceSync').onclick();
    assert.equal(h.node('pendingCount').textContent, '1');
    assert.match(h.node('workspaceNotice').textContent, /You are offline/);
    assert.match(h.node('workspaceNotice').textContent, /sync automatically/);
    assert.doesNotMatch(h.node('workspaceNotice').textContent, /transaction\(s\) synced/);
});

test('an unexpected account switch cannot replace the displayed saved profile', async () => {
    const saved = snapshot(); saved.profile = { name: 'Original profile' };
    const h = setup('account', saved); workspace.mount(h.win); await settle();
    h.setSnapshot({ ...saved, user: { id: 99 }, profile: { name: 'Foreign profile' } });
    await h.event('offline-data-updated');
    assert.doesNotMatch(h.node('profileDetails').textContent, /Foreign profile/);
    assert.match(h.node('workspaceNotice').textContent, /account changed/);
});

test('profile account link respects the app subdirectory and sign out uses shared session handling', async () => {
    const h = setup('account'); let signedOut = false;
    h.win.getOfflineAppUrl = path => 'https://example.test/portal/' + path;
    h.win.logoutOfflineUser = () => { signedOut = true; };
    workspace.mount(h.win); await settle(); h.node('logoutButton').onclick();
    assert.equal(h.node('onlineProfile').href, 'https://example.test/portal/account');
    assert.equal(signedOut, true);
});
