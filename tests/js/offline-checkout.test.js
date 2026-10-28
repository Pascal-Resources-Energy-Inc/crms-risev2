const assert = require('node:assert/strict');
const test = require('node:test');
const checkout = require('../../public/offline/checkout.js');
const { harness, snapshot, settle, cartIds, confirmationIds, context, item } = require('./offline-ui-helpers');

function cartHarness() { return harness(cartIds, { offlineCartContext: context(), dealerCartData: [item()] }); }
function confirmationHarness() {
    const draft = { ...context(), items: [item()], idempotency_key: '00000000-0000-4000-8000-000000000001', payment_method: 'cash', delivery_type: 'pickup' };
    return harness(confirmationIds, { dealerOrderData: draft, offlineCartContext: context(), dealerCartData: [item()] });
}

test('AD cart above zero stock reaches order review using its saved price and preferences', async () => {
    const h = cartHarness(); checkout.mount(h.win); await settle();
    assert.equal(h.node('reviewOrder').disabled, false);
    h.node('paymentMethod').value = 'gcash'; h.node('deliveryType').value = 'delivery';
    await h.node('reviewOrder').onclick();
    assert.equal(h.parsed('dealerOrderData').items[0].quantity, 20);
    assert.equal(h.parsed('dealerOrderData').payment_method, 'gcash');
    assert.equal(h.parsed('dealerOrderData').delivery_type, 'delivery');
    assert.equal(h.win.location.href, 'confirm_order.html');
});

test('a price update disables checkout without changing the saved price or selected preferences', async () => {
    const h = cartHarness(); checkout.mount(h.win); await settle();
    h.node('paymentMethod').value = 'credit'; h.node('deliveryType').value = 'delivery';
    const next = snapshot(); next.products[0].order_price = 55; h.setSnapshot(next);
    await h.event('offline-data-updated');
    assert.equal(h.node('reviewOrder').disabled, true);
    assert.equal(h.parsed('dealerCartData')[0].price, 50);
    assert.equal(h.node('paymentMethod').value, 'credit');
    assert.equal(h.node('deliveryType').value, 'delivery');
    assert.match(h.node('checkoutNotice').textContent, /price changed/);
});

test('new account data recovers checkout opened before its first saved snapshot', async () => {
    const h = cartHarness(); h.setSnapshot(null); checkout.mount(h.win); await settle();
    assert.equal(h.node('reviewOrder').disabled, true);
    h.setSnapshot(snapshot()); await h.event('offline-data-updated');
    assert.equal(h.node('reviewOrder').disabled, false);
});

