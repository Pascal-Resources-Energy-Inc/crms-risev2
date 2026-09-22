@extends('layouts.header')

@section('content')
@php
    $productImage = config('app.crms_admin_url') . '/public/uploads/products/' . $product->product_image;
@endphp

<div class="stock-transaction-page">
    <div class="transaction-topbar">
        <button type="button" class="icon-btn" onclick="window.location.href='{{ route('dealer.stock.inventory') }}'" aria-label="Back to stock inventory">
            <i class="bi bi-arrow-left"></i>
        </button>
        <div>
            <h1>Stock Transactions</h1>
            <p>{{ $product->product_name }}</p>
        </div>
        <button type="button" class="icon-btn" onclick="window.location.reload()" aria-label="Refresh transactions">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>

    <main class="transaction-shell">
        <section class="product-panel">
            <div class="product-photo">
                <img
                    src="{{ $productImage }}"
                    onerror="this.src='{{ asset('images/no-image.jpg') }}'"
                    alt="{{ $product->product_name }}"
                >
            </div>
            <div class="product-copy">
                <span class="sku-label">{{ $product->sku ? 'SKU: ' . $product->sku : 'No SKU' }}</span>
                <h2>{{ $product->product_name }}</h2>
                <p>{{ $product->description ?: 'No description available' }}</p>
            </div>
        </section>

        <section class="metric-grid">
            <div class="metric-card current">
                <i class="bi bi-box-seam metric-icon"></i>
                <span>Current Stock</span>
                <strong>{{ number_format($currentStock) }}</strong>
            </div>
            <div class="metric-card in">
                <i class="bi bi-arrow-down-circle metric-icon"></i>
                <span>Total Stock In</span>
                <strong>{{ number_format($totalIn) }}</strong>
            </div>
            <div class="metric-card out">
                <i class="bi bi-arrow-up-circle metric-icon"></i>
                <span>Total Stock Out</span>
                <strong>{{ number_format($totalOut) }}</strong>
            </div>
            <div class="metric-card value">
                <i class="bi bi-cash-stack metric-icon"></i>
                <span>Stock Value</span>
                <strong>PHP {{ number_format($stockValue, 2) }}</strong>
            </div>
        </section>

        <section class="transaction-card">
            <div class="section-title-row">
                <div>
                    <h2>Movement History</h2>
                    <p>{{ $transactions->count() }} transaction{{ $transactions->count() == 1 ? '' : 's' }} found</p>
                </div>
                <div class="legend">
                    <span><i class="dot in"></i> Stock In</span>
                    <span><i class="dot out"></i> Stock Out</span>
                </div>
            </div>

            @if($transactions->count())
                <div class="transaction-list">
                    @foreach($transactions as $transaction)
                        <article class="transaction-row {{ $transaction['type'] }}">
                            <div class="movement-icon">
                                <i class="bi {{ $transaction['type'] === 'in' ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle' }}"></i>
                            </div>

                            <div class="movement-main">
                                <div class="movement-heading">
                                    <div>
                                        <h3>{{ $transaction['label'] }}</h3>
                                        <p>{{ $transaction['reference'] }} - {{ $transaction['party'] }}</p>
                                    </div>
                                    <span class="status-pill {{ $transaction['type'] }}">{{ $transaction['status'] }}</span>
                                </div>

                                <div class="movement-meta">
                                    <span><i class="bi bi-calendar3"></i>{{ $transaction['date'] }}</span>
                                    <span><i class="bi bi-tag"></i>PHP {{ number_format($transaction['unit_price'], 2) }} each</span>
                                    <span><i class="bi bi-cash"></i>PHP {{ number_format($transaction['amount'], 2) }}</span>
                                </div>
                            </div>

                            <div class="movement-qty {{ $transaction['type'] }}">
                                {{ $transaction['signed_qty'] > 0 ? '+' : '' }}{{ number_format($transaction['signed_qty']) }}
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <i class="bi bi-list-check"></i>
                    <h2>No stock transactions yet</h2>
                    <p>Completed AD orders and dealer sales for this product will appear here.</p>
                </div>
            @endif
        </section>
    </main>
</div>
@endsection

