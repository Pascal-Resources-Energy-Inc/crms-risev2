{{--  --}}
@extends('layouts.header')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="order-payment-page">
  <div class="content-area-fix">
    <div class="page-header-nya bg-white border-bottom">
      <div class="d-flex justify-content-between align-items-center position-relative px-3 py-3">
        <button class="btn btn-link back-btn p-1" onclick="history.back()">
          <i class="bi bi-arrow-left"></i>
        </button>
        <h1 class="page-title position-absolute start-50 translate-middle-x m-0">Order Payment</h1>
      </div>
    </div>

    <div class="section-card mx-3 my-3 bg-white rounded-3 shadow-sm">
      <div class="section-header d-flex align-items-center gap-2 p-3 border-bottom">
        <i class="bi bi-person-circle"></i>
        <span>Customer Details</span>
      </div>
      <div class="section-content p-3">
        <div class="customer-display d-flex align-items-center gap-3 p-3 rounded-2 bg-light">
          <div class="customer-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 50px; height: 50px; background: #D6F4FF;">
            <i class="bi bi-person-fill" style="font-size: 24px; color: #4A90E2;"></i>
          </div>
          <div class="customer-info flex-grow-1">
            <div class="customer-name fw-semibold" id="customer-name-display">Loading...</div>
            <div class="customer-details small text-muted" id="customer-details-display">Loading customer details...</div>
          </div>
        </div>
      </div>
    </div>

    <div class="section-card mx-3 my-3 bg-white rounded-3 shadow-sm">
      <div class="section-header d-flex align-items-center gap-2 p-3 border-bottom">
        <i class="bi bi-receipt"></i>
        <span>Order Summary</span>
      </div>
      <div class="section-content p-3">
        <div class="info-row d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="info-label">Payment Method:</span>
          <span class="info-value fw-semibold" id="payment-method">Cash on Delivery</span>
        </div>
        <div class="info-row d-flex justify-content-between align-items-center py-2 border-bottom">
          <span class="info-label">Total Items:</span>
          <span class="info-value fw-semibold" id="total-items">0</span>
        </div>
        <div class="info-row d-flex justify-content-between align-items-center py-3 bg-light mx-n3 px-3 mb-n3">
          <span class="info-label">Total Amount:</span>
          <span class="info-value fw-bold" id="order-total">₱ 0.00</span>
        </div>
        
        <div class="order-items border-top mt-3 pt-3" id="order-items-list">
          <!-- Items will be dynamically loaded -->
        </div>
      </div>
    </div>

    <div class="section-card mx-3 my-3 bg-white rounded-3 shadow-sm">
      <div class="section-header d-flex align-items-center gap-2 p-3 border-bottom">
        <i class="bi bi-credit-card"></i>
        <span>Payment Details</span>
      </div>
      <div class="section-content p-3">
        <div class="mb-3">
          <label class="form-label fw-semibold">Selected Payment Method</label>
          <div class="payment-method-display d-flex align-items-center gap-3 p-3 rounded-2" id="payment-method-display">
            <div class="payment-icon rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
              <i class="bi bi-cash-coin"></i>
            </div>
            <div class="payment-details flex-grow-1">
              <div class="payment-name fw-semibold">Cash on Delivery</div>
              <div class="payment-desc small text-muted">Customer pays upon delivery</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="complete-order-wrapper px-3 mt-3">
      <button class="btn btn-success complete-order-btn w-100 d-flex align-items-center justify-content-center gap-2 py-3 rounded-3 fw-semibold" id="complete-order-btn">
        <i class="bi bi-check-circle"></i>
        <span>Place Order</span>
      </button>
    </div>
  </div>
</div>
@endsection

