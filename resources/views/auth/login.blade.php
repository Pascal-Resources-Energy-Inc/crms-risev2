@extends('layouts.app')
@section('content')
<?php
$hasErrors = $errors->any();
$hasSelectedRole = old('selected_role');
$showLoginDirectly = $hasErrors && $hasSelectedRole;

$isDirect = request('direct') === 'true';
$showRoleSelection = $isDirect && !$showLoginDirectly;
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="landing-page" id="landingPage" style="display: {{ $showLoginDirectly || $showRoleSelection ? 'none' : 'flex' }};">
    <div class="landing-content">
        <div class="landing-wrapper">
            <div class="logo-section">
                <img src="{{asset('images/logo_sa_labas.png')}}" alt="GazLite Logo" class="main-logo-img">
            </div>

            <div class="illustration-section">
                <img src="{{asset('images/human.png')}}" alt="Easy Management Illustration" class="human-illustration">
            </div>

            <div class="tagline">
                <p>Easy Management for your Store.</p>
            </div>

            <div class="progress-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>

            <button class="landing-signin-button" onclick="showRoleSelection()">
                Sign in
            </button>
        </div>
    </div>
</div>

<div class="role-selection-page" id="roleSelectionPage" style="display: {{ $showRoleSelection ? 'flex' : 'none' }};">
    <div class="role-header">
        <div class="header-left">
        </div>
    </div>

    <div class="role-content">
        <div class="role-wrapper">
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome to Gaz Lite !</h1>
                <p class="welcome-subtitle">Select your account type to get started.</p>
            </div>

            <div class="context-image-section">
                <img src="{{asset('images/context.png')}}" alt="Role Selection Context" class="context-image">
            </div>

            <div class="progress-dots">
                <span class="dot"></span>
                <span class="dot active"></span>
                <span class="dot"></span>
            </div>

            <div class="role-buttons">
                <button type="button" class="role-button dealer-btn" data-role="dealer" onclick="selectRole('dealer')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="role-icon">
                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Mega Dealer / Dealer
                    <span class="selected-indicator" style="display: none;">✓</span>
                </button>
                <button type="button" class="role-button center-chief-btn" data-role="center chief" onclick="selectRole('center chief')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="role-icon">
                        <path d="M12 3L19 6V11C19 15.5 16.1 19.6 12 21C7.9 19.6 5 15.5 5 11V6L12 3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Center Chief
                    <span class="selected-indicator" style="display: none;">✓</span>
                </button>
                
                <button type="button" class="role-button client-btn" data-role="client" onclick="selectRole('client')">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" class="role-icon">
                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Client
                    <span class="selected-indicator" style="display: none;">✓</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="login-page" id="loginFormPage" style="display: {{ $showLoginDirectly ? 'flex' : 'none' }};">
    <div class="login-header">
        <div class="header-left">
            <button type="button" class="back-btn" onclick="showRoleSelection()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <h1 class="page-title">Sign in</h1>
        </div>
        <div class="logo-container">
            <img src="{{asset('images/logo_nya.png')}}" alt="Logo" class="header-logo">
        </div>
    </div>

    <div class="login-content">
        <div class="form-container">
            <div class="role-indicator" id="roleIndicator" style="display: {{ $showLoginDirectly ? 'block' : 'none' }};">
                <div class="role-info">
                    <span class="role-label">Signing in as:</span>
                    <span class="role-name" id="selectedRoleName">{{ $hasSelectedRole ? ucwords(old('selected_role')) : 'User' }}</span>
                </div>
            </div>

            <form id="loginForm" method="POST" action="{{ route('login') }}" aria-label="{{ __('Login') }}">
                @csrf
                
                <input type="hidden" name="selected_role" id="selectedRoleInput" value="{{ old('selected_role') }}">

                <div class="input-group">
                    <label for="email" class="input-label">Email or Phone Number</label>
                    <input 
                        id="email" 
                        type="text" 
                        class="form-input" 
                        name="email" 
                        value="{{ old('email') }}" 
                        placeholder="Email or Phone Number"
                        required 
                        autofocus
                    >
                </div>

                <div class="input-group">
                    <label class="input-label" for="password">Password</label>
                    <input 
                        id="password" 
                        type="password" 
                        class="form-input" 
                        placeholder="At least 8 characters" 
                        name="password" 
                        required
                    >
                </div>

                <button class="signin-button" type="submit" id="signinButton">
                    Sign in
                </button>

                <div class="forgot-section">
                    <a href="{{ route('password.request') }}" class="forgot-link">
                        Forgot password?
                    </a>
                </div>

                <div class="progress-dots">
                    <span class="dot"></span>
                    <span class="dot"></span>
                    <span class="dot active"></span>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: #ffffff;
}