@section('css')
<style>
    body {
        background: #eef4f8;
        padding-top: 0 !important;
    }

    .stock-transaction-page {
        min-height: 100vh;
        padding-bottom: 110px;
        color: #172033;
    }

    .transaction-topbar {
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

    .transaction-topbar h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        color: #101828;
    }

    .transaction-topbar p {
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
        background: #eaf7fc;
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

    .transaction-shell {
        width: min(1080px, 100%);
        margin: 0 auto;
        padding: 18px;
    }

    .product-panel,
    .transaction-card,
    .metric-card {
        background: #ffffff;
        border: 1px solid #e8eef6;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .product-panel {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
        gap: 16px;
        align-items: center;
        padding: 16px;
        margin-bottom: 14px;
        position: relative;
        overflow: hidden;
    }

    .product-panel::before {
        content: "";
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: #1688b3;
    }

    .product-photo {
        width: 112px;
        aspect-ratio: 1;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #edf2f7;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .product-photo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .sku-label {
        display: inline-flex;
        border-radius: 999px;
        background: #eaf7fc;
        color: #1688b3;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .product-copy h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        color: #101828;
    }

    .product-copy p {
        margin: 6px 0 0;
        color: #667085;
        font-size: 13px;
        line-height: 1.5;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
    }

    .metric-card {
        padding: 16px;
        min-height: 96px;
        border-left: 4px solid #1688b3;
        position: relative;
        overflow: hidden;
    }

    .metric-card.in {
        border-left-color: #16834a;
    }

    .metric-card.out {
        border-left-color: #b42318;
    }

    .metric-card.value {
        border-left-color: #7c3aed;
    }

    .metric-card span {
        display: block;
        font-size: 12px;
        color: #667085;
        margin-bottom: 8px;
        padding-right: 34px;
    }

    .metric-card strong {
        display: block;
        font-size: 22px;
        line-height: 1.2;
        color: #101828;
        overflow-wrap: anywhere;
        padding-right: 34px;
    }

    .metric-icon {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eaf7fc;
        color: #1688b3;
        font-size: 17px;
    }

    .metric-card.in .metric-icon {
        background: #ecfdf3;
        color: #16834a;
    }

    .metric-card.out .metric-icon {
        background: #fef2f2;
        color: #b42318;
    }

    .metric-card.value .metric-icon {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .transaction-card {
        padding: 16px;
    }

    .section-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
        padding-bottom: 14px;
        border-bottom: 1px solid #e8eef6;
    }

    .section-title-row h2 {
        margin: 0;
        font-size: 17px;
        font-weight: 800;
        color: #101828;
    }

    .section-title-row p {
        margin: 4px 0 0;
        color: #667085;
        font-size: 12px;
    }

    .legend {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        color: #667085;
        font-size: 12px;
        font-weight: 700;
    }

    .dot {
        width: 9px;
        height: 9px;
        border-radius: 99px;
        display: inline-block;
        margin-right: 5px;
    }

    .dot.in {
        background: #16834a;
    }

    .dot.out {
        background: #b42318;
    }

    .transaction-list {
        display: grid;
        gap: 10px;
    }

    .transaction-row {
        display: grid;
        grid-template-columns: 44px minmax(0, 1fr) 96px;
        gap: 12px;
        align-items: center;
        padding: 12px;
        border: 1px solid #edf2f7;
        border-radius: 8px;
        background: #ffffff;
        position: relative;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }

    .transaction-row:hover {
        border-color: #c8d8e5;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.07);
        transform: translateY(-1px);
    }

    .transaction-row.in {
        border-left: 4px solid #16834a;
    }

    .transaction-row.out {
        border-left: 4px solid #b42318;
    }

    .movement-icon {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ecfdf3;
        color: #16834a;
    }

    .transaction-row.out .movement-icon {
        background: #fef2f2;
        color: #b42318;
    }

    .movement-icon i {
        font-size: 21px;
    }

    .movement-heading {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 8px;
    }

    .movement-heading h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 800;
        color: #101828;
    }

    .movement-heading p {
        margin: 3px 0 0;
        color: #667085;
        font-size: 12px;
    }

    .status-pill {
        border-radius: 999px;
        padding: 5px 9px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-pill.in {
        background: #ecfdf3;
        color: #16834a;
    }

    .status-pill.out {
        background: #fef2f2;
        color: #b42318;
    }

    .movement-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 14px;
        color: #667085;
        font-size: 12px;
    }

    .movement-meta span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 24px;
        padding: 3px 8px;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #edf2f7;
    }

    .movement-qty {
        text-align: right;
        font-size: 22px;
        font-weight: 900;
    }

    .movement-qty.in {
        color: #16834a;
    }

    .movement-qty.out {
        color: #b42318;
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: #667085;
    }

    .empty-state i {
        font-size: 42px;
        color: #b8c4d2;
        margin-bottom: 12px;
    }

    .empty-state h2 {
        margin: 0 0 6px;
        font-size: 18px;
        color: #101828;
        font-weight: 800;
    }

    .empty-state p {
        margin: 0;
        font-size: 13px;
    }

    @media (max-width: 860px) {
        .metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .section-title-row {
            display: block;
        }

        .legend {
            margin-top: 10px;
        }
    }

    @media (max-width: 620px) {
        .transaction-shell {
            padding: 14px;
        }

        .product-panel {
            grid-template-columns: 82px minmax(0, 1fr);
        }

        .product-photo {
            width: 82px;
        }

        .product-copy h2 {
            font-size: 18px;
        }

        .metric-card strong {
            font-size: 18px;
        }

        .transaction-row {
            grid-template-columns: 40px minmax(0, 1fr);
        }

        .transaction-row:hover {
            transform: none;
        }

        .movement-icon {
            width: 40px;
            height: 40px;
        }

        .movement-qty {
            grid-column: 1 / -1;
            text-align: left;
            padding-left: 52px;
        }

        .movement-heading {
            display: block;
        }

        .status-pill {
            display: inline-flex;
            margin-top: 8px;
        }
    }
</style>
@endsection
