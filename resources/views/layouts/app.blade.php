{{-- app blade --}}
<!DOCTYPE html>
<html lang="en" data-layout="vertical" data-topbar="light" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable" data-theme="default" data-theme-colors="default">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>
    
    <!-- Favicons -->
    <link rel="shortcut icon" href="{{url('images/aaa.png')}}">
    <link rel="icon" href="{{url('images/aaa.png')}}">
    
    <!-- PWA Manifest - CRITICAL: Must be present -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <script>window.OfflineDataConfig = window.OfflineDataConfig || {}; window.OfflineDataConfig.headless = true;</script>
    <script defer src="{{ asset('offline/auth.js') }}"></script>
    <script defer src="{{ asset('offline/data.js') }}"></script>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#5DADE2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GazLite">
    <link rel="apple-touch-icon" href="{{url('images/icons/icon-192x192.png')}}">
    <link rel="apple-touch-icon" sizes="152x152" href="{{url('images/icons/icon-152x152.png')}}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{url('images/icons/icon-192x192.png')}}">
    <link rel="apple-touch-icon" sizes="167x167" href="{{url('images/icons/icon-192x192.png')}}">

    <!-- Layout config Js -->
    <script src="{{asset('inside_css/assets/js/layout.js')}}"></script>
    <!-- Bootstrap Css -->
    <link href="{{asset('inside_css/assets/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- Icons Css -->
    <link href="{{asset('inside_css/assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- App Css-->
    <link href="{{asset('inside_css/assets/css/app.min.css')}}" rel="stylesheet" type="text/css" />
    <!-- custom Css-->
    <script src="{{asset('inside_css/assets/libs/sweetalert2/sweetalert2.all.min.js')}}"></script>
    
    <link href="{{asset('inside_css/assets/css/custom.min.css')}}" rel="stylesheet" type="text/css" />
 
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css">
    
    <style>
        .offline-setup-status{display:flex;flex-wrap:wrap;gap:10px;align-items:center;max-width:760px;margin:16px auto;padding:12px 16px;border:1px solid #cde4ef;border-radius:12px;background:#eff9fc;color:#21445b;font:13px/1.5 Arial,sans-serif;overflow-wrap:anywhere}
        .offline-setup-status span{flex:1 1 240px;min-width:0}.offline-setup-status button{padding:9px 13px;border:1px solid #1682aa;border-radius:8px;background:#fff;color:#096483;font:inherit;min-height:40px;cursor:pointer}.offline-setup-status [hidden]{display:none}.offline-setup-status button:focus-visible{outline:3px solid #ffb64d;outline-offset:2px}@media(max-width:800px){.offline-setup-status{margin:12px}}
        .loader {
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: url("{{ asset('login_css/images/loader.gif')}}") 50% 50% no-repeat white;
            opacity: .8;
            background-size:120px 120px;
        }
        .bg-overlay {
            background: linear-gradient(to right, #c3c3c3, #c3c3c3) !important;
        }
        
        /* PWA Install Button Styles */
        .pwa-install-container {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9998;
            display: none;
        }
        
        .pwa-install-prompt {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 90vw;
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .pwa-install-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #5DADE2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .pwa-install-text {
            flex: 1;
        }
        
        .pwa-install-title {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            margin: 0 0 4px 0;
        }
        
        .pwa-install-subtitle {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        
        .pwa-install-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .pwa-install-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .pwa-install-btn.primary {
            background: #5DADE2;
            color: white;
        }
        
        .pwa-install-btn.primary:hover {
            background: #3498DB;
        }
        
        .pwa-install-btn.secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .pwa-install-btn.secondary:hover {
            background: #e0e0e0;
        }
        
        /* Notification for already installed */
        .pwa-installed-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 9999;
            animation: slideInRight 0.3s ease-out;
        }

        .pwa-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            transition: opacity 0.5s ease-out;
        }

        .pwa-loading-overlay.fade-out {
            opacity: 0;
            pointer-events: none;
        }

        .pwa-loading-overlay.hidden {
            display: none;
        }

        .pwa-loading-content {
            text-align: center;
            color: #333;
            max-width: 90%;
        }

        .pwa-loading-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }

        .pwa-loading-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            letter-spacing: 1px;
            color: #5DADE2;
        }

        .pwa-loading-subtitle {
            font-size: 16px;
            opacity: 0.7;
            margin-bottom: 40px;
            color: #666;
        }

        .pwa-progress-container {
            width: 300px;
            max-width: 90%;
            margin: 0 auto;
        }

        .pwa-progress-bar-bg {
            width: 100%;
            height: 6px;
            background: #E0E0E0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .pwa-progress-bar {
            height: 100%;
            background: #5DADE2;
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s ease;
        }

        .pwa-loading-status {
            font-size: 14px;
            opacity: 0.7;
            min-height: 20px;
            margin-bottom: 10px;
            color: #666;
        }

        .pwa-loading-percentage {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #5DADE2;
        }

        .pwa-loading-tasks {
            margin-top: 30px;
            text-align: left;
            max-width: 300px;
        }

        .pwa-task-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            font-size: 13px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
            color: #666;
        }

        .pwa-task-item.active {
            opacity: 1;
            font-weight: 600;
            color: #5DADE2;
        }

        .pwa-task-item.completed {
            opacity: 0.4;
            color: #999;
        }

        .pwa-task-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pwa-spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(93, 173, 226, 0.3);
            border-top-color: #5DADE2;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .pwa-checkmark {
            color: #4ade80;
            font-size: 16px;
        }

        .pwa-error-icon {
            color: #f87171;
            font-size: 16px;
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
            .pwa-loading-logo {
                width: 100px;
                height: 100px;
            }
            
            .pwa-loading-title {
                font-size: 24px;
            }
            
            .pwa-loading-subtitle {
                font-size: 14px;
            }
            
            .pwa-progress-container {
                width: 280px;
            }
        }
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div id="loader" style="display:none;" class="loader"></div>
    
    <div class="pwa-install-container" id="pwaInstallPrompt">
        <div class="pwa-install-prompt">
            <div class="pwa-install-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </div>
            <div class="pwa-install-text">
                <p class="pwa-install-title">Install GazLite App</p>
                <p class="pwa-install-subtitle">Access faster, work offline</p>
            </div>
            <div class="pwa-install-actions">
                <button class="pwa-install-btn secondary" onclick="dismissInstallPrompt()">Later</button>
                <button class="pwa-install-btn primary" onclick="installPWA()">Install</button>
            </div>
        </div>
    </div>
    
    <div class="pwa-installed-badge" id="pwaInstalledBadge">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        App installed successfully!
    </div>
  
    @yield('content')
    <aside class="offline-setup-status" aria-label="Offline availability">
        <span id="offlineSetupStatus" role="status">Checking offline availability on this device…</span>
        <button type="button" id="offlineSetupRetry" hidden>Retry offline setup</button>
    </aside>

    <script src="{{asset('inside_css/assets/libs/bootstrap/js/bootstrap.bundle.min.js')}}"></script>
    <script src="{{asset('inside_css/assets/libs/simplebar/simplebar.min.js')}}"></script>
    <script src="{{asset('inside_css/assets/libs/node-waves/waves.min.js')}}"></script>
    <script src="{{asset('inside_css/assets/libs/feather-icons/feather.min.js')}}"></script>
    <script src="{{asset('inside_css/assets/js/pages/plugins/lord-icon-2.1.0.js')}}"></script>
    <script src="{{asset('inside_css/assets/js/plugins.js')}}"></script>
    <script src="{{asset('inside_css/assets/libs/particles.js/particles.js')}}"></script>
    <script src="{{asset('inside_css/assets/js/pages/particles.app.js')}}"></script>
    <script src="{{asset('inside_css/assets/js/pages/password-addon.init.js')}}"></script>

    <script>
    // Offline startup verifies a complete bundle, independent of sign-in and optional downloads.
    (function () {
        'use strict';
        const workerUrl = new URL(@json(asset('sw.js')), window.location.href);
        const scope = new URL('./', workerUrl).href;
        let task;
        function show(message, retry) {
            const node = document.getElementById('offlineSetupStatus'), button = document.getElementById('offlineSetupRetry');
            if (node) node.textContent = message;
            if (button) { button.hidden = !retry; button.disabled = false; }
        }
        function activeWorker(registration) {
            const worker = registration.installing || registration.waiting || registration.active;
            if (!worker) return Promise.reject(new Error('Offline installation has not started. Connect and retry.'));
            if (worker.state === 'activated') return Promise.resolve(worker);
            return new Promise((resolve, reject) => {
                const done = error => {
                    clearTimeout(timer); worker.removeEventListener('statechange', changed);
                    if (error) reject(error); else resolve(worker);
                };
                const changed = () => {
                    if (worker.state === 'activated') done();
                    else if (worker.state === 'redundant') done(new Error('Offline installation did not finish. Keep your connection and retry.'));
                };
                const timer = setTimeout(() => done(new Error('Offline setup is still pending. Keep your connection and retry.')), 20000);
                worker.addEventListener('statechange', changed); changed();
            });
        }
        function ask(worker, type) {
            return new Promise((resolve, reject) => {
                const channel = new MessageChannel();
                const timer = setTimeout(() => { channel.port1.close(); reject(new Error('Offline readiness could not be confirmed. Keep your connection and retry.')); }, type === 'REFRESH_OFFLINE_ASSETS' ? 25000 : 10000);
                channel.port1.onmessage = event => { clearTimeout(timer); channel.port1.close(); resolve(event.data || {}); };
                try { worker.postMessage({ type }, [channel.port2]); }
                catch (error) { clearTimeout(timer); channel.port1.close(); reject(error); }
            });
        }
        function prepare() {
            if (task) return task;
            task = (async () => {
                if (!('serviceWorker' in navigator)) {
                    show('Offline access needs a supported browser and a secure connection. You can still sign in online.', false); return;
                }
                show('Preparing offline access on this device. Keep this page open while connected…', false);
                try {
                    const registration = navigator.onLine === false
                        ? await navigator.serviceWorker.getRegistration(scope)
                        : await navigator.serviceWorker.register(workerUrl.href, { scope, updateViaCache: 'none' });
                    if (!registration) throw new Error('Offline setup is not ready for this address. Connect and retry.');
                    if (navigator.onLine !== false) { try { await registration.update(); } catch (_) {} }
                    const worker = await activeWorker(registration);
                    let state = await ask(worker, 'GET_OFFLINE_STATUS');
                    if (!state.ready && navigator.onLine !== false) {
                        const result = await ask(worker, 'REFRESH_OFFLINE_ASSETS');
                        if (!result.success) throw new Error(result.message || 'Offline files could not be downloaded.');
                        state = await ask(worker, 'GET_OFFLINE_STATUS');
                    }
                    if (!state.ready) throw new Error(state.message || 'Offline setup is incomplete. Connect and retry.');
                    show('Offline pages are ready on this device. Sign in online to save your account data before disconnecting.', false);
                } catch (error) { show(error.message || 'Offline setup is not ready. Stay connected and retry.', true); }
            })().finally(() => { task = null; });
            return task;
        }
        function start() {
            const button = document.getElementById('offlineSetupRetry');
            if (button) button.onclick = () => { button.disabled = true; prepare(); };
            prepare();
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true }); else start();
        window.addEventListener('online', prepare);
    })();
    </script>

    <script>
