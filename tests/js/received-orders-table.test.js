const assert = require('node:assert/strict');
const test = require('node:test');
const ui = require('../../public/inside_css/assets/js/received-orders.js');

const available = {
    id: 9, dealer_name: 'ARLENE ABALUADO', transaction_id: 'PRORD-0009',
    created_at_display: 'Oct 28, 2026 · 04:24 PM', product_name: '330g Gaz Lite Crate', sku: 'GL-CRATE',
    quantity: 2, available_stock: 8, stock_state: 'available', can_fulfil: true,
    stock_message: 'Enough stock to fulfil this order.', payment_label: 'Cash', delivery_label: 'Pickup',
    total_amount: 800, status: 'Pending'
};

function element(dataset = {}) {
    const handlers = {};
    return {
        dataset, handlers, disabled: false, hidden: true, textContent: '', value: '', attributes: {},
        setAttribute(key, value) { this.attributes[key] = value; },
        getAttribute(key) { return this.attributes[key]; },
        addEventListener(event, callback) { handlers[event] = callback; },
        querySelector() { return this.label || null; }
    };
}

function screen(options = {}) {
    const events = {};
    const buttons = [element({ id: '9', reference: 'PRORD-0009', allowed: 'true' }),
        element({ id: '10', reference: 'PRORD-0010', allowed: 'false' })];
    buttons.forEach(button => { button.label = element(); });
    const node = element({ source: '/api/mega-dealer/orders/received', fulfilUrl: '/mega-dealer/orders/__ORDER__/fulfil' });
    node.closest = () => ({ classList: { toggle() {} } });
    node.querySelectorAll = () => buttons;
    node.contains = button => buttons.includes(button);
    const ids = { 'received-dealer-orders': node };
    for (const id of ['error', 'error-message', 'count', 'status', 'sort', 'refresh', 'retry']) {
        ids['received-orders-' + id] = element();
    }
    ids['received-orders-sort'].value = 'newest';
    const reloads = [];
    const posts = [];
    const alerts = [];
    const orders = [];
    const pageChanges = [];
    const draws = [];
    const page = Object.assign(function (target) { pageChanges.push(target); return api; }, {
        info() { return options.page || { recordsDisplay: 30, start: 10 }; }
    });
    let currentOrder = [[0, 'desc']];
    const api = {
        ajax: { reload(callback, reset) {
            reloads.push([callback, reset]);
            events['processing.dt']({}, {}, true);
        } },
        page,
        order(value) {
            if (!value) return currentOrder;
            currentOrder = value; orders.push(value); return api;
        },
        draw(value) { draws.push(value); return api; },
        table() { return { container: () => ({ querySelector: () => null }) }; }
    };
    let settings;
    const wrapped = {
        on(event, callback) { events[event] = callback; return wrapped; },
        DataTable(value) { settings = value; return api; }
    };
    const jquery = () => wrapped;
    jquery.fn = { DataTable() {} };
    const env = {
        document: {
            getElementById(id) { return ids[id]; },
            querySelector() { return options.noCsrf ? null : { getAttribute: () => 'synthetic-csrf' }; }
        },
        jQuery: jquery,
        addEventListener(event, callback) { events[event] = callback; },
        Swal: {
            async fire(alert) {
                alerts.push(alert);
                if (alert.input) return options.confirm ? options.confirm(alert) : { isConfirmed: true, value: 'Collected by Dealer' };
                return {};
            },
            showValidationMessage(message) { alerts.push({ validation: message }); }
        },
        async fetch(url, config) {
            posts.push({ url, ...config });
            if (options.fetch) return options.fetch(url, config);
            return { ok: true, status: 200, text: async () => '{"success":true,"status":"For Verification","message":"Order submitted for Dealer verification."}' };
        }
    };
    const controller = ui.mount(env);
    return { controller, env, events, ids, node, buttons, reloads, posts, alerts, orders, pageChanges, draws,
        settings, api, loaded(json = { draw: 2, recordsTotal: 30 }) {
            events['xhr.dt']({}, { iDraw: 2 }, json, { status: 200 });
            settings.drawCallback.call({ api: () => api });
            events['processing.dt']({}, {}, false);
        }
    };
}

