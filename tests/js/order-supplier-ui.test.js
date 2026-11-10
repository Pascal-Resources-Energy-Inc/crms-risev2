const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const cartView = fs.readFileSync(path.join(__dirname, '../../resources/views/cart.blade.php'), 'utf8');
const confirmationView = fs.readFileSync(path.join(__dirname, '../../resources/views/place_order.blade.php'), 'utf8');
const cartNormalizer = cartView.slice(cartView.indexOf('function normalizeOrderSupplier(supplier)'), cartView.indexOf('    window.matchedADs = @json'));
const confirmationNormalizer = confirmationView.slice(confirmationView.indexOf('function normalizeConfirmationSupplier(supplier)'), confirmationView.indexOf('    function displayCustomerDetails()'));
const submit = confirmationView.slice(confirmationView.indexOf('    async function saveTransactionsToDatabase()'), confirmationView.indexOf('    const completeOrderBtn ='))
    .replaceAll("{{ route('ad.transactions.store') }}", '/ad-transactions')
    .replaceAll("{{ route('transactions.store') }}", '/client-transactions');

function confirmation(supplier) {
    const calls = [];
    const context = vm.createContext({
        URL, FormData, console: { log() {}, error() {} },
        orderData: { transaction_type: 'ad_order', payment_method: 'cash', delivery_type: 'pickup' },
        customerData: null, adData: supplier, dealerCartData: [{ id: 10, name: 'Refill', quantity: 2 }],
        window: { location: { origin: 'https://example.test' } }, localStorage: { getItem() { return null; } },
        document: { querySelector() { return { getAttribute() { return 'synthetic-csrf'; } }; } },
        async fetch(url, options) { calls.push({ url, ...options }); return { ok: true, status: 200, text: async () => JSON.stringify({ success: true }) }; }
    });
    vm.runInContext(confirmationNormalizer + '\n' + submit, context);
    context.adData = context.normalizeConfirmationSupplier(supplier);
    return { context, calls };
}

function supplierRadioScreen(options = {}) {
    const suppliers = [{ id: 'area_distributor:200', supplier_id: 200, supplier_type: 'area_distributor', name: 'Assigned AD' },
        ...(options.noMega ? [] : [{ id: 'mega_dealer:200', supplier_id: 200, supplier_type: 'mega_dealer', name: 'Same-area Mega Dealer' }]),
        ...(options.withDms ? [{ id: 'mega_dealer:dms_prei:200', supplier_id: 200, supplier_type: 'mega_dealer', supplier_source: 'dms_prei', name: 'Same-area DMS Mega Dealer' }] : [])];
    const local = new Map(Object.entries(options.saved || {}));
    const nodes = Object.fromEntries(['orderSupplierAD', 'orderSupplierMega', 'order-supplier-type-help', 'assigned-ad-name', 'assigned-ad-details'].map(id => [id, { textContent: '', checked: false, disabled: false }]));
    const begin = cartView.indexOf('    window.matchedADs = @json');
    const end = cartView.indexOf("    document.addEventListener('DOMContentLoaded'", begin);
    const initialize = cartView.slice(begin, end)
        .replace('@json($matchedADs)', JSON.stringify(suppliers)).replace('@json($availableADs)', JSON.stringify(suppliers))
        .replace('@json($userCenter)', '"North"')
        .replace(/@json\(auth\(\)->user\(\)->isDealerLike\(\)[^\n;]+\)/, options.dealer === false ? 'false' : 'true');
    const detectBegin = cartView.indexOf('    function detectAD() {');
    const detect = cartView.slice(detectBegin, cartView.indexOf('    // function openADSelection()', detectBegin));
    const context = vm.createContext({ window: {}, localStorage: { getItem: key => local.get(key) || null, setItem: (key, value) => local.set(key, String(value)), removeItem: key => local.delete(key) },
        document: { getElementById: id => nodes[id] || null }, renderADDetails() {} });
    vm.runInContext(cartNormalizer + '\n' + initialize + '\n' + detect, context);
    context.detectAD();
    return { context, nodes, local };
}

