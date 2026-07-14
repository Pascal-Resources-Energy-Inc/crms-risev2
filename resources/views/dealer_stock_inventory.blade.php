@extends('layouts.header')

@section('content')
<div class="inventory-page">
    <div class="inventory-topbar">
        <button type="button" class="icon-btn" onclick="window.location.href='{{ url('account') }}'" aria-label="Back to account">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div>
            <h1>Stock Inventory</h1>
            <p>{{ $dealerProfile->store_name ?? auth()->user()->name }}{{ !empty($dealerProfile->area) ? ' - ' . $dealerProfile->area : '' }}</p>
        </div>
        <button type="button" class="icon-btn" id="refreshInventory" aria-label="Refresh inventory">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>

    <div class="inventory-shell">
        @if(session('success'))
            <div class="stock-feedback success"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="stock-feedback error"><i class="bi bi-exclamation-circle-fill"></i>{{ $errors->first() }}</div>
        @endif
        <div class="summary-grid">
            <div class="summary-card units-card">
                <div class="summary-icon units"><i class="bi bi-box-seam"></i></div>
                <span>Total Units</span>
                <strong>{{ number_format($totalUnits) }}</strong>
            </div>
            <div class="summary-card value-card">
                <div class="summary-icon value"><i class="bi bi-cash-stack"></i></div>
                <span>Stock Value</span>
                <strong>PHP {{ number_format($totalValue, 2) }}</strong>
            </div>
            <div class="summary-card healthy-card">
                <div class="summary-icon healthy"><i class="bi bi-check2-circle"></i></div>
                <span>With Stock</span>
                <strong>{{ number_format($inStockCount) }}</strong>
            </div>
            <div class="summary-card alert-card">
                <div class="summary-icon alert"><i class="bi bi-exclamation-triangle"></i></div>
                <span>Needs Stock</span>
                <strong>{{ number_format($outStockCount + $lowStockCount) }}</strong>
            </div>
        </div>

        <div class="inventory-tools">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="search" id="inventorySearch" placeholder="Search product or SKU">
            </div>

            <div class="filter-tabs" aria-label="Inventory filters">
                <button type="button" class="filter-tab active" data-filter="all">All <span>{{ $totalProducts }}</span></button>
                <button type="button" class="filter-tab" data-filter="healthy">In Stock <span>{{ $inStockCount }}</span></button>
                <button type="button" class="filter-tab" data-filter="low">Low <span>{{ $lowStockCount }}</span></button>
                <button type="button" class="filter-tab" data-filter="out">No Stock <span>{{ $outStockCount }}</span></button>
            </div>
        </div>

        <div class="inventory-list" id="inventoryList">
            @forelse($inventoryItems as $item)
                @php($stockRequest = $stockRequests->get($item['id']))
                <article
                    class="inventory-card status-{{ $item['status'] }}"
                    data-status="{{ $item['status'] }}"
                    data-search="{{ strtolower($item['name'] . ' ' . $item['sku'] . ' ' . $item['description']) }}"
                >
                    <div class="product-media">
                        <img
                            src="{{ $item['image'] }}"
                            onerror="this.src='{{ asset('images/no-image.png') }}'"
                            alt="{{ $item['name'] }}"
                        >
                    </div>

                    <div class="product-main">
                        <div class="product-heading">
                            <div>
                                <h2>{{ $item['name'] }}</h2>
                                <p>{{ $item['sku'] ? 'SKU: ' . $item['sku'] : 'No SKU' }}</p>
                            </div>
                            <span class="stock-badge {{ $item['status'] }}">{{ $item['status_label'] }}</span>
                        </div>

                        <div class="product-description">
                            {{ $item['description'] ?: 'No description available' }}
                        </div>

                        <div class="stock-meter {{ $item['status'] }}" aria-label="Dealer stock level">
                            <div style="width: {{ min(100, max(0, ((int) ($item['dealer_stock'] ?? 0)) * 10)) }}%;"></div>
                        </div>
                    </div>

                    <div class="stock-numbers">
                        <div>
                            <span>Dealer Stock</span>
                            <strong>{{ number_format($item['dealer_stock']) }}</strong>
                        </div>
                        <div>
                            <span>AD Stock</span>
                            <strong>{{ number_format($item['ad_stock']) }}</strong>
                        </div>
                        <div>
                            <span>Unit Price</span>
                            <strong>PHP {{ number_format($item['price'], 2) }}</strong>
                        </div>
                        <div>
                            <span>Value</span>
                            <strong>PHP {{ number_format($item['value'], 2) }}</strong>
                        </div>
                        <a href="{{ route('dealer.stock.transactions', $item['id']) }}" class="transaction-link">
                            <i class="bi bi-list-check"></i>
                            <span>View Transactions</span>
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                        @if(auth()->user()->role === 'Dealer')
                            @if($stockRequest && $stockRequest->status === 'Rejected')
                                <div class="rejection-remarks" role="status">
                                    <div class="rejection-remarks-icon"><i class="bi bi-x-circle-fill"></i></div>
                                    <div>
                                        <span class="rejection-remarks-title">Stock request declined</span>
                                        <p><strong>Admin remarks:</strong> {{ $stockRequest->remarks ?: 'No reason was provided.' }}</p>
                                        @if($stockRequest->reviewed_at)
                                            <small>Reviewed {{ $stockRequest->reviewed_at->format('M j, Y') }}</small>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if($item['dealer_stock'] > 0)
                                <div class="stock-action locked"><i class="bi bi-lock-fill"></i><span>Stock already available</span></div>
                            @elseif($stockRequest && $stockRequest->status === 'Pending')
                                <div class="stock-action pending"><i class="bi bi-hourglass-split"></i><span>Request pending approval</span></div>
                            @else
                                <button type="button" class="stock-action request-stock" data-product-id="{{ $item['id'] }}" data-product-name="{{ e($item['name']) }}" data-product-sku="{{ e($item['sku'] ?: 'No SKU') }}">
                                    <i class="bi bi-plus-circle-fill"></i><span>Request stock</span><i class="bi bi-arrow-right-short"></i>
                                </button>
                            @endif
                        @endif
                    </div>
                </article>
            @empty
                <div class="inventory-empty">
                    <i class="bi bi-box"></i>
                    <h2>No products found</h2>
                    <p>Activated products for your area will appear here.</p>
                </div>
            @endforelse
        </div>

        <div class="inventory-empty hidden" id="filteredEmpty">
            <i class="bi bi-search"></i>
            <h2>No matching stock items</h2>
            <p>Try a different search or filter.</p>
        </div>
    </div>