test('DataTables requests real server paging, searching and processing with allowlisted column names', () => {
    const h = screen();
    assert.equal(h.settings.serverSide, true);
    assert.equal(h.settings.processing, true);
    assert.equal(h.settings.ajax.url, '/api/mega-dealer/orders/received');
    assert.deepEqual(h.settings.lengthMenu, [10, 25, 50, 100]);
    assert.deepEqual(h.settings.order, [[0, 'desc']]);
    assert.equal(h.settings.columns[3].orderable, false);
    assert.equal(h.settings.columns[8].orderable, false);
    assert.equal(h.settings.columns[6].name, 'total_amount');
    const request = { draw: 4, start: 20, length: 10, search: { value: 'Crate' } };
    h.ids['received-orders-status'].value = 'Pending';
    h.settings.ajax.data(request);
    assert.equal(request.status, 'Pending');
    assert.equal(request.start, 20);
    assert.equal(request.search.value, 'Crate');
});

test('mobile row labels preserve the meaning of all nine table cells', () => {
    const cells = Array.from({ length: 9 }, () => element());
    ui.createOptions({ url: '/' }).createdRow({ cells });
    assert.deepEqual(cells.map(cell => cell.attributes['data-label']),
        ['Order', 'Product', 'Requested', 'Available stock', 'Payment', 'Delivery', 'Total', 'Status', 'Action']);
});

test('all buyer, reference, product, SKU, status and stock message content is escaped', () => {
    const attack = '"><img src=x onerror="alert(1)">&';
    const row = { ...available, dealer_name: attack, transaction_id: attack, created_at_display: attack,
        product_name: attack, sku: attack, status: attack, stock_message: attack, stock_state: attack };
    for (const render of [ui.renderOrder, ui.renderProduct, ui.renderStock, ui.renderStatus]) {
        const html = render(row);
        assert.equal(html.includes('<img'), false);
        assert.ok(html.includes('&lt;img'));
    }
    const action = ui.renderAction({ ...available, id: attack, transaction_id: attack, stock_message: attack });
    assert.equal(action.includes('data-allowed="true"'), false);
    assert.equal(action.includes('<img'), false);
    assert.ok(action.includes('disabled'));
    const payment = ui.createOptions({ url: '/' }).columns[4].render(attack, 'display');
    assert.equal(payment.includes('<img'), false);
});

test('fulfilment is enabled only for a Pending order with enough actual stock and server permission', () => {
    assert.equal(ui.canFulfil(available), true);
    for (const changed of [
        { available_stock: 0 }, { available_stock: 1 }, { available_stock: null }, { stock_state: 'unavailable' },
        { stock_state: 'out_of_stock' }, { quantity: 0 }, { quantity: -2 }, { status: 'Completed' }, { status: 'For Verification' },
        { can_fulfil: false }, { can_fulfil: 'true' }, { id: '../../anything' }
    ]) {
        const row = { ...available, ...changed };
        assert.equal(ui.canFulfil(row), false, JSON.stringify(changed));
        assert.equal(ui.renderAction(row).includes('data-allowed="true"'), false);
    }
    assert.ok(ui.renderAction(available).includes('data-allowed="true"'));
    assert.equal(ui.renderAction({ ...available, status: 'Completed' }).includes('<button'), false);
});

test('orders awaiting Dealer verification show the verification badge and cannot be fulfilled again', () => {
    const row = { ...available, status: 'For Verification' };
    assert.equal(ui.canFulfil(row), false);
    assert.match(ui.renderStatus(row), /badge--verification/);
    assert.match(ui.renderStatus(row), /For Verification/);
    assert.equal(ui.renderAction(row).includes('<button'), false);
    assert.match(ui.renderAction(row), /Awaiting Dealer verification/);
    assert.match(ui.renderAction({ ...row, status: 'Completed' }), /Receipt confirmed by Dealer/);
    const h = screen();
    const request = {};
    h.ids['received-orders-status'].value = 'For Verification';
    h.settings.ajax.data(request);
    assert.equal(request.status, 'For Verification');
});

