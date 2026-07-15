@extends('layouts.header')

@section('content')
<div class="transaction-history-page">
  <div class="content-area-fix">
    <!-- Page Header -->
    <div class="page-header-nya">
      <button class="back-btn btn btn-link p-0" onclick="window.history.back()">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h1 class="page-title mb-0">Transaction History</h1>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <button class="filter-btn btn w-100 d-flex align-items-center justify-content-between" onclick="openFilterModal()">
        <div class="filter-icon d-flex align-items-center">
          <i class="bi bi-sliders me-2"></i>
          <span>Filter Date & Time</span>
        </div>
        <i class="bi bi-chevron-right"></i>
      </button>
    </div>

    <!-- Transactions Container -->
    <div id="transactions-container">
      @php
        // Group transactions by date
        $groupedTransactions = $transactions->groupBy(function($transaction) {
          return \Carbon\Carbon::parse($transaction->created_at)->format('Y-m-d');
        })->sortKeysDesc();
      @endphp

      @forelse($groupedTransactions as $date => $dayTransactions)
        <div class="transaction-group">
          <div class="group-header d-flex justify-content-between align-items-center">
            <span class="group-date">{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}</span>
            <span class="group-total">₱{{ number_format($dayTransactions->sum(function($transaction) { return $transaction->price * $transaction->qty; }), 2) }}</span>
          </div>
          
          @foreach($dayTransactions as $transaction)
            <div class="transaction-card d-flex justify-content-between align-items-start">
              <div class="transaction-details flex-grow-1">
                <div class="transaction-name">
                  {{ $transaction->is_guest ? 'GUEST' : strtoupper($transaction->customer->name ?? 'Unknown Customer') }}
                </div>
                <div class="transaction-info">
                  {{ \Carbon\Carbon::parse($transaction->created_at)->format('g:i A') }} - 
                  {{ $transaction->transaction_type ?? 'Sales Invoice' }}
                </div>
              </div>
              <div class="transaction-actions d-flex gap-2 align-items-center">
                <button class="transaction__badge transaction__badge--view btn" onclick="openDetailsModal({{ $transaction->id }})">
                  View Details
                </button>
              </div>
            </div>
          @endforeach
        </div>
      @empty
        <div class="text-center py-5">
          <p class="text-muted">No transaction history found.</p>
        </div>
      @endforelse
    </div>

    <!-- Filter Modal -->
    <div id="filterModal" class="filter-modal">
      <div class="filter-modal__dialog">
        <div class="filter-modal__header d-flex justify-content-between align-items-center border-bottom">
          <h2 class="filter-modal__title mb-0">Reset</h2>
          <button class="filter-modal__close btn btn-link p-0" onclick="closeFilterModal()">
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="filter-modal__body">
          <div class="filter-modal__option mb-3">
            <label class="filter-modal__radio d-flex align-items-center position-relative">
              <input type="radio" name="dateFilter" value="90days" checked class="position-absolute opacity-0">
              <span class="filter-modal__radio-text flex-grow-1">in the last 90 days</span>
              <span class="filter-modal__radio-indicator"></span>
            </label>
          </div>

          <div class="filter-modal__option mb-3">
            <label class="filter-modal__radio d-flex align-items-center position-relative">
              <input type="radio" name="dateFilter" value="custom" class="position-absolute opacity-0">
              <span class="filter-modal__radio-text flex-grow-1">Choose the date</span>
              <span class="filter-modal__radio-indicator"></span>
            </label>
          </div>

          <div class="filter-modal__date-range" id="dateRangeSection">
            <div class="row g-3">
              <div class="col-6">
                <div class="filter-modal__input-group d-flex flex-column">
                  <label class="filter-modal__label">Starting from</label>
                  <input type="date" class="filter-modal__input form-control" id="startDate">
                </div>
              </div>

              <div class="col-6">
                <div class="filter-modal__input-group d-flex flex-column">
                  <label class="filter-modal__label">Until</label>
                  <input type="date" class="filter-modal__input form-control" id="endDate">
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="filter-modal__footer">
          <button class="filter-modal__submit btn w-100" onclick="applyFilter()">Filter</button>
        </div>
      </div>
    </div>

    <!-- Transaction Details Modal -->
    <div id="detailsModal" class="details-modal">
      <div class="details-modal__dialog">
        <div class="details-modal__header">
          <h2 class="details-modal__title">Transaction Details</h2>
          <button class="details-modal__close btn btn-link p-0" onclick="closeDetailsModal()">
            <i class="bi bi-x"></i>
          </button>
        </div>

        <div class="details-modal__body" id="detailsContent">
          <!-- Content will be loaded dynamically -->
          <div class="details-loading text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden transaction data for JavaScript -->
    <script id="transactionData" type="application/json">
      {!! json_encode($transactions->map(function($t) {
        return [
          'id' => $t->id,
          'transaction_id' => $t->transaction_id,
          'item' => $t->item,
          'item_description' => $t->item_description,
          'price' => $t->price,
          'qty' => $t->qty,
          'total' => $t->price * $t->qty,
          'points_dealer' => $t->points_dealer,
          'points_client' => $t->points_client,
          'payment_method' => $t->payment_method ?? 'Cash',
          'status' => $t->status ?? 'Paid',
          'customer_name' => $t->customer->name ?? 'Unknown Customer',
          'dealer_name' => $t->dealer->name ?? 'N/A',
          'client_name' => $t->client->name ?? 'N/A',
          'created_at' => \Carbon\Carbon::parse($t->created_at)->format('F j, Y g:i A'),
          'updated_at' => \Carbon\Carbon::parse($t->updated_at)->format('F j, Y g:i A'),
        ];
      })) !!}
    </script>
  </div>
