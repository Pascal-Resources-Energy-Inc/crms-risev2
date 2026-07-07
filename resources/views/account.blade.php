@extends('layouts.header')

@section('content')
<div class="account-wrapper">
    <div class="content-wrapper">
        <div class="account-header">
            <button class="back-button" onclick="window.location.href='{{ url('/') }}'">
                <i class="bi bi-arrow-left"></i>
            </button>
            
            <div class="profile-section">
                <img src="{{ $profile->avatar ?? url('design/assets/images/profile/user-1.png') }}" onerror="this.src='{{url('design/assets/images/profile/user-1.png')}}';" alt="Avatar Image" class="profile-avatar">
                     
                <h2 class="profile-name">{{auth()->user()->name}}</h2>
                <p class="profile-email">{{auth()->user()->email}}</p>
            </div>
        </div>

        <div class="main-content">
            @if(session('success'))
                <div class="account-alert success">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="account-alert error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="stats-section mb-2">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="stat-card">
                            <h3 class="stat-value">{{ number_format($available_points ?? 0, 0) }}</h3>
                            <p class="stat-label">Total Points</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="stat-card">
                            <h3 class="stat-value">{{ $total_transactions ?? 0}}</h3>
                            <p class="stat-label">Transactions</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="menu-section">
                <button type="button" class="menu-item menu-button" onclick="openAccountModal()">
                    <div class="menu-icon">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Account Settings</h4>
                        <p class="menu-subtitle">View and edit your profile</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </button>
                <button type="button" class="menu-item menu-button" onclick="openBusinessModal()">
                    <div class="menu-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Business information</h4>
                        <p class="menu-subtitle">View your registered account details</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </button>

                <a href="{{ url('points-history') }}" class="menu-item">
                    <div class="menu-icon">
                        <i class="bi bi-gift"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Points History</h4>
                        <p class="menu-subtitle">Review earned and redeemed points</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>

                <a href="{{url('history')}}" class="menu-item">
                    <div class="menu-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Transaction History</h4>
                        <p class="menu-subtitle">View your recent orders and sales</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                
                <a href="{{ route('dealer.stock.inventory') }}" class="menu-item">
                    <div class="menu-icon">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Stock Inventory</h4>
                        <p class="menu-subtitle">Track dealer stock, value, and low items</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                {{-- <a href="{{ route('dealer.customers') }}" class="menu-item">
                    <div class="menu-icon">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">My Customers</h4>
                        <p class="menu-subtitle">Manage your customer relationships</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a> --}}

                <a href="mailto:support@pascalresources.com.ph" class="menu-item">
                    <div class="menu-icon">
                        <i class="bi bi-info-circle"></i>
                    </div>
                    <div class="menu-content">
                        <h4 class="menu-title">Help & Support</h4>
                        <p class="menu-subtitle">Get help and contact support</p>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
            </div>
        </div>

        <div class="logout-section">
            <button class="logout-button">
                <i class="bi bi-box-arrow-right"></i>
                Log Out
            </button>
        </div>
    </div>
</div>