@section('css')
  <link href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.10.1/sweetalert2.min.css" rel="stylesheet">
  
  <style>
    .content-area-fix {
        margin-top: -80px;
    }

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

    .page-title {
        font-size: 20px;
        font-weight: 600;
        color: #4A90E2;
    }

    .page-header-nya {
      outline: 0.2px solid #e1e1e1ff;
    }

    .section-card .bi-truck {
      color: #000000ff;
    }

    .section-card {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }

    .section-header {
      font-size: 18px;
      font-weight: 600;
      color: #333 !important;
    }

    .customer-display {
      border: 2px solid #e1e8ed;
      transition: all 0.3s ease;
    }

    .customer-display:hover {
      border-color: #4A90E2;
      box-shadow: 0 2px 8px rgba(74, 144, 226, 0.1);
    }

    .customer-name {
      font-size: 16px;
      color: #333;
      margin-bottom: 4px;
    }

    .customer-details {
      font-size: 13px;
      color: #666;
    }

    .customer-icon {
      transition: transform 0.3s ease;
    }

    .customer-display:hover .customer-icon {
      transform: scale(1.05);
    }

    .info-row {
      font-size: 14px !important;
    }

    .info-row:last-child {
      font-size: 16px !important;
      font-weight: 700;
      color: #4A90E2;
    }

    .info-label {
      color: #606060ff !important;
    }

    .info-value {
      color: #333 !important;
      font-weight: 600 !important;
    }

    .order-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      font-size: 13px;
    }

    .item-info {
      flex-grow: 1;
    }

    .quantity-item {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      background-color: #1976d2;
      color: #fff;
      font-weight: 600;
      font-size: 14px;
      border-radius: 6px;
      margin-right: 10px;
    }

    .item-name {
      color: #333;
      font-weight: 500;
      margin-bottom: 2px;
    }

    .item-details {
      color: #666;
      font-size: 12px;
    }

    .item-total {
      color: #4A90E2;
      font-weight: 600;
    }

    .payment-method-display {
      background: #f8f9fa;
      border: 2px solid #e1e8ed;
    }

    .payment-icon {
      width: 40px;
      height: 40px;
      background: #4A90E2;
      font-size: 18px;
      color: white;
    }

    .payment-icon.cod {
      background: #28a745;
    }

    .payment-icon.gcash {
      background: #007DFF;
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

    .complete-order-wrapper {
      bottom: 120px;
      left: var(--sidebar-width);
      right: 0;
      transition: left var(--transition-duration) ease;
      background: transparent;
      pointer-events: none;
    }

    .complete-order-btn {
      pointer-events: auto;
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      border: none;
      font-size: 16px;
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
      transition: all 0.2s ease;
    }

    .complete-order-btn:hover {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(40, 167, 69, 0.5);
    }

    .complete-order-btn:active {
      transform: scale(0.98);
    }

    .complete-order-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
      box-shadow: none;
    }

    .sidebar.collapsed ~ .main-content .complete-order-wrapper {
      left: var(--sidebar-collapsed-width);
    }

    @keyframes checkmark {
      0% { transform: scale(0); }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); }
    }

    .success-checkmark {
      animation: checkmark 0.3s ease-in-out;
    }

    @keyframes slideInUp {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .customer-name:empty::before,
    .customer-details:empty::before {
      content: 'Loading...';
      animation: pulse 1.5s ease-in-out infinite;
    }

    .swal2-popup {
      border-radius: 16px !important;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15) !important;
    }
    
    .swal2-title {
      font-weight: 700 !important;
      color: #333 !important;
      font-size: 1.5em !important;
    }
    
    .swal2-html-container {
      font-weight: 400 !important;
      line-height: 1.6 !important;
      color: #555 !important;
    }
    
    .swal2-confirm {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
      border: none !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      padding: 12px 24px !important;
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4) !important;
    }
    
    .swal2-confirm:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 6px 16px rgba(40, 167, 69, 0.5) !important;
    }
    
    .swal2-deny {
      background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%) !important;
      border: none !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      padding: 12px 24px !important;
      box-shadow: 0 4px 12px rgba(74, 144, 226, 0.4) !important;
    }
    
    .swal2-cancel {
      background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
      border: none !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      padding: 12px 24px !important;
      box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4) !important;
    }
    
    .swal2-timer-progress-bar {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
    }
    
    .swal2-icon.swal2-success {
      border-color: #28a745 !important;
      color: #28a745 !important;
    }

    @media (max-width: 768px) {
      .complete-order-wrapper {
        left: 0 !important;
        right: 0;
        bottom: 120px;
        z-index: 1100;
      }
    }

    @media (max-width: 480px) {
      .section-card {
        margin: 10px;
      }
      
      .section-header {
        padding: 15px;
        font-size: 16px !important;
      }
      
      .section-content {
        padding: 15px;
      }
      
      .info-row {
        padding: 10px 0;
        font-size: 13px !important;
      }
      
      .complete-order-btn {
        padding: 14px 18px;
        font-size: 15px;
      }

      .customer-display {
        gap: 10px !important;
      }

      .customer-icon {
        width: 45px !important;
        height: 45px !important;
      }

      .customer-name {
        font-size: 15px !important;
      }

      .customer-details {
        font-size: 12px;
      }
    }
  </style>
