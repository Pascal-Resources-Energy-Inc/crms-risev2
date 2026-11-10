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

test('a Dealer can choose a same-area Mega Dealer without confusing an overlapping AD ID', async () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push({ id: 200, name: 'North Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10], stock_by_product: { 10: 6 } });
    const h = harness(catalogIds, {}, saved);
    h.api.validateOrder = require('../../public/offline/data.js')(h.win, {}).validateOrder;
    catalog.mount(h.win); await settle(); h.node('orderMode').onchange();
    assert.equal(h.parsed('offlineCartContext').ad_id, 200);
    assert.match(h.node('distributorSelect').textContent, /Area Distributor · Assigned AD/);
    assert.doesNotMatch(h.node('distributorSelect').textContent, /Mega Dealer/);
    assert.equal(h.node('supplierAD').checked, true); assert.equal(h.node('supplierMega').disabled, false);
    h.node('supplierMega').onchange();
    assert.match(h.node('distributorSelect').textContent, /Mega Dealer · North Mega Dealer/);
    assert.doesNotMatch(h.node('distributorSelect').textContent, /Assigned AD/);
    h.node('distributorSelect').value = 'mega_dealer:200'; h.node('distributorSelect').onchange();
    assert.equal(h.node('productGrid').children.length, 1);
    assert.match(h.node('productGrid').textContent, /Mega Dealer stock: 6/);
    const button = h.node('productGrid').children[0].children[4].children[1]; button.onclick();
    h.node('productQuantity').value = 20; h.node('quantityForm').onsubmit({ preventDefault() {} });
    const selected = h.parsed('offlineCartContext');
    assert.equal(selected.supplier_type, 'mega_dealer'); assert.equal(selected.mega_dealer_id, 200); assert.equal(selected.ad_id, null);
    assert.equal(h.parsed('dealerCartData')[0].price, 50);
    assert.equal(h.parsed('dealerCartData')[0].quantity, 20);
});

test('DMS and local Mega Dealers with the same ID keep distinct selections, products and saved carts', async () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push(
        { id: 200, name: 'Local Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10], stock_by_product: { 10: 6 } },
        { id: 200, name: 'DMS Mega Dealer', supplier_type: 'mega_dealer', supplier_source: 'dms_prei', product_ids: [11], stock_by_product: { 11: 9 } }
    );
    const selected = context({ supplier_type: 'mega_dealer', mega_dealer_id: 200, supplier_source: 'dms_prei', ad_id: null });
    const h = harness(catalogIds, { offlineCartContext: selected, dealerCartData: [] }, saved);
    h.api.validateOrder = require('../../public/offline/data.js')(h.win, {}).validateOrder;
    catalog.mount(h.win); await settle();
    assert.equal(h.node('distributorSelect').value, 'mega_dealer:dms_prei:200');
    assert.deepEqual(h.node('distributorSelect').children.slice(1).map(option => option.value), ['mega_dealer:200', 'mega_dealer:dms_prei:200']);
    assert.match(h.node('productGrid').textContent, /Cylinder/); assert.doesNotMatch(h.node('productGrid').textContent, /Refill/);
    assert.match(h.node('productGrid').textContent, /Mega Dealer stock: 9/);
    h.node('productGrid').children[0].children[4].children[1].onclick();
    h.node('productQuantity').value = 2; h.node('quantityForm').onsubmit({ preventDefault() {} });
    assert.equal(h.parsed('offlineCartContext').supplier_source, 'dms_prei');
    assert.equal(h.parsed('dealerCartData')[0].product_id, 11);
    h.win.confirm = () => false; h.node('distributorSelect').value = 'mega_dealer:200'; h.node('distributorSelect').onchange();
    assert.equal(h.node('distributorSelect').value, 'mega_dealer:dms_prei:200');
    assert.equal(h.parsed('dealerCartData').length, 1);
    h.win.confirm = () => true; h.node('distributorSelect').value = 'mega_dealer:200'; h.node('distributorSelect').onchange();
    assert.equal(h.parsed('offlineCartContext').supplier_source, undefined);
    assert.equal(h.parsed('dealerCartData').length, 0);
    assert.match(h.node('productGrid').textContent, /Refill/); assert.doesNotMatch(h.node('productGrid').textContent, /Cylinder/);
});

test('a removed DMS Mega Dealer cannot fall back to a local supplier with the same ID', () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push({ id: 200, name: 'Local Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10] });
    const selected = context({ supplier_type: 'mega_dealer', mega_dealer_id: 200, supplier_source: 'dms_prei', ad_id: null });
    assert.deepEqual(catalog.productsFor(saved, selected, ''), []);
    for (const source of ['unknown', false, 0, {}]) assert.deepEqual(catalog.productsFor(saved, { ...selected, supplier_source: source }, ''), []);
});

test('reopening the order mode does not replace an unavailable saved DMS supplier with the only local match', async () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push({ id: 200, name: 'Local Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10] });
    const selected = context({ supplier_type: 'mega_dealer', mega_dealer_id: 200, supplier_source: 'dms_prei', ad_id: null });
    const h = harness(catalogIds, { offlineCartContext: selected, dealerCartData: [item()] }, saved);
    catalog.mount(h.win); await settle(); h.node('orderMode').onchange(); h.node('supplierMega').onchange();
    assert.equal(h.parsed('offlineCartContext').supplier_source, 'dms_prei');
    assert.equal(h.parsed('dealerCartData').length, 1); assert.equal(h.node('distributorSelect').value, '');
    assert.match(h.node('catalogNotice').textContent, /previously selected Mega Dealer is unavailable/);
    assert.doesNotMatch(h.node('productGrid').textContent, /Refill/);
});

test('supplier radio switches preserve an unsent cart when replacement is cancelled', async () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push({ id: 200, name: 'North Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10] });
    const h = harness(catalogIds, { offlineCartContext: context(), dealerCartData: [item()] }, saved);
    h.win.confirm = () => false; catalog.mount(h.win); await settle(); h.node('supplierMega').onchange();
    assert.equal(h.node('supplierAD').checked, true); assert.equal(h.node('supplierMega').checked, false);
    assert.equal(h.parsed('offlineCartContext').ad_id, 200); assert.equal(h.parsed('dealerCartData').length, 1);
    h.win.confirm = () => true; h.node('supplierMega').onchange();
    assert.equal(h.parsed('offlineCartContext').supplier_type, 'mega_dealer'); assert.equal(h.parsed('dealerCartData').length, 0);
});

test('an explicit Mega Dealer choice restores its radio and filtered supplier after reopening', async () => {
    const saved = snapshot(); saved.user.role = 'Dealer';
    saved.distributors.push({ id: 200, name: 'North Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10] });
    const selected = context({ supplier_type: 'mega_dealer', mega_dealer_id: 200, ad_id: null });
    const h = harness(catalogIds, { offlineCartContext: selected, dealerCartData: [] }, saved);
    catalog.mount(h.win); await settle();
    assert.equal(h.node('supplierMega').checked, true); assert.equal(h.node('supplierAD').checked, false);
    assert.equal(h.node('distributorSelect').value, 'mega_dealer:200');
    assert.doesNotMatch(h.node('distributorSelect').textContent, /Assigned AD/);
    h.node('orderMode').onchange();
    assert.equal(h.parsed('offlineCartContext').mega_dealer_id, 200); assert.equal(h.parsed('offlineCartContext').ad_id, null);
});

test('a Mega Dealer radio is disabled for another role or when no eligible Mega Dealer exists', async () => {
    for (const role of ['Mega Dealer', 'center_chief', 'Dealer']) {
        const saved = snapshot(); saved.user.role = role;
        if (role !== 'Dealer') saved.distributors.push({ id: 200, name: 'Ineligible Mega Dealer', supplier_type: 'mega_dealer', product_ids: [10] });
        const h = harness(catalogIds, {}, saved); catalog.mount(h.win); await settle(); h.node('orderMode').onchange();
        assert.equal(h.node('supplierMega').disabled, true); h.node('supplierMega').onchange();
        assert.equal(h.node('supplierAD').checked, true); assert.doesNotMatch(h.node('distributorSelect').textContent, /Ineligible/);
        assert.match(h.node('supplierTypeHelp').textContent, role === 'Dealer' ? /No eligible Mega Dealer/ : /Dealer accounts/);
    }
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