let deferredPrompt;
let installAttempted = false;

function isAppInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches || 
           window.navigator.standalone === true ||
           localStorage.getItem('pwa-installed') === 'true';
}

if (isAppInstalled()) {
    console.log('✅ App is running as installed PWA');
} else {
    console.log('📱 App is running in browser - install prompt available');
}

window.addEventListener('beforeinstallprompt', (e) => {
    console.log('🎯 beforeinstallprompt event fired');
    
    e.preventDefault();
    
    deferredPrompt = e;
    
    const dismissed = localStorage.getItem('pwa-prompt-dismissed');
    const dismissedTime = localStorage.getItem('pwa-prompt-dismissed-time');
    
    const shouldShowAgain = !dismissedTime || 
        (Date.now() - parseInt(dismissedTime)) > 7 * 24 * 60 * 60 * 1000;
    
    if (!dismissed || shouldShowAgain) {
        setTimeout(() => {
            showInstallPrompt();
        }, 2000);
    }
});

function showInstallPrompt() {
    const prompt = document.getElementById('pwaInstallPrompt');
    if (prompt && !isAppInstalled()) {
        prompt.style.display = 'block';
        console.log('📲 Install prompt displayed');
    }
}

async function installPWA() {
    if (!deferredPrompt) {
        console.log('❌ No deferred prompt available');
        Swal.fire({
            icon: 'info',
            title: 'Installation Not Available',
            text: 'The app is either already installed or your browser doesn\'t support installation.',
            confirmButtonColor: '#5DADE2'
        });
        return;
    }

    installAttempted = true;
    
    document.getElementById('pwaInstallPrompt').style.display = 'none';

    deferredPrompt.prompt();

    const { outcome } = await deferredPrompt.userChoice;
    
    console.log(`👤 User response: ${outcome}`);

    if (outcome === 'accepted') {
        console.log('✅ User accepted the install prompt');
        localStorage.setItem('pwa-installed', 'true');
        
        Swal.fire({
            icon: 'success',
            title: 'App Installed!',
            text: 'GazLite has been added to your home screen.',
            timer: 3000,
            showConfirmButton: false
        });
        
        const badge = document.getElementById('pwaInstalledBadge');
        badge.style.display = 'flex';
        setTimeout(() => {
            badge.style.display = 'none';
        }, 5000);
    } else {
        console.log('❌ User dismissed the install prompt');
    }

    deferredPrompt = null;
}

