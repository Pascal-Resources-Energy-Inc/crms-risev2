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
    <link rel="manifest" href="../public/manifest.json">
    
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="{{asset('inside_css/assets/css/custom.min.css')}}" rel="stylesheet" type="text/css" />
 
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet" type="text/css">
    
    <style>
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
      <div class="pwa-loading-overlay" id="pwaLoadingOverlay">
        <div class="pwa-loading-content">
            <img src="{{url('images/logo_nya.png')}}" alt="GazLite Logo" class="pwa-loading-logo">
            
            <h1 class="pwa-loading-title">GazLite</h1>
            <p class="pwa-loading-subtitle">Preparing your experience...</p>
            
            <div class="pwa-progress-container">
                <div class="pwa-progress-bar-bg">
                    <div class="pwa-progress-bar" id="pwaProgressBar"></div>
                </div>
                
                <div class="pwa-loading-percentage" id="pwaLoadingPercentage">0%</div>
                <div class="pwa-loading-status" id="pwaLoadingStatus">Initializing...</div>
                
                <div class="pwa-loading-tasks">
                    <div class="pwa-task-item" id="task-sw">
                        <div class="pwa-task-icon">
                            <div class="pwa-spinner"></div>
                        </div>
                        <span>Loading Service Worker</span>
                    </div>
                    
                    <div class="pwa-task-item" id="task-cache">
                        <div class="pwa-task-icon">
                            <div class="pwa-spinner"></div>
                        </div>
                        <span>Caching essential files</span>
                    </div>
                    
                    <div class="pwa-task-item" id="task-db">
                        <div class="pwa-task-icon">
                            <div class="pwa-spinner"></div>
                        </div>
                        <span>Syncing database</span>
                    </div>
                    
                    <div class="pwa-task-item" id="task-assets">
                        <div class="pwa-task-icon">
                            <div class="pwa-spinner"></div>
                        </div>
                        <span>Loading resources</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const hasCompletedFirstLoad = localStorage.getItem('pwa-first-load-complete');
        const overlay = document.getElementById('pwaLoadingOverlay');
        
        
        if (hasCompletedFirstLoad === 'true') {
            if (overlay) {
                overlay.classList.add('hidden');
                overlay.style.display = 'none';
            }
            document.body.style.overflow = 'auto';
        } else {
            if (overlay) {
                overlay.classList.remove('hidden');
                overlay.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
    })();
    </script>
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
window.addEventListener('offline', () => {
    if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ 
            type: 'CLEAR_ONLINE_CACHE' 
        });
    }
    
    console.log('📵 Going offline - cleared online cache');
    
    setTimeout(() => {
        const isAuth = localStorage.getItem('current_user_id') || 
                       localStorage.getItem('offlineUser');
        
        if (isAuth) {
            window.location.href = '/offline/home.html';
        } else {
            window.location.href = '/offline/login.html';
        }
    }, 1000);
});
</script>

    
    <script>
      class PWALoadingManager {
    constructor() {
        this.tasks = {
            sw: { completed: false, weight: 20 },
            cache: { completed: false, weight: 20 },
            db: { completed: false, weight: 30 },
            assets: { completed: false, weight: 10 }
        };
        
        this.progressBar = document.getElementById('pwaProgressBar');
        this.percentageText = document.getElementById('pwaLoadingPercentage');
        this.statusText = document.getElementById('pwaLoadingStatus');
        this.overlay = document.getElementById('pwaLoadingOverlay');
        
        this.shouldShowLoading = false;
        this.allTasksCompleted = false;
        this.loadingStartTime = Date.now();
        this.MINIMUM_LOADING_TIME = 20000;
    }
    
    checkIfLoadingNeeded() {
        const hasCompletedFirstLoad = localStorage.getItem('pwa-first-load-complete');
        
        if (hasCompletedFirstLoad === 'true') {
            console.log('Skipping loading - first load already completed');
            this.shouldShowLoading = false;
            this.overlay.classList.add('hidden');
            return false;
        }
        
        this.shouldShowLoading = true;
        this.overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        return true;
    }
    
    updateProgress() {
        if (!this.shouldShowLoading) return;
        
        let taskProgress = 0;
        for (const [key, task] of Object.entries(this.tasks)) {
            if (task.completed) {
                taskProgress += task.weight;
            }
        }
        
        const elapsedTime = Date.now() - this.loadingStartTime;
        let totalProgress = 0;
        
        if (taskProgress >= 80) {
            const progressFrom80 = 80 + ((elapsedTime / this.MINIMUM_LOADING_TIME) * 20);
            totalProgress = Math.min(progressFrom80, 100);
        } else {
            const timeProgress = Math.min((elapsedTime / 19000) * 20, 20);
            totalProgress = Math.min(taskProgress + timeProgress, 80);
        }
        
        this.progressBar.style.width = totalProgress + '%';
        this.percentageText.textContent = Math.round(totalProgress) + '%';
        
        const allComplete = Object.values(this.tasks).every(task => task.completed);
        
        if (allComplete && !this.allTasksCompleted) {
            this.allTasksCompleted = true;
            this.complete();
        }
    }
    
    completeTask(taskName, message = '') {
        if (this.tasks[taskName]) {
            this.tasks[taskName].completed = true;
            
            const taskElement = document.getElementById(`task-${taskName}`);
            if (taskElement) {
                taskElement.classList.add('completed');
                taskElement.classList.remove('active');
                
                const icon = taskElement.querySelector('.pwa-task-icon');
                icon.innerHTML = '<span class="pwa-checkmark">✓</span>';
            }
            
            if (message && this.shouldShowLoading) {
                this.statusText.textContent = message;
            }
            
            console.log(`Task completed: ${taskName} - ${message}`);
            this.updateProgress();
        }
    }
    
    setActiveTask(taskName, message = '') {
        if (!this.shouldShowLoading) return;
        
        const taskElement = document.getElementById(`task-${taskName}`);
        if (taskElement) {
            taskElement.classList.add('active');
        }
        
        if (message) {
            this.statusText.textContent = message;
        }
    }
    
    failTask(taskName, message = '') {
        console.error(`Task failed: ${taskName} - ${message}`);
        
        const taskElement = document.getElementById(`task-${taskName}`);
        if (taskElement) {
            taskElement.classList.add('completed');
            taskElement.classList.remove('active');
            
            const icon = taskElement.querySelector('.pwa-task-icon');
            icon.innerHTML = '<span class="pwa-error-icon">✗</span>';
        }
        
        if (message && this.shouldShowLoading) {
            this.statusText.textContent = message;
        }
        
        setTimeout(() => {
            this.completeTask(taskName, 'Completed with warnings');
        }, 2000);
    }
    
    complete() {
        const elapsedTime = Date.now() - this.loadingStartTime;
        const remainingTime = this.MINIMUM_LOADING_TIME - elapsedTime;
        
        if (remainingTime > 0) {
            console.log(`All tasks done, waiting ${Math.round(remainingTime/1000)}s more...`);
            this.statusText.textContent = 'Finalizing download...';
            
            const progressInterval = setInterval(() => {
                this.updateProgress();
            }, 100);
            
            setTimeout(() => {
                clearInterval(progressInterval);
                this.updateProgress();
                this.finishLoading();
            }, remainingTime);
        } else {
            this.updateProgress();
            this.finishLoading();
        }
    }
    
    finishLoading() {
        if (this.shouldShowLoading) {
            this.statusText.textContent = 'Ready!';
            this.progressBar.style.width = '100%';
            this.percentageText.textContent = '100%';
        }
        
        localStorage.setItem('pwa-first-load-complete', 'true');
        localStorage.setItem('pwa-last-sync', Date.now().toString());
        
        console.log('All tasks completed - App is ready!');
        
        setTimeout(() => {
            this.overlay.classList.add('fade-out');
            
            setTimeout(() => {
                this.overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }, 500);
        }, 500);
    }
}