@endsection

@section('js')
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.10.1/sweetalert2.all.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    let orderData = {};
    let dealerCartData = [];
    let customerData = null;
    
    try {
        let storedOrderData = localStorage.getItem('dealerOrderData');
        if (!storedOrderData) {
            storedOrderData = localStorage.getItem('orderData');
        }
        
        const storedDealerCartData = localStorage.getItem('dealerCartData');
        
        if (storedOrderData) {
            orderData = JSON.parse(storedOrderData);
            customerData = orderData.customer;
            console.log('Loaded order data:', orderData);
        }
        
        if (storedDealerCartData) {
            dealerCartData = JSON.parse(storedDealerCartData);
            console.log('Loaded cart data:', dealerCartData);
        }
    } catch (error) {
        console.error('Error loading order data:', error);
        Swal.fire({
            title: 'Error Loading Data',
            text: 'Error loading order data. Please go back and try again.',
            icon: 'error',
            confirmButtonText: 'Go Back'
        }).then(() => {
            history.back();
        });
        return;
    }

    function displayCustomerDetails() {
        const customerNameDisplay = document.getElementById('customer-name-display');
        const customerDetailsDisplay = document.getElementById('customer-details-display');
        
        let customer = customerData || orderData.customer;
        
        if (!customer || !customer.id) {
            const customerId = localStorage.getItem('selectedCustomerId');
            const customerName = localStorage.getItem('selectedCustomerName');
            const customerSerial = localStorage.getItem('selectedCustomerSerial');
            const customerNumber = localStorage.getItem('selectedCustomerNumber');
            
            if (customerId && customerName) {
                customer = {
                    id: customerId,
                    name: customerName,
                    serial: customerSerial,
                    number: customerNumber
                };
                customerData = customer;
            }
        }
        
        if (customer && customer.id) {
            customerNameDisplay.textContent = customer.name;
            
            const detailsParts = [];
            if (customer.serial) detailsParts.push(`Serial: ${customer.serial}`);
            if (customer.number) detailsParts.push(`Number: ${customer.number}`);
            
            customerDetailsDisplay.textContent = detailsParts.join(' • ');
            
            console.log('Displaying customer:', customer);
        } else {
            customerNameDisplay.textContent = 'No Customer Selected';
            customerDetailsDisplay.textContent = '';
            
            Swal.fire({
                title: 'No Customer Selected',
                text: 'Please go back and select a customer.',
                icon: 'warning',
                confirmButtonText: 'Go Back'
            }).then(() => {
                history.back();
            });
        }
    }

    function initializeOrderPage() {
        const orderId = 'ORD-' + Date.now().toString().slice(-6);
        
        const totalItems = dealerCartData.reduce((sum, item) => sum + item.quantity, 0);
        const totalAmount = dealerCartData.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        document.getElementById('total-items').textContent = totalItems;
        document.getElementById('order-total').textContent = '₱ ' + totalAmount.toFixed(2);
        
        const paymentMethod = orderData.payment_method || 'cash';
        updatePaymentMethodDisplay(paymentMethod);
        
        displayOrderItems();
        displayCustomerDetails();
        
        orderData.order_id = orderId;
        orderData.total_amount = totalAmount;
    }

    function updatePaymentMethodDisplay(method) {
        const display = document.getElementById('payment-method-display');
        const paymentMethodText = document.getElementById('payment-method');
        
        if (method === 'gcash') {
            display.innerHTML = `
                <div class="payment-icon gcash rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="bi bi-phone-fill"></i>
                </div>
                <div class="payment-details flex-grow-1">
                    <div class="payment-name fw-semibold">GCash Payment</div>
                    <div class="payment-desc small text-muted">Payment completed via GCash</div>
                </div>
            `;
            paymentMethodText.textContent = 'GCash';
        } else {
            display.innerHTML = `
                <div class="payment-icon cod rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="payment-details flex-grow-1">
                    <div class="payment-name fw-semibold">Cash on Delivery</div>
                    <div class="payment-desc small text-muted">Customer pays upon delivery</div>
                </div>
            `;
            paymentMethodText.textContent = 'Cash on Delivery';
        }
    }

    function displayOrderItems() {
        const container = document.getElementById('order-items-list');
        let itemsHTML = '';
        
        dealerCartData.forEach(item => {
            const itemTotal = item.price * item.quantity;
            let colorInfo = '';
            
            if (item.color) {
                colorInfo = ` (${item.color.charAt(0).toUpperCase() + item.color.slice(1)})`;
            }
            
            itemsHTML += `
                <div class="order-item">
                    <div class="quantity-item">${item.quantity}</div>
                    <div class="item-info">
                        <div class="item-name">${item.originalName || item.name}${colorInfo}</div>
                        <div class="item-details">₱${item.price.toFixed(2)}</div>
                    </div>
                    <div class="item-total">₱${itemTotal.toFixed(2)}</div>
                </div>
            `;
        });
        
        container.innerHTML = itemsHTML;
    }

    async function saveTransactionsToDatabase() {
        try {
            const customerId = customerData?.id || localStorage.getItem('selectedCustomerId');
            
            console.log('Customer ID:', customerId);
            console.log('Customer Data:', customerData);
            console.log('Cart Items:', dealerCartData);
            
            if (!customerId) {
                throw new Error('No customer selected. Please go back and select a customer.');
            }

            if (!dealerCartData || dealerCartData.length === 0) {
                throw new Error('Cart is empty. Please add items to cart.');
            }

            let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            if (!csrfToken) {
                const tokenInput = document.querySelector('input[name="_token"]');
                csrfToken = tokenInput ? tokenInput.value : null;
            }
            
            console.log('CSRF Token:', csrfToken ? 'Found' : 'Not Found');
            
            if (!csrfToken) {
                throw new Error('CSRF token not found. Please refresh the page.');
            }

            let successCount = 0;
            let failedItems = [];

            for (let i = 0; i < dealerCartData.length; i++) {
                const item = dealerCartData[i];
                
                try {
                    console.log(`Saving item ${i + 1}/${dealerCartData.length}:`, item);
                    
                    const formData = new FormData();
                    formData.append('item_id', item.id);
                    formData.append('qty', item.quantity);
                    formData.append('customer_id', customerId);
                    formData.append('_token', csrfToken);

                    const storeUrl = "{{ route('transactions.store') }}";
                    
                    console.log('Sending request to:', storeUrl);
                    console.log('FormData:', {
                        item_id: item.id,
                        qty: item.quantity,
                        customer_id: customerId
                    });
                    
                    const response = await fetch(storeUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin',
                        redirect: 'follow'
                    });

                    console.log(`Response status for item ${i + 1}:`, response.status);
                    
                    const responseText = await response.text();
                    
                    let responseData;
                    try {
                        responseData = JSON.parse(responseText);
                        console.log(`Response data for item ${i + 1}:`, responseData);
                        
                        if (responseData.errors) {
                            console.error('Validation errors:', responseData.errors);
                            console.error('Request data sent:', responseData.request_data);
                        }
                    } catch (parseError) {
                        console.error('Failed to parse JSON. Raw response:', responseText.substring(0, 1000));
                        
                        if (responseText.includes('login') || responseText.includes('Login') || response.status === 401) {
                            throw new Error('Session expired. Please login again.');
                        }
                        
                        if (responseText.includes('404') || responseText.includes('Not Found')) {
                            throw new Error('Route not found. Please check if the route exists in web.php');
                        }
                        
                        if (responseText.includes('<!DOCTYPE') || responseText.includes('<html')) {
                            throw new Error('Server returned HTML instead of JSON. Check server logs for details.');
                        }
                        
                        throw new Error('Server returned invalid response. Please check server logs.');
                    }

                    if (!response.ok) {
                        throw new Error(responseData.message || `HTTP ${response.status}: Failed to save item`);
                    }

                    if (responseData.success || response.status === 200) {
                        successCount++;
                        console.log(`Item ${i + 1} saved successfully`);
                    } else {
                        throw new Error(responseData.message || 'Unknown error occurred');
                    }

                } catch (itemError) {
                    console.error(`Error saving item ${i + 1}:`, itemError);
                    failedItems.push({
                        name: item.originalName || item.name,
                        error: itemError.message
                    });
                }
            }

            if (failedItems.length > 0) {
                const failedItemsList = failedItems.map(fi => `- ${fi.name}: ${fi.error}`).join('\n');
                throw new Error(`Failed to save some items:\n${failedItemsList}\n\nSaved: ${successCount}/${dealerCartData.length}`);
            }
            
            console.log(`All ${successCount} transactions saved successfully!`);
            return true;

        } catch (error) {
            console.error('Error in saveTransactionsToDatabase:', error);
            throw error;
        }
    }

    const completeOrderBtn = document.getElementById('complete-order-btn');
    
    completeOrderBtn.addEventListener('click', async function() {
        const paymentMethod = orderData.payment_method || 'cash';
        
        const customerId = customerData?.id || localStorage.getItem('selectedCustomerId');
        if (!customerId) {
            Swal.fire({
                title: 'Customer Not Selected',
                text: 'Please go back and select a customer before placing the order.',
                icon: 'warning',
                confirmButtonText: 'Go Back'
            }).then(() => {
                history.back();
            });
            return;
        }

        if (!dealerCartData || dealerCartData.length === 0) {
            Swal.fire({
                title: 'Cart is Empty',
                text: 'Please add items to your cart before placing the order.',
                icon: 'warning',
                confirmButtonText: 'Go Back'
            }).then(() => {
                history.back();
            });
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Processing...</span>';
        
        try {
            console.log('Starting order placement...');
            console.log('Customer:', customerData);
            
            await saveTransactionsToDatabase();
            
            console.log('All transactions saved, completing order...');
            
            setTimeout(() => {
                orderData.completed_at = new Date().toLocaleString('en-PH', {
                    year: 'numeric',
                    month: 'long', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                orderData.status = 'completed';
                
                if (paymentMethod === 'cash') {
                    orderData.received_amount = orderData.total_amount;
                    orderData.change_amount = 0;
                }
                
                const completedOrders = JSON.parse(localStorage.getItem('completedOrders') || '[]');
                completedOrders.push(orderData);
                localStorage.setItem('completedOrders', JSON.stringify(completedOrders));
                
                localStorage.removeItem('dealerCartData');
                localStorage.removeItem('dealerCartItems');
                localStorage.removeItem('orderData');
                localStorage.removeItem('dealerOrderData');
                localStorage.removeItem('cartTotal');
                localStorage.removeItem('cartItems');
                localStorage.removeItem('selectedCustomerId');
                localStorage.removeItem('selectedCustomerName');
                localStorage.removeItem('selectedCustomerSerial');
                localStorage.removeItem('selectedCustomerNumber');
                localStorage.removeItem('selectedCustomerFullName');

                if (typeof updateCartBadge === 'function') updateCartBadge();
                if (typeof updateFloatingCartButton === 'function') updateFloatingCartButton();
                
                this.innerHTML = '<i class="bi bi-check-circle success-checkmark"></i> <span>Order Completed!</span>';
                
                Swal.fire({
                    title: 'Order Completed Successfully!',
                    html: `
                        <p>Order has been placed for <strong>${customerData?.name || 'Customer'}</strong></p>
                    `,
                    icon: 'success',
                    timer: 2500,
                    showConfirmButton: false,
                    timerProgressBar: true,
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = '{{url('/')}}';
                });
            }, 1000);

        } catch (error) {
            console.error('Failed to complete order:', error);
            
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-check-circle"></i> <span>Place Order</span>';
            
            Swal.fire({
                title: 'Error',
                text: error.message || 'Failed to save order to database. Please try again.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });

    initializeOrderPage();
    completeOrderBtn.disabled = false;
});
</script>
@endsection