function dismissInstallPrompt() {
    document.getElementById('pwaInstallPrompt').style.display = 'none';
    localStorage.setItem('pwa-prompt-dismissed', 'true');
    localStorage.setItem('pwa-prompt-dismissed-time', Date.now().toString());
    console.log('⏭️ Install prompt dismissed by user');
}

window.addEventListener('appinstalled', (e) => {
    console.log('✅ PWA was successfully installed');
    localStorage.setItem('pwa-installed', 'true');
    
    document.getElementById('pwaInstallPrompt').style.display = 'none';
    
    const badge = document.getElementById('pwaInstalledBadge');
    badge.style.display = 'flex';
    setTimeout(() => {
        badge.style.display = 'none';
    }, 5000);
    
    deferredPrompt = null;
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', event => {
        const { type } = event.data;
        
        switch(type) {
            case 'OFFLINE_STATUS':
                handleOfflineStatus();
                break;
                
            case 'CHECK_AUTH':
                const offlineUser = localStorage.getItem('offlineUser');
                const currentUserId = localStorage.getItem('current_user_id');
                const authenticated = !!(offlineUser || currentUserId);
                
                event.ports[0].postMessage({ 
                    authenticated: authenticated,
                    userId: currentUserId,
                    role: localStorage.getItem('current_user_role')
                });
                break;
        }
    });


}

