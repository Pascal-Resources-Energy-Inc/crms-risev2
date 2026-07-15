<!-- product blade -->
@extends('layouts.header')

@section('content')

<div class="search-overlay" id="searchOverlay"></div>

<!-- Search Container -->
<div class="search-container" id="searchContainer">
  <form class="search-form" id="searchForm">
    <div class="search-input-wrapper"> 
      <input 
        type="text" 
        class="search-input" 
        id="searchInput" 
        placeholder="Search for products..."
        autocomplete="off"
      >
      <div class="search-results" id="searchResults"></div>
    </div>
    <button type="submit" class="search-btn">Search</button>
    <button type="button" class="close-search" id="closeSearch">
      <i class="bi bi-x"></i>
    </button>
  </form>
</div>

<!-- Using Bootstrap classes for top controls -->
<div class="top-controls mt-1 d-flex justify-content-between align-items-center p-3">
  <div class="dropdown">
    <button class="category-dropdown" type="button" id="categoryDropdown">
      <span id="selectedCategory">Select Customer</span>
      <i class="bi bi-chevron-down"></i>
    </button>
    <ul class="dropdown-menu" id="categoryDropdownMenu">
      <li class="dropdown-search-wrapper">
        <input type="text" class="dropdown-search-input" id="customerSearchInput" placeholder="Type to search customer..." autocomplete="off">
      </li>
      <li class="dropdown-empty-state" id="dropdownEmptyState">
        <div class="empty-state-content">
          <i class="bi bi-search"></i>
          <p>Start typing to search customers...</p>
        </div>
      </li>
      <li class="dropdown-no-results hidden" id="dropdownNoResults">
        <div class="empty-state-content">
          <i class="bi bi-exclamation-circle"></i>
          <p>No customers found</p>
        </div>
      </li>
      <li class="customer-list-wrapper">
        <a class="dropdown-item category-filter hidden" href="#" data-customer-id="">
          -- Clear Selection --
        </a>
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
          <a class="dropdown-item category-filter customer-option hidden" href="#" 
            data-customer-id="{{ $customer->id }}"
            data-name="{{ $masked }}"
            data-serial="{{ $masked_serial }}"
            data-number="{{ $masked_number }}"
            data-full-name="{{ $fullName }}"
            data-search="{{ strtolower($masked . ' ' . $masked_serial . ' ' . $masked_number) }}">
            <div class="customer-info">
              <div class="customer-name">{{ $masked }}</div>
              <div class="customer-details">
                <span class="customer-serial">Serial: {{ $masked_serial }}</span>
                <span class="customer-number">Number: {{ $masked_number }}</span>
              </div>
            </div>
          </a>
        @endforeach
      </li>
    </ul>
  </div>
  <!-- Using Bootstrap flex utilities -->
  <div class="d-flex gap-3">
    <div class="nav-icon-container">
    <i class="bi bi-search text-secondary fs-5 cursor-pointer"></i>
    </div>
    <a href="#" class="nav-item {{ request()->is('cart') ? 'active' : '' }}" id="cartNavLink">
        <div class="nav-icon-container">
            <i class="bi bi-cart"></i>
            <div class="cart-badge" id="cartBadge">0</div>
        </div>
    </a>
  </div>
</div>

<div class="content-area container-fluid" id="contentWrapper">
  <div class="row g-2">
    @forelse($products as $product)
      @php
        $dealerStock = (int) ($dealerStockByProduct[$product->id] ?? 0);
        $productPrice = $product->dealer_price ?? 0;
        $hasProductImage = !empty($product->product_image);
        $productImageUrl = $hasProductImage
            ? config('app.crms_admin_url') . '/public/uploads/products/' . $product->product_image
            : null;
      @endphp
      <div class="col-xl-4 col-lg-4 col-md-6 col-sm-12">
        <div class="product-card-container" id="container-{{ $product->id }}">
          <div class="product-card h-100">
            {{-- Product Image --}}
            <div class="product-image-container">
              @if($hasProductImage)
                <img
                  src="{{ $productImageUrl }}"
                  onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');"
                  alt="{{ $product->product_name }}"
                  class="product-image"
                >
                <div class="product-image-placeholder d-none" aria-label="No image available">
                  <span class="placeholder-icon"><i class="bi bi-image"></i></span>
                  <span class="placeholder-title">No image</span>
                </div>
              @else
                <div class="product-image-placeholder" aria-label="No image available">
                  <span class="placeholder-icon"><i class="bi bi-image"></i></span>
                  <span class="placeholder-title">No image</span>
                </div>
              @endif
              <span class="product-availability {{ $dealerStock > 0 ? 'is-available' : 'is-empty' }}">
                <i class="bi {{ $dealerStock > 0 ? 'bi-check2-circle' : 'bi-dash-circle' }}"></i>
                {{ $dealerStock > 0 ? 'Available' : 'Out of stock' }}
              </span>
            </div>
            {{-- Product Info --}}
            <div class="product-info">

                <div class="product-name">
                    <label>{{ $product->product_name }}</label>
                    <small>{{ $product->description }}</small>
                </div>

                <div class="stock-status-row">
                    <span class="stock-pill {{ $dealerStock > 0 ? 'stock-pill-available' : 'stock-pill-empty' }}">
                        <i class="bi bi-shop"></i>
                        <span>Dealer</span>
                        <strong>{{ $dealerStock > 0 ? $dealerStock : 0 }}</strong>
                    </span>
                </div>

                <div class="price-add-container">

                    <div class="price-block">
                        <span class="price-label">Price</span>
                        <div class="product-price">&#8369; {{ number_format($productPrice, 2) }}</div>
                    </div>

                    <div class="add-to-cart">
                        <button
                            type="button"
                            class="add-btn btn btn-primary rounded-circle p-0 d-flex justify-content-center align-items-center"
                            data-id="{{ $product->id }}"
                            data-container="container-{{ $product->id }}"
                            data-price="{{ $productPrice }}"
                            data-name="{{ $product->product_name }}"
                            data-image="{{ $productImageUrl ?? asset('images/no-image.png') }}"
                            data-dealer-stock="{{ $dealerStock }}"
                            aria-label="Add {{ $product->product_name }} to cart"
                        >
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>

                </div>
            </div>
          </div>
        </div>
      </div>
    @empty
      <div class="col-12">
        <div class="text-center py-5 text-muted">
          <i class="bi bi-box display-1 mb-3 d-block text-light"></i>
          <h3 class="fs-5 mb-2">No Products Available</h3>
          <p class="fs-6">There are currently no products to display.</p>
        </div>
      </div>
    @endforelse
  </div>
