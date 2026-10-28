(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else { root.OfflineCheckout = api; const start = () => api.mount(root); if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', start, { once: true }); else start(); }
})(typeof window !== 'undefined' ? window : this, function () {
    'use strict';
    const money = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value);
    function reference(crypto) {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16)); bytes[6] = bytes[6] & 15 | 64; bytes[8] = bytes[8] & 63 | 128;
        const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }
    function read(storage, key, fallback) { const raw = storage.getItem(key); return raw === null ? fallback : JSON.parse(raw); }
    function sameContext(a, b) { return ['user_id', 'type', 'ad_id', 'client_id', 'retry_key'].every(key => String(a[key] || '') === String(b[key] || '')); }
    function mount(win) {
        const doc = win.document, api = win.OfflineData, byId = id => doc.getElementById(id), cartPage = !!byId('cartItems');
        const notice = message => { byId('checkoutNotice').textContent = message; };
        const node = (tag, className, text) => { const element = doc.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; };
        const action = byId(cartPage ? 'reviewOrder' : 'saveOrder');
        let context, cart, snapshot, draft, saving = false, saved = false, ready = false, generation = 0, savedGeneration = 0, savedRow = null, quantityInputs = [];
        async function showSavedState() {
            if (!saved || !draft) return;
            const current = ++savedGeneration;
            let row = savedRow;
            try {
                const rows = typeof api.listOrders === 'function' ? await api.listOrders() : [];
                if (current !== savedGeneration) return;
                row = rows.find(entry => entry.idempotency_key === draft.idempotency_key && Number(entry.user_id) === Number(draft.user_id)) || savedRow;
            } catch (_) { if (current !== savedGeneration) return; }
            savedRow = row;
            action.disabled = true;
            const link = byId('pendingLink'); link.hidden = false; link.textContent = 'View order history';
            if (row && row.status === 'synced') {
                action.textContent = 'Saved and synced';
                notice(draft.type === 'order' ? 'Your order was submitted successfully. Stock, payment and fulfilment remain subject to confirmation. Check its progress in order history.' : 'Your transaction was synced successfully. View transaction history for its latest status.');
            } else if (row && row.status === 'needs_review') {
                action.textContent = 'Saved · Needs review';
                notice('Your saved order needs review before it can sync. ' + (row.last_error || 'Open order history to review the latest details.'));
            } else {
                action.textContent = 'Saved on this device';
                notice('Your order is saved and pending sync. It will sync automatically when a connection is available. You can track it in order history.');
            }
        }
        function currentCart() {
            const latest = read(win.localStorage, 'offlineCartContext', {}), items = read(win.localStorage, 'dealerCartData', []);
            if (!sameContext(latest, context) || JSON.stringify(items) !== JSON.stringify(cart)) throw new Error('This cart changed in another tab. Reload the cart to review its latest version.');
        }
        function savePreferences() {
            if (!cartPage || !ready) return;
            currentCart();
            const next = Object.assign({}, context, { payment_method: byId('paymentMethod').value, delivery_type: byId('deliveryType').value });
            win.localStorage.setItem('offlineCartContext', JSON.stringify(next)); context = next;
        }
        function storeCart(items) {
            currentCart();
            const previous = win.localStorage.getItem('dealerCartData');
            try {
                win.localStorage.setItem('dealerCartData', JSON.stringify(items));
                win.localStorage.setItem('dealerCartItems', String(items.reduce((sum, item) => sum + Number(item.quantity), 0)));
                win.localStorage.removeItem('dealerOrderData'); cart = items;
            } catch (error) {
                try { if (previous !== null) win.localStorage.setItem('dealerCartData', previous); } catch (_) {}
                throw new Error('Your cart could not be saved on this device. The previous cart is kept.');
            }
        }
        function summary() {
            const total = cart.reduce((sum, item) => sum + Number(item.price ?? item.unit_price) * Number(item.quantity), 0);
            byId('cartTotal').textContent = money(total);
            const recipients = context.type === 'order' ? snapshot.distributors : snapshot.customers;
            const recipient = recipients.find(row => String(row.id) === String(context.type === 'order' ? context.ad_id : context.client_id));
            byId('recipientLabel').textContent = context.type === 'order' ? 'Area Distributor' : 'Customer';
            byId('recipientName').textContent = recipient ? recipient.display_name || recipient.name : 'Choose a recipient in Products';
            byId('recipientDetails').textContent = recipient ? recipient.address || recipient.sales_territory || '' : '';
        }
        function hasQuantityEdits() {
            return quantityInputs.some(({ input, item }) => String(input.value).trim() !== String(item.quantity));
        }
        function renderItems() {
            const holder = byId(cartPage ? 'cartItems' : 'confirmationItems'); holder.replaceChildren();
            quantityInputs = [];
            cart.forEach((item, index) => {
                const row = node('article', 'item'), details = node('div');
                details.append(node('h3', '', item.name || 'Saved product'), node('p', 'muted', money(Number(item.price ?? item.unit_price)) + ' each'));
                if (cartPage) {
                    const controls = node('div', 'quantity'), minus = node('button', 'button', '−'), plus = node('button', 'button', '+'), input = node('input');
                    minus.type = plus.type = 'button'; input.type = 'number'; input.min = 1; input.max = 1000000; input.step = 1; input.value = item.quantity; input.setAttribute('aria-label', (item.name || 'Product') + ' quantity');
                    quantityInputs.push({ input, item });
                    input.oninput = () => { action.disabled = true; };
                    minus.setAttribute('aria-label', 'Decrease ' + item.name); plus.setAttribute('aria-label', 'Increase ' + item.name);
                    const change = value => {
                        try {
                            const next = Object.assign({}, item, { quantity: Number(value) });
                            api.validateOrder(snapshot, context, [next]);
                            const items = cart.slice(); items[index] = next; storeCart(items); renderItems(); validate();
                        } catch (error) { input.value = item.quantity; notice(error.message); }
                    };
                    minus.onclick = () => change(Number(item.quantity) - 1); plus.onclick = () => change(Number(item.quantity) + 1); input.onchange = () => change(input.value);
                    controls.append(minus, input, plus); details.append(controls);
                    const remove = node('button', 'button danger', 'Remove'); remove.type = 'button'; remove.onclick = () => { try { storeCart(cart.filter((_, itemIndex) => itemIndex !== index)); renderItems(); validate(); } catch (error) { notice(error.message); } };
                    row.append(details, remove);
                } else row.append(details, node('strong', '', '× ' + item.quantity));
                holder.append(row);
            });
            if (!cart.length) holder.append(node('p', 'muted', 'Your cart is empty. Add products to continue.'));
            if (snapshot) summary();
        }
        function validate(showUpdated) {
            action.disabled = true;
            if (!snapshot || !ready || saving || saved) return;
            try {
                api.validateOrder(snapshot, context, cart);
                action.disabled = false;
                if (showUpdated) notice('Latest data loaded. Your saved prices and selections are kept. Review the order before continuing.');
                else notice(context.type === 'order' ? 'AD orders can be saved even when the AD is out of stock. Supply and fulfilment remain subject to confirmation.' : 'This sale uses available saved stock. Prices and stock are checked again when you sync.');
            } catch (error) { notice(error.message + ' Open Products to review your cart.'); }
        }
        async function refresh(showUpdated) {
            if (!ready || saving || saved) return;
            const current = ++generation; action.disabled = true;
            try {
                const latest = await api.getSnapshot(); if (current !== generation || saving || saved) return;
                if (!latest) throw new Error('No account data has been saved yet. Connect and refresh offline data.');
                snapshot = latest;
                if (Number(snapshot.user.id) !== Number(context.user_id)) throw new Error('This cart belongs to another account. Open Products for your current account.');
                // A reconnect snapshot may arrive before a typed quantity blurs.
                // Keep that edit visible until its normal change handler saves it.
                if (!cartPage || !hasQuantityEdits()) renderItems(); else summary();
                validate(showUpdated);
                if (cartPage && hasQuantityEdits()) action.disabled = true;
            } catch (error) { action.disabled = true; notice(error.message); }
        }
        async function initialize() {
            try {
                if (!api) throw new Error('Offline data is unavailable. Connect and update the offline version.');
                context = read(win.localStorage, cartPage ? 'offlineCartContext' : 'dealerOrderData', null);
                if (!context) throw new Error('Open Products to start a cart before continuing.');
                draft = cartPage ? null : context; cart = cartPage ? read(win.localStorage, 'dealerCartData', []) : draft.items;
                if (!Array.isArray(cart)) throw new Error('This cart uses an older version. Open Products and choose New cart.');
                if (cartPage) {
                    byId('paymentMethod').value = ['cash', 'gcash', 'bank_transfer', 'credit'].includes(context.payment_method) ? context.payment_method : 'cash';
                    byId('deliveryType').value = context.delivery_type === 'delivery' ? 'delivery' : 'pickup';
                    byId('paymentMethod').onchange = byId('deliveryType').onchange = () => { try { savePreferences(); } catch (error) { notice(error.message); } };
                } else {
                    byId('paymentSummary').textContent = 'Payment: ' + String(draft.payment_method || '').replace(/_/g, ' ') + ' · Not collected offline';
                    byId('deliverySummary').textContent = 'Fulfilment: ' + String(draft.delivery_type || '');
                }
                ready = true; renderItems(); await refresh(false);
            } catch (error) { notice(error.message); }
        }
        action.onclick = async () => {
            if (!ready || saving || saved || action.disabled) return;
            if (cartPage) {
                try {
                    savePreferences(); snapshot = await api.getSnapshot(); api.validateOrder(snapshot, context, cart);
                    const next = Object.assign({}, context, { idempotency_key: context.retry_key || reference(win.crypto), items: cart, snapshot_saved_at: snapshot.saved_at });
                    win.localStorage.setItem('dealerOrderData', JSON.stringify(next)); win.location.href = 'confirm_order.html';
                } catch (error) { action.disabled = true; notice(error.message); }
            } else {
                saving = true; generation++; action.disabled = true;
                try {
                    const queued = await api.queueOrder(draft); saved = true;
                    if (queued && queued.idempotency_key === draft.idempotency_key && Number(queued.user_id) === Number(draft.user_id)) savedRow = queued;
                    // Clear only the exact draft this page saved; a newer cart in
                    // another tab must survive the acknowledgement.
                    const latestDraft = read(win.localStorage, 'dealerOrderData', {}), latestContext = read(win.localStorage, 'offlineCartContext', {});
                    if (latestDraft.idempotency_key === draft.idempotency_key && sameContext(latestContext, draft) && JSON.stringify(read(win.localStorage, 'dealerCartData', [])) === JSON.stringify(cart)) {
                        ['dealerOrderData', 'dealerCartData', 'dealerCartItems', 'offlineCartContext'].forEach(key => win.localStorage.removeItem(key));
                    }
                    await showSavedState();
                } catch (error) {
                    if (saved) { await showSavedState(); notice(byId('checkoutNotice').textContent + ' The saved cart could not be cleared. Check order history before creating another order.'); }
                    else { saving = false; await refresh(false); notice(error.message); }
                } finally { saving = false; }
            }
        };
        const updated = () => saved ? showSavedState() : refresh(true);
        win.addEventListener('offline-data-updated', updated);
        win.addEventListener('offline-outbox-updated', updated);
        win.addEventListener('offline-before-update', event => {
            if (saving) { event.preventDefault(); notice('Wait for your pending order to finish saving before applying the update.'); return; }
            if (cartPage && hasQuantityEdits()) { event.preventDefault(); return; }
            if (!cartPage && saved && event.detail && event.detail.automatic) {
                // The completed draft is deliberately removed after its durable
                // save. Reopen its history instead of an empty confirmation.
                event.detail.url = new URL('transaction.html', win.location.href).href;
                return;
            }
            try { savePreferences(); } catch (error) { event.preventDefault(); notice(error.message); }
        });
        initialize();
        return { refresh: () => refresh(true) };
    }
    return { mount, reference, sameContext };
});