<div class="account-modal-overlay" id="accountModal">
    <div class="account-modal">
        <div class="modal-header-row">
            <h3>Account Settings</h3>
            <button type="button" class="modal-close" onclick="closeAccountModal()">
                <i class="bi bi-x"></i>
            </button>
        </div>

        <form action="{{ route('account.update') }}" method="POST">
            @csrf
            @method('PATCH')

            <div class="form-grid">
                <label>
                    <span>Full Name</span>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                </label>

                <label>
                    <span>Email Address</span>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
                </label>

                <label>
                    <span>Contact Number</span>
                    <input type="text" name="number" value="{{ old('number', $profile->number ?? '') }}">
                </label>

                <label>
                    <span>Facebook</span>
                    <input type="text" name="facebook" value="{{ old('facebook', $profile->facebook ?? '') }}">
                </label>

                @if(auth()->user()->role == 'Dealer')
                    <label>
                        <span>Store Name</span>
                        <input type="text" name="store_name" value="{{ old('store_name', $profile->store_name ?? '') }}">
                    </label>

                    <label>
                        <span>Store Type</span>
                        <input type="text" name="store_type" value="{{ old('store_type', $profile->store_type ?? '') }}">
                    </label>
                @endif

                <label class="full-row">
                    <span>Address</span>
                    <textarea name="address" rows="3" readonly>{{ old('address', $profile->address ?? '') }}</textarea>
                </label>

                <label>
                    <span>Current Password</span>
                    <input type="password" name="current_password" autocomplete="current-password">
                </label>

                <label>
                    <span>New Password</span>
                    <input type="password" name="password" autocomplete="new-password">
                </label>

                <label>
                    <span>Confirm Password</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password">
                </label>
            </div>

            <div class="modal-actions">
                <button type="button" class="secondary-btn" onclick="closeAccountModal()">Cancel</button>
                <button type="submit" class="primary-btn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="account-modal-overlay" id="businessModal">
    <div class="account-modal">
        <div class="modal-header-row">
            <h3>Business Information</h3>
            <button type="button" class="modal-close" onclick="closeBusinessModal()">
                <i class="bi bi-x"></i>
            </button>
        </div>

        <div class="detail-list">
            <div><span>Role</span><strong>{{ auth()->user()->role }}</strong></div>
            <div><span>Name</span><strong>{{ $profile->name ?? auth()->user()->name }}</strong></div>
            <div><span>Contact</span><strong>{{ $profile->number ?? 'Not set' }}</strong></div>
            <div><span>Email</span><strong>{{ $profile->email_address ?? auth()->user()->email }}</strong></div>
            @if(auth()->user()->role == 'Dealer')
                <div><span>Store</span><strong>{{ $profile->store_name ?? 'Not set' }}</strong></div>
                <div><span>Area</span><strong>{{ $profile->area ?? 'Not set' }}</strong></div>
            @endif
            <div><span>Address</span><strong>{{ $profile->address ?? 'Not set' }}</strong></div>
        </div>
    </div>
</div>

<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
    @csrf
</form>
@endsection

