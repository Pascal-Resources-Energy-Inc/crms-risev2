(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else { root.OfflineCatalog = api; const start = () => api.mount(root); if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', start, { once: true }); else start(); }
})(typeof window !== 'undefined' ? window : this, function () {
    'use strict';
    const MAX_QUANTITY = 1000000;
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
    const idOf = item => String(item.product_id || item.id);
    function itemFor(product, type, quantity) {
        const count = Number(quantity), rawPrice = type === 'order' ? product.order_price : product.price;
        if (!Number.isSafeInteger(count) || count < 1 || count > MAX_QUANTITY) throw new Error('Enter a whole quantity from 1 to 1,000,000.');
        if (type === 'client' && (!product.client_allowed || !Number.isFinite(Number(product.dealer_stock)) || count > Number(product.dealer_stock))) throw new Error('Quantity exceeds the available saved stock for client transactions.');
        if (rawPrice === null || rawPrice === '' || !Number.isFinite(Number(rawPrice)) || Number(rawPrice) < 0) throw new Error('This product does not have an available price. Refresh saved data.');
        return { id: product.id, product_id: product.id, name: product.name, description: product.description || '', sku: product.sku || '', image: product.image || null, quantity: count, price: Number(rawPrice), unit_price: Number(rawPrice) };
    }
    function productsFor(snapshot, context, query) {
        return snapshot.products.filter(product => (context.type === 'order' ? String(product.ad_id) === String(context.ad_id) : product.client_allowed) && (String(product.name || '') + ' ' + String(product.sku || '')).toLowerCase().includes(String(query || '').toLowerCase().trim()));
    }
    function read(storage, key, fallback) { const raw = storage.getItem(key); return raw === null ? fallback : JSON.parse(raw); }
    function commit(storage, changes) {
        const before = {};
        Object.keys(changes).forEach(key => { before[key] = storage.getItem(key); });
        try { Object.entries(changes).forEach(([key, value]) => value === null ? storage.removeItem(key) : storage.setItem(key, value)); }
        catch (error) {
            Object.entries(before).forEach(([key, value]) => { try { value === null ? storage.removeItem(key) : storage.setItem(key, value); } catch (_) {} });
            throw new Error('Unable to save your cart on this device. Your previous cart is kept.');
        }
    }
    function mount(win) {
        const doc = win.document, api = win.OfflineData, byId = id => doc.getElementById(id);
        const notice = message => { byId('catalogNotice').textContent = message || ''; byId('catalogNotice').hidden = !message; };
        const node = (tag, className, text) => { const element = doc.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; };
        let snapshot = null, context = null, cart = [], locked = false, editing = null, generation = 0;
        const viewer = api && api.identity();
        if (!api || !viewer) {
            const message = api ? 'Sign in online before preparing the offline catalog.' : 'The offline data files are not ready. Connect and update the offline version before creating an order.';
            notice(message); byId('productGrid').replaceChildren(node('p', 'empty', message));
            ['clientMode', 'orderMode', 'customerSelect', 'distributorSelect', 'refreshData', 'startNewCart'].forEach(id => { byId(id).disabled = true; });
            return;
        }
        function badge() { byId('cartCount').textContent = String(cart.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0)); }
        function saveCart(items, next) {
            try {
                commit(win.localStorage, { dealerCartData: JSON.stringify(items), dealerCartItems: String(items.reduce((sum, item) => sum + Number(item.quantity), 0)), dealerCartTotal: String(items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.price), 0)), offlineCartContext: JSON.stringify(next), dealerOrderData: null });
                cart = items; context = next; locked = false; badge(); return true;
            } catch (error) { notice(error.message); return false; }
        }
        function initialContext() { return { user_id: Number(viewer.id), type: snapshot.settings.can_transact ? 'client' : 'order', client_id: null, ad_id: null, snapshot_saved_at: snapshot.saved_at }; }
        function initializeCart() {
            try {
                const savedCart = read(win.localStorage, 'dealerCartData', []), savedContext = read(win.localStorage, 'offlineCartContext', null);
                if (!Array.isArray(savedCart)) throw new Error('The existing cart uses an older format. Choose New cart to replace it when ready.');
                cart = savedCart;
                if ((savedContext && Number(savedContext.user_id) !== Number(viewer.id)) || (cart.length && !savedContext)) throw new Error('The existing cart belongs to another account or an older version. Choose New cart to replace it when ready.');
                context = savedContext || initialContext();
                if (!['order', 'client'].includes(context.type)) throw new Error('The existing cart has no transaction type. Choose New cart to replace it when ready.');
            } catch (error) { locked = true; context = initialContext(); notice(error.message || 'The current cart cannot be read. Choose New cart to replace it when ready.'); }
            badge();
        }
        function recipients(select, values, empty, current) {
            select.replaceChildren(); const placeholder = node('option', '', empty); placeholder.value = ''; select.append(placeholder);
            values.forEach(recipient => { const option = node('option', '', recipient.display_name || recipient.name || 'Unnamed account'); option.value = recipient.id; select.append(option); });
            select.value = current === null || current === undefined ? '' : String(current);
        }
        function controls() {
            if (!snapshot || !context) return;
            byId('clientMode').disabled = locked || !snapshot.settings.can_transact; byId('orderMode').disabled = locked || !snapshot.settings.can_order;
            byId('clientMode').checked = context.type === 'client'; byId('orderMode').checked = context.type === 'order';
            byId('customerField').hidden = context.type !== 'client'; byId('distributorField').hidden = context.type !== 'order';
            recipients(byId('customerSelect'), snapshot.customers, 'Select customer', context.client_id); recipients(byId('distributorSelect'), snapshot.distributors, 'Select Area Distributor', context.ad_id);
            byId('customerSelect').disabled = locked || !snapshot.settings.can_transact; byId('distributorSelect').disabled = locked || !snapshot.settings.can_order;
            byId('snapshotTime').textContent = 'Last saved: ' + new Date(snapshot.saved_at).toLocaleString(); badge();
        }
        function choose(next) {
            if (locked) return;
            const changed = ['type', 'client_id', 'ad_id'].some(key => String(context[key] || '') !== String(next[key] || ''));
            if (changed && cart.length && !win.confirm('Changing the transaction or recipient replaces this unsent cart. Pending saved transactions are kept. Continue?')) { controls(); return; }
            if (saveCart(changed ? [] : cart, next)) { notice(''); controls(); render(); }
        }
        function render() {
            const grid = byId('productGrid'); grid.replaceChildren();
            if (!snapshot || !context) { grid.append(node('p', 'empty', 'No saved catalog yet. Connect, sign in online, and refresh saved data.')); return; }
            if (!snapshot.settings[context.type === 'order' ? 'can_order' : 'can_transact']) { grid.append(node('p', 'empty', 'This transaction is unavailable for your account.')); return; }
            if (context.type === 'order' && !context.ad_id) { grid.append(node('p', 'empty', snapshot.distributors.length ? 'Select an Area Distributor to see products you can order.' : 'No Area Distributor is assigned to your saved sales territory. Refresh while connected.')); return; }
            const products = productsFor(snapshot, context, byId('productSearch').value);
            products.forEach(product => {
                const card = node('article', 'product'), image = node('div', 'image', 'No image saved');
                if (product.image) { const img = node('img'); try { const url = new URL(product.image, win.location.href); if (['http:', 'https:'].includes(url.protocol)) { img.src = url.href; img.alt = product.name; img.loading = 'lazy'; img.onerror = () => image.replaceChildren(node('span', '', 'No image saved')); image.replaceChildren(img); } } catch (_) {} }
                const order = context.type === 'order', available = Number(order ? product.ad_stock : product.dealer_stock) || 0;
                const stock = node('div', 'stock' + (order && available < 1 ? ' backorder' : ''), order ? (available > 0 ? 'AD stock: ' + available + ' · Orders accepted' : 'Out of stock · Available to order') : 'Available stock: ' + available);
                const rawPrice = order ? product.order_price : product.price, priced = rawPrice !== null && rawPrice !== '' && Number.isFinite(Number(rawPrice)) && Number(rawPrice) >= 0;
                const actions = node('div', 'actions'), button = node('button', 'button primary'); button.type = 'button';
                const existing = cart.find(item => idOf(item) === String(product.id)); button.textContent = existing ? 'Edit · ' + existing.quantity : 'Add'; button.setAttribute('aria-label', (existing ? 'Edit ' : 'Add ') + product.name);
                button.disabled = locked || !priced || (!order && available < 1); button.onclick = () => openQuantity(product);
                actions.append(node('span', 'price', priced ? money(Number(rawPrice)) : 'No price'), button); card.append(image, node('h2', '', product.name), node('p', '', product.description || product.sku || ''), stock, actions); grid.append(card);
            });
            if (!products.length) grid.append(node('p', 'empty', 'No matching products in your saved catalog.'));
        }
        function openQuantity(product) {
            if (locked) return;
            if (!(context.type === 'order' ? context.ad_id : context.client_id)) { notice('Select a customer or Area Distributor before adding products.'); return; }
            editing = product; const existing = cart.find(item => idOf(item) === String(product.id)), order = context.type === 'order';
            byId('quantityTitle').textContent = product.name; byId('quantityAvailability').textContent = order ? 'AD orders can exceed available stock. Your order remains pending until accepted and fulfilled.' : 'Available saved stock: ' + product.dealer_stock;
            byId('productQuantity').max = order ? MAX_QUANTITY : Math.max(1, Number(product.dealer_stock) || 0); byId('productQuantity').value = existing ? existing.quantity : 1;
            byId('saveQuantity').textContent = existing ? 'Update cart item' : 'Add to cart'; byId('removeItem').hidden = !existing; byId('quantityError').hidden = true;
            total(); byId('quantityDialog').showModal(); byId('productQuantity').focus();
        }
        function total() { if (!editing || !context) return; const price = Number(context.type === 'order' ? editing.order_price : editing.price), quantity = Number(byId('productQuantity').value); byId('quantityTotal').textContent = money(Number.isFinite(quantity) ? price * quantity : 0); }
        async function load(initial) {
            const current = ++generation;
            try {
                const saved = await api.getSnapshot(); if (current !== generation) return;
                if (!saved) { render(); notice('Connect and sign in online to prepare the latest products, customers and assigned Area Distributors.'); return; }
                const first = !snapshot; snapshot = saved; if (first) initializeCart(); controls(); render();
                if (!locked && (!initial || first)) notice(cart.length ? 'Saved data updated. Review cart prices and availability before confirming.' : 'Latest products, customers and Area Distributors are saved for offline use.');
            } catch (error) { notice(error.message || 'Unable to read saved data. Your cart and pending transactions are kept.'); }
        }
        byId('clientMode').onchange = () => { if (context) choose(Object.assign({}, context, { type: 'client', client_id: null, ad_id: null })); };
        byId('orderMode').onchange = () => { if (context && snapshot) choose(Object.assign({}, context, { type: 'order', client_id: null, ad_id: snapshot.distributors.length === 1 ? snapshot.distributors[0].id : null })); };
        byId('customerSelect').onchange = () => { if (context) choose(Object.assign({}, context, { client_id: byId('customerSelect').value || null, ad_id: null })); };
        byId('distributorSelect').onchange = () => { if (context) choose(Object.assign({}, context, { ad_id: byId('distributorSelect').value || null, client_id: null })); };
        byId('productSearch').oninput = render;
        byId('refreshData').onclick = async () => { byId('refreshData').disabled = true; try { await api.refresh(); } catch (error) { notice(error.message); } finally { byId('refreshData').disabled = false; } };
        byId('startNewCart').onclick = () => { if (snapshot && win.confirm('Replace the current unsent cart with a new empty cart? Pending transactions are kept.') && saveCart([], initialContext())) { notice('New cart ready. Select the transaction type and recipient.'); controls(); render(); } };
        byId('viewCart').onclick = event => { if (locked) { event.preventDefault(); notice('Choose New cart to replace the existing incompatible cart before continuing.'); } };
        byId('cancelQuantity').onclick = () => byId('quantityDialog').close(); byId('productQuantity').oninput = total;
        byId('removeItem').onclick = () => { if (editing && saveCart(cart.filter(item => idOf(item) !== String(editing.id)), context)) { byId('quantityDialog').close(); render(); } };
        byId('quantityForm').onsubmit = event => {
            event.preventDefault();
            try {
                const latest = snapshot.products.find(product => String(product.id) === String(editing.id)); if (!latest) throw new Error('This product is no longer in your saved catalog. Close this window and refresh.');
                const item = itemFor(latest, context.type, byId('productQuantity').value), items = cart.filter(entry => idOf(entry) !== String(latest.id)).concat(item);
                // Updating one item may leave other items needing price review.
                // Keep those items intact; the complete cart is checked at checkout.
                api.validateOrder(snapshot, context, [item]);
                if (saveCart(items, Object.assign({}, context, { snapshot_saved_at: snapshot.saved_at }))) { byId('quantityDialog').close(); notice('Cart saved on this device.'); render(); }
            } catch (error) { byId('quantityError').textContent = error.message; byId('quantityError').hidden = false; }
        };
        win.addEventListener('offline-before-update', event => {
            // A quantity has not joined the durable cart until its dialog is saved.
            if (byId('quantityDialog').open) event.preventDefault();
        });
        win.addEventListener('offline-data-updated', () => load(false)); win.addEventListener('offline-outbox-updated', () => load(false)); load(true);
        return { reload: () => load(false) };
    }
    return { mount, itemFor, productsFor, commit };
});