</div>

@if(auth()->user()->role === 'Dealer')
<div class="request-modal" id="stockRequestModal" aria-hidden="true">
    <div class="request-modal-card" role="dialog" aria-modal="true" aria-labelledby="requestTitle">
        <button type="button" class="modal-close" id="closeRequestModal" aria-label="Close"><i class="bi bi-x-lg"></i></button>
        <div class="modal-icon"><i class="bi bi-box-seam"></i></div>
        <span class="modal-kicker">ADMIN APPROVAL REQUIRED</span>
        <h2 id="requestTitle">Request item stock</h2>
        <p id="requestProduct" class="request-product-name"></p>
        <p class="modal-copy">Your request will be reviewed by an administrator. Stock is added only after approval.</p>
        <form method="POST" action="{{ route('dealer.stock.requests.store') }}">
            @csrf
            <input type="hidden" name="product_id" id="requestProductId">
            <label for="requestQuantity">Quantity to request</label>
            <div class="quantity-input"><i class="bi bi-boxes"></i><input id="requestQuantity" name="quantity" type="number" min="1" max="100000" required placeholder="Enter quantity" inputmode="numeric"></div>
            <button type="submit" class="submit-request"><i class="bi bi-send"></i> Submit request</button>
        </form>
    </div>
</div>
@endif
@endsection

