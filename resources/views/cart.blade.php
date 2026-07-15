@extends('layouts.header')
@section('content')
<div class="place-order-page">
  <div class="content-area-fix">
    <div class="page-header d-flex align-items-center position-relative py-3 px-3">
      <button class="btn back-btn p-2" onclick="window.location.href='{{ url('products')}}'">
        <i class="bi bi-arrow-left"></i>
      </button>
      <h1 class="page-title position-absolute start-50 translate-middle-x m-0">Process Transaction</h1>
    </div>
    <div class="cart-section">
        <div class="section-header">
            <i class="bi bi-diagram-3-fill"></i> Transaction Type
        </div>

        <div class="payment-option">
            <label class="d-flex align-items-center w-100">
            <input type="radio" name="transaction_type" value="client_transaction" class="me-3" checked>
            <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3">
                <i class="bi bi-person-check-fill"></i>
            </div>
            <div class="payment-details flex-grow-1">
                <div class="payment-name">Client Transaction</div>
                <div class="payment-desc">Process sales transactions and manage client purchases in real time.</div>
            </div>
            </label>
        </div>

        <div class="payment-option">
            <label class="d-flex align-items-center w-100">
            <input type="radio" name="transaction_type" value="ad_order" class="me-3">
            <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="background:#6f42c1; color:white;">
                <i class="bi bi-megaphone-fill"></i>
            </div>
            <div class="payment-details flex-grow-1">
                <div class="payment-name">Order Transaction</div>
                <div class="payment-desc">Process and monitor customer orders efficiently.</div>
            </div>
            </label>
        </div>
    </div>
    <div id="client-transaction-section" class="client-section">
        <div class="client-content">
            <div class="assigned-ads-card d-flex align-items-center" onclick="openCustomerSelection()" style="cursor: pointer;">
                <div class="ads-icon d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="fas fa-user"></i>
                </div>
                <div class="client-info flex-grow-1">
                    <div class="client-label">Assigned Customer</div>
                    <div class="client-name" id="assigned-customer-name">Select a Customer</div>
                    <div class="client-details" id="assigned-customer-details"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="ad-transaction-section" class="client-section" style="display:none;">
        <div class="client-content">
            <div class="assigned-ads-card d-flex align-items-center">
                <div class="ads-icon d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="bi bi-magic"></i>
                </div>

                <div class="client-info flex-grow-1">
                    <div class="client-label">Auto Assigned Area Distributor </div>
                    
                    <div class="client-name" id="assigned-ad-name">
                        Detecting AD...
                    </div>

                    {{-- <div class="client-details" id="assigned-ad-details"
                        style="font-size:11px;color:#999;margin-top:3px;">
                    </div> --}}
                    <div class="client-details" id="assigned-ad-details"></div>
                </div>

                <button class="btn btn-sm btn-outline-primary" onclick="openADSelection()">
                    Change
                </button>
            </div>
            <div class="assigned-ads-card align-items-center">
                <div class="fw-semibold mb-2" style="font-size:13px;">
                    Delivery Method
                </div>
                <div class="d-flex gap-2">
                    <!-- PICKUP -->
                    <label class="delivery-option flex-fill">
                        <input type="radio" name="delivery_type" value="pickup" checked onchange="toggleDeliveryAddress()">
                        <div class="option-box">
                            <i class="bi bi-shop"></i>
                            <span>Pickup</span>
                        </div>
                    </label>
                    <!-- DELIVERY -->
                    <label class="delivery-option flex-fill">
                        <input type="radio" name="delivery_type" value="delivery" onchange="toggleDeliveryAddress()">
                        <div class="option-box">
                            <i class="bi bi-truck"></i>
                            <span>Delivery</span>
                        </div>
                    </label>
                </div>
                <!-- PICKUP ADDRESS CARD -->
                {{-- <div id="pickup-address-card" class="mt-3">
                    <div class="pickup-address-card">
                        <div class="d-flex align-items-start">
                            
                            <div class="pickup-icon">
                                <i class="bi bi-geo-alt-fill"></i>
                            </div>

                            <div class="flex-grow-1">
                                <div class="pickup-title">
                                    Pickup Location
                                </div>

                                <div class="pickup-subtitle">
                                    Please pick up your order at the assigned Area Distributor location.
                                </div>

                                <div class="pickup-address" id="pickup-address-text">
                                    {{ optional(auth()->user()->ad)->address ?? 'No pickup address available' }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>

    <!-- Customer Selection Modal -->
    <div id="clientModal" class="modal-overlays">
        <div class="modal-contents">
            <div class="modal-headers d-flex justify-content-between align-items-center">
                <span class="modal-titles">Select Customer</span>
                <button class="modal-close" onclick="closeClientModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="customer-selection-label">Choose a customer:</div>
                <div id="customerDropdownContainer"></div>
                <select id="customerSelector" class="customer-selector" style="display: none;">
                    <option value="">Select a customer</option>
                    <option value="guest"
                        data-name="Guest"
                        data-full-name="Guest"
                        data-serial=""
                        data-number=""
                        data-masked-serial=""
                        data-masked-number="">
                        Guest
                    </option>
                    @if(isset($customers))
                        @foreach($customers as $customer)
                            @php
                                $fullName = $customer->name;
                                $parts = explode(' ', $fullName);
                                $lastName = array_pop($parts);
                                $masked = str_repeat('*', strlen(implode(' ', $parts))) . ' ' . $lastName;
                                
                                $serial = $customer->serial->serial_number ?? '';
                                $masked_serial = str_repeat('*', max(0, strlen($serial) - 5)) . substr($serial, -5);
                                
                                $number = $customer->number;
                                $masked_number = str_repeat('*', max(0, strlen($number) - 5)) . substr($number, -5);
                            @endphp
                            <option value="{{ $customer->id }}" 
                                data-name="{{ $masked }}" {{-- DISPLAY --}}
                                data-full-name="{{ $fullName }}" {{-- REAL --}}
                                data-serial="{{ $serial }}" {{-- REAL --}}
                                data-number="{{ $number }}" {{-- REAL --}}
                                data-masked-serial="{{ $masked_serial }}" {{-- DISPLAY --}}
                                data-masked-number="{{ $masked_number }}" {{-- DISPLAY --}}>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    @endif
                </select>
                <button class="update-btn w-100" onclick="updateCustomerSelection()">
                    Confirm Selection
                </button>
            </div>
        </div>
    </div>

    <!-- AD Selection Modal -->
    <div id="adModal" class="modal-overlays">
        <div class="modal-contents">
            <div class="modal-headers d-flex justify-content-between align-items-center">
                <span class="modal-titles">Area Distributor</span>
                <button class="modal-close" onclick="closeADModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="customer-selection-label">Choose an AD:</div>
                <div id="adDropdownContainer"></div>
                <select id="adSelector" class="customer-selector" style="display: none;">
                    <option value="">Select an AD</option>
                    @if(isset($availableADs))
                        @foreach($availableADs as $ad)
                            <option value="{{ $ad->id }}" 
                                data-name="{{ $ad->name }}"
                                data-area="{{ $ad->area ?? '' }}">
                                {{ $ad->name }} 
                            </option>
                        @endforeach
                    @endif
                </select>
                <button class="update-btn w-100" onclick="updateADSelection()">
                    Confirm Selection
                </button>
            </div>
        </div>
    </div>
    <div class="cart-section modern-cart">

        <!-- Header -->
        <div class="section-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-cart-fill"></i>
                <span class="fw-semibold">Cart Items</span>
            </div>
            <a href="{{ url('/products') }}" class="add-more-btn">
                + Add More
            </a>
        </div>

        <!-- Items -->
        <div id="cart-items"></div>

        <!-- Footer Summary -->
        <div class="cart-footer">
            <div class="cart-info">
                <i class="bi bi-bag-check"></i>
                <div>
                    <div class="label">Total Items</div>
                    <div class="sub">In your cart</div>
                </div>
            </div>

            <div class="cart-badge-new" id="total-items">0</div>
        </div>

    </div>
    {{-- <div class="cart-section">
        <div class="section-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cart-fill"></i> Cart Items</span>
            <a href="{{ url('/products') }}" class="add-more text-decoration-none">+ Add More</a>
        </div>
        <div id="cart-items">
        <!-- Cart items will be dynamically loaded here -->
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold">
                <i class="bi bi-cart"></i> Items in Cart
            </div>
            <span id="total-items" 
                style="
                    background:#007bff;
                    color:#fff;
                    padding:4px 10px;
                    border-radius:20px;
                    font-size:12px;
                    font-weight:600;
                ">
                0
            </span>
        </div>
    </div> --}}

    <div class="cart-section">
      <div class="section-header">
        <i class="bi bi-receipt"></i> Order Summary
      </div>
      <div class="summary-row d-flex justify-content-between align-items-center">
        <span class="summary-label">Subtotal:</span>
        <span class="summary-value" id="subtotal">₱ 0.00</span>
      </div>
      <div class="summary-row d-flex justify-content-between align-items-center">
        <span class="summary-label">Discount:</span>
        <span class="summary-value" id="discount">₱ 0.00</span>
      </div>
      <div class="summary-row total d-flex justify-content-between align-items-center">
        <span class="summary-label">Total Amount:</span>
        <span class="summary-value" id="total-final">₱ 0.00</span>
      </div>
    </div>

    <div class="cart-section">
      <div class="section-header">
        <i class="bi bi-credit-card-fill"></i> Payment Method
      </div>
      <div class="payment-option">
        <label class="d-flex align-items-center w-100">
          <input type="radio" name="payment_method" value="cash" id="cash" class="me-3" checked>
          <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3">
            <i class="bi bi-cash-coin"></i>
          </div>
          <div class="payment-details flex-grow-1">
            <div class="payment-name">Cash on Delivery</div>
            <div class="payment-desc">Pay when you receive your order</div>
          </div>
        </label>
      </div>

      <div class="payment-option">
        <label class="d-flex align-items-center w-100">
          <input type="radio" name="payment_method" value="gcash" id="gcash" class="me-3">
          <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="background: #007DFF; color: white;">
            <i class="bi bi-phone-fill"></i>
          </div>
          <div class="payment-details flex-grow-1">
            <div class="payment-name">GCash</div>
            <div class="payment-desc">Pay online via GCash</div>
          </div>
        </label>
      </div>

      <div class="payment-option">
        <label class="d-flex align-items-center w-100">
          <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" class="me-3">
          <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="background: #357abd; color: white;">
            <i class="bi bi-bank"></i>
          </div>
          <div class="payment-details flex-grow-1">
            <div class="payment-name">Bank Transfer</div>
            <div class="payment-desc">Pay via Debit / Online Banking</div>
          </div>
        </label>
      </div>

      <div class="payment-option">
        <label class="d-flex align-items-center w-100">
          <input type="radio" name="payment_method" value="credit" id="credit" class="me-3">
          <div class="payment-icon d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="background: #d72f2f; color: white;">
            <i class="bi bi-collection"></i>
          </div>
          <div class="payment-details flex-grow-1">
            <div class="payment-name">Credit</div>
            <div class="payment-desc">Pay via Credit</div>
          </div>
        </label>
      </div>
      
    </div>

    <div class="place-order-wrapper">
        <button class="btn place-order-btn w-100" id="place-order-btn" data-route="{{ route('place_order') }}">
            Place Order
        </button>
    </div>
  </div>
</div>
@endsection

@section('css')
    <style>    
    .content-area-fix{
        margin-top: -80px;
    }

    .back-btn {
        background-color: transparent !important;
        border: none;
    }

    .page-title {
        font-size: 20px;
        font-weight: 600;
        color: #4A90E2;
    }

    .client-section {
        background: #fff;
        margin: 0 15px 15px 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .assigned-ads-card {
        margin-left: 17px;
        background: #fff;
        padding: 20px;
        gap: 25px;
        border: none;
        border-radius: 0;
    }

    .ads-icon {
        width: 50px;
        height: 50px;
        background: #D6F4FF;
        border-radius: 10px;
    }

    .rider {
        height: 100px;
        width: 100px;
    }

    .ads-label {
        font-size: 12px;
        color: #888;
        margin-bottom: 3px;
        font-weight: 400;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ads-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }

    .ads-change {
        font-size: 14px;
        color: #4A90E2;
        cursor: pointer;
        font-weight: 400;
    }

    .ads-change:hover {
        text-decoration: underline;
    }

    .cart-section {
        background: #fff;
        margin: 15px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .section-header {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 18px;
        font-weight: 600;
        color: #333;
    }

    .add-more {
        font-size: 14px;
        color: #007bff;
        cursor: pointer;
    }

    .add-more:hover {
        text-decoration: underline;
    }

    .summary-row {
        padding: 12px 20px;
        font-size: 14px;
    }

    .summary-row.total {
        font-size: 16px;
        font-weight: 700;
        color: #333;
        border-top: 2px solid #f0f0f0;
        background: #f8f9fa;
    }

    .summary-label {
        color: #666;
    }

    .summary-value {
        color: #333;
        font-weight: 600;
    }

    .summary-row.total .summary-value {
        color: #4A90E2;
        font-size: 18px;
    }

    .payment-option {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .payment-option:last-child {
        border-bottom: none;
    }

    .payment-option:hover {
        background: #f8f9fa;
    }

    .payment-option input[type="radio"] {
        transform: scale(1.2);
        cursor: pointer;
    }

    .payment-icon {
        width: 40px;
        height: 40px;
        background: #28a745;
        border-radius: 8px;
        font-size: 18px;
        color: #fff;
    }

    .payment-name {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
    }

    .payment-desc {
        font-size: 12px;
        color: #666;
    }

    .place-order-wrapper {
        margin-top: 30px;
        padding: 0 15px;
        background: transparent;
    }

    .empty-cart {
        text-align: center;
        padding: 60px 20px;
        color: #666;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .empty-cart i {
        font-size: 64px;
        color: #ddd;
        margin-bottom: 20px;
    }

    .empty-cart h3 {
        font-size: 20px;
        margin-bottom: 10px;
        color: #333;
    }

    .empty-cart p {
        font-size: 14px;
        margin-bottom: 30px;
    }

    .continue-shopping-btn {
        background: #4A90E2;
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .continue-shopping-btn:hover {
        background: #357abd;
    }

    .place-order-btn {
        margin-bottom: 20px;
        background: linear-gradient(135deg, #4A90E2 0%, #357abd 100%);
        color: #fff;
        border: none;
        padding: 16px 20px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4);
        transition: all 0.2s ease;
    }

    .place-order-btn:hover {
        background: linear-gradient(135deg, #357abd 0%, #2868a8 100%);
        transform: translateY(-2px);
    }

    .place-order-btn:active {
        transform: scale(0.98);
        box-shadow: 0 2px 8px rgba(74, 144, 226, 0.6);
    }

    .modal-overlays {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: flex-end;
        z-index: 9999 !important;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .modal-overlays.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-contents {
        background: #fff;
        width: 100%;
        border-radius: 20px 20px 0 0;
        height: 70vh;
        overflow-y: auto;
        transform: translateY(100%);
        transition: transform 0.3s ease;
    }

    .modal-overlays.active .modal-contents {
        transform: translateY(0);
    }

    .modal-headers {
        padding: 20px 25px;
        border-bottom: 1px solid #f0f0f0;
    }

    .modal-titles {
        font-size: 20px;
        font-weight: 600;
        color: #333;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #666;
        cursor: pointer;
    }

    .modal-body {
        padding: 25px;
    }

    .custom-dropdown {
        position: relative;
        width: 100%;
    }

    .dropdown-input-wrapper {
        position: relative;
        width: 100%;
    }

    .dropdown-input {
        width: 100%;
        padding: 12px 40px 12px 15px;
        border: 2px solid #f0f0f0;
        border-radius: 12px;
        font-size: 16px;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .dropdown-input:focus {
        outline: none;
        border-color: #4A90E2;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }

    .dropdown-input.active {
        border-color: #4A90E2;
        border-radius: 12px 12px 0 0;
    }

    .dropdown-arrow {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        transition: transform 0.3s ease;
        color: #666;
    }

    .dropdown-arrow.rotate {
        transform: translateY(-50%) rotate(180deg);
    }

    .dropdown-options {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 2px solid #4A90E2;
        border-top: none;
        border-radius: 0 0 12px 12px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .dropdown-options.show {
        display: block;
    }

    .dropdown-option {
        padding: 12px 15px;
        cursor: pointer;
        transition: background 0.2s ease;
        border-bottom: 1px solid #f0f0f0;
    }

    .dropdown-option:last-child {
        border-bottom: none;
    }

    .dropdown-option:hover {
        background: #f8f9fa;
    }

    .dropdown-option.selected {
        background: #e3f2fd;
        color: #4A90E2;
        font-weight: 600;
    }

    .dropdown-option.hidden {
        display: none;
    }

    .option-name {
        font-size: 15px;
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }

    .option-details {
        font-size: 12px;
        color: #666;
    }

    .no-results {
        padding: 20px;
        text-align: center;
        color: #999;
        font-size: 14px;
    }

    .dropdown-options::-webkit-scrollbar {
        width: 6px;
    }

    .dropdown-options::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 0 0 10px 0;
    }

    .dropdown-options::-webkit-scrollbar-thumb {
        background: #4A90E2;
        border-radius: 3px;
    }

    .dropdown-options::-webkit-scrollbar-thumb:hover {
        background: #357abd;
    }

    .dropdown-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 999;
        display: none;
    }

    .dropdown-backdrop.show {
        display: block;
    }

    .customer-selection-label {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
    }

    .customer-selector {
        padding: 15px;
        border: 2px solid #f0f0f0;
        border-radius: 12px;
        font-size: 16px;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.2s ease;
    }

    .customer-selector:focus {
        outline: none;
        border-color: #4A90E2;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }

    .update-btn {
        background: linear-gradient(135deg, #4A90E2, #357abd);
        color: #fff;
        border: none;
        padding: 16px 20px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 12px;
        cursor: pointer;
        margin-top: 25px;
        transition: all 0.2s ease;
    }

    .update-btn:hover {
        background: linear-gradient(135deg, #357abd, #2868a8);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(74, 144, 226, 0.3);
    }

    .update-btn:active {
        transform: translateY(0);
    }

    .swipe-container {
        position: relative;
        overflow: hidden;
        background: #fff;
    }

    .swipe-item {
        position: relative;
        transform: translateX(0);
        transition: transform 0.3s ease;
        background: #fff;
        z-index: 2;
        width: 100%;
    }

    .swipe-item.swiping {
        transition: none;
    }

    .delete-background {
        position: absolute;
        top: 10px;
        right: 10px;
        height: 40px;
        border-radius: 10px;
        width: 100px;
        background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        z-index: 999;
        cursor: pointer;
        box-shadow: inset 2px 0 8px rgba(0, 0, 0, 0.2);
    }

    .delete-background:hover {
        background: linear-gradient(135deg, #ff3742 0%, #ff2d3a 100%);
    }

    .delete-background:active {
        transform: scale(0.95);
        transition: transform 0.1s ease;
    }

    .delete-background::after {
        content: 'Delete';
        font-size: 12px;
        font-weight: 600;
        margin-left: 8px;
    }

    .cart-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        background: #fff;
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .item-image {
        width: 60px;
        height: 60px;
        background: #fafafa;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        overflow: hidden;
        flex-shrink: 0;
    }

    .item-image img {
        max-width: 80%;
        max-height: 80%;
        object-fit: contain;
    }

    .item-name {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
        line-height: 1.3;
    }

    .item-price {
        font-size: 16px;
        font-weight: 700;
        color: #4A90E2;
        margin-bottom: 8px;
    }

    .item-quantity {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 8px;
    }

    .quantity-btn {
        background: #4A90E2;
        border: none;
        color: #fff;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 20px;
        font-weight: bold;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .quantity-btn:hover {
        background: #357abd;
        transform: scale(1.05);
    }

    .quantity-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .qty-input {
        width: 60px;
        height: 40px;
        text-align: center;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        background: #fff;
        -webkit-appearance: none;
        -moz-appearance: textfield;
    }

    .qty-input:focus {
        outline: none;
        border-color: #4A90E2;
        box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
    }

    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    @media (max-width: 768px) {
        .place-order-wrapper {
        bottom: 120px;
        z-index: 1100;
        }
    }

    @media (max-width: 480px) {
        .cart-section {
        margin: 10px;
        }
        
        .client-section {
        margin: 20px 10px 10px 10px;
        }
        
        .section-header {
        padding: 15px;
        font-size: 16px;
        }
        
        .cart-item {
        padding: 12px 15px;
        }
        
        .item-image {
        width: 50px;
        height: 50px;
        margin-right: 12px;
        }

        .delete-background {
        width: 100px;
        font-size: 20px;
        }
        
        .delete-background::after {
        font-size: 10px;
        margin-left: 6px;
        }
    }

    @keyframes fadeInOut {
        0% { opacity: 0; transform: translate(-50%, -20px); }
        10% { opacity: 1; transform: translate(-50%, 0); }
        90% { opacity: 1; transform: translate(-50%, 0); }
        100% { opacity: 0; transform: translate(-50%, -20px); }
    }

    /* Delivery Option Styles */
    .delivery-option input {
        display: none;
    }

    .option-box {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 14px;
        text-align: center;
        cursor: pointer;
        background: #fff;
        transition: 0.2s;
        font-weight: 600;
        font-size: 14px;
    }

    .option-box i {
        display: block;
        font-size: 20px;
        margin-bottom: 6px;
    }

    .delivery-option input:checked + .option-box {
        border: 2px solid #0d6efd;
        background: #f0f7ff;
        color: #0d6efd;
    }

    .pickup-address-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
    }

    .pickup-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: #e0f2fe;
        color: #0284c7;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 14px;
        font-size: 18px;
        flex-shrink: 0;
    }

    .pickup-title {
        font-weight: 700;
        font-size: 14px;
        color: #111827;
    }

    .pickup-subtitle {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
    }

    .pickup-address {
        margin-top: 10px;
        font-size: 13px;
        color: #374151;
        line-height: 1.6;
        background: white;
        padding: 12px;
        border-radius: 12px;
        border: 1px dashed #cbd5e1;
    }

    .modern-cart {
        background: #fff;
        border-radius: 14px;
        padding: 14px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    }

    /* Header */
    .add-more-btn {
        font-size: 12px;
        color: #007bff;
        text-decoration: none;
        font-weight: 600;
    }

    .add-more-btn:hover {
        text-decoration: underline;
    }

    /* Footer */
    .cart-footer {
        padding: 15px 15px 0px 15px;
        border-top: 1px solid #eee;

        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Left info */
    .cart-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .cart-info i {
        font-size: 18px;
        color: #007bff;
    }

    .cart-info .label {
        font-size: 13px;
        font-weight: 600;
    }

    .cart-info .sub {
        font-size: 11px;
        color: #888;
    }

    /* Badge */
    .cart-badge-new {
        background: linear-gradient(135deg, #007bff, #00c6ff);
        color: #fff;
        font-weight: 700;
        font-size: 14px;
        padding: 6px 14px;
        border-radius: 20px;
        min-width: 40px;
        text-align: center;
        box-shadow: 0 4px 10px rgba(0,123,255,0.3);
        transition: all 0.2s ease;
    }

    /* Animation */
    .cart-badge-new.animate {
        transform: scale(1.2);
    }
    </style>
@endsection

@section('javascript')
<script>

    function showSuccessMessage(message) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 12px 24px;
            max-width: 90%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            text-align: center;
            animation: fadeInOut 3s ease;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 3000);
    }

    const toastStyle = document.createElement('style');
    toastStyle.textContent = `
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translate(-50%, -20px); }
            10% { opacity: 1; transform: translate(-50%, 0); }
            90% { opacity: 1; transform: translate(-50%, 0); }
            100% { opacity: 0; transform: translate(-50%, -20px); }
        }
    `;
    document.head.appendChild(toastStyle);

    function loadSelectedCustomerFromProducts() {
        const customerId = localStorage.getItem('selectedCustomerId');
        const customerName = localStorage.getItem('selectedCustomerName');
        const customerSerial = localStorage.getItem('selectedCustomerSerial');
        const customerNumber = localStorage.getItem('selectedCustomerNumber');
        
        const customerNameElement = document.getElementById('assigned-customer-name');
        const customerDetailsElement = document.getElementById('assigned-customer-details');
        
        if (customerId && customerName && customerNameElement) {
            customerNameElement.textContent = customerName;

            if (customerId === 'guest' && customerDetailsElement) {
                customerDetailsElement.textContent = 'Guest transaction - no points awarded';
            }
            
            if (customerDetailsElement && customerSerial && customerNumber) {
                customerDetailsElement.textContent = `Serial: ${customerSerial} • Number: ${customerNumber}`;
            }
            
            console.log('Loaded customer from products:', {
                id: customerId,
                name: customerName,
                serial: customerSerial,
                number: customerNumber
            });
        } else {
            if (customerNameElement) {
                customerNameElement.textContent = 'Select a Customer';
            }
            if (customerDetailsElement) {
                customerDetailsElement.textContent = '';
            }
        }
    }

    class SearchableDropdown {
        constructor(containerId, options, config = {}) {
            this.container = document.getElementById(containerId);
            this.options = options;
            this.config = {
                storagePrefix: config.storagePrefix || 'selected',
                nameElementId: config.nameElementId || null,
                detailsElementId: config.detailsElementId || null,
                detailsFormat: config.detailsFormat || '',
                ...config
            };
            this.selectedOption = null;
            this.filteredOptions = [...options];
            this.isOpen = false;
            
            this.init();
        }
        
        init() {
            this.render();
            this.attachEventListeners();
            this.loadSavedSelection();
        }
        
        render() {
            const html = `
                <div class="dropdown-backdrop" id="dropdownBackdrop"></div>
                <div class="custom-dropdown">
                    <div class="dropdown-input-wrapper">
                        <input 
                            type="text" 
                            class="dropdown-input" 
                            id="dropdownInput"
                            placeholder="Search or select..."
                            autocomplete="off"
                        >
                        <span class="dropdown-arrow" id="dropdownArrow">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="dropdown-options" id="dropdownOptions">
                        ${this.renderOptions()}
                    </div>
                </div>
            `;
            
            this.container.innerHTML = html;
        }
        
        renderOptions() {
            if (this.filteredOptions.length === 0) {
                return '<div class="no-results">No results found</div>';
            }
            
            return this.filteredOptions.map(option => `
                <div class="dropdown-option" data-id="${option.id}">
                    <!-- <div class="option-name">${option.name}</div>
                    <div class="option-details">${option.serial ? option.serial + ' • ' : ''}${option.number || option.area || ''}</div> -->

                    <div class="option-name">${option.name}</div>
                    <div class="option-details">
                        ${option.maskedSerial ? option.maskedSerial + ' • ' : ''}
                        ${option.maskedNumber || (option.isGuest ? 'No points awarded' : '')}
                    </div>
                </div>
            `).join('');
        }
        
        attachEventListeners() {
            const input = document.getElementById('dropdownInput');
            const arrow = document.getElementById('dropdownArrow');
            const optionsContainer = document.getElementById('dropdownOptions');
            const backdrop = document.getElementById('dropdownBackdrop');
            
            input.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
            
            arrow.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
            
            input.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                this.filterOptions(searchTerm);
                
                if (!this.isOpen) {
                    this.openDropdown();
                }
            });
            
            optionsContainer.addEventListener('click', (e) => {
                const option = e.target.closest('.dropdown-option');
                if (option) {
                    const id = option.dataset.id;
                    this.selectOption(id);
                }
            });
            
            backdrop.addEventListener('click', () => {
                this.closeDropdown();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeDropdown();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const firstVisible = optionsContainer.querySelector('.dropdown-option:not(.hidden)');
                    if (firstVisible && this.isOpen) {
                        this.selectOption(firstVisible.dataset.id);
                    }
                }
            });
        }
        
        filterOptions(searchTerm) {
            this.filteredOptions = this.options.filter(option => {
                return option.name.toLowerCase().includes(searchTerm) ||
                    (option.fullName && option.fullName.toLowerCase().includes(searchTerm)) ||
                    (option.serial && option.serial.toLowerCase().includes(searchTerm)) ||
                    (option.number && option.number.toLowerCase().includes(searchTerm)) ||
                    (option.area && option.area.toLowerCase().includes(searchTerm));
            });
            
            const optionsContainer = document.getElementById('dropdownOptions');
            optionsContainer.innerHTML = this.renderOptions();
        }
        
        selectOption(id) {
            const option = this.options.find(opt => opt.id == id);
            if (option) {
                this.selectedOption = option;
                
                const input = document.getElementById('dropdownInput');
                input.value = option.name;
                
                // Store in localStorage with configurable prefix
                localStorage.setItem(`${this.config.storagePrefix}Id`, option.id);
                localStorage.setItem(`${this.config.storagePrefix}Name`, option.name);
                // if (option.serial) localStorage.setItem(`${this.config.storagePrefix}Serial`, option.serial);
                // if (option.number) localStorage.setItem(`${this.config.storagePrefix}Number`, option.number);
                localStorage.setItem(`${this.config.storagePrefix}Serial`, option.serial);
                localStorage.setItem(`${this.config.storagePrefix}Number`, option.number);
                if (option.area) localStorage.setItem(`${this.config.storagePrefix}Area`, option.area);
                
                this.updateMainDisplay(option);
                
                this.closeDropdown();
                
                console.log('Selected:', option);
            }
        }
        
        updateMainDisplay(option) {
            if (this.config.nameElementId) {
                const nameElement = document.getElementById(this.config.nameElementId);
                if (nameElement) {
                    nameElement.textContent = option.name;
                }
            }
            
            if (this.config.detailsElementId) {
                const detailsElement = document.getElementById(this.config.detailsElementId);
                if (detailsElement) {
                    let details = '';
                    if (option.isGuest) {
                        details = 'Guest transaction - no points awarded';
                    } else if (this.config.detailsFormat) {
                        // Replace placeholders with actual values
                        // details = this.config.detailsFormat
                        //     .replace('{serial}', option.serial || '')
                        //     .replace('{number}', option.number || '')
                        //     .replace('{area}', option.area || '')
                        //     .replace('• ', '')
                        //     .trim();
                        details = `${option.maskedSerial || ''} • ${option.maskedNumber || ''}`;
                    } else {
                        details = [option.serial, option.number, option.area].filter(Boolean).join(' • ');
                    }
                    detailsElement.textContent = details;
                }
            }
        }
        
        loadSavedSelection() {
            const savedId = localStorage.getItem(`${this.config.storagePrefix}Id`);
            if (savedId) {
                const savedOption = this.options.find(opt => opt.id == savedId);
                if (savedOption) {
                    const input = document.getElementById('dropdownInput');
                    input.value = savedOption.name;
                    this.selectedOption = savedOption;
                }
            }
        }
        
        toggleDropdown() {
            if (this.isOpen) {
                this.closeDropdown();
            } else {
                this.openDropdown();
            }
        }
        
        openDropdown() {
            const input = document.getElementById('dropdownInput');
            const arrow = document.getElementById('dropdownArrow');
            const optionsContainer = document.getElementById('dropdownOptions');
            const backdrop = document.getElementById('dropdownBackdrop');
            
            input.classList.add('active');
            arrow.classList.add('rotate');
            optionsContainer.classList.add('show');
            backdrop.classList.add('show');
            
            this.isOpen = true;
            
            if (input.value === this.selectedOption?.name) {
                input.value = '';
                this.filterOptions('');
            }
            
            input.focus();
        }
        
        closeDropdown() {
            const input = document.getElementById('dropdownInput');
            const arrow = document.getElementById('dropdownArrow');
            const optionsContainer = document.getElementById('dropdownOptions');
            const backdrop = document.getElementById('dropdownBackdrop');
            
            input.classList.remove('active');
            arrow.classList.remove('rotate');
            optionsContainer.classList.remove('show');
            backdrop.classList.remove('show');
            
            this.isOpen = false;
            
            if (this.selectedOption) {
                input.value = this.selectedOption.name;
            }
            
            this.filterOptions('');
        }
        
        getSelectedValue() {
            return this.selectedOption;
        }
    }

    let customerDropdown = null;

    function openCustomerSelection() {
        const modal = document.getElementById('clientModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        if (!customerDropdown) {
            const customerSelector = document.getElementById('customerSelector');
            const customers = [];
            
            if (customerSelector) {
                Array.from(customerSelector.options).forEach(option => {
                    if (option.value) {
                        // customers.push({
                        //     id: option.value,
                        //     name: option.getAttribute('data-name'),
                        //     fullName: option.getAttribute('data-full-name'),
                        //     serial: option.getAttribute('data-serial'),
                        //     number: option.getAttribute('data-number')
                        // });
                        customers.push({
                            id: option.value,
                            isGuest: option.value === 'guest',

                            // DISPLAY
                            name: option.getAttribute('data-name'),
                            maskedSerial: option.getAttribute('data-masked-serial'),
                            maskedNumber: option.getAttribute('data-masked-number'),

                            // REAL DATA
                            fullName: option.getAttribute('data-full-name'),
                            serial: option.getAttribute('data-serial'),
                            number: option.getAttribute('data-number')
                        });
                    }
                });
            }
            
            customerDropdown = new SearchableDropdown('customerDropdownContainer', customers, {
                storagePrefix: 'selectedCustomer',
                nameElementId: 'assigned-customer-name',
                detailsElementId: 'assigned-customer-details',
                detailsFormat: 'Serial: {serial} • Number: {number}'
            });
        }
        
        if (typeof resetAllSwipes === 'function') {
            resetAllSwipes();
        }
    }

    function closeClientModal() {
        const modal = document.getElementById('clientModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        if (customerDropdown && customerDropdown.isOpen) {
            customerDropdown.closeDropdown();
        }
    }

    function updateCustomerSelection() {
        if (!customerDropdown) {
            alert('Please select a customer');
            return;
        }
        
        const selected = customerDropdown.getSelectedValue();
        
        if (!selected) {
            alert('Please select a customer');
            return;
        }
        
        const customerNameElement = document.getElementById('assigned-customer-name');
        const customerDetailsElement = document.getElementById('assigned-customer-details');
        
        if (customerNameElement) {
            customerNameElement.textContent = selected.name;
        }
        
        if (customerDetailsElement) {
            customerDetailsElement.textContent =`${selected.maskedSerial || ''} • ${selected.maskedNumber || ''}`;
        }
        
        if (selected.isGuest && customerDetailsElement) {
            customerDetailsElement.textContent = 'Guest transaction - no points awarded';
        }

        closeClientModal();
        showSuccessMessage(`Customer "${selected.name}" selected`);
    }

    window.openCustomerSelection = openCustomerSelection;
    window.closeClientModal = closeClientModal;
    window.updateCustomerSelection = updateCustomerSelection;

    // AD Selection Functions
    let adDropdown = null;

    function openADSelection() {
        const modal = document.getElementById('adModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';

        if (!window.adDropdown) {
            initADDropdown();
        }
    }
    window.openADSelection = openADSelection;

    function closeADModal() {
        const modal = document.getElementById('adModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        
        if (adDropdown && adDropdown.isOpen) {
            adDropdown.closeDropdown();
        }
    }

    function updateADSelection() {
    if (!window.adDropdown) {
        alert('Please select an AD');
        return;
    }

    const selected = window.adDropdown.getSelectedValue();

    if (!selected) {
        alert('Please select an AD');
        return;
    }

    // ✅ ONE SINGLE SOURCE OF TRUTH
    window.selectedAD = selected;

    localStorage.setItem('selectedADId', selected.id);
    localStorage.setItem('selectedADName', selected.name);
    // localStorage.setItem('selectedADArea', selected.area || '');
    const adAreas = JSON.parse(localStorage.getItem('selectedADAreas') || '[]');

    const adName = document.getElementById('assigned-ad-name');
    const adDetails = document.getElementById('assigned-ad-details');

        if (adName) adName.textContent = selected.name;
        // if (adDetails) adDetails.textContent = `Area: ${selected.area || 'N/A'}`;
        if (adDetails) {
            const areaText = selected.areas && selected.areas.length
                ? selected.areas.join(', ')
                : 'No Area Assigned';

            adDetails.textContent = `Areas: ${areaText}`;
        }

        // 🔥 IMPORTANT: refresh logic
        detectAD();

        closeADModal();
        showSuccessMessage(`AD "${selected.name}" selected`);
    }

    // window.openADSelection = openADSelection;
    window.closeADModal = closeADModal;
    window.updateADSelection = updateADSelection;


    document.addEventListener('DOMContentLoaded', function() {
        loadSelectedCustomerFromProducts();
        
        const savedCustomerName = localStorage.getItem('selectedCustomerName');
        const savedCustomerSerial = localStorage.getItem('selectedCustomerSerial');
        const savedCustomerNumber = localStorage.getItem('selectedCustomerNumber');
        
        const customerNameElement = document.getElementById('assigned-customer-name');
        const customerDetailsElement = document.getElementById('assigned-customer-details');
        
        if (savedCustomerName && customerNameElement) {
            customerNameElement.textContent = savedCustomerName;
            
            if (customerDetailsElement && savedCustomerSerial && savedCustomerNumber) {
                customerDetailsElement.textContent = `${savedCustomerSerial} • ${savedCustomerNumber}`;
            }
        }

        let dealerCartData = [];
        
        try {
            const storedDealerCartData = localStorage.getItem('dealerCartData');
            if (storedDealerCartData) {
                dealerCartData = JSON.parse(storedDealerCartData);
                console.log('Loaded cart data:', dealerCartData.length, 'items');
            } else {
                console.log('Cart is empty');
            }
        } catch (error) {
            console.error('Error loading cart data:', error);
            dealerCartData = [];
        }
        updateTotalItemsUI();

        let isSwipeActive = false;
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let activeSwipeItem = null;
        let swipeDirection = null;

        const SWIPE_THRESHOLD = 50;
        const MAX_SWIPE_DISTANCE = 120;
        const MIN_MOVEMENT_THRESHOLD = 15;

        function initSwipeListeners() {
            document.querySelectorAll('.swipe-item').forEach(item => {
                item.addEventListener('touchstart', handleTouchStart, { passive: true });
                item.addEventListener('touchmove', handleTouchMove, { passive: false });
                item.addEventListener('touchend', handleTouchEnd, { passive: true });

                item.addEventListener('mousedown', handleMouseStart);
                item.addEventListener('mousemove', handleMouseMove);
                item.addEventListener('mouseup', handleMouseEnd);
                item.addEventListener('mouseleave', handleMouseEnd);
            });
        }

        function shouldPreventSwipe(target) {
            const preventSwipeElements = [
                'input', 'button', 'select', 'textarea', 
                '.qty-input', '.quantity-btn', '.plus-btn', '.minus-btn'
            ];
            
            return preventSwipeElements.some(selector => {
                if (selector.startsWith('.')) {
                    return target.classList.contains(selector.substring(1));
                }
                return target.tagName.toLowerCase() === selector;
            }) || target.closest('.item-quantity');
        }

        function handleTouchStart(e) {
            if (shouldPreventSwipe(e.target)) return;
            const touch = e.touches[0];
            handleStart(touch.clientX, touch.clientY, e.currentTarget);
        }

        function handleMouseStart(e) {
            if (shouldPreventSwipe(e.target)) return;
            e.preventDefault();
            handleStart(e.clientX, e.clientY, e.currentTarget);
        }

        function handleStart(clientX, clientY, element) {
            if (activeSwipeItem && activeSwipeItem !== element) {
                resetSwipe(activeSwipeItem);
            }
            
            isSwipeActive = true;
            startX = clientX;
            startY = clientY;
            currentX = clientX;
            currentY = clientY;
            activeSwipeItem = element;
            swipeDirection = null;
        }

        function handleTouchMove(e) {
            if (!isSwipeActive) return;
            
            const touch = e.touches[0];
            currentX = touch.clientX;
            currentY = touch.clientY;
            
            if (swipeDirection === null) {
                const deltaX = Math.abs(currentX - startX);
                const deltaY = Math.abs(currentY - startY);
                
                if (deltaX > MIN_MOVEMENT_THRESHOLD || deltaY > MIN_MOVEMENT_THRESHOLD) {
                    swipeDirection = deltaX > deltaY ? 'horizontal' : 'vertical';
                }
            }
            
            if (swipeDirection === 'horizontal') {
                e.preventDefault();
                handleMove();
            } else if (swipeDirection === 'vertical') {
                resetSwipe(activeSwipeItem);
                isSwipeActive = false;
                activeSwipeItem = null;
                swipeDirection = null;
            }
        }

        function handleMouseMove(e) {
            if (!isSwipeActive) return;
            currentX = e.clientX;
            currentY = e.clientY;
            e.preventDefault();
            swipeDirection = 'horizontal';
            handleMove();
        }

        function handleMove() {
            const diffX = startX - currentX;
            
            if (diffX > 0 && diffX <= MAX_SWIPE_DISTANCE) {
                activeSwipeItem.classList.add('swiping');
                activeSwipeItem.style.transform = `translateX(-${diffX}px)`;
            } else if (diffX <= 0) {
                resetSwipe(activeSwipeItem);
            } else if (diffX > MAX_SWIPE_DISTANCE) {
                activeSwipeItem.style.transform = `translateX(-${MAX_SWIPE_DISTANCE}px)`;
            }
        }

        function handleTouchEnd(e) {
            handleEnd();
        }

        function handleMouseEnd(e) {
            handleEnd();
        }

        function handleEnd() {
            if (!isSwipeActive || !activeSwipeItem) return;
            
            isSwipeActive = false;
            if (activeSwipeItem) {
                activeSwipeItem.classList.remove('swiping');
            }
            
            if (swipeDirection === 'horizontal') {
                const diffX = startX - currentX;
                
                if (diffX > SWIPE_THRESHOLD) {
                    activeSwipeItem.style.transform = `translateX(-${MAX_SWIPE_DISTANCE}px)`;
                    activeSwipeItem.style.boxShadow = '-8px 0 16px rgba(0, 0, 0, 0.1)';
                    if ('vibrate' in navigator) {
                        navigator.vibrate(50);
                    }
                } else {
                    resetSwipe(activeSwipeItem);
                }
            }
            
            swipeDirection = null;
        }

        function resetSwipe(element) {
            if (element) {
                element.style.transform = 'translateX(0)';
                element.style.boxShadow = '';
                element.classList.remove('swiping');
            }
        }

        function resetAllSwipes() {
            document.querySelectorAll('.swipe-item').forEach(item => {
                resetSwipe(item);
            });
            activeSwipeItem = null;
            isSwipeActive = false;
            swipeDirection = null;
        }

        window.resetAllSwipes = resetAllSwipes;

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.swipe-container') && !e.target.closest('.delete-background')) {
                resetAllSwipes();
            }
            if (shouldPreventSwipe(e.target)) {
                resetAllSwipes();
            }
        });

        let scrollTimer;
        document.addEventListener('scroll', function() {
            if (activeSwipeItem) {
                resetAllSwipes();
            }
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                resetAllSwipes();
            }, 100);
        }, { passive: true });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && activeSwipeItem) {
                resetAllSwipes();
            }
        });


        window.updateQuantityFromInput = function(itemId, newQuantity) {
            const quantity = parseInt(newQuantity);
            
            if (isNaN(quantity) || quantity < 1) {
                const input = document.querySelector(`.qty-input[data-id="${itemId}"]`);
                input.value = 1;
                updateItemQuantity(itemId, 1);
                return;
            }
            
            if (quantity > 999) {
                const input = document.querySelector(`.qty-input[data-id="${itemId}"]`);
                input.value = 999;
                updateItemQuantity(itemId, 999);
                return;
            }
            
            updateItemQuantity(itemId, quantity);
        };

        window.validateQuantityInput = function(input) {
            const value = parseInt(input.value);
            if (isNaN(value) || value < 1) {
                input.value = 1;
                updateQuantityFromInput(input.dataset.id, 1);
            } else if (value > 999) {
                input.value = 999;
                updateQuantityFromInput(input.dataset.id, 999);
            }
        };

        window.handleInputFocus = function(input) {
            resetAllSwipes();
            setTimeout(() => input.select(), 50);
        };
        
        window.handleQuantityInput = function(input) {
            const value = parseInt(input.value);
            const itemId = input.dataset.id;
            
            if (isNaN(value) || value < 1) {
                input.value = 1;
                updateItemQuantity(itemId, 1);
            } else if (value > 999) {
                input.value = 999;
                updateItemQuantity(itemId, 999);
            } else {
                updateItemQuantity(itemId, value);
            }
        };
        
        function updateItemQuantity(itemId, newQuantity) {
            const item = dealerCartData.find(item => item.id == itemId);
            if (item) {
                item.quantity = newQuantity;
                localStorage.setItem('dealerCartData', JSON.stringify(dealerCartData));
                updateCartStats();
                updateOrderSummary();
                updateQuantityDisplays();
            }
        }

        function updateQuantityDisplays() {
            dealerCartData.forEach(item => {
                const input = document.querySelector(`.qty-input[data-id="${item.id}"]`);
                if (input && input !== document.activeElement) {
                    input.value = item.quantity;
                }
            });
        }

        function renderCartItems() {
            const cartItemsContainer = document.getElementById('cart-items');
            
            if (dealerCartData.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="empty-cart">
                        <i class="bi bi-cart-x"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add some items to your cart to continue</p>
                        <button class="continue-shopping-btn" onclick="window.location.href='{{ url('products')}}'">
                            Continue Shopping
                        </button>
                    </div>
                `;
                document.querySelector('.place-order-wrapper').style.display = 'none';
                updateTotalItemsUI(); // ✅ IMPORTANT
                return;
            }

            let cartHTML = '';
            dealerCartData.forEach(item => {
                let colorIndicatorHTML = '';
                if (item.color) {
                    colorIndicatorHTML = `
                        <div class="color-indicator">
                            <div class="color-dot ${item.color}"></div>
                            <span style="font-size: 12px">Color: ${item.color.charAt(0).toUpperCase() + item.color.slice(1)}</span>
                        </div>
                    `;
                }

                cartHTML += `
                    <div class="swipe-container">
                        <div class="delete-background" onclick="removeItemWithAnimation('${item.id}')">
                            <i class="bi bi-trash"></i>
                        </div>
                        <div class="swipe-item" data-id="${item.id}">
                            <div class="cart-item">
                                <div class="item-image">
                                    <img src="${item.image}" alt="${item.originalName || item.name}">
                                </div>
                                <div class="item-details">
                                    <div class="item-name">${item.originalName || item.name}</div>
                                    ${colorIndicatorHTML}
                                    <div class="item-price">₱ ${item.price.toFixed(2)}</div>
                                    <div class="item-quantity">
                                        <button class="quantity-btn minus-btn" data-id="${item.id}">−</button>
                                        <input type="number" 
                                            class="qty-input" 
                                            value="${item.quantity}" 
                                            min="1" 
                                            max="999"
                                            data-id="${item.id}"
                                            oninput="handleQuantityInput(this)"
                                            onchange="updateQuantityFromInput('${item.id}', this.value)"
                                            onblur="validateQuantityInput(this)"
                                            onfocus="handleInputFocus(this)">
                                        <button class="quantity-btn plus-btn" data-id="${item.id}">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            cartItemsContainer.innerHTML = cartHTML;
            initSwipeListeners();
            updateTotalItemsUI();

            // document.querySelectorAll('.minus-btn').forEach(btn => {
            //     btn.addEventListener('click', function(e) {
            //         e.stopPropagation();
            //         resetAllSwipes();
            //         const itemId = this.dataset.id;
            //         const currentInput = document.querySelector(`.qty-input[data-id="${itemId}"]`);
            //         const currentQty = parseInt(currentInput.value);
            //         if (currentQty > 1) {
            //             updateItemQuantity(itemId, currentQty - 1);
            //         }
            //     });
            // });

            document.querySelectorAll('.minus-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    resetAllSwipes();

                    const itemId = this.dataset.id;
                    const currentInput = document.querySelector(`.qty-input[data-id="${itemId}"]`);
                    const currentQty = parseInt(currentInput.value);

                    if (currentQty <= 1) {
                        // 🔥 REMOVE ITEM when qty is 1
                        if (confirm('Remove this item from cart?')) {
                            removeItem(itemId);
                        }
                    } else {
                        // normal decrease
                        updateItemQuantity(itemId, currentQty - 1);
                    }
                });
            });

            document.querySelectorAll('.plus-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    resetAllSwipes();
                    const itemId = this.dataset.id;
                    const currentInput = document.querySelector(`.qty-input[data-id="${itemId}"]`);
                    const currentQty = parseInt(currentInput.value);
                    if (currentQty < 999) {
                        updateItemQuantity(itemId, currentQty + 1);
                    }
                });
            });

            const placeOrderWrapper = document.querySelector('.place-order-wrapper');
            if (placeOrderWrapper) {
                placeOrderWrapper.style.display = 'block';
            }
        }

        // window.removeItemWithAnimation = function(itemId) {
        //     const swipeContainer = document.querySelector(`[data-id="${itemId}"]`).closest('.swipe-container');
            
        //     if (confirm('Are you sure you want to remove this item from your cart?')) {
        //         swipeContainer.style.transition = 'all 0.3s ease';
        //         swipeContainer.style.transform = 'translateX(-100%)';
        //         swipeContainer.style.opacity = '0';
                
        //         setTimeout(() => {
        //             removeItem(itemId);
        //         }, 300);
        //     } else {
        //         resetAllSwipes();
        //     }
        // };

        window.removeItemWithAnimation = function(itemId) {
            const swipeContainer = document
                .querySelector(`[data-id="${itemId}"]`)
                .closest('.swipe-container');

            Swal.fire({
                title: 'Remove item?',
                text: 'This will remove the item from your cart.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, remove it',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {

                    // ✅ animation
                    swipeContainer.style.transition = 'all 0.3s ease';
                    swipeContainer.style.transform = 'translateX(-100%)';
                    swipeContainer.style.opacity = '0';

                    setTimeout(() => {
                        removeItem(itemId);

                        // ✅ success feedback
                        Swal.fire({
                            icon: 'success',
                            title: 'Removed!',
                            text: 'Item has been removed.',
                            timer: 1200,
                            showConfirmButton: false
                        });

                    }, 300);

                } else {
                    resetAllSwipes();
                }
            });
        };

        // function removeItem(itemId) {
        //     dealerCartData = dealerCartData.filter(item => item.id != itemId);
        //     localStorage.setItem('dealerCartData', JSON.stringify(dealerCartData));
        //     updateCartStats();
        //     renderCartItems();
        //     updateOrderSummary();
        // }

        function removeItem(itemId) {
            dealerCartData = dealerCartData.filter(item => item.id != itemId);
            localStorage.setItem('dealerCartData', JSON.stringify(dealerCartData));

            updateCartStats();
            renderCartItems();   // rebinds listeners again
            updateOrderSummary();
        }
        function calculateTotalItems() {
            return dealerCartData.reduce((sum, item) => {
                return sum + (parseInt(item.quantity) || 0);
            }, 0);
        }
        function updateTotalItemsUI() {
            const el = document.getElementById('total-items');
            if (!el) return;

            const total = dealerCartData.reduce((sum, item) => sum + item.quantity, 0);

            el.classList.add('animate');
            el.textContent = total;

            setTimeout(() => {
                el.classList.remove('animate');
            }, 150);
        }
        function updateCartStats() {
            const totalItems = dealerCartData.reduce((sum, item) => sum + item.quantity, 0);
            // const totalAmount = dealerCartData.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const totalAmount = dealerCartData.reduce((sum, item) => {
                return sum + (item.price * item.quantity);
            }, 0);
            
            localStorage.setItem('dealerCartItems', totalItems.toString());
            localStorage.setItem('dealerCartTotal', totalAmount.toFixed(2));

            updateTotalItemsUI();
        
            if (typeof triggerCartBadgeUpdate === 'function') {
                triggerCartBadgeUpdate();
            }
        }

        function updateOrderSummary() {
            const subtotal = dealerCartData.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const total = subtotal;

            const subtotalElement = document.getElementById('subtotal');
            const totalFinalElement = document.getElementById('total-final');
            const finalTotalElement = document.getElementById('final-total');

            if (subtotalElement) subtotalElement.textContent = `₱ ${subtotal.toFixed(2)}`;
            if (totalFinalElement) totalFinalElement.textContent = `₱ ${total.toFixed(2)}`;
            if (finalTotalElement) finalTotalElement.textContent = `₱ ${total.toFixed(2)}`;
        }

        function showStockAlert(message) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Insufficient Stock',
                    html: message,
                    confirmButtonText: 'OK'
                });
                return;
            }

            alert(message.replace(/<[^>]*>/g, ''));
        }

        async function fetchLatestStocks(transactionType, adId = null) {
            const url = new URL("{{ route('products.stocks') }}", window.location.origin);

            if (transactionType === 'ad_order' && adId) {
                url.searchParams.set('ad_id', adId);
            }

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Unable to check stock. Please try again.');
            }

            return response.json();
        }

        function getCartStockIssues(stockSummary, transactionType) {
            if (transactionType !== 'client_transaction') {
                return [];
            }

            const dealerStocks = stockSummary.dealer || {};

            return dealerCartData.reduce((issues, item) => {
                const productId = String(item.id);
                const availableStock = parseInt(dealerStocks[productId] || 0, 10);
                const requestedQty = parseInt(item.quantity || 0, 10);
                const itemName = item.originalName || item.name || 'Product';

                if (availableStock <= 0) {
                    issues.push(`<strong>${itemName}</strong> has no dealer stock available.`);
                } else if (requestedQty > availableStock) {
                    issues.push(`<strong>${itemName}</strong> only has ${availableStock} dealer stock available, but ${requestedQty} is in the cart.`);
                }

                return issues;
            }, []);
        }

        const placeOrderBtn = document.getElementById('place-order-btn');
        if (placeOrderBtn) {
            placeOrderBtn.addEventListener('click', async function() {
                const transactionTypeElement = document.querySelector('input[name="transaction_type"]:checked');
                const transactionType = transactionTypeElement ? transactionTypeElement.value : 'client_transaction';

                let customerData = null;

                if (transactionType === 'client_transaction') {
                    const customerId = localStorage.getItem('selectedCustomerId');
                    const customerName = localStorage.getItem('selectedCustomerName');
                    const customerSerial = localStorage.getItem('selectedCustomerSerial');
                    const customerNumber = localStorage.getItem('selectedCustomerNumber');

                    if (!customerId || !customerName) {
                        alert('Please select a customer before placing the order.');
                        return;
                    }

                    customerData = {
                        id: customerId,
                        name: customerName,
                        serial: customerSerial,
                        number: customerNumber,
                        is_guest: customerId === 'guest'
                    };
                }
                
                // Get AD data when transaction type is ad_order
                let adData = null;
                if (transactionType === 'ad_order') {
                    // Use window.selectedAD if available (user manually selected)
                    // Otherwise fallback to localStorage
                    if (window.selectedAD && window.selectedAD.id) {
                        adData = window.selectedAD;
                    } else {
                        const adId = localStorage.getItem('selectedADId');
                        const adName = localStorage.getItem('selectedADName');
                        const adArea = localStorage.getItem('selectedADArea');
                        
                        if (adId && adName) {
                            adData = {
                                id: adId,
                                name: adName,
                                area: adArea || ''
                            };
                        }
                    }
                    
                    // Validate AD is selected
                    if (!adData || !adData.id) {
                        alert('Please select an Area Distributor before placing the order.');
                        return;
                    }
                }

                if (transactionType === 'client_transaction') {
                    try {
                        const stockSummary = await fetchLatestStocks(transactionType);
                        const stockIssues = getCartStockIssues(stockSummary, transactionType);

                        if (stockIssues.length > 0) {
                            showStockAlert(stockIssues.join('<br>'));
                            return;
                        }
                    } catch (error) {
                        showStockAlert(error.message || 'Unable to check stock. Please try again.');
                        return;
                    }
                }

                const deliveryTypeElement = document.querySelector('input[name="delivery_type"]:checked');
                const deliveryType = deliveryTypeElement ? deliveryTypeElement.value : 'pickup';

                // Get payment method from selected radio
                const paymentMethodElement = document.querySelector('input[name="payment_method"]:checked');
                const paymentMethod = paymentMethodElement ? paymentMethodElement.value : 'cash';
                
                // Calculate subtotal and total
                const subtotal = dealerCartData.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                const total = subtotal;
                
                const orderData = {
                    items: dealerCartData,
                    transaction_type: transactionType,
                    customer: customerData,
                    ad: adData,
                    delivery_type: deliveryType,
                    payment_method: paymentMethod,
                    subtotal: subtotal,
                    total: total,
                    created_at: new Date().toISOString()
                };

                localStorage.setItem('dealerOrderData', JSON.stringify(orderData));
                localStorage.setItem('selectedDeliveryType', deliveryType);

                placeOrderBtn.disabled = true;
                placeOrderBtn.innerHTML = 'Processing... <i class="bi bi-hourglass-split"></i>';

                console.log('Order Data:', orderData);
                console.log('Customer:', orderData.customer);
                console.log('Payment Method:', paymentMethod);
                console.log('Total:', orderData.total);

                setTimeout(() => {
                    window.location.href = "{{ route('place_order') }}";
                }, 1000);
            });
        }

        // Mark Ian 04/26/26
        document.addEventListener('DOMContentLoaded', function () {
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.innerHTML = 'Place Order';
            }
        });

        window.addEventListener("pageshow", function () {
            if (placeOrderBtn) {
                placeOrderBtn.disabled = false;
                placeOrderBtn.innerHTML = 'Place Order';
            }
        });

        const clientModal = document.getElementById('clientModal');
        if (clientModal) {
            clientModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeClientModal();
                }
            });
        }
        
        const adModal = document.getElementById('adModal');
        if (adModal) {
            adModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeADModal();
                }
            });
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (clientModal && clientModal.classList.contains('active')) {
                    closeClientModal();
                }
                if (adModal && adModal.classList.contains('active')) {
                    closeADModal();
                }
            }
        });

        renderCartItems();
        updateOrderSummary();
    });