@section('css')
<style>
    body {
        padding: 0 !important;
        margin: 0 !important;
    }

    .account-wrapper {
        min-height: 100vh;
        background: #f8f9fa;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
    }

    .account-header {
        background: linear-gradient(135deg, #5BC2E7 0%, #4facfe 100%);
        padding: 60px 20px 40px;
        color: white;
        position: relative;
    }

    .back-button {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }

    .back-button:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    .back-button i {
        color: white;
        font-size: 20px;
    }

    .profile-section {
        text-align: center;
        padding: 20px 0;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid white;
        margin: 0 auto 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        object-fit: cover;
    }

    .profile-name {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 5px 0;
    }

    .profile-email {
        font-size: 14px;
        opacity: 0.9;
        margin: 0;
    }

    .menu-section {
        background: white;
        margin: -20px 20px 20px;
        border-radius: 20px;
        padding: 20px 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        text-decoration: none;
        color: #333;
        transition: all 0.2s ease;
        border-bottom: 1px solid #f0f0f0;
    }

    .menu-button {
        width: 100%;
        border: 0;
        background: transparent;
        text-align: left;
        font-family: inherit;
    }

    .menu-item:last-child {
        border-bottom: none;
    }

    .menu-item:hover {
        background: #f8f9fa;
    }

    .menu-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: #f0f7ff;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .menu-icon i {
        font-size: 20px;
        color: #5BC2E7;
    }

    .menu-content {
        flex: 1;
    }

    .menu-title {
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 2px 0;
        color: #333;
    }

    .menu-subtitle {
        font-size: 12px;
        color: #999;
        margin: 0;
    }

    .menu-arrow {
        color: #ccc;
        font-size: 18px;
    }

    .stats-section {
        padding: 20px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #5BC2E7;
        margin: 0 0 5px 0;
    }

    .stat-label {
        font-size: 13px;
        color: #999;
        margin: 0;
    }

    .content-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .main-content {
        flex: 1;
    }

    .logout-section {
        padding: 20px;
        margin-top: auto;
        background: #f8f9fa;
    }

    .logout-button {
        width: 100%;
        padding: 16px;
        background: white;
        border: 2px solid #fee2e2;
        border-radius: 12px;
        color: #dc2626;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .logout-button:hover {
        background: #fee2e2;
    }

    .logout-button i {
        font-size: 20px;
    }

    .account-alert {
        margin: 16px 20px 0;
        padding: 12px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
    }

    .account-alert.success {
        background: #e8f6ee;
        color: #207245;
        border: 1px solid #b9e2c8;
    }

    .account-alert.error {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .account-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 2200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15, 23, 42, 0.45);
    }

    .account-modal-overlay.active {
        display: flex;
    }

    .account-modal {
        width: 100%;
        max-width: 560px;
        max-height: 88vh;
        overflow-y: auto;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, 0.24);
        padding: 20px;
    }

    .modal-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .modal-header-row h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 50%;
        background: #f3f4f6;
        color: #4b5563;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .form-grid label {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0;
    }

    .form-grid label span {
        font-size: 12px;
        font-weight: 700;
        color: #4b5563;
    }

    .form-grid input,
    .form-grid textarea {
        width: 100%;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 10px 12px;
        color: #111827;
        outline: none;
        font-size: 14px;
    }

    .form-grid input:focus,
    .form-grid textarea:focus {
        border-color: #5BC2E7;
        box-shadow: 0 0 0 3px rgba(91, 194, 231, 0.18);
    }

    .full-row {
        grid-column: 1 / -1;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 18px;
        padding-top: 16px;
        border-top: 1px solid #f0f0f0;
    }

    .primary-btn,
    .secondary-btn {
        border: 0;
        border-radius: 10px;
        padding: 11px 16px;
        font-weight: 700;
        cursor: pointer;
    }

    .primary-btn {
        background: #4facfe;
        color: #fff;
    }

    .secondary-btn {
        background: #f3f4f6;
        color: #4b5563;
    }

    .detail-list {
        display: grid;
        gap: 10px;
    }

    .detail-list div {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .detail-list div:last-child {
        border-bottom: 0;
    }

    .detail-list span {
        color: #6b7280;
        font-size: 13px;
    }

    .detail-list strong {
        color: #111827;
        font-size: 13px;
        text-align: right;
    }

    @media (max-width: 768px) {
        .account-header {
            padding: 50px 15px 30px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
        }

        .profile-name {
            font-size: 20px;
        }

        .menu-section {
            margin: -15px 15px 15px;
        }

        .logout-section {
            padding: 20px 15px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .modal-actions {
            flex-direction: column-reverse;
        }

        .primary-btn,
        .secondary-btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('js')
<script>
function openAccountModal() {
    document.getElementById('accountModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAccountModal() {
    document.getElementById('accountModal').classList.remove('active');
    document.body.style.overflow = '';
}

function openBusinessModal() {
    document.getElementById('businessModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBusinessModal() {
    document.getElementById('businessModal').classList.remove('active');
    document.body.style.overflow = '';
}

function logout(event) {
    if (event) event.preventDefault();
    document.getElementById('logout-form').submit();
}

document.querySelector('.logout-button')?.addEventListener('click', logout);

document.querySelectorAll('.account-modal-overlay').forEach((overlay) => {
    overlay.addEventListener('click', (event) => {
        if (event.target !== overlay) return;
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    closeAccountModal();
    closeBusinessModal();
});

@if($errors->any())
    openAccountModal();
@endif
</script>
@endsection