test('confirmation waits for durable save and cannot be reenabled by outbox events', async () => {
    const h = confirmationHarness(); let finish;
    h.api.queueOrder = async order => { h.queued.push(order); await new Promise(resolve => { finish = resolve; }); };
    checkout.mount(h.win); await settle();
    const saving = h.node('saveOrder').onclick(); await settle();
    await h.event('offline-outbox-updated'); assert.equal(h.node('saveOrder').disabled, true);
    let prevented = false; await h.event('offline-before-update', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.ok(h.values.has('dealerOrderData'));
    finish(); await saving; await h.event('offline-outbox-updated');
    assert.equal(h.node('saveOrder').disabled, true);
    assert.equal(h.queued.length, 1); assert.equal(h.node('pendingLink').hidden, false);
    assert.equal(h.values.has('dealerOrderData'), false);
});

test('a newer cart from another tab survives a saved order acknowledgement', async () => {
    const h = confirmationHarness(); let finish;
    h.api.queueOrder = async () => new Promise(resolve => { finish = resolve; });
    checkout.mount(h.win); await settle();
    const saving = h.node('saveOrder').onclick(); await settle();
    h.values.set('dealerCartData', JSON.stringify([item({ quantity: 99 })]));
    finish(); await saving;
    assert.equal(h.parsed('dealerCartData')[0].quantity, 99);
});

test('an automatic acknowledgement before queueOrder returns shows submitted status without a second save', async () => {
    const h = confirmationHarness(); let accepted;
    h.api.listOrders = async () => accepted ? [accepted] : [];
    h.api.queueOrder = async order => {
        h.queued.push(order); accepted = { ...order, status: 'synced' };
        await h.event('offline-outbox-updated');
        return accepted;
    };
    checkout.mount(h.win); await settle(); await h.node('saveOrder').onclick();
    assert.equal(h.node('saveOrder').textContent, 'Saved and synced');
    assert.equal(h.node('saveOrder').disabled, true);
    assert.match(h.node('checkoutNotice').textContent, /submitted successfully/);
    assert.match(h.node('checkoutNotice').textContent, /fulfilment remain subject to confirmation/);
    await h.node('saveOrder').onclick(); assert.equal(h.queued.length, 1);
});

test('saved confirmation follows later review and sync events without touching a newer cart', async () => {
    const h = confirmationHarness(); let row;
    h.api.listOrders = async () => row ? [row] : [];
    h.api.queueOrder = async order => { row = { ...order, status: 'pending' }; };
    checkout.mount(h.win); await settle(); await h.node('saveOrder').onclick();
    assert.match(h.node('checkoutNotice').textContent, /sync automatically/);
    h.values.set('dealerCartData', JSON.stringify([item({ quantity: 99 })]));
    row = { ...row, status: 'needs_review', last_error: 'The price changed.' };
    await h.event('offline-outbox-updated');
    assert.equal(h.node('saveOrder').textContent, 'Saved · Needs review');
    assert.match(h.node('checkoutNotice').textContent, /The price changed/);
    row = { ...row, status: 'synced' }; await h.event('offline-data-updated');
    assert.equal(h.node('saveOrder').textContent, 'Saved and synced');
    assert.equal(h.node('saveOrder').disabled, true);
    assert.equal(h.parsed('dealerCartData')[0].quantity, 99);
});

test('a slow pending status read cannot overwrite a newer sync acknowledgement', async () => {
    const h = confirmationHarness(); const order = h.parsed('dealerOrderData');
    h.api.listOrders = async () => [{ ...order, status: 'pending' }];
    checkout.mount(h.win); await settle(); await h.node('saveOrder').onclick();
    let finish;
    h.api.listOrders = () => new Promise(resolve => { finish = resolve; });
    const firstRead = h.event('offline-outbox-updated');
    h.api.listOrders = async () => [{ ...order, status: 'synced' }];
    await h.event('offline-outbox-updated'); finish([{ ...order, status: 'pending' }]); await firstRead;
    assert.equal(h.node('saveOrder').textContent, 'Saved and synced');
    assert.equal(h.node('saveOrder').disabled, true);
});

test('a temporary queue read failure keeps the last acknowledged saved status', async () => {
    const h = confirmationHarness(); const order = h.parsed('dealerOrderData');
    h.api.queueOrder = async () => ({ ...order, status: 'synced' });
    h.api.listOrders = async () => { throw new Error('Storage temporarily unavailable'); };
    checkout.mount(h.win); await settle(); await h.node('saveOrder').onclick();
    await h.event('offline-outbox-updated');
    assert.equal(h.node('saveOrder').textContent, 'Saved and synced');
    assert.equal(h.node('saveOrder').disabled, true);
});

test('applying an update persists cart controls and blocks on storage failure', async () => {
    const h = cartHarness(); checkout.mount(h.win); await settle();
    h.node('paymentMethod').value = 'bank_transfer'; h.node('deliveryType').value = 'delivery';
    await h.event('offline-before-update', { preventDefault() { throw new Error('Unexpected prevention'); } });
    assert.equal(h.parsed('offlineCartContext').payment_method, 'bank_transfer');
    h.storage.failKey = 'offlineCartContext'; let prevented = false;
    await h.event('offline-before-update', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
});

test('client sales still cannot exceed the saved dealer stock', async () => {
    const h = harness(cartIds, { offlineCartContext: context({ type: 'client', client_id: 100, ad_id: null }), dealerCartData: [item({ price: 70, unit_price: 70, quantity: 4 })] });
    checkout.mount(h.win); await settle();
    assert.equal(h.node('reviewOrder').disabled, true);
    assert.match(h.node('checkoutNotice').textContent, /available stock/);
});

test('reconnection keeps an uncommitted cart quantity and defers automatic updates until it is saved', async () => {
    const h = cartHarness(); checkout.mount(h.win); await settle();
    const input = h.node('cartItems').children[0].children[0].children[2].children[1];
    input.value = 27; input.oninput();
    await h.event('offline-data-updated');
    assert.equal(h.node('cartItems').children[0].children[0].children[2].children[1], input);
    assert.equal(input.value, 27); assert.equal(h.parsed('dealerCartData')[0].quantity, 20);
    assert.equal(h.node('reviewOrder').disabled, true);
    let prevented = false;
    await h.event('offline-before-update', { detail: { automatic: true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    input.onchange(); assert.equal(h.parsed('dealerCartData')[0].quantity, 27);
    prevented = false;
    await h.event('offline-before-update', { detail: { automatic: true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});

test('a saved confirmation opens history after an automatic update and preserves a newer cart', async () => {
    const h = confirmationHarness(); checkout.mount(h.win); await settle();
    await h.node('saveOrder').onclick();
    h.values.set('dealerCartData', JSON.stringify([item({ quantity: 99 })]));
    const detail = { automatic: true };
    await h.event('offline-before-update', { detail, preventDefault() { throw Error('Saved confirmation should be safe to update'); } });
    assert.equal(detail.url, 'https://example.test/app/offline/transaction.html');
    assert.equal(h.parsed('dealerCartData')[0].quantity, 99);
    assert.equal(h.queued.length, 1);
});

test('a stale cart from another tab cannot be overwritten by automatic update preparation', async () => {
    const h = cartHarness(); checkout.mount(h.win); await settle();
    h.values.set('offlineCartContext', JSON.stringify(context({ ad_id: 999 })));
    let prevented = false;
    await h.event('offline-before-update', { detail: { automatic: true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.equal(h.parsed('offlineCartContext').ad_id, 999);
});