const loadingManager = new PWALoadingManager();

(function initializePWA() {
    const needsLoading = loadingManager.checkIfLoadingNeeded();
    
    if (!needsLoading) {
        return;
    }
    
    startLoadingTasks();
    
    setInterval(() => {
        loadingManager.updateProgress();
    }, 100);
})();

function startLoadingTasks() {
    loadingManager.setActiveTask('sw', 'Registering Service Worker...');
    
    if ('serviceWorker' in navigator) {
        const getBasePath = () => {
            const path = window.location.pathname;
            if (path.includes('/crms-risev2/public')) {
                return '/crms-risev2/public';
            }
            return '';
        };
        
        const BASE_PATH = getBasePath();
        const swPath = `${BASE_PATH}/sw.js`;
        const swScope = `${BASE_PATH}/`;
        
        fetch(swPath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`sw.js not found at ${swPath}`);
                }
                return navigator.serviceWorker.register(swPath, { scope: swScope });
            })
            .then(registration => {
                console.log('Service Worker registered');
                
                if (registration.active) {
                    loadingManager.completeTask('sw', 'Service Worker active');
                    loadCacheAndAssets();
                } else {
                    const worker = registration.installing || registration.waiting;
                    if (worker) {
                        worker.addEventListener('statechange', () => {
                            if (worker.state === 'activated') {
                                loadingManager.completeTask('sw', 'Service Worker active');
                                loadCacheAndAssets();
                            }
                        });
                    }
                }
                
                setInterval(() => {
                    registration.update();
                }, 60000);
            })
            .catch(error => {
                console.error('Service Worker failed:', error);
                loadingManager.failTask('sw', 'Service Worker registration failed');
            });
    } else {
        loadingManager.completeTask('sw', 'Service Worker not supported');
        loadCacheAndAssets();
    }
    
    loadingManager.setActiveTask('db', 'Syncing database...');
    saveAllDataToIndexedDBWithProgress();
}

