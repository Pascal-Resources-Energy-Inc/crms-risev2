const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const home = fs.readFileSync(path.join(__dirname, '../../resources/views/home.blade.php'), 'utf8');
const listener = home.slice(home.indexOf("        document.querySelectorAll('.complete-btn')"),
    home.indexOf("        document.querySelectorAll('.cancel-order-btn')"));

function screen(options = {}) {
    const calls = [], dialogs = [], attributes = new Map();
    const complete = { disabled: false, dataset: { id: '17', verification: options.legacy ? 'false' : 'true', maxRemarks: options.legacy ? '500' : '255' },
        addEventListener(event, callback) { this.click = callback; },
        setAttribute(name, value) { attributes.set(name, value); },
        removeAttribute(name) { attributes.delete(name); },
        remove() { this.removed = true; } };
    const cancel = { disabled: false };
    const initialStatus = options.legacy ? 'For Delivery' : 'For Verification';
    const badge = { innerHTML: initialStatus };
    const actions = { innerHTML: 'Mark Completed / Cancel Order', querySelectorAll() { return [complete, cancel]; } };
    const context = vm.createContext({
        baseUrl: 'https://example.test/portal',
        document: {
            querySelectorAll() { return [complete]; },
            querySelector() { return options.noCsrf ? null : { getAttribute() { return 'synthetic-token'; } }; },
            getElementById(id) { return id === 'status-badge-17' ? badge : id === 'order-actions-17' ? actions : null; }
        },
        Swal: {
            async fire(...args) {
                dialogs.push(args);
                if (args[0]?.input === 'textarea') {
                    return options.confirm ? options.confirm() : { isConfirmed: !options.cancel, value: 'Received from Mega Dealer' };
                }
                return {};
            },
            showValidationMessage() {}
        },
        async fetch(url, settings) {
            calls.push({ url, settings });
            if (options.failure) throw new Error('Connection lost');
            return { ok: !options.status || options.status < 400, status: options.status || 200,
                async json() {
                    if (options.invalidJson) throw new Error('Invalid JSON');
                    return options.response || { success: true, status: 'Completed', message: 'Order completed successfully.' };
                } };
        }
    });
    vm.runInContext(listener, context);
    return { complete, cancel, actions, badge, initialStatus, calls, dialogs, attributes, click: () => complete.click.call(complete) };
}

test('Dealer completion sends secure remarks and clears all pending order actions after confirmed success', async () => {
    const h = screen(); await h.click();
    assert.equal(h.calls.length, 1);
    assert.equal(h.calls[0].url, 'https://example.test/portal/ad-orders/17/complete');
    const request = h.calls[0].settings;
    assert.equal(request.method, 'POST'); assert.equal(request.credentials, 'same-origin');
    assert.equal(request.headers['X-CSRF-TOKEN'], 'synthetic-token');
    assert.deepEqual(JSON.parse(request.body), { remarks: 'Received from Mega Dealer' });
    assert.match(h.badge.innerHTML, /Completed/); assert.equal(h.actions.innerHTML, '');
    assert.equal(h.attributes.has('aria-busy'), false);
    assert.equal(h.dialogs[0][0].title, 'Confirm order received');
    assert.equal(h.dialogs[0][0].inputAttributes.maxlength, 255);
});

test('rejected receipt keeps the Dealer order For Verification and restores its action', async () => {
    const h = screen({ status: 422, response: { success: false, message: 'This order has an invalid quantity.' } });
    await h.click();
    assert.equal(h.badge.innerHTML, 'For Verification'); assert.notEqual(h.actions.innerHTML, '');
    assert.equal(h.complete.disabled, false); assert.equal(h.cancel.disabled, false);
    assert.equal(h.dialogs.at(-1)[1], 'This order has an invalid quantity.');
});

test('confirmation blocks duplicate completion and cancellation clicks while pending', async () => {
    let confirm;
    const h = screen({ confirm: () => new Promise(resolve => { confirm = resolve; }) });
    const first = h.click();
    assert.equal(h.complete.disabled, true); assert.equal(h.cancel.disabled, true);
    assert.equal(h.attributes.get('aria-busy'), 'true');
    await h.click(); assert.equal(h.dialogs.length, 1);
    confirm({ isConfirmed: true, value: 'Received once' }); await first;
    assert.equal(h.calls.length, 1);
});

test('cancelled confirmation and absent CSRF cannot complete the order', async () => {
    for (const options of [{ cancel: true }, { noCsrf: true }]) {
        const h = screen(options); await h.click();
        assert.equal(h.calls.length, 0); assert.equal(h.badge.innerHTML, 'For Verification');
        assert.equal(h.complete.disabled, false); assert.equal(h.cancel.disabled, false);
    }
});

test('invalid or failed server responses never display a completed Dealer order', async () => {
    for (const options of [{ invalidJson: true }, { response: { success: false } }, { response: {} },
        { response: { success: true, status: 'For Verification' } }, { status: 419 }, { failure: true }]) {
        const h = screen(options); await h.click();
        assert.equal(h.badge.innerHTML, 'For Verification'); assert.notEqual(h.actions.innerHTML, '');
        assert.equal(h.complete.disabled, false); assert.equal(h.cancel.disabled, false);
        assert.equal(h.dialogs.at(-1)[0], 'Error');
    }
});

test('existing AD completion remains compatible with its original response and remarks limit', async () => {
    const h = screen({ legacy: true, response: { success: true } }); await h.click();
    assert.equal(h.calls.length, 1); assert.match(h.badge.innerHTML, /Completed/);
    assert.equal(h.dialogs[0][0].title, 'Completion Remarks');
    assert.equal(h.dialogs[0][0].inputAttributes.maxlength, 500);
});