test('online supplier radio switches filter the supplier list and do not restore an AD after choosing Mega Dealer', () => {
    const h = supplierRadioScreen(); assert.equal(h.nodes.orderSupplierAD.checked, true);
    assert.equal(h.context.window.selectedAD.supplier_type, 'area_distributor');
    h.context.changeOrderSupplierType('mega_dealer');
    assert.equal(h.nodes.orderSupplierMega.checked, true); assert.equal(h.nodes.orderSupplierAD.checked, false);
    assert.equal(h.context.window.selectedAD.supplier_type, 'mega_dealer');
    assert.equal(h.context.orderSuppliersForType('mega_dealer').length, 1);
    assert.equal(h.context.orderSuppliersForType('mega_dealer')[0].name, 'Same-area Mega Dealer');
    h.context.detectAD(); assert.equal(h.context.window.selectedAD.supplier_type, 'mega_dealer');
    const reopened = supplierRadioScreen({ saved: { selectedOrderSupplierType: h.local.get('selectedOrderSupplierType'), selectedAD: JSON.stringify(h.context.window.selectedAD) } });
    assert.equal(reopened.nodes.orderSupplierMega.checked, true); assert.equal(reopened.context.window.selectedAD.supplier_type, 'mega_dealer');
});

test('online Mega Dealer radio remains disabled for an ineligible role or an empty supplier group', () => {
    for (const options of [{ dealer: false }, { noMega: true }]) {
        const h = supplierRadioScreen(options); assert.equal(h.nodes.orderSupplierMega.disabled, true);
        h.context.changeOrderSupplierType('mega_dealer');
        assert.equal(h.nodes.orderSupplierAD.checked, true); assert.equal(h.context.window.orderSupplierType, 'area_distributor');
        assert.match(h.nodes['order-supplier-type-help'].textContent, options.noMega ? /No eligible Mega Dealer/ : /Dealer accounts/);
    }
});

test('online Dealer confirmation posts a Mega Dealer identity without reusing the overlapping AD ID', async () => {
    const h = confirmation({ id: 'mega_dealer:200', supplier_type: 'mega_dealer', supplier_id: 200, name: 'Same-area Mega Dealer' });
    await h.context.saveTransactionsToDatabase();
    assert.equal(h.calls.length, 1); assert.equal(h.calls[0].url, '/ad-transactions');
    const body = h.calls[0].body;
    assert.equal(body.get('supplier_type'), 'mega_dealer'); assert.equal(body.get('mega_dealer_id'), '200');
    assert.equal(body.has('supplier_source'), false);
    assert.equal(body.has('area_distributor_id'), false); assert.equal(body.get('item_id'), '10'); assert.equal(body.get('qty'), '2');
});

test('online Mega Dealer selection preserves DMS identity when primary and DMS profile IDs collide', () => {
    const dms = { id: 'mega_dealer:dms_prei:200', supplier_id: 200, supplier_type: 'mega_dealer', supplier_source: 'dms_prei', name: 'Same-area DMS Mega Dealer' };
    for (const saved of [{ selectedAD: JSON.stringify(dms) }, { selectedADId: dms.id }]) {
        const h = supplierRadioScreen({ withDms: true, saved: { ...saved, selectedOrderSupplierType: 'mega_dealer' } });
        assert.equal(h.context.window.selectedAD.id, dms.id);
        assert.equal(h.context.window.selectedAD.supplier_source, 'dms_prei');
        assert.equal(h.context.window.selectedAD.name, dms.name);
        assert.equal(h.context.orderSuppliersForType('mega_dealer').length, 2);
    }
    const legacy = supplierRadioScreen({ withDms: true, saved: { selectedAD: JSON.stringify({ id: 'mega_dealer:200', supplier_type: 'mega_dealer' }), selectedOrderSupplierType: 'mega_dealer' } });
    assert.equal(legacy.context.window.selectedAD.id, 'mega_dealer:200');
    assert.equal(legacy.context.window.selectedAD.supplier_source, 'admin_crms');
});