</div>

@endsection

@section('css')

<style>
.top-controls {
  background: #fff;
  margin-top: 0px !important;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  outline: 0.2px solid #e1e1e1ff;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  padding: 15px 20px;
}

.content-area {
  padding: 15px !important;
  text-align: left !important;
  /* padding-bottom: 120px !important; */
}

.toast {
  position: fixed;
  top: 80px;
  right: 20px;
  padding: 12px 20px;
  border-radius: 8px;
  z-index: 9999;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  transform: translateX(100%);
  transition: transform 0.3s ease;
  max-width: 300px;
  font-size: 14px;
}

.toast.success {
  background: #28a745;
  color: white;
}

.toast.error {
  background: #dc3545;
  color: white;
}

.toast.show {
  transform: translateX(0);
}

.category-dropdown {
  background: #fff;
  border: 2px solid #e9ecef;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #333;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  min-width: 250px !important;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.category-dropdown:hover {
  border-color: #4A90E2;
  box-shadow: 0 3px 8px rgba(74, 144, 226, 0.15);
}

.category-dropdown:focus {
  outline: none;
  border-color: #4A90E2;
  box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
}

#selectedCategory {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #666;
}

.category-dropdown.has-selection #selectedCategory {
  color: #333;
  font-weight: 600;
}

.category-dropdown i {
  font-size: 12px;
  color: #999;
  transition: transform 0.2s ease;
}

.category-dropdown.active i {
  transform: rotate(180deg);
}

.dropdown-empty-state,
.dropdown-no-results {
  padding: 2rem 1.5rem;
  text-align: center;
  pointer-events: none;
  user-select: none;
}

.empty-state-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}

.empty-state-content i {
  font-size: 2.5rem;
  opacity: 0.4;
  color: #6c757d;
}

.dropdown-empty-state .empty-state-content i {
  color: #0d6efd;
  opacity: 0.5;
}

.dropdown-no-results .empty-state-content i {
  color: #dc3545;
  opacity: 0.5;
}

.empty-state-content p {
  margin: 0;
  font-size: 0.9rem;
  color: #6c757d;
  font-weight: 500;
}

.hidden {
  display: none !important;
}

.dropdown {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  z-index: 1050;
  display: none;
  min-width: 320px;
  padding: 8px 0;
  margin: 0;
  font-size: 14px;
  list-style: none;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  max-height: 400px;
  overflow-y: auto;
}

.dropdown-menu.show {
  display: block;
  animation: dropdownSlideIn 0.2s ease-out;
}

@keyframes dropdownSlideIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dropdown-search-wrapper {
  padding: 8px 12px;
  background: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  margin-bottom: 4px;
  position: sticky;
  top: 0;
  z-index: 1;
}

.dropdown-search-input {
  width: 100%;
  padding: 10px 12px 10px 36px;
  border: 1px solid #dee2e6;
  border-radius: 8px;
  font-size: 13px;
  outline: none;
  transition: all 0.2s ease;
  background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 12px center;
}

.dropdown-search-input:focus {
  border-color: #4A90E2;
  box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
}

.dropdown-search-input::placeholder {
  color: #999;
}

.dropdown-item {
  display: block;
  width: 100%;
  padding: 10px 16px;
  color: #333;
  text-decoration: none;
  background: transparent;
  border: 0;
  transition: all 0.15s ease;
  cursor: pointer;
}

.dropdown-item:hover {
  background: #f8f9fa;
}

.dropdown-item[data-customer-id=""] {
  font-weight: 600;
  color: #666;
  border-bottom: 1px solid #e9ecef;
  padding: 12px 16px;
}

.dropdown-item[data-customer-id=""]:hover {
  background: #f0f0f0;
}

.customer-option {
  padding: 12px 16px !important;
  border-left: 3px solid transparent;
}