test('zero, insufficient and unknown stock have distinct counts and reasons', () => {
    const empty = ui.renderStock({ ...available, available_stock: 0, stock_state: 'out_of_stock', stock_message: 'No stock available.' });
    assert.ok(empty.includes('<strong>0</strong>'));
    assert.ok(empty.includes('out of stock'));
    assert.ok(empty.includes('No stock available.'));
    const short = ui.renderStock({ ...available, available_stock: 1, stock_state: 'insufficient', stock_message: 'Need 1 more.' });
    assert.ok(short.includes('available · short'));
    assert.ok(short.includes('Need 1 more.'));
    const unknown = ui.renderStock({ ...available, available_stock: null, stock_state: 'unavailable' });
    assert.ok(unknown.includes('Unavailable'));
    assert.equal(unknown.includes('<strong>0</strong>'), false);
    assert.equal(ui.money(2976), '₱2,976.00');
});

test('status changes reset paging, refresh/retry keep it, and mobile sorting sends table ordering', () => {
    const h = screen();
    h.ids['received-orders-status'].handlers.change();
    h.ids['received-orders-refresh'].handlers.click();
    h.ids['received-orders-retry'].handlers.click();
    h.events.online();
    assert.deepEqual(h.reloads.map(call => call[1]), [true, false, false, false]);
    h.ids['received-orders-sort'].value = 'total';
    h.ids['received-orders-sort'].handlers.change();
    assert.deepEqual(h.orders, [[[6, 'desc']]]);
    assert.deepEqual(h.draws, [undefined]);
    h.events['order.dt']();
    assert.equal(h.ids['received-orders-sort'].value, 'total');
});