function loadCacheAndAssets() {
    loadingManager.setActiveTask('cache', 'Caching essential files...');
    
    const checkCacheReady = async () => {
        try {
            const cacheNames = await caches.keys();
            if (cacheNames.length > 0) {
                const cache = await caches.open(cacheNames[0]);
                const keys = await cache.keys();
                
                if (keys.length > 5) {
                    console.log('Cache populated with', keys.length, 'files');
                    loadingManager.completeTask('cache', 'Cache ready');
                    loadAssets();
                    return true;
                }
            }
            return false;
        } catch (err) {
            console.error('Error checking cache:', err);
            return false;
        }
    };
    
    let attempts = 0;
    const maxAttempts = 20;
    
    const pollCache = setInterval(async () => {
        attempts++;
        
        const ready = await checkCacheReady();
        
        if (ready) {
            clearInterval(pollCache);
        } else if (attempts >= maxAttempts) {
            clearInterval(pollCache);
            console.log('Cache check timeout - marking as complete');
            loadingManager.completeTask('cache', 'Cache ready');
            loadAssets();
        } else {
            console.log(`Caching files... (${attempts}/${maxAttempts}s)`);
            loadingManager.statusText.textContent = `Caching files... ${attempts}/${maxAttempts}s`;
        }
    }, 1000);
}

function loadAssets() {
    loadingManager.setActiveTask('assets', 'Loading resources...');
    
    if (document.readyState === 'complete') {
        loadingManager.completeTask('assets', 'Resources loaded');
    } else {
        window.addEventListener('load', () => {
            loadingManager.completeTask('assets', 'Resources loaded');
        });
    }
}

async function saveAllDataToIndexedDBWithProgress() {
    try {
        console.log('Starting IndexedDB sync...');
        
        await saveUsersToIndexedDB();
        await saveDealersToIndexedDB();
        await saveClientsToIndexedDB();
        await saveTransactionsToIndexedDB();
        await saveStovesToIndexedDB();
        await saveItemsToIndexedDB();
        
        console.log('IndexedDB sync completed!');
        loadingManager.completeTask('db', 'Database synchronized');
    } catch (err) {
        console.error('IndexedDB sync failed:', err);
        loadingManager.failTask('db', 'Database sync failed');
    }
}

window.addEventListener('offline', () => {
    if (loadingManager.shouldShowLoading && !loadingManager.allTasksCompleted) {
        console.log('Connection lost during loading - continuing with cached data');
        loadingManager.statusText.textContent = 'Connection lost - loading cached data...';
    }
});

window.addEventListener('online', () => {
    if (loadingManager.shouldShowLoading && !loadingManager.allTasksCompleted) {
        console.log('Connection restored - continuing downloads...');
        loadingManager.statusText.textContent = 'Connection restored, continuing...';
    }
});