window.addEventListener('online', () => {
    console.log('🌐 You are online');
    document.body.classList.remove('offline-mode');
    
    if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ 
            type: 'RESET_OFFLINE_STATE' 
        });
    }
    
    showOnlineNotification();
});

window.addEventListener('offline', () => {
    console.log('📵 You are offline');
    document.body.classList.add('offline-mode');
    handleOfflineStatus();
});

function handleOfflineStatus() {
    if (window.location.pathname.includes('/offline/')) {
        console.log('Already on offline page');
        return;
    }
    
    showOfflineChoiceDialog();
}

function showOfflineChoiceDialog() {
    Swal.fire({
        icon: 'warning',
        title: 'Connection Lost',
        html: `
            <p style="font-size: 16px; margin-bottom: 20px;">
                You have lost your internet connection.
            </p>
            <p style="font-size: 14px; color: #666;">
                Choose an option to continue:
            </p>
        `,
        showDenyButton: true,
        showCancelButton: false,
        confirmButtonText: '<i class="fas fa-sync-alt"></i> Retry Connection',
        denyButtonText: '<i class="fas fa-wifi-slash"></i> Go Offline',
        confirmButtonColor: '#5DADE2',
        denyButtonColor: '#95a5a6',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            retryConnection();
        } else if (result.isDenied) {
            redirectToOfflineMode();
        }
    });
}

