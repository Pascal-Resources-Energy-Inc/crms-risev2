(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.ReceivedDealerOrders = api;
        var start = function () { api.mount(root); };
        if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', start);
        else start();
    }
}(typeof window !== 'undefined' ? window : this, function () {
    'use strict';

    var labels = ['Order', 'Product', 'Requested', 'Available stock', 'Payment', 'Delivery', 'Total', 'Status', 'Action'];
    var statusClasses = { Pending: 'pending', 'For Verification': 'verification', Completed: 'completed', Cancelled: 'cancelled', 'For Delivery': 'delivery' };
    var sorts = { newest: [0, 'desc'], oldest: [0, 'asc'], product: [1, 'asc'], quantity: [2, 'desc'], total: [6, 'desc'] };
    var stockLabels = { available: 'available', insufficient: 'available · short', out_of_stock: 'out of stock', unavailable: 'Unavailable' };

    function text(value, fallback) {
        return value === null || value === undefined || value === '' ? (fallback || '') : String(value);
    }

    function escapeHtml(value) {
        return text(value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function number(value) {
        if (value === null || value === undefined || value === '' || typeof value === 'boolean') return null;
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function count(value) {
        var parsed = number(value);
        return parsed === null ? '—' : parsed.toLocaleString('en-PH', { maximumFractionDigits: 0 });
    }

    function money(value) {
        var parsed = number(value);
        return parsed === null ? '—' : '₱' + parsed.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function canFulfil(row) {
        var available = number(row.available_stock);
        var quantity = number(row.quantity);
        return row.can_fulfil === true && row.status === 'Pending' && /^[1-9]\d*$/.test(text(row.id)) &&
            available !== null && quantity !== null && quantity > 0 && available >= quantity && row.stock_state === 'available';
    }

    function renderOrder(row) {
        var name = text(row.dealer_name, 'Dealer');
        return '<div class="received-order__buyer"><span class="received-order__avatar" aria-hidden="true">' +
            escapeHtml(Array.from(name)[0].toUpperCase()) + '</span><div><strong class="received-order__name">' +
            escapeHtml(name) + '</strong><span class="received-order__reference">' + escapeHtml(text(row.transaction_id, 'Reference unavailable')) +
            '</span><span class="received-order__date">' + escapeHtml(text(row.created_at_display, 'Date unavailable')) + '</span></div></div>';
    }

    function renderProduct(row) {
        return '<strong class="received-order__product-name">' + escapeHtml(text(row.product_name, 'Product unavailable')) +
            '</strong>' + (row.sku ? '<span class="received-order__sku">SKU: ' + escapeHtml(row.sku) + '</span>' : '');
    }

    function renderStock(row) {
        var state = Object.prototype.hasOwnProperty.call(stockLabels, row.stock_state) ? row.stock_state : 'unavailable';
        var available = number(row.available_stock);
        return '<span class="received-order__stock received-order__stock--' + state + '">' +
            (available === null ? '' : '<strong>' + escapeHtml(count(Math.max(0, available))) + '</strong> ') +
            stockLabels[state] + '</span><span class="received-order__stock-message">' +
            escapeHtml(text(row.stock_message, 'Refresh stock to check availability.')) + '</span>';
    }

    function renderStatus(row) {
        var style = Object.prototype.hasOwnProperty.call(statusClasses, row.status) ? statusClasses[row.status] : 'neutral';
        return '<span class="received-order__badge received-order__badge--' + style + '">' + escapeHtml(text(row.status, 'Unknown')) + '</span>';
    }

    function renderAction(row) {
        if (row.status !== 'Pending') return '<span class="received-order__action-note">' +
            (row.status === 'For Verification' ? 'Awaiting Dealer verification' :
                row.status === 'Completed' ? 'Receipt confirmed by Dealer' : 'No fulfilment action') + '</span>';
        var allowed = canFulfil(row);
        var reference = text(row.transaction_id, 'this order');
        return '<button type="button" class="received-order-fulfil" data-id="' + escapeHtml(row.id) +
            '" data-reference="' + escapeHtml(reference) + '" data-allowed="' + (allowed ? 'true' : 'false') +
            '" aria-label="' + escapeHtml((allowed ? 'Fulfil order ' : 'Cannot fulfil order ') + reference) + '"' +
            (allowed ? '' : ' disabled') + '><span class="received-order-fulfil__label">' + (allowed ? 'Fulfil order' : 'Cannot fulfil') +
            '</span>' + (allowed ? '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5"/></svg>' : '') +
            '</button>' + (allowed ? '' : '<span class="received-order__action-note">' +
                escapeHtml(text(row.stock_message, 'The requested quantity is not available.')) + '</span>');
    }

    function display(renderer, fallback) {
        return function (value, type, row) {
            return type === 'display' ? renderer(row) : (value === null || value === undefined ? (fallback || '') : value);
        };
    }

    function createOptions(settings) {
        var plain = function (value, type) { return type === 'display' ? escapeHtml(text(value, 'Not specified')) : text(value); };
        return {
            serverSide: true,
            processing: true,
            deferRender: true,
            autoWidth: false,
            searchDelay: 400,
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            pagingType: 'simple_numbers',
            order: [[0, 'desc']],
            dom: '<"received-orders__toolbar"lf>r<"received-orders__table-scroll"t><"received-orders__pagination"ip>',
            ajax: {
                url: settings.url,
                type: 'GET',
                timeout: 20000,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                data: function (request) { request.status = settings.getStatus ? settings.getStatus() : ''; }
            },
            columns: [
                { data: 'created_at_display', name: 'created_at', className: 'received-orders__cell--order', render: display(renderOrder) },
                { data: 'product_name', name: 'product_name', className: 'received-orders__cell--product', render: display(renderProduct) },
                { data: 'quantity', name: 'quantity', className: 'received-orders__cell--quantity', render: display(function (row) { return '<strong class="received-order__quantity">' + escapeHtml(count(row.quantity)) + '</strong>'; }) },
                { data: 'available_stock', name: '', orderable: false, searchable: false, className: 'received-orders__cell--stock', render: display(renderStock) },
                { data: 'payment_label', name: 'payment_method', className: 'received-orders__cell--payment', render: plain },
                { data: 'delivery_label', name: 'delivery_type', className: 'received-orders__cell--delivery', render: plain },
                { data: 'total_amount', name: 'total_amount', className: 'received-orders__cell--total', render: display(function (row) { return '<strong class="received-order__total">' + escapeHtml(money(row.total_amount)) + '</strong>'; }) },
                { data: 'status', name: 'status', className: 'received-orders__cell--status', render: display(renderStatus) },
                { data: null, name: '', orderable: false, searchable: false, className: 'received-orders__cell--action', render: display(renderAction) }
            ],
            language: {
                search: 'Search orders',
                searchPlaceholder: 'Dealer, order or product',
                lengthMenu: 'Show _MENU_ orders',
                processing: '<span role="status"><span class="received-orders__spinner" aria-hidden="true"></span>Checking orders and stock…</span>',
                loadingRecords: 'Loading orders…',
                emptyTable: '<strong>No Dealer orders yet</strong>New orders in your sales area will appear here.',
                zeroRecords: '<strong>No matching orders</strong>Try another search or choose a different status.',
                info: 'Showing _START_–_END_ of _TOTAL_ orders',
                infoEmpty: 'No orders to show',
                infoFiltered: '(from _MAX_ total)',
                paginate: { previous: 'Previous', next: 'Next' },
                aria: { sortAscending: ': sort ascending', sortDescending: ': sort descending' }
            },
            createdRow: function (row) {
                Array.prototype.forEach.call(row.cells, function (cell, index) { cell.setAttribute('data-label', labels[index]); });
            },
            drawCallback: function () {
                if (settings.onDraw) settings.onDraw(this.api());
            },
            initComplete: function () {
                var container = this.api().table().container();
                var search = container.querySelector('input[type="search"]');
                if (search) search.setAttribute('aria-label', 'Search Dealer orders');
            }
        };
    }

    function responseMessage(data, status, fallback) {
        if (status === 401 || status === 419) return 'Your session has expired. Sign in again before fulfilling an order.';
        if (data && data.errors && typeof data.errors === 'object') {
            var errors = Object.keys(data.errors).map(function (key) { return data.errors[key]; });
            for (var index = 0; index < errors.length; index++) {
                var error = Array.isArray(errors[index]) ? errors[index][0] : errors[index];
                if (typeof error === 'string' && error.trim()) return error.slice(0, 1000);
            }
        }
        return data && typeof data.message === 'string' && data.message.trim() ? data.message.slice(0, 1000) : fallback;
    }

    function mount(env) {
        var document = env.document;
        var node = document.getElementById('received-dealer-orders');
        if (!node) return null;
        if (node.receivedOrdersController) return node.receivedOrdersController;
        var section = node.closest('.received-orders');
        var errorBox = document.getElementById('received-orders-error');
        var errorMessage = document.getElementById('received-orders-error-message');
        var total = document.getElementById('received-orders-count');
        var filter = document.getElementById('received-orders-status');
        var sort = document.getElementById('received-orders-sort');
        var refresh = document.getElementById('received-orders-refresh');
        var retry = document.getElementById('received-orders-retry');
        var busy = new Map();
        var loading = false;
        var failed = false;
        var table;

        function syncButtons() {
            node.querySelectorAll('.received-order-fulfil').forEach(function (button) {
                button.disabled = button.dataset.allowed !== 'true' || loading || failed || busy.size > 0;
                var state = busy.get(button.dataset.id);
                button.setAttribute('aria-busy', state ? 'true' : 'false');
                var label = button.querySelector('.received-order-fulfil__label');
                if (label) label.textContent = state || (button.dataset.allowed === 'true' ? 'Fulfil order' : 'Cannot fulfil');
            });
            if (refresh) refresh.disabled = loading;
            if (retry) retry.disabled = loading;
        }

        function showError(message) {
            failed = true;
            if (errorMessage) errorMessage.textContent = message;
            if (errorBox) errorBox.hidden = false;
            syncButtons();
        }

        function clearError() {
            failed = false;
            if (errorBox) errorBox.hidden = true;
        }

        function reload(resetPaging) {
            if (table) table.ajax.reload(null, resetPaging === true);
        }

        function notify(title, message, icon) {
            if (env.Swal && typeof env.Swal.fire === 'function') return env.Swal.fire({ title: title, text: message, icon: icon, confirmButtonColor: '#196ed2' });
            showError(message);
            return Promise.resolve();
        }

        async function fulfil(button) {
            var id = button && button.dataset.id;
            if (!button || button.disabled || button.dataset.allowed !== 'true' || busy.size || loading || failed || !/^[1-9]\d*$/.test(text(id))) return;
            var csrf = document.querySelector('meta[name="csrf-token"]');
            csrf = csrf && csrf.getAttribute('content');
            if (!csrf) { await notify('Refresh required', 'Refresh this page to restore your secure session, then try again.', 'error'); return; }
            if (!env.Swal || typeof env.Swal.fire !== 'function') { showError('Order confirmation could not start. Refresh the page and try again.'); return; }
            busy.set(id, 'Confirming…');
            syncButtons();
            var attempted = false;
            var timer;
            try {
                var result = await env.Swal.fire({
                    title: 'Fulfil order?',
                    text: text(button.dataset.reference) + ' · Stock will be reserved and the order sent to the Dealer for verification.',
                    input: 'textarea',
                    inputLabel: 'Fulfilment remarks',
                    inputPlaceholder: 'Enter fulfilment or collection details',
                    inputAttributes: { maxlength: '255' },
                    showCancelButton: true,
                    confirmButtonText: 'Submit for verification',
                    cancelButtonText: 'Keep pending',
                    confirmButtonColor: '#196ed2',
                    preConfirm: function (value) {
                        var remarks = text(value).trim();
                        if (!remarks || remarks.length > 255) {
                            env.Swal.showValidationMessage(!remarks ? 'Fulfilment remarks are required.' : 'Use no more than 255 characters.');
                            return false;
                        }
                        return remarks;
                    }
                });
                if (!result || !result.isConfirmed) return;
                var remarks = text(result.value).trim();
                if (!remarks || remarks.length > 255) throw new Error('Enter fulfilment remarks using no more than 255 characters.');
                busy.set(id, 'Saving…');
                syncButtons();
                attempted = true;
                var abort = typeof env.AbortController === 'function' ? new env.AbortController() : null;
                if (abort) timer = env.setTimeout(function () { abort.abort(); }, 20000);
                var response = await env.fetch(node.dataset.fulfilUrl.replace('__ORDER__', encodeURIComponent(id)), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ remarks: remarks }),
                    signal: abort ? abort.signal : undefined
                });
                var data = null;
                try { data = JSON.parse(await response.text()); } catch (parseError) { /* An expired session may return an HTML sign-in page. */ }
                if (!response.ok || !data || data.success !== true || (data.status !== 'For Verification' && data.status !== 'Completed')) {
                    throw new Error(responseMessage(data, response.status, response.ok ? 'The fulfilment status could not be confirmed. Check the refreshed order status before trying again.' : 'The order could not be submitted for verification. Refresh stock and try again.'));
                }
                reload(false);
                await notify(data.status === 'Completed' ? 'Order already completed' : 'Submitted for verification', text(data.message,
                    data.status === 'Completed' ? 'The Dealer has already confirmed receipt of this order.' :
                        'The order is awaiting Dealer verification. Stock has been reserved and refreshed.'), 'success');
            } catch (error) {
                // A request can complete on the server even if its response is lost.
                if (attempted) reload(false);
                await notify('Order not confirmed', error && error.name === 'AbortError' ?
                    'The request timed out. Check the refreshed order status before trying again.' :
                    text(error && error.message, 'Check your connection and refresh stock before trying again.'), 'error');
            } finally {
                if (timer) env.clearTimeout(timer);
                busy.delete(id);
                syncButtons();
            }
        }

        var $ = env.jQuery;
        if (!$ || !$.fn || !$.fn.DataTable) {
            showError('The order table could not start. Refresh the page to load its required files.');
            if (total) total.textContent = 'Orders unavailable';
            return null;
        }
        var wrapped = $(node);
        wrapped.on('processing.dt', function (event, settings, processing) {
            loading = processing;
            node.setAttribute('aria-busy', processing ? 'true' : 'false');
            if (section) section.classList.toggle('received-orders--loading', processing);
            syncButtons();
        });
        wrapped.on('xhr.dt', function (event, settings, json, xhr) {
            if (!json) {
                showError(responseMessage(xhr && xhr.responseJSON, xhr && xhr.status,
                    'Orders and live stock could not be loaded. Check your connection and try again. Fulfilment is paused until stock is refreshed.'));
                if (total) total.textContent = 'Orders unavailable';
                return true; // Handle this table's Ajax error without a global DataTables alert.
            }
            if (settings && Number(json.draw) < settings.iDraw) return true;
            clearError();
            if (total) total.textContent = count(json.recordsTotal) + (Number(json.recordsTotal) === 1 ? ' order' : ' orders');
        });
        wrapped.on('order.dt', function () {
            if (!table || !sort) return;
            var order = table.order()[0];
            sort.value = Object.keys(sorts).find(function (key) { return sorts[key][0] === order[0] && sorts[key][1] === order[1]; }) || '';
        });
        table = wrapped.DataTable(createOptions({
            url: node.dataset.source,
            getStatus: function () { return filter ? filter.value : ''; },
            onDraw: function (api) {
                syncButtons();
                var page = api.page.info();
                if (page.recordsDisplay > 0 && page.start >= page.recordsDisplay) api.page('last').draw('page');
            }
        }));
        node.addEventListener('click', function (event) {
            var button = event.target.closest('.received-order-fulfil');
            if (button && node.contains(button)) fulfil(button);
        });
        if (filter) filter.addEventListener('change', function () { reload(true); });
        if (sort) sort.addEventListener('change', function () {
            if (Object.prototype.hasOwnProperty.call(sorts, sort.value)) table.order([sorts[sort.value]]).draw();
        });
        if (refresh) refresh.addEventListener('click', function () { reload(false); });
        if (retry) retry.addEventListener('click', function () { reload(false); });
        if (env.addEventListener) env.addEventListener('online', function () { reload(false); });
        node.receivedOrdersController = { table: table, reload: reload, fulfil: fulfil };
        return node.receivedOrdersController;
    }

    return { mount: mount, createOptions: createOptions, escapeHtml: escapeHtml, renderOrder: renderOrder,
        renderProduct: renderProduct, renderStock: renderStock, renderStatus: renderStatus, renderAction: renderAction,
        canFulfil: canFulfil, money: money, responseMessage: responseMessage };
}));