</div>
@endsection

@section('css')
<style>
  .back-btn {
    background: none;
    border: none;
    color: #666;
    font-size: 18px;
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s ease;
    text-decoration: none;
  }

  .back-btn:hover {
    color: #4A90E2;
  }

  .page-header-nya {
    background: #fff;
    padding: 20px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px !important;
    position: relative;
    outline: 0.2px solid #e1e1e1ff;
    margin-top: -80px;
  }

  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #4A90E2;
    margin: 0;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
  }

  .filter-section {
    background: #fff;
    margin-top: -9px;
    margin-bottom: 15px;
  }

  .filter-btn {
    width: 100%;
    background: none;
    border: none;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    font-size: 16px;
    color: #333;
    font-weight: 500;
    transition: background-color 0.2s ease;
    text-align: left;
  }

  .filter-btn:hover {
    background: #f8f9fa;
  }

  .filter-icon {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* Filter Modal Styles */
  .filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999 !important;
    align-items: flex-end;
    justify-content: center;
  }

  .filter-modal.active {
    display: flex;
  }

  .filter-modal__dialog {
    background: #fff;
    border-radius: 20px 20px 0 0;
    width: 100%;
    max-width: 500px;
    animation: slideUP 0.3s ease-out;
  }

  @keyframes slideUP {
    from {
      transform: translateY(100%);
    }
    to {
      transform: translateY(0);
    }
  }

  .filter-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e8e8e8;
  }

  .filter-modal__title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
  }

  .filter-modal__close {
    background: none;
    border: none;
    font-size: 28px;
    color: #2c3e50;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
    text-decoration: none;
  }

  .filter-modal__close:hover {
    color: #7f8c8d;
  }

  .filter-modal__body {
    padding: 24px;
  }

  .filter-modal__option {
    margin-bottom: 20px;
  }

  .filter-modal__radio {
    display: flex;
    align-items: center;
    cursor: pointer;
    position: relative;
    font-size: 16px;
    color: #2c3e50;
    padding: 4px 0;
  }

  .filter-modal__radio input[type="radio"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
  }

  .filter-modal__radio-text {
    flex: 1;
    font-weight: 400;
  }

  .filter-modal__radio-indicator {
    width: 24px;
    height: 24px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    position: relative;
    transition: all 0.2s ease;
  }

  .filter-modal__radio input[type="radio"]:checked ~ .filter-modal__radio-indicator {
    border-color: #5dade2;
    background: #fff;
  }

  .filter-modal__radio input[type="radio"]:checked ~ .filter-modal__radio-indicator::after {
    content: '';
    position: absolute;
    width: 12px;
    height: 12px;
    background: #5dade2;
    border-radius: 50%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
  }

  .filter-modal__date-range {
    margin-top: 20px;
    opacity: 0.5;
    pointer-events: none;
    transition: opacity 0.2s ease;
  }

  .filter-modal__date-range.active {
    opacity: 1;
    pointer-events: auto;
  }

  .filter-modal__input {
    background: #f8f9fa;
    border: none;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 14px;
    color: #2c3e50;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
  }

  .filter-modal__input:focus {
    outline: 2px solid #5dade2;
    outline-offset: 0;
    box-shadow: none;
    background: #f8f9fa;
  }

  .filter-modal__input-group {
    display: flex;
    flex-direction: column;
  }

  .filter-modal__label {
    font-size: 13px;
    color: #95a5a6;
    margin-bottom: 8px;
    font-weight: 400;
  }

  .filter-modal__footer {
    padding: 16px 24px 24px;
  }

  .filter-modal__submit {
    width: 100%;
    background: #5dade2;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 16px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease;
  }

  .filter-modal__submit:hover {
    background: #3498db;
  }

  /* Transaction Details Modal */
  .details-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000 !important;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .details-modal.active {
    display: flex;
  }

  .details-modal__dialog {
    background: #fff;
    border-radius: 16px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    animation: scaleIn 0.3s ease-out;
  }

  @keyframes scaleIn {
    from {
      transform: scale(0.9);
      opacity: 0;
    }
    to {
      transform: scale(1);
      opacity: 1;
    }
  }

  .details-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #e8e8e8;
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 1;
  }

  .details-modal__title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
  }

  .details-modal__close {
    background: none;
    border: none;
    font-size: 28px;
    color: #2c3e50;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
    text-decoration: none;
  }

  .details-modal__close:hover {
    color: #7f8c8d;
  }

  .details-modal__body {
    padding: 24px;
  }

  .detail-section {
    margin-bottom: 24px;
  }

  .detail-section:last-child {
    margin-bottom: 0;
  }

  .detail-section__title {
    font-size: 14px;
    font-weight: 600;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
  }

  .detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
  }

  .detail-row:last-child {
    border-bottom: none;
  }

  .detail-label {
    font-size: 15px;
    color: #7f8c8d;
    font-weight: 500;
  }

  .detail-value {
    font-size: 15px;
    color: #2c3e50;
    font-weight: 600;
    text-align: right;
  }

  .detail-value--highlight {
    color: #5dade2;
    font-size: 18px;
  }

  .status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
  }

  .status-badge--paid {
    background: #e8f5e9;
    color: #2e7d32;
  }

  .status-badge--pending {
    background: #fff3e0;
    color: #f57c00;
  }

  .status-badge--cancelled {
    background: #ffebee;
    color: #c62828;
  }

  .payment-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: #e3f2fd;
    color: #1976d2;
  }

  @media (max-width: 480px) {
    .filter-modal__dialog {
      max-width: 100%;
    }

    .details-modal__dialog {
      max-width: 100%;
      border-radius: 12px;
    }

    .details-modal__header,
    .details-modal__body {
      padding: 20px;
    }
  }

  .transaction-group {
    margin-bottom: 25px;
    padding: 0 20px;
  }

  .group-header {
    padding: 15px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0px;
  }

  .group-date {
    font-size: 15px;
    font-weight: 600;
    color: #2c3e50;
  }

  .group-total {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
  }

  .transaction-card {
    background: #fff;
    padding: 24px 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
  }

  .transaction-card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
  }

  .transaction-details {
    flex-grow: 1;
  }

  .transaction-name {
    font-size: 18px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 6px;
  }

  .transaction-info {
    font-size: 14px;
    color: #7f8c8d;
    font-weight: 400;
  }

  .transaction-actions {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  /* Transaction Badge Styles */
  .transaction__badge {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: auto;
    white-space: nowrap;
  }

  .transaction__badge--view {
    background: #5dade2;
    color: #fff;
  }

  .transaction__badge--view:hover {
    background: #3498db !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(93, 173, 226, 0.3);
  }

  @media (max-width: 480px) {
    .transaction-group {
      padding: 0 15px;
    }

    .transaction-card {
      padding: 18px 16px;
      flex-direction: row;
      align-items: center;
      gap: 12px;
    }
    
    .transaction-details {
      flex: 1;
      padding-right: 0;
    }

    .transaction-actions {
      flex-shrink: 0;
      align-self: center;
    }
    
    .transaction__badge {
      padding: 10px 20px;
      font-size: 13px;
      width: 100%;
      min-width: auto;
    }
    
    .transaction-name {
      font-size: 16px;
    }
    
    .transaction-info {
      font-size: 13px;
    }
    
    .group-header {
      padding: 12px 0;
    }
    
    .page-header-nya {
      padding: 15px;
    }
    
    .page-title {
      font-size: 18px;
    }

    .group-date {
      font-size: 14px;
    }

    .group-total {
      font-size: 16px;
    }
  }
</style>

<script>
    let transactionsData = [];
    let allTransactions = [];

    function openFilterModal() {
    document.getElementById('filterModal').classList.add('active');
    document.body.style.overflow = 'hidden';
    }

    function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('active');
    document.body.style.overflow = '';
    }

    function applyFilter() {
    const selectedFilter = document.querySelector('input[name="dateFilter"]:checked').value;
    let filteredTransactions = [];

    const now = new Date();
    now.setHours(23, 59, 59, 999);

    if (selectedFilter === '90days') {
        // Filter last 90 days
        const ninetyDaysAgo = new Date(now);
        ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
        ninetyDaysAgo.setHours(0, 0, 0, 0);
        
        filteredTransactions = allTransactions.filter(transaction => {
        const transactionDate = new Date(transaction.created_at);
        return transactionDate >= ninetyDaysAgo && transactionDate <= now;
        });
    } else if (selectedFilter === 'custom') {
        // Filter by custom date range
        const startDateInput = document.getElementById('startDate').value;
        const endDateInput = document.getElementById('endDate').value;
        
        if (!startDateInput || !endDateInput) {
        alert('Please select both start and end dates');
        return;
        }
        
        const startDate = new Date(startDateInput);
        startDate.setHours(0, 0, 0, 0);
        
        const endDate = new Date(endDateInput);
        endDate.setHours(23, 59, 59, 999);
        
        if (startDate > endDate) {
        alert('Start date must be before end date');
        return;
        }
        
        filteredTransactions = allTransactions.filter(transaction => {
        const transactionDate = new Date(transaction.created_at);
        return transactionDate >= startDate && transactionDate <= endDate;
        });
    }

    // Update the display
    renderTransactions(filteredTransactions);
    closeFilterModal();
    }

    function renderTransactions(transactions) {
    const container = document.getElementById('transactions-container');

    if (transactions.length === 0) {
        container.innerHTML = `
        <div class="text-center py-5">
            <p class="text-muted">No transactions found for the selected date range.</p>
        </div>
        `;
        return;
    }

    // Group transactions by date
    const groupedByDate = {};
    transactions.forEach(transaction => {
        const date = new Date(transaction.created_at);
        const dateKey = date.toISOString().split('T')[0];
        
        if (!groupedByDate[dateKey]) {
        groupedByDate[dateKey] = [];
        }
        groupedByDate[dateKey].push(transaction);
    });

    // Sort dates in descending order
    const sortedDates = Object.keys(groupedByDate).sort().reverse();

    let html = '';
    sortedDates.forEach(dateKey => {
        const dayTransactions = groupedByDate[dateKey];
        const date = new Date(dateKey);
        const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
        });
        
        // Calculate total for the day
        const dayTotal = dayTransactions.reduce((sum, t) => sum + t.total, 0);
        
        html += `
        <div class="transaction-group">
            <div class="group-header d-flex justify-content-between align-items-center">
            <span class="group-date">${formattedDate}</span>
            <span class="group-total">₱${dayTotal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        `;
        
        dayTransactions.forEach(transaction => {
        const time = new Date(transaction.created_at).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
        
        html += `
            <div class="transaction-card d-flex justify-content-between align-items-start">
            <div class="transaction-details flex-grow-1">
                <div class="transaction-name">
                ${transaction.customer_name.toUpperCase()}
                </div>
                <div class="transaction-info">
                ${time} - Sales Invoice
                </div>
            </div>
            <div class="transaction-actions d-flex gap-2 align-items-center">
                <button class="transaction__badge transaction__badge--view btn" onclick="openDetailsModal(${transaction.id})">
                View Details
                </button>
            </div>
            </div>
        `;
        });
        
        html += `</div>`;
    });

    container.innerHTML = html;
    }

    function openDetailsModal(transactionId) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');

    // Find transaction data
    const transaction = transactionsData.find(t => t.id === transactionId);

    if (transaction) {
        content.innerHTML = `
        <div class="detail-section">
            <div class="detail-section__title">Transaction Information</div>
            <div class="detail-row">
            <span class="detail-label">Transaction ID</span>
            <span class="detail-value">#${transaction.transaction_id}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Date & Time</span>
            <span class="detail-value">${transaction.created_at}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                <span class="status-badge status-badge--${transaction.status.toLowerCase()}">${transaction.status}</span>
            </span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Payment Method</span>
            <span class="detail-value">
                <span class="payment-badge">${transaction.payment_method.toUpperCase()}</span>
            </span>
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section__title">Customer Details</div>
            <div class="detail-row">
            <span class="detail-label">Customer Name</span>
            <span class="detail-value">${transaction.customer_name}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Dealer</span>
            <span class="detail-value">${transaction.dealer_name}</span>
            </div>
            <div class="detail-row">
            </div>
        </div>

        <div class="detail-section">
            <div class="detail-section__title">Product Information</div>
            <div class="detail-row">
            <span class="detail-label">Item</span>
            <span class="detail-value">${transaction.item}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Description</span>
            <span class="detail-value">${transaction.item_description}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Unit Price</span>
            <span class="detail-value">₱${Number(transaction.price).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Quantity</span>
            <span class="detail-value">${transaction.qty}</span>
            </div>
            <div class="detail-row">
            <span class="detail-label">Total Amount</span>
            <span class="detail-value detail-value--highlight">₱${Number(transaction.total).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
            </div>
        </div>

        <!-- <div class="detail-section">
          <div class="detail-section__title">Points Earned</div>
          <div class="detail-row">
          <span class="detail-label">Dealer Points</span>
          <span class="detail-value">${transaction.points_dealer} pts</span>
          </div>
          <div class="detail-row">
          <span class="detail-label">Client Points</span>
          <span class="detail-value">${transaction.points_client} pts</span>
          </div>
        </div> -->
        `;
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    }

    function closeDetailsModal() {
    document.getElementById('detailsModal').classList.remove('active');
    document.body.style.overflow = '';
    }

    // Handle radio button changes
    document.addEventListener('DOMContentLoaded', function() {
    const dataScript = document.getElementById('transactionData');
    if (dataScript) {
        transactionsData = JSON.parse(dataScript.textContent);
        allTransactions = [...transactionsData];
    }

    const radioButtons = document.querySelectorAll('input[name="dateFilter"]');
    const dateRangeSection = document.getElementById('dateRangeSection');

    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
        if (this.value === 'custom') {
            dateRangeSection.classList.add('active');
        } else {
            dateRangeSection.classList.remove('active');
        }
        });
    });

    // Close modals when clicking outside
    document.getElementById('filterModal').addEventListener('click', function(e) {
        if (e.target === this) {
        closeFilterModal();
        }
    });

    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
        closeDetailsModal();
        }
    });
    });
</script>
@endsection