.landing-page {
    min-height: 100vh;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
}

.landing-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
}

.landing-wrapper {
    width: 100%;
    max-width: 400px;
    text-align: center;
}

.logo-section {
    margin-bottom: 40px;
}

.main-logo-img {
    max-width: 549px;
    width: 100%;
    max-height: 186px;
    height: 100%;
}

.illustration-section {
    margin-bottom: 30px;
}

.human-illustration {
    max-width: 256.42px;
    width: 100%;
    max-height: 284px;
    height: auto;
}

.tagline {
    margin-bottom: 30px;
}

.tagline p {
    font-size: 18px;
    color: #666666;
    font-weight: 400;
}

.progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 40px;
}

.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #E0E0E0;
    transition: background-color 0.3s ease;
}

.dot.active {
    background-color: #5DADE2;
}

.landing-signin-button {
    max-width: 327px;
    width: 100%;
    height: 57px;
    border: none;
    border-radius: 28px;
    background-color: #5DADE2;
    color: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 24px;
    transition: all 0.3s ease;
}

.landing-signin-button:hover {
    background-color: #3498DB;
    transform: translateY(-2px);
}

.role-selection-page {
    min-height: 100vh;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
}

.role-header {
    display: flex;
    align-items: center;
    padding: 20px 24px;
    background-color: #ffffff;
}

.role-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
    margin-top: -80px;
}

.role-wrapper {
    width: 100%;
    max-width: 350px;
    text-align: center;
}

.welcome-section {
    margin-bottom: 20px;
}

.welcome-title {
    font-size: 24px;
    color: #4A5568;
    font-weight: 600;
    margin-bottom: 8px;
    line-height: 1.2;
}

.welcome-subtitle {
    font-size: 14px;
    color: #666666;
    font-weight: 400;
}

.context-image-section {
    margin-bottom: 25px;
}

.context-image {
    max-width: 200px;
    width: 100%;
    height: auto;
}

.role-selection-page .progress-dots {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 25px;
}