@section('css')
<style>
    body {
        background: #eef4f8;
        padding-top: 0 !important;
    }

    .inventory-page {
        min-height: 100vh;
        padding-bottom: 110px;
        color: #172033;
    }

    .inventory-topbar {
        position: sticky;
        top: 0;
        z-index: 20;
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) 44px;
        align-items: center;
        gap: 12px;
        padding: 16px 18px;
        background: rgba(255, 255, 255, 0.96);
        border-bottom: 1px solid #e6edf5;
        box-shadow: 0 8px 28px rgba(15, 23, 42, 0.07);
        backdrop-filter: blur(12px);
    }

    .inventory-topbar h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #101828;
    }

    .inventory-topbar p {
        margin: 3px 0 0;
        font-size: 12px;
        color: #667085;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .icon-btn {
        width: 44px;
        height: 44px;
        border: 0;
        border-radius: 8px;
        background: #e8f7fc;
        color: #1688b3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.18s ease, background 0.18s ease;
    }

    .icon-btn:hover {
        background: #d8f0f8;
        transform: translateY(-1px);
    }

    .icon-btn i {
        font-size: 20px;
    }

    .inventory-shell {
        width: min(1180px, 100%);
        margin: 0 auto;
        padding: 18px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .summary-card {
        min-height: 116px;
        background: #ffffff;
        border: 1px solid #e8eef6;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: #1688b3;
    }

    .summary-card.value-card::before {
        background: #16834a;
    }

    .summary-card.healthy-card::before {
        background: #175cd3;
    }

    .summary-card.alert-card::before {
        background: #c2410c;
    }

    .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
    }

    .summary-icon.units {
        background: #e9f7fc;
        color: #1688b3;
    }

    .summary-icon.value {
        background: #ecfdf3;
        color: #16834a;
    }

    .summary-icon.healthy {
        background: #eff8ff;
        color: #175cd3;
    }

    .summary-icon.alert {
        background: #fff7ed;
        color: #c2410c;
    }

    .summary-card span {
        display: block;
        font-size: 12px;
        color: #667085;
        margin-bottom: 4px;
    }

    .summary-card strong {
        display: block;
        font-size: 22px;
        line-height: 1.2;
        color: #101828;
        overflow-wrap: anywhere;
    }

    .inventory-tools {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) auto;
        gap: 12px;
        align-items: center;
        margin-bottom: 14px;
        padding: 12px;
        background: #ffffff;
        border: 1px solid #e8eef6;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .search-box {
        height: 48px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 14px;
        background: #f8fafc;
        border: 1px solid #d9e4ef;
        border-radius: 8px;
    }

    .search-box:focus-within {
        border-color: #1688b3;
        box-shadow: 0 0 0 3px rgba(22, 136, 179, 0.12);
    }

    .search-box i {
        color: #667085;
        font-size: 18px;
    }

    .search-box input {
        width: 100%;
        border: 0;
        outline: 0;
        color: #101828;
        font-size: 14px;
        background: transparent;
    }

    .filter-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .filter-tab {
        height: 42px;
        border: 1px solid #d9e4ef;
        border-radius: 8px;
        background: #ffffff;
        color: #344054;
        padding: 0 12px;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
        transition: border-color 0.18s ease, background 0.18s ease, color 0.18s ease;
    }

    .filter-tab:hover {
        border-color: #1688b3;
        color: #1688b3;
    }

    .filter-tab span {
        color: #667085;
        font-weight: 700;
        margin-left: 4px;
    }

    .filter-tab.active {
        background: #1688b3;
        border-color: #1688b3;
        color: #ffffff;
    }

    .filter-tab.active span {
        color: #dff6ff;
    }

    .inventory-list {
        display: grid;
        gap: 12px;
    }

    .inventory-card {
        display: grid;
        grid-template-columns: 88px minmax(0, 1fr) minmax(280px, 360px);
        gap: 16px;
        align-items: center;
        background: #ffffff;
        border: 1px solid #e8eef6;
        border-radius: 8px;
        padding: 14px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .inventory-card:hover {
        border-color: #c8d8e5;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
        transform: translateY(-1px);
    }

    .inventory-card.status-out {
        border-left: 4px solid #b42318;
    }

    .inventory-card.status-low {
        border-left: 4px solid #c2410c;
    }

    .inventory-card.status-healthy {
        border-left: 4px solid #16834a;
    }

    .product-media {
        width: 88px;
        aspect-ratio: 1;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #edf2f7;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .product-media img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }

    .product-heading h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #101828;
    }

    .product-heading p,
    .product-description {
        margin: 4px 0 0;
        font-size: 12px;
        color: #667085;
    }

    .product-description {
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .stock-badge {
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .stock-badge.healthy {
        background: #ecfdf3;
        color: #16834a;
    }

    .stock-badge.low {
        background: #fff7ed;
        color: #c2410c;
    }

    .stock-badge.out {
        background: #fef2f2;
        color: #b42318;
    }

    .stock-meter {
        height: 7px;
        width: 100%;
        background: #edf2f7;
        border-radius: 99px;
        overflow: hidden;
        margin-top: 12px;
    }

    .stock-meter div {
        height: 100%;
        background: #1688b3;
        border-radius: inherit;
    }

    .stock-meter.healthy div {
        background: #16834a;
    }

    .stock-meter.low div {
        background: #c2410c;
    }

    .stock-meter.out div {
        background: #b42318;
    }

    .stock-numbers {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .stock-numbers div {
        min-height: 68px;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #edf2f7;
        padding: 10px;
    }

    .stock-numbers span {
        display: block;
        font-size: 12px;
    }

    .stock-numbers strong {
        display: block;
        font-size: 15px;
        color: #101828;
        overflow-wrap: anywhere;
    }

    .transaction-link {
        grid-column: 1 / -1;
        min-height: 46px;
        border-radius: 8px;
        background: #1688b3;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }

    .transaction-link:hover {
        color: #ffffff;
        background: #0f7298;
        box-shadow: 0 10px 22px rgba(22, 136, 179, 0.22);
        transform: translateY(-1px);
    }

    .stock-action {
        grid-column: 1 / -1;
        min-height: 44px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 800;
    }

    .request-stock {
        border: 1px solid #1688b3;
        background: #e8f7fc;
        color: #11769c;
        cursor: pointer;
        transition: transform 0.18s ease, background 0.18s ease;
    }

    .request-stock:hover { background: #d5f0fa; transform: translateY(-1px); }
    .stock-action.locked { background: #f1f5f9; color: #64748b; }
    .stock-action.pending { background: #fff7ed; color: #b45309; }

    .rejection-remarks {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: 10px;
        padding: 11px;
        border: 1px solid #fecaca;
        border-radius: 8px;
        background: #fff7f7;
        color: #991b1b;
    }

    .rejection-remarks-icon {
        width: 34px;
        height: 34px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #fee2e2;
        color: #dc2626;
        font-size: 17px;
    }

    .rejection-remarks-title {
        display: block;
        margin-top: 1px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .01em;
    }

    .rejection-remarks p {
        margin: 3px 0 0;
        color: #7f1d1d;
        font-size: 12px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .rejection-remarks p strong { font-weight: 800; }

    .rejection-remarks small {
        display: block;
        margin-top: 5px;
        color: #b45309;
        font-size: 11px;
        font-weight: 700;
    }

    .stock-feedback {
        margin-bottom: 16px;
        padding: 13px 15px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 13px;
        font-weight: 700;
    }

    .stock-feedback.success { background: #ecfdf3; color: #16834a; border: 1px solid #bbf7d0; }
    .stock-feedback.error { background: #fef2f2; color: #b42318; border: 1px solid #fecaca; }

    .request-modal { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, 0.58); backdrop-filter: blur(4px); }
    .request-modal.show { display: flex; }
    .request-modal-card { position: relative; width: min(430px, 100%); padding: 30px; border-radius: 16px; background: #fff; box-shadow: 0 24px 80px rgba(15, 23, 42, .28); }
    .modal-close { position: absolute; top: 14px; right: 14px; width: 36px; height: 36px; border: 0; border-radius: 8px; color: #667085; background: #f1f5f9; cursor: pointer; }
    .modal-icon { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 12px; color: #1688b3; background: #e8f7fc; font-size: 22px; }
    .modal-kicker { display: block; margin-top: 18px; color: #1688b3; font-size: 10px; font-weight: 800; letter-spacing: .9px; }
    .request-modal h2 { margin: 6px 0 4px; color: #101828; font-size: 22px; font-weight: 800; }
    .request-product-name { margin: 0; color: #344054; font-weight: 800; font-size: 14px; }
    .modal-copy { margin: 11px 0 20px; color: #667085; font-size: 13px; line-height: 1.55; }
    .request-modal label { display: block; margin-bottom: 7px; color: #344054; font-size: 13px; font-weight: 800; }
    .quantity-input { display: flex; align-items: center; gap: 10px; height: 48px; padding: 0 13px; border: 1px solid #d9e4ef; border-radius: 8px; color: #1688b3; }
    .quantity-input:focus-within { border-color: #1688b3; box-shadow: 0 0 0 3px rgba(22,136,179,.12); }
    .quantity-input input { width: 100%; border: 0; outline: 0; color: #101828; font-size: 14px; }
    .submit-request { width: 100%; height: 48px; margin-top: 16px; border: 0; border-radius: 8px; background: #1688b3; color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; }
    .submit-request:hover { background: #0f7298; }

    .inventory-empty {
        text-align: center;
        padding: 52px 20px;
        background: #ffffff;
        border: 1px solid #e8eef6;
        border-radius: 8px;
        color: #667085;
    }

    .inventory-empty i {
        font-size: 42px;
        color: #b8c4d2;
        margin-bottom: 12px;
    }

    .inventory-empty h2 {
        margin: 0 0 6px;
        font-size: 18px;
        color: #101828;
        font-weight: 800;
    }

    .inventory-empty p {
        margin: 0;
        font-size: 13px;
    }

    .hidden {
        display: none !important;
    }

    @media (max-width: 980px) {
        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .inventory-tools {
            grid-template-columns: 1fr;
        }

        .filter-tabs {
            width: 100%;
        }

        .inventory-card {
            grid-template-columns: 76px minmax(0, 1fr);
        }

        .product-media {
            width: 76px;
        }

        .stock-numbers {
            grid-column: 1 / -1;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .inventory-shell {
            padding: 14px;
        }

        .summary-grid {
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .summary-card {
            min-height: 106px;
            padding: 13px;
        }

        .summary-card strong {
            font-size: 18px;
        }

        .inventory-card {
            grid-template-columns: 68px minmax(0, 1fr);
            gap: 12px;
            padding: 12px;
        }

        .inventory-card:hover {
            transform: none;
        }

        .product-media {
            width: 68px;
        }

        .product-heading {
            display: block;
        }

        .stock-badge {
            display: inline-flex;
            margin-top: 8px;
        }

        .stock-numbers {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
@endsection

@section('js')
<script>
    const searchInput = document.getElementById('inventorySearch');
    const filterButtons = document.querySelectorAll('.filter-tab');
    const cards = document.querySelectorAll('.inventory-card');
    const filteredEmpty = document.getElementById('filteredEmpty');
    let activeFilter = 'all';

    function applyInventoryFilters() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach((card) => {
            const matchesFilter = activeFilter === 'all' || card.dataset.status === activeFilter;
            const matchesSearch = !query || card.dataset.search.includes(query);
            const isVisible = matchesFilter && matchesSearch;

            card.classList.toggle('hidden', !isVisible);
            if (isVisible) visibleCount++;
        });

        if (filteredEmpty) {
            filteredEmpty.classList.toggle('hidden', visibleCount !== 0 || cards.length === 0);
        }
    }

    searchInput?.addEventListener('input', applyInventoryFilters);

    filterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            filterButtons.forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            activeFilter = button.dataset.filter || 'all';
            applyInventoryFilters();
        });
    });

    document.getElementById('refreshInventory')?.addEventListener('click', () => {
        window.location.reload();
    });

    const requestModal = document.getElementById('stockRequestModal');
    const requestProductId = document.getElementById('requestProductId');
    const requestProduct = document.getElementById('requestProduct');
    const requestQuantity = document.getElementById('requestQuantity');

    document.querySelectorAll('.request-stock').forEach((button) => {
        button.addEventListener('click', () => {
            requestProductId.value = button.dataset.productId;
            requestProduct.textContent = `${button.dataset.productName} · ${button.dataset.productSku}`;
            requestQuantity.value = '';
            requestModal.classList.add('show');
            requestModal.setAttribute('aria-hidden', 'false');
            requestQuantity.focus();
        });
    });

    function closeStockRequestModal() {
        requestModal?.classList.remove('show');
        requestModal?.setAttribute('aria-hidden', 'true');
    }

    document.getElementById('closeRequestModal')?.addEventListener('click', closeStockRequestModal);
    requestModal?.addEventListener('click', (event) => {
        if (event.target === requestModal) closeStockRequestModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeStockRequestModal();
    });
</script>
@endsection
