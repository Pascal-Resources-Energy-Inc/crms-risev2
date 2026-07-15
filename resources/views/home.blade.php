@extends('layouts.header')
@section('header')

@endsection
<style>
    .main-content-wrapper {
        padding: 20px 16px 100px 16px;
        min-height: calc(100vh - 140px);
    }

    .school-img {
        position: absolute;
        right: 0;
        bottom: 0;
        width: 200px;
        height: 100%;
        display: flex;
        align-items: flex-end;
        justify-content: flex-end;
        pointer-events: none;
    }

    .school-img img {
        width: auto;
        height: 100%;
        max-height: 180px;
        object-fit: contain;
        object-position: right bottom;
    }
    
     @media (max-width: 576px) {
        .school-img {
            width: 140px !important;
            max-height: 100px;
        }
        
        .school-img img {
            max-height: 100px;
        }
    }

    .stats-card {
        background: white;
        border: none;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        padding: 20px;
        position: relative;
        height: 130px;
    }
    
    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: white;
        border: 2px solid #17a2b8;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        box-shadow: 0 1px 4px rgba(23, 162, 184, 0.15);
    }
    
    .icon-circle i {
        font-size: 20px;
        color: #17a2b8;
    }
    
    .stats-number {
        font-size: 1.75rem;
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .stats-label {
        font-size: 0.875rem;
        color: #6c757d;
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .trend-indicator {
        position: absolute;
        top: 16px;
        right: 16px;
        color: #28a745;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    
    .trend-indicator i {
        font-size: 20px;
    }

    .trend-indicator.text-success {
        color: #28a745 !important;
    }
    .trend-indicator.text-danger {
        color: #dc3545 !important;
    }
    .trend-indicator.text-muted {
        color: #6c757d !important;
    }
   
    .icon-circle svg {
        width: 24px;
        height: 24px;
        stroke: #17a2b8;
    }

    @media (max-width: 576px) {
        .form-select-sm {
            font-size: 0.875rem;
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
        }

        .main-content-wrapper {
            padding: 15px 12px 100px 12px;
        }
    }

    @media (max-width: 768px) {
        .text-nowrap {
            white-space: nowrap;
        }
        
        .flex-grow-1 {
            flex: 1;
            min-width: 0;
        }
    }

    .customer-link {
        font-size: 14px !important;
    }

    .welcome {
        margin-bottom: 24px;
    }

    .welcome-dealer {
        margin-bottom: 24px;
    }

    .card {
        margin-bottom: 20px;
    }

    .transaction-list {
        max-height: 500px;
        overflow-y: auto;
    }

    .transaction-item {
        transition: all 0.2s ease;
    }

    .transaction-card {
        background: transparent;
    }

    .transaction-card .transaction-item {
        background-color: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        margin-bottom: 12px;
        padding: 16px;
    }

    #qrcode {
        max-width: 150px;
        margin: 0 auto;
    }

    section {
        margin-bottom: 24px;
    }

    section:last-child {
        margin-bottom: 0;
    }

    .table-responsive {
        margin-bottom: 0;
    }

    .modal-dialog {
        margin: 1.75rem auto;
    }

    @media (max-width: 576px) {
        .modal-dialog {
            margin: 1rem;
        }
    }

    .sync-btn {
        background: linear-gradient(135deg, #5BC2E7 0%, #4facfe 100%);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(91, 194, 231, 0.3);
        margin-bottom: 16px;
    }

    .sync-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(91, 194, 231, 0.4);
    }

    .sync-btn:disabled {
        background: #6c757d;
        cursor: not-allowed;
        transform: none;
    }

    .sync-btn i {
        font-size: 18px;
    }

    .sync-btn.syncing i {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .sync-status {
        background: white;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .sync-status .status-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .sync-status .status-item:last-child {
        border-bottom: none;
    }

    .sync-status .label {
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
    }

    .sync-status .value {
        font-size: 14px;
        color: #2c3e50;
        font-weight: 600;
    }

    .sync-status .value.success {
        color: #28a745;
    }

    .sync-status .value.error {
        color: #dc3545;
    }

    .sync-status .value.pending {
        color: #ffc107;
    }

    .client-transaction-row {
        background: #f8fafc;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .client-transaction-row.is-completed {
        background: #f8fff9;
        border-color: #d8f3df !important;
    }

    .client-transaction-row.is-pending {
        background: #fff9e8;
        border-color: #ffe7a3 !important;
        opacity: 0.68;
        filter: grayscale(0.18);
    }

    .client-transaction-row.is-pending .transaction-avatar {
        opacity: 0.55;
    }

    .client-transaction-row.is-pending .client-transaction-name {
        color: #6c5a1f;
    }

    .client-transaction-row.is-pending small {
        color: #927a28 !important;
    }

    .client-transaction-row:hover {
        opacity: 1;
        filter: none;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }

    .order-transaction-card {
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }

    .order-transaction-card:hover {
        border-color: #f2d58a !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.1) !important;
        transform: translateY(-1px);
    }

    .order-action-panel {
        min-width: 175px;
    }

    .cancel-window-note {
        border: 1px solid #ffe0a3;
        background: #fffaf0;
        color: #8a5a00;
        border-radius: 12px;
        padding: 8px 10px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .cancel-window-note.is-locked {
        border-color: #e2e8f0;
        background: #f8fafc;
        color: #64748b;
    }

    .cancel-order-btn {
        border-radius: 999px;
        font-weight: 700;
        box-shadow: 0 6px 14px rgba(220, 53, 69, 0.18);
    }

    .order-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
    }

    @media (max-width: 576px) {
        .order-transaction-layout {
            flex-direction: column;
            gap: 14px;
        }

        .order-action-panel {
            width: 100%;
        }

        .order-actions,
        .order-areas {
            justify-content: flex-start !important;
        }
    }
</style>

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="main-content-wrapper">
    @if(auth()->user()->role == "Dealer")
        <section class="welcome welcome-dealer">
            <div class="row">    
                <div class="col-lg-10 col-xl-12 text-left mb-4">
                    <div class="card w-100 stretch">
                        <div class="card-body position-relative">
                            <div class='row'>
                                <div class="col-lg-12 col-xl-12" style="font-size: 12px; height: 80px;">
                                    Dealer ID: {{date('Y',strtotime($dealer->created_at))}}-{{$dealer->id}}<br>
                                    Name: {{$dealer->name}} <br>
                                    Contact No.: {{$dealer->number}} <br>
                                    Registered: {{date('M d,Y',strtotime($dealer->created_at))}} <br>
                                </div>
                            </div>
                            <div class="school-img d-sm-block">
                                <img src="{{asset('images/background.png')}}" class="img-fluid" alt="" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <button class="sync-btn d-none" id="syncButton" onclick="syncOfflineTransactions()">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <span>Sync Offline Transactions</span>
                    </button>
                    
                    <div class="sync-status d-none" id="syncStatus">
                        <div class="status-item">
                            <span class="label">Pending Transactions:</span>
                            <span class="value pending" id="pendingCount">0</span>
                        </div>
                        <div class="status-item">
                            <span class="label">Synced Successfully:</span>
                            <span class="value success" id="syncedCount">0</span>
                        </div>
                        <div class="status-item">
                            <span class="label">Failed to Sync:</span>
                            <span class="value error" id="failedCount">0</span>
                        </div>
                        <div class="status-item">
                            <span class="label">Last Sync:</span>
                            <span class="value" id="lastSync">Never</span>
                        </div>
                    </div>
                </div>
            </div>
            
            
            {{-- <div class="row mt-3">
                <div class="col-lg-6">
                    <h5 class="mb-2 fw-bold" style="color: #5BC2E7;">Latest Transaction</h5>
                    <div class="w-100">
                        <div class="transaction-card">
                            @if($transactions_details->isNotEmpty())
                            <div class="d-flex justify-content-end align-items-center mb-3">
                                    <label for="showEntries" class="me-2 mb-0 text-muted" style="font-size: 14px;">Show</label>
                                    <select id="showEntries" class="form-select form-select-sm" style="width: auto; min-width: 80px;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span class="ms-2 text-muted" style="font-size: 14px;">entries</span>
                                </div>

                                <div class="transaction-list" id="transactionList">
                                        @foreach($transactions_details as $transaction)
                                            <div class="transaction-item" style="display: none;">
                                                <div class="mb-3 p-3" style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0;">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-center flex-grow-1">
                                                            <div class="flex-shrink-0 me-3">
                                                                <div class="avatar-circle" style="width: 55px; height: 55px;">
                                                                    <img src="{{ optional($transaction->customer)->avatar ? asset($transaction->customer->avatar) : asset('design/assets/images/profile/user-1.png') }}" 
                                                                        alt="{{ optional($transaction->customer)->name ?? 'Customer' }}"
                                                                        class="rounded-circle w-100 h-100 object-fit-cover"
                                                                        style="border: 2px solid #e2e8f0;">
                                                                </div>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0 fw-bold text-dark">
                                                                    {{ strtoupper($transaction->customer->name ?? 'Unknown Customer') }}
                                                                </h6>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <small class="text-muted">{{ date('m/d/Y', strtotime($transaction->created_at)) }}</small>
                                                                    <small class="text-muted">{{ date('h:i A', strtotime($transaction->created_at)) }}</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="flex-shrink-0">
                                                            <div class="badge rounded-circle d-flex align-items-center justify-content-center" 
                                                                style="width: 30px; height: 30px; background-color: #5BC2E7; font-size: 14px; font-weight: bold;">
                                                                {{ number_format($transaction->qty, 0) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="ti ti-file-invoice fs-1 text-muted mb-3"></i>
                                    <p class="text-muted">No transactions yet</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h5 class="mb-2 fw-bold" style="color: #5BC2E7;">Order Transaction (AD)</h5>

                    <div class="transaction-card p-3">
                        @if($ad_transactions->isNotEmpty())

                            <div class="transaction-list">
                                @foreach($ad_transactions as $transaction)
                                    <div class="mb-3 p-3 rounded-3 shadow-sm border bg-white">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <!-- LEFT: AD INFO -->
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width: 55px; height: 55px; background: #fff3cd;">
                                                        <i class="bi bi-building" style="font-size: 22px; color: #856404;"></i>
                                                    </div>
                                                </div>

                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark">
                                                        {{ strtoupper(optional($transaction->areaDistributor)->name ?? 'AREA DISTRIBUTOR') }}
                                                    </h6>

                                                    <small class="text-muted">
                                                        {{ $transaction->ad_address ?? 'No address available' }}
                                                    </small>

                                                    <div class="small text-muted">
                                                        {{ date('M d, Y', strtotime($transaction->created_at)) }} • 
                                                        {{ date('h:i A', strtotime($transaction->created_at)) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- RIGHT: ORDER DETAILS -->
                                            <div class="text-end">
                                                <div class="fw-bold text-primary">
                                                    ₱{{ number_format($transaction->price * $transaction->qty, 2) }}
                                                </div>

                                                <div class="small text-muted">
                                                    Qty: {{ $transaction->qty }}
                                                </div>

                                                <span class="badge bg-warning text-dark mt-1">
                                                    AD ORDER
                                                </span>
                                            </div>

                                        </div>

                                        <!-- ITEM DETAILS -->
                                        <div class="mt-2 small text-muted">
                                            Item: <strong>{{ $transaction->item }}</strong>
                                        </div>

                                    </div>
                                @endforeach
                            </div>

                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-box-seam fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No AD orders yet</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div> --}}
            <div class="row mt-4 g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-primary">
                                <i class="bi bi-people-fill me-2"></i>Client Transactions
                            </h6>

                            <select id="showEntriesClient" class="form-select form-select-sm w-auto">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                        </div>

                        <div class="card-body p-3" style="max-height: 420px; overflow-y: auto;">

                            @forelse($client_transaction_feed ?? $transactions_details as $transaction)
                                @php
                                    $transactionStatus = $transaction->status ?? 'Completed';
                                    $isPendingTransaction = $transactionStatus === 'Pending';
                                @endphp
                                <div class="client-transaction-row {{ $isPendingTransaction ? 'is-pending' : 'is-completed' }} d-flex align-items-center justify-content-between mb-3 p-3 rounded-3 border">
                                    <!-- LEFT -->
                                    <div class="d-flex align-items-center">
                                        <img src="{{ optional($transaction->customer)->avatar ? asset($transaction->customer->avatar) : asset('design/assets/images/profile/user-1.png') }}"
                                            class="transaction-avatar rounded-circle me-3"
                                            style="width:50px;height:50px;object-fit:cover;">
                                        <div>
                                            <div class="fw-semibold client-transaction-name">
                                                {{ strtoupper($transaction->customer->name ?? 'GUEST') }}
                                            </div>

                                            <small class="text-muted">
                                                {{ date('M d, Y • h:i A', strtotime($transaction->created_at)) }}
                                            </small>
                                        </div>
                                    </div>
                                    <!-- RIGHT -->
                                    <div class="text-end">
                                        <div class="fw-bold {{ $isPendingTransaction ? 'text-muted' : 'text-success' }}">
                                            ₱{{ number_format($transaction->price * $transaction->qty, 2) }}
                                        </div>
                                        <div class="d-flex flex-column align-items-end gap-1 mt-1">
                                            <span class="badge {{ $isPendingTransaction ? 'bg-warning text-dark' : 'bg-success' }}">
                                                {{ $isPendingTransaction ? 'Pending' : 'Completed' }}
                                            </span>
                                            <span class="badge {{ $isPendingTransaction ? 'bg-light text-muted border' : 'bg-primary' }}">
                                                Qty: {{ $transaction->qty }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-receipt fs-1"></i>
                                    <p>No client transactions yet</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-warning">
                                <i class="bi bi-building me-2"></i>Order Transactions
                            </h6>
                        </div>
                        <div class="card-body p-3" style="max-height: 420px; overflow-y: auto;">
                            @forelse($ad_transactions as $transaction)
                            @php
                                $subtotal = $transaction['subtotal'] ?? $transaction['price_total'];
                                $deliveryFee = (float) ($transaction['delivery_fee'] ?? 0);
                                $grandTotal = $transaction['price_total'] ?? ($subtotal + $deliveryFee);
                                $createdAt = $transaction['created_at'];
                                $cancelExpiresAt = $createdAt->copy()->addHour();
                                $minutesLeftToCancel = max(0, now()->diffInMinutes($cancelExpiresAt, false));
                                $isPendingOrder = ($transaction['status'] ?? '') === 'Pending';
                                $canCancelOrder = $isPendingOrder && now()->lessThanOrEqualTo($cancelExpiresAt);
                            @endphp
                            <div class="mb-3 p-3 rounded-4 border bg-white shadow-sm order-transaction-card" id="ad-order-card-{{ $transaction['id'] }}">
                                <div class="d-flex justify-content-between align-items-start order-transaction-layout">
                                    <!-- LEFT -->
                                    <div class="d-flex gap-3">

                                        <!-- ICON -->
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle shadow-sm"
                                            style="width:55px;height:55px;background:linear-gradient(135deg,#fff3cd,#ffe69c);">
                                            <i class="bi bi-building text-warning fs-5"></i>
                                        </div>

                                        <!-- DETAILS -->
                                        <div>

                                            <!-- NAME -->
                                            <div class="fw-bold text-dark fs-6">
                                                {{ strtoupper(optional($transaction['ad'])->name ?? 'AREA DISTRIBUTOR') }}
                                            </div>

                                            <!-- CONTACT -->
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-telephone text-info me-1"></i>
                                                {{ optional($transaction['ad'])->contact_number ?? 'No contact number' }}
                                            </div>

                                            <!-- ADDRESS (🔥 NEW) -->
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-geo-alt text-danger me-1"></i>
                                                {{ optional($transaction['ad'])->address ?? 'No address available' }}
                                            </div>

                                            <!-- DATE -->
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-clock me-1"></i>
                                                {{ $transaction['created_at']->format('M d, Y • h:i A') }}
                                            </div>

                                            <!-- BADGES -->
                                            <div class="d-flex flex-wrap gap-2 mt-2">

                                                <!-- PAYMENT -->
                                                <span class="badge 
                                                    @if($transaction['payment_method'] == 'cash') bg-success
                                                    @elseif(in_array($transaction['payment_method'], ['gcash','bank_transfer'])) bg-info
                                                    @else bg-warning text-dark
                                                    @endif px-3 py-2 rounded-pill">

                                                    <i class="bi bi-wallet2 me-1"></i>
                                                    {{ ucwords(str_replace('_', ' ', $transaction['payment_method'] ?? 'cash')) }}
                                                </span>

                                                <!-- DELIVERY -->
                                                <span class="badge 
                                                    @if($transaction['delivery_type'] == 'delivery') bg-primary
                                                    @else bg-secondary
                                                    @endif px-3 py-2 rounded-pill">

                                                    <i class="bi 
                                                        @if($transaction['delivery_type'] == 'delivery') bi-truck
                                                        @else bi-box-seam
                                                        @endif me-1"></i>

                                                    {{ ucfirst($transaction['delivery_type'] ?? 'pickup') }}
                                                </span>

                                                @if($deliveryFee > 0)
                                                    <span class="badge bg-light text-primary border px-3 py-2 rounded-pill">
                                                        <i class="bi bi-plus-circle me-1"></i>
                                                        Delivery Fee ₱{{ number_format($deliveryFee, 2) }}
                                                    </span>
                                                @endif

                                            </div>

                                            <!-- STATUS -->
                                            <div class="mt-2">
                                                @php
                                                    $statusColors = [
                                                        'Pending' => 'warning text-dark',
                                                        'For Delivery' => 'info text-dark',
                                                        'Completed' => 'success',
                                                        'Cancelled' => 'danger'
                                                    ];
                                                @endphp

                                                <span id="status-badge-{{ $transaction['id'] }}">
                                                    <span class="badge bg-{{ $statusColors[$transaction['status']] ?? 'secondary' }} px-3 py-2 rounded-pill">
                                                        {{ $transaction['status'] }}
                                                    </span>
                                                </span>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- RIGHT SIDE -->
                                    <div class="text-end">

                                        <!-- PRICE BREAKDOWN -->
                                        <div class="rounded-4 border bg-light p-2 px-3 d-inline-block text-start order-action-panel">
                                            <div class="d-flex justify-content-between gap-3 small text-muted">
                                                <span>Subtotal</span>
                                                <span class="fw-semibold text-dark">₱{{ number_format($subtotal, 2) }}</span>
                                            </div>

                                            @if($deliveryFee > 0)
                                                <div class="d-flex justify-content-between gap-3 small text-muted mt-1">
                                                    <span>
                                                        <i class="bi bi-truck text-primary me-1"></i>Delivery
                                                    </span>
                                                    <span class="fw-semibold text-primary">+₱{{ number_format($deliveryFee, 2) }}</span>
                                                </div>
                                            @endif

                                            <div class="border-top mt-2 pt-2 d-flex justify-content-between gap-3 align-items-center">
                                                <span class="small fw-semibold text-muted">Total</span>
                                                <span class="fw-bold text-primary fs-5">₱{{ number_format($grandTotal, 2) }}</span>
                                            </div>
                                        </div>

                                        <!-- AREAS -->
                                        <div class="mt-2 d-flex flex-wrap justify-content-end gap-1 order-areas">
                                            @forelse(optional($transaction['ad'])->areas ?? [] as $area)
                                                <span class="badge rounded-pill bg-light text-dark border">
                                                    <i class="bi bi-map text-danger me-1"></i>
                                                    {{ $area->area_name }}
                                                </span>
                                            @empty
                                                <span class="badge bg-secondary">No Area</span>
                                            @endforelse
                                        </div>
                                        
                                        <div class="mt-3" id="order-actions-{{ $transaction['id'] }}">
                                            @if($canCancelOrder)
                                                <div class="cancel-window-note mb-2">
                                                    <i class="bi bi-hourglass-split"></i>
                                                    {{ $minutesLeftToCancel }} min left to cancel
                                                </div>
                                            @elseif($isPendingOrder)
                                                <div class="cancel-window-note is-locked mb-2">
                                                    <i class="bi bi-lock"></i>
                                                    Cancel window ended
                                                </div>
                                            @endif

                                            <div class="order-actions">
                                                @if($canCancelOrder)
                                                    <button type="button"
                                                            class="btn btn-outline-danger btn-sm cancel-order-btn"
                                                            data-id="{{ $transaction['id'] }}">
                                                        <i class="bi bi-x-circle me-1"></i> Cancel Order
                                                    </button>
                                                @endif

                                                @if(!in_array($transaction['status'], ['Completed','Cancelled','Pending']))
                                                <button type="button"
                                                        class="btn btn-success btn-sm complete-btn"
                                                        data-id="{{ $transaction['id'] }}">
                                                    <i class="bi bi-check-circle me-1"></i> Mark Completed
                                                </button>
                                                @endif
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- ITEMS -->
                                <div class="mt-3 small border-top pt-2">

                                    @foreach($transaction['items'] as $item)
                                        <div class="d-flex justify-content-between">
                                            <span class="text-dark fw-semibold">{{ $item['name'] }}</span>
                                            <span class="text-muted">
                                                Qty: {{ $item['qty'] }}
                                                @if(isset($item['total']))
                                                    <span class="ms-2 text-dark fw-semibold">₱{{ number_format($item['total'], 2) }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach

                                </div>

                            </div>
                            @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-box fs-1"></i>
                                <p>No order transaction yet</p>
                            </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if(auth()->user()->role == "Client")
        <section class="welcome">
            <div class="row">    
                <div class="col-lg-10 col-xl-5 text-left">
                    <div class="card w-100 stretch">
                        <div class="card-body position-relative">
                            <div class='row'>
                                <div class="col-lg-12 col-xl-6" style="font-size: 12px; height: 80px;">
                                    Serial Number ID: {{$customer->serial->serial_number}}<br>
                                    Name: {{$customer->name}} <br>
                                    Contact No.: {{$customer->number}} <br>
                                    Registered: {{date('M d, Y',strtotime($customer->created_at))}} <br>
                                </div>
                            </div>
                            <div class="school-img d-sm-block">
                                <img src="{{asset('images/background.png')}}" class="img-fluid" alt="" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h5 class="mb-2 fw-bold" style="color: #5BC2E7;">Purchase History</h5>
            
            <div class="row mt-3">
                <div class="col-lg-12">
                    <div class="w-100">
                        <div class="transaction-card">
                            @if($transactions_details->isNotEmpty())
                                <div class="d-flex justify-content-end align-items-center mb-3">
                                    <label for="showEntriesClient" class="me-2 mb-0 text-muted" style="font-size: 14px;">Show</label>
                                    <select id="showEntriesClient" class="form-select form-select-sm" style="width: auto; min-width: 80px;">
                                        <option value="10" selected>10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span class="ms-2 text-muted" style="font-size: 14px;">entries</span>
                                </div>

                                <div class="transaction-list" id="transactionListClient">
                                    @foreach($transactions_details as $transaction)
                                        <div class="transaction-item" style="display: none;">
                                            <div class="mb-3 p-3" style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0;">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center flex-grow-1">
                                                        <div class="flex-shrink-0 me-3">
                                                            <div class="avatar-circle" style="width: 55px; height: 55px;">
                                                                <img src="{{ optional($transaction->dealer)->avatar ? asset($transaction->dealer->avatar) : asset('design/assets/images/profile/user-1.png') }}" 
                                                                    alt="{{ optional($transaction->dealer)->name ?? 'Dealer' }}"
                                                                    class="rounded-circle w-100 h-100 object-fit-cover"
                                                                    style="border: 2px solid #e2e8f0;">
                                                            </div>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-0 fw-bold text-dark">
                                                                {{ strtoupper($transaction->dealer->name ?? 'Unknown Dealer') }}
                                                            </h6>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <small class="text-muted">{{ date('m/d/Y', strtotime($transaction->created_at)) }}</small>
                                                                <small class="text-muted">{{ date('h:i A', strtotime($transaction->created_at)) }}</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <div class="badge rounded-circle d-flex align-items-center justify-content-center" 
                                                            style="width: 30px; height: 30px; background-color: #5BC2E7; font-size: 14px; font-weight: bold;">
                                                            {{ number_format($transaction->qty, 0) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-4">
                                    <i class="ti ti-file-invoice fs-1 text-muted mb-3"></i>
                                    <p class="text-muted">No transactions yet</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // IndexedDB Configuration
    const DB_NAME = 'GazLiteDB';
    const DB_VERSION = 1;
    const STORE_NAME = 'transactions';
    
    let hasAutoSyncedThisSession = false;

    document.addEventListener("DOMContentLoaded", function () {
        const showEntries = document.getElementById("showEntries");
        const transactionListContainer = document.querySelector("#transactionList");
        
        if (showEntries && transactionListContainer) {
            let allItems = Array.from(transactionListContainer.querySelectorAll(".transaction-item"));
            let currentShown = 10;

            function updateDisplay() {
                const limit = parseInt(showEntries.value);
                
                allItems.forEach((item, index) => {
                    if (index < limit) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
                
                currentShown = Math.min(limit, allItems.length);
            }

            showEntries.addEventListener("change", function() {
                currentShown = parseInt(this.value);
                updateDisplay();
            });

            updateDisplay();
        }

        const showEntriesClient = document.getElementById("showEntriesClient");
        const transactionListClientContainer = document.querySelector("#transactionListClient");
        
        if (showEntriesClient && transactionListClientContainer) {
            let allItemsClient = Array.from(transactionListClientContainer.querySelectorAll(".transaction-item"));
            let currentShownClient = 10;

            function updateDisplayClient() {
                const limit = parseInt(showEntriesClient.value);
                
                allItemsClient.forEach((item, index) => {
                    if (index < limit) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
                
                currentShownClient = Math.min(limit, allItemsClient.length);
            }

            showEntriesClient.addEventListener("change", function() {
                currentShownClient = parseInt(this.value);
                updateDisplayClient();
            });

            updateDisplayClient();
        }

        checkPendingTransactions();
        
        if (navigator.onLine) {
            autoSyncOnLoad();
        }
    });

    function openGazLiteDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            
            request.onerror = (e) => {
                console.error('Database failed to open:', e.target.error);
                reject(e.target.error);
            };

            request.onsuccess = (e) => {
                console.log('Database opened successfully');
                resolve(e.target.result);
            };

            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                console.log('Database upgrade needed, version:', e.oldVersion);
                
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const objectStore = db.createObjectStore(STORE_NAME, { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    
                    objectStore.createIndex('customer_id', 'customer_id', { unique: false });
                    objectStore.createIndex('client_id', 'client_id', { unique: false });
                    objectStore.createIndex('created_at', 'created_at', { unique: false });
                    objectStore.createIndex('payment_method', 'payment_method', { unique: false });
                    objectStore.createIndex('status', 'status', { unique: false });
                    
                    console.log('Object store "transactions" created');
                }
            };
        });
    }

    async function getOfflineTransactions() {
        try {
            const db = await openGazLiteDB();
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            
            return new Promise((resolve, reject) => {
                const request = store.getAll();
                request.onsuccess = () => {
                    console.log('Retrieved transactions:', request.result);
                    resolve(request.result);
                };
                request.onerror = () => reject(request.error);
            });
        } catch (error) {
            console.error('Error getting offline transactions:', error);
            return [];
        }
    }

    async function deleteOfflineTransaction(id) {
        try {
            const db = await openGazLiteDB();
            const tx = db.transaction(STORE_NAME, 'readwrite');
            const store = tx.objectStore(STORE_NAME);
            
            return new Promise((resolve, reject) => {
                const request = store.delete(id);
                request.onsuccess = () => {
                    console.log('Deleted transaction:', id);
                    resolve(true);
                };
                request.onerror = () => reject(request.error);
            });
        } catch (error) {
            console.error('Error deleting offline transaction:', error);
            return false;
        }
    }

    async function checkPendingTransactions() {
        try {
            const transactions = await getOfflineTransactions();
            const statusDiv = document.getElementById('syncStatus');
            const syncButton = document.getElementById('syncButton');
            const pendingCountEl = document.getElementById('pendingCount');
            
            console.log('Pending transactions count:', transactions.length);
            
            if (transactions && transactions.length > 0) {
                syncButton.classList.remove('d-none');
                statusDiv.classList.remove('d-none');
                pendingCountEl.textContent = transactions.length;
            } else {
                syncButton.classList.add('d-none');
                statusDiv.classList.add('d-none');
            }
        } catch (error) {
            console.error('Error checking pending transactions:', error);
        }
    }

    async function autoSyncOnLoad() {
        if (hasAutoSyncedThisSession) {
            console.log('Auto-sync already performed this session');
            return;
        }
        
        Swal.fire({
            title: 'Checking Transactions',
            html: 'Please wait while we check for offline transactions...',
            icon: 'info',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        await new Promise(resolve => setTimeout(resolve, 500));
        
        const transactions = await getOfflineTransactions();
        
        console.log('Checking for auto-sync...', {
            hasTransactions: transactions && transactions.length > 0,
            count: transactions ? transactions.length : 0,
            isOnline: navigator.onLine
        });
        
        if (transactions && transactions.length > 0) {
            console.log('Starting auto-sync with', transactions.length, 'transactions');
            
            Swal.update({
                title: 'Syncing Transactions',
                html: `Syncing ${transactions.length} offline transaction(s)...<br><span id="syncProgress" style="font-weight: bold; color: #5BC2E7;">0/${transactions.length}</span>`,
            });
            
            await performAutoSync();
            hasAutoSyncedThisSession = true;
        } else {
            console.log('No transactions to sync');
            
            Swal.fire({
                icon: 'success',
                title: 'All Synced!',
                text: 'No offline transactions found. Everything is up to date.',
                confirmButtonColor: '#5BC2E7',
                timer: 2000,
                timerProgressBar: true
            });
            
            hasAutoSyncedThisSession = true;
        }
    }

    window.addEventListener('online', async () => {
        console.log('Network connection restored');
        
        if (!hasAutoSyncedThisSession) {
            Swal.fire({
                title: 'Connection Restored',
                html: 'Checking for offline transactions...',
                icon: 'info',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            await new Promise(resolve => setTimeout(resolve, 500));
            
            const transactions = await getOfflineTransactions();
            
            console.log('Online event - checking transactions:', {
                hasTransactions: transactions && transactions.length > 0,
                count: transactions ? transactions.length : 0
            });
            
            if (transactions && transactions.length > 0) {
                console.log('Starting auto-sync from online event');
                
                Swal.update({
                    title: 'Syncing Transactions',
                    html: `Syncing ${transactions.length} offline transaction(s)...<br><span id="syncProgress" style="font-weight: bold; color: #5BC2E7;">0/${transactions.length}</span>`,
                });
                
                await performAutoSync();
                hasAutoSyncedThisSession = true;
            } else {
                console.log('No transactions to sync from online event');
                
                Swal.fire({
                    icon: 'success',
                    title: 'All Synced!',
                    text: 'No offline transactions found. Everything is up to date.',
                    confirmButtonColor: '#5BC2E7',
                    timer: 2000,
                    timerProgressBar: true
                });
                
                hasAutoSyncedThisSession = true;
            }
        } else {
            console.log('Auto-sync already performed this session');
            await checkPendingTransactions();
        }
    });

    window.addEventListener('offline', () => {
        console.log('Network connection lost');
    });

    async function performAutoSync() {
        const transactions = await getOfflineTransactions();
        
        if (!transactions || transactions.length === 0) {
            Swal.close();
            return;
        }
        
        let syncedCount = 0;
        let failedCount = 0;
        const failedTransactions = [];
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            Swal.fire({
                icon: 'error',
                title: 'Security Error',
                text: 'Security token not found. Please refresh the page.',
                confirmButtonColor: '#5BC2E7'
            });
            return;
        }
        
        for (let i = 0; i < transactions.length; i++) {
            const transaction = transactions[i];
            
            const progressEl = document.getElementById('syncProgress');
            if (progressEl) {
                progressEl.textContent = `${i + 1}/${transactions.length}`;
            }
            
            try {
                let itemId = null;
                let customerId = null;
                let dealerId = null;
                let quantity = 1;
                let paymentMethod = 'cash';
                
                // Extract item ID - handle multiple formats
                if (transaction.items && Array.isArray(transaction.items) && transaction.items.length > 0) {
                    itemId = transaction.items[0].id;
                } else if (transaction.item_id) {
                    itemId = parseInt(transaction.item_id) || transaction.item_id;
                }
                
                // Extract customer ID - handle multiple formats
                if (transaction.customer_id) {
                    customerId = parseInt(transaction.customer_id) || transaction.customer_id;
                } else if (transaction.client_id) {
                    customerId = parseInt(transaction.client_id) || transaction.client_id;
                }
                
                // Extract dealer ID if available
                if (transaction.dealer_id) {
                    dealerId = parseInt(transaction.dealer_id) || transaction.dealer_id;
                }
                
                // Extract quantity - handle multiple formats
                if (transaction.quantity) {
                    quantity = parseInt(transaction.quantity);
                } else if (transaction.qty) {
                    quantity = parseInt(transaction.qty);
                } else if (transaction.items && Array.isArray(transaction.items)) {
                    quantity = transaction.items.reduce((sum, item) => sum + (parseInt(item.quantity) || 1), 0);
                }
                
                // Extract payment method
                if (transaction.payment_method) {
                    paymentMethod = transaction.payment_method;
                } else if (transaction.paymentMethod) {
                    paymentMethod = transaction.paymentMethod;
                }
                
                // Validate required fields
                if (!itemId) {
                    throw new Error('Item ID is missing');
                }
                if (!customerId) {
                    throw new Error('Customer ID is missing');
                }
                if (isNaN(quantity) || quantity < 1) {
                    throw new Error('Invalid quantity: ' + quantity);
                }
                
                // Build transaction data
                const transactionData = {
                    item_id: itemId,
                    qty: quantity,
                    customer_id: customerId,
                    payment_method: paymentMethod
                };
                
                // Add dealer_id if available, otherwise it will use authenticated user
                if (dealerId) {
                    transactionData.dealer_id = dealerId;
                }
                
                const response = await fetch('/api/transactions/store', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(transactionData)
                });
                
                let result;
                try {
                    const responseText = await response.text();
                    if (!responseText) {
                        throw new Error('Empty response from server');
                    }
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`Server returned invalid response (Status: ${response.status})`);
                }
                
                if (response.ok && result.success) {
                    await deleteOfflineTransaction(transaction.id);
                    syncedCount++;
                } else {
                    let errorMessage = 'Unknown error';
                    
                    if (result.message) {
                        errorMessage = result.message;
                    } else if (result.errors) {
                        if (typeof result.errors === 'object') {
                            errorMessage = Object.values(result.errors).flat().join(', ');
                        } else {
                            errorMessage = result.errors;
                        }
                    }
                    
                    failedCount++;
                    failedTransactions.push({
                        transaction: transaction,
                        error: errorMessage,
                        status: response.status
                    });
                }
                
            } catch (error) {
                failedCount++;
                failedTransactions.push({
                    transaction: transaction,
                    error: error.message
                });
            }
            
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        
        const syncedCountEl = document.getElementById('syncedCount');
        const failedCountEl = document.getElementById('failedCount');
        const pendingCountEl = document.getElementById('pendingCount');
        const lastSyncEl = document.getElementById('lastSync');
        
        if (syncedCountEl) syncedCountEl.textContent = syncedCount;
        if (failedCountEl) failedCountEl.textContent = failedCount;
        if (pendingCountEl) pendingCountEl.textContent = Math.max(0, transactions.length - syncedCount - failedCount);

        const now = new Date();
        if (lastSyncEl) lastSyncEl.textContent = now.toLocaleString();

        if (syncedCount > 0) {
            if (failedCount > 0) {
                const errorSummary = failedTransactions.slice(0, 3).map((ft, idx) => {
                    const txId = ft.transaction.id || ft.transaction.timestamp || (idx + 1);
                    return `<li class="text-start"><strong>Transaction ${txId}:</strong> ${ft.error}</li>`;
                }).join('');
                
                const moreErrors = failedCount > 3 ? `<li class="text-start">...and ${failedCount - 3} more</li>` : '';
                
                Swal.fire({
                    icon: 'warning',
                    title: 'Partial Success',
                    html: `
                        <div class="text-center mb-3">
                            <p>Successfully synced <strong class="text-success">${syncedCount}</strong> transaction(s)</p>
                            <p><strong class="text-danger">${failedCount}</strong> failed to sync</p>
                        </div>
                        <div class="mt-3">
                            <p class="text-start mb-2"><strong>Failed Transactions:</strong></p>
                            <ul class="text-muted" style="font-size: 0.9em; max-height: 200px; overflow-y: auto;">
                                ${errorSummary}
                                ${moreErrors}
                            </ul>
                        </div>
                    `,
                    confirmButtonColor: '#5BC2E7',
                    confirmButtonText: 'OK',
                    width: '600px'
                });
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Sync Complete!',
                    html: `
                        <p>Successfully synced <strong>${syncedCount}</strong> transaction(s)</p>
                        <p class="text-muted mt-2">The page will refresh to show updated data</p>
                    `,
                    confirmButtonColor: '#5BC2E7',
                    timer: 2000,
                    timerProgressBar: true
                }).then(() => {
                    window.location.reload();
                });
            }
        } else {
            const firstError = failedTransactions[0];
            let errorDetail = 'Unknown error occurred';
            
            if (firstError) {
                errorDetail = firstError.error;
                
                if (firstError.status === 422) {
                    errorDetail += '<br><small class="text-muted">This usually means some required data is missing or invalid.</small>';
                } else if (firstError.status === 401 || firstError.status === 403) {
                    errorDetail += '<br><small class="text-muted">Authentication failed. Please try logging out and logging back in.</small>';
                } else if (firstError.status >= 500) {
                    errorDetail += '<br><small class="text-muted">Server error. Please contact support if this persists.</small>';
                }
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Sync Failed',
                html: `
                    <p>Failed to sync any transactions.</p>
                    <div class="mt-3 p-3 bg-light rounded">
                        <p class="mb-0"><strong>Error:</strong></p>
                        <p class="text-danger mb-0">${errorDetail}</p>
                    </div>
                `,
                confirmButtonColor: '#5BC2E7'
            });
        }

        await checkPendingTransactions();
    }

    async function syncOfflineTransactions() {
    const button = document.getElementById('syncButton');

    if (!navigator.onLine) {
        Swal.fire({
            icon: 'error',
            title: 'No Internet Connection',
            text: 'Please check your internet connection and try again.',
            confirmButtonColor: '#5BC2E7'
        });
        return;
    }

    const transactions = await getOfflineTransactions();

    if (!transactions || transactions.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No Transactions',
            text: 'No offline transactions to sync',
            confirmButtonColor: '#5BC2E7'
        });
        button.classList.add('d-none');
        const statusDiv = document.getElementById('syncStatus');
        if (statusDiv) statusDiv.classList.add('d-none');
        return;
    }

    button.disabled = true;
    button.classList.add('syncing');
    button.querySelector('span').textContent = 'Syncing...';

    Swal.fire({
        title: 'Syncing Transactions',
        html: `Syncing ${transactions.length} transaction(s)...<br><span id="syncProgress">0/${transactions.length}</span>`,
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    await performAutoSync();

    button.disabled = false;
    button.classList.remove('syncing');
    button.querySelector('span').textContent = 'Sync Offline Transactions';
    }
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {

        const baseUrl = "{{ url('/') }}"; // ✅ Laravel-safe base URL

        document.querySelectorAll('.complete-btn').forEach(btn => {

            btn.addEventListener('click', async function () {

                const id = this.dataset.id;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                if (!csrf) {
                    Swal.fire('Error', 'CSRF token not found. Refresh page.', 'error');
                    return;
                }

                // ✅ FIXED URL (works with /public folder)
                const url = `${baseUrl}/ad-orders/${encodeURIComponent(id)}/complete`;

                try {
                    const remarksResult = await Swal.fire({
                        title: 'Completion Remarks',
                        input: 'textarea',
                        inputLabel: 'Remarks',
                        inputPlaceholder: 'Enter remarks before marking this order as completed',
                        inputAttributes: {
                            maxlength: 500
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Mark Completed',
                        confirmButtonColor: '#198754',
                        cancelButtonText: 'Cancel',
                        preConfirm: (value) => {
                            const remarks = (value || '').trim();

                            if (!remarks) {
                                Swal.showValidationMessage('Remarks are required.');
                                return false;
                            }

                            return remarks;
                        }
                    });

                    if (!remarksResult.isConfirmed) {
                        return;
                    }

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            remarks: remarksResult.value
                        })
                    });

                    const data = await response.json().catch(() => null);

                    if (!response.ok) {
                        throw new Error(data?.message || `Request failed (${response.status})`);
                    }

                    const badgeEl = document.getElementById(`status-badge-${id}`);

                    if (badgeEl) {
                        badgeEl.innerHTML = `
                            <span class="badge bg-success px-3 py-2 rounded-pill">
                                <i class="bi bi-check-circle me-1"></i> Completed
                            </span>`;
                    }

                    this.remove();

                    Swal.fire('Success', data?.message || 'Order completed', 'success');

                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                }

            });

        });

        document.querySelectorAll('.cancel-order-btn').forEach(btn => {

            btn.addEventListener('click', async function () {

                const id = this.dataset.id;
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                if (!csrf) {
                    Swal.fire('Error', 'CSRF token not found. Refresh page.', 'error');
                    return;
                }

                const url = `${baseUrl}/ad-orders/${encodeURIComponent(id)}/cancel`;

                try {
                    const cancelResult = await Swal.fire({
                        title: 'Cancel this order?',
                        html: '<div class="text-muted small">Only pending orders within 1 hour of creation can be cancelled.</div>',
                        icon: 'warning',
                        input: 'textarea',
                        inputLabel: 'Reason',
                        inputPlaceholder: 'Optional cancellation reason',
                        inputAttributes: {
                            maxlength: 500
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Cancel Order',
                        confirmButtonColor: '#dc3545',
                        cancelButtonText: 'Keep Order',
                        reverseButtons: true,
                        preConfirm: (value) => {
                            const remarks = (value || '').trim();

                            if (remarks.length > 500) {
                                Swal.showValidationMessage('Reason may not be greater than 500 characters.');
                                return false;
                            }

                            return remarks;
                        }
                    });

                    if (!cancelResult.isConfirmed) {
                        return;
                    }

                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cancelling';

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            remarks: cancelResult.value
                        })
                    });

                    const data = await response.json().catch(() => null);

                    if (!response.ok) {
                        throw new Error(data?.message || `Request failed (${response.status})`);
                    }

                    const badgeEl = document.getElementById(`status-badge-${id}`);
                    const actionsEl = document.getElementById(`order-actions-${id}`);
                    const cardEl = document.getElementById(`ad-order-card-${id}`);

                    if (badgeEl) {
                        badgeEl.innerHTML = `
                            <span class="badge bg-danger px-3 py-2 rounded-pill">
                                <i class="bi bi-x-circle me-1"></i> Cancelled
                            </span>`;
                    }

                    if (actionsEl) {
                        actionsEl.innerHTML = `
                            <div class="cancel-window-note is-locked">
                                <i class="bi bi-check2-circle"></i>
                                Order cancelled
                            </div>`;
                    }

                    if (cardEl) {
                        cardEl.classList.add('border-danger-subtle');
                    }

                    Swal.fire('Cancelled', data?.message || 'Order cancelled successfully.', 'success');

                } catch (error) {
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-x-circle me-1"></i> Cancel Order';
                    Swal.fire('Error', error.message, 'error');
                }

            });

        });

    });
</script>
@endsection