</script>

<script>
    window.matchedADs = @json($matchedADs);
    window.availableADs = @json($availableADs);
    window.authUserCenter = @json($userCenter);
    window.selectedAD = null;

    document.addEventListener('DOMContentLoaded', function () {
        initTransactionSystem();
    });

    function initTransactionSystem() {
        document.querySelectorAll('input[name="transaction_type"]').forEach(radio => {
            radio.addEventListener('change', handleTransactionTypeChange);
        });

        handleTransactionTypeChange();
    }

    function handleTransactionTypeChange() {
        const selected = document.querySelector('input[name="transaction_type"]:checked')?.value;

        const client = document.getElementById('client-transaction-section');
        const ad = document.getElementById('ad-transaction-section');

        if (!client || !ad) return;

        if (selected === 'client_transaction') {
            client.style.display = 'block';
            ad.style.display = 'none';
        } else {
            client.style.display = 'none';
            ad.style.display = 'block';

            detectAD();
        }
    }

    // function detectAD() {
    //     const adName = document.getElementById('assigned-ad-name');
    //     const adDetails = document.getElementById('assigned-ad-details');

    //     if (!adName || !adDetails) return;

    //     // ✅ PRIORITY: if user already selected manually
    //     if (window.selectedAD) {
    //         adName.textContent = window.selectedAD.name;
    //         // adDetails.textContent = `Area: ${window.selectedAD.area || 'N/A'}`;
    //         const selectedAreas = window.selectedAD.areas && window.selectedAD.areas.length
    //             ? window.selectedAD.areas.join(', ')
    //             : 'No Area Assigned';

    //         adDetails.textContent = `Areas: ${selectedAreas}`;
    //         return;
    //     }
    //     if (window.matchedADs.length > 0) {
    //         const nearest = window.matchedADs[0];

    //         window.selectedAD = nearest;

    //         adName.textContent = nearest.name;
    //         adDetails.textContent = `Distance: ${nearest.distance} km`;
    //     }

    //     // fallback logic
    //     if (window.matchedADs.length === 1) {
    //         const ad = window.matchedADs[0];
    //         window.selectedAD = ad;

    //         adName.textContent = ad.name;
    //         // adDetails.textContent = `Area: ${window.authUserCenter}`;
    //         const areaText = ad.areas && ad.areas.length
    //             ? ad.areas.map(area => area.area_name).join(', ')
    //             : 'No Area Assigned';

    //         adDetails.textContent = `Areas: ${areaText}`;
    //     }

    //     else if (window.matchedADs.length > 1) {
    //         adName.textContent = "Select Area Distributor";
    //         adDetails.textContent = `${window.matchedADs.length} ADs in your area`;
    //     }

    //     else if (window.availableADs.length > 0) {
    //         adName.textContent = "Select Area Distributor";
    //         adDetails.textContent = `${window.availableADs.length} distributors available`;
    //     }

    //     else {
    //         adName.textContent = "No AD Found";
    //         adDetails.textContent = "No distributor available";
    //     }
    // }
    function detectAD() {
        const adName = document.getElementById('assigned-ad-name');
        const adDetails = document.getElementById('assigned-ad-details');

        if (!adName || !adDetails) return;

        // helper to normalize areas
        const formatAreas = (areas) => {
            if (!areas || areas.length === 0) return [];

            // handle both formats:
            // ['Taguig'] OR [{area_name:'Taguig'}]
            if (typeof areas[0] === 'string') return areas;

            return areas.map(a => a.area_name);
        };

        // helper to build details string
        const buildDetails = (ad) => {
            let details = [];

            if (ad.distance !== null && ad.distance !== undefined) {
                details.push(`Distance: ${ad.distance} km`);
            }

            const areaNames = formatAreas(ad.areas);
            if (areaNames.length) {
                details.push(`Areas: ${areaNames.join(', ')}`);
            }

            return details.length ? details.join(' • ') : 'No details available';
        };

        // ✅ MANUAL SELECTION
        // if (window.selectedAD) {
        //     adName.textContent = window.selectedAD.name;
        //     adDetails.textContent = buildDetails(window.selectedAD);
        //     return;
        // }

        if (window.matchedADs.length > 0) {
            const nearest = window.matchedADs[0];

            window.selectedAD = nearest;

            adName.textContent = nearest.name;
            renderADDetails(nearest);
            return;
        }

        // ✅ AUTO SELECT NEAREST
        if (window.matchedADs.length > 0) {
            const nearest = window.matchedADs[0]; // must already be sorted

            window.selectedAD = nearest;

            adName.textContent = nearest.name;
            adDetails.textContent = buildDetails(nearest);
            return;
        }

        // ✅ FALLBACK
        if (window.availableADs.length > 0) {
            adName.textContent = "Select Area Distributor";
            adDetails.textContent = `${window.availableADs.length} distributors available`;
        } else {
            adName.textContent = "No AD Found";
            adDetails.textContent = "No distributor available";
        }
    }

    // function openADSelection() {
    //     const modal = document.getElementById('adModal');
    //     modal.classList.add('active');
    //     document.body.style.overflow = 'hidden';

    //     if (!window.adDropdown) {
    //         initADDropdown();
    //     }
    // }

    window.adDropdown = null;

    function initADDropdown() {
        let sourceList = window.matchedADs.length > 0
            ? window.matchedADs
            : window.availableADs;

        // const formatted = sourceList.map(ad => ({
        //     id: ad.id,
        //     name: ad.name,
        //     area: ad.area || ad.name
        // }));

        const formatted = sourceList.map(ad => ({
            id: ad.id,
            name: ad.name,
            areas: ad.areas ? ad.areas.map(area => area.area_name) : [],
            distance: ad.distance,
            address: ad.address
        }));

        window.adDropdown = new SearchableDropdown(
            'adDropdownContainer',
            formatted,
            {
                storagePrefix: 'selectedAD',
                nameElementId: 'assigned-ad-name',
                detailsElementId: 'assigned-ad-details',
                detailsFormat: 'Area: {area}'
            }
        );

        // window.adDropdown = new SearchableDropdown(
        //     'adDropdownContainer',
        //     formatted,
        //     {
        //         storagePrefix: 'selectedAD',
        //         nameElementId: 'assigned-ad-name',
        //         detailsElementId: 'assigned-ad-details',
        //         detailsFormat: 'Area: {area}'
        //     }
        // );
    }

    function toggleDeliveryAddress() {
        const selected = document.querySelector('input[name="delivery_type"]:checked')?.value;
        const pickupCard = document.getElementById('pickup-address-card');

        if (!pickupCard) return;

        if (selected === 'pickup') {
            pickupCard.style.display = 'block';
        } else {
            pickupCard.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        toggleDeliveryAddress();
    });

    function renderADDetails(ad) {
        const el = document.getElementById('assigned-ad-details');
        if (!el) return;

        const distance = ad.distance !== null && ad.distance !== undefined
            ? parseFloat(ad.distance)
            : null;

        const areas = ad.areas?.length
            ? (typeof ad.areas[0] === 'string'
                ? ad.areas
                : ad.areas.map(a => a.area_name))
            : [];

        const address = ad.address || ad.full_address || null;

        // ✅ Distance formatting
        let distanceText = 'Distance unknown';

        if (distance !== null) {
            distanceText = distance < 1
                ? `${Math.round(distance * 1000)} m`
                : `${distance.toFixed(2)} km`;
        }

        // Status styling
        let badgeColor = '#28a745';
        let statusText = 'Near';

        if (distance >= 10) {
            badgeColor = '#ffc107';
            statusText = 'Moderate';
        }

        if (distance >= 20) {
            badgeColor = '#dc3545';
            statusText = 'Far';
        }

        // Progress width
        const progressWidth = distance !== null
            ? Math.min((distance / 50) * 100, 100)
            : 0;

        el.innerHTML = `
            <div style="display:flex; flex-direction:column; gap:8px;">

                <!-- Distance Badge -->
                <div style="
                    display:inline-flex;
                    align-items:center;
                    gap:6px;
                    font-size:12px;
                    font-weight:600;
                    padding:5px 12px;
                    border-radius:20px;
                    background:${badgeColor}15;
                    color:${badgeColor};
                    width:fit-content;
                ">
                    <i class="bi bi-geo-alt-fill"></i>
                    ${distanceText}
                    <span style="font-size:10px; opacity:0.8;">
                        • ${statusText}
                    </span>
                </div>

                ${
                    distance !== null
                    ? `
                    <!-- Progress -->
                    <div style="
                        width:50%;
                        height:6px;
                        background:#eee;
                        border-radius:10px;
                        overflow:hidden;
                    ">
                        <div style="
                            width:${progressWidth}%;
                            height:100%;
                            background:${badgeColor};
                            transition:0.3s ease;
                        "></div>
                    </div>
                    `
                    : ''
                }

                <!-- Address -->
                <div style="
                    font-size:11px;
                    color:#555;
                    display:flex;
                    align-items:flex-start;
                    gap:5px;
                ">
                    <i class="bi bi-geo"></i>
                    <span>${address || 'No address available'}</span>
                </div>

                ${
                    areas.length
                    ? `
                    <div style="font-size:11px; color:#777;">
                        <i class="bi bi-map"></i>
                        ${areas.join(', ')}
                    </div>
                    `
                    : ''
                }

            </div>
        `;
    }
</script>