function retryConnection() {
    Swal.fire({
        title: 'Checking Connection...',
        html: 'Please wait while we verify your internet connection.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    Promise.all([fetch(@json(asset('manifest.json')), { method: 'HEAD', cache: 'no-store', signal: controller.signal })])
        .then(responses => {
            clearTimeout(timeoutId);
            
            const currentTime = new Date().getTime();
            const connectionValid = responses.some(response => {
                return response && response.ok;
            });

            if (connectionValid && navigator.onLine) {
                Swal.fire({
                    icon: 'success',
                    title: 'Connected!',
                    text: 'Your internet connection has been restored.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                throw new Error('No valid connection');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.log('Connection check failed:', error.message);
            
            Swal.fire({
                icon: 'error',
                title: 'Connection Failed',
                html: `
                    <p style="font-size: 16px; margin-bottom: 15px;">
                        Unable to establish a connection.
                    </p>
                    <p style="font-size: 14px; color: #666;">
                        ${error.name === 'AbortError' ? 'Connection timeout.' : 'No internet access detected.'}<br>
                        Redirecting to offline mode...
                    </p>
                `,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                redirectToOfflineMode();
            });
        });
    }

function redirectToOfflineMode() {
    let authenticated = false;
    try { authenticated = !!(localStorage.getItem('current_user_id') || localStorage.getItem('offlineUser')); } catch (_) {}
    const offlinePath = authenticated ? @json(asset('offline/home.html')) : @json(asset('offline/login.html'));

    Swal.fire({
        icon: 'info',
        title: 'Switching to Offline Mode',
        html: `Redirecting to offline mode...<br><small>Limited features available</small>`,
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    }).then(() => {
        window.location.href = offlinePath;
    });
}

function showOnlineNotification() {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    Toast.fire({
        icon: 'success',
        title: 'You are back online!'
    });
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', event => {
        const { type } = event.data;
        
        switch(type) {
            case 'OFFLINE_CHOICE_DIALOG':
                showOfflineChoiceDialog();
                break;
                
            case 'ONLINE_STATUS':
                if (event.data.status === 'online') {
                    showOnlineNotification();
                }
                break;
        }
    });
}

if (!navigator.onLine) {
    console.log('📵 Starting offline');
    document.body.classList.add('offline-mode');
    
    setTimeout(() => {
        handleOfflineStatus();
    }, 500);
}

const offlineStyles = document.createElement('style');
offlineStyles.textContent = `
    .offline-mode {
        filter: grayscale(20%);
    }
    
    .offline-mode::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #FF9800, #FF5722);
        z-index: 10000;
        animation: pulse 2s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
`;
document.head.appendChild(offlineStyles);
</script>
</body>
</html>