test('a successful fulfilment submits for Dealer verification with CSRF and refreshes stock on the current page', async () => {
    const h = screen();
    await h.controller.fulfil(h.buttons[0]);
    assert.equal(h.posts.length, 1);
    assert.equal(h.posts[0].url, '/mega-dealer/orders/9/fulfil');
    assert.equal(h.posts[0].method, 'POST');
    assert.equal(h.posts[0].credentials, 'same-origin');
    assert.equal(h.posts[0].headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    assert.deepEqual(JSON.parse(h.posts[0].body), { remarks: 'Collected by Dealer' });
    assert.deepEqual(h.reloads, [[null, false]]);
    assert.equal(h.alerts.at(-1).icon, 'success');
    assert.equal(h.alerts.at(-1).title, 'Submitted for verification');
    assert.equal(h.alerts[0].inputLabel, 'Fulfilment remarks');
    assert.equal(h.alerts[0].confirmButtonText, 'Submit for verification');
    assert.equal(h.alerts[0].inputAttributes.maxlength, '255');
    assert.equal(h.buttons[0].disabled, true, 'Cannot click while refreshed stock is loading');
    h.loaded();
    assert.equal(h.buttons[0].disabled, false);
    assert.equal(h.buttons[1].disabled, true);
});

test('cancelled confirmation and stock-disabled actions never submit fulfilment', async () => {
    const h = screen({ confirm: async () => ({ isConfirmed: false }) });
    await h.controller.fulfil(h.buttons[0]);
    await h.controller.fulfil(h.buttons[1]);
    assert.equal(h.posts.length, 0);
    assert.equal(h.reloads.length, 0);
    assert.equal(h.buttons[0].disabled, false);
});

test('a busy order cannot be submitted twice, even while the confirmation is open', async () => {
    let resolve;
    const confirmation = new Promise(done => { resolve = done; });
    const h = screen({ confirm: () => confirmation });
    const first = h.controller.fulfil(h.buttons[0]);
    assert.equal(h.buttons[0].disabled, true);
    assert.equal(h.buttons[0].attributes['aria-busy'], 'true');
    await h.controller.fulfil(h.buttons[0]);
    resolve({ isConfirmed: true, value: 'Delivered' });
    await first;
    assert.equal(h.posts.length, 1);
});

test('a stale stock rejection shows its validation reason and reloads stock on the same page', async () => {
    const h = screen({ fetch: async () => ({ ok: false, status: 422,
        text: async () => JSON.stringify({ message: 'The given data was invalid.', errors: { stock: ['Only 1 unit remains; 2 requested.'] } }) }) });
    await h.controller.fulfil(h.buttons[0]);
    assert.equal(h.alerts.at(-1).text, 'Only 1 unit remains; 2 requested.');
    assert.equal(h.alerts.at(-1).icon, 'error');
    assert.deepEqual(h.reloads, [[null, false]]);
});

test('failed table loading keeps stale actions disabled until a fresh successful response', async () => {
    const h = screen();
    h.events['processing.dt']({}, {}, true);
    assert.equal(h.buttons[0].disabled, true);
    assert.equal(h.node.attributes['aria-busy'], 'true');
    assert.equal(h.events['xhr.dt']({}, {}, null, { status: 0 }), true);
    h.events['processing.dt']({}, {}, false);
    assert.equal(h.ids['received-orders-error'].hidden, false);
    assert.match(h.ids['received-orders-error-message'].textContent, /Fulfilment is paused/);
    await h.controller.fulfil(h.buttons[0]);
    assert.equal(h.posts.length, 0);
    h.loaded();
    assert.equal(h.ids['received-orders-error'].hidden, true);
    assert.equal(h.ids['received-orders-count'].textContent, '30 orders');
    assert.equal(h.buttons[0].disabled, false);
});

test('a response from an older draw cannot clear the latest table failure', () => {
    const h = screen();
    h.events['xhr.dt']({}, {}, null, { status: 500 });
    h.events['xhr.dt']({}, { iDraw: 7 }, { draw: 6, recordsTotal: 99 }, { status: 200 });
    assert.equal(h.ids['received-orders-error'].hidden, false);
    assert.equal(h.ids['received-orders-count'].textContent, 'Orders unavailable');
});

test('session expiry, non-JSON responses and missing success or verification status cannot report successful submission', async () => {
    for (const response of [
        { ok: false, status: 419, text: async () => '{"message":"CSRF token mismatch."}' },
        { ok: true, status: 200, text: async () => '<html>Sign in</html>' },
        { ok: true, status: 200, text: async () => '{"message":"Unknown response"}' },
        { ok: true, status: 200, text: async () => '{"success":true}' },
        { ok: true, status: 200, text: async () => '{"success":true,"status":"Pending"}' }
    ]) {
        const h = screen({ fetch: async () => response });
        await h.controller.fulfil(h.buttons[0]);
        assert.equal(h.alerts.at(-1).icon, 'error');
        assert.deepEqual(h.reloads, [[null, false]]);
    }
});

test('a repeated fulfil request after Dealer receipt reports the completed state without claiming another submission', async () => {
    const h = screen({ fetch: async () => ({ ok: true, status: 200,
        text: async () => '{"success":true,"status":"Completed","message":"Dealer already confirmed receipt."}' }) });
    await h.controller.fulfil(h.buttons[0]);
    assert.equal(h.alerts.at(-1).title, 'Order already completed');
    assert.equal(h.alerts.at(-1).icon, 'success');
    assert.deepEqual(h.reloads, [[null, false]]);
});

test('missing CSRF and invalid remarks prevent requests before stock can be changed', async () => {
    const noSession = screen({ noCsrf: true });
    await noSession.controller.fulfil(noSession.buttons[0]);
    assert.equal(noSession.posts.length, 0);
    assert.match(noSession.alerts.at(-1).text, /secure session/);
    const blank = screen({ confirm: async alert => {
        assert.equal(alert.preConfirm('  '), false);
        assert.equal(alert.preConfirm('x'.repeat(256)), false);
        return { isConfirmed: true, value: ' ' };
    } });
    await blank.controller.fulfil(blank.buttons[0]);
    assert.equal(blank.posts.length, 0);
    assert.equal(blank.buttons[0].disabled, false);
});

test('lost network responses also recheck order status before another fulfilment attempt', async () => {
    const h = screen({ fetch: async () => { throw new TypeError('Failed to fetch'); } });
    await h.controller.fulfil(h.buttons[0]);
    assert.equal(h.alerts.at(-1).icon, 'error');
    assert.deepEqual(h.reloads, [[null, false]]);
    assert.equal(h.buttons[0].disabled, true);
});

test('removing the last order on a filtered page moves to the last valid page', () => {
    const h = screen({ page: { recordsDisplay: 10, start: 10 } });
    h.settings.drawCallback.call({ api: () => h.api });
    assert.deepEqual(h.pageChanges, ['last']);
    assert.deepEqual(h.draws, ['page']);
});

test('mounting again does not bind another handler or create another DataTable', () => {
    const h = screen();
    assert.equal(ui.mount(h.env), h.controller);
});