.customer-option:hover {
  background: linear-gradient(90deg, #f0f7ff 0%, #ffffff 100%);
  border-left-color: #4A90E2;
}

.customer-option.active {
  background: #e8f4fd;
  border-left-color: #4A90E2;
}

.customer-option.active .customer-name {
  color: #4A90E2;
  font-weight: 700;
}

.dropdown-item.hidden {
  display: none !important;
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.customer-name {
  font-size: 14px !important;
  font-weight: 600;
  color: #333;
  line-height: 1.3;
}

.customer-details {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: #666;
}

.customer-serial,
.customer-number {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.customer-serial::before {
  content: "📋";
  font-size: 10px;
}

.customer-number::before {
  content: "📱";
  font-size: 10px;
}

.customer-option:hover .customer-name {
  color: #4A90E2;
}

.customer-option:hover .customer-details {
  color: #4A90E2;
}
.add-btn.has-quantity {
  background: #ca1f1f;
}

.add-btn.has-quantity:hover {
  background: #ca1f1f;
}

.quantity-badge {
  font-size: 12px;
  font-weight: 700;
  color: #fff;
}

.product-card-container {
  height: 100%;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.product-card {
  position: relative;
  height: 100%;
  min-height: 390px;
  padding: 10px;
  border: 1px solid #e7edf4;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.product-card:hover {
  border-color: #bdd7f2;
  box-shadow: 0 14px 32px rgba(40, 91, 151, 0.14);
  transform: translateY(-2px);
}

.product-image-container {
  position: relative;
  width: 100%;
  height: 190px;
  border-radius: 7px;
  background: linear-gradient(180deg, #f7fafc 0%, #eef4f8 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.product-image-container img,
.product-image {
  width: 100%;
  height: 100%;
  padding: 16px;
  object-fit: contain;
  transition: transform 0.2s ease;
}

.product-image-placeholder {
  width: calc(100% - 24px);
  height: calc(100% - 24px);
  border: 1px dashed #c8d4df;
  border-radius: 6px;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.88), rgba(241, 246, 250, 0.88)),
    repeating-linear-gradient(135deg, rgba(83, 108, 133, 0.06) 0, rgba(83, 108, 133, 0.06) 8px, transparent 8px, transparent 16px);
  color: #7a8794;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-align: center;
}

.placeholder-icon {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: #ffffff;
  color: #536b85;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

.placeholder-title {
  font-size: 12px;
  font-weight: 700;
  color: #677685;
}

.product-card:hover .product-image-container img,
.product-card:hover .product-image {
  transform: scale(1.04);
}

.product-availability {
  position: absolute;
  top: 10px;
  left: 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 26px;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
}

.product-availability.is-available {
  color: #177245;
}

.product-availability.is-empty {
  color: #7a828c;
}

.product-info {
  flex: 1;
  padding: 12px 2px 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.product-name {
  min-height: 66px;
  margin-bottom: 0;
  line-height: 1.35;
}

.product-name label {
  display: -webkit-box;
  margin-bottom: 4px;
  color: #172033;
  font-size: 15px;
  font-weight: 700;
  line-height: 1.3;
  text-align: left;
  overflow: hidden;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.product-name small {
  display: -webkit-box;
  color: #687386;
  font-size: 12px;
  line-height: 1.35;
  text-align: left;
  overflow: hidden;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.stock-status-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
  margin: 0;
}

.stock-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  min-height: 34px;
  padding: 7px 8px;
  border: 1px solid;
  border-radius: 7px;
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
}

.stock-pill span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.stock-pill strong {
  margin-left: auto;
  font-size: 13px;
}

.stock-pill-available {
  background: #ecf8f1;
  color: #17693e;
  border-color: #bee7ce;
}

.stock-pill-empty {
  background: #f5f7f9;
  color: #737d8c;
  border-color: #e2e8ef;
}

.price-add-container {
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid #edf1f5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.price-label {
  display: block;
  margin-bottom: 2px;
  color: #7a8595;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: uppercase;
}

.product-price {
  color: #256fb4;
  font-size: 18px;
  font-weight: 800;
  line-height: 1.2;
}

.add-btn {
  width: 42px;
  height: 42px;
  background: #256fb4;
  color: #fff;
  border: none;
  font-size: 16px;
  box-shadow: 0 8px 18px rgba(37, 111, 180, 0.26);
  transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.add-btn:hover {
  background: #195b98;
  box-shadow: 0 10px 22px rgba(37, 111, 180, 0.32);
  transform: scale(1.05);
}

.add-btn:disabled {
  background: #98a2ad;
  box-shadow: none;
  cursor: not-allowed;
  opacity: 0.7;
}

.add-btn:disabled:hover {
  background: #98a2ad;
  transform: none;
}

@media (max-width: 575.98px) {
  .content-area {
    padding: 10px !important;
  }

  .product-card {
    min-height: 360px;
  }

  .product-image-container {
    height: 170px;
  }
}

/* Filter animations */
.product-card-container.filtered-out { 
  display: none !important; 
}

.product-card-container.filtered-in {
  display: block;
  animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
  from { 
    opacity: 0; 
    transform: translateY(20px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

.filter-empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #666;
}

.filter-empty-state i {
  font-size: 48px;
  margin-bottom: 16px;
  display: block;
  color: #ccc;
}

.filter-empty-state h3 {
  font-size: 18px;
  margin-bottom: 8px;
  font-weight: 600;
}

.clear-filter-btn {
  background: #4A90E2;
  color: #fff;
  border: none;
  padding: 10px 20px;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.2s ease;
}

.clear-filter-btn:hover { 
  background: #186ed1; 
}

/* Search functionality */
.search-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: #fff;
  padding: 15px 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  z-index: 1500;
  transform: translateY(-100%);
  transition: transform 0.3s ease-in-out;
  border-bottom: 1px solid #e1e1e1;
}

.search-container.active { 
  transform: translateY(0); 
}

.search-form {
  display: flex;
  align-items: center;
  gap: 15px;
}

.search-input-wrapper {
  flex: 1;
  position: relative;
}

.search-input {
  width: 100%;
  padding: 12px 16px;
  border: 2px solid #e1e1e1;
  border-radius: 8px;
  font-size: 16px;
  outline: none;
  transition: border-color 0.2s ease;
  background: #f8f9fa;
}

.search-input:focus {
  border-color: #4A90E2;
  background: #fff;
}

.search-input::placeholder { 
  color: #999; 
}

.search-btn {
  background: #4A90E2;
  color: #fff;
  border: none;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
  white-space: nowrap;
}

.search-btn:hover { 
  background: #186ed1; 
}

.close-search {
  background: none;
  border: none;
  font-size: 18px;
  color: #666;
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  transition: background 0.2s ease;
}

.close-search:hover { 
  background: #f5f5f5; 
}

.search-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.3);
  z-index: 1400;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.search-overlay.active {
  opacity: 1;
  visibility: visible;
}

.search-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #e1e1e1;
  border-top: none;
  border-radius: 0 0 8px 8px;
  max-height: 300px;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 10;
}

.search-result-item {
  padding: 12px 16px;
  border-bottom: 1px solid #f0f0f0;
  cursor: pointer;
  transition: background 0.2s ease;
  display: flex;
  align-items: center;
  gap: 12px;
}

.search-result-item:hover { 
  background: #f8f9fa; 
}

.search-result-item:last-child { 
  border-bottom: none; 
}

.search-result-image {
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 6px;
  background: #f5f5f5;
}

.search-result-info { 
  flex: 1; 
}

.search-result-name {
  font-size: 14px;
  font-weight: 500;
  color: #333;
  margin-bottom: 2px;
}

.search-result-price {
  font-size: 13px;
  color: #4A90E2;
  font-weight: 600;
}

.no-results {
  padding: 20px;
  text-align: center;
  color: #666;
  font-style: italic;
}

/* Quantity modal */
.color-selection-expansion {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  padding: 0;
  width: 90%;
  max-width: 350px;
  max-height: 80vh;
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  z-index: 2000;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.color-selection-expansion.active {
  opacity: 1;
  visibility: visible;
  padding: 20px;
}

.expansion-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1999;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.expansion-overlay.active {
  opacity: 1;
  visibility: visible;
}

.expansion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 15px;
  border-bottom: 1px solid #e9ecef;
}

.expansion-title {
  font-size: 16px;
  font-weight: 600;
  color: #333;
  margin: 0;
}

.close-expansion {
  background: none;
  border: none;
  color: #666;
  font-size: 18px;
  cursor: pointer;
  padding: 4px;
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s ease;
}

.close-expansion:hover {
  background: #f5f5f5;
  color: #333;
}

.expansion-total {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 0;
  border-top: 1px solid #e9ecef;
  margin-bottom: 15px;
}

.expansion-total-label {
  font-size: 14px;
  font-weight: 500;
  color: #666;
}

.expansion-total-price {
  font-size: 16px;
  font-weight: 700;
  color: #4A90E2;
}

.quantity-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  padding: 0;
  width: 90%;
  max-width: 400px;
  max-height: 80vh;
  overflow: hidden;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  z-index: 2000;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.quantity-modal.active {
  opacity: 1;
  visibility: visible;
  padding: 20px;
}

.product-info-modal {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px 0;
  border-bottom: 1px solid #f0f0f0;
  margin-bottom: 20px;
}

.modal-product-image {
  width: 60px;
  height: 60px;
  object-fit: contain;
  border-radius: 8px;
  background: #f5f5f5;
  padding: 8px;
}

.modal-product-details {
  flex: 1;
}

.modal-product-name {
  font-size: 16px;
  font-weight: 600;
  color: #333;
  margin-bottom: 4px;
  line-height: 1.3;
}

.modal-product-price {
  font-size: 18px;
  font-weight: 700;
  color: #4A90E2;
}

.quantity-selection {
  margin-bottom: 20px;
}

.quantity-input-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quantity-label {
  font-size: 14px;
  font-weight: 600;
  color: #333;
}

.quantity-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  justify-content: center;
}

.quantity-btn {
  background: #f8f9fa;
  border: 2px solid #e9ecef;
  color: #495057;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  font-size: 18px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.quantity-btn:hover {
  background: #e9ecef;
  border-color: #dee2e6;
  transform: translateY(-1px);
}

.quantity-btn:active {
  transform: translateY(0) scale(0.95);
}

.quantity-btn.minus-btn {
  color: #dc3545;
  border-color: #f5c6cb;
}

.quantity-btn.minus-btn:hover {
  background: #f8d7da;
  border-color: #f1aeb5;
}

.quantity-btn.plus-btn {
  color: #28a745;
  border-color: #c3e6cb;
}

.quantity-btn.plus-btn:hover {
  background: #d4edda;
  border-color: #b8dabd;
}

.quantity-input {
  width: 80px;
  height: 40px;
  text-align: center;
  font-size: 16px;
  font-weight: 600;
  border: 2px solid #e9ecef;
  border-radius: 8px;
  outline: none;
  transition: border-color 0.2s ease;
}

.quantity-input:focus {
  border-color: #4A90E2;
  box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
}

.quantity-input::-webkit-outer-spin-button,
.quantity-input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.quantity-input[type=number] {
  -moz-appearance: textfield;
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  padding-top: 15px;
  border-top: 1px solid #e9ecef;
}

.modal-action-btn {
  flex: 1;
  padding: 12px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.cancel-btn {
  background: #f8f9fa;
  color: #6c757d;
  border: 1px solid #e9ecef;
}

.cancel-btn:hover {
  background: #e9ecef;
  color: #495057;
  transform: translateY(-1px);
}

.confirm-btn {
  background: #4A90E2;
  color: #fff;
  box-shadow: 0 2px 4px rgba(74, 144, 226, 0.3);
}

.confirm-btn:hover {
  background: #186ed1;
  transform: translateY(-1px);
  box-shadow: 0 3px 6px rgba(74, 144, 226, 0.4);
}

.validation-modal {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  width: 90%;
  max-width: 380px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  z-index: 2100;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.validation-modal.active {
  opacity: 1;
  visibility: visible;
}

.validation-overlay-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2099;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.validation-overlay-modal.active {
  opacity: 1;
  visibility: visible;
}

.validation-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.validation-icon.warning {
  background: #fff3cd;
  color: #856404;
}

.validation-content {
  text-align: center;
}

.validation-title {
  font-size: 18px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.validation-message {
  font-size: 14px;
  color: #666;
  margin-bottom: 20px;
  line-height: 1.5;
}

.validation-list {
  text-align: left;
  margin: 16px 0;
  padding-left: 24px;
  list-style: none;
}

.validation-list li {
  font-size: 14px;
  color: #666;
  margin-bottom: 8px;
  line-height: 1.4;
  position: relative;
  padding-left: 20px;
}

.validation-list li:before {
  content: "⚠️";
  position: absolute;
  left: 0;
  font-size: 12px;
}

.validation-actions {
  display: flex;
  gap: 12px;
  margin-top: 20px;
}

.validation-btn {
  flex: 1;
  padding: 12px 16px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.validation-btn-primary {
  background: #4A90E2;
  color: #fff;
}

.validation-btn-primary:hover {
  background: #186ed1;
  transform: translateY(-1px);
}

@media (max-width: 480px) {
  .top-controls {
    padding: 12px 15px;
  }
  
  .content-area {
    margin-top: 0px !important;
    padding: 12px !important;
  }
  
  .category-dropdown {
    min-width: 160px;
    padding: 8px 12px;
    font-size: 13px;
  }
  
  .dropdown-menu {
    min-width: 280px;
  }
  
  .customer-name {
    font-size: 13px;
  }
  
  .customer-details {
    font-size: 11px;
    gap: 8px;
  }
  
  .customer-option {
    padding: 10px 12px !important;
  }
  
  .color-selection-expansion,
  .quantity-modal {
    width: 95%;
    max-height: 75vh;
  }
  
  .color-selection-expansion.active,
  .quantity-modal.active { 
    padding: 15px; 
  }
  
  .expansion-title { 
    font-size: 15px; 
  }
  
  .search-container { 
    padding: 12px 15px; 
  }
  
  .search-input {
    padding: 10px 14px;
    font-size: 14px;
  }
  
  .search-btn {
    padding: 10px 16px;
    font-size: 13px;
  }
  
  .modal-product-image {
    width: 50px;
    height: 50px;
  }
  
  .modal-product-name {
    font-size: 14px;
  }
  
  .modal-product-price {
    font-size: 16px;
  }
  
  .quantity-btn {
    width: 36px;
    height: 36px;
    font-size: 16px;
  }
  
  .quantity-input {
    width: 70px;
    height: 36px;
    font-size: 14px;
  }
  
  .modal-action-btn {
    padding: 10px 14px;
    font-size: 13px;
  }
  
  .quantity-badge {
    font-size: 12px;
  }
}

@media (max-width: 375px) {
  .top-controls {
    padding: 10px 12px;
  }
  
  .category-dropdown {
    min-width: 140px;
    padding: 7px 10px;
    font-size: 12px;
  }
  
  .dropdown-menu {
    min-width: 260px;
  }
}
</style>
@endsection

@section('js')
<script>
  document.addEventListener('DOMContentLoaded', function() {
  const searchContainer = document.getElementById('searchContainer');
  const searchOverlay = document.getElementById('searchOverlay');
  const searchInput = document.getElementById('searchInput');
  const searchResults = document.getElementById('searchResults');
  const searchForm = document.getElementById('searchForm');
  const closeSearchBtn = document.getElementById('closeSearch');
  const searchIcon = document.querySelector('.nav-icon-container .bi-search');

  const productCards = document.querySelectorAll('.product-card-container');
  const contentWrapper = document.getElementById('contentWrapper');

  let allProducts = [];

  productCards.forEach(card => {
    const nameElement = card.querySelector('.product-name');
    const priceElement = card.querySelector('.product-price');
    const imageElement = card.querySelector('img');
    
    if (nameElement && priceElement) {
      allProducts.push({
        name: nameElement.textContent.trim(),
        price: priceElement.textContent.trim(),
        image: imageElement ? imageElement.src : '',
        element: card
      });
    }
  });

  function showSearch() {
    searchContainer.classList.add('active');
    searchOverlay.classList.add('active');
    searchInput.focus();
    document.body.style.overflow = 'hidden';
  }

  function hideSearch() {
    searchContainer.classList.remove('active');
    searchOverlay.classList.remove('active');
    searchInput.value = '';
    searchResults.innerHTML = '';
    document.body.style.overflow = '';
  }

  function performSearch(query) {
    if (!query.trim()) {
      searchResults.innerHTML = '';
      return;
    }

    const filtered = allProducts.filter(product => 
      product.name.toLowerCase().includes(query.toLowerCase())
    );

    if (filtered.length === 0) {
      searchResults.innerHTML = '<div class="no-results">No products found</div>';
      return;
    }

    const resultsHTML = filtered.map(product => `
      <div class="search-result-item" data-product-name="${product.name}">
        <img src="${product.image}" alt="${product.name}" class="search-result-image" onerror="this.src='${window.location.origin}/images/stovewcyllinder.jpg'">
        <div class="search-result-info">
          <div class="search-result-name">${product.name}</div>
          <div class="search-result-price">${product.price}</div>
        </div>
      </div>
    `).join('');

    searchResults.innerHTML = resultsHTML;
  }

  if (searchIcon) {
    searchIcon.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      showSearch();
    });
  }

  if (closeSearchBtn) closeSearchBtn.addEventListener('click', (e) => { e.preventDefault(); hideSearch(); });
  if (searchOverlay) searchOverlay.addEventListener('click', (e) => { e.preventDefault(); hideSearch(); });
  if (searchInput) searchInput.addEventListener('input', (e) => performSearch(e.target.value));
  
  if (searchForm) {
    searchForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const query = searchInput.value.trim();
      if (query) {
        filterProducts(query);
        hideSearch();
      }
    });
  }

  document.addEventListener('click', (e) => {
    if (e.target.closest('.search-result-item')) {
      const resultItem = e.target.closest('.search-result-item');
      const productName = resultItem.dataset.productName;
      filterProducts(productName);
      hideSearch();
    }
  });

  const categoryDropdown = document.getElementById('categoryDropdown');
  const categoryDropdownMenu = document.getElementById('categoryDropdownMenu');
  const selectedCategory = document.getElementById('selectedCategory');
  const categoryFilters = document.querySelectorAll('.category-filter');
  const customerSearchInput = document.getElementById('customerSearchInput');
  const emptyState = document.getElementById('dropdownEmptyState');
  const noResults = document.getElementById('dropdownNoResults');
  const customerOptions = document.querySelectorAll('.customer-option');
  const clearSelection = document.querySelector('.category-filter[data-customer-id=""]');

  let selectedCustomerId = '';

  if (emptyState) emptyState.classList.remove('hidden');
  if (noResults) noResults.classList.add('hidden');
  customerOptions.forEach(option => option.classList.add('hidden'));
  if (clearSelection) clearSelection.classList.add('hidden');

  if (categoryDropdown) {
    categoryDropdown.addEventListener('click', (e) => {
      e.stopPropagation();
      categoryDropdownMenu.classList.toggle('show');
      
      if (categoryDropdownMenu.classList.contains('show') && customerSearchInput) {
        customerSearchInput.value = '';
        customerSearchInput.focus();
        if (emptyState) emptyState.classList.remove('hidden');
        if (noResults) noResults.classList.add('hidden');
        customerOptions.forEach(option => option.classList.add('hidden'));
        if (clearSelection) clearSelection.classList.add('hidden');
      }
    });
  }

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.dropdown')) {
      categoryDropdownMenu.classList.remove('show');
    }
  });

  if (customerSearchInput) {
    customerSearchInput.addEventListener('input', (e) => {
      const searchTerm = e.target.value.toLowerCase().trim();
      
      if (searchTerm === '') {
        if (emptyState) emptyState.classList.remove('hidden');
        if (noResults) noResults.classList.add('hidden');
        customerOptions.forEach(option => option.classList.add('hidden'));
        if (clearSelection) clearSelection.classList.add('hidden');
        return;
      }
      
      if (emptyState) emptyState.classList.add('hidden');
      
      let hasResults = false;
      
      categoryFilters.forEach(filter => {
        if (filter.dataset.customerId === '') return;
        
        const searchData = filter.getAttribute('data-search') || '';
        const name = (filter.getAttribute('data-name') || '').toLowerCase();
        const serial = (filter.getAttribute('data-serial') || '').toLowerCase();
        const number = (filter.getAttribute('data-number') || '').toLowerCase();
        
        if (searchData.includes(searchTerm) ||
            name.includes(searchTerm) ||
            serial.includes(searchTerm) ||
            number.includes(searchTerm)) {
          filter.classList.remove('hidden');
          hasResults = true;
        } else {
          filter.classList.add('hidden');
        }
      });
      
      if (hasResults && clearSelection) {
        clearSelection.classList.remove('hidden');
      } else if (clearSelection) {
        clearSelection.classList.add('hidden');
      }
      
      if (!hasResults) {
        if (noResults) noResults.classList.remove('hidden');
      } else {
        if (noResults) noResults.classList.add('hidden');
      }
    });
    
    customerSearchInput.addEventListener('click', (e) => e.stopPropagation());
  }

  categoryFilters.forEach(filter => {
    filter.addEventListener('click', (e) => {
      e.preventDefault();
      const customerId = filter.dataset.customerId;
      const customerName = filter.dataset.name || '-- Select Customer --';
      const customerFullName = filter.dataset.fullName || '';
      const customerSerial = filter.dataset.serial || '';
      const customerNumber = filter.dataset.number || '';
      
      selectedCategory.textContent = customerName;
      
      categoryFilters.forEach(f => f.classList.remove('active'));
      filter.classList.add('active');
      
      selectedCustomerId = customerId;
      
      if (customerId) {
        localStorage.setItem('selectedCustomerId', customerId);
        localStorage.setItem('selectedCustomerName', customerName);
        localStorage.setItem('selectedCustomerFullName', customerFullName);
        localStorage.setItem('selectedCustomerSerial', customerSerial);
        localStorage.setItem('selectedCustomerNumber', customerNumber);
        
        console.log('Customer saved to localStorage:', {
          id: customerId,
          name: customerName,
          serial: customerSerial,
          number: customerNumber
        });
      } else {
        localStorage.removeItem('selectedCustomerId');
        localStorage.removeItem('selectedCustomerName');
        localStorage.removeItem('selectedCustomerFullName');
        localStorage.removeItem('selectedCustomerSerial');
        localStorage.removeItem('selectedCustomerNumber');
      }
      
      categoryDropdownMenu.classList.remove('show');
      
      if (customerSearchInput) {
        customerSearchInput.value = '';
        if (emptyState) emptyState.classList.remove('hidden');
        if (noResults) noResults.classList.add('hidden');
        customerOptions.forEach(option => option.classList.add('hidden'));
        if (clearSelection) clearSelection.classList.add('hidden');
      }
      
      console.log('Selected customer ID:', customerId);
    });
  });

  function filterProducts(searchQuery) {
    let visibleCount = 0;
    
    productCards.forEach(card => {
      const productName = card.querySelector('.product-name').textContent.toLowerCase();
      const matchesSearch = productName.includes(searchQuery.toLowerCase());
      
      if (matchesSearch) {
        card.classList.remove('filtered-out');
        card.classList.add('filtered-in');
        visibleCount++;
      } else {
        card.classList.add('filtered-out');
        card.classList.remove('filtered-in');
      }
    });

    toggleEmptyState(visibleCount === 0);
  }

  function toggleEmptyState(show) {
    let emptyState = document.querySelector('.filter-empty-state');
    
    if (show && !emptyState) {
      const emptyHTML = `
        <div class="col-12">
          <div class="filter-empty-state">
            <i class="bi bi-search"></i>
            <h3>No products found</h3>
            <p>Try adjusting your search</p>
            <button class="clear-filter-btn" onclick="clearSearchFilter()">Clear Search</button>
          </div>
        </div>
      `;
      contentWrapper.querySelector('.row').insertAdjacentHTML('beforeend', emptyHTML);
    } else if (!show && emptyState) {
      emptyState.closest('.col-12').remove();
    }
  }

  window.clearSearchFilter = function() {
    productCards.forEach(card => {
      card.classList.remove('filtered-out');
      card.classList.add('filtered-in');
    });
    
    const emptyState = document.querySelector('.filter-empty-state');
    if (emptyState) {
      emptyState.closest('.col-12').remove();
    }
  };

  window.getSelectedCustomerId = function() {
    return selectedCustomerId;
  };

  const addButtons = document.querySelectorAll('.add-btn');
    
  let expansionOverlay = document.getElementById('expansionOverlay');
  if (!expansionOverlay) {
    expansionOverlay = document.createElement('div');
    expansionOverlay.className = 'expansion-overlay';
    expansionOverlay.id = 'expansionOverlay';
    document.body.appendChild(expansionOverlay);
  }

  let cart = {
    items: 0,
    amount: 0,
    products: []
  };

  const buttonQuantities = new Map();
  const buttonToProduct = new Map();

  let currentExpansion = null;

  function getDealerStock(button) {
    return Math.max(0, parseInt(button.getAttribute('data-dealer-stock') || '0', 10) || 0);
  }

  function getSafeQuantity(quantity) {
    return Math.max(0, Math.min(parseInt(quantity, 10) || 0, 999));
  }

  function selectCustomerInDropdown(customerId) {
    const customerOption = document.querySelector(`.category-filter[data-customer-id="${customerId}"]`);
    
    if (customerOption) {
      customerOption.click();
      
      setTimeout(() => {
        displayToast('Customer selected from QR scan!', 'success', 2000);
      }, 300);
    } else {
      console.warn('Customer not found in dropdown');
      alert('Customer found but not available in the dropdown list.');
    }
  }
  
  window.selectCustomerInDropdown = selectCustomerInDropdown;
  
  const scannedCustomerId = localStorage.getItem('scannedCustomerId');
  
  if (scannedCustomerId) {
    setTimeout(() => {
      selectCustomerInDropdown(scannedCustomerId);
      
      localStorage.removeItem('scannedCustomerId');
      localStorage.removeItem('scannedCustomerName');
      localStorage.removeItem('scannedCustomerSerial');
      localStorage.removeItem('scannedCustomerNumber');
    }, 500);
  }

  addButtons.forEach((button, index) => {
    const productName = button.getAttribute('data-name');
    const productId = button.getAttribute('data-id');
    const dealerStock = getDealerStock(button);

    buttonToProduct.set(button, {
      id: productId,
      name: productName,
      price: parseFloat(button.getAttribute('data-price')),
      image: button.getAttribute('data-image'),
      containerId: button.getAttribute('data-container') || button.closest('[id^="container-"]')?.id,
      dealerStock: dealerStock
    });

    buttonQuantities.set(button, 0);
    updateButtonDisplay(button, 0);
  });

  loadSavedCart();
  addButtons.forEach(button => {
    button.addEventListener('click', function () {
      const productData = buttonToProduct.get(this);
      if (!productData) return;

      const { name, price, image, dealerStock } = productData;

      if (currentExpansion) closeExpansion();

      showQuantityModal(this, name, price, image, dealerStock);

      this.style.transform = 'scale(0.9)';
      setTimeout(() => {
        this.style.transform = 'scale(1)';
      }, 150);
    });
  });

  function updateCartAndTriggerHeaderUpdate() {
    saveCartToLocalStorage();
    
    if (typeof window.updateCartBadge === 'function') {
      window.updateCartBadge();
    }
    if (typeof window.updateFloatingCartButton === 'function') {
      window.updateFloatingCartButton();
    }
    
    window.dispatchEvent(new CustomEvent('cartUpdated', {
      detail: { 
        items: cart.items, 
        amount: cart.amount, 
        products: cart.products.length 
      }
    }));
  }

  function saveCartToLocalStorage() {
    try {
      localStorage.setItem('dealerCartData', JSON.stringify(cart.products));
      localStorage.setItem('dealerCartTotal', cart.amount.toFixed(2));
      localStorage.setItem('dealerCartItems', cart.items.toString());
      
      localStorage.setItem('cartData', JSON.stringify(cart.products));
      localStorage.setItem('cartTotal', cart.amount.toFixed(2));
      localStorage.setItem('cartItems', cart.items.toString());
      
      console.log('Cart saved to localStorage:', {
        items: cart.items,
        amount: cart.amount,
        products: cart.products.length
      });
      
      return true;
    } catch (error) {
      console.error('Error saving cart:', error);
      return false;
    }
  }

  function updateRegularProductCart(productData, button, oldQuantity, newQuantity) {
    const quantityDiff = newQuantity - oldQuantity;
    const priceDiff = productData.price * quantityDiff;
    
    cart.items += quantityDiff;
    cart.amount += priceDiff;
    
    const buttonId = Array.from(addButtons).indexOf(button);
    
    const existingProductIndex = cart.products.findIndex(p => 
      p.name === productData.name && p.buttonId === buttonId
    );
    
    if (newQuantity === 0) {
      if (existingProductIndex !== -1) {
        cart.products.splice(existingProductIndex, 1);
      }
    } else if (existingProductIndex !== -1) {
      cart.products[existingProductIndex].quantity = newQuantity;
    } else {
      cart.products.push({
        id: productData.id,
        name: productData.name,
        originalName: productData.name,
        price: productData.price,
        quantity: newQuantity,
        image: productData.image,
        dealerStock: productData.dealerStock,
        buttonId: buttonId
      });
    }
    
    updateCartAndTriggerHeaderUpdate();
  }

  function sanitizeCartAgainstDealerStock() {
    let adjusted = false;

    cart.products = cart.products.reduce((products, product) => {
      const button = Array.from(addButtons).find((candidate, index) => {
        const data = buttonToProduct.get(candidate);

        return data && (
          data.id === String(product.id) ||
          (product.buttonId !== undefined && product.buttonId === index) ||
          data.name === product.name ||
          data.name === product.originalName
        );
      });

      if (!button) {
        adjusted = true;
        return products;
      }

      const productData = buttonToProduct.get(button);
      const dealerStock = productData.dealerStock;
      const safeQuantity = getSafeQuantity(product.quantity);

      if (safeQuantity !== product.quantity) {
        adjusted = true;
      }

      if (safeQuantity > 0) {
        products.push({
          ...product,
          quantity: safeQuantity,
          dealerStock: dealerStock
        });
      }

      return products;
    }, []);

    cart.items = cart.products.reduce((sum, product) => sum + product.quantity, 0);
    cart.amount = cart.products.reduce((sum, product) => sum + (product.price * product.quantity), 0);

    if (adjusted) {
      displayToast('Cart updated because dealer stock changed.', 'error');
    }
  }

  function loadSavedCart() {
    try {
      const savedProducts = localStorage.getItem('dealerCartData');
      const savedItems = localStorage.getItem('dealerCartItems');
      const savedTotal = localStorage.getItem('dealerCartTotal');
      
      if (savedProducts) {
        cart.products = JSON.parse(savedProducts);
        cart.items = parseInt(savedItems) || 0;
        cart.amount = parseFloat(savedTotal) || 0;
        
        console.log('Found saved cart:', cart);
        
        sanitizeCartAgainstDealerStock();
        updateButtonsFromSavedData();
        updateCartAndTriggerHeaderUpdate();
      }
    } catch (error) {
      console.error('Error loading saved cart:', error);
      cart = { items: 0, amount: 0, products: [] };
    }
  }
  function updateButtonsFromSavedData() {
    addButtons.forEach((button, buttonIndex) => {
      const productData = buttonToProduct.get(button);
      if (!productData) return;

      const matchingProducts = cart.products.filter(product =>
        (product.name === productData.name || product.originalName === productData.name) &&
        (product.buttonId === buttonIndex || product.buttonId === undefined)
      );

      if (matchingProducts.length > 0) {
        const totalQuantity = matchingProducts.reduce((sum, product) => sum + product.quantity, 0);

        buttonQuantities.set(button, totalQuantity);
        updateButtonDisplay(button, totalQuantity);
      }
    });
  }

  function displayToast(message, type, duration) {
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, duration || 3000);
  }

  

  function showQuantityModal(button, productName, price, productImage, dealerStock) {
    const currentQuantity = getSafeQuantity(buttonQuantities.get(button));
    
    const existingModal = document.querySelector('.quantity-modal');
    if (existingModal) existingModal.remove();

    const modalHTML = `
      <div class="color-selection-expansion quantity-modal" data-product-name="${productName}" data-product-price="${price}" data-product-image="${productImage}">
        <div class="expansion-header">
          <h4 class="expansion-title">Select Quantity</h4>
          <button class="close-expansion">
            <i class="bi bi-x"></i>
          </button>
        </div>
        
        <div class="quantity-selection">
          <div class="product-info-modal">
            <img src="${productImage}" alt="${productName}" class="modal-product-image" 
                 onerror="this.src='${window.location.origin}/images/stovewcyllinder.jpg'">
            <div class="modal-product-details">
              <div class="modal-product-name">${productName}</div>
              <div class="modal-product-price">₱ ${price.toFixed(2)}</div>
            </div>
          </div>
          
          <div class="quantity-input-section">
            <label class="quantity-label">Quantity:</label>
            <div class="current_cart_qty" style="font-size: 12px; color: #666; margin-bottom: 10px;">
                Currently in cart: ${currentQuantity}
            </div>
            <div class="dealer_stock_qty" style="font-size: 12px; color: #666; margin-bottom: 10px;">
                Dealer stock: ${dealerStock}
            </div>
            <div class="quantity-controls">
              <button type="button" class="quantity-btn minus-btn" data-action="decrease">-</button>
              <input type="number" class="quantity-input" placeholder="0" min="0" value="${currentQuantity}" max="999">
              <button type="button" class="quantity-btn plus-btn" data-action="increase">+</button>
            </div>
          </div>
        </div>
        
        <div class="expansion-total">
          <span class="expansion-total-label">Total:</span>
          <span class="expansion-total-price">₱ ${(price * currentQuantity).toFixed(2)}</span>
        </div>
        
        <div class="modal-actions">
          <button class="modal-action-btn cancel-btn">Cancel</button>
          <button class="modal-action-btn confirm-btn">Confirm</button>
        </div>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    const modal = document.querySelector('.quantity-modal');
    currentExpansion = modal;
    
    expansionOverlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    setupQuantityModalEvents(modal, price, button, dealerStock);
  }

  function setupQuantityModalEvents(modal, basePrice, button, dealerStock) {
    const quantityInput = modal.querySelector('.quantity-input');
    const totalPrice = modal.querySelector('.expansion-total-price');
    const confirmBtn = modal.querySelector('.confirm-btn');
    const cancelBtn = modal.querySelector('.cancel-btn');
    const closeBtn = modal.querySelector('.close-expansion');
    const plusBtn = modal.querySelector('.plus-btn');
    const minusBtn = modal.querySelector('.minus-btn');

    function updateTotal() {
      const quantity = getSafeQuantity(quantityInput.value);
      quantityInput.value = quantity;
      const total = basePrice * quantity;
      totalPrice.textContent = `₱ ${total.toFixed(2)}`;
    }

    plusBtn.addEventListener('click', () => {
      const currentValue = parseInt(quantityInput.value) || 0;
      quantityInput.value = Math.min(currentValue + 1, 999);
      updateTotal();
    });

    minusBtn.addEventListener('click', () => {
      const currentValue = parseInt(quantityInput.value) || 0;
      quantityInput.value = Math.max(currentValue - 1, 0);
      updateTotal();
    });

    quantityInput.addEventListener('input', () => {
      let value = parseInt(quantityInput.value) || 0;

      value = getSafeQuantity(value);
      quantityInput.value = value;
      updateTotal();
    });

    confirmBtn.addEventListener('click', () => {
      const newQuantity = parseInt(quantityInput.value) || 0;
      const productData = buttonToProduct.get(button);
      
      if (!productData) return;

      const oldQuantity = buttonQuantities.get(button) || 0;
      const addedQuantity = newQuantity - oldQuantity;
      
      buttonQuantities.set(button, newQuantity);
      
      updateRegularProductCart(productData, button, oldQuantity, newQuantity);
      updateButtonDisplay(button, newQuantity);
      
      if (addedQuantity > 0) {
        displayToast(`${addedQuantity} item(s) added to cart!`, 'success');
      } else if (addedQuantity < 0) {
        displayToast(`${Math.abs(addedQuantity)} item(s) removed from cart!`, 'info');
      }
      
      closeExpansion();
    });

    cancelBtn.addEventListener('click', closeExpansion);
    closeBtn.addEventListener('click', closeExpansion);
  }

  function updateButtonDisplay(button, quantity) {
    const productData = buttonToProduct.get(button);
    const isStove = productData && productData.name.toLowerCase().includes('stove');
    
    if (quantity > 0) {
      button.innerHTML = `<span class="quantity-badge">${quantity}</span>`;
      button.classList.add('has-quantity');
      
      if (isStove) {
        button.classList.add('stove-product');
      }
    } else {
      button.innerHTML = `<i class="bi bi-plus"></i>`;
      button.classList.remove('has-quantity', 'stove-product');
    }
  }

  function closeExpansion() {
    if (currentExpansion) {
      currentExpansion.classList.remove('active');
      
      setTimeout(() => {
        if (currentExpansion && (
          currentExpansion.classList.contains('quantity-modal')
        )) {
          currentExpansion.remove();
        }
      }, 300);
      
      currentExpansion = null;
    }
    
    expansionOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  expansionOverlay.addEventListener('click', closeExpansion);
  
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && currentExpansion) {
      closeExpansion();
    }
  });

  setTimeout(() => {
    if (typeof window.updateCartBadge === 'function') {
      window.updateCartBadge();
    }
    if (typeof window.updateFloatingCartButton === 'function') {
      window.updateFloatingCartButton();
    }
  }, 100);
});


window.triggerCartUpdate = function() {
  if (typeof window.updateCartBadge === 'function') {
    window.updateCartBadge();
  }
  if (typeof window.updateFloatingCartButton === 'function') {
    window.updateFloatingCartButton();
  }
};

window.addEventListener('storage', function(e) {
  if (e.key === 'dealerCartData' || e.key === 'dealerCartItems' || e.key === 'dealerCartTotal') {
    window.triggerCartUpdate();
  }
});

window.addEventListener('cartUpdated', function(e) {
  console.log('Cart updated event received:', e.detail);
});
</script>

<script>
function showValidationModal(issues) {
  let validationOverlay = document.getElementById('validationOverlayModal');
  if (!validationOverlay) {
    validationOverlay = document.createElement('div');
    validationOverlay.className = 'validation-overlay-modal';
    validationOverlay.id = 'validationOverlayModal';
    document.body.appendChild(validationOverlay);
  }

  let validationModal = document.getElementById('validationModal');
  if (!validationModal) {
    validationModal = document.createElement('div');
    validationModal.className = 'validation-modal';
    validationModal.id = 'validationModal';
    document.body.appendChild(validationModal);
  }

  let issuesList = '<ul class="validation-list">';
  issues.forEach(issue => {
    issuesList += `<li>${issue}</li>`;
  });
  issuesList += '</ul>';

  validationModal.innerHTML = `
    <div class="validation-icon warning">
      <i class="bi bi-exclamation-triangle"></i>
    </div>
    <div class="validation-content">
      <h4 class="validation-title">Cannot Proceed to Cart</h4>
      <p class="validation-message">Please complete the following:</p>
      ${issuesList}
    </div>
    <div class="validation-actions">
      <button class="validation-btn validation-btn-primary" onclick="closeValidationModal()">Got it</button>
    </div>
  `;

  validationOverlay.classList.add('active');
  validationModal.classList.add('active');
  document.body.style.overflow = 'hidden';

  validationOverlay.onclick = closeValidationModal;
}

function closeValidationModal() {
  const validationOverlay = document.getElementById('validationOverlayModal');
  const validationModal = document.getElementById('validationModal');

  if (validationModal) validationModal.classList.remove('active');
  if (validationOverlay) validationOverlay.classList.remove('active');
  document.body.style.overflow = '';
}

const cartNavLink = document.getElementById('cartNavLink');

if (cartNavLink) {
  cartNavLink.addEventListener('click', function(e) {
    e.preventDefault();
    
    const cartItems = parseInt(localStorage.getItem('dealerCartItems') || '0');
    const issues = [];

    if (cartItems === 0) {
      issues.push('Your cart is empty. Please add products to continue');
    }

    if (issues.length > 0) {
      showValidationModal(issues);
      return;
    }

    window.location.href = "{{url('/cart')}}";
  });
}
</script>
@endsection
