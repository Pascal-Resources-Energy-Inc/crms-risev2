const assert = require('node:assert/strict');

class Element {
    constructor(tag = 'div') { this.tagName = tag; this.children = []; this.value = ''; this.disabled = false; this.hidden = false; this.attributes = {}; this._text = ''; this.open = false; }
    set textContent(value) { this._text = String(value); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent || '').join(' '); }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this._text = ''; this.children = children; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    focus() { this.focused = true; }
    showModal() { this.open = true; }
    close() { this.open = false; }
}

function snapshot() {
    return { schema_version: 1, saved_at: '2026-10-28T00:00:00Z', user: { id: 42, role: 'mega_dealer' }, settings: { can_order: true, can_transact: true },
        customers: [{ id: 100, display_name: 'Assigned Customer' }], distributors: [{ id: 200, user_id: 900, name: 'Assigned AD' }],
        products: [{ id: 10, name: 'Refill', sku: 'REFILL', price: 70, order_price: 50, dealer_stock: 3, ad_stock: 0, ad_id: 200, client_allowed: true },
            { id: 11, name: 'Cylinder', price: 150, order_price: 130, dealer_stock: 0, ad_stock: 0, ad_id: 200, client_allowed: true }] };
}

function validateOrder(saved, context, items) {
    if (!saved || !context || Number(context.user_id) !== Number(saved.user.id)) throw new Error('This cart belongs to another account.');
    const recipient = (context.type === 'order' ? saved.distributors : saved.customers).find(row => String(row.id) === String(context.type === 'order' ? context.ad_id : context.client_id));
    if (!recipient) throw new Error('Select a customer or Area Distributor.');
    if (!items.length) throw new Error('Your cart is empty.');
    const checked = items.map(item => {
        const product = saved.products.find(row => String(row.id) === String(item.product_id || item.id));
        if (!product) throw new Error('Product unavailable.');
        const quantity = Number(item.quantity), price = Number(context.type === 'order' ? product.order_price : product.price);
        if (!Number.isSafeInteger(quantity) || quantity < 1 || quantity > 1000000) throw new Error('Invalid quantity.');
        if (context.type === 'client' && (!product.client_allowed || quantity > product.dealer_stock)) throw new Error('Quantity exceeds available stock.');
        if (context.type === 'order' && String(product.ad_id) !== String(context.ad_id)) throw new Error('Wrong distributor.');
        if (Number(item.unit_price ?? item.price) !== price) throw new Error(product.name + ': price changed.');
        return { ...item, unit_price: price, quantity };
    });
    return { items: checked, recipient, total: checked.reduce((sum, item) => sum + item.quantity * item.unit_price, 0) };
}

function harness(ids, initialStorage = {}, initialSnapshot = snapshot()) {
    const nodes = new Map(ids.map(id => [id, new Element()]));
    const values = new Map(Object.entries(initialStorage).map(([key, value]) => [key, typeof value === 'string' ? value : JSON.stringify(value)]));
    const events = new Map(), queued = [];
    let saved = initialSnapshot;
    const storage = { failKey: null,
        getItem: key => values.has(key) ? values.get(key) : null,
        setItem(key, value) { if (this.failKey === key) throw new Error('Storage full'); values.set(key, String(value)); },
        removeItem: key => values.delete(key)
    };
    const api = { identity: () => ({ id: 42, role: 'mega_dealer' }), getSnapshot: async () => structuredClone(saved), validateOrder,
        async refresh() {}, async queueOrder(order) { queued.push(structuredClone(order)); return order; } };
    const win = {
        document: { getElementById: id => nodes.get(id) || null, createElement: tag => new Element(tag) },
        localStorage: storage, location: { href: 'https://example.test/app/offline/products.html' },
        OfflineData: api, confirm: () => true,
        crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000001' },
        addEventListener(name, handler) { if (!events.has(name)) events.set(name, []); events.get(name).push(handler); }
    };
    return { win, nodes, values, storage, queued, api, setSnapshot: value => { saved = value; },
        async event(name, value = {}) { await Promise.all((events.get(name) || []).map(handler => handler(value))); },
        parsed: key => JSON.parse(values.get(key)), node: id => { assert.ok(nodes.has(id), id); return nodes.get(id); }
    };
}
const settle = () => new Promise(resolve => setImmediate(resolve));
const catalogIds = 'catalogNotice cartCount clientMode orderMode customerField distributorField customerSelect distributorSelect snapshotTime productGrid productSearch quantityTitle quantityAvailability productQuantity saveQuantity removeItem quantityError quantityTotal quantityDialog refreshData startNewCart viewCart cancelQuantity quantityForm'.split(' ');
const cartIds = 'checkoutNotice cartItems recipientLabel recipientName recipientDetails cartTotal reviewOrder paymentMethod deliveryType'.split(' ');
const confirmationIds = 'checkoutNotice confirmationItems recipientLabel recipientName recipientDetails cartTotal saveOrder paymentSummary deliverySummary pendingLink'.split(' ');
const context = overrides => ({ user_id: 42, type: 'order', ad_id: 200, client_id: null, ...overrides });
const item = overrides => ({ id: 10, product_id: 10, name: 'Refill', price: 50, unit_price: 50, quantity: 20, ...overrides });

module.exports = { harness, snapshot, settle, catalogIds, cartIds, confirmationIds, context, item };