setTimeout(() => {
    if (loadingManager.shouldShowLoading && !loadingManager.allTasksCompleted) {
        console.log('Maximum loading time reached (25s) - forcing completion');
        
        Object.keys(loadingManager.tasks).forEach(taskName => {
            if (!loadingManager.tasks[taskName].completed) {
                loadingManager.completeTask(taskName, 'Auto-completed');
            }
        });
    }
}, 25000);
    </script>

    <!-- IndexedDB Management Script -->
    <script>
// Initialize IndexedDB for Laravel CRMS
function initCRMSDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('CRMSDB', 3);
    
    request.onupgradeneeded = function(event) {
      const db = event.target.result;
      
      // Create users store if it doesn't exist
      if (!db.objectStoreNames.contains('users')) {
        db.createObjectStore('users', { keyPath: 'id' });
      }
      
      // Create dealers store if it doesn't exist
      if (!db.objectStoreNames.contains('dealers')) {
        db.createObjectStore('dealers', { keyPath: 'id' });
      }
      
      // Create clients store if it doesn't exist
      if (!db.objectStoreNames.contains('clients')) {
        db.createObjectStore('clients', { keyPath: 'id' });
      }
      
      // Create stoves store if it doesn't exist
      if (!db.objectStoreNames.contains('stoves')) {
        db.createObjectStore('stoves', { keyPath: 'id' });
      }
      
      // Create items store if it doesn't exist
      if (!db.objectStoreNames.contains('items')) {
        db.createObjectStore('items', { keyPath: 'id' });
      }
    };
    
    request.onsuccess = function(event) {
      resolve(event.target.result);
    };
    
    request.onerror = function(event) {
      reject('IndexedDB error: ' + event.target.error);
    };
  });
}

// Save users to IndexedDB with passwords and debugging
async function saveUsersToIndexedDB() {
  try {
    console.log('📡 Fetching users from API...');
    const response = await fetch('/api/get-users');
    if (!response.ok) throw new Error('HTTP error ' + response.status);
    
    const users = await response.json();
    
    console.log('📥 Fetched users:', users.length);
    console.log('🔐 First user data:', users[0]);
    console.log('🔐 First user has password:', users[0]?.password ? 'YES ✅' : 'NO ❌');
    
    // Handle null values in the data
    const cleanedUsers = users.map(user => {
      const cleanUser = {};
      for (const [key, value] of Object.entries(user)) {
        cleanUser[key] = value === null ? '' : value;
      }
      return cleanUser;
    });
    
    console.log('🧹 Cleaned first user:', cleanedUsers[0]);
    console.log('🔐 Cleaned user has password:', cleanedUsers[0]?.password ? 'YES ✅' : 'NO ❌');
    
    const db = await initCRMSDB();
    const tx = db.transaction('users', 'readwrite');
    const store = tx.objectStore('users');
    
    // Clear existing data
    store.clear();
    
    // Add each user
    cleanedUsers.forEach(user => {
      try {
        store.put(user);
      } catch (err) {
        console.error('Error saving user:', user.id, err);
      }
    });
    
    tx.oncomplete = () => {
      console.log('✅ Users saved to IndexedDB:', cleanedUsers.length, 'records');
      console.log('🔐 Passwords included in sync!');
    };
    
    tx.onerror = (e) => {
      console.error('❌ Transaction error saving users:', e.target.error);
    };
    
  } catch (err) {
    console.error('❌ Error saving users:', err);
  }
}

