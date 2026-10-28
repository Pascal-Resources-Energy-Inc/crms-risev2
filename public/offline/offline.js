// Shared offline session checks. Opening a page never requires a network request.
(function () {
    'use strict';
    const keys = ['offlineUser', 'current_user_id', 'current_user_role', 'current_user_name', 'current_user_email'];
    const script = document.currentScript;
    const base = new URL('./', script && script.src || new URL('offline/offline.js', window.location.href));
    let initialized = false;
    function clearSession() {
        try { keys.forEach(key => localStorage.removeItem(key)); } catch (_) {}
        // An expired session must not erase an unsent cart or pending transactions.
    }
    function normalize(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
        const id = Number(value.id);
        if (!Number.isSafeInteger(id) || id < 1 || typeof value.role !== 'string' || !value.role.trim()) return null;
        if (value.loginTime) {
            const timestamp = Date.parse(value.loginTime);
            if (!Number.isFinite(timestamp) || Date.now() - timestamp > 24 * 60 * 60 * 1000) return null;
        }
        if (value.offlineExpiresAt && (!Number.isFinite(Date.parse(value.offlineExpiresAt)) || Date.parse(value.offlineExpiresAt) <= Date.now())) return null;
        return Object.assign({}, value, { id, role: value.role.trim(), name: typeof value.name === 'string' ? value.name : 'User', email: typeof value.email === 'string' ? value.email : '' });
    }
    function currentUser() {
        try {
            const saved = localStorage.getItem('offlineUser');
            if (saved) {
                let value;
                try { value = JSON.parse(saved); } catch (_) { clearSession(); return null; }
                const user = normalize(value);
                if (!user) clearSession();
                return user;
            }
            const user = normalize({ id: localStorage.getItem('current_user_id'), role: localStorage.getItem('current_user_role'), name: localStorage.getItem('current_user_name'), email: localStorage.getItem('current_user_email') });
            if (!user) clearSession();
            return user;
        } catch (_) { return null; }
    }
    function saveSession(value) {
        const user = normalize(value);
        if (!user) return false;
        try {
            const safe = { id: user.id, name: user.name, role: user.role, email: user.email, loginTime: new Date().toISOString() };
            if (user.offlineExpiresAt) safe.offlineExpiresAt = user.offlineExpiresAt;
            localStorage.setItem('offlineUser', JSON.stringify(safe));
            ['id', 'role', 'name', 'email'].forEach(key => localStorage.setItem('current_user_' + key, String(safe[key])));
            return true;
        } catch (_) { return false; }
    }
    function go(filename) { const target = new URL(filename, base).href; if (window.location.href !== target) window.location.replace(target); }
    function init() {
        if (initialized) return; initialized = true;
        const page = window.location.pathname.split('/').pop().replace(/\.html$/, ''), user = currentUser();
        if (page === 'login' || page === '') { if (user) go('home.html'); }
        else if (['home', 'dashboard', 'products', 'cart', 'transaction', 'account', 'confirm_order'].includes(page)) {
            if (!user) go('login.html'); else saveSession(user);
        }
    }
    window.getOfflineUser = currentUser;
    window.isOfflineAuthenticated = () => !!currentUser();
    window.saveOfflineSession = saveSession;
    window.getOfflinePageUrl = filename => new URL(filename, base).href;
    window.getOfflineAppUrl = path => new URL(String(path).replace(/^\/+/, ''), new URL('../', base)).href;
    window.logoutOfflineUser = () => {
        const leave = () => { clearSession(); go('login.html'); };
        if (typeof Swal !== 'undefined') Swal.fire({ title: 'Logout', text: 'Log out on this device?', icon: 'question', showCancelButton: true, confirmButtonText: 'Logout' }).then(result => { if (result.isConfirmed) leave(); });
        else if (confirm('Log out on this device?')) leave();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