.role-buttons {
    margin-bottom: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.role-button {
    width: 100%;
    height: 48px;
    border: none;
    border-radius: 24px;
    background-color: #5DADE2;
    color: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s ease;
    position: relative;
}

.role-button:hover {
    background-color: #3498DB;
    transform: translateY(-2px);
}

.role-button.selected {
    opacity: 0.8;
    box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #5DADE2;
}

.selected-indicator {
    position: absolute;
    right: 15px;
    font-size: 0;
    font-weight: bold;
}

.selected-indicator::before {
    content: "\2713";
    font-size: 16px;
}

.role-icon {
    width: 20px;
    height: 20px;
    stroke: white;
}

.continue-section {
    margin-bottom: 20px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.continue-section.show {
    opacity: 1;
}

.continue-button {
    width: 100%;
    height: 48px;
    border: none;
    border-radius: 24px;
    background-color: #5DADE2;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.continue-button:hover {
    background-color: #3498DB;
    transform: translateY(-2px);
}

.create-account-section {
    margin-bottom: 15px;
}

.create-account-link {
    color: #5DADE2;
    text-decoration: underline;
    font-size: 14px;
    font-weight: 400;
}

.create-account-link:hover {
    color: #3498DB;
}

.login-page {
    min-height: 100vh;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
}

.login-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    background-color: #ffffff;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.back-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background-color: #5DADE2;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s ease;
    z-index: 10;
    position: relative;
}

.back-btn:hover {
    background-color: #3498DB;
}

.page-title {
    margin-top: 7px;
    margin-left: 20px;
    font-size: 27px;
    font-weight: 550;
    color: #5DADE2;
    letter-spacing: 1px;
}

.logo-container {
    width: 60px;
    height: 60px;
}

.header-logo {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.login-content {
    margin-top: -57px;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 24px;
}

.form-container {
    width: 100%;
    max-width: 400px;
}

.role-indicator {
    background-color: #F0F8FF;
    border-left: 4px solid #5DADE2;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.role-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.role-label {
    font-size: 14px;
    color: #666666;
}

.role-name {
    font-size: 16px;
    font-weight: 600;
    color: #5DADE2;
}

.input-group {
    margin-bottom: 24px;
}

.input-label {
    display: block;
    font-size: 16px;
    color: #666666;
    margin-bottom: 8px;
    font-weight: 400;
}

.form-input {
    width: 100%;
    height: 56px;
    padding: 0 20px;
    border: none;
    border-radius: 12px !important;
    background-color: #F5F5F5;
    font-size: 16px;
    color: #333333;
    outline: none;
    transition: background-color 0.2s ease;
}

.form-input::placeholder {
    color: #AAAAAA;
    font-size: 16px;
}

.form-input:focus {
    background-color: #EEEEEE;
}

.signin-button {
    width: 100%;
    height: 56px;
    border: none;
    border-radius: 12px;
    background-color: #5DADE2;
    color: white;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    margin-bottom: 24px;
    transition: background-color 0.2s ease;
}

.signin-button:hover {
    background-color: #3498DB;
}

.forgot-section {
    text-align: center;
    margin-bottom: 24px;
}

.forgot-link {
    color: #5DADE2;
    text-decoration: none;
    font-size: 16px;
    font-weight: 400;
}

.forgot-link:hover {
    text-decoration: underline;
}

@media (max-width: 480px) {
    .login-header, .role-header {
        padding: 16px 20px;
    }
    
    .landing-content, .login-content, .role-content {
        padding: 0 20px;
    }
    
    .main-logo-img {
        max-width: 200px;
    }
    
    .human-illustration {
        max-width: 160px;
    }
    
    .context-image {
        max-width: 180px;
    }
    
    .tagline p {
        font-size: 16px;
    }
    
    .welcome-title {
        font-size: 22px;
    }
    
    .welcome-subtitle {
        font-size: 13px;
    }
    
    .header-left {
        gap: 12px;
    }
    
    .page-title {
        font-size: 18px;
    }
    
    .form-input, .signin-button {
        height: 52px;
    }
    
    .role-button {
        height: 44px;
        font-size: 15px;
    }
    
    .role-icon {
        width: 18px;
        height: 18px;
    }
    
    .role-content {
        margin-top: -60px;
    }
    
    .role-wrapper {
        max-width: 320px;
    }
}
</style>

<script>
let currentRole = @json(old('selected_role', ''));

document.addEventListener('DOMContentLoaded', function() {
    const selectedRoleInput = document.getElementById('selectedRoleInput');
    
    if (selectedRoleInput && selectedRoleInput.value) {
        currentRole = selectedRoleInput.value;
        updateLoginFormRole();
    }

    @if($errors->any())
        let errorMessage = '';
        @if($errors->has('email') || $errors->has('password'))
            errorMessage = 'Invalid credentials. Please check your email/phone and password.';
        @else
            errorMessage = @json($errors->first());
        @endif
        
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: errorMessage,
            confirmButtonColor: '#5DADE2'
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Login Failed',
            text: @json(session('error')),
            confirmButtonColor: '#5DADE2'
        });
    @endif

    // Online sign-in prepares only the successfully authenticated account.
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitButton = document.getElementById('signinButton');
            if (submitButton.disabled) return;
            const originalText = submitButton.innerHTML;
            const formData = new FormData(this);
            const identifier = String(formData.get('email') || '').trim();
            const password = String(formData.get('password') || '');
            const selectedRole = String(formData.get('selected_role') || '');
            const offlineUrl = @json(asset('offline/home.html'));
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';

            async function useSavedLogin() {
                if (!window.OfflineAuth) throw new Error('Offline sign-in files are not ready. Connect and sign in once to prepare this browser.');
                const user = await window.OfflineAuth.authenticate(identifier, password, selectedRole);
                await window.OfflineAuth.startSession(user);
                document.getElementById('password').value = '';
                await Swal.fire({ icon: 'success', title: 'Signed in offline', text: 'Your saved account is ready.', timer: 1000, showConfirmButton: false });
                window.location.href = offlineUrl;
            }

            try {
                if (navigator.onLine === false) { await useSavedLogin(); return; }
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 10000);
                let response;
                try {
                    response = await fetch(loginForm.action, {
                        method: 'POST', body: formData, signal: controller.signal, credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                } catch (error) {
                    if (error && ['TypeError', 'AbortError'].includes(error.name)) { await useSavedLogin(); return; }
                    throw error;
                } finally { clearTimeout(timer); }
                if (response.status === 503) { await useSavedLogin(); return; }
                const contentType = response.headers.get('content-type') || '';
                const result = contentType.includes('application/json') ? await response.json() : {};

                if (response.ok && result.success) {
                    let offlineReady = false, setupWarning = '';
                    if (result.user && result.user.id) {
                        window.OfflineDataConfig = window.OfflineDataConfig || { headless: true };
                        window.OfflineDataConfig.user = result.user;
                        try {
                            if (!window.OfflineAuth) throw new Error('Offline sign-in files have not loaded. Refresh while connected and sign in again.');
                            await window.OfflineAuth.startSession(result.user);
                            if (result.offline_login === null) {
                                await window.OfflineAuth.forget(result.user.id);
                            } else if (result.offline_login) {
                                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing offline sign-in...';
                                if (!window.OfflineData) throw new Error('Offline account files have not loaded. Refresh while connected and sign in again.');
                                const [snapshot] = await Promise.all([window.OfflineData.refresh(), window.OfflineData.refreshAssets()]);
                                const grant = snapshot && snapshot.offline_login;
                                if (!grant || grant.credential_version !== result.offline_login.credential_version || Number(grant.user.id) !== Number(result.user.id)) {
                                    throw new Error('Account credentials changed during setup. Sign in again to prepare offline access.');
                                }
                                const enrollment = await window.OfflineAuth.enroll(grant, password, identifier);
                                await window.OfflineAuth.startSession(enrollment.user);
                                offlineReady = true;
                            } else throw new Error('This server has not prepared offline sign-in yet. Refresh while connected and sign in again.');
                        } catch (error) { setupWarning = error.message || 'Offline setup did not finish. Stay connected and sign in again before using this account offline.'; }
                    }
                    document.getElementById('password').value = '';
                    const status = document.getElementById('offlineSetupStatus');
                    if (status && offlineReady) status.textContent = 'Offline sign-in and account data are ready on this device.';
                    await Swal.fire({
                        icon: setupWarning ? 'warning' : 'success', title: 'Login Successful!',
                        text: setupWarning ? 'Signed in online. ' + setupWarning : offlineReady ? 'Offline sign-in and your account data are ready on this device.' : 'Welcome back!',
                        timer: setupWarning ? undefined : 1500, showConfirmButton: !!setupWarning,
                        confirmButtonText: 'Continue online', confirmButtonColor: '#5DADE2'
                    });
                    window.location.href = result.redirect || '{{ route("home") }}';
                    return;
                }
                if (response.status === 419) {
                    await Swal.fire({ icon: 'error', title: 'Session Expired', text: 'Please refresh the page and try again.', confirmButtonColor: '#5DADE2' });
                    return;
                }
                const firstError = result.errors && Object.values(result.errors).flat()[0];
                await Swal.fire({ icon: 'error', title: 'Login Failed', text: firstError || result.message || 'Invalid credentials. Please check your email/phone and password.', confirmButtonColor: '#5DADE2' });
            } catch (error) {
                await Swal.fire({ icon: 'error', title: 'Login Failed', text: error.message || 'Unable to sign in. Connect and prepare this account for offline access.', confirmButtonColor: '#5DADE2' });
            } finally {
                formData.delete('password');
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
    }
    // End online sign-in preparation.
});

function showRoleSelection() {
    document.getElementById('landingPage').style.display = 'none';
    document.getElementById('roleSelectionPage').style.display = 'flex';
    document.getElementById('loginFormPage').style.display = 'none';
    
    currentRole = '';
    clearRoleSelection();
    clearAllErrors();
}

function selectRole(role) {
    currentRole = role;
    
    clearRoleSelection();
    clearAllErrors();
    
    const selectedButton = document.querySelector(`.role-button[data-role="${role}"]`);
    if (!selectedButton) {
        console.error('Selected login role button was not found:', role);
        return;
    }
    const checkmark = selectedButton.querySelector('.selected-indicator');
    
    selectedButton.classList.add('selected');
    checkmark.style.display = 'inline';
    
    setTimeout(() => {
        showLoginForm();
    }, 300);
}

function clearRoleSelection() {
    document.querySelectorAll('.role-button').forEach(button => {
        button.classList.remove('selected');
        const checkmark = button.querySelector('.selected-indicator');
        if (checkmark) {
            checkmark.style.display = 'none';
        }
    });
}

function clearAllErrors() {
    document.querySelectorAll('.form-input').forEach(input => {
        if (input.name !== 'selected_role') {
            input.value = '';
        }
    });
    
    const selectedRoleInput = document.getElementById('selectedRoleInput');
    if (selectedRoleInput) {
        selectedRoleInput.value = '';
    }
}

function showLoginForm() {
    document.getElementById('landingPage').style.display = 'none';
    document.getElementById('roleSelectionPage').style.display = 'none';
    document.getElementById('loginFormPage').style.display = 'flex';
    
    updateLoginFormRole();
}

function updateLoginFormRole() {
    const roleIndicator = document.getElementById('roleIndicator');
    const roleName = document.getElementById('selectedRoleName');
    const signinButton = document.getElementById('signinButton');
    const selectedRoleInput = document.getElementById('selectedRoleInput');
    
    if (currentRole) {
        roleIndicator.style.display = 'block';
        roleName.textContent = currentRole.replace(/\b\w/g, char => char.toUpperCase());
        signinButton.textContent = `Sign in`;
        
        selectedRoleInput.value = currentRole;
    }
}

function showLandingPage() {
    document.getElementById('loginFormPage').style.display = 'none';
    document.getElementById('roleSelectionPage').style.display = 'none';
    document.getElementById('landingPage').style.display = 'flex';
    
    currentRole = '';
    clearAllErrors();
}
</script>
@endsection