// Save dealers to IndexedDB with null handling
async function saveDealersToIndexedDB() {
  try {
    console.log('📡 Fetching dealers from API...');
    const response = await fetch('/api/get-dealers');
    if (!response.ok) throw new Error('HTTP error ' + response.status);
    
    const dealers = await response.json();
    console.log('📥 Fetched dealers:', dealers.length);
    
    // Handle null values - convert to empty strings
    const cleanedDealers = dealers.map(dealer => {
      const cleanDealer = {};
      for (const [key, value] of Object.entries(dealer)) {
        cleanDealer[key] = value === null ? '' : value;
      }
      return cleanDealer;
    });
    
    console.log('🧹 Cleaned dealers:', cleanedDealers.length);
    
    const db = await initCRMSDB();
    const tx = db.transaction('dealers', 'readwrite');
    const store = tx.objectStore('dealers');
    
    // Clear existing data
    store.clear();
    
    // Add each dealer
    cleanedDealers.forEach(dealer => {
      try {
        store.put(dealer);
      } catch (err) {
        console.error('Error saving dealer:', dealer.id, err);
      }
    });
    
    tx.oncomplete = () => {
      console.log('✅ Dealers saved to IndexedDB:', cleanedDealers.length, 'records');
    };
    
    tx.onerror = (e) => {
      console.error('❌ Transaction error saving dealers:', e.target.error);
    };
    
  } catch (err) {
    console.error('❌ Error saving dealers:', err);
  }
}

// Save clients to IndexedDB with null handling
async function saveClientsToIndexedDB() {
  try {
    console.log('📡 Fetching clients from API...');
    const response = await fetch('/api/get-clients');
    if (!response.ok) throw new Error('HTTP error ' + response.status);
    
    const clients = await response.json();
    console.log('📥 Fetched clients:', clients.length);
    
    // Handle null values - convert to empty strings
    const cleanedClients = clients.map(client => {
      const cleanClient = {};
      for (const [key, value] of Object.entries(client)) {
        cleanClient[key] = value === null ? '' : value;
      }
      return cleanClient;
    });
    
    console.log('🧹 Cleaned clients:', cleanedClients.length);
    
    const db = await initCRMSDB();
    const tx = db.transaction('clients', 'readwrite');
    const store = tx.objectStore('clients');
    
    // Clear existing data
    store.clear();
    
    // Add each client
    cleanedClients.forEach(client => {
      try {
        store.put(client);
      } catch (err) {
        console.error('Error saving client:', client.id, err);
      }
    });
    
    tx.oncomplete = () => {
      console.log('✅ Clients saved to IndexedDB:', cleanedClients.length, 'records');
    };
    
    tx.onerror = (e) => {
      console.error('❌ Transaction error saving clients:', e.target.error);
    };
    
  } catch (err) {
    console.error('❌ Error saving clients:', err);
  }
}

// Save stoves to IndexedDB with null handling
async function saveStovesToIndexedDB() {
  try {
    console.log('📡 Fetching stoves from API...');
    const response = await fetch('/api/get-stoves');
    if (!response.ok) throw new Error('HTTP error ' + response.status);
    
    const stoves = await response.json();
    console.log('📥 Fetched stoves:', stoves.length);
    
    // Handle null values - convert to empty strings
    const cleanedStoves = stoves.map(stove => {
      const cleanStove = {};
      for (const [key, value] of Object.entries(stove)) {
        cleanStove[key] = value === null ? '' : value;
      }
      return cleanStove;
    });
    
    console.log('🧹 Cleaned stoves:', cleanedStoves.length);
    
    const db = await initCRMSDB();
    const tx = db.transaction('stoves', 'readwrite');
    const store = tx.objectStore('stoves');
    
    // Clear existing data
    store.clear();
    
    // Add each stove
    cleanedStoves.forEach(stove => {
      try {
        store.put(stove);
      } catch (err) {
        console.error('Error saving stove:', stove.id, err);
      }
    });
    
    tx.oncomplete = () => {
      console.log('✅ Stoves saved to IndexedDB:', cleanedStoves.length, 'records');
    };
    
    tx.onerror = (e) => {
      console.error('❌ Transaction error saving stoves:', e.target.error);
    };
    
  } catch (err) {
    console.error('❌ Error saving stoves:', err);
  }
}

