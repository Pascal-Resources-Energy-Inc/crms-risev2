<section class="received-orders" aria-labelledby="received-orders-title">
    <header class="received-orders__header">
        <div>
            <p class="received-orders__eyebrow">Sales area orders</p>
            <h2 id="received-orders-title">Dealer orders received</h2>
            <p id="received-orders-help">Submit fulfilment for Dealer verification. Available stock excludes orders awaiting verification; receipt is confirmed by the Dealer.</p>
        </div>
        <span class="received-orders__count" id="received-orders-count" aria-live="polite">Loading orders</span>
    </header>
    <div class="received-orders__filters">
        <div class="received-orders__status-filter">
            <label for="received-orders-status">Order status</label>
            <select id="received-orders-status">
                <option value="">All statuses</option>
                <option value="Pending">Pending</option>
                <option value="For Verification">For Verification</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
                <option value="For Delivery">For Delivery</option>
            </select>
        </div>
        <div class="received-orders__sort-filter">
            <label for="received-orders-sort">Sort by</label>
            <select id="received-orders-sort">
                <option value="" disabled>Custom order</option>
                <option value="newest" selected>Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="product">Product name</option>
                <option value="quantity">Highest quantity</option>
                <option value="total">Highest total</option>
            </select>
        </div>
        <button class="received-orders__refresh" id="received-orders-refresh" type="button">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16.5 7A7 7 0 1 0 17 12M17 3v5h-5"/></svg>
            Refresh stock
        </button>
    </div>
    <div class="received-orders__error" id="received-orders-error" role="alert" hidden>
        <span id="received-orders-error-message"></span>
        <button type="button" id="received-orders-retry">Try again</button>
    </div>
    <div class="received-orders__table-region">
        <table id="received-dealer-orders" class="received-orders__table" aria-describedby="received-orders-help"
               data-source="{{ route('received.dealer.orders') }}"
               data-fulfil-url="{{ url('/mega-dealer/orders/__ORDER__/fulfil') }}">
            <caption class="received-orders__sr-only">Dealer orders, requested quantities, available product stock, payment, delivery and fulfilment.</caption>
            <thead>
                <tr>
                    <th scope="col">Order</th>
                    <th scope="col">Product</th>
                    <th scope="col">Requested</th>
                    <th scope="col">Available stock</th>
                    <th scope="col">Payment</th>
                    <th scope="col">Delivery</th>
                    <th scope="col">Total</th>
                    <th scope="col">Status</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <noscript><p class="received-orders__notice">Enable JavaScript to load orders and check their available stock.</p></noscript>
</section>