test('an unavailable saved DMS supplier cannot silently become the primary supplier with the same ID', () => {
    const h = supplierRadioScreen({ saved: { selectedAD: JSON.stringify({ id: 'mega_dealer:dms_prei:200', supplier_type: 'mega_dealer', supplier_source: 'dms_prei' }), selectedOrderSupplierType: 'mega_dealer' } });
    assert.equal(h.context.window.selectedAD, null);
    assert.match(h.nodes['assigned-ad-details'].textContent, /previous supplier is unavailable/);
    h.context.changeOrderSupplierType('area_distributor');
    assert.equal(h.context.window.selectedAD.supplier_type, 'area_distributor');
});

test('online confirmation sends the DMS source without changing the Mega Dealer numeric ID', async () => {
    for (const supplier of [
        { id: 'mega_dealer:dms_prei:200', supplier_type: 'mega_dealer' },
        { id: 200, supplier_type: 'mega_dealer', supplier_source: 'dms_prei' }
    ]) {
        const h = confirmation(supplier); await h.context.saveTransactionsToDatabase();
        assert.equal(h.calls.length, 1);
        const body = h.calls[0].body;
        assert.equal(body.get('mega_dealer_id'), '200');
        assert.equal(body.get('supplier_source'), 'dms_prei');
        assert.equal(body.has('area_distributor_id'), false);
        assert.equal(h.context.adData.id, 'mega_dealer:dms_prei:200');
    }
});

test('both online normalizers reject contradictory or unsupported supplier sources', () => {
    const context = vm.createContext({}); vm.runInContext(cartNormalizer + '\n' + confirmationNormalizer, context);
    for (const normalize of [context.normalizeOrderSupplier, context.normalizeConfirmationSupplier]) {
        for (const supplier of [
            { id: 'mega_dealer:dms_prei:200', supplier_type: 'mega_dealer', supplier_source: 'admin_crms' },
            { id: 'mega_dealer:200', supplier_type: 'mega_dealer', supplier_source: 'dms_prei' },
            { id: 'mega_dealer:dms_prei:200', supplier_type: 'mega_dealer', supplier_id: 201 },
            { id: 'mega_dealer:other_db:200', supplier_type: 'mega_dealer' },
            { id: 200, supplier_type: 'mega_dealer', supplier_source: 'other_db' },
            { id: 200, supplier_type: 'mega_dealer', supplier_source: false },
            { id: 200, supplier_type: 'mega_dealer', supplier_source: 0 },
            { id: 200, supplier_type: 'mega_dealer', supplier_source: {} },
            { id: 200, supplier_type: 'area_distributor', supplier_source: 'admin_crms' }
        ]) assert.equal(normalize(supplier), null);
        for (const source of [undefined, null, '']) assert.equal(normalize({ id: 200, supplier_type: 'mega_dealer', supplier_source: source }).supplier_source, 'admin_crms');
        assert.equal(normalize({ id: 'mega_dealer:dms_prei:200', supplier_type: 'mega_dealer' }).supplier_source, 'dms_prei');
    }
});

test('product stock requests carry the selected DMS source and reject a contradictory key', async () => {
    const start = cartView.indexOf('        async function fetchLatestStocks(');
    const stockFetch = cartView.slice(start, cartView.indexOf('        function getCartStockIssues(', start))
        .replace("{{ route('products.stocks') }}", '/product-stocks');
    const calls = [], context = vm.createContext({ URL, window: { location: { origin: 'https://example.test' } },
        async fetch(url) { calls.push(new URL(url)); return { ok: true, async json() { return { supplier: {} }; } }; } });
    vm.runInContext(cartNormalizer + '\n' + stockFetch, context);
    await context.fetchLatestStocks('ad_order', 'mega_dealer:dms_prei:200');
    assert.equal(calls[0].searchParams.get('supplier_source'), 'dms_prei');
    assert.equal(calls[0].searchParams.get('mega_dealer_id'), '200');
    await context.fetchLatestStocks('ad_order', 'mega_dealer:200');
    assert.equal(calls[1].searchParams.has('supplier_source'), false);
    await assert.rejects(context.fetchLatestStocks('ad_order', { id: 'mega_dealer:200', supplier_type: 'mega_dealer', supplier_source: 'dms_prei' }), /eligible supplier/);
    assert.equal(calls.length, 2);
});

