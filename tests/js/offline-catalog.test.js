const assert = require('node:assert/strict');
const test = require('node:test');
const catalog = require('../../public/offline/catalog.js');
const { harness, snapshot, settle, catalogIds, context, item } = require('./offline-ui-helpers');

test('AD products remain orderable at zero stock with the saved order price', () => {
    const product = snapshot().products[0];
    assert.equal(catalog.itemFor(product, 'order', 500).price, 50);
    assert.equal(catalog.itemFor(product, 'order', 500).quantity, 500);
    assert.throws(() => catalog.itemFor(product, 'client', 4), /available saved stock/);
    for (const value of [0, -1, 1.5, 1000001, Infinity]) assert.throws(() => catalog.itemFor(product, 'order', value), /whole quantity/);
});

test('catalog filters order products by the selected AD without excluding zero stock', () => {
    const saved = snapshot(); saved.products.push({ ...saved.products[0], id: 99, ad_id: 999 });
    assert.deepEqual(catalog.productsFor(saved, context(), 'refill').map(row => row.id), [10]);
});

test('the first saved snapshot populates an already open empty catalog', async () => {
    const h = harness(catalogIds, {}, null); catalog.mount(h.win); await settle();
    assert.match(h.node('productGrid').textContent, /No saved catalog/);
    h.setSnapshot(snapshot()); await h.event('offline-data-updated');
    assert.match(h.node('productGrid').textContent, /Refill/);
    assert.match(h.node('customerSelect').textContent, /Assigned Customer/);
});

test('a zero-stock AD product can be added through the quantity dialog', async () => {
    const h = harness(catalogIds); catalog.mount(h.win); await settle();
    h.node('orderMode').onchange();
    assert.match(h.node('productGrid').textContent, /Out of stock · Available to order/);
    const button = h.node('productGrid').children[0].children[4].children[1];
    assert.equal(button.disabled, false); button.onclick();
    assert.equal(h.node('productQuantity').max, 1000000); h.node('productQuantity').value = 500;
    h.node('quantityForm').onsubmit({ preventDefault() {} });
    assert.equal(h.parsed('dealerCartData')[0].quantity, 500);
    assert.equal(h.parsed('dealerCartData')[0].price, 50);
    assert.equal(h.parsed('offlineCartContext').ad_id, 200);
});

test('client product at zero dealer stock stays disabled', async () => {
    const h = harness(catalogIds); catalog.mount(h.win); await settle();
    assert.equal(h.node('productGrid').children[1].children[4].children[1].disabled, true);
});

test('an incompatible account cart stays intact until explicitly replaced', async () => {
    const cart = [item()], h = harness(catalogIds, { dealerCartData: cart, offlineCartContext: context({ user_id: 99 }) });
    catalog.mount(h.win); await settle();
    assert.equal(h.node('orderMode').disabled, true);
    assert.match(h.node('catalogNotice').textContent, /another account/);
    assert.deepEqual(h.parsed('dealerCartData'), cart);
    assert.equal(h.parsed('offlineCartContext').user_id, 99);
});

test('latest prices update the catalog without silently repricing the existing cart', async () => {
    const h = harness(catalogIds, { dealerCartData: [item()], offlineCartContext: context() });
    catalog.mount(h.win); await settle();
    const next = snapshot(); next.products[0].order_price = 55; h.setSnapshot(next);
    await h.event('offline-data-updated');
    assert.match(h.node('productGrid').textContent, /55\.00/);
    assert.equal(h.parsed('dealerCartData')[0].price, 50);
    assert.match(h.node('catalogNotice').textContent, /Review cart prices/);
});

test('a failed cart context write restores the prior cart instead of losing it', () => {
    const h = harness(catalogIds, { dealerCartData: '["previous"]', offlineCartContext: '{"user_id":42}' });
    h.storage.failKey = 'offlineCartContext';
    assert.throws(() => catalog.commit(h.storage, { dealerCartData: '[]', offlineCartContext: '{}' }), /previous cart is kept/);
    assert.equal(h.values.get('dealerCartData'), '["previous"]');
});

test('automatic updates wait for the quantity dialog to save or close without changing the cart', async () => {
    const h = harness(catalogIds, { dealerCartData: [item()], offlineCartContext: context() });
    catalog.mount(h.win); await settle();
    const before = h.values.get('dealerCartData');
    h.node('quantityDialog').open = true; h.node('productQuantity').value = 42;
    let prevented = false;
    await h.event('offline-before-update', { detail: { automatic: true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true); assert.equal(h.node('productQuantity').value, 42);
    assert.equal(h.values.get('dealerCartData'), before);
    h.node('quantityDialog').close(); prevented = false;
    await h.event('offline-before-update', { detail: { automatic: true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});

test('missing shared data files show recovery text and disable ordering without throwing', () => {
    const h = harness(catalogIds); delete h.win.OfflineData;
    assert.doesNotThrow(() => catalog.mount(h.win));
    assert.match(h.node('productGrid').textContent, /files are not ready/);
    assert.equal(h.node('orderMode').disabled, true);
    assert.equal(h.node('refreshData').disabled, true);
});