// Save items to IndexedDB with null handling and BLOB support
async function saveItemsToIndexedDB() {
  try {
    console.log('📡 Fetching items from API...');
    const response = await fetch('/api/get-items');
    if (!response.ok) throw new Error('HTTP error ' + response.status);
    
    const items = await response.json();
    console.log('📥 Fetched items:', items.length);
    
    // Handle null values and ensure item_image is properly formatted
    const cleanedItems = items.map(item => {
      const cleanItem = {};
      for (const [key, value] of Object.entries(item)) {
        // Convert null to empty string
        if (value === null) {
          cleanItem[key] = '';
        }
        // Keep item_image as base64 string
        else if (key === 'item_image') {
          cleanItem[key] = value;
        }
        else {
          cleanItem[key] = value;
        }
      }
      return cleanItem;
    });
    
    console.log('🧹 Cleaned items:', cleanedItems.length);
    
    const db = await initCRMSDB();
    const tx = db.transaction('items', 'readwrite');
    const store = tx.objectStore('items');
    
    store.clear();
    
    cleanedItems.forEach(item => {
      try {
        store.put(item);
      } catch (err) {
        console.error('Error saving item:', item.id, err);
      }
    });
    
    tx.oncomplete = () => {
      console.log('✅ Items saved to IndexedDB:', cleanedItems.length, 'records');
    };
    
    tx.onerror = (e) => {
      console.error('❌ Transaction error saving items:', e.target.error);
    };
    
  } catch (err) {
    console.error('❌ Error saving items:', err);
  }
}

async function saveAllDataToIndexedDB() {
  console.log('🔄 Starting IndexedDB sync...');
  
  try {
    await saveUsersToIndexedDB();
    await saveDealersToIndexedDB();
    await saveClientsToIndexedDB();
    await saveStovesToIndexedDB();
    await saveItemsToIndexedDB();
    console.log('✅ IndexedDB sync completed!');
  } catch (err) {
    console.error('❌ IndexedDB sync failed:', err);
  }
}

async function getDataFromIndexedDB(storeName) {
  try {
    const db = await initCRMSDB();
    const tx = db.transaction(storeName, 'readonly');
    const store = tx.objectStore(storeName);
    
    return new Promise((resolve, reject) => {
      const request = store.getAll();
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  } catch (err) {
    console.error('Error reading from IndexedDB:', err);
    return [];
  }
}

window.addEventListener('load', saveAllDataToIndexedDB);
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
    window.addEventListener('load', () => {
        const getBasePath = () => {
            const path = window.location.pathname;
            if (path.includes('/crms-risev2/public')) {
                return '/crms-risev2/public';
            }
            return '';
        };
        
        const BASE_PATH = getBasePath();
        const swPath = `${BASE_PATH}/sw.js`;
        const swScope = `${BASE_PATH}/`;
        
        console.log('🔍 PWA Environment:');
        console.log('   SW Path:', swPath);
        console.log('   SW Scope:', swScope);
        
        fetch(swPath)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`sw.js not found at ${swPath}`);
                }
                console.log('✅ sw.js file found');
                
                return navigator.serviceWorker.register(swPath, { 
                    scope: swScope,
                    updateViaCache: 'none'
                });
            })
            .then(registration => {
                console.log('✅ Service Worker registered successfully');
                console.log('   Scope:', registration.scope);
                
                setInterval(() => {
                    registration.update();
                }, 60000);
                
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('🔄 New Service Worker found');
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('✨ New version available!');
                            if (confirm('New version available! Reload to update?')) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                                window.location.reload();
                            }
                        }
                    });
                });
            })
            .catch(error => {
                console.error('❌ Service Worker registration failed:', error);
            });
    });

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

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('🔄 Service Worker controller changed');
        if (!window.reloadTriggered) {
            window.reloadTriggered = true;
            window.location.reload();
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

    Promise.all([
        fetch('https://www.google.com/generate_204', {
            method: 'HEAD',
            mode: 'no-cors',
            cache: 'no-store',
            signal: controller.signal
        }),
        fetch('https://1.1.1.1/cdn-cgi/trace', {
            method: 'GET',
            cache: 'no-store',
            signal: controller.signal
        })
    ])
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
    const getBasePath = () => {
        const path = window.location.pathname;
        if (path.includes('/crms-risev2/public')) {
            return '/crms-risev2/public';
        }
        return '';
    };
    
    // Check authentication
    const currentUserId = localStorage.getItem('current_user_id');
    const offlineUser = localStorage.getItem('offlineUser');
    const isAuthenticated = !!(currentUserId || offlineUser);
    
    const targetPage = isAuthenticated ? 'home.html' : 'login.html';
    const offlinePath = `${getBasePath()}/offline/${targetPage}`;
    
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