test('existing AD confirmation remains compatible with a legacy numeric supplier ID', async () => {
    const h = confirmation({ id: 200, name: 'Assigned AD' }); await h.context.saveTransactionsToDatabase();
    const body = h.calls[0].body;
    assert.equal(body.get('supplier_type'), 'area_distributor'); assert.equal(body.get('area_distributor_id'), '200');
    assert.equal(body.has('mega_dealer_id'), false);
});

test('typed supplier keys cannot be reinterpreted as another supplier type in either online screen', async () => {
    const context = vm.createContext({}); vm.runInContext(cartNormalizer + '\n' + confirmationNormalizer, context);
    for (const normalize of [context.normalizeOrderSupplier, context.normalizeConfirmationSupplier]) {
        assert.equal(normalize({ id: 'mega_dealer:200' }), null);
        assert.equal(normalize({ id: 'area_distributor:200', supplier_type: 'mega_dealer' }), null);
        assert.equal(normalize({ id: 200, supplier_type: 'primary_distributor' }), null);
        assert.equal(normalize({ id: 'mega_dealer:200', supplier_type: 'mega_dealer' }).supplier_id, 200);
    }
    const h = confirmation({ id: 'mega_dealer:200' });
    await assert.rejects(h.context.saveTransactionsToDatabase(), /eligible supplier/); assert.equal(h.calls.length, 0);
});

test('client confirmation continues to send only the customer identity', async () => {
    const h = confirmation(null); h.context.orderData.transaction_type = 'client_transaction'; h.context.customerData = { id: 100 };
    await h.context.saveTransactionsToDatabase(); const body = h.calls[0].body;
    assert.equal(h.calls[0].url, '/client-transactions'); assert.equal(body.get('customer_id'), '100');
    assert.equal(body.has('supplier_type'), false); assert.equal(body.has('mega_dealer_id'), false);
});

test('supplier picker and address cards escape profile text without double escaping it', () => {
    const escape = cartView.slice(cartView.indexOf('function escapeOrderSupplierText(value)'), cartView.indexOf('    function normalizeOrderSupplier(supplier)'));
    const dropdown = cartView.slice(cartView.indexOf('    class SearchableDropdown {'), cartView.indexOf('    let customerDropdown = null;'));
    const details = cartView.slice(cartView.indexOf('    function renderADDetails(ad) {'), cartView.indexOf('</script>', cartView.indexOf('    function renderADDetails(ad) {')));
    const card = { innerHTML: '' }, context = vm.createContext({ document: { getElementById() { return card; } } });
    vm.runInContext(escape + '\n' + dropdown + '\nthis.SupplierDropdown=SearchableDropdown;\n' + details, context);
    const picker = Object.create(context.SupplierDropdown.prototype);
    picker.filteredOptions = [{ id: 'mega_dealer:200', name: 'MD & Co --> <img src=x onerror=attack()>',
        supplierLabel: 'Mega Dealer <script>attack()</script>' }];
    const html = picker.renderOptions();
    assert.match(html, /MD &amp; Co --&gt; &lt;img/); assert.doesNotMatch(html, /<img|<script>|<!--|&amp;amp;/);
    assert.match(html, /data-id="mega_dealer:200"/);
    context.renderADDetails({ supplier_type: 'mega_dealer', distance: 1, areas: ['North & East', '<svg onload=attack()>'],
        address: 'Road & Lane </span><img src=x onerror=attack()>' });
    assert.match(card.innerHTML, /Road &amp; Lane &lt;\/span&gt;&lt;img/);
    assert.match(card.innerHTML, /North &amp; East/); assert.doesNotMatch(card.innerHTML, /<img|<svg onload|&amp;amp;/);
});